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

$root = dirname(__DIR__, 2);

test('the composer autoloader is required before include/csrf.php', function () use ($root) {
	$src = file_get_contents($root . '/include/global.php');

	$autoload = strpos($src, 'require_once($vendor_autoload);');
	$csrf     = strpos($src, "require_once(CACTI_PATH_INCLUDE . '/csrf.php');");

	expect($autoload)->not->toBeFalse();
	expect($csrf)->not->toBeFalse();
	expect($autoload)->toBeLessThan($csrf);
});
