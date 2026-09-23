<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Validates if the given value is equal to the comparison value. Used as part of Cacti's lib
 * functionality.
 *
 * @param mixed $value The value to be validated.
 * @param mixed $c_value The value to compare against.
 *
 * @return void No value is returned.
 */
function input_validate_input_equals($value, $c_value) {
	if ($value != $c_value) {
		die_html_input_error();
	}
}

/**
 * Validates if the given value is a number. Used as part of Cacti's lib functionality.
 *
 * @param mixed $value The value to be validated.
 *
 * @return void No value is returned.
 */
function input_validate_input_number($value) {
	if ((!is_numeric($value)) && ($value != '')) {
		die_html_input_error();
	}
}

/**
 * Validates the input value against a given regular expression. Used as part of Cacti's lib
 * functionality.
 *
 * @param string $value The input value to be validated.
 * @param string $regex The regular expression to validate the input value against.
 *
 * @return void No value is returned.
 */
function input_validate_input_regex($value, $regex) {
	if ($value != null && $value != '' && (!preg_match('/' . $regex . '/', $value))) {
		die_html_input_error();
	}
}

/**
 * Logs an input validation error for a given variable. This function logs a debug backtrace
 * message indicating that input validation was not performed for the specified variable. Used as
 * part of Cacti's lib functionality.
 *
 * @param string $variable The name of the variable for which input validation was not performed.
 *
 * @return void No value is returned.
 */
function html_log_input_error($variable) {
	cacti_debug_backtrace("Input Validation Not Performed for '$variable'");
}

/**
 * Writes a structured security event for an input validation failure. Rejected values and request
 * payloads are deliberately excluded to avoid copying credentials or other sensitive input into
 * the security log. Used as part of Cacti's lib functionality.
 *
 * @param mixed $variable Name of the rejected input variable.
 *
 * @return string Correlation identifier for related diagnostic log entries.
 */
function security_log_input_validation_failure($variable) {
	try {
		$event_id = bin2hex(random_bytes(16));
	} catch (\Exception $e) {
		$event_id = substr(hash('sha256', uniqid('', true) . microtime(true)), 0, 32);
	}

	$source_address = CACTI_CLI ? '' : get_client_addr();
	$event          = array(
		'event'          => 'input_validation_failure',
		'event_id'       => $event_id,
		'variable'       => is_scalar($variable) ? (string) $variable : gettype($variable),
		'source_address' => $source_address === false ? '' : (string) $source_address,
		'request_method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : PHP_SAPI,
		'script'         => isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : ''
	);

	cacti_log(json_encode($event, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), false, 'SECURITY');

	return $event_id;
}

/**
 * Terminates the script execution and outputs an error message for HTML input validation errors.
 * Used as part of Cacti's lib functionality.
 *
 * @param mixed $variable The name of the variable that caused the validation error.
 * @param mixed $value The value of the variable that caused the validation error.
 * @param string $message An optional custom error message.
 *
 * @return void No value is returned.
 */
function die_html_input_error($variable = '', $value = '', $message = '') {
	global $config;
	$event_id = security_log_input_validation_failure($variable);

	if ($message == '') {
		$message = __esc('Validation error for variable %s with a value of %s.  See backtrace below for more details.', $variable, html_escape($value));
	}

	if (isset_request_var('json')) {
		cacti_debug_backtrace('Validation Error, Event: ' . $event_id . ($variable != '' ? ', Variable:' . html_escape($variable):'') . ($value != '' ? ', Value:' . html_escape($value):'') . ', Source: ' . get_client_addr() . ', Request: ' . json_encode($_REQUEST), false);
		print json_encode(
			array(
				'status' => '500',
				'statusText' => __('Validation Error'),
				'responseText' => $message
			)
		);
	} else {
		cacti_debug_backtrace('Validation Error, Event: ' . $event_id . ($variable != '' ? ', Variable:' . html_escape($variable):'') . ($value != '' ? ', Value:' . html_escape($value):'') . ', Source: ' . get_client_addr() . ', Request: ' . json_encode($_REQUEST), true);

		print "<table style='width:100%;text-align:center;'><tr><td>$message</td></tr></table>";
		bottom_footer();
	}

	exit;
}
