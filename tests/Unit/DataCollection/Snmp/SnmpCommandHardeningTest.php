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

$snmpSource = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');

test('binary SNMP execution uses discrete argv arrays', function () use ($snmpSource) {
	expect($snmpSource)
		->not->toContain('exec($command')
		->not->toContain('exec_into_array($command')
		->not->toContain("'SNMP Command is: %s'")
		->toContain('cacti_get_snmp_auth_args(')
		->toContain('cacti_exec($binary, $args');
});

test('SNMPv3 values remain single argv elements', function () {
	global $snmp_auth_protocols, $snmp_priv_protocols;

	$snmp_auth_protocols = array('SHA' => 'SHA');
	$snmp_priv_protocols = array('AES' => 'AES');
	$payload = 'value" & whoami | value';
	$args = cacti_get_snmpv3_auth_args('SHA', $payload, $payload, 'AES', $payload, $payload, $payload);

	expect($args)->toContain($payload)
		->and(array_count_values($args)[$payload])->toBe(5)
		->and($args)->toContain('-u', '-A', '-X', '-n', '-e');
});

test('SNMP response type prefixes are removed only at the start', function () {
	expect(format_snmp_string('STRING: device-string:value', false))->toBe('device-string:value')
		->and(format_snmp_string('device-string:value', false))->toBe('device-string:value');
});

test('binary targets remain one argv element', function () {
	expect(snmp_format_target_arg('192.0.2.1', 161))->toBe('192.0.2.1:161')
		->and(snmp_format_target_arg('2001:db8::1', 1161))->toBe('udp6:[2001:db8::1]:1161');
});

test('legacy Windows shell quoting fails closed on metacharacters', function () {
	global $config;

	$config['cacti_server_os'] = 'win32';
	expect(snmp_escape_string('ordinary'))->toBe('"ordinary"')
		->and(snmp_escape_string('value" & whoami'))->toBe('')
		->and(snmp_escape_string('%PATH%'))->toBe('');
});
