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

/*
 * csrf_guard() degrades to a disabled guard when Symfony is absent, which is
 * intended: an install running before composer populates include/vendor must
 * not fatal. In a web context that branch is only reachable under the
 * pre-auth installer, and it leaves the request with no CSRF check at all --
 * something csrf-magic could not do. Degrading silently is the part that is
 * not acceptable.
 */
test('the fail-open branch is recorded rather than silent', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	$start = strpos($src, 'function csrf_guard(');
	expect($start)->not->toBeFalse();

	$end  = strpos($src, "\nfunction ", $start + 1);
	$body = substr($src, $start, ($end === false ? strlen($src) : $end) - $start);

	$disable = strpos($body, 'new CactiCsrfGuard(null, false)');
	$warn    = strpos($body, 'cacti_log(');

	expect($disable)->not->toBeFalse()
		->and($warn)->not->toBeFalse()
		->and($warn)->toBeLessThan($disable);

	// the CLI takes the same branch every run and must not log on every run
	expect($body)->toContain('if (CACTI_WEB) {');
});

/*
 * Retiring the fork took include/vendor/csrf/ with it, and that is where
 * path_csrf_secret has always defaulted. The installer used to demand the path
 * be writable: getPermissions() raised a STEP_PERMISSION_CHECK error, and
 * setStep() clamps the current step down to the first errored step, so a fresh
 * install could not advance past a directory the user has no way to create.
 *
 * The default is deliberately left pointing at the old location. Upgrades read
 * it for the grace window, and moving it would strand every existing install.
 */
test('the installer does not gate on the retired secret path', function () use ($root) {
	$installer = file_get_contents($root . '/lib/installer.php');

	expect($installer)->not->toContain("\$install_paths['csrf']")
		->and($installer)->not->toContain("if (\$name == 'csrf')");
});

test('the installer skips the secret when its directory is absent', function () use ($root) {
	$installer = file_get_contents($root . '/lib/installer.php');

	$start = strpos($installer, 'private function setCSRFSecret(');
	expect($start)->not->toBeFalse();

	$end  = strpos($installer, "\n\t/**", $start + 1);
	$body = substr($installer, $start, ($end === false ? strlen($installer) : $end) - $start);

	$guard  = strpos($body, 'is_dir(dirname(CACTI_CSRF_SECRET))');
	$create = strpos($body, 'install_create_csrf_secret(');

	expect($guard)->not->toBeFalse()
		->and($create)->not->toBeFalse()
		->and($guard)->toBeLessThan($create);
});

test('the default secret path is unchanged so upgrades still find it', function () use ($root) {
	$global = file_get_contents($root . '/include/global.php');

	expect($global)->toContain("CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-secret.php'");
});
