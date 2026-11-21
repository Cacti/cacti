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
 * The function is the unchanged one from lib/functions.php, loaded into this
 * namespace so the database, the resolver and the request can be answered
 * here.
 */

namespace RemoteCollectorAddressTest;

function __($text) {
	return $text;
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return $GLOBALS['remote_collector']['hostname'];
}

function gethostbyname($hostname) {
	$GLOBALS['remote_collector']['lookups'][] = $hostname;

	return isset($GLOBALS['remote_collector']['dns'][$hostname]) ? $GLOBALS['remote_collector']['dns'][$hostname] : $hostname;
}

function get_url_type() {
	return $GLOBALS['remote_collector']['scheme'];
}

function get_default_contextoption() {
	return $GLOBALS['remote_collector']['context'];
}

function remote_agent_read_response($url, $context) {
	$GLOBALS['remote_collector']['requests'][] = array($url, stream_context_get_options($context));

	return $GLOBALS['remote_collector']['reply'];
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['remote_collector']['log'][] = $facility . ': ' . $message;
}

function debounce_run_notification($id) {
	return true;
}

function admin_email($subject, $message) {
	return true;
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/functions.php', 'call_remote_data_collector', __NAMESPACE__);

function remote_collector_request($hostname, array $dns = array(), $scheme = 'http', array $context = array(), $reply = 'collector reply') {
	$GLOBALS['remote_collector'] = array(
		'hostname' => $hostname,
		'dns'      => $dns,
		'scheme'   => $scheme,
		'context'  => $context,
		'reply'    => $reply,
		'lookups'  => array(),
		'requests' => array(),
		'log'      => array(),
	);

	$result = call_remote_data_collector(2, '/cacti/remote_agent.php?action=ping&host_id=7');

	return array($result, $GLOBALS['remote_collector']);
}

test('a collector at a reserved address is not requested', function ($hostname, array $dns) {
	[$result, $state] = remote_collector_request($hostname, $dns);

	expect($result)->toBe('')
		->and($state['requests'])->toBe(array())
		->and($state['log'])->toHaveCount(1)
		->and($state['log'][0])->toStartWith('SECURITY: ERROR: Refusing Remote Data Collector request for PollerID:2');
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
	[$result, $state] = remote_collector_request($hostname);

	expect($result)->toBe('collector reply')
		->and($state['log'])->toBe(array())
		->and($state['lookups'])->toBe(array())
		->and($state['requests'])->toHaveCount(1)
		->and($state['requests'][0][0])->toBe('http://' . $url_host . '/cacti/remote_agent.php?action=ping&host_id=7')
		->and($state['requests'][0][1])->toBe(array());
})->with(array(
	'10/8'           => array('10.20.30.40', '10.20.30.40'),
	'172.16/12'      => array('172.16.4.4', '172.16.4.4'),
	'192.168/16'     => array('192.168.1.10', '192.168.1.10'),
	'public'         => array('192.0.2.10', '192.0.2.10'),
	'IPv6'           => array('2001:db8::10', '[2001:db8::10]'),
	'IPv6 private'   => array('fd00::10', '[fd00::10]'),
));

test('a collector named by host name is requested at the address that was checked', function ($address) {
	[$result, $state] = remote_collector_request('collector.example.com', array('collector.example.com' => $address), 'https', array(
		'ssl'   => array('verify_peer' => true, 'verify_peer_name' => true),
		'https' => array('timeout' => 5),
	));

	expect($result)->toBe('collector reply')
		->and($state['lookups'])->toBe(array('collector.example.com'))
		->and($state['requests'])->toHaveCount(1)
		->and($state['requests'][0][0])->toBe('https://' . $address . '/cacti/remote_agent.php?action=ping&host_id=7')
		->and($state['requests'][0][1]['http']['header'])->toBe(array('Host: collector.example.com'))
		->and($state['requests'][0][1]['ssl'])->toBe(array(
			'verify_peer'      => true,
			'verify_peer_name' => true,
			'peer_name'        => 'collector.example.com',
		))
		->and($state['requests'][0][1]['https'])->toBe(array('timeout' => 5));
})->with(array(
	'private' => array('10.20.30.40'),
	'public'  => array('192.0.2.10'),
));

test('request headers added by a plugin are kept', function ($existing, array $expected) {
	[, $state] = remote_collector_request('collector.example.com', array('collector.example.com' => '10.20.30.40'), 'http', array(
		'http' => array('timeout' => 5, 'header' => $existing),
	));

	expect($state['requests'][0][1]['http'])->toBe(array('timeout' => 5, 'header' => $expected));
})->with(array(
	'string' => array("X-Collector: 1\r\n", array('X-Collector: 1', 'Host: collector.example.com')),
	'list'   => array(array('X-Collector: 1'), array('X-Collector: 1', 'Host: collector.example.com')),
));

test('a name that does not resolve is still reported and not requested', function () {
	[$result, $state] = remote_collector_request('collector.invalid');

	expect($result)->toBe('')
		->and($state['requests'])->toBe(array())
		->and($state['log'][0])->toContain('It is not reachable via DNS!');
});

test('a failed request is logged and answered with an empty reply', function () {
	[$result, $state] = remote_collector_request('10.20.30.40', array(), 'http', array(), false);

	expect($result)->toBe('')
		->and($state['requests'])->toHaveCount(1)
		->and($state['log'])->toBe(array('WEBUI: WARNING: PollerID:2 did not return a successful response within the size limit.'));
});
