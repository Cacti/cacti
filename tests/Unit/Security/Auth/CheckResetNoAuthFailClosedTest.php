<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-69vw-qxxv-p8w2: check_reset_no_authentication() runs pre-session on every
 * page load (include/auth.php). It used to "recover" the deprecated No
 * Authentication mode by wiping the admin password and handing the triggering -
 * still unauthenticated - request a full admin session, so the first visitor to
 * a legacy auth_method=0 instance became admin and locked the real admin out.
 * It must now fail closed: no session grant, no credential mutation, just an
 * error that requires an administrator to reset auth_method manually.
 */

$root = dirname(__DIR__, 4);

// Pull the function verbatim from lib/auth.php so the assertions track shipped code.
$extract = static function (string $source, string $name): string {
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		throw new RuntimeException($name . '() not found');
	}

	$depth = 0;
	$len   = strlen($source);

	for ($i = strpos($source, '{', $start); $i < $len; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	throw new RuntimeException($name . '() is unbalanced');
};

$body = $extract(file_get_contents($root . '/lib/auth.php'), 'check_reset_no_authentication');

test('check_reset_no_authentication never establishes a session', function () use ($body) {
	expect($body)->not->toContain('$_SESSION[SESS_USER_ID]')
		->and($body)->not->toContain('$_SESSION[SESS_CHANGE_PASSWORD]');
});

test('check_reset_no_authentication never mutates credentials or auto-redirects', function () use ($body) {
	expect($body)->not->toContain("password = ''")
		->and($body)->not->toContain('auth_changepassword.php?action=force');
});

test('check_reset_no_authentication fails closed with an error on auth_method None', function () use ($body) {
	expect($body)->toContain('AUTH_METHOD_NONE')
		->and($body)->toContain('auth_display_custom_error_message')
		->and($body)->toContain('exit;');
});
