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
const POLLER_VERBOSITY_MEDIUM           = 5;

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

$GLOBALS['snmpagent_fetch_calls']   = 0;
$GLOBALS['snmpagent_managers']      = array();
$GLOBALS['snmpagent_varbind_defs']  = array();
$GLOBALS['snmpagent_exec_calls']    = array();

function db_fetch_assoc_prepared($sql, $params) {
	$GLOBALS['snmpagent_fetch_calls']++;

	/* calls 1 and 3 both fetch notification managers (the function re-fetches
	 * them, ordered by message type, inside the difference-check block);
	 * call 2 fetches the registered var binds. */
	return in_array($GLOBALS['snmpagent_fetch_calls'], array(1, 3), true) ? $GLOBALS['snmpagent_managers'] : $GLOBALS['snmpagent_varbind_defs'];
}

function cacti_is_sensitive_key($key) {
	return in_array($key, array('snmp_community', 'snmp_password', 'snmp_priv_passphrase'), true);
}

function exec_background_process($filename, $args) {
	$GLOBALS['snmpagent_exec_calls'][] = array($filename, $args);

	return true;
}

function sql_save($save, $table) {
	return 1;
}

function cacti_escapeshellarg_cmd($string, $quote = true, $strip_env = false) {
	return "'" . $string . "'";
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
	$GLOBALS['snmpagent_fetch_calls']       = 0;
	$GLOBALS['snmpagent_managers']          = array();
	$GLOBALS['snmpagent_varbind_defs']      = array();
	$GLOBALS['snmpagent_exec_calls']        = array();
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
		->and($GLOBALS['snmpagent_notification_logs'][0][0])->toContain('No enabled SNMP notification receivers')
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

/*
 * GHSA-rjvj-r52f-8v5q follow-up: the trap is now sent through
 * exec_background_process(), which uses proc_open(..., ['bypass_shell' =>
 * true]) so every argument reaches snmptrap directly, with no shell (and
 * therefore no cmd.exe metacharacter or %VAR% expansion, and no credential
 * leak through a concatenated debug-log string) involved at all.
 */
test('a v1 notification is sent through the argv-based background path without leaking the community', function () {
	$GLOBALS['snmpagent_managers'] = array(
		array(
			'id' => 1, 'snmp_version' => 1, 'snmp_community' => 'public%PATH%',
			'hostname' => 'host1', 'snmp_port' => 162, 'snmp_message_type' => 1
		)
	);
	$GLOBALS['snmpagent_varbind_defs'] = array(
		array('attribute' => 'trapReason', 'oid' => '.1.3.6.1.4.1.500.2.1', 'type' => 'octect string', 'tcType' => '')
	);

	$result = snmpagent_notification('cactiNotifyDeviceDown', 'CACTI-MIB', array('trapReason' => 'linkDown'), SNMPAGENT_EVENT_SEVERITY_HIGH);

	expect($result)->not->toBeFalse()
		->and($GLOBALS['snmpagent_exec_calls'])->toHaveCount(1);

	$call     = $GLOBALS['snmpagent_exec_calls'][0];
	$filename = $call[0];
	$args     = $call[1];

	expect($filename)->toBe('/usr/bin/snmptrap')
		->and($args)->toBeArray()
		->and($args)->toContain('public%PATH%')
		->and($args)->toContain('host1:162');

	$noteLine = null;
	foreach ($GLOBALS['snmpagent_notification_logs'] as $log) {
		if (str_starts_with($log[0], 'NOTE:')) {
			$noteLine = $log[0];
		}
	}

	expect($noteLine)->not->toBeNull()
		->and($noteLine)->toContain('[REDACTED]')
		->not->toContain('public%PATH%');
});
