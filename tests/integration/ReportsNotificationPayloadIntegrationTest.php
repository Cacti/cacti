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
 * tests/Unit/Security/InputValidation/ReportsNotificationPayloadGuardTest.php only checks that the
 * is_array() guards appear before the foreach/switch in the source; it never
 * calls the function. This test drives the real function end to end -
 * decoding a stored reports_queued.notification payload, validating it, and
 * dispatching to mailer() - with the DB and mailer() layer stubbed, the same
 * way tests/integration/AuthSystemRegressionIntegrationTest.php stubs the DB
 * layer for lib/auth.php. It catches a regression that would let a malformed
 * or malicious notification payload reach mailer() with the wrong arguments,
 * or fatal instead of logging and moving on to sql_save().
 */

require_once CACTI_PATH_TESTS . '/Helpers/IsolatedProbe.php';

$probe = __DIR__ . '/fixtures/reports_notification_payload_probe.php';

test('a notification payload that decodes to a non-array is logged and skipped, not iterated', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, ['"just a string"']);

	expect($verdict['mailer_calls'])->toBeEmpty()
		->and($verdict['log'])->toHaveCount(1)
		->and($verdict['log'][0])->toContain('invalid notification payload');

	// the function must still reach sql_save() afterwards instead of fataling.
	expect($verdict['sql_save'])->toHaveCount(1);
});

test('a per-type entry that decodes to a non-array is logged and skipped, not dispatched', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, [json_encode([
		'email' => 'attacker-controlled-string-not-an-array',
	])]);

	expect($verdict['mailer_calls'])->toBeEmpty()
		->and($verdict['log'])->toHaveCount(1)
		->and($verdict['log'][0])->toContain("invalid notification data for type 'email'");

	expect($verdict['sql_save'])->toHaveCount(1);
});

test('a well-formed email notification still dispatches to mailer() with the right recipients', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, [json_encode([
		'email' => [
			'to_email' => 'ops@example.com',
			'cc_email' => 'lead@example.com',
		],
	])]);

	expect($verdict['log'])->toBeEmpty()
		->and($verdict['mailer_calls'])->toHaveCount(1)
		->and($verdict['mailer_calls'][0]['to'])->toBe('ops@example.com')
		->and($verdict['mailer_calls'][0]['cc'])->toBe('lead@example.com');

	expect($verdict['sql_save'])->toHaveCount(1);
});

test('mixed valid and malformed entries in the same payload are handled independently', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, [json_encode([
		'email'   => ['to_email' => 'ops@example.com'],
		'unknown' => 'not-an-array',
	])]);

	expect($verdict['mailer_calls'])->toHaveCount(1)
		->and($verdict['log'])->toHaveCount(1)
		->and($verdict['log'][0])->toContain("invalid notification data for type 'unknown'");
});
