<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

(function () use ($root) {
	$config = array(
		'php_snmp_support' => false,
		'include_path'     => $root . '/include'
	);

	require_once $root . '/lib/snmp.php';
})();

test('an IPv4 literal on a non-default port is a plain host:port', function () {
	expect(snmp_format_agent('127.0.0.1', 1161))->toBe('127.0.0.1:1161')
		->and(snmp_format_agent('192.0.2.10', '10161'))->toBe('192.0.2.10:10161');
});

test('a hostname on a non-default port is a plain host:port', function () {
	expect(snmp_format_agent('router.example.net', 1161))->toBe('router.example.net:1161')
		->and(snmp_format_agent(' router.example.net ', 1161))->toBe('router.example.net:1161');
});

test('an IPv4 literal or hostname on the default or an empty port stays bare', function () {
	expect(snmp_format_agent('127.0.0.1', 161))->toBe('127.0.0.1')
		->and(snmp_format_agent('127.0.0.1', '161'))->toBe('127.0.0.1')
		->and(snmp_format_agent('router.example.net', 161))->toBe('router.example.net')
		->and(snmp_format_agent('127.0.0.1', ''))->toBe('127.0.0.1')
		->and(snmp_format_agent('router.example.net', null))->toBe('router.example.net');
});

test('a bare IPv6 literal is bracketed with the port', function () {
	expect(snmp_format_agent('2001:db8::1', 1161))->toBe('[2001:db8::1]:1161')
		->and(snmp_format_agent('2001:db8::1', 161))->toBe('[2001:db8::1]:161')
		->and(snmp_format_agent('::1', ''))->toBe('[::1]:161');
});

test('a bracketed IPv6 target keeps an embedded port and gains a missing one', function () {
	expect(snmp_format_agent('[2001:db8::1]', 1161))->toBe('[2001:db8::1]:1161')
		->and(snmp_format_agent('[2001:db8::1]', ''))->toBe('[2001:db8::1]:161')
		->and(snmp_format_agent('[2001:db8::1]:1161', 500))->toBe('[2001:db8::1]:1161');
});

test('a transport-prefixed target is left untouched', function () {
	expect(snmp_format_agent('udp:127.0.0.1', 1161))->toBe('udp:127.0.0.1')
		->and(snmp_format_agent('tcp:router.example.net:1161', 500))->toBe('tcp:router.example.net:1161')
		->and(snmp_format_agent('udp6:[2001:db8::1]', 1161))->toBe('udp6:[2001:db8::1]')
		->and(snmp_format_agent('tcp6:[2001:db8::1]:1161', 161))->toBe('tcp6:[2001:db8::1]:1161')
		->and(snmp_format_agent('unix:/var/agentx/master', 1161))->toBe('unix:/var/agentx/master');
});
