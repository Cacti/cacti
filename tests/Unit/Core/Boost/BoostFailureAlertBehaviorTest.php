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

$root = dirname(__DIR__, 4);

function boostFailureAlertRead($name) {
	return $GLOBALS['boost_failure_alert_state']['settings'][$name] ?? '';
}

function boostFailureAlertSet($name, $value) {
	$GLOBALS['boost_failure_alert_state']['settings'][$name] = $value;
}

function boostFailureAlertEmail($subject, $message) {
	$GLOBALS['boost_failure_alert_state']['emails'][] = array($subject, $message);
}

function boostFailureAlertTranslate($message) {
	return $message;
}

function boostFailureAlertTime() {
	return $GLOBALS['boost_failure_alert_state']['now'];
}

function boostFailureAlertLoadFunction($root) {
	if (function_exists('boostFailureAlertSend')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_alert_failure_queue(');
	$end    = strpos($source, "\nfunction ", $start + 1);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_alert_failure_queue',
		'read_config_option',
		'set_config_option',
		'admin_email',
		'time()',
		'__(',
	), array(
		'boostFailureAlertSend',
		'boostFailureAlertRead',
		'boostFailureAlertSet',
		'boostFailureAlertEmail',
		'boostFailureAlertTime()',
		'boostFailureAlertTranslate(',
	), $function);

	eval($function);
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

	boostFailureAlertSend('new failure detail');

	expect($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_error'])->toBe('new failure detail')
		->and($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'])->toBe(199999)
		->and($GLOBALS['boost_failure_alert_state']['emails'])->toBe(array());
});

test('urgent failure email has a five-minute flood-control interval', function () {
	$GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'] = 199701;
	boostFailureAlertSend('first urgent detail', true);

	expect($GLOBALS['boost_failure_alert_state']['emails'])->toBe(array());

	$GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'] = 199699;
	boostFailureAlertSend('second urgent detail', true);

	expect($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_error'])->toBe('second urgent detail')
		->and($GLOBALS['boost_failure_alert_state']['settings']['boost_failure_last_alert'])->toBe(200000)
		->and($GLOBALS['boost_failure_alert_state']['emails'])->toHaveCount(1);
});
