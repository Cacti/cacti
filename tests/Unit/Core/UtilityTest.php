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

it('cacti_sizeof handles various inputs', function () {
	expect(cacti_sizeof([1, 2, 3]))->toBe(3);
	expect(cacti_sizeof([]))->toBe(0);
	expect(cacti_sizeof(false))->toBe(0);
	expect(cacti_sizeof(null))->toBe(0);
	expect(cacti_sizeof('not an array'))->toBe(0);
});

it('cacti_count handles various inputs', function () {
	expect(cacti_count([1, 2]))->toBe(2);
	expect(cacti_count(false))->toBe(0);
});
