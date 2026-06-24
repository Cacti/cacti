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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// =====================================================================
// is_valid_pathname traversal guard
//
// The guard must reject '..' traversal in unix and Windows drive-relative
// forms. "C:..\rra" places ':' before '..', so the separator-only check
// missed it; ':' now counts as a boundary too. Benign filenames that merely
// contain '..' (no separator either side) stay valid.
// =====================================================================

test('is_valid_pathname rejects traversal', function (string $path) {
	expect(is_valid_pathname($path))->toBeFalse();
})->with([
	'unix'                => ['../etc'],
	'win drive backslash' => ['C:..\\rra'],
	'win drive slash'     => ['C:../rra'],
	'nested'              => ['a/../b'],
]);

test('is_valid_pathname accepts benign names containing dots', function (string $path) {
	expect(is_valid_pathname($path))->toBeTrue();
})->with([
	'double dot suffix' => ['file..bak'],
	'double dot middle' => ['a..b'],
	'rrd file'          => ['/var/lib/rrd/host.rrd'],
]);
