<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 4) . '/lib/CactiValidator.php';
require_once dirname(__DIR__, 4) . '/lib/rrd.php';
require_once dirname(__DIR__, 4) . '/lib/api_aggregate.php';
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/fixtures/Core/Rrd/AxisOptions.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/rrd.php', 'rrd_function_process_graph_options', 'CactiAxisFixture');
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/template.php', 'push_out_graph', 'CactiAxisFixture');

test('axis defaults and older configured versions preserve graph commands', function () {
	$fields = ['vertical_label_angle' => '90', 'right_axis_label_angle' => '270', 'right_axis_range' => '0:100'];

	foreach (['1.5.0', '1.9.0', '1.10.0'] as $version) {
		expect(rrd_graph_axis_options($fields, $version))->toBe('');
	}
	expect(rrd_graph_axis_options([], '1.11.0'))->toBe('')
		->and(rrd_graph_axis_options(array_fill_keys(array_keys($fields), ''), '1.11.0'))->toBe('');
});

test('new and future versions emit independent angles including zero', function () {
	foreach (['1.11.0', '1.12.0'] as $version) {
		$options = rrd_graph_axis_options(['vertical_label_angle' => '0', 'right_axis_label_angle' => '270', 'right_axis_range' => '0:100'], $version);
		expect($options)->toBe('--vertical-label-angle ' . cacti_escapeshellarg('0') . RRD_NL .
			'--right-axis-label-angle ' . cacti_escapeshellarg('270') . RRD_NL .
			'--right-axis-range ' . cacti_escapeshellarg('0:100') . RRD_NL);
	}
});

test('right axis bounds accept open endpoints and finite numeric values', function (string $range) {
	expect(rrd_graph_axis_option_is_valid('right_axis_range', $range))->toBeTrue();
})->with(['', '0:100', '0:', ':100', '-2.5:3.25', '-1e2:1e2', '.5:1.', '0:0']);

test('invalid imported axis values cannot reach the graph command', function (string $field, mixed $value) {
	expect(rrd_graph_axis_option_is_valid($field, $value))->toBeFalse()
		->and(rrd_graph_axis_options([$field => $value], '1.11.0'))->toBe('');
})->with([
	['vertical_label_angle', '45'], ['right_axis_label_angle', '270;touch /tmp/axis'],
	['vertical_label_angle', ['90']], ['right_axis_range', '100:0'],
	['right_axis_range', ':'], ['right_axis_range', 'NaN:100'],
	['right_axis_range', '0:INF'], ['right_axis_range', '1e999:'],
	['right_axis_range', "0:100\n"], ['right_axis_range', '0:100 --title injected'],
	['right_axis_range', str_repeat('1', 65) . ':'],
]);

test('graph rendering includes the new options only at the configured capability level', function () {
	global $image_types;
	$image_types = [1 => 'PNG'];
	$graph       = [
		'auto_scale'             => '', 'upper_limit' => '', 'lower_limit' => '',
		'auto_scale_log'         => '', 'scale_log_units' => '', 'auto_scale_rigid' => '',
		'unit_value'             => '', 'unit_exponent_value' => '', 'height' => '100', 'width' => '200',
		'image_format_id'        => 1, 'vertical_label_angle' => '90',
		'right_axis_label_angle' => '270', 'right_axis_range' => ':100',
	];
	$data = [];

	foreach (['1.10.0', '1.11.0'] as $version) {
		$GLOBALS['axis_fixture_version'] = $version;
		$options                         = CactiAxisFixture\rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

		foreach (['vertical-label-angle', 'right-axis-label-angle', 'right-axis-range'] as $flag) {
			expect(substr_count($options, '--' . $flag . ' '))->toBe($version === '1.11.0' ? 1 : 0);
		}
	}
});

test('aggregate overrides retain zero and reject invalid axis values', function () {
	$_SESSION[SESS_ERROR_FIELDS] = [];
	$posted                      = ['t_vertical_label_angle' => 'on', 'vertical_label_angle' => '0',
		't_right_axis_range'                                    => 'on', 'right_axis_range' => '100:0'];
	$params = aggregate_validate_graph_params($posted, true);
	expect($params['vertical_label_angle'])->toBe('0')
		->and($params['t_vertical_label_angle'])->toBe('on')
		->and($params['right_axis_label_angle'])->toBe('')
		->and($params['t_right_axis_label_angle'])->toBe('')
		->and($_SESSION[SESS_ERROR_FIELDS])->toHaveKey('right_axis_range');
	unset($_SESSION[SESS_ERROR_FIELDS], $_SESSION[SESS_FIELD_VALUES], $_SESSION['messages']);
});

test('new template fields propagate while per graph overrides remain intact', function () {
	global $struct_graph;
	$previous = $struct_graph ?? null;
	require dirname(__DIR__, 4) . '/include/global_arrays.php';
	require dirname(__DIR__, 4) . '/include/global_form.php';
	$GLOBALS['axis_fixture_template'] = [
		'id'                       => 42, 'graph_template_id' => 7,
		't_vertical_label_angle'   => '', 'vertical_label_angle' => '90',
		't_right_axis_label_angle' => 'on', 'right_axis_label_angle' => '270',
		't_right_axis_range'       => '', 'right_axis_range' => '0:100',
	];
	$GLOBALS['axis_fixture_updates'] = [];

	try {
		expect(CactiAxisFixture\push_out_graph(42, false))->toBeTrue();
		$updates = $GLOBALS['axis_fixture_updates'];
		expect($updates)->toHaveCount(2)
			->and($updates[0][0])->toContain('SET vertical_label_angle = ?')
			->and($updates[0][1])->toBe(['90', 42])
			->and($updates[1][0])->toContain('SET right_axis_range = ?')
			->and($updates[1][1])->toBe(['0:100', 42]);
	} finally {
		$struct_graph = $previous;
		unset($GLOBALS['axis_fixture_template'], $GLOBALS['axis_fixture_updates']);
	}
});
