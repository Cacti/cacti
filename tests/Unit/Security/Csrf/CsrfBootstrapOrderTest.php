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
 * CactiCsrfGuard resolves Symfony classes while include/csrf.php is being
 * evaluated, so the Composer autoloader has to be registered first. This is an
 * include-ordering property of a single file; there is no runtime seam to
 * assert against, so the source order is the assertion.
 */

$root = dirname(__DIR__, 4);

test('the composer autoloader is required before include/csrf.php', function () use ($root) {
	$src = file_get_contents($root . '/include/global.php');

	$autoload = strpos($src, 'require_once($vendor_autoload);');
	$csrf     = strpos($src, "require_once(CACTI_PATH_INCLUDE . '/csrf.php');");

	expect($autoload)->not->toBeFalse();
	expect($csrf)->not->toBeFalse();
	expect($autoload)->toBeLessThan($csrf);
});

/*
 * csrf-magic validated the request from the bottom of its own file, so merely
 * including it protected every POST. Nothing does that implicitly now: if this
 * call goes missing, every page still renders and every form still carries a
 * token, but no submission is ever checked.
 */
test('include/global.php invokes the guard after loading it', function () use ($root) {
	$src = file_get_contents($root . '/include/global.php');

	$include = strpos($src, "require_once(CACTI_PATH_INCLUDE . '/csrf.php');");
	$startup = strpos($src, 'csrf_startup();');

	expect($startup)->not->toBeFalse()
		->and($include)->not->toBeFalse()
		->and($include)->toBeLessThan($startup);
});

/*
 * include/session.php registers session_write_close() as a shutdown
 * function, and shutdown functions run before output buffers flush. A token
 * minted lazily from inside the ob_start() callback would therefore write
 * $_SESSION after the session already closed on any request where that
 * callback runs the first mint of the request. Exercising this end to end
 * would require a real shutdown-function pass, which Pest cannot simulate
 * mid-test, so this asserts the fix at the source level: csrf_startup()
 * must call token() before it registers the output handler.
 */
test('csrf_startup mints a token before registering the output handler', function () use ($root) {
	$src = file_get_contents($root . '/include/csrf.php');

	$start = strpos($src, 'function csrf_startup(');
	expect($start)->not->toBeFalse();

	$end  = strpos($src, "\nfunction ", $start + 1);
	$body = substr($src, $start, ($end === false ? strlen($src) : $end) - $start);

	$token   = strpos($body, '$guard->token();');
	$obStart = strpos($body, 'ob_start(');

	expect($token)->not->toBeFalse()
		->and($obStart)->not->toBeFalse()
		->and($token)->toBeLessThan($obStart);
});
