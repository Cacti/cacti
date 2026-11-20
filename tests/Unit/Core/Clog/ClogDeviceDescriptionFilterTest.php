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

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

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

test('a comma/space separated Device[] id list resolves every description', function () {
	$line = "14/09/2026 15:49:21 - PCOMMAND Device[11804, 11805] NOTE: Recache Event Detected for Devices\n";

	$host_descriptions = array('11804' => 'se-boi-3504', '11805' => 'se-boi-3505');

	expect(determine_display_log_entry(-1, $line, 'se-boi-3504', true, $host_descriptions))->toBe($line)
		->and(determine_display_log_entry(-1, $line, 'se-boi-3505', true, $host_descriptions))->toBe($line);
});

test('literal log search matches a date and punctuation regardless of case', function () {
	$line   = '2026/11/18 - SYSTEM NOTE: Interface [missing; retry]';
	$filter = '11/18 - system note: interface [MISSING;';

	expect(determine_display_log_entry(-1, $line, $filter, true))->toBe($line)
		->and(determine_display_log_entry(-1, $line, $filter, false))->toBeFalse()
		->and(determine_display_log_entry(-1, 'other line', $filter, false))->toBe('other line');
});

test('log search preserves regex operators, uppercase escapes and delimiters', function () {
	$line   = "2026/11/18 - Interface 'up'";
	$filter = "^2026/11/[0-9]+ - interface '\\D+'$";

	expect(determine_display_log_entry(-1, $line, $filter, true))->toBe($line)
		->and(determine_display_log_entry(-1, $line, $filter, false))->toBeFalse()
		->and(determine_display_log_entry(-1, 'SYSTEM [missing', '[MISSING', true))->toBe('SYSTEM [missing');
});

test('log search matches long repetitions and handles alternatives and negated classes', function () {
	$line = str_repeat('ab', 1500);

	expect(determine_display_log_entry(-1, $line, '^(?:ab)+?$', true))->toBe($line)
		->and(determine_display_log_entry(-1, "eth7\n", '^(eth|wan)[^a-z]$', true))->toBe("eth7\n")
		->and(determine_display_log_entry(-1, 'etha', '^(eth|wan)[^a-z]$', true))->toBeFalse();
});

test('unsupported PCRE syntax is matched as literal text', function () {
	$filter = '(?=ready)';

	expect(determine_display_log_entry(-1, 'ready', $filter, true))->toBeFalse()
		->and(determine_display_log_entry(-1, 'NOTE (?=READY)', $filter, true))->toBe('NOTE (?=READY)');
});

test('accepted regex searches run through PCRE and agree with the NFA', function () {
	require_once dirname(__DIR__, 4) . '/lib/clog_search.php';

	$lines = array(
		"2026/11/19 00:12:01 - SYSTEM WARN: Device[123] eth0 down\n",
		'2026/11/19 13:00:00 - error: path /var/log/cacti.log',
		"tab\tseparated_word 42",
		'',
		"a\0b",
	);
	$patterns = array('ERROR|WARN', '^2026/11/19 0[0-3]', 'Device\[12[0-9]\]', '/var/[a-z]+', '\d+$', '\s\w+_\w+', '(?:eth|wan)\d*\s', '[^a-z0-9 ]', 'a.b', '^$');

	foreach ($patterns as $filter) {
		$pattern = CactiLogSearchPattern::compile($filter);

		expect($pattern)->not->toBeNull()
			->and((fn () => $this->regex)->call($pattern))->not->toBeNull();

		foreach ($lines as $line) {
			expect($pattern->matches($line))->toBe((fn () => $this->nfaMatches($line))->call($pattern));
		}
	}
});

test('a line that exhausts the PCRE budget is decided by the NFA without a warning', function () {
	require_once dirname(__DIR__, 4) . '/lib/clog_search.php';

	$pattern  = CactiLogSearchPattern::compile('(a+)+$');
	$warnings = 0;

	set_error_handler(function () use (&$warnings) {
		$warnings++;

		return true;
	});

	$miss = $pattern->matches(str_repeat('a', 40) . 'b');
	$hit  = $pattern->matches(str_repeat('a', 40));

	restore_error_handler();

	expect($miss)->toBeFalse()
		->and($hit)->toBeTrue()
		->and($warnings)->toBe(0);
});
