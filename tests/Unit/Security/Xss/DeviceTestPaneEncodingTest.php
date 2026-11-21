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
 * The device test pane prints what the device answers over SNMP and, for a
 * device on another data collector, the reply that collector relays. Both are
 * shown as text: the SNMP strings are HTML encoded where they are printed, and
 * the relayed reply loses its markup and keeps its line breaks.
 *
 * api_device_ping_device() is the unchanged function from lib/api_device.php,
 * loaded into this namespace so the SNMP and collector calls can be answered
 * here.
 */

namespace DeviceTestPaneEncodingTest;

function __($text) {
	return $text;
}

function db_fetch_row_prepared($sql, $params = array()) {
	return $GLOBALS['device_test_pane']['host'];
}

function call_remote_data_collector($poller_id, $url) {
	$GLOBALS['device_test_pane']['remote_calls'][] = array($poller_id, $url);

	return $GLOBALS['device_test_pane']['remote_reply'];
}

function cacti_snmp_session() {
	$GLOBALS['snmp_error'] = $GLOBALS['device_test_pane']['session_error'];

	if ($GLOBALS['device_test_pane']['session_error'] != '') {
		return false;
	}

	return new class {
		public function close() {
			return true;
		}
	};
}

function cacti_snmp_session_get($session, $oid) {
	$answers = $GLOBALS['device_test_pane']['answers'];

	if (!array_key_exists($oid, $answers)) {
		return '';
	}

	if ($answers[$oid] === false) {
		$GLOBALS['snmp_error'] = $GLOBALS['device_test_pane']['get_error'];
	}

	return $answers[$oid];
}

function cacti_snmp_select_uptime($system_uptime, $engine_time) {
	return is_numeric($system_uptime) ? (int) $system_uptime : false;
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_device.php', 'api_device_ping_device', __NAMESPACE__);

function device_test_pane(array $state, $from_remote = false) {
	$GLOBALS['device_test_pane'] = $state + array(
		'host' => array(
			'id'                   => 7,
			'poller_id'            => 1,
			'disabled'             => '',
			'availability_method'  => AVAIL_SNMP,
			'hostname'             => 'device.example.com',
			'snmp_community'       => 'public',
			'snmp_version'         => 2,
			'snmp_username'        => '',
			'snmp_password'        => '',
			'snmp_auth_protocol'   => '',
			'snmp_priv_passphrase' => '',
			'snmp_priv_protocol'   => '',
			'snmp_context'         => '',
			'snmp_engine_id'       => '',
			'snmp_port'            => 161,
			'snmp_timeout'         => 500,
			'ping_retries'         => 1,
			'max_oids'             => 10,
		),
		'answers'       => array(),
		'session_error' => '',
		'get_error'     => '',
		'remote_reply'  => '',
		'remote_calls'  => array(),
	);

	$config   = $GLOBALS['config'];
	$previous = array_key_exists('snmp_error', $GLOBALS) ? $GLOBALS['snmp_error'] : null;

	$GLOBALS['config']['poller_id'] = 1;
	$GLOBALS['config']['url_path']  = '/cacti/';

	ob_start();

	try {
		api_device_ping_device(7, $from_remote);

		return ob_get_contents();
	} finally {
		ob_end_clean();

		$GLOBALS['config']     = $config;
		$GLOBALS['snmp_error'] = $previous;
	}
}

test('the SNMP system strings are shown as text', function () {
	$output = device_test_pane(array(
		'answers' => array(
			'.1.3.6.1.2.1.1.1.0' => 'Router <b>A</b> & "core"',
			'.1.3.6.1.2.1.1.3.0' => '8640000',
			'.1.3.6.1.2.1.1.5.0' => "<i>edge</i>'1",
			'.1.3.6.1.2.1.1.6.0' => '<img src=x>Rack 4',
			'.1.3.6.1.2.1.1.4.0' => 'noc <noc@example.com>',
		),
	));

	expect($output)->toContain('<strong>System:</strong> Router &lt;b&gt;A&lt;/b&gt; &amp; &quot;core&quot;<br>')
		->and($output)->toContain('<strong>Hostname:</strong> &lt;i&gt;edge&lt;/i&gt;&apos;1<br>')
		->and($output)->toContain('<strong>Location:</strong> &lt;img src=x&gt;Rack 4<br>')
		->and($output)->toContain('<strong>Contact:</strong> noc &lt;noc@example.com&gt;<br>')
		->and($output)->toContain('<strong>Uptime:</strong> 8640000')
		->and($output)->not->toContain('<b>')
		->and($output)->not->toContain('<i>')
		->and($output)->not->toContain('<img');
});

test('a long system description is still split into lines after encoding', function () {
	$output = device_test_pane(array(
		'answers' => array(
			'.1.3.6.1.2.1.1.1.0' => str_repeat('Cisco IOS Software <C3750> ', 6),
			'.1.3.6.1.2.1.1.3.0' => '100',
		),
	));

	expect($output)->toMatch('/<strong>System:<\/strong> [^<]+<br>[^<]+<br>/')
		->and($output)->toContain('&lt;C3750&gt;')
		->and($output)->not->toContain('<C3750>');
});

test('the SNMP error text is shown as text at each step', function (array $state) {
	$output = device_test_pane($state);

	expect($output)->toContain('SNMP error - Timeout &lt;b&gt;udp&lt;/b&gt;</span>')
		->and($output)->not->toContain('<b>');
})->with(array(
	'session' => array(array('session_error' => 'Timeout <b>udp</b>')),
	'system'  => array(array(
		'answers'   => array('.1.3.6.1.2.1.1.1.0' => false),
		'get_error' => 'Timeout <b>udp</b>',
	)),
	'host' => array(array(
		'answers' => array(
			'.1.3.6.1.2.1.1.1.0' => '',
			'.1.3.6.1.2.1.1.3.0' => false,
		),
		'get_error' => 'Timeout <b>udp</b>',
	)),
));

test('the reply relayed from another data collector is shown as text', function () {
	$reply = "SNMP Information<br><span class='monoSpace'><strong>System:</strong> Router &lt;b&gt;A<br>"
		. "<strong>Uptime:</strong> 100&nbsp;(0days)<br><a href='#' onclick='x()'>Location</a><img src=x></span><br>"
		. "Ping Results<br>\n<span class='hostUp'>ICMP Ping Success (0.4 ms)</span>\n";

	$state           = array('remote_reply' => $reply);
	$state['host']   = array('id' => 7, 'poller_id' => 2, 'availability_method' => AVAIL_SNMP, 'disabled' => '');
	$output          = device_test_pane($state);

	expect($GLOBALS['device_test_pane']['remote_calls'])->toBe(array(array(2, '/cacti/remote_agent.php?action=ping&host_id=7')))
		->and($output)->toBe(
			'SNMP Information<br />' . PHP_EOL
			. 'System: Router &lt;b&gt;A<br />' . PHP_EOL
			. 'Uptime: 100&nbsp;(0days)<br />' . PHP_EOL
			. 'Location<br />' . PHP_EOL
			. 'Ping Results<br />' . PHP_EOL
			. 'ICMP Ping Success (0.4 ms)<br />' . "\n"
		);
});

test('an empty reply from another data collector is still reported', function () {
	$state         = array('remote_reply' => '');
	$state['host'] = array('id' => 7, 'poller_id' => 2, 'availability_method' => AVAIL_SNMP, 'disabled' => '');

	expect(device_test_pane($state))->toBe('ERROR: Failed to connect to remote collector.');
});
