<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiBoostArchiveRequeueFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['boost_requeue_state'] = array(
	'archives' => array('poller_output_boost_arch_100', 'poller_output_boost_arch_200'),
	'calls'    => array(),
	'fail_at'  => 0,
	'logs'     => array(),
);

function boost_get_arch_table_names() {
	return $GLOBALS['boost_requeue_state']['archives'];
}

function boost_is_valid_archive_table($table) {
	return preg_match('/^poller_output_boost_arch_\d+$/D', $table) === 1;
}

function db_execute_prepared($sql, $params = array(), $log = true) {
	$state =& $GLOBALS['boost_requeue_state'];
	$state['calls'][] = array('sql' => $sql, 'params' => $params, 'log' => $log);

	return $state['fail_at'] !== count($state['calls']);
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['boost_requeue_state']['logs'][] = array($message, $facility);
}

\cacti_fixture_source_function(
	dirname(__DIR__, 3) . '/poller_boost.php',
	'boost_requeue_unprocessed_rows',
	__NAMESPACE__
);

$run_id  = str_repeat('a', 32);
$success = boost_requeue_unprocessed_rows($run_id);
$calls   = $GLOBALS['boost_requeue_state']['calls'];

$GLOBALS['boost_requeue_state']['archives'] = array('poller_output_boost_arch_bad');
$GLOBALS['boost_requeue_state']['calls']    = array();
$invalid_table = boost_requeue_unprocessed_rows($run_id);

$GLOBALS['boost_requeue_state']['archives'] = array('poller_output_boost_arch_100', 'poller_output_boost_arch_200');
$GLOBALS['boost_requeue_state']['calls']    = array();
$GLOBALS['boost_requeue_state']['fail_at']  = 2;
$failed_copy = boost_requeue_unprocessed_rows($run_id);
$failed_calls = $GLOBALS['boost_requeue_state']['calls'];

print json_encode(array(
	'success'       => $success,
	'calls'         => $calls,
	'invalid_run'   => boost_requeue_unprocessed_rows('invalid'),
	'invalid_table' => $invalid_table,
	'failed_copy'   => $failed_copy,
	'failed_calls'  => $failed_calls,
), JSON_THROW_ON_ERROR);
