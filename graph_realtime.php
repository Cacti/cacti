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

$guest_account = true;

include('./include/auth.php');
include_once('./lib/rrd.php');

if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
case 'init':
case 'timespan':
case 'interval':
case 'countdown':
	ob_start();

	$guest_account = true;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('graph_start'));
	input_validate_input_number(get_request_var_request('graph_end'));
	input_validate_input_number(get_request_var_request('graph_height'));
	input_validate_input_number(get_request_var_request('graph_width'));
	input_validate_input_number(get_request_var_request('local_graph_id'));
	input_validate_input_number(get_request_var_request('ds_step'));
	input_validate_input_number(get_request_var_request('count'));
	input_validate_input_number(get_request_var_request('top'));
	input_validate_input_number(get_request_var_request('left'));
	/* ==================================================== */

	/* clean up action string */
	if (isset($_REQUEST['action'])) {
		$_REQUEST['action'] = sanitize_search_string(get_request_var('action'));
	}

	switch ($_REQUEST['action']) {
	case 'init':
		load_current_session_value('ds_step',     'sess_realtime_ds_step',     read_config_option('realtime_interval'));
		load_current_session_value('graph_start', 'sess_realtime_graph_start', read_config_option('realtime_gwindow'));

		break;
	case 'timespan':
		load_current_session_value('graph_start', 'sess_realtime_graph_start', read_config_option('realtime_gwindow'));

		break;
	case 'interval':
		load_current_session_value('ds_step',     'sess_realtime_ds_step',     read_config_option('realtime_interval'));

		break;
	case 'countdown':
		/* do nothing */

		break;
	}

	$graph_data_array = array();

	/* ds */
	$graph_data_array['ds_step'] = read_config_option('realtime_interval');
	if (!empty($_REQUEST['ds_step'])) {
		$graph_data_array['ds_step'] = (int)$_REQUEST['ds_step'];
		$_SESSION['sess_realtime_dsstep'] = $_REQUEST['ds_step'];
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
		$graph_data_array['graph_width'] = read_graph_config_option('default_width');
		$graph_data_array['graph_height'] = read_graph_config_option('default_height');
	}

	/* override: graph start */
	if (!empty($_REQUEST['graph_start'])) {
		$graph_data_array['graph_start'] = $_REQUEST['graph_start'];
		$_SESSION['sess_realtime_window'] = $_REQUEST['ds_step'];
	}

	/* override: graph end */
	if (!empty($_REQUEST['graph_end'])) {
		$graph_data_array['graph_end'] = $_REQUEST['graph_end'];
	}

	/* print RRDTool graph source? */
	if (!empty($_REQUEST['show_source'])) {
		$graph_data_array['print_source'] = $_REQUEST['show_source'];
	}

	/* check ds */
	if ($graph_data_array['ds_step'] < 1) {
		$graph_data_array['ds_step'] = read_config_option('realtime_interval');
	}

	/* call poller */
	$command = read_config_option('path_php_binary');
	$args    = sprintf('poller_realtime.php --graph=%s --interval=%d --poller_id=' . session_id(), $_REQUEST['local_graph_id'], $graph_data_array['ds_step']);

	shell_exec("$command $args");

	/* construct the image name  */
	$graph_data_array['export_realtime'] = read_config_option('realtime_cache_path') . '/user_' . session_id() . '_lgi_' . $_REQUEST['local_graph_id'] . '.png';
	$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_GRAPH_DATA;

	rrdtool_function_graph($_REQUEST['local_graph_id'], '', $graph_data_array);

	/* send text information back to browser as well as image information */
	$return_array = array(
		'local_graph_id' => $_REQUEST['local_graph_id'],
		'top' => $_REQUEST['top'],
		'left' => $_REQUEST['left'],
		'ds_step' => (isset($_SESSION['sess_realtime_ds_step']) ? $_SESSION['sess_realtime_ds_step']:$graph_data_array['ds_step']),
		'graph_start' => (isset($_SESSION['sess_realtime_graph_start']) ? $_SESSION['sess_realtime_graph_start']:$graph_data_array['graph_start']),
	);

	print json_encode($return_array);

	exit;
	break;
case 'view':
	$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . session_id() . '_lgi_' . $_REQUEST['local_graph_id'] . '.png';

	if (file_exists($graph_rrd)) {
		header('Content-type: image/png');
		print file_get_contents($graph_rrd);
	}
	exit;
	break;
}

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request('ds_step'));
input_validate_input_number(get_request_var_request('local_graph_id'));
input_validate_input_number(get_request_var_request('graph_start'));
/* ==================================================== */

$init = '';

if (!isset($_SESSION['sess_realtime_ds_step'])) {
	load_current_session_value('ds_step', 'sess_realtime_ds_step', read_config_option('realtime_interval'));
}else{
	$_REQUEST['ds_step'] = $_SESSION['sess_realtime_ds_step'];
}

