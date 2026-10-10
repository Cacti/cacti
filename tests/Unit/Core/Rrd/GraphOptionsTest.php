<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Cacti\Rrd\Graph\GraphOptions;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

require_once dirname(__DIR__, 4) . '/lib/Rrd/Graph/GraphOptions.php';

test('graph options retain plugin values and normalize scalar inputs', function () {
	$options = GraphOptions::resolve([
		'export'          => '1',
		'export_filename' => '/tmp/export.png',
		'graph_end'       => 200.0,
		'graph_height'    => '125.5',
		'graph_start'     => '100',
		'graph_theme'     => 'classic',
		'graph_width'     => 425,
		'image_format'    => 'png',
		'output_flag'     => '3',
		'plugin_option'   => 'retained',
	]);

	expect($options)->toMatchArray([
		'export'          => '1',
		'export_filename' => '/tmp/export.png',
		'graph_end'       => 200,
		'graph_height'    => 125.5,
		'graph_start'     => 100,
		'graph_width'     => 425,
		'output_flag'     => 3,
		'plugin_option'   => 'retained',
	]);
});

test('graph options accept every supported flag and path type', function () {
	$options = GraphOptions::resolve([
		'export'          => true,
		'export_csv'      => 1,
		'get_error'       => 'true',
		'graph_nolegend'  => false,
		'graph_start'     => 10,
		'graphv'          => 0,
		'print_source'    => '0',
		'export_filename' => '',
		'export_realtime' => '/tmp/realtime.png',
		'output_filename' => '/tmp/output.png',
		'image_format'    => 'svg+xml',
	]);

	expect($options)->toHaveCount(11)
		->and(GraphOptions::resolve([]))->toBe([]);
});

test('graph options reject invalid integers', function (mixed $value) {
	GraphOptions::resolve(['graph_start' => $value]);
})->with([1.5, 'not-an-integer'])->throws(InvalidOptionsException::class);

test('graph options reject invalid dimensions', function (mixed $value) {
	GraphOptions::resolve(['graph_width' => $value]);
})->with([NAN, 'not-a-number'])->throws(InvalidOptionsException::class);

test('graph option constraints reject invalid boundary values', function (array $options) {
	GraphOptions::resolve($options);
})->with([
	[['graph_height' => 0]],
	[['image_format' => 'gif']],
	[['output_flag' => 6]],
])->throws(InvalidOptionsException::class);
