<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioural coverage for cacti_exec_log_describe(), the credential-redacting
 * formatter used on cacti_exec() failure paths. The contract is that an SNMP
 * secret never reaches the log in either the split (-c secret) or attached
 * (-csecret) form, that non-SNMP argv is passed through verbatim, and that the
 * hostname the exec applies to (IPv4, DNS name, plain IPv6 or zone-scoped IPv6)
 * is resolved into the Device[id] prefix. The hostname is passed in by the
 * caller, it is not guessed from the Net-SNMP target argument.
 *
 * This suite's bootstrap is vendor/autoload.php only, so db_fetch_cell_prepared()
 * is not defined by Cacti here. A deterministic stub below maps a handful of
 * known hostnames to device ids, which lets the Device[id] resolution path run
 * without a database and makes an unresolved host observable as a missing prefix.
 */

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = array()) {
		$map = array(
			'10.1.2.3'           => 42,
			'router.example.com' => 11,
			'2001:db8::1'        => 7,
			'fe80::1%eth0'       => 99,
		);

		$host = isset($params[0]) ? $params[0] : '';

		return isset($map[$host]) ? $map[$host] : false;
	}
}

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
});

test('split-form SNMP community is redacted and the device resolves', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-OfntevU', '-c', 'public', '-v', '2c', '-t', '1', '-r', '3', '10.1.2.3:161', '.1.3.6.1.2.1.1.3.0'
	), '10.1.2.3');

	expect($out)->toStartWith('Device[42] ')
		->and($out)->toContain('-c [REDACTED]')
		->and($out)->not->toContain('public')
		->and($out)->toContain('10.1.2.3:161')
		->and($out)->toContain('.1.3.6.1.2.1.1.3.0');
});

test('attached-form SNMP community is redacted', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-cpublic', '-v', '2c', '10.1.2.3:161', '.1.3.6.1.2.1.1.3.0'
	), '10.1.2.3');

	expect($out)->toContain('-c[REDACTED]')
		->and($out)->not->toContain('public')
		->and($out)->toStartWith('Device[42] ');
});

test('attached-form v3 auth and priv passphrases are redacted while protocol flags survive', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '3', '-l', 'authPriv', '-u', 'cactiuser',
		'-a', 'SHA', '-AsuperSecretAuth', '-x', 'AES', '-XsuperSecretPriv',
		'10.1.2.3:161', '.1.3.6.1.2.1.1.3.0'
	));

	expect($out)->toContain('-A[REDACTED]')
		->and($out)->toContain('-X[REDACTED]')
		->and($out)->not->toContain('superSecretAuth')
		->and($out)->not->toContain('superSecretPriv')
		->and($out)->toContain('-a SHA')
		->and($out)->toContain('-x AES');
});

test('non-SNMP argv is passed through verbatim with no redaction or device prefix', function () {
	$args = array('graph', '-', '--imgformat=PNG', 'DEF:a=/var/lib/cacti/rra/x.rrd:traffic_in:AVERAGE');
	$out  = cacti_exec_log_describe('/usr/bin/rrdtool', $args);

	expect($out)->toBe('/usr/bin/rrdtool ' . implode(' ', $args))
		->and($out)->not->toContain('[REDACTED]')
		->and($out)->not->toContain('Device[');
});

test('a DNS hostname resolves while its host:port target is logged unchanged', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '2c', '-c', 'private', 'router.example.com:16161', '.1.3.6.1.2.1.1.1.0'
	), 'router.example.com');

	expect($out)->toStartWith('Device[11] ')
		->and($out)->toContain('router.example.com:16161')
		->and($out)->not->toContain('private');
});

test('a plain IPv6 hostname resolves while its bracketed udp6 target is logged unchanged', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '2c', '-c', 'public', 'udp6:[2001:db8::1]:161', '.1.3.6.1.2.1.1.3.0'
	), '2001:db8::1');

	expect($out)->toStartWith('Device[7] ')
		->and($out)->toContain('udp6:[2001:db8::1]:161')
		->and($out)->not->toContain('public');
});

test('a bracketed IPv6 hostname, as the binary SNMP paths pass it, is looked up without its transport brackets', function () {
	$hostname = cacti_format_ipv6_colon('2001:db8::1');

	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '2c', '-c', 'public', 'udp6:[2001:db8::1]:161', '.1.3.6.1.2.1.1.3.0'
	), $hostname);

	expect($hostname)->toBe('[2001:db8::1]')
		->and($out)->toStartWith('Device[7] ')
		->and($out)->toContain('udp6:[2001:db8::1]:161')
		->and(cacti_exec_host_id('[fe80::1%eth0]'))->toBe(99)
		->and(cacti_exec_host_id('[2001:db8::99]'))->toBe(0);
});

test('zone-scoped IPv6 keeps its zone id, resolves and still redacts the secret', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-cpublic', 'udp6:[fe80::1%eth0]:161', '.1.3.6.1.2.1.1.3.0'
	), 'fe80::1%eth0');

	expect($out)->toStartWith('Device[99] ')
		->and($out)->toContain('udp6:[fe80::1%eth0]:161')
		->and($out)->toContain('-c[REDACTED]')
		->and($out)->not->toContain('public');
});

test('the device is not guessed from the target argument when no hostname is passed', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '2c', '-c', 'public', '10.1.2.3:161', '.1.3.6.1.2.1.1.3.0'
	));

	expect($out)->not->toContain('Device[')
		->and($out)->toStartWith('/usr/bin/snmpget ')
		->and($out)->not->toContain('public');
});

test('a hostname that is not a device has no device prefix', function () {
	$out = cacti_exec_log_describe('/usr/bin/snmpget', array(
		'-v', '2c', '-c', 'public', '192.0.2.200:161', '.1.3.6.1.2.1.1.3.0'
	), '192.0.2.200');

	expect($out)->not->toContain('Device[')
		->and($out)->toStartWith('/usr/bin/snmpget ');
});