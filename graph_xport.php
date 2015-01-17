<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* since we'll have additional headers, tell php when to flush them */
ob_start();

$guest_account = true;

include('./include/auth.php');
include_once('./lib/rrd.php');

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request('graph_start'));
input_validate_input_number(get_request_var_request('graph_end'));
input_validate_input_number(get_request_var_request('graph_height'));
input_validate_input_number(get_request_var_request('graph_width'));
input_validate_input_number(get_request_var_request('local_graph_id'));
input_validate_input_number(get_request_var_request('rra_id'));
input_validate_input_number(get_request_var_request('stdout'));
/* ==================================================== */

/* flush the headers now */
ob_end_clean();

session_write_close();

$graph_data_array = array();

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request('local_graph_id'));
input_validate_input_number(get_request_var_request('rra_id'));
/* ==================================================== */

/* override: graph start time (unix time) */
if (!empty($_REQUEST['graph_start']) && is_numeric($_REQUEST['graph_start'] && $_REQUEST['graph_start'] < 1600000000)) {
	$graph_data_array['graph_start'] = get_request_var_request('graph_start');
}

/* override: graph end time (unix time) */
if (!empty($_REQUEST['graph_end']) && is_numeric($_REQUEST['graph_end']) && $_REQUEST['graph_end'] < 1600000000) {
	$graph_data_array['graph_end'] = get_request_var_request('graph_end');
}

/* override: graph height (in pixels) */
if (!empty($_REQUEST['graph_height']) && is_numeric($_REQUEST['graph_height']) && $_REQUEST['graph_height'] < 3000) {
	$graph_data_array['graph_height'] = get_request_var_request('graph_height');
}

/* override: graph width (in pixels) */
if (!empty($_REQUEST['graph_width']) && is_numeric($_REQUEST['graph_width']) && $_REQUEST['graph_width'] < 3000) {
	$graph_data_array['graph_width'] = get_request_var_request('graph_width');
}

/* override: skip drawing the legend? */
if (!empty($_REQUEST['graph_nolegend'])) {
	$graph_data_array['graph_nolegend'] = get_request_var_request('graph_nolegend');
}

/* print RRDTool graph source? */
if (!empty($_REQUEST['show_source'])) {
	$graph_data_array['print_source'] = get_request_var_request('show_source');
}

$graph_info = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', array(get_request_var_request('local_graph_id')));

/* for bandwidth, NThPercentile */
$xport_meta = array();

/* tell function we are csv */
$graph_data_array['export_csv'] = true;

/* Get graph export */
$xport_array = @rrdtool_function_xport($_REQUEST['local_graph_id'], get_request_var_request('rra_id'), $graph_data_array, $xport_meta);

/* Make graph title the suggested file name */
if (is_array($xport_array['meta'])) {
	$filename = $xport_array['meta']['title_cache'] . '.csv';
} else {
	$filename = 'graph_export.csv';
}

header('Content-type: application/vnd.ms-excel');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
	header('Pragma: cache');
}

header('Cache-Control: max-age=15');
if (!isset($_REQUEST['stdout'])) {
	header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if (is_array($xport_array['meta'])) {
	print '"Title:","'          . $xport_array['meta']['title_cache']                . '"' . "\n";
	print '"Vertical Label:","' . $xport_array['meta']['vertical_label']             . '"' . "\n";
	print '"Start Date:","'     . date('Y-m-d H:i:s', $xport_array['meta']['start']) . '"' . "\n";
	print '"End Date:","'       . date('Y-m-d H:i:s', $xport_array['meta']['end'])   . '"' . "\n";
	print '"Step:","'           . $xport_array['meta']['step']                       . '"' . "\n";
	print '"Total Rows:","'     . $xport_array['meta']['rows']                       . '"' . "\n";
	print '"Graph ID:","'       . $xport_array['meta']['local_graph_id']             . '"' . "\n";
	print '"Host ID:","'        . $xport_array['meta']['host_id']                    . '"' . "\n";

	if (isset($xport_meta['NthPercentile'])) {
		foreach($xport_meta['NthPercentile'] as $item) {
			print '"Nth Percentile:","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
		}
	}
	if (isset($xport_meta['Summation'])) {
		foreach($xport_meta['Summation'] as $item) {
			print '"Summation:","' . $item['value'] . '","' . $item['format'] . '"' . "\n";
		}
	}

	print '""' . "\n";

	$header = '"Date"';
	for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
		$header .= ',"' . $xport_array['meta']['legend']['col' . $i] . '"';
	}
	print $header . "\n";
}

if (is_array($xport_array['data'])) {
	foreach($xport_array['data'] as $row) {
		$data = '"' . date('Y-m-d H:i:s', $row['timestamp']) . '"';
		for ($i = 1; $i <= $xport_array['meta']['columns']; $i++) {
			$data .= ',"' . $row['col' . $i] . '"';
		}
		print $data . "\n";
	}
}

/* log the memory usage */
if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_MEDIUM && function_exists('memory_get_peak_usage')) {
	cacti_log("The Peak Graph XPORT Memory Usage was '" . memory_get_peak_usage() . "'", FALSE, 'WEBUI');
}

