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
 * Unit coverage for rrd_check_path() traversal and NUL rejection. The base
 * argument is left empty so these cases exercise the syntactic checks only,
 * independent of the filesystem.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('rejects directory traversal', function () {
	expect(rrd_check_path('/rra/../etc/passwd', ''))->toBeFalse();
	expect(rrd_check_path('/rra/a/../b.rrd', ''))->toBeFalse();
	expect(rrd_check_path('../rra/x.rrd', ''))->toBeFalse();
	expect(rrd_check_path('..', ''))->toBeFalse();
});

test('rejects a NUL byte and empty path', function () {
	expect(rrd_check_path("/rra/x\x00.rrd", ''))->toBeFalse();
	expect(rrd_check_path('', ''))->toBeFalse();
});

test('allows an ordinary path when no base is enforced', function () {
	expect(rrd_check_path('/var/lib/cacti/rra/site/traffic.rrd', ''))->toBeTrue();
	expect(rrd_check_path('rra/1/2.rrd', ''))->toBeTrue();
});
