<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * 1.2.x backport of the batch-7 develop security fixes (the eight that were not
 * already closed on 1.2.x). Each assertion pins the 1.2.x fix so it cannot
 * regress: f7jw (automation preview XSS), vx2m (automation pivot CASE WHEN),
 * 929h (aggregate LIKE filter), mpfm (rrd error-image theme allowlist), m3fh
 * (SNMPv3 control chars), cg45 (remember-me row revoke), xq26 (remember-me
 * retention window), m67r (import xml_path containment).
 */

$root = dirname(__DIR__, 4);

test('automation Resulting Branch values are escaped (GHSA-f7jw)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_automation.php');
	expect($s)->toContain('html_escape(array_shift($replacement))');
});

test('automation pivot builders escape field_name (GHSA-vx2m)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_automation.php');
	expect($s)->not->toContain("CASE WHEN field_name='")
		->and($s)->not->toContain("CASE WHEN field_name ='")
		->and($s)->toContain('CASE WHEN field_name = " . db_qstr(');
});

test('aggregate LIKE filter term is quoted (GHSA-929h)', function () use ($root) {
	$s = file_get_contents($root . '/aggregate_graphs.php');
	expect($s)->toContain("db_qstr('%'")
		->and($s)->not->toContain("LIKE '%\" . trim(\$i)");
});

test('rrdtool_create_error_image validates the theme (GHSA-mpfm)', function () use ($root) {
	$s = file_get_contents($root . '/lib/rrd.php');
	expect($s)->toContain('cacti_validate_theme(get_selected_theme())');
});

test('SNMPv3 credential fields strip control characters (GHSA-m3fh)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_device.php');
	expect($s)->toContain('[\x00-\x1f\x7f]');
});

test('nopassword change revokes the server-side remember-me row (GHSA-cg45)', function () use ($root) {
	$s = file_get_contents($root . '/auth_changepassword.php');
	expect($s)->toContain('clear_auth_cookie()');
});

test('remember-me token check enforces a retention window (GHSA-xq26)', function () use ($root) {
	$s = file_get_contents($root . '/lib/auth.php');
	expect($s)->toContain('AND last_update >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
});

test('imported data-query xml_path is confined to the Cacti tree (GHSA-m67r)', function () use ($root) {
	$s = file_get_contents($root . '/lib/import.php');
	expect($s)->toContain('$contained')
		->and($s)->toContain('realpath($config[');
});
