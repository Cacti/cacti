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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/CactiProcess.php';

test('run captures stdout and exits zero for echo', function () {
	$result = CactiProcess::run(['echo', 'hello']);

	expect($result->exitCode())->toBe(0)
		->and($result->stdout())->toStartWith('hello')
		->and($result->outputLines())->toContain('hello');
});

test('run throws CactiProcessException on timeout', function () {
	expect(static function () {
		CactiProcess::run(['sleep', '5'], ['timeout' => 1]);
	})->toThrow(CactiProcessException::class);
});

test('run throws when exit code is not in expected list', function () {
	// /usr/bin/false is the canonical "exit 1" binary on POSIX systems.
	expect(static function () {
		CactiProcess::run(['false']);
	})->toThrow(CactiProcessException::class);
});

test('run accepts non-zero exit when expected_exit_codes allows it', function () {
	$result = CactiProcess::run(['false'], ['expected_exit_codes' => [0, 1]]);

	expect($result->exitCode())->toBe(1);
});

test('argv mode prevents shell metachar expansion', function () {
	/*
	 * If Symfony Process were quoting through /bin/sh, $(true) would be
	 * substituted to an empty string. In array-mode the literal token survives
	 * intact, which is the security property the wrapper exists to guarantee.
	 */
	$result = CactiProcess::run(['echo', '$(true)']);

	expect($result->exitCode())->toBe(0)
		->and($result->stdout())->toContain('$(true)');
});

test('outputLines splits multi-line stdout and drops trailing empty', function () {
	$result = CactiProcess::run(['/bin/sh', '-c', 'printf "a\nb\nc\n"']);

	expect($result->exitCode())->toBe(0)
		->and($result->outputLines())->toBe(['a', 'b', 'c']);
});

test('child process cannot read parent env vars outside the allowlist', function () {
	/*
	 * The wrapper's central security claim is that subprocesses see only
	 * the baseline env (PATH, HOME, LANG, TZ) plus any names the caller
	 * explicitly passes through. A reviewer can verify the contract by
	 * setting a fresh secret in this process and confirming /usr/bin/env
	 * does not echo it back from the child.
	 */
	$marker = 'CACTIPROC_LEAK_PROBE_' . bin2hex(random_bytes(6));
	putenv($marker . '=should-not-leak');

	try {
		$result = CactiProcess::run(['/usr/bin/env']);

		expect($result->exitCode())->toBe(0)
			->and($result->stdout())->not->toContain($marker)
			->and($result->stdout())->not->toContain('should-not-leak');
	} finally {
		putenv($marker);
	}
});

test('caller can opt a parent env var into the child via env allowlist', function () {
	$marker = 'CACTIPROC_OPTIN_' . bin2hex(random_bytes(6));
	putenv($marker . '=carried-through');

	try {
		$result = CactiProcess::run(['/usr/bin/env'], ['env' => [$marker]]);

		expect($result->exitCode())->toBe(0)
			->and($result->stdout())->toContain($marker . '=carried-through');
	} finally {
		putenv($marker);
	}
});

test('Windows env baseline preserves SYSTEMROOT, COMSPEC, PATHEXT, WINDIR', function () {
	// PHP refuses to start without SYSTEMROOT on Windows and proc_open()
	// relies on COMSPEC/PATHEXT for cmd.exe resolution. Source-scan so the
	// branch can be verified on a Linux CI without standing up Windows.
	$source = file_get_contents(__DIR__ . '/../../lib/CactiProcess.php');

	expect($source)->toContain("PHP_OS_FAMILY === 'Windows'");

	foreach (['SYSTEMROOT', 'COMSPEC', 'PATHEXT', 'WINDIR'] as $name) {
		expect($source)->toContain("'" . $name . "'");
	}
});
