<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
$root = dirname(__DIR__, 4);
require $root . '/tests/Helpers/RequestCoverage.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';

$directory = sys_get_temp_dir() . '/cacti-native-update-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$binary = $directory . '/rrdtool';
$log = $directory . '/commands';
$config = ['is_web' => false, 'cacti_server_os' => 'unix', 'config_options_array' => ['rrdtool_version' => $argv[1] ?? '1.4.0', 'storage_location' => 0, 'path_rrdtool' => $binary, 'path_rrdtool_default_font' => '', 'i18n_language_support' => '0']];
require $root . '/include/global_languages.php';
require $root . '/lib/auth.php';
require $root . '/lib/plugins.php';
require $root . '/lib/html.php';
define('IN_CACTI_INSTALL', true);
$config += ['poller_id' => 1, 'connection' => false, 'url_path' => '/'];
require $root . '/lib/rrd.php';
$pipe = false;

try {
	// Exercise Cacti's native code with an external process that records commands.
	file_put_contents($binary, '#!' . PHP_BINARY . "\n<?php\nwhile ((\$line = fgets(STDIN)) !== false) { file_put_contents(" . var_export($log, true) . ", \$line, FILE_APPEND); }\n");
	chmod($binary, 0700);
	$pipe = rrd_init(false);
	$updates = [];

	foreach (['first', 'second'] as $name) {
		$path = $directory . '/' . $name . '.rrd';
		touch($path);
		$updates[$path] = ['local_data_id' => 0, 'data_template_id' => 0, 'times' => [1700000180 => ['value' => 44], 1700000060 => ['value' => 42], 1700000120 => ['value' => 43]]];
	}
	$count = rrdtool_function_update($updates, $pipe);
	$empty = rrdtool_function_update([], $pipe);
	rrd_close($pipe);
	$pipe = false;
	$commands = file($log, FILE_IGNORE_NEW_LINES);

	if ($count !== 6 || $empty !== 0 || count($commands) !== 6) {
		throw new RuntimeException('Native update did not send the complete batch.');
	}
	foreach ($commands as $index => $command) {
		$sample = [1700000060, 1700000120, 1700000180][$index % 3] . ':' . [42, 43, 44][$index % 3];
		if (!str_contains($command, $sample) || str_contains($command, '--skip-past-updates') !== version_compare(get_rrdtool_version(), '1.5', '>=')) {
			throw new RuntimeException('Native update changed timestamp order or capability options.');
		}
	}
	$config['include_path'] = $root . '/include';
	$config['base_path'] = $root;
	$config['config_options_array'] += ['i18n_language_support' => '0', 'auth_method' => 1, 'font_method' => 0, 'rrdtool_watermark' => '', 'default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'graph_watermark' => '', 'graph_watermark_rrd' => '', 'selected_theme' => 'modern'];
	$_SESSION = ['sess_user_config_array' => ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => '']];
	foreach (['title', 'axis', 'legend', 'unit', 'watermark'] as $type) {
		$config['config_options_array'][$type . '_font'] = 'DejaVu Sans';
		$config['config_options_array'][$type . '_size'] = 10;
	}
	require $root . '/include/global_arrays.php';
	$graph = ['auto_scale' => 'on', 'auto_scale_opts' => '1', 'lower_limit' => '0', 'upper_limit' => '100', 'auto_scale_log' => '', 'scale_log_units' => '', 'auto_scale_rigid' => '', 'unit_value' => '', 'unit_exponent_value' => '', 'height' => '120', 'width' => '500', 'image_format_id' => 3, 'title_cache' => 'Capabilities', 'vertical_label' => 'Values', 'legend_position' => 'south', 'legend_direction' => 'topdown', 'left_axis_formatter' => 'numeric', 'right_axis_formatter' => 'numeric'];
	$data = ['graph_start' => 1700000000, 'graph_end' => 1700003600, 'graph_theme' => 'modern'];
	$graphCommand = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);
	foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
		if (str_contains($graphCommand, '--' . $flag) !== version_compare(get_rrdtool_version(), '1.4', '>=')) {
			throw new RuntimeException('Native graph formatter capability changed.');
		}
	}
	echo json_encode(['count' => $count, 'empty' => $empty, 'commands' => $commands], JSON_THROW_ON_ERROR), "\n";
} finally {
	if ($pipe !== false) {
		rrd_close($pipe);
	}
	foreach (glob($directory . '/*') as $path) {
		unlink($path);
	}
	rmdir($directory);
}
