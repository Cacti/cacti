<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 4) . '/lib/rrd.php';

test('native graph options apply capabilities consistently to every formatter', function () {
	global $config;
	$savedConfig   = $config;
	$savedSession  = $_SESSION;
	$savedDatechar = $GLOBALS['datechar'] ?? null;

	try {
		$GLOBALS['datechar']    = [1 => '/'];
		$options                = ['storage_location' => '', 'path_rrdtool_default_font' => '', 'font_method' => 0, 'rrdtool_watermark' => '', 'default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'graph_watermark' => '', 'graph_watermark_rrd' => ''];
		$config[OPTIONS_CLI]    = array_replace($config[OPTIONS_CLI] ?? [], $options);
		$_SESSION[OPTIONS_WEB]  = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
		$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];
		$graph                  = ['auto_scale' => 'on', 'auto_scale_opts' => '1', 'lower_limit' => '0', 'upper_limit' => '100', 'auto_scale_log' => '', 'scale_log_units' => '', 'auto_scale_rigid' => '', 'unit_value' => '', 'unit_exponent_value' => '', 'height' => '120', 'width' => '500', 'image_format_id' => 3, 'title_cache' => 'Capabilities', 'vertical_label' => 'Values', 'legend_position' => 'south', 'legend_direction' => 'topdown', 'left_axis_formatter' => 'numeric', 'right_axis_formatter' => 'numeric'];
		$data                   = ['graph_start' => 1700000000, 'graph_end' => 1700003600, 'graph_theme' => 'modern'];
		$command                = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

		foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
			expect(str_contains($command, '--' . $flag))->toBe(version_compare(get_rrdtool_version(), '1.4', '>='));
		}
	} finally {
		$config              = $savedConfig;
		$_SESSION            = $savedSession;
		$GLOBALS['datechar'] = $savedDatechar;
	}
});

test('native updates retain timestamp order and acknowledgement across two RRD files', function () {
	global $config;
	$savedConfig  = $config;
	$savedSession = $_SESSION;
	$directory    = sys_get_temp_dir() . '/cacti-rrd-capabilities-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$binary = $directory . '/rrdtool';
	$log    = $directory . '/commands';
	$pipe   = null;

	try {
		// Replace only the external executable; Cacti's command and transport code remain native.
		file_put_contents($binary, '#!' . PHP_BINARY . "\n<?php\nwhile ((\$line = fgets(STDIN)) !== false) { file_put_contents(" . var_export($log, true) . ", \$line, FILE_APPEND); echo \"OK u:0.0 s:0.0 r:0.0\\n\"; flush(); }\n");
		chmod($binary, 0700);
		$options               = ['path_rrdtool' => $binary, 'storage_location' => '', 'path_rrdtool_default_font' => ''];
		$config[OPTIONS_CLI]   = array_replace($config[OPTIONS_CLI] ?? [], $options);
		$_SESSION[OPTIONS_WEB] = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
		$pipe                  = __rrd_init(false);
		expect(rrdtool_is_process($pipe))->toBeTrue();
		$updates = [];

		foreach (['first', 'second'] as $name) {
			$path = $directory . '/' . $name . '.rrd';
			touch($path);
			$updates[$path] = ['local_data_id' => 0, 'data_template_id' => 0, 'times' => [1700000180 => ['value' => 44], 1700000060 => ['value' => 42], 1700000120 => ['value' => 43]]];
		}
		$acknowledged = [];
		expect(rrdtool_function_update($updates, $pipe, $acknowledged))->toBe(6);
		expect(rrdtool_function_update([], $pipe))->toBe(0);
		$commands = file($log, FILE_IGNORE_NEW_LINES);
		expect($commands)->toHaveCount(6);

		foreach ($commands as $index => $command) {
			$arguments = str_getcsv($command, ' ', '"', '\\');
			expect(array_slice($arguments, -3))->toBe(['--template', 'value', [1700000060, 1700000120, 1700000180][$index % 3] . ':' . [42, 43, 44][$index % 3]]);
			expect(str_contains($command, '--skip-past-updates'))->toBe(version_compare(get_rrdtool_version(), '1.5', '>='));
		}

		foreach ($acknowledged as $timestamps) {
			expect($timestamps)->toBe([1700000060 => true, 1700000120 => true, 1700000180 => true]);
		}
	} finally {
		if ($pipe !== null) {
			rrd_close($pipe);
		}
		$config   = $savedConfig;
		$_SESSION = $savedSession;

		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
});
