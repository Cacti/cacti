<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
$auth_text     = true;
$gtype = 'png';

include('./include/auth.php');
include_once('./lib/rrd.php');

/* ================= input validation ================= */
get_filter_request_var('graph_start');
get_filter_request_var('graph_end');
get_filter_request_var('graph_height');
get_filter_request_var('graph_width');
get_filter_request_var('local_graph_id');

if (isset_request_var('graph_nolegend')) {
	set_request_var('graph_nolegend', 'true');
}

get_filter_request_var('graph_theme', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
/* ==================================================== */

api_plugin_hook_function('graph_image');

$graph_data_array = array();

// Determine the graph type of the output
if (!isset_request_var('image_format')) {
	$type   = db_fetch_cell_prepared('SELECT image_format_id FROM graph_templates_graph WHERE local_graph_id = ?', array(get_request_var('local_graph_id')));
	switch($type) {
	case '1':
		$gtype = 'png';
		break;
	case '3':
		$gtype = 'svg+xml';
		break;
	}
} else {
	switch(strtolower(get_nfilter_request_var('image_format'))) {
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

cacti_session_close();

/* override: graph start time (unix time) */
if (!isempty_request_var('graph_start') && get_request_var('graph_start') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_start'] = get_request_var('graph_start');
}

/* override: graph end time (unix time) */
if (!isempty_request_var('graph_end') && get_request_var('graph_end') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_end'] = get_request_var('graph_end');
}

/* override: graph height (in pixels) */
if (!isempty_request_var('graph_height') && get_request_var('graph_height') < 3000) {
	$graph_data_array['graph_height'] = get_request_var('graph_height');
}

/* override: graph width (in pixels) */
if (!isempty_request_var('graph_width') && get_request_var('graph_width') < 3000) {
	$graph_data_array['graph_width'] = get_request_var('graph_width');
}

/* override: skip drawing the legend? */
if (!isempty_request_var('graph_nolegend')) {
	$graph_data_array['graph_nolegend'] = get_request_var('graph_nolegend');
}

/* print RRDtool graph source? */
if (!isempty_request_var('show_source')) {
	$graph_data_array['print_source'] = get_request_var('show_source');
}

/* disable cache check */
if (isset_request_var('disable_cache')) {
	$graph_data_array['disable_cache'] = true;
}

/* set the theme */
if (isset_request_var('graph_theme')) {
	$graph_data_array['graph_theme'] = get_request_var('graph_theme');
}

if (isset_request_var('rra_id')) {
	if (get_nfilter_request_var('rra_id') == 'all') {
		$rra_id = 'all';
	} else {
		$rra_id = get_filter_request_var('rra_id');
	}
} else {
	$rra_id = null;
}

$null_param = array();
$output = rrdtool_function_graph(get_request_var('local_graph_id'), $rra_id, $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]);

if ($output !== false && $output != '') {
	/* flush the headers now */
	ob_end_clean();

	header('Content-type: image/'. $gtype);
	header('Cache-Control: max-age=15');

	print $output;
} else {
	ob_start();

	/* get the error string */
	$graph_data_array['get_error'] = true;
	$null_param = array();
	rrdtool_function_graph(get_request_var('local_graph_id'), $rra_id, $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]);

	$error = ob_get_contents();

	if (read_config_option('stats_poller') == '') {
		$error = __('The Cacti Poller has not run yet.');
	}

	if (isset($graph_data_array['graph_width']) && isset($graph_data_array['graph_height'])) {
		$image = rrdtool_create_error_image($error, $graph_data_array['graph_width'], $graph_data_array['graph_height']);
	} else {
		$image = rrdtool_create_error_image($error);
	}

	ob_end_clean();

	header('Content-type: image/png');
	header('Cache-Control: max-age=15');

	if ($image !== false) {
		print $image;
	} else {
		print file_get_contents(__DIR__ . '/images/cacti_error_image.png');
	}
}

