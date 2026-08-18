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
 * Source-scan tests for the get_error / print_source text-mode guard in
 * rrdtool_function_graph() (lib/rrd.php).
 *
 * When an RRD file does not exist and the caller has set either
 * $graph_data_array['get_error'] (CLI/STDERR debug mode) or
 * $graph_data_array['print_source'] (HTML debug view), the function must
 * return a human-readable error string rather than falling through to the
 * rrdtool graph invocation which would produce binary PNG bytes. Returning
 * binary content in these text-output modes corrupts CLI output and renders
 * garbage in the browser debug view.
 *
 * These tests verify the guard by scanning the source of lib/rrd.php.
 * They do not execute rrdtool or require a running Cacti installation.
 */

// --- source helper ---

function getRrdGraphFunctionSource(): string {
	$rrdPhp = file_get_contents(__DIR__ . '/../../lib/rrd.php');
	expect($rrdPhp)->not->toBeFalse('Failed to read lib/rrd.php');

	$start = strpos($rrdPhp, 'function rrdtool_function_graph(');
	expect($start)->not->toBeFalse('rrdtool_function_graph() must exist in lib/rrd.php');

	// Capture enough of the function body to cover both the guard and the
	// rrdtool graph invocation further below.
	return substr($rrdPhp, $start, 80000);
}

// --- the get_error / print_source combined guard must be present ---

test('rrd.php contains the get_error or print_source text-mode guard when RRD file is missing', function () {
	$source = getRrdGraphFunctionSource();

	/*
	 * The guard must test both keys with ||:
	 *   if (isset($graph_data_array['get_error']) || isset($graph_data_array['print_source']))
	 */
	$pattern = '/if\s*\(\s*isset\s*\(\s*\$graph_data_array\s*\[\s*[\'"]get_error[\'"]\s*\]\s*\)\s*\|\|\s*isset\s*\(\s*\$graph_data_array\s*\[\s*[\'"]print_source[\'"]\s*\]\s*\)\s*\)/';

	expect(preg_match($pattern, $source))->toBe(1,
		'The combined get_error || print_source guard must be present in rrdtool_function_graph()'
	);
});

// --- the guard returns a string containing the expected error prefix ---

test('rrd.php text-mode guard returns a string containing "ERROR: RRD file does not exist"', function () {
	$source = getRrdGraphFunctionSource();

	/*
	 * Inside the guard block the return must use __() with the error message.
	 * Match the return statement that follows the guard condition.
	 */
	$pattern = '/isset\s*\(\s*\$graph_data_array\s*\[\s*[\'"]get_error[\'"]\s*\]\s*\)[\s\S]{0,200}return\s+__\s*\(\s*[\'"]ERROR: RRD file does not exist/';

	expect(preg_match($pattern, $source))->toBe(1,
		'The text-mode guard must return an __() string starting with "ERROR: RRD file does not exist"'
	);
});

// --- the guard appears before the rrdtool graph invocation ---

test('rrd.php text-mode guard precedes the rrdtool graph execution call', function () {
	$source = getRrdGraphFunctionSource();

	$guardPos = strpos($source, "isset(\$graph_data_array['get_error']) || isset(\$graph_data_array['print_source'])");
	expect($guardPos)->not->toBeFalse('get_error || print_source guard must exist in rrdtool_function_graph()');

	// The actual rrdtool graph execution happens via rrdtool_execute("graph ...
	// or by building $source_command_line with path_rrdtool . " graph ".
	$graphExecPos = strpos($source, 'rrdtool_execute("graph ');
	if ($graphExecPos === false) {
		$graphExecPos = strpos($source, "rrdtool_execute('graph ");
	}
	if ($graphExecPos === false) {
		// Fall back to the source_command_line build that contains " graph "
		$graphExecPos = strpos($source, '" graph "');
	}

	expect($graphExecPos)->not->toBeFalse('rrdtool graph invocation must exist in rrdtool_function_graph()');
	expect($guardPos)->toBeLessThan($graphExecPos,
		'The text-mode guard must appear before the rrdtool graph execution call'
	);
});

// --- PNG header bytes cannot be returned when get_error is set ---

test('rrd.php does not return PNG header bytes when get_error is set', function () {
	$source = getRrdGraphFunctionSource();

	/*
	 * Once the get_error || print_source guard returns the error string, the
	 * function exits. Verify there is no path that could return the PNG magic
	 * bytes (chr(137).'PNG') inside the guard block itself. We check that the
	 * PNG magic constant does not appear between the opening of the guard and
	 * its closing return statement.
	 *
	 * Approach: extract the guard block and assert it contains no PNG reference.
	 */
	$guardStart = strpos($source, "isset(\$graph_data_array['get_error']) || isset(\$graph_data_array['print_source'])");
	expect($guardStart)->not->toBeFalse('get_error || print_source guard must exist');

	// Grab the immediate vicinity of the guard (the if-block is short).
	$guardRegion = substr($source, $guardStart, 500);

	expect($guardRegion)->not->toContain('PNG',
		'The text-mode guard block must not reference PNG output'
	);
	expect($guardRegion)->not->toContain('chr(137)',
		'The text-mode guard block must not contain the PNG magic byte sequence'
	);
});
