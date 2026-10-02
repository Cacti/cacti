<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Remember-me session hardening: the nopassword change path must revoke the
 * server-side remember-me row, not just the browser cookie (GHSA-cg45), and
 * the remember-me token check must enforce a retention window instead of
 * trusting the cron purge alone (GHSA-xq26).
 */

$root = dirname(__DIR__, 4);

test('nopassword change revokes the server-side remember-me row (GHSA-cg45)', function () use ($root) {
	$s = file_get_contents($root . '/auth_changepassword.php');
	expect($s)->toContain('clear_auth_cookie()');
});

test('remember-me token check enforces a retention window (GHSA-xq26)', function () use ($root) {
	$s = file_get_contents($root . '/lib/auth.php');
	expect($s)->toContain('AND last_update >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
});
