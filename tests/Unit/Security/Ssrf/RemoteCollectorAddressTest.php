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
 * call_remote_data_collector() requests a page from the host stored for a
 * data collector. The address that host resolves to is checked before the
 * request: loopback, link-local and other reserved addresses are refused,
 * public and private (RFC 1918) addresses are not. The request then goes to
 * the checked address, with the collector's name kept in the Host header and
 * as the name its certificate is checked against.
 *
 * The probe runs the unchanged function from lib/functions.php with the
 * database, the resolver and the request answered from the scenario.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function remote_collector_request(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RemoteCollectorRequestProbe.php', array(json_encode($arguments)));
}

const REMOTE_COLLECTOR_PATH = '/cacti/remote_agent.php?action=ping&host_id=7';

test('a collector at a reserved address is not requested', function ($hostname, array $dns) {
	$result = remote_collector_request(array('hostname' => $hostname, 'dns' => $dns));

	expect($result['reply'])->toBe('')
		->and($result['requests'])->toBe(array())
		->and($result['log'])->toHaveCount(1)
		->and($result['log'][0])->toStartWith('SECURITY: ERROR: Refusing Remote Data Collector request for PollerID:2');
})->with(array(
	'loopback'               => array('127.0.0.1', array()),
	'loopback range'         => array('127.8.9.10', array()),
	'link-local'             => array('169.254.169.254', array()),
	'this network'           => array('0.0.0.0', array()),
	'reserved'               => array('240.0.0.1', array()),
	'IPv6 loopback'          => array('::1', array()),
	'IPv6 link-local'        => array('fe80::1', array()),
	'IPv6 scoped link-local' => array('fe80::1%eth0', array()),
	'name for loopback'      => array('localhost', array('localhost' => '127.0.0.1')),
	'name for link-local'    => array('collector.example.com', array('collector.example.com' => '169.254.10.20')),
));

test('a collector at a public or private address is requested at that address', function ($hostname, $url_host) {
	$scheme = 'http';
	$result = remote_collector_request(array('hostname' => $hostname, 'scheme' => $scheme));

	expect($result['reply'])->toBe('collector reply')
		->and($result['log'])->toBe(array())
		->and($result['lookups'])->toBe(array())
		->and($result['requests'])->toBe(array(array($scheme . '://' . $url_host . REMOTE_COLLECTOR_PATH, array())));
})->with(array(
	'10/8'         => array('10.20.30.40', '10.20.30.40'),
	'172.16/12'    => array('172.16.4.4', '172.16.4.4'),
	'192.168/16'   => array('192.168.1.10', '192.168.1.10'),
	'public'       => array('192.0.2.10', '192.0.2.10'),
	'IPv6'         => array('2001:db8::10', '[2001:db8::10]'),
	'IPv6 private' => array('fd00::10', '[fd00::10]'),
));

test('a collector named by host name is requested at the address that was checked', function ($address) {
	$result = remote_collector_request(array(
		'hostname' => 'collector.example.com',
		'dns'      => array('collector.example.com' => $address),
		'scheme'   => 'https',
		'context'  => array(
			'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
			'http' => array('timeout' => 5),
		),
	));

	expect($result['reply'])->toBe('collector reply')
		->and($result['lookups'])->toBe(array('collector.example.com'))
		->and($result['requests'])->toHaveCount(1)
		->and($result['requests'][0][0])->toBe('https://' . $address . REMOTE_COLLECTOR_PATH)
		->and($result['requests'][0][1]['http'])->toBe(array('timeout' => 5, 'header' => array('Host: collector.example.com')))
		->and($result['requests'][0][1]['ssl'])->toBe(array(
			'verify_peer'      => true,
			'verify_peer_name' => true,
			'peer_name'        => 'collector.example.com',
		));
})->with(array(
	'private' => array('10.20.30.40'),
	'public'  => array('192.0.2.10'),
));

test('request headers added by a plugin are kept', function ($existing, array $expected) {
	$result = remote_collector_request(array(
		'hostname' => 'collector.example.com',
		'dns'      => array('collector.example.com' => '10.20.30.40'),
		'context'  => array('http' => array('timeout' => 5, 'header' => $existing)),
	));

	expect($result['requests'][0][1]['http'])->toBe(array('timeout' => 5, 'header' => $expected));
})->with(array(
	'string' => array("X-Collector: 1\r\n", array('X-Collector: 1', 'Host: collector.example.com')),
	'list'   => array(array('X-Collector: 1'), array('X-Collector: 1', 'Host: collector.example.com')),
));

test('a name that does not resolve is still reported and not requested', function () {
	$result = remote_collector_request(array('hostname' => 'collector.invalid'));

	expect($result['reply'])->toBe('')
		->and($result['requests'])->toBe(array())
		->and($result['log'][0])->toContain('It is not reachable via DNS!');
});

test('a failed request is logged and answered with an empty reply', function () {
	$result = remote_collector_request(array('hostname' => '10.20.30.40', 'reply' => false));

	expect($result['reply'])->toBe('')
		->and($result['requests'])->toHaveCount(1)
		->and($result['log'])->toBe(array('WEBUI: WARNING: PollerID:2 did not return a successful response within the size limit.'));
});
