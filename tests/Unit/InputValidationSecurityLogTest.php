<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace InputValidationSecurityLogTest;

const CACTI_CLI = false;

$GLOBALS['validation_security_logs'] = [];
$GLOBALS['validation_client_addr']   = '192.0.2.10';

/**
 * Returns deterministic bytes for correlation-ID assertions.
 *
 * @param int $length Requested byte length.
 *
 * @return string Deterministic byte string.
 */
function random_bytes(int $length) : string {
	return str_repeat("\x2a", $length);
}

/**
 * Returns a fixed client address for security-event assertions.
 *
 * @return string|false Test client address.
 */
function get_client_addr() : string|false {
	return $GLOBALS['validation_client_addr'];
}

/**
 * Captures structured security log entries.
 *
 * @param string $message Log message.
 * @param bool   $output  Whether to print the message.
 * @param string $environ Log subsystem.
 *
 * @return bool Always true for the unit test.
 */
function cacti_log(string $message, bool $output, string $environ) : bool {
	$GLOBALS['validation_security_logs'][] = [$message, $output, $environ];

	return true;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_validate.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/html_validate.php for the validation security-log test.');
}

if (preg_match('/function security_log_input_validation_failure\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract security_log_input_validation_failure() for the validation security-log test.');
}

eval('namespace InputValidationSecurityLogTest;' . $matches[0]);

beforeEach(function () : void {
	$GLOBALS['validation_security_logs']  = [];
	$GLOBALS['validation_client_addr']    = '192.0.2.10';
	$_SERVER['REQUEST_METHOD']            = 'POST';
	$_SERVER['SCRIPT_NAME']               = '/cacti/graphs.php';
});

test('validation failures produce structured correlated security events', function () : void {
	$event_id = security_log_input_validation_failure('graph_id');
	$event    = json_decode($GLOBALS['validation_security_logs'][0][0], true);

	expect($event_id)->toBe(str_repeat('2a', 16))
		->and($event)->toBe([
			'event'          => 'input_validation_failure',
			'event_id'       => $event_id,
			'variable'       => 'graph_id',
			'source_address' => '192.0.2.10',
			'request_method' => 'POST',
			'script'         => 'graphs.php'
		])
		->and($GLOBALS['validation_security_logs'][0][1])->toBeFalse()
		->and($GLOBALS['validation_security_logs'][0][2])->toBe('SECURITY');
});

test('non-scalar variables and missing request metadata are handled safely', function () : void {
	unset($_SERVER['REQUEST_METHOD'], $_SERVER['SCRIPT_NAME']);
	$GLOBALS['validation_client_addr'] = false;

	security_log_input_validation_failure(['secret' => 'not logged']);
	$event = json_decode($GLOBALS['validation_security_logs'][0][0], true);

	expect($event['variable'])->toBe('array')
		->and($event['source_address'])->toBe('')
		->and($event['request_method'])->toBe(PHP_SAPI)
		->and($event['script'])->toBe('')
		->and($GLOBALS['validation_security_logs'][0][0])->not->toContain('not logged');
});

test('validation diagnostics include the structured event correlation ID', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_validate.php');

	expect($source)->toContain('$event_id = security_log_input_validation_failure($variable)')
		->and(substr_count($source, "'Validation Error, Event: ' . \$event_id"))->toBe(3)
		->and(substr_count($source, ". \$variable . \$value . ', Source: '"))->toBe(2)
		->and($source)->toContain("\$source_address = CACTI_CLI ? '' : get_client_addr();");
});
