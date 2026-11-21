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

	$lines = [
		"2026/11/19 00:12:01 - SYSTEM WARN: Device[123] eth0 down\n",
		'2026/11/19 13:00:00 - error: path /var/log/cacti.log',
		"tab\tseparated_word 42",
		'',
		"a\0b",
	];
	$patterns = ['ERROR|WARN', '^2026/11/19 0[0-3]', 'Device\[12[0-9]\]', '/var/[a-z]+', '\d+$', '\s\w+_\w+', '(?:eth|wan)\d*\s', '[^a-z0-9 ]', 'a.b', '^$'];

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
