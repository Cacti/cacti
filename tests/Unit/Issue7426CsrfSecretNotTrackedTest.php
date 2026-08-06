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
 * include/vendor/csrf/csrf-secret.php holds the secret every CSRF token is
 * derived from. csrf_get_secret() only generates one when the file is missing
 * or empty, so a secret committed to the repository is never replaced: every
 * install built from that tree shares it, and anyone who reads the repository
 * can mint tokens for all of them.
 *
 * .gitignore has always listed the path, but the file was committed anyway and
 * an ignore rule does not apply to something already in the index. These pin
 * both halves so it cannot drift back.
 */

/**
 * Runs a git command in the repository, or skips the test where there is no
 * checkout to ask (a release tarball, for instance).
 *
 * @param string $arguments The git arguments to run.
 *
 * @return array{output: string, status: int} The trimmed output and exit status.
 */
function csrf_secret_git(string $arguments) : array {
	$root = dirname(__DIR__, 2);

	/* In a worktree or a submodule .git is a file pointing at the real gitdir,
	   so testing for a directory would skip where tracking is inspectable. */
	if (!file_exists($root . '/.git')) {
		test()->markTestSkipped('not a git checkout, so tracking cannot be inspected');
	}

	$output = [];
	$status = 0;

	exec('git -C ' . escapeshellarg($root) . ' ' . $arguments . ' 2>/dev/null', $output, $status);

	return ['output' => trim(implode("\n", $output)), 'status' => $status];
}

test('the csrf secret is not tracked in the repository', function () {
	$tracked = csrf_secret_git('ls-files --error-unmatch include/vendor/csrf/csrf-secret.php');

	// a zero status means git knows the file, which is the bug
	expect($tracked['status'])->not->toBe(0)
		->and($tracked['output'])->toBe('');
});

test('no file under the csrf vendor directory holds a bare secret', function () {
	$tracked = csrf_secret_git('ls-files include/vendor/csrf/');

	expect($tracked['output'])->not->toBe('');

	foreach (explode("\n", $tracked['output']) as $file) {
		$contents = trim((string) file_get_contents(dirname(__DIR__, 2) . '/' . $file));

		// the secret is written as a bare 40 character hex digest, no php tags
		expect($contents)->not->toMatch('/^[0-9a-f]{40}$/');
	}
});

test('the secret path is still ignored so it cannot be committed again', function () {
	$gitignore = file_get_contents(dirname(__DIR__, 2) . '/.gitignore');

	expect($gitignore)->toContain('include/vendor/csrf/csrf-secret.php');
});

/**
 * Removing the file is only safe because csrf-magic.php writes a fresh secret
 * the first time it finds none, so this pins the generation path rather than
 * assuming it.
 */
test('csrf-magic still generates and stores a secret when none exists', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/include/vendor/csrf/csrf-magic.php');
	expect($source)->not->toBeFalse('csrf-magic.php must be readable');

	$start = strpos($source, 'function csrf_get_secret(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);

	expect($body)->toContain('csrf_generate_secret()')
		->and($body)->toContain('csrf_writable(')
		->and($body)->toContain('fwrite(');
});
