<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
// Isolated production update body with instrumented process boundaries.
$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
$state = ['version' => '1.3.0', 'comparisons' => 0, 'commands' => []];
function capability_fixture_version() {
	return $GLOBALS['state']['version'];
}
function capability_fixture_compare($left, $right, $operator) {
	$GLOBALS['state']['comparisons']++;

	return cacti_version_compare($left, $right, $operator);
}
function capability_fixture_exists($path) {
	return true;
}
function capability_fixture_config($name) {
	return 0;
}
function capability_fixture_execute($command, ...$args) {
	$GLOBALS['state']['commands'][] = $command;

	return '';
}
$source   = file_get_contents($root . '/lib/rrd.php');
$start    = strpos($source, 'function rrdtool_function_update(');
$end      = strpos($source, "\nfunction ", $start + 1);
$function = substr($source, $start, $end - $start);
$map      = ['rrdtool_function_update' => 'capability_fixture_update', 'get_rrdtool_version' => 'capability_fixture_version', 'cacti_version_compare' => 'capability_fixture_compare', 'file_exists' => 'capability_fixture_exists', 'read_config_option' => 'capability_fixture_config', 'rrdtool_execute' => 'capability_fixture_execute'];
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
eval($compiled);
$updates = [];

foreach (['/tmp/capability-first.rrd', '/tmp/capability-second.rrd'] as $path) {
	$updates[$path] = ['local_data_id' => 0, 'data_template_id' => 0, 'times' => [1700000060 => ['value' => 42], 1700000120 => ['value' => 43], 1700000180 => ['value' => 44]]];
}
$results = [];

foreach (['1.3.0', '1.4.0', '1.5.0', '1.8.0', '1.3.0'] as $version) {
	$state     = ['version' => $version, 'comparisons' => 0, 'commands' => []];
	$count     = capability_fixture_update($updates);
	$results[] = ['version' => $version, 'count' => $count, 'comparisons' => $state['comparisons'], 'commands' => $state['commands']];
}
$state['comparisons'] = 0;
capability_fixture_update([]);
echo json_encode(['cases' => $results, 'empty_comparisons' => $state['comparisons']], JSON_THROW_ON_ERROR), "\n";
