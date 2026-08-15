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
 * Tests for automation_get_next_host() in lib/api_automation.php.
 *
 * The octet carry loop produced invalid octets (e.g. 10.1.272.113) for
 * offsets that cross more than one octet boundary. Every result must be a
 * valid dotted quad. lib/api_automation.php only defines functions at load
 * time, so it can be included without a database or full Cacti bootstrap.
 */

require_once dirname(__DIR__, 4) . '/lib/api_automation.php';

/* $total is only used to gate the early return; any value above $count works. */
function next_host($start, $count) {
	return automation_get_next_host($start, PHP_INT_MAX, $count, '10.0.0.0/8');
}

test('offset within a single octet is unchanged', function () {
	expect(next_host('10.0.0.1', 1000))->toBe('10.0.3.233');
});

test('offset crossing two octet boundaries stays a valid quad', function () {
	expect(next_host('10.0.0.1', 70000))->toBe('10.1.17.113');
});

test('offset of a full /8 increments the leading octet', function () {
	/* $total must match the range so $count < $total, as real callers pass it;
	 * a /8 offset against a /8 total would hit the $count == $total early
	 * return, so use a /7 (33554432 addresses) to keep the offset in range. */
	expect(automation_get_next_host('10.0.0.0', 33554432, 16777216, '10.0.0.0/7'))->toBe('11.0.0.0');
});

test('carry normalizes an overflowing final octet', function () {
	expect(next_host('10.0.0.200', 100))->toBe('10.0.1.44');
});
