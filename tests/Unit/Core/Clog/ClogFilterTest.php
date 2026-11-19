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

test('log search does not interpret regex metacharacters', function () {
	$line   = 'SYSTEM NOTE: Interface 42';
	$filter = 'Interface [0-9]+';

	expect(determine_display_log_entry(-1, $line, $filter, true))->toBeFalse()
		->and(determine_display_log_entry(-1, $line, $filter, false))->toBe($line)
		->and(determine_display_log_entry(-1, $filter, $filter, true))->toBe($filter);
});
