<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

api_plugin_hook_function('graph_image');

/* ================= input validation ================= */
input_validate_input_number(get_request_var('graph_start'));
input_validate_input_number(get_request_var('graph_end'));
input_validate_input_number(get_request_var('graph_height'));
input_validate_input_number(get_request_var('graph_width'));
input_validate_input_number(get_request_var('local_graph_id'));
input_validate_input_number(get_request_var('rra_id'));
/* ==================================================== */

if (!is_numeric(get_request_var('local_graph_id'))) {
	die_html_input_error();
}

if (!is_numeric(get_request_var('local_graph_id'))) {
	die_html_input_error();
}

$graph_data_array = array();

// Determine the graph type of the output
if (!isset($_REQUEST['image_format'])) {
	$type   = db_fetch_cell('SELECT image_format_id FROM graph_templates_graph WHERE local_graph_id=' . $_REQUEST['local_graph_id']);
	switch($type) {
	case '1':
		$gtype = 'png';
		break;
	case '3':
		$gtype = 'svg+xml';
		break;
	}
}else{
	switch(strtolower(get_request_var('image_format'))) {
	case 'png':
		$gtype = 'png';
		break;
	case 'svg':
		$gtype = 'svg+xml';
		break;
	default:
		$gtype = 'png';
		break;
	}
}

$graph_data_array['image_format'] = $gtype;

header('Content-type: image/'. $gtype);

/* flush the headers now */
ob_end_clean();

session_write_close();

/* override: graph start time (unix time) */
if (!empty($_REQUEST['graph_start']) && $_REQUEST['graph_start'] < 1600000000) {
	$graph_data_array['graph_start'] = $_REQUEST['graph_start'];
}

/* override: graph end time (unix time) */
if (!empty($_REQUEST['graph_end']) && $_REQUEST['graph_end'] < 1600000000) {
	$graph_data_array['graph_end'] = $_REQUEST['graph_end'];
}

/* override: graph height (in pixels) */
if (!empty($_REQUEST['graph_height']) && $_REQUEST['graph_height'] < 3000) {
	$graph_data_array['graph_height'] = $_REQUEST['graph_height'];
}

/* override: graph width (in pixels) */
if (!empty($_REQUEST['graph_width']) && $_REQUEST['graph_width'] < 3000) {
	$graph_data_array['graph_width'] = $_REQUEST['graph_width'];
}

/* override: skip drawing the legend? */
if (!empty($_REQUEST['graph_nolegend'])) {
	$graph_data_array['graph_nolegend'] = $_REQUEST['graph_nolegend'];
}

/* print RRDTool graph source? */
if (!empty($_REQUEST['show_source'])) {
	$graph_data_array['print_source'] = $_REQUEST['show_source'];
}

/* disable cache check */
if (isset($_REQUEST['disable_cache'])) {
	$graph_data_array['disable_cache'] = true;
}

print @rrdtool_function_graph($_REQUEST['local_graph_id'], (array_key_exists('rra_id', $_REQUEST) ? $_REQUEST['rra_id'] : null), $graph_data_array);

