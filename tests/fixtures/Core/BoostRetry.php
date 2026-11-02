<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiBoostRetryFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

function retry_reset($processes, $rows) {
	$GLOBALS['boost_retry_state'] = array(
		'processes' => $processes,
		'rows'      => $rows,
		'settings'  => array(),
		'messages'  => array(),
		'logs'      => array(),
	);
	$_SESSION['sess_user_id'] = 7;
}

function db_fetch_assoc($sql) {
	return $GLOBALS['boost_retry_state']['processes'];
}

function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function boost_get_total_rows($stop_after = 0) {
	return $GLOBALS['boost_retry_state']['rows'];
}

function raise_message($id, $message = '', $level = 0) {
	$GLOBALS['boost_retry_state']['messages'][] = array($id, $message, $level);
}

function __($message) {
	return $message;
}

function read_config_option($name) {
	return $name == 'boost_rrd_update_interval' ? 15 : '';
}

function time() {
	return 1800000000;
}

function set_config_option($name, $value) {
	$GLOBALS['boost_retry_state']['settings'][$name] = $value;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['boost_retry_state']['logs'][] = array($message, $facility);
}

function get_username($user_id) {
	return 'fixture-admin';
}

if (!defined('MESSAGE_LEVEL_INFO')) {
	define('MESSAGE_LEVEL_INFO', 1);
	define('MESSAGE_LEVEL_WARN', 2);
}

\cacti_fixture_source_function(
	dirname(__DIR__, 3) . '/utilities.php',
	'boost_retry_queued_data',
	__NAMESPACE__
);

retry_reset(array(array('pid' => 123)), 10);
boost_retry_queued_data();
$running = $GLOBALS['boost_retry_state'];

retry_reset(array(), 0);
boost_retry_queued_data();
$empty = $GLOBALS['boost_retry_state'];

retry_reset(array(), 10);
boost_retry_queued_data();
$scheduled = $GLOBALS['boost_retry_state'];

print json_encode(array(
	'running'   => $running,
	'empty'     => $empty,
	'scheduled' => $scheduled,
), JSON_THROW_ON_ERROR);
