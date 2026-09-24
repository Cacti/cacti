<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for issue #8020.
 *
 * The Log viewer's Filter box only ever matched the raw log line, which for a
 * device only ever contains its numeric id (e.g. "Device[11804]"), never its
 * description. determine_display_log_entry() now also matches a resolved
 * "Device[id] (description)" form when a host id => description map is supplied.
 *
 * @group regression
 */

require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/html_utility.php';

if (!function_exists('api_plugin_is_enabled')) {
	function api_plugin_is_enabled($plugin) {
		return false;
	}
}

$line = "14/09/2026 15:49:21 - PCOMMAND Device[11804] NOTE: Recache Event Detected for Device\n";

test('filtering by a device description matches once the id is resolved', function () use ($line) {
	$host_descriptions = array('11804' => 'se-boi-3504');

	expect(determine_display_log_entry(-1, $line, 'se-boi-3504', true, $host_descriptions))->toBe($line);
});

test('filtering by a device description fails without the resolved map (the reported bug)', function () use ($line) {
	expect(determine_display_log_entry(-1, $line, 'se-boi-3504', true, array()))->toBeFalse();
});

test('filtering by the numeric device id still works', function () use ($line) {
	$host_descriptions = array('11804' => 'se-boi-3504');

	expect(determine_display_log_entry(-1, $line, '11804', true, $host_descriptions))->toBe($line);
});

test('an unmapped device id does not error and simply fails to match its description', function () use ($line) {
	expect(determine_display_log_entry(-1, $line, 'se-boi-3504', true, array()))->toBeFalse();
});

test('lines without a Device[] tag are unaffected by the host description map', function () {
	$line = "14/09/2026 10:29:35 - PCOMMAND NOTE: No Poller Commands found for processing\n";

	expect(determine_display_log_entry(-1, $line, 'no poller commands', true, array('11804' => 'se-boi-3504')))->toBe($line)
		->and(determine_display_log_entry(-1, $line, 'se-boi-3504', true, array('11804' => 'se-boi-3504')))->toBeFalse();
});
