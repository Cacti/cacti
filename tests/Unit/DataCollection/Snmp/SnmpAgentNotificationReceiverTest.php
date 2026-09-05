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

namespace SnmpAgentNotificationReceiverTest;

const SNMPAGENT_EVENT_SEVERITY_LOW      = 1;
const SNMPAGENT_EVENT_SEVERITY_MEDIUM   = 2;
const SNMPAGENT_EVENT_SEVERITY_HIGH     = 3;
const SNMPAGENT_EVENT_SEVERITY_CRITICAL = 4;
const POLLER_VERBOSITY_NONE             = 1;

$GLOBALS['config']                      = array();
$GLOBALS['snmpagent_notification_logs'] = array();
$GLOBALS['snmpagent_event_severity']    = array(
	SNMPAGENT_EVENT_SEVERITY_LOW      => 'low',
	SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'medium',
	SNMPAGENT_EVENT_SEVERITY_HIGH     => 'high',
	SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'critical'
);

function read_config_option($name) {
	return $name == 'path_snmptrap' ? '/usr/bin/snmptrap' : '';
}

function db_fetch_cell_prepared($sql, $params) {
	return '.1.3.6.1.4.1.500.1';
}

function db_fetch_assoc_prepared($sql, $params) {
	return array();
}

function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function cacti_log($message, $output, $environ, $level) {
	$GLOBALS['snmpagent_notification_logs'][] = array($message, $output, $environ, $level);

	return true;
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/snmpagent.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/snmpagent.php for the notification receiver test.');
}

if (preg_match('/function snmpagent_notification\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract snmpagent_notification() for the notification receiver test.');
}

eval('namespace SnmpAgentNotificationReceiverTest;' . $matches[0]);

beforeEach(function () {
	$GLOBALS['config']                      = array();
	$GLOBALS['snmpagent_notification_logs'] = array();
});

test('missing receivers produce an actionable notice', function () {
	$result = snmpagent_notification(
		'cactiNotifyDeviceFailedPoll',
		'CACTI-MIB',
		array(),
		SNMPAGENT_EVENT_SEVERITY_MEDIUM
	);

	expect($result)->toBeFalse()
		->and($GLOBALS['snmpagent_notification_logs'])->toHaveCount(1)
		->and($GLOBALS['snmpagent_notification_logs'][0][0])->toStartWith('NOTICE:')
		->and($GLOBALS['snmpagent_notification_logs'][0][0])->toContain('Console > Utilities > SNMP Agent Utilities > SNMP Notification Receivers')
		->and($GLOBALS['snmpagent_notification_logs'][0][0])->toContain('ignore this notice when SNMP traps are intentionally disabled')
		->and($GLOBALS['snmpagent_notification_logs'][0][2])->toBe('SNMPAGENT')
		->and($GLOBALS['snmpagent_notification_logs'][0][3])->toBe(POLLER_VERBOSITY_NONE);
});

test('medium-severity missing receiver notices remain suppressed after the first event', function () {
	snmpagent_notification('cactiNotifyDeviceFailedPoll', 'CACTI-MIB', array(), SNMPAGENT_EVENT_SEVERITY_MEDIUM);
	snmpagent_notification('cactiNotifyDeviceFailedPoll', 'CACTI-MIB', array(), SNMPAGENT_EVENT_SEVERITY_MEDIUM);

	expect($GLOBALS['snmpagent_notification_logs'])->toHaveCount(1)
		->and($GLOBALS['config']['snmpagent']['notifications']['ignore']['cactiNotifyDeviceFailedPoll'])->toBe(1);
});

test('high-severity missing receiver notices are not suppressed', function () {
	snmpagent_notification('cactiNotifyDeviceDown', 'CACTI-MIB', array(), SNMPAGENT_EVENT_SEVERITY_HIGH);
	snmpagent_notification('cactiNotifyDeviceDown', 'CACTI-MIB', array(), SNMPAGENT_EVENT_SEVERITY_HIGH);

	expect($GLOBALS['snmpagent_notification_logs'])->toHaveCount(2)
		->and($GLOBALS['config']['snmpagent']['notifications']['ignore']['cactiNotifyDeviceDown'] ?? null)->toBeNull();
});
