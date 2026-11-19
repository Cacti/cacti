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

use PHPUnit\Framework\TestCase;

final class ClogFilterTest extends TestCase {
	public function testSlashContainingRegexMatchesWithoutWarning(): void {
		$line   = '2026/11/18 - SYSTEM MAINT STATS';
		$filter = '^2026/11/[0-9]+ - system';

		self::assertSame($line, determine_display_log_entry(-1, $line, $filter, true));
		self::assertFalse(determine_display_log_entry(-1, $line, $filter, false));
		self::assertSame('other line', determine_display_log_entry(-1, 'other line', $filter, false));
	}

	public function testInvalidRegexFallsBackToLiteralMatchingWithoutWarning(): void {
		$line     = 'SYSTEM NOTE: Interface [missing';
		$warnings = [];
		$handler  = static function (int $level, string $message) use (&$warnings): bool {
			$warnings[] = $message;

			return true;
		};

		set_error_handler($handler, E_WARNING);

		try {
			self::assertSame($line, determine_display_log_entry(-1, $line, '[MISSING', true));
			self::assertFalse(determine_display_log_entry(-1, $line, '[MISSING', false));
			self::assertSame($line, determine_display_log_entry(-1, $line, '[OTHER', false));
			self::assertSame([], $warnings);

			$current_handler = set_error_handler($handler, E_WARNING);
			restore_error_handler();
			self::assertSame($handler, $current_handler);
		} finally {
			restore_error_handler();
		}
	}
}
