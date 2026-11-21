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
 * Runs the unchanged api_device_ping_device() from lib/api_device.php in its
 * own process, with the SNMP and data collector calls answered from the
 * scenario, and prints what the device test pane would show.
 */

namespace CactiDeviceTestPaneFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';

$config = array('poller_id' => 1, 'url_path' => '/cacti/', 'is_web' => false, 'config_options_array' => array());

function __($text) {
	return $text;
}

function db_fetch_row_prepared($sql, $params = array()) {
	return $GLOBALS['scenario']['host'];
}

function call_remote_data_collector($poller_id, $url) {
	$GLOBALS['remote_calls'][] = array($poller_id, $url);

	return $GLOBALS['scenario']['remote_reply'];
}

function cacti_snmp_session() {
	$GLOBALS['snmp_error'] = $GLOBALS['scenario']['session_error'];

	if ($GLOBALS['scenario']['session_error'] != '') {
		return false;
	}

	return new class {
		public function close() {
			return true;
		}
	};
}

function cacti_snmp_session_get($session, $oid) {
	$answers = $GLOBALS['scenario']['answers'];

	if (!array_key_exists($oid, $answers)) {
		return '';
	}

	if ($answers[$oid] === false) {
		$GLOBALS['snmp_error'] = $GLOBALS['scenario']['get_error'];
	}

	return $answers[$oid];
}

function cacti_snmp_select_uptime($system_uptime, $engine_time) {
	return is_numeric($system_uptime) ? (int) $system_uptime : false;
}

\cacti_fixture_source_function($root . '/lib/api_device.php', 'api_device_ping_device', __NAMESPACE__);

function device_test_pane(array $scenario) {
	$GLOBALS['remote_calls'] = array();
	$GLOBALS['scenario']     = $scenario + array(
		'host'          => array(),
		'answers'       => array(),
		'session_error' => '',
		'get_error'     => '',
		'remote_reply'  => '',
	);

	$GLOBALS['scenario']['host'] += array(
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
	);

	ob_start();
	api_device_ping_device(7);

	return array('output' => ob_get_clean(), 'remote_calls' => $GLOBALS['remote_calls']);
}

print json_encode(device_test_pane(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
