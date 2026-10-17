<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Run separately so cached RRDtool versions and test collection stubs cannot
// affect the graph command characterization.
require_once dirname(__DIR__) . '/bootstrap-unit.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

date_default_timezone_set('UTC');
$config[OPTIONS_CLI] = array_replace($config[OPTIONS_CLI] ?? [], [
	'rrdtool_version'      => $argv[1],
	'rrdtool_watermark'    => 'on',
	'graph_watermark'      => 'Cacti graph',
	'graph_watermark_rrd'  => '',
	'default_date_format'  => GD_Y_MO_D,
	'default_datechar'     => 1,
	'font_method'          => 0,
]);
$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];

foreach (['title', 'axis', 'legend', 'unit', 'watermark'] as $type) {
	$config[OPTIONS_CLI][$type . '_font'] = 'DejaVu Sans';
	$config[OPTIONS_CLI][$type . '_size'] = 10;
}

$defaults = [
	'auto_scale'          => 'on',
	'auto_scale_opts'     => '1',
	'lower_limit'         => '0',
	'upper_limit'         => '100',
	'auto_scale_log'      => '',
	'scale_log_units'     => '',
	'auto_scale_rigid'    => '',
	'unit_value'          => '',
	'unit_exponent_value' => '',
	'height'              => '120',
	'width'               => '500',
	'image_format_id'     => 3,
	'title_cache'         => "Interface ' uplink |host_snmp_password|",
	'vertical_label'      => 'Bits per second',
	'legend_position'     => 'south',
	'legend_direction'    => 'topdown',
	'left_axis_formatter' => 'numeric',
	'right_axis_formatter'=> 'numeric',
];

$cases = [
	'autoscale'         => [[], []],
	'autoscale maximum' => [['auto_scale_opts' => '2'], []],
	'autoscale minimum' => [['auto_scale_opts' => '3'], []],
	'autoscale limits'  => [['auto_scale_opts' => '4', 'lower_limit' => '-50'], []],
	'fixed logarithmic' => [[
		'auto_scale'             => '',
		'auto_scale_log'         => 'on',
		'scale_log_units'        => 'on',
		'auto_scale_rigid'       => 'on',
		'unit_value'             => '10:1',
		'unit_exponent_value'    => '3',
		'slope_mode'             => CHECKED,
		'alt_y_grid'             => CHECKED,
		'right_axis'             => '1:0',
		'right_axis_label'       => "Secondary ' label",
		'right_axis_format'      => 1,
		'right_axis_format_text' => '%8.2lf %s',
		'left_axis_format'       => 1,
		'left_axis_format_text'  => '%6.1lf',
	], []],
	'export PNG and overrides' => [[], [
		'export'          => true,
		'export_filename' => "/tmp/graph ' export.png",
		'image_format'    => 'png',
		'graph_height'    => '240',
		'graph_width'     => '800',
		'graph_nolegend'  => 'on',
		'graphv'          => true,
	]],
];

// Exercise callers whose shared image-format metadata has not been loaded.
if (($argv[2] ?? '') === 'cold') {
	unset($GLOBALS['image_types']);
}

$results = [];

foreach ($cases as $name => [$values, $options]) {
	$graph            = array_replace($defaults, $values);
	$graph_data_array = array_replace([
		'graph_theme' => 'modern',
		'graph_start' => 1700000000,
		'graph_end'   => 1700003600,
	], $options);
	$command        = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $graph_data_array);
	$results[$name] = ['command' => $command, 'graph' => $graph, 'options' => $graph_data_array];
}

print json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
