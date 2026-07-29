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

/*
 * Integration coverage for reports_log_and_notify() (lib/reports.php).
 * tests/Unit/ReportsNotificationPayloadGuardTest.php only checks that the
 * is_array() guards appear before the foreach/switch in the source; it never
 * calls the function. This test drives the real function end to end -
 * decoding a stored reports_queued.notification payload, validating it, and
 * dispatching to mailer() - with the DB and mailer() layer stubbed, the same
 * way tests/integration/AuthSystemRegressionIntegrationTest.php stubs the DB
 * layer for lib/auth.php. It catches a regression that would let a malformed
 * or malicious notification payload reach mailer() with the wrong arguments,
 * or fatal instead of logging and moving on to sql_save().
 */

$root = dirname(__DIR__, 2);

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return ($array === false || !is_array($array)) ? 0 : sizeof($array);
	}
}

if (!function_exists('__')) {
	function __($text, ...$args) {
		return $args ? vsprintf($text, $args) : $text;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return $GLOBALS['reports_integration_config'][$name] ?? '';
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $output = false, $environ = 'REPORTS') {
		$GLOBALS['reports_integration_log'][] = $message;
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = []) {
		if (str_contains($sql, 'FROM reports_queued')) {
			return $GLOBALS['reports_integration_report'] ?? [];
		}

		return [];
	}
}

if (!function_exists('mailer')) {
	function mailer($from, $to, $cc, $bcc, $reply_to, $subject, $html, $text, $attachments, $headers) {
		$GLOBALS['reports_integration_mailer_calls'][] = compact('from', 'to', 'cc', 'bcc', 'reply_to', 'subject');

		return true;
	}
}

if (!function_exists('sql_save')) {
	function sql_save($save, $table) {
		$GLOBALS['reports_integration_sql_save'][] = ['save' => $save, 'table' => $table];

		return true;
	}
}

require_once $root . '/lib/reports.php';

beforeEach(function () {
	$GLOBALS['reports_integration_config']       = [];
	$GLOBALS['reports_integration_log']          = [];
	$GLOBALS['reports_integration_mailer_calls']  = [];
	$GLOBALS['reports_integration_sql_save']     = [];
	$GLOBALS['reports_integration_report']       = [
		'name'         => 'Nightly Report',
		'request_type' => 1,
		'requested_by' => 'admin',
		'requested_id' => 1,
	];
});

function invoke_reports_log_and_notify(string $notification) : void {
	$GLOBALS['reports_integration_report']['notification'] = $notification;

	$raw_data  = [];
	$oput_raw  = '<html></html>';
	$oput_html = '<html></html>';
	$oput_text = '';

	reports_log_and_notify(1, time() - 5, 'html', 'reports', 1, 'Nightly Report', $raw_data, $oput_raw, $oput_html, $oput_text);
}

test('a notification payload that decodes to a non-array is logged and skipped, not iterated', function () {
	invoke_reports_log_and_notify('"just a string"');

	expect($GLOBALS['reports_integration_mailer_calls'])->toBeEmpty()
		->and($GLOBALS['reports_integration_log'])->toHaveCount(1)
		->and($GLOBALS['reports_integration_log'][0])->toContain('invalid notification payload');

	// the function must still reach sql_save() afterwards instead of fataling.
	expect($GLOBALS['reports_integration_sql_save'])->toHaveCount(1);
});

test('a per-type entry that decodes to a non-array is logged and skipped, not dispatched', function () {
	invoke_reports_log_and_notify(json_encode([
		'email' => 'attacker-controlled-string-not-an-array',
	]));

	expect($GLOBALS['reports_integration_mailer_calls'])->toBeEmpty()
		->and($GLOBALS['reports_integration_log'])->toHaveCount(1)
		->and($GLOBALS['reports_integration_log'][0])->toContain("invalid notification data for type 'email'");

	expect($GLOBALS['reports_integration_sql_save'])->toHaveCount(1);
});

test('a well-formed email notification still dispatches to mailer() with the right recipients', function () {
	invoke_reports_log_and_notify(json_encode([
		'email' => [
			'to_email' => 'ops@example.com',
			'cc_email' => 'lead@example.com',
		],
	]));

	expect($GLOBALS['reports_integration_log'])->toBeEmpty()
		->and($GLOBALS['reports_integration_mailer_calls'])->toHaveCount(1)
		->and($GLOBALS['reports_integration_mailer_calls'][0]['to'])->toBe('ops@example.com')
		->and($GLOBALS['reports_integration_mailer_calls'][0]['cc'])->toBe('lead@example.com');

	expect($GLOBALS['reports_integration_sql_save'])->toHaveCount(1);
});

test('mixed valid and malformed entries in the same payload are handled independently', function () {
	invoke_reports_log_and_notify(json_encode([
		'email'   => ['to_email' => 'ops@example.com'],
		'unknown' => 'not-an-array',
	]));

	expect($GLOBALS['reports_integration_mailer_calls'])->toHaveCount(1)
		->and($GLOBALS['reports_integration_log'])->toHaveCount(1)
		->and($GLOBALS['reports_integration_log'][0])->toContain("invalid notification data for type 'unknown'");
});
