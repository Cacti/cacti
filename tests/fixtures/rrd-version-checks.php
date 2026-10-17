<?php
// Isolated behavioral fixture: retain production statements and rename I/O boundaries.
$root = $argv[1] ?? dirname(__DIR__, 2);
require dirname(__DIR__) . '/bootstrap-unit.php';
require CACTI_PATH_LIBRARY . '/rrd.php';
$state = ['version' => '1.4.0', 'comparisons' => 0, 'commands' => []];
function version_fixture_compare($left, $right, $operator) {
	$GLOBALS['state']['comparisons']++;

	return cacti_version_compare($left, $right, $operator);
}
function version_fixture_version() {
	return $GLOBALS['state']['version'];
}
function version_fixture_exists(...$args) {
	return true;
}
function version_fixture_execute($command, ...$args) {
	$GLOBALS['state']['commands'][] = is_string($command) ? $command : rrdtool_build_command($command);

	return '';
}
function version_fixture_load($path, $name) {
	$source = file_get_contents($path);
	$start  = strpos($source, 'function ' . $name . '(');
	$end    = strpos($source, "\nfunction ", $start + 1);

	if ($start === false) {
		throw new RuntimeException('Missing production function');
	}
	$function = substr($source, $start, $end === false ? null : $end - $start);
	$map      = [$name => 'version_fixture_' . $name, 'cacti_version_compare' => 'version_fixture_compare', 'get_rrdtool_version' => 'version_fixture_version', 'file_exists' => 'version_fixture_exists', 'rrdtool_file_exists' => 'version_fixture_exists', 'rrdtool_execute' => 'version_fixture_execute'];

	foreach (['cacti_has_control_chars', 'cacti_rrdtool_valid_path', 'cacti_rrdtool_valid_ds_name', 'cacti_rrdtool_valid_ds_template'] as $helper) {
		$map[$helper] = 'version_fixture_' . $helper;
	}
	$compiled = '';

	foreach (token_get_all('<?php ' . $function) as $token) {
		if (is_array($token)) {
			if ($token[0] !== T_OPEN_TAG) {
				$compiled .= $token[0] === T_STRING ? ($map[$token[1]] ?? $token[1]) : $token[1];
			}
		} else {
			$compiled .= $token;
		}
	}
	$file = tempnam(sys_get_temp_dir(), 'rrd-version');

	try {
		file_put_contents($file, "<?php\n" . $compiled);
		include $file;
	} finally {
		unlink($file);
	}
}

if (str_contains(file_get_contents($root . '/lib/functions.php'), 'function cacti_rrdtool_valid_path(')) {
	foreach (['cacti_has_control_chars', 'cacti_rrdtool_valid_path', 'cacti_rrdtool_valid_ds_name', 'cacti_rrdtool_valid_ds_template'] as $helper) {
		version_fixture_load($root . '/lib/functions.php', $helper);
	}
}
version_fixture_load($root . '/lib/rrd.php', 'rrdtool_function_update');
$graphFile     = is_file($root . '/lib/rrd_graph_options.php') ? $root . '/lib/rrd_graph_options.php' : $root . '/lib/rrd.php';
$graphFunction = str_ends_with($graphFile, 'rrd_graph_options.php') ? 'rrdtool_build_graph_options' : 'rrd_function_process_graph_options';
version_fixture_load($graphFile, $graphFunction);
$runGraph               = 'version_fixture_' . $graphFunction;
$config[OPTIONS_CLI]    = array_replace($config[OPTIONS_CLI] ?? [], ['storage_location' => '', 'path_rrdtool_default_font' => '', 'font_method' => 0, 'rrdtool_watermark' => '', 'default_date_format' => GD_Y_MO_D, 'default_datechar' => 1]);
$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];
$graph                  = ['auto_scale' => 'on', 'auto_scale_opts' => '1', 'lower_limit' => '0', 'upper_limit' => '100', 'auto_scale_log' => '', 'scale_log_units' => '', 'auto_scale_rigid' => '', 'unit_value' => '', 'unit_exponent_value' => '', 'height' => '120', 'width' => '500', 'image_format_id' => 3, 'title_cache' => 'Version fixture', 'vertical_label' => 'Values', 'legend_position' => 'south', 'legend_direction' => 'topdown', 'left_axis_formatter' => 'numeric', 'right_axis_formatter' => 'numeric'];
$updates                = [];

foreach (['/tmp/version-first.rrd', '/tmp/version-second.rrd'] as $path) {
	$updates[$path] = ['local_data_id' => 0, 'data_template_id' => 0, 'times' => [1700000060 => ['value' => 42], 1700000120 => ['value' => 43], 1700000180 => ['value' => 44]]];
}
$results = [];

foreach (['1.3.0', '1.4.0', '1.5.0', '1.8.0', '1.3.0'] as $version) {
	$state['version']     = $version;
	$state['comparisons'] = 0;
	$state['commands']    = [];
	$count                = version_fixture_rrdtool_function_update($updates);
	$updateComparisons    = $state['comparisons'];
	$commands             = $state['commands'];
	$state['comparisons'] = 0;
	$metadata             = $graph;
	$options              = ['graph_start' => 1700000000, 'graph_end' => 1700003600, 'graph_theme' => 'modern'];
	$command              = $runGraph(1700000000, 1700003600, $metadata, $options);
	$results[]            = ['version' => $version, 'updates' => $count, 'update_comparisons' => $updateComparisons, 'commands' => $commands, 'graph_comparisons' => $state['comparisons'], 'graph' => $command, 'metadata' => $metadata, 'options' => $options];
}
$state['comparisons'] = 0;
version_fixture_rrdtool_function_update([]);
echo json_encode(['cases' => $results, 'empty_update_comparisons' => $state['comparisons']], JSON_THROW_ON_ERROR), "\n";
