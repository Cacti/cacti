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
 * Tests for XSS fix in auth_resetpassword.php.
 *
 * The hash value in hidden form inputs was output without escaping. The fix
 * wraps $_REQUEST['hash'] with htmlerv() to prevent XSS when the hash is reflected.
 */

$authPath = __DIR__ . '/../../auth_resetpassword.php';

// --- auth_resetpassword.php: hash escaped in output ---

test('auth_resetpassword.php escapes hash with htmlerv', function () use ($authPath) {
	$contents = file_get_contents($authPath);

	expect($contents)->toContain("htmlerv('hash')");
});
