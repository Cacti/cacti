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
 * The RRD Cleaner rescan quotes each RRA file name with db_qstr() when it builds
 * the data_source_purge_temp insert. In the remote storage branch each
 * "path,size,mtime" record is split from the right, so a comma in the path stays
 * with the path, and blank or incomplete records are skipped.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$rrd_cleaner_scan_probe = dirname(__DIR__, 3) . '/fixtures/Core/CollectorCleanupGates.php';

test('the directory scan keeps file names with quotes and commas intact', function () use ($rrd_cleaner_scan_probe) {
	$result = cacti_test_isolated_probe($rrd_cleaner_scan_probe, ['post']);

	expect($result['scan'])->toBe(['a,b.rrd' => 7, "it's.rrd" => 7, 'plain.rrd' => 7]);
});

test('the proxy listing is parsed record by record', function () use ($rrd_cleaner_scan_probe) {
	$result = cacti_test_isolated_probe($rrd_cleaner_scan_probe, ['post']);

	expect($result['listing'])->toBe(['a,b.rrd' => 20, "it's.rrd" => 30, 'one.rrd' => 10]);
});

test('the rescan quotes the RRA file name with db_qstr in both branches', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/rrdcleaner.php');

	expect($source)->toContain("db_qstr(str_replace(\$rra_path, '', \$parts[1]))")
		->and($source)->toContain("db_qstr(str_replace(\$rra_path, '', \$file->getPathname()))")
		->and($source)->not->toContain("\"('\" . str_replace(\$rra_path");
});
