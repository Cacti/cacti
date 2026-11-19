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

test('slash-containing regexes match without warning', function () {
	$line   = '2026/11/18 - SYSTEM MAINT STATS';
	$filter = '^2026/11/[0-9]+ - system';

	expect(determine_display_log_entry(-1, $line, $filter, true))->toBe($line)
		->and(determine_display_log_entry(-1, $line, $filter, false))->toBeFalse()
		->and(determine_display_log_entry(-1, 'other line', $filter, false))->toBe('other line');
});

test('log filtering falls back when a regex exceeds its match budget', function () {
	$line   = str_repeat('ab', 1500);
	$filter = '^(?:ab)+?$';

	expect(determine_display_log_entry(-1, $line, $filter, true))->toBeFalse()
		->and(determine_display_log_entry(-1, $line, $filter, false))->toBe($line);
});

test('invalid regexes fall back to literal matching without warning', function () {
	$line     = 'SYSTEM NOTE: Interface [missing';
	$warnings = [];
	$handler  = static function (int $level, string $message) use (&$warnings): bool {
		$warnings[] = $message;

		return true;
	};

	set_error_handler($handler, E_WARNING);

	try {
		expect(determine_display_log_entry(-1, $line, '[MISSING', true))->toBe($line)
			->and(determine_display_log_entry(-1, $line, '[MISSING', false))->toBeFalse()
			->and(determine_display_log_entry(-1, $line, '[OTHER', false))->toBe($line)
			->and($warnings)->toBe([]);

		$current_handler = set_error_handler($handler, E_WARNING);
		restore_error_handler();
		expect($current_handler)->toBe($handler);
	} finally {
		restore_error_handler();
	}
});
