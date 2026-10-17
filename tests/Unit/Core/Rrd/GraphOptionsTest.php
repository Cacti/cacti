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

test('graph options reject invalid output flags', function (mixed $value) {
	GraphOptions::resolve(['output_flag' => $value]);
})->with(['not-an-integer', 7])->throws(InvalidOptionsException::class);

test('graph options keep the legacy tolerance for empty, relative and fractional values', function () {
	$options = [
		'graph_start'     => 'now-1d',
		'graph_end'       => 1700000000.5,
		'graph_height'    => 0,
		'graph_width'     => '',
		'graphv'          => null,
		'output_filename' => '',
	];
	$resolved = GraphOptions::resolve($options);

	expect($resolved['graph_start'])->toBe('now-1d')
		->and($resolved['graph_end'])->toBe(1700000000)
		->and($resolved['graph_height'])->toBe(0)
		->and($resolved['graph_width'])->toBe('')
		->and($resolved['graphv'])->toBeNull()
		->and($resolved['output_filename'])->toBe('');
});

test('graph options reject invalid dimensions', function (mixed $value) {
	GraphOptions::resolve(['graph_width' => $value]);
})->with([NAN, 'not-a-number'])->throws(InvalidOptionsException::class);

test('graph option constraints reject invalid boundary values', function (array $options) {
	GraphOptions::resolve($options);
})->with([
	[['graph_height' => 'not-a-number']],
	[['image_format' => 'gif']],
	[['output_flag' => 6]],
])->throws(InvalidOptionsException::class);

test('graph dimensions preserve scientific notation and large finite numeric strings', function ($value, $expected) {
	$options = GraphOptions::resolve(['graph_width' => $value, 'graph_height' => $value]);
	expect($options['graph_width'])->toBe($expected);
	expect($options['graph_height'])->toBe($expected);
})->with([
	'integer string'        => ['1000', 1000],
	'positive exponent'     => ['1e3', 1000.0],
	'negative exponent'     => ['1e-3', 0.001],
	'uppercase exponent'    => ['2E2', 200.0],
	'outside integer range' => ['9223372036854775808', 9223372036854775808.0],
]);

test('graph option resolutions retain no values after successful or failed calls', function () {
	$first = GraphOptions::resolve(['graph_width' => '125', 'plugin_option' => 'first']);

	expect(fn () => GraphOptions::resolve(['graph_width' => 'wide']))
		->toThrow(InvalidOptionsException::class);

	$second = GraphOptions::resolve(['graph_height' => '50', 'plugin_option' => 'second']);

	expect($first)->toBe(['graph_width' => 125, 'plugin_option' => 'first'])
		->and($second)->toBe(['graph_height' => 50, 'plugin_option' => 'second'])
		->and(GraphOptions::resolve([]))->toBe([]);
});
