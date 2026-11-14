<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$rrdSource = file_get_contents(__DIR__ . '/../../../../lib/rrd.php');

/*
 * rrdtool_function_graph() returns the "RRD file does not exist" message in the
 * get_error and print_source text-output modes, and graph.php prints that return
 * verbatim for the Graph Properties debug view. The stored data_source_path is
 * saved with only a CR/LF filter, so it must be html_escape()'d before it is
 * substituted into the message.
 */
test('the missing-RRD error escapes the data source path', function () use ($rrdSource) {
	expect($rrdSource)->not->toBeFalse();

	expect($rrdSource)->toContain("__('ERROR: RRD file does not exist: %s', html_escape(\$data_source_path))");

	/* the raw reflection must be gone */
	expect($rrdSource)->not->toContain("__('ERROR: RRD file does not exist: %s', \$data_source_path)");
});
