<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace BoostFailureAlertFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function read_config_option($name) {
	return $GLOBALS['boost_failure_alert_state']['settings'][$name] ?? '';
}

function set_config_option($name, $value) {
	$GLOBALS['boost_failure_alert_state']['settings'][$name] = $value;
}

function admin_email($subject, $message) {
	$GLOBALS['boost_failure_alert_state']['emails'][] = array($subject, $message);
}

function __($message) {
	return $message;
}

function time() {
	return $GLOBALS['boost_failure_alert_state']['now'];
}

function boostFailureAlertLoadFunction($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_alert_failure_queue')) {
		return;
	}

	\cacti_fixture_source_function(
		$root . '/lib/boost.php',
		'boost_alert_failure_queue',
		__NAMESPACE__
	);
}

beforeEach(function () use ($root) {
	boostFailureAlertLoadFunction($root);
	$GLOBALS['boost_failure_alert_state'] = array(
		'now'      => 200000,
		'settings' => array(),
		'emails'   => array(),
	);
});

test('rate-limited warnings still replace the persisted operator diagnostic', function () {
	$GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'] = 199999;

	boost_alert_failure_queue('new failure detail');

	expect($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_error'])->toBe('new failure detail')
		->and($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'])->toBe(199999)
		->and($GLOBALS['boost_failure_alert_state']['emails'])->toBe(array());
});

test('urgent failure email has a five-minute flood-control interval', function () {
	$GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'] = 199701;
	boost_alert_failure_queue('first urgent detail', true);

	expect($GLOBALS['boost_failure_alert_state']['emails'])->toBe(array());

	$GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'] = 199699;
	boost_alert_failure_queue('second urgent detail', true);

	expect($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_error'])->toBe('second urgent detail')
		->and($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'])->toBe(200000)
		->and($GLOBALS['boost_failure_alert_state']['emails'])->toHaveCount(1);
});
