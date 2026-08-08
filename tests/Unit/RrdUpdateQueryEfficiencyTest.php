<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

test('RRD updates do not query metadata that is never consumed', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

	expect($source)->not->toBeFalse();

	$start  = strpos($source, 'function rrdtool_function_update(');
	$end    = strpos($source, 'function rrdtool_function_tune(', $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);

	expect($function)->not->toMatch('/\bdb_[a-z_]+\s*\(/i')
		->not->toContain('$unused_data_source_names')
		->not->toContain('$data_template_id')
		->not->toContain('$create_rrd_file')
		->not->toContain('$times = array_keys');
});
