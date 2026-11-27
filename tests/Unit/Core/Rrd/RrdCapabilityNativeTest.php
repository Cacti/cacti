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

require_once dirname(__DIR__, 4) . '/lib/rrd.php';
require_once dirname(__DIR__, 4) . '/lib/boost.php';

test('native graph options always emit the legend and axis formatter options', function () {
	global $config;
	$savedConfig     = $config;
	$savedSession    = $_SESSION;
	$savedDatechar   = $GLOBALS['datechar'] ?? null;
	$savedImageTypes = $GLOBALS['image_types'] ?? null;

	try {
		$GLOBALS['datechar']    = [1 => '/'];
		$GLOBALS['image_types'] = [1 => 'PNG', 3 => 'SVG'];
		$options                = ['storage_location' => '', 'path_rrdtool_default_font' => '', 'font_method' => 0, 'rrdtool_watermark' => '', 'default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'graph_watermark' => '', 'graph_watermark_rrd' => ''];
		$config[OPTIONS_CLI]    = array_replace($config[OPTIONS_CLI] ?? [], $options);
		$_SESSION[OPTIONS_WEB]  = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
		$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];
		$graph                  = ['auto_scale' => 'on', 'auto_scale_opts' => '1', 'lower_limit' => '0', 'upper_limit' => '100', 'auto_scale_log' => '', 'scale_log_units' => '', 'auto_scale_rigid' => '', 'unit_value' => '', 'unit_exponent_value' => '', 'height' => '120', 'width' => '500', 'image_format_id' => 3, 'title_cache' => 'Capabilities', 'vertical_label' => 'Values', 'legend_position' => 'south', 'legend_direction' => 'topdown', 'left_axis_formatter' => 'numeric', 'right_axis_formatter' => 'numeric'];
		$data                   = ['graph_start' => 1700000000, 'graph_end' => 1700003600, 'graph_theme' => 'modern'];
		$command                = rrd_function_process_graph_options(1700000000, 1700003600, $graph, $data);

		foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
			expect($command)->toContain('--' . $flag);
		}
	} finally {
		$config              = $savedConfig;
		$_SESSION            = $savedSession;
		$GLOBALS['datechar'] = $savedDatechar;

		if ($savedImageTypes === null) {
			unset($GLOBALS['image_types']);
		} else {
			$GLOBALS['image_types'] = $savedImageTypes;
		}
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
			$timestamp = [1700000060, 1700000120, 1700000180][$index % 3];
			$value     = [42, 43, 44][$index % 3];
			$template  = sprintf('"--template" "value" "%d:%d"', $timestamp, $value);

			expect($command)->toContain($template);
			expect($command)->toContain('"--skip-past-updates" ' . $template);
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

test('Boost updates always skip past samples and report a rejected update', function () {
	global $config;
	$savedConfig  = $config;
	$savedSession = $_SESSION;
	$directory    = sys_get_temp_dir() . '/cacti-boost-update-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$binary = $directory . '/rrdtool';
	$log    = $directory . '/commands';
	$path   = $directory . '/ds.rrd';
	$pipe   = null;

	try {
		// The fake binary rejects every update the way rrdtool does without --skip-past-updates.
		file_put_contents($binary, '#!' . PHP_BINARY . "\n<?php\nwhile ((\$line = fgets(STDIN)) !== false) { file_put_contents(" . var_export($log, true) . ", \$line, FILE_APPEND); echo \"ERROR: ds.rrd: illegal attempt to update using time 1700000060 when last update time is 1700000060 (minimum one second step)\\n\"; flush(); }\n");
		chmod($binary, 0700);
		touch($path);
		$options               = ['path_rrdtool' => $binary, 'storage_location' => '', 'path_rrdtool_default_font' => ''];
		$config[OPTIONS_CLI]   = array_replace($config[OPTIONS_CLI] ?? [], $options);
		$_SESSION[OPTIONS_WEB] = array_replace($_SESSION[OPTIONS_WEB] ?? [], $options);
		$pipe                  = __rrd_init(false);
		$values                = '1700000060:1 1700000120:2';

		// A rejected multi-value update stops at the first bad timestamp, so
		// reporting it as OK would delete the newer samples unwritten.
		expect(boost_rrdtool_function_update(1, $path, 'value', $values, $pipe))->toStartWith('ERROR');
		expect(file($log, FILE_IGNORE_NEW_LINES))->toHaveCount(1)
			->and(file($log, FILE_IGNORE_NEW_LINES)[0])->toContain('--skip-past-updates --template value 1700000060:1 1700000120:2');
	} finally {
		if ($pipe !== null) {
			rrd_close($pipe);
		}
		$config   = $savedConfig;
		$_SESSION = $savedSession;

		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
});
