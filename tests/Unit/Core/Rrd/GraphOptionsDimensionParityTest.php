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

use Cacti\Rrd\Graph\GraphOptions;

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

// rrdtool_function_graph() resolves the caller's options before building the
// command. Validation must not change which --height and --width the graph gets:
// the builder only honors an override spelled as plain digits, and falls back to
// the graph template's size for any other numeric spelling.
test('validated graph sizes build the same command as the caller supplied', function (mixed $size) {
	global $config;

	$savedConfig  = $config;
	$savedSession = $_SESSION;
	$savedImages  = $GLOBALS['image_types'] ?? null;

	try {
		$GLOBALS['image_types'] = [1 => 'PNG', 3 => 'SVG'];
		$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];

		$graph = [
			'auto_scale'          => '',
			'auto_scale_opts'     => '1',
			'lower_limit'         => '',
			'upper_limit'         => '',
			'auto_scale_log'      => '',
			'scale_log_units'     => '',
			'auto_scale_rigid'    => '',
			'unit_value'          => '',
			'unit_exponent_value' => '',
			'height'              => '120',
			'width'               => '500',
			'image_format_id'     => 1,
		];

		$raw      = ['graph_height' => $size, 'graph_width' => $size];
		$resolved = GraphOptions::resolve($raw);
		$legacy   = $graph;
		$current  = $graph;

		$expected = rrd_function_process_graph_options(1700000000, 1700003600, $legacy, $raw);
		$actual   = rrd_function_process_graph_options(1700000000, 1700003600, $current, $resolved);

		expect($actual)->toBe($expected);
	} finally {
		$config   = $savedConfig;
		$_SESSION = $savedSession;

		if ($savedImages === null) {
			unset($GLOBALS['image_types']);
		} else {
			$GLOBALS['image_types'] = $savedImages;
		}
	}
})->with([
	'digits'              => ['150'],
	'integer'             => [150],
	'decimal spelling'    => ['150.0'],
	'scientific notation' => ['1e2'],
	'leading whitespace'  => [' 150'],
	'fraction'            => ['125.5'],
]);
