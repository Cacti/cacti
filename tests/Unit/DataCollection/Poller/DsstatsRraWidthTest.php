<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: the stats graph used the default 400 pixel width, so RRDtool
 * read the RRA that suits about 1512 seconds a pixel for a week instead of
 * the finest RRA that covers the period.  The graph must be one pixel per
 * step of the RRDfile wide.
 */

namespace DsstatsRraWidthTest;

function read_config_option($name, $force = false) {
	return 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP') {
}

function cacti_rrdtool_valid_path($path) {
	return true;
}

function cacti_rrdtool_valid_ds_name($name) {
	return true;
}

function rrdtool_build_path_command($command, $path) {
	return $command . ' ' . $path;
}

function file_exists($path) {
	return true;
}

function dsstats_rrdtool_execute($command, &$pipes) {
	$GLOBALS['dsstats_width_commands'][] = $command;

	return array_shift($GLOBALS['dsstats_width_responses']);
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

if ($source === false || preg_match('/^function dsstats_obtain_data_source_avgpeak_values\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_obtain_data_source_avgpeak_values() for the width test.');
}

// the code is lib/dsstats.php from this checkout
eval('namespace DsstatsRraWidthTest;' . $matches[0]);

test('the stats graph is one pixel per step wide', function ($interval, $info_step, $start, $width) {
	if (!isset($GLOBALS['config']['cacti_server_os'])) {
		$GLOBALS['config']['cacti_server_os'] = 'unix';
	}

	$GLOBALS['dsstats_width_commands']  = array();
	$GLOBALS['dsstats_width_responses'] = array(
		$info_step . "ds[traffic].index = 0\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n",
		"0x0\n1.000000\nOK u:0.00 s:0.00 r:0.00\n",
	);

	$pipes = array();

	dsstats_obtain_data_source_avgpeak_values(1, '/rra/f.rrd', $interval, $pipes);

	expect($GLOBALS['dsstats_width_commands'][1])->toStartWith("graph x --start now-1$start --end now --width=$width DEF:");
})->with(array(
	array('daily', "step = 300\n", 'day', 288),
	array('weekly', "step = 300\n", 'week', 2016),
	array('monthly', "step = 300\n", 'month', 8928),
	array('yearly', "step = 60\n", 'year', 527040),
	array('weekly', '', 'week', 2016),
));
