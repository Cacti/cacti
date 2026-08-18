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

// reports_unserialize_selected_items() lives in lib/reports.php, which the
// reports.php page loads before html_reports.php (the bulk-action call site).
// The per-item type/id guard the bulk-action loop applies before dispatch is
// reproduced here as a pure predicate matching reports_form_actions().

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/reports.php';

/**
 * Mirror of the bulk-action loop guard in reports_form_actions(): an item is
 * dispatchable only when it parses to a known type and a positive numeric id.
 */
function reports_item_is_dispatchable(mixed $report) : bool {
	if (!is_string($report) || strpos($report, '_') === false) {
		return false;
	}

	[$type, $report_id] = explode('_', $report, 2);

	if (!in_array($type, ['reports', 'reportit'], true)) {
		return false;
	}

	return is_numeric($report_id) && (int) $report_id > 0;
}

// =====================================================================
// reports_unserialize_selected_items: type-prefixed payloads survive
// =====================================================================

test('deserializer keeps legitimate type-prefixed report items', function () {
	$items = serialize(['reports_3', 'reportit_7']);

	expect(reports_unserialize_selected_items($items))->toBe(['reports_3', 'reportit_7']);
});

test('deserializer rejects PHP object injection', function () {
	$payload = 'a:1:{i:0;O:8:"stdClass":0:{}}';

	expect(reports_unserialize_selected_items($payload))->toBeFalse();
});

test('deserializer rejects object with plus sign in length', function () {
	$payload = 'a:1:{i:0;O:+8:"stdClass":0:{}}';

	expect(reports_unserialize_selected_items($payload))->toBeFalse();
});

test('deserializer rejects empty, null and non-string input', function () {
	expect(reports_unserialize_selected_items(''))->toBeFalse()
		->and(reports_unserialize_selected_items(null))->toBeFalse()
		->and(reports_unserialize_selected_items(42))->toBeFalse();
});

// =====================================================================
// per-item validation: dispatch only known type plus positive numeric id
// =====================================================================

test('valid report items are dispatchable', function () {
	expect(reports_item_is_dispatchable('reports_3'))->toBeTrue()
		->and(reports_item_is_dispatchable('reportit_7'))->toBeTrue();
});

test('unknown item type is rejected', function () {
	expect(reports_item_is_dispatchable('users_3'))->toBeFalse()
		->and(reports_item_is_dispatchable('settings_1'))->toBeFalse();
});

test('non-numeric or non-positive id is rejected', function () {
	expect(reports_item_is_dispatchable('reports_abc'))->toBeFalse()
		->and(reports_item_is_dispatchable('reports_0'))->toBeFalse()
		->and(reports_item_is_dispatchable('reports_-1'))->toBeFalse();
});

test('injection payload in the id is rejected', function () {
	expect(reports_item_is_dispatchable('reports_3; DROP TABLE reports'))->toBeFalse()
		->and(reports_item_is_dispatchable('reports_3 OR 1=1'))->toBeFalse();
});

test('item without a separator is rejected', function () {
	expect(reports_item_is_dispatchable('reports'))->toBeFalse()
		->and(reports_item_is_dispatchable('3'))->toBeFalse();
});
