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
 * Tests for the Log Viewer search matcher. Accepted regex searches run through
 * PCRE under a match limit and must agree with the NFA; a line that reaches the
 * limit is decided by the NFA without writing a warning.
 */

require_once dirname(__DIR__, 2) . '/lib/clog_search.php';

test('accepted regex searches run through PCRE and agree with the NFA', function () {
	$lines = array(
		"2026/11/19 00:12:01 - SYSTEM WARN: Device[123] eth0 down\n",
		'2026/11/19 13:00:00 - error: path /var/log/cacti.log',
		"tab\tseparated_word 42",
		'',
		"a\0b",
	);
	$patterns = array('ERROR|WARN', '^2026/11/19 0[0-3]', 'Device\[12[0-9]\]', '/var/[a-z]+', '\d+$', '\s\w+_\w+', '(?:eth|wan)\d*\s', '[^a-z0-9 ]', 'a.b', '^$');

	$regex = function () {
		return $this->regex;
	};
	$nfa = function ($line) {
		return $this->nfaMatches($line);
	};

	foreach ($patterns as $filter) {
		$pattern = CactiLogSearchPattern::compile($filter);

		expect($pattern)->not->toBeNull();
		expect($regex->call($pattern))->not->toBeNull();

		foreach ($lines as $line) {
			expect($pattern->matches($line))->toBe($nfa->call($pattern, $line));
		}
	}
});

test('a line that exhausts the PCRE budget is decided by the NFA without a warning', function () {
	$pattern  = CactiLogSearchPattern::compile('(a+)+$');
	$warnings = 0;

	set_error_handler(function () use (&$warnings) {
		$warnings++;

		return true;
	});

	$miss = $pattern->matches(str_repeat('a', 40) . 'b');
	$hit  = $pattern->matches(str_repeat('a', 40));

	restore_error_handler();

	expect($miss)->toBeFalse();
	expect($hit)->toBeTrue();
	expect($warnings)->toBe(0);
});
