<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 'SNMP');
}

if (!function_exists('is_hex_string')) {
	function is_hex_string(&$value) {
		return false;
	}
}

if (!function_exists('cacti_snmp_validate_oid')) {
	$config = array(
		'php_snmp_support' => false,
		'include_path'     => dirname(__DIR__, 4) . '/include',
	);

	require_once dirname(__DIR__, 4) . '/lib/snmp.php';
}

test('numeric OIDs are accepted', function ($oid) {
	expect(cacti_snmp_validate_oid($oid))->toBeTrue();
})->with(array('1', '.1.3.6.1', '0.0.4294967295'));

test('non-canonical or non-integer OID components are rejected', function ($oid) {
	expect(cacti_snmp_validate_oid($oid))->toBeFalse();
})->with(array(
	'', '.', '1.', '1..3', '1e2.3', '+1.3', '-1.3', ' 1.3', '1.3 ', '01.3', "1.3\n4",
));
