<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

// since we'll have additional headers, tell php when to flush them
ob_start();

$guest_account = true;
$auth_text     = true;
$gtype         = 'png';

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

// ================= input validation =================
gfrv('graph_start');
gfrv('graph_end');
gfrv('graph_height');
gfrv('graph_width');
gfrv('local_graph_id');

if (isrv('graph_nolegend')) {
	srv('graph_nolegend', 'true');
}

gfrv('graph_theme', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);
// ====================================================

api_plugin_hook_function('graph_image');

$graph_data_array = [];

// Determine the graph type of the output
if (!isrv('image_format')) {
	$type   = db_fetch_cell_prepared('SELECT image_format_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		[grv('local_graph_id')]);

	switch($type) {
		case '1':
			$gtype = 'png';

			break;
		case '3':
			$gtype = 'svg+xml';

			break;
	}
} else {
	switch(cacti_strtolower(gnrv('image_format'))) {
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

// override: graph start time (unix time)
if (!ierv('graph_start') && grv('graph_start') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_start'] = grv('graph_start');
}

// override: graph end time (unix time)
if (!ierv('graph_end') && grv('graph_end') < FILTER_VALIDATE_MAX_DATE_AS_INT) {
	$graph_data_array['graph_end'] = grv('graph_end');
}

// override: graph height (in pixels)
if (!ierv('graph_height') && grv('graph_height') < 3000) {
	$graph_data_array['graph_height'] = grv('graph_height');
}

// override: graph width (in pixels)
if (!ierv('graph_width') && grv('graph_width') < 3000) {
	$graph_data_array['graph_width'] = grv('graph_width');
}

// override: skip drawing the legend?
if (!ierv('graph_nolegend')) {
	$graph_data_array['graph_nolegend'] = grv('graph_nolegend');
}

// print RRDtool graph source?
if (!ierv('show_source')) {
	$graph_data_array['print_source'] = grv('show_source');
}

// disable cache check
if (isrv('disable_cache')) {
	$graph_data_array['disable_cache'] = true;
}

// set the theme
if (isrv('graph_theme')) {
	$graph_data_array['graph_theme'] = cacti_validate_theme(grv('graph_theme'));
}

if (isrv('rra_id')) {
	if (gnrv('rra_id') == 'all') {
		$rra_id = 'all';
	} else {
		$rra_id = gfrv('rra_id');
	}
} else {
	$rra_id = null;
}

if (POLLER_ID == 1 || read_config_option('storage_location')) { // @phpstan-ignore-line
	$null_param = [];
	$output     = rrdtool_function_graph(grv('local_graph_id'), $rra_id, $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
} else {
	$url  = CACTI_PATH_URL . 'remote_agent.php?action=graph_json';
	$url .= '&local_graph_id=' . grv('local_graph_id');
	$url .= '&rra_id=' . $rra_id;
	$url .= '&effective_user=' . $_SESSION['sess_user_id'];

	foreach ($graph_data_array as $variable => $value) {
		$url .= '&' . rawurlencode((string) $variable) . '=' . rawurlencode((string) $value);
	}

	$output = call_remote_data_collector(1, $url);

	if (is_array($output) && isset($output['image'])) {
		$output = $output['image'];
	}

	if ($output !== false && $output != '') {
		// Find the beginning of the image definition row
		$image_begin_pos = strpos($output, 'image = ');

		if ($image_begin_pos !== false) {
			// Find the end of the line of the image definition row, after this the raw image data will come
			$image_data_pos = strpos($output, "\n", $image_begin_pos);

			if ($image_data_pos !== false) {
				// Insert the raw image data to the array
				$output = substr($output, $image_data_pos + 1);
			} else {
				$output = false;
			}
		} else {
			$output = false;
		}
	}
}

if ($output !== false && $output != '') {
	// flush the headers now
	ob_end_clean();

	header('Content-type: image/' . $gtype);
	header('Cache-Control: max-age=15');

	print $output;
} else {
	ob_start();

	// get the error string
	$graph_data_array['get_error'] = true;

	$null_param = [];

	rrdtool_function_graph(grv('local_graph_id'), $rra_id, $graph_data_array, null, $null_param, $_SESSION['sess_user_id']);

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

	if ($image != false) {
		print $image;
	} else {
		print file_get_contents(__DIR__ . '/images/cacti_error_image.png');
	}
}
