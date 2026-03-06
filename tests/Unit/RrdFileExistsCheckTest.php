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
 * Tests for the rrdtool_function_graph() file-existence early return.
 *
 * Prior to the fix for issue #6530, the early return when an RRD file
 * did not exist was gated on export_realtime or export_csv mode:
 *
 *   if (!rrdtool_file_exists(...) && (isset(...'export_realtime']) || isset(...'export_csv']))) {
 *
 * This meant normal graph rendering would skip the check and fall through
 * to rrdtool execution, which retried 5 times before failing. For new
 * graphs where the poller hasn't run yet, this caused unnecessary delays.
 *
 * The fix simplifies the condition to apply to all rendering modes:
 *
 *   if (!rrdtool_file_exists(...)) {
 *
 * These tests verify the fix by scanning the source code of lib/rrd.php.
 *
 * See: https://github.com/Cacti/cacti/issues/6530
 */

// --- source scanning helper ---

function getRrdFileExistsBlock(): string {
	$rrdPhp = file_get_contents(__DIR__ . '/../../lib/rrd.php');
	expect($rrdPhp)->not->toBeFalse('Failed to read lib/rrd.php');

	/* extract the rrdtool_function_graph function body */
	$start = strpos($rrdPhp, 'function rrdtool_function_graph(');
	expect($start)->not->toBeFalse('rrdtool_function_graph() must exist in lib/rrd.php');

	/* grab a region around the file-existence check (lines 1800-1850 area) */
	$region = substr($rrdPhp, $start, 20000);

	return $region;
}

// --- the condition no longer restricts the check to export_realtime/export_csv ---

test('file existence check does not reference export_realtime or export_csv', function () {
	$source = getRrdFileExistsBlock();

	/*
	 * Find the rrdtool_file_exists call and its surrounding if-statement.
	 * The old code had: !rrdtool_file_exists(...) && (isset(...export_realtime...
	 * The fix removes the && clause entirely.
	 */
	$pattern = '/if\s*\(\s*!rrdtool_file_exists\([^)]+\)\s*&&\s*\(isset\(\$graph_data_array\[.export_realtime.\]\)/';

	expect(preg_match($pattern, $source))->toBe(0,
		'The export_realtime/export_csv gate should have been removed from the file-existence check'
	);
});

// --- the source contains the simplified condition ---

test('file existence check uses simplified unconditional form', function () {
	$source = getRrdFileExistsBlock();

	/*
	 * The fixed code should contain a clean check:
	 *   if (!rrdtool_file_exists($data_source_path, $rrdtool_pipe)) {
	 *       return false;
	 *   }
	 * Match the if-line without any && continuation.
	 */
	$pattern = '/if\s*\(\s*!rrdtool_file_exists\(\$data_source_path,\s*\$rrdtool_pipe\)\s*\)\s*\{/';

	expect(preg_match($pattern, $source))->toBe(1,
		'The file-existence check should be a simple unconditional test'
	);
});

// --- the early return pattern applies to all rendering modes ---

test('file existence check block contains an unconditional return false', function () {
	$source = getRrdFileExistsBlock();

	/*
	 * Verify the pattern: the unconditional file-existence check block
	 * contains a return false. The block may also contain a log call before
	 * the return, so match anything inside the braces up to return false.
	 */
	$pattern = '/if\s*\(\s*!rrdtool_file_exists\(\$data_source_path,\s*\$rrdtool_pipe\)\s*\)\s*\{[\s\S]*?return\s+false;/s';

	expect(preg_match($pattern, $source))->toBe(1,
		'return false must be present in the file-existence check block'
	);
});

// --- negative: the old gated pattern must not appear anywhere in the function ---

test('no rrdtool_file_exists call is gated by export mode checks', function () {
	$source = getRrdFileExistsBlock();

	/*
	 * Broader check: no rrdtool_file_exists usage should be combined
	 * with export_realtime or export_csv via && in the same condition.
	 */
	$pattern = '/rrdtool_file_exists\b.*&&.*export_(realtime|csv)/';

	expect(preg_match($pattern, $source))->toBe(0,
		'No file-existence check should be gated by export mode'
	);
});
