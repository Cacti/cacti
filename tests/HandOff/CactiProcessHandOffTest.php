<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Hand-off tests for CactiProcess.
 *
 * Where CactiProcessTest.php exercises the wrapper's public surface, this
 * suite isolates each boundary the data crosses on its way to and from the
 * child: argv into execve, parent env into the child env, child stdout bytes
 * back into PHP strings, child exit code back into CactiProcessResult, the
 * exec()-shaped backward-compat helper, and the timeout exception chain.
 *
 * Each test pins one boundary so a regression in any single hop is loud.
 */

if (!file_exists(dirname(__DIR__, 2) . '/lib/CactiProcess.php')) {
	test('CactiProcess hand-off: feature not present on this branch', function () {})
		->skip('lib/CactiProcess.php absent — feature PR #7073 not merged into develop yet');
	return;
}

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/CactiProcess.php';

test('argv array reaches child as literal args without shell expansion', function () {
	/*
	 * /usr/bin/env with no args prints the env, so we instead use it as a
	 * "echo every argv element on its own line" by piping argv through
	 * printf inside a controlled shell-free invocation. We pick printf
	 * directly so there is no /bin/sh layer to blame for substitution.
	 */
	$payload = '$(uname);`whoami`;${HOME};*;|';
	$result  = CactiProcess::run(['printf', '%s\n', $payload]);

	expect($result->exitCode())->toBe(0)
		->and($result->stdout())->toContain($payload);
});

test('env allowlist excludes parent secret from child env', function () {
	$marker = 'CACTIPROC_HANDOFF_SECRET_' . bin2hex(random_bytes(6));
	putenv($marker . '=top-secret-value');

	try {
		$result = CactiProcess::run(['/usr/bin/env']);

		expect($result->exitCode())->toBe(0)
			->and($result->stdout())->not->toContain($marker)
			->and($result->stdout())->not->toContain('top-secret-value');
	} finally {
		putenv($marker);
	}
});

test('env allowlist forwards parent variable verbatim when opted in', function () {
	$marker = 'CACTIPROC_HANDOFF_OPTIN_' . bin2hex(random_bytes(6));
	$value  = 'verbatim value with spaces and = signs';
	putenv($marker . '=' . $value);

	try {
		$result = CactiProcess::run(['/usr/bin/env'], ['env' => [$marker]]);

		expect($result->exitCode())->toBe(0)
			->and($result->stdout())->toContain($marker . '=' . $value);
	} finally {
		putenv($marker);
	}
});

test('child stdout bytes are preserved byte-for-byte including multibyte UTF-8', function () {
	/*
	 * Cyrillic, CJK, and an emoji in one payload. printf %s leaves the bytes
	 * intact, so any corruption (re-encoding, locale folding, stream
	 * filters) shows up as a string mismatch.
	 */
	$payload = "\xD0\xBF\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82-\xE6\x97\xA5\xE6\x9C\xAC\xE8\xAA\x9E-\xF0\x9F\x9A\x80";
	$result  = CactiProcess::run(['printf', '%s', $payload]);

	expect($result->exitCode())->toBe(0)
		->and($result->stdout())->toBe($payload);
});

test('child exit code surfaces through CactiProcessResult exitCode', function () {
	$result = CactiProcess::run(
		['/bin/sh', '-c', 'exit 7'],
		['expected_exit_codes' => [0, 7]]
	);

	expect($result->exitCode())->toBe(7);
});

test('outputLines matches the array shape exec() callers expect', function () {
	/*
	 * exec($cmd, $output) populates $output with one element per line and
	 * does NOT include the trailing empty string from the final newline.
	 * outputLines() must match that shape so the migration is mechanical.
	 */
	$cmd       = '/bin/sh -c \'printf "alpha\nbeta\ngamma\n"\'';
	$exec_out  = [];
	$exec_exit = 0;
	exec($cmd, $exec_out, $exec_exit);

	$result = CactiProcess::run(['/bin/sh', '-c', 'printf "alpha\nbeta\ngamma\n"']);

	expect($result->exitCode())->toBe($exec_exit)
		->and($result->outputLines())->toBe($exec_out);
});

test('timeout throws CactiProcessException carrying ProcessTimedOutException as previous', function () {
	try {
		CactiProcess::run(['sleep', '5'], ['timeout' => 1]);
		$this->fail('expected CactiProcessException to be thrown on timeout');
	} catch (CactiProcessException $e) {
		expect($e->getPrevious())
			->toBeInstanceOf(\Symfony\Component\Process\Exception\ProcessTimedOutException::class);
	}
});
