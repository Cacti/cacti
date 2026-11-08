<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Native probe for the lib/rrd.php paths that lost their RRDtool version
// checks: the legend and axis formatter graph options, the theme colors and
// fonts, and the poller update with --skip-past-updates. lib/rrd.php runs
// unmodified; only the external rrdtool binary is replaced by a recorder.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

// Boot the unit environment before coverage starts: recording every vendor and
// include file with branch analysis exhausts the probe's memory limit, and only
// lib/rrd.php is under test here.
require_once dirname(__DIR__, 2) . '/bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

/**
 * Fail the probe with a message when a condition does not hold.
 *
 * @param bool   $condition The expectation.
 * @param string $message   What went wrong.
 *
 * @return void
 */
function rrd_floor_probe_assert(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$passed    = [];
$directory = sys_get_temp_dir() . '/cacti-rrd-floor-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);

register_shutdown_function(static function () use ($directory) : void {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}

	rmdir($directory);
});

$GLOBALS['datechar']    = [1 => '/'];
$GLOBALS['image_types'] = [1 => 'PNG', 3 => 'SVG'];

$options = [
	'storage_location'          => '',
	'path_rrdtool_default_font' => '',
	'font_method'               => 0,
	'rrdtool_watermark'         => '',
	'default_date_format'       => GD_Y_MO_D,
	'default_datechar'          => 1,
	'graph_watermark'           => '',
	'graph_watermark_rrd'       => '',
];

$config[OPTIONS_CLI]    = array_replace($config[OPTIONS_CLI] ?? [], $options);
$_SESSION[OPTIONS_WEB]  = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];

$graph = [
	'auto_scale'           => 'on',
	'auto_scale_opts'      => '1',
	'lower_limit'          => '0',
	'upper_limit'          => '100',
	'auto_scale_log'       => '',
	'scale_log_units'      => '',
	'auto_scale_rigid'     => '',
	'unit_value'           => '',
	'unit_exponent_value'  => '',
	'height'               => '120',
	'width'                => '500',
	'image_format_id'      => 3,
	'title_cache'          => 'Floor',
	'vertical_label'       => 'Values',
	'legend_position'      => 'south',
	'legend_direction'     => 'topdown',
	'left_axis_formatter'  => 'numeric',
	'right_axis_formatter' => 'numeric',
];

// A theme with an rrdtheme.php supplies colors, a border and the watermark font.
$data    = ['graph_start' => 1700000000, 'graph_end' => 1700003600, 'graph_theme' => 'modern'];
$command = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
	rrd_floor_probe_assert(str_contains($command, '--' . $flag . ' '), 'Graph options must always emit --' . $flag . ': ' . $command);
}

rrd_floor_probe_assert(str_contains($command, '--color '), 'A theme with colors must emit --color: ' . $command);
$passed[] = 'graph-options';

// Empty values leave the options out rather than passing an empty argument.
foreach (['legend_position', 'legend_direction', 'left_axis_formatter', 'right_axis_formatter'] as $field) {
	$graph[$field] = '';
}

$command = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
	rrd_floor_probe_assert(!str_contains($command, '--' . $flag), 'An empty value must not emit --' . $flag . ': ' . $command);
}

$passed[] = 'graph-options-empty';

// Poller updates go through a recording rrdtool so the real pipe transport runs.
$binary = $directory . '/rrdtool';
$log    = $directory . '/commands';
file_put_contents($binary, '#!' . PHP_BINARY . "\n<?php\nwhile ((\$line = fgets(STDIN)) !== false) { file_put_contents(" . var_export($log, true) . ", \$line, FILE_APPEND); echo \"OK u:0.0 s:0.0 r:0.0\\n\"; flush(); }\n");
chmod($binary, 0700);

$config[OPTIONS_CLI]['path_rrdtool']   = $binary;
$_SESSION[OPTIONS_WEB]['path_rrdtool'] = $binary;

$pipe = __rrd_init(false);
$path = $directory . '/ds.rrd';
touch($path);

$updates      = [$path => ['local_data_id' => 0, 'data_template_id' => 0, 'times' => [1700000120 => ['value' => 43], 1700000060 => ['value' => 42]]]];
$acknowledged = [];
$processed    = rrdtool_function_update($updates, $pipe, $acknowledged);
rrd_close($pipe);

// Closing the pipe sends a trailing quit, which is not an update.
$commands = array_values(array_filter(file($log, FILE_IGNORE_NEW_LINES), static fn (string $line) : bool => $line !== 'quit'));
rrd_floor_probe_assert($processed === 2, 'Both poller samples must be acknowledged, got ' . $processed);
rrd_floor_probe_assert(count($commands) === 2, 'Each poller sample must be one update: ' . var_export($commands, true));

foreach ($commands as $index => $line) {
	$expected = '--skip-past-updates --template value ' . [1700000060, 1700000120][$index] . ':' . [42, 43][$index];
	rrd_floor_probe_assert(str_contains($line, $expected), 'Poller update must skip past samples in timestamp order: ' . $line);
}

$passed[] = 'poller-update';

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
