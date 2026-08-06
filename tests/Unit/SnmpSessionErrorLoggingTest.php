<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace SnmpSessionErrorLoggingTest;

const POLLER_VERBOSITY_HIGH = 4;

$GLOBALS['snmp_session_error_logs'] = array();

/**
 * Supplies the timeout constant used by the extracted logging helper.
 */
class SNMP {
	const ERRNO_TIMEOUT = 4;
}

/**
 * Provides deterministic native-session errors for unit tests.
 */
class FakeSnmpSession {
	private $errno;
	private $error;

	/**
	 * @param int    $errno Native SNMP error number.
	 * @param string $error Native SNMP error message.
	 */
	public function __construct($errno, $error) {
		$this->errno = $errno;
		$this->error = $error;
	}

	/**
	 * @return int Native SNMP error number.
	 */
	public function getErrno() {
		return $this->errno;
	}

	/**
	 * @return string Native SNMP error message.
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Emits the PHP 8.5 failure shape described in issue 7590.
	 *
	 * @return false Always reports an operation failure.
	 */
	public function get() {
		trigger_error('Could not open SNMP session: Invalid address (Permission denied)', E_USER_WARNING);

		return false;
	}

	/**
	 * Emits a non-warning error for handler-delegation coverage.
	 *
	 * @return false Always reports an operation failure.
	 */
	public function notice() {
		trigger_error('Delegated notice', E_USER_NOTICE);

		return false;
	}
}

/**
 * Captures SNMP warning log entries.
 *
 * @param string $message Log message.
 * @param bool   $output  Whether to print the message.
 * @param string $environ Log subsystem.
 * @param int    $level   Poller verbosity level.
 *
 * @return bool Always true for the unit test.
 */
function cacti_log($message, $output, $environ, $level) {
	$GLOBALS['snmp_session_error_logs'][] = array($message, $output, $environ, $level);

	return true;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/snmp.php');
preg_match('/function cacti_snmp_log_session_error\(.*?^}\R/ms', $source, $matches);
eval('namespace SnmpSessionErrorLoggingTest;' . $matches[0]);
preg_match('/function cacti_snmp_session_call\(.*?^}\R/ms', $source, $matches);
eval('namespace SnmpSessionErrorLoggingTest;' . $matches[0]);

beforeEach(function () {
	$GLOBALS['snmp_session_error_logs'] = array();
});

test('timeout failures retain the configured timeout detail', function () {
	cacti_snmp_log_session_error(new FakeSnmpSession(SNMP::ERRNO_TIMEOUT, 'ignored'), array('timeout' => 1500, 'hostname' => 'router-1'), '.1.3.6');

	expect($GLOBALS['snmp_session_error_logs'][0][0])->toContain("SNMP Error:'Timeout (2 ms)'")
		->and($GLOBALS['snmp_session_error_logs'][0][0])->toContain("Device:'router-1', OID:'.1.3.6'")
		->and($GLOBALS['snmp_session_error_logs'][0][2])->toBe('SNMP')
		->and($GLOBALS['snmp_session_error_logs'][0][3])->toBe(POLLER_VERBOSITY_HIGH);
});

test('non-timeout failures log the native reason without line injection', function () {
	cacti_snmp_log_session_error(new FakeSnmpSession(2, "Invalid address\r\nPermission denied"), array('timeout' => 500, 'hostname' => 'router-2'), array('.1', '.2'), 'ignored warning');

	expect($GLOBALS['snmp_session_error_logs'][0][0])->toContain("SNMP Error:'Invalid address  Permission denied'")
		->and($GLOBALS['snmp_session_error_logs'][0][0])->toContain("OID:'.1,.2'")
		->and($GLOBALS['snmp_session_error_logs'][0][0])->not->toContain("\n");
});

test('suppressed operation warnings are captured when native errors are empty', function () {
	$session = new FakeSnmpSession(0, '');
	$warning = '';
	$result  = cacti_snmp_session_call($session, 'get', array('.3'), $warning);

	expect($result)->toBeFalse()
		->and($warning)->toBe('Could not open SNMP session: Invalid address (Permission denied)');

	cacti_snmp_log_session_error($session, array('timeout' => 500, 'hostname' => 'router-3'), '.3', $warning);

	expect($GLOBALS['snmp_session_error_logs'][0][0])->toContain("SNMP Error:'Could not open SNMP session: Invalid address (Permission denied)'");
});

test('non-warning errors are delegated to the previous handler', function () {
	$delegated = array();
	$session   = new FakeSnmpSession(0, '');
	$warning   = '';

	set_error_handler(function($level, $message) use (&$delegated) {
		$delegated[] = array($level, $message);

		return true;
	});

	try {
		cacti_snmp_session_call($session, 'notice', array(), $warning);
	} finally {
		restore_error_handler();
	}

	expect($delegated[0][0])->toBe(E_USER_NOTICE)
		->and($warning)->toBe('');
});

test('empty native errors retain their numeric diagnostic and all callers use the helper', function () use ($source) {
	cacti_snmp_log_session_error(new FakeSnmpSession(9, ''), array('timeout' => 500, 'hostname' => 'router-3'), '.3');

	expect($GLOBALS['snmp_session_error_logs'][0][0])->toContain("SNMP Error:'Error Number 9'")
		->and(substr_count($source, 'cacti_snmp_log_session_error($session, $info, $oid, $warning);'))->toBe(3)
		->and(substr_count($source, 'cacti_snmp_session_call($session,'))->toBe(4);
});
