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
	expect($s)->toContain('html_escape((string) array_shift($replacement))');
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

	// Every credential field that reaches the net-snmp command line must be stripped, including snmp_engine_id (-e).
	foreach (['snmp_community', 'snmp_username', 'snmp_password', 'snmp_priv_passphrase', 'snmp_context', 'snmp_engine_id'] as $field) {
		expect($s)->toContain("preg_replace('/[\\x00-\\x1f\\x7f]/', '', form_input_validate(\$$field");
	}
});

test('nopassword change revokes the server-side remember-me row (GHSA-cg45)', function () use ($root) {
	$s = file_get_contents($root . '/auth_changepassword.php');
	expect($s)->toContain('clear_auth_cookie()');
});

test('remember-me token check enforces a retention window (GHSA-xq26)', function () use ($root) {
	$s = file_get_contents($root . '/lib/auth.php');
	expect($s)->toContain('AND last_update >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
});

test('imported data-query xml_path is confined by a lexical gate before canonicalization (GHSA-m67r)', function () use ($root) {
	$s = file_get_contents($root . '/lib/import.php');

	// The lexical prefix/traversal gate must exist...
	expect($s)->toContain('$lexically_contained')
		->and($s)->toContain("strpos(\$path_norm, \$base_norm . '/') === 0")
		->and($s)->toContain("strpos(\$path_norm, '/../') === false");

	// ...and it must run before realpath() dereferences the attacker path, so a UNC path cannot be probed first.
	$gate_pos     = strpos($s, '$lexically_contained =');
	$realpath_pos = strpos($s, 'realpath($path)');

	expect($gate_pos)->not->toBeFalse()
		->and($realpath_pos)->not->toBeFalse()
		->and($gate_pos)->toBeLessThan($realpath_pos);
});
