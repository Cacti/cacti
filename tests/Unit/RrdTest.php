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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/rrd.php';

it('escapes rrdtool strings correctly', function () {
	expect(rrdtool_escape_string('Normal String'))->toBe('Normal String');
	expect(rrdtool_escape_string('String with "quotes"'))->toBe('String with \"quotes\"');
	expect(rrdtool_escape_string('String with :colons:'))->toBe('String with \:colons\:');
});

it('escapes percent signs only when ignore_percent is false', function () {
	// Default ($ignore_percent = true): % is left as-is.
	expect(rrdtool_escape_string('100%', true))->toBe('100%');
	// Explicit false: % is doubled to %% for rrdtool format escaping.
	expect(rrdtool_escape_string('100%', false))->toBe('100%%');
});