if (!isset($_SESSION['sess_realtime_graph_start'])) {
	load_current_session_value('graph_start', 'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
}else{
	$_REQUEST['graph_start'] = $_SESSION['sess_realtime_graph_start'];
}

if (read_config_option('realtime_enabled') == '') {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>Realtime has been disabled by your administrator.</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
}elseif (!is_dir(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>The Image Cache Directory directory does not exist.  Please first create it and set permissions and then attempt to open another realtime graph.</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
}elseif (!is_writable(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>The Image Cache Directory is not writable.  Please set permissions and then attempt to open another realtime graph.</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
}

?>
<html>
<head>
    <meta http-equiv='X-UA-Compatible' content='edge'>
    <meta content='width=720, initial-scale=1.2, maximum-scale=1.2, minimum-scale=1.2' name='viewport'>
	<title>Cacti Realtime Graphing</title>
    <meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
    <link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.css' type='text/css' rel='stylesheet'>
    <link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.zoom.css' type='text/css' rel='stylesheet'>
    <link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery-ui.css' type='text/css' rel='stylesheet'>
    <link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/default/style.css' type='text/css' rel='stylesheet'>
    <link href='<?php echo $config['url_path']; ?>include/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>
    <link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
    <?php api_plugin_hook('page_head'); ?>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js' language='javascript'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js' language='javascript'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js' language='javascript'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jstree.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.zoom.js' language='javascript'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/calendar.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
    <script type='text/javascript' src='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.js'></script>
    <?php include($config['base_path'] . '/include/global_session.php'); api_plugin_hook('page_head'); ?>
</head>
<body style='text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;'>
<form method='post' action='graph_popup_rt.php' id='gform'>
	<div>
		<table align='center'>
			<tr>
				<td> 
					<strong>Timespan:</strong>
				</td>
				<td>
					<select name='graph_start' id='graph_start' onChange='self.imageOptionsChanged("timespan")'>
					<?php
					foreach ($realtime_window as $interval => $text) {
						printf('<option value="%d"%s>%s</option>',
							$interval, $interval == abs($_REQUEST['graph_start']) ? ' selected="selected"' : '', $text
						);
					}
					?>
					</select>
				</td>
				<td>
					<strong>Interval:</strong>
				</td>
				<td>
					<select name='ds_step' id='ds_step' onChange="self.imageOptionsChanged('interval')">
					<?php
					foreach ($realtime_refresh as $interval => $text) {
						printf('<option value="%d"%s>%s</option>',
							$interval, $interval == $_REQUEST['ds_step'] ? ' selected="selected"' : '', $text
						);
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td align='center' colspan='6'>
					<span id='countdown'><strong><?php echo $_REQUEST['ds_step']; ?> seconds left.</strong></span>
				</td>
			</tr>
		</table>
	</div>
	<br>
	<div id='image'>
		<i id='imaging' style='font-size:40px;' class='fa fa-spin fa-circle-o-notch'></i>
	</div>
	<input type='hidden' id='url_path' name='url_path' value='<?php echo $config['url_path'];?>'/>
	<input type='hidden' id='local_graph_id' name='local_graph_id' value='<?php echo $_REQUEST['local_graph_id']; ?>'/>
	<script type='text/javascript'>
	var url;
	var ds_step = 0;
	var sizeset = false;
	var count   = 0;
	var browser = "";

	function countdown_update() {
		ds_step--;

		if (ds_step < 0) {
			ds_step = $("#ds_step").val();
			imageOptionsChanged('countdown');
			sizeset = false;
		}

		$('#countdown').html( '<strong>' + ds_step + ' seconds left.</strong>');

		browser = realtimeDetectBrowser();

		/* set the window size */
		height = $(".graphimage").height();
		width  = $(".graphimage").width();

		//console.log("Height '" + height + "', Width '" + width + "'");

		if (height > 40) {
			if (browser == "IE") {
				width  = width  + 30;
				height = height + 110;
			}else{
				width  = width  + 40;
				height = height + 165;
			}

			if (sizeset == false) {
				if (browser == "FF") {
					window.outerHeight = height;
					window.outerWidth  = width;
				}else{
					window.resizeTo(width, height);
				}

				sizeset = true;
			}
		}

		count++;
	
		setTimeout('countdown_update()', 1000);
	}

	setTimeout('countdown_update()', 1000);

	function realtimeDetectBrowser() {
		if (navigator.userAgent.indexOf('MSIE') >= 0) {
			browser = "IE";
		}else if (navigator.userAgent.indexOf('Chrome') >= 0) {
			browser = 'Chrome';
		}else if (navigator.userAgent.indexOf('Mozilla') >= 0) {
			browser = "FF";
		}else if (navigator.userAgent.indexOf('Opera') >= 0) {
			browser = "Opera";
		}else{
			browser = "Other";
		}

		return browser;
	}

	$(function() {
		imageOptionsChanged('init');
	});

	function imageOptionsChanged(action) {
		graph_start    = $("#graph_start").val();
		graph_end      = 0;
		local_graph_id = $("#local_graph_id").val();
		ds_step        = $("#ds_step").val();

		url="?action="+action+"&graph_start=-"+graph_start+"&local_graph_id="+local_graph_id+"&ds_step="+ds_step+"&count="+count;

		$.get(url, function(data) {
			results = $.parseJSON(data);

			$('#image').empty().html("<img id='graph_"+local_graph_id+"' class='graphimage' alt='' src='graph_realtime.php?action=view&local_graph_id="+local_graph_id+"&count="+count+"'/>").change();
		});
	}
	</script>
</form>
</body>
</html>

