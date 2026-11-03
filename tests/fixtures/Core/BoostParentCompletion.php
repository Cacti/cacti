<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiBoostParentCompletionFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/BlockSourceSlice.php';

function completion_reset($completed, $failed, $requeued, $updates) {
	$GLOBALS['boost_completion_state'] = array(
		'completed'  => $completed,
		'failed'     => $failed,
		'requeued'   => $requeued,
		'updates'    => $updates,
		'settings'   => array(),
		'logs'       => array(),
		'statistics' => array(),
	);
}

function boost_completed_children($run_id) {
	return $GLOBALS['boost_completion_state']['completed'];
}

function boost_failed_children($run_id) {
	return $GLOBALS['boost_completion_state']['failed'];
}

function boost_requeue_unprocessed_rows($run_id) {
	return $GLOBALS['boost_completion_state']['requeued'];
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return $GLOBALS['boost_completion_state']['updates'];
}

function set_config_option($name, $value) {
	$GLOBALS['boost_completion_state']['settings'][$name] = $value;
}

function boost_log_statistics($updates) {
	$GLOBALS['boost_completion_state']['statistics'][] = $updates;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['boost_completion_state']['logs'][] = $message;
}

\cacti_fixture_source_block(
	dirname(__DIR__, 3) . '/poller_boost.php',
	'$completed_children = boost_completed_children($run_id);',
	"set_config_option('boost_poller_started', 0);",
	'boost_parent_completion',
	__NAMESPACE__
);

function completion_scenario($completed, $failed, $requeued, $updates) {
	completion_reset($completed, $failed, $requeued, $updates);

	$result = boost_parent_completion(array(
		'run_id'            => str_repeat('a', 32),
		'expected_children' => 2,
		'current_time'      => 1800000000,
		'last_run_time'     => 1799990000,
		'seconds_offset'    => 900,
		'exit_code'         => 0,
	));

	return array('result' => $result, 'state' => $GLOBALS['boost_completion_state']);
}

print json_encode(array(
	'incomplete'     => completion_scenario(1, 0, true, 10),
	'requeue_failed' => completion_scenario(2, 1, false, 10),
	'rrd_failed'     => completion_scenario(2, 1, true, 10),
	'success'        => completion_scenario(2, 0, true, 10),
), JSON_THROW_ON_ERROR);
