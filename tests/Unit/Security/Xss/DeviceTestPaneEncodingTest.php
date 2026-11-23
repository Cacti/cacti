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
 * The probe runs the unchanged api_device_ping_device() from
 * lib/api_device.php with the SNMP and collector calls answered from the
 * scenario.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function device_test_pane(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/DeviceTestPaneProbe.php', array(json_encode($arguments)));
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
	))['output'];

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
	))['output'];

	expect($output)->toMatch('/<strong>System:<\/strong> [^<]+<br>[^<]+<br>/')
		->and($output)->toContain('&lt;C3750&gt;')
		->and($output)->not->toContain('<C3750>');
});

test('the SNMP error text is shown as text at each step', function (array $scenario) {
	$output = device_test_pane($scenario)['output'];

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

	$result = device_test_pane(array('remote_reply' => $reply, 'host' => array('poller_id' => 2)));

	expect($result['remote_calls'])->toBe(array(array(2, '/cacti/remote_agent.php?action=ping&host_id=7')))
		->and($result['output'])->toBe(
			'SNMP Information<br />' . PHP_EOL
			. 'System: Router &lt;b&gt;A<br />' . PHP_EOL
			. 'Uptime: 100&nbsp;(0days)<br />' . PHP_EOL
			. 'Location<br />' . PHP_EOL
			. 'Ping Results<br />' . PHP_EOL
			. 'ICMP Ping Success (0.4 ms)<br />' . "\n"
		);
});

test('an empty reply from another data collector is still reported', function () {
	$result = device_test_pane(array('remote_reply' => '', 'host' => array('poller_id' => 2)));

	expect($result['output'])->toBe('ERROR: Failed to connect to remote collector.');
});
