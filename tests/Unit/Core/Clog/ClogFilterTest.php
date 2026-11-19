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

test('log search falls back when a regex exceeds its match budget', function () {
	$line = str_repeat('ab', 1500);

	expect(determine_display_log_entry(-1, $line, '^(?:ab)+?$', true))->toBeFalse()
		->and(determine_display_log_entry(-1, $line, '^(?:ab)+?$', false))->toBe($line);
});
