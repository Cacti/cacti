<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$rrdSource = file_get_contents(__DIR__ . '/../../../../lib/rrd.php');

function rrd_info_function_body($source, $name) {
	$start = strpos($source, "function $name(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end === false ? null : $end - $start);
}

test('rrdtool_info2html escapes the free-text header and item cells', function () use ($rrdSource) {
	$body = rrd_info_function_body($rrdSource, 'rrdtool_info2html');

	/* the RRD filename (the stored data_source_path) is rendered in the header value cell */
	expect($body)->toContain("form_selectable_ecell(\$value, 'value'")
		->and($body)->toContain("form_selectable_ecell(\$key, 'name'")
		->and($body)->toContain("form_selectable_ecell((isset(\$value['type'])")
		->and($body)->toContain("form_selectable_ecell((isset(\$value['cf'])");

	/* the non-numeric min/max fall-throughs must not stay raw */
	expect($body)->not->toContain("form_selectable_cell(\$value['min'], 'min', '', 'color:red");
	expect($body)->not->toContain("form_selectable_cell(\$value['max'], 'max', '', 'color:red");
});

test('rrdtool_tune_print_leaves escapes the formRow line', function () use ($rrdSource) {
	$body = rrd_info_function_body($rrdSource, 'rrdtool_tune_print_leaves');

	expect($body)->toContain("html_escape(\$line)")
		->and($body)->not->toContain("'<div class=\\'formRow\\'>' . \$line");
});

test('rrdtool_tune_print_html escapes the suggested tune and resize commands', function () use ($rrdSource) {
	$body = rrd_info_function_body($rrdSource, 'rrdtool_tune_print_html');

	expect($body)->toContain("html_escape(\$rrd_path . ' tune ' . \$line)")
		->and($body)->toContain("html_escape(\$rrd_path . ' resize ' . \$line)")
		->and($body)->toContain('html_escape($rrd_file)');
});
