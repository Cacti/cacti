<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: on the aggregate_graphs action-confirmation render, local_graph_id
 * is not integer-validated (the validating branches exit first), so it must be
 * html_escape()'d before it is reflected into the hidden input value attribute.
 */

$source = file_get_contents(dirname(__DIR__, 4) . '/aggregate_graphs.php');

test('local_graph_id is html_escaped where it is reflected into the hidden input', function () use ($source) {
	expect($source)->not->toBeFalse();
	expect($source)->toContain("html_escape(get_nfilter_request_var('local_graph_id'))");
	// the raw reflection must be gone
	expect($source)->not->toContain("? get_nfilter_request_var('local_graph_id'):0");
});
