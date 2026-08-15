<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The procedural csrf_* functions are a deprecated compatibility surface.
 * Third-party plugins call them and the installer calls them, so they must
 * keep resolving and keep delegating to the guard until phase 2 retires them.
 */

$root = dirname(__DIR__, 2);

test('the vendored csrf-magic fork is gone', function () use ($root) {
	expect(is_dir($root . '/include/vendor/csrf'))->toBeFalse();
});

test('include/csrf.php no longer loads the fork', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	expect($src)->not->toContain('csrf-magic.php');
	expect($src)->not->toContain('csrf-conf.php');
});

test('the deprecated procedural surface still exists', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	foreach (['csrf_guard', 'csrf_get_tokens', 'csrf_check', 'csrf_check_tokens', 'csrf_conf', 'csrf_startup', 'csrf_error_callback', 'csrf_get_secret', 'csrf_generate_secret', 'csrf_writable', 'csrf_log'] as $fn) {
		expect($src)->toContain('function ' . $fn . '(');
	}
});

test('the installer error branch is preserved verbatim', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	expect($src)->toContain('csrf_timeout');
	expect($src)->toContain('IN_CACTI_INSTALL');
	expect($src)->toContain('session_regenerate_id');
});

/**
 * Collect the csrf_* functions a file actually calls.
 *
 * Tokenising rather than matching text keeps prose out of the result;
 * cli/refresh_csrf.php names csrf_secret() in its help output, which a regex
 * over the raw source reports as a call to a function that never existed.
 *
 * @param string $file The PHP file to scan.
 *
 * @return array<int, string> The called function names, without duplicates.
 */
function csrf_facade_called_functions(string $file) : array {
	$tokens = token_get_all((string) file_get_contents($file));
	$found  = [];

	foreach ($tokens as $index => $token) {
		if (!is_array($token) || $token[0] !== T_STRING || strpos($token[1], 'csrf_') !== 0) {
			continue;
		}

		// a declaration, a method call or a static call is not a plain call
		for ($back = $index - 1; $back >= 0; $back--) {
			$previous = $tokens[$back];

			if (is_array($previous) && ($previous[0] === T_WHITESPACE || $previous[0] === T_COMMENT || $previous[0] === T_DOC_COMMENT)) {
				continue;
			}

			if (is_array($previous) && in_array($previous[0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true)) {
				continue 2;
			}

			break;
		}

		for ($ahead = $index + 1; $ahead < count($tokens); $ahead++) {
			$next = $tokens[$ahead];

			if (is_array($next) && $next[0] === T_WHITESPACE) {
				continue;
			}

			if ($next === '(') {
				$found[] = $token[1];
			}

			break;
		}
	}

	return array_values(array_unique($found));
}

/*
 * csrf-magic defined helpers that outlive it: the installer writes the secret
 * and the refresh CLI rotates it.  Deleting the fork without porting these
 * fatals a fresh install.
 */
test('every surviving csrf_ caller still resolves', function () use ($root) {
	$callers = [
		$root . '/install/functions.php',
		$root . '/cli/refresh_csrf.php',
		$root . '/pollers.php',
		$root . '/include/global_session.php',
		$root . '/install/step_json.php',
		$root . '/include/global.php',
	];

	$facade = file_get_contents($root . '/include/csrf.php');
	$lib    = file_get_contents($root . '/lib/csrf.php');
	$seen   = 0;

	foreach ($callers as $caller) {
		foreach (csrf_facade_called_functions($caller) as $fn) {
			$seen++;

			expect($facade . $lib)->toContain('function ' . $fn . '(');
		}
	}

	// a scanner that silently matches nothing would pass every assertion above
	expect($seen)->toBeGreaterThan(0);
});

test('the script url points at the moved asset', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	expect($src)->toContain('include/js/csrf.js');
	expect($src)->not->toContain('include/vendor/csrf/csrf-magic.js');
});
