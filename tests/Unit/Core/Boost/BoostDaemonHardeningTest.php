<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$boostSource = file_get_contents(__DIR__ . '/../../../../lib/boost.php');

expect($boostSource)->not->toBeFalse();

test('boost_graph_set_file delegates cache publication to the atomic writer', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_set_file(');
	expect($start)->not->toBeFalse();

	$body = substr($boostSource, $start, 2500);

	expect($body)->toContain('boost_atomic_write_cache($cache_file, $output)')
		->and($body)->not->toContain('file_put_contents($cache_file');
});

test('boost_graph_cache_check casts IDs to int', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_cache_check(');
	expect($start)->not->toBeFalse();

	$body = substr($boostSource, $start, 500);
	expect($body)->toContain('$local_graph_id = (int)$local_graph_id');
	expect($body)->toContain('$rra_id         = (int)$rra_id');
});

test('boost_graph_set_file casts IDs to int', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_graph_set_file(');
	expect($start)->not->toBeFalse();

	$body = substr($boostSource, $start, 500);
	expect($body)->toContain('(int)$local_graph_id');
	expect($body)->toContain('(int)$rra_id');
});

test('RRD update boundaries reject unsafe paths templates and values', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_rrdtool_function_update(');
	expect($start)->not->toBeFalse();

	$body = substr($boostSource, $start, 5000);

	expect($body)->toContain('cacti_rrdtool_valid_path($rrd_path)')
		->and($body)->toContain('cacti_rrdtool_valid_ds_template($rrd_update_template)')
		->and($body)->toContain('cacti_has_control_chars($rrd_update_values)');
});

test('on-demand processing validates its identifier before building temporary table names', function () use ($boostSource) {
	$start = strpos($boostSource, 'function boost_process_poller_output(');
	expect($start)->not->toBeFalse();

	$body = substr($boostSource, $start, 1000);

	expect($body)->toContain('$local_data_id = (int) $local_data_id;')
		->and($body)->toContain('if ($local_data_id <= 0)');
});
