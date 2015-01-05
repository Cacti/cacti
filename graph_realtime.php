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

if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

switch ($_REQUEST["action"]) {
case 'init':
case 'timespan':
case 'interval':
case 'sync':
case 'countdown':
	ob_start();

	$guest_account = true;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("graph_start"));
	input_validate_input_number(get_request_var_request("graph_end"));
	input_validate_input_number(get_request_var_request("graph_height"));
	input_validate_input_number(get_request_var_request("graph_width"));
	input_validate_input_number(get_request_var_request("local_graph_id"));
	input_validate_input_number(get_request_var_request("ds_step"));
	input_validate_input_number(get_request_var_request("count"));
	/* ==================================================== */

	/* clean up sync string */
	if (isset($_REQUEST["sync"])) {
		$_REQUEST["sync"] = sanitize_search_string(get_request_var("sync"));
	}

	/* clean up action string */
	if (isset($_REQUEST["action"])) {
		$_REQUEST["action"] = sanitize_search_string(get_request_var("action"));
	}

	switch ($_REQUEST["action"]) {
	case 'init':
		load_current_session_value("ds_step",     "sess_realtime_ds_step",     read_config_option("realtime_interval"));
		load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));
		load_current_session_value("sync",        "sess_realtime_sync",        read_config_option("realtime_sync"));

		break;
	case 'timespan':
		load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));

		break;
	case 'interval':
		load_current_session_value("ds_step",     "sess_realtime_ds_step",     read_config_option("realtime_interval"));

		break;
	case 'sync':
		load_current_session_value("sync",        "sess_realtime_sync",        read_config_option("realtime_sync"));

		break;
	case 'countdown':
		/* do nothing */

		break;
	}

	$graph_data_array = array();

	/* ds */
	$graph_data_array["ds_step"] = read_config_option("realtime_interval");
	if (!empty($_REQUEST["ds_step"]))
		$graph_data_array["ds_step"] = (int)$_REQUEST["ds_step"];

	/* override: graph height (in pixels) */
	if (!empty($_REQUEST["graph_height"]) && $_REQUEST["graph_height"] < 3000) {
		$graph_data_array["graph_height"] = $_REQUEST["graph_height"];
	}

	/* override: graph width (in pixels) */
	if (!empty($_REQUEST["graph_width"]) && $_REQUEST["graph_width"] < 3000) {
		$graph_data_array["graph_width"] = $_REQUEST["graph_width"];
	}

	/* override: skip drawing the legend? */
	if (!empty($_REQUEST["graph_nolegend"])) {
		$graph_data_array["graph_nolegend"] = $_REQUEST["graph_nolegend"];
	}

	/* override: graph start */
	if (!empty($_REQUEST["graph_start"])) {
		$graph_data_array["graph_start"] = $_REQUEST["graph_start"];
	}

	/* override: graph end */
	if (!empty($_REQUEST["graph_end"])) {
		$graph_data_array["graph_end"] = $_REQUEST["graph_end"];
	}

	/* print RRDTool graph source? */
	if (!empty($_REQUEST["show_source"])) {
		$graph_data_array["print_source"] = $_REQUEST["show_source"];
	}

	/* check ds */
	if ($graph_data_array["ds_step"] < 1) {
		$graph_data_array["ds_step"] = read_config_option("realtime_interval");
	}

	/* call poller */
	$command = read_config_option("path_php_binary");
	$args    = sprintf('poller_realtime.php --graph=%s --interval=%d', $_REQUEST["local_graph_id"], $graph_data_array["ds_step"]);

	shell_exec("$command $args");

	/* construct the image name  */
	$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . $_SESSION['sess_user_id'] . '_lgi_' . $_REQUEST['local_graph_id'] . ".png";

	graph_me($_REQUEST['local_graph_id'], $graph_data_array, $graph_rrd);

	if (isset($_SESSION["sess_realtime_sync"]) && strlen($_SESSION["sess_realtime_sync"])) {
		$sync = "on";
	}else{
		$sync = "off";
	}

	/* send text information back to browser as well as image information */
	$return_array = array(
		'ds_step' => (isset($_SESSION["sess_realtime_ds_step"]) ? $_SESSION["sess_realtime_ds_step"]:$graph_data_array["ds_step"]),
		'graph_start' => (isset($_SESSION["sess_realtime_graph_start"]) ? $_SESSION["sess_realtime_graph_start"]:$graph_data_array["graph_start"]),
		'sync' => $sync
	);

	print json_encode($return_array);

	exit;
	break;
case 'view':
	$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . $_SESSION['sess_user_id'] . '_lgi_' . $_REQUEST['local_graph_id'] . ".png";

	if (file_exists($graph_rrd)) {
		header("Content-type: image/png");
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

load_current_session_value('sync', 'sess_realtime_sync', read_config_option('realtime_sync'));

if ($_REQUEST['sync'] == 'on') {
	load_current_session_value('ds_step', 'sess_realtime_ds_step', read_config_option('realtime_interval'));
	load_current_session_value('graph_start', 'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
	$init = 'init';
}else{
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
				<td>
					<strong>Synchronize:</strong>
				</td>
				<td>
					<input type='checkbox' id='sync' name='Synchronize' <?php echo (($_REQUEST['sync'] == 'on') ? 'checked': '');?> onChange="self.imageOptionsChanged('sync')"/>
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
		sync = $("#sync").is(':checked');
		if (sync) {
			sync = "on";
		}else{
			sync = "";
		}

		graph_start    = $("#graph_start").val();
		graph_end      = 0;
		local_graph_id = $("#local_graph_id").val();
		ds_step        = $("#ds_step").val();

		url="?action="+action+"&graph_start=-"+graph_start+"&local_graph_id="+local_graph_id+"&ds_step="+ds_step+"&count="+count+"&sync="+sync;

		$.get(url, function(data) {
			results = $.parseJSON(data);

			$('#image').empty().html("<img id='graph_"+local_graph_id+"' class='graphimage' alt='' src='graph_realtime.php?action=view&local_graph_id="+local_graph_id+"&count="+count+"'/>").change();
		});
	}
	</script>
</form>
</body>
</html>
<?php

function graph_me($local_graph_id, $graph_data_array, $cache_file) {
	/* since we'll have additional headers, tell php when to flush them */
	ob_start();

	include_once("./lib/rrd.php");

	ob_end_clean();
	session_write_close();

	/* print out the content now */
	$data = rrdtool_function_graph_rt($local_graph_id, $graph_data_array);

	if ($fp = fopen($cache_file, 'w')) {
		fwrite($fp, $data, strlen($data));
		fclose($fp);
		chmod($cache_file, 0644);
	}
}

/* functions */
function rrdtool_function_graph_rt($local_graph_id, $graph_data_array) {
	global $config;

	include_once($config["library_path"] . "/cdef.php");
	include_once($config["library_path"] . "/graph_variables.php");
	include($config["include_path"] . "/global_arrays.php");

	/* set the rrdtool default font */
	if (read_config_option("path_rrdtool_default_font")) {
		putenv("RRD_DEFAULT_FONT=" . read_config_option("path_rrdtool_default_font"));
	}

	/* before we do anything; make sure the user has permission to view this graph,
	if not then get out */
	if ((read_config_option("auth_method") != 0) && (isset($_SESSION["sess_user_id"]))) {
		$access_denied = !(is_graph_allowed($local_graph_id));

		if ($access_denied == true) {
			return "GRAPH ACCESS DENIED";
		}
	}

	/* find the step and how often this graph is updated with new data
	 * each second */
	$ds_step = @$graph_data_array["ds_step"];
	if ($ds_step == 0) {
		$ds_step = read_config_option("realtime_interval");
	}else if ($ds_step < 1) {
		$ds_step = 1;
	}

	/* if no rra was specified, we need to figure out which one RRDTool will choose using
	 * "best-fit" resolution fit algorithm */
	$rra["rows"] = 600;
	$rra["steps"] = 1;
	$rra["timespan"] = 2400; /* 30 minutes */

	$seconds_between_graph_updates = ($ds_step * $rra["steps"]);

	$graph = db_fetch_row("select
		graph_local.host_id,
		graph_local.snmp_query_id,
		graph_local.snmp_index,
		graph_templates_graph.title_cache,
		graph_templates_graph.vertical_label,
		graph_templates_graph.slope_mode,
		graph_templates_graph.auto_scale,
		graph_templates_graph.auto_scale_opts,
		graph_templates_graph.auto_scale_log,
		graph_templates_graph.scale_log_units,
		graph_templates_graph.auto_scale_rigid,
		graph_templates_graph.auto_padding,
		graph_templates_graph.base_value,
		graph_templates_graph.upper_limit,
		graph_templates_graph.lower_limit,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.image_format_id,
		graph_templates_graph.unit_value,
		graph_templates_graph.unit_exponent_value,
		graph_templates_graph.export
		from (graph_templates_graph,graph_local)
		where graph_local.id=graph_templates_graph.local_graph_id
		and graph_templates_graph.local_graph_id=$local_graph_id");

	/* lets make that sql query... */
	$graph_items = db_fetch_assoc("select
		graph_templates_item.id as graph_templates_item_id,
		graph_templates_item.cdef_id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		graph_templates_gprint.gprint_text,
		colors.hex,
		graph_templates_item.alpha,
		data_template_rrd.id as data_template_rrd_id,
		data_template_rrd.local_data_id,
		data_template_rrd.rrd_minimum,
		data_template_rrd.rrd_maximum,
		data_template_rrd.data_source_name,
		data_template_rrd.local_data_template_rrd_id
		from graph_templates_item
		left join data_template_rrd on (graph_templates_item.task_item_id=data_template_rrd.id)
		left join colors on (graph_templates_item.color_id=colors.id)
		left join graph_templates_gprint on (graph_templates_item.gprint_id=graph_templates_gprint.id)
		where graph_templates_item.local_graph_id=$local_graph_id
		order by graph_templates_item.sequence");

	/* +++++++++++++++++++++++ GRAPH OPTIONS +++++++++++++++++++++++ */

	/* define some variables */
	$scale = "";
	$rigid = "";
	$unit_value = "";
	$unit_exponent_value = "";
	$graph_legend = "";
	$graph_defs = "";
	$txt_graph_items = "";
	$text_padding = "";
	$greatest_text_format = 0;
	$last_graph_type = "";

	if ($graph["auto_scale"] == "on") {
		switch ($graph["auto_scale_opts"]) {
			case "1": /* autoscale ignores lower, upper limit */
				$scale = "--alt-autoscale" . RRD_NL;
				break;
			case "2": /* autoscale-max, accepts a given lower limit */
				$scale = "--alt-autoscale-max" . RRD_NL;
				if ( is_numeric($graph["lower_limit"])) {
					$scale .= "--lower-limit=" . $graph["lower_limit"] . RRD_NL;
				}
				break;
			case "3": /* autoscale-min, accepts a given upper limit */
				$scale = "--alt-autoscale-min" . RRD_NL;
				if ( is_numeric($graph["upper_limit"])) {
					$scale .= "--upper-limit=" . $graph["upper_limit"] . RRD_NL;
				}
				break;
			case "4": /* auto_scale with limits */
				$scale = "--alt-autoscale" . RRD_NL;
				if ( is_numeric($graph["upper_limit"])) {
					$scale .= "--upper-limit=" . $graph["upper_limit"] . RRD_NL;
				}
				if ( is_numeric($graph["lower_limit"])) {
					$scale .= "--lower-limit=" . $graph["lower_limit"] . RRD_NL;
				}
				break;
		}
	}else{
		$graph["lower_limit"] = rrd_substitute_host_query_data($graph["lower_limit"], $graph, null);
		$graph["upper_limit"] = rrd_substitute_host_query_data($graph["upper_limit"], $graph, null);

		$scale  =  "--upper-limit=" . $graph["upper_limit"] . RRD_NL;
		$scale .= "--lower-limit="  . $graph["lower_limit"] . RRD_NL;
	}

	if ($graph["auto_scale_log"] == "on") {
		$scale .= "--logarithmic" . RRD_NL;
	}

	/* --units=si only defined for logarithmic y-axis scaling, even if it doesn't hurt on linear graphs */
	if (($graph["scale_log_units"] == "on") &&
		($graph["auto_scale_log"] == "on")) {
		$scale .= "--units=si" . RRD_NL;
	}

	if ($graph["auto_scale_rigid"] == "on") {
		$rigid = "--rigid" . RRD_NL;
	}

	if (!empty($graph["unit_value"])) {
		$unit_value = "--y-grid=" . $graph["unit_value"] . RRD_NL;
	}

	if (ereg("^[0-9]+$", $graph["unit_exponent_value"])) {
		$unit_exponent_value = "--units-exponent=" . $graph["unit_exponent_value"] . RRD_NL;
	}

	/*
	 * optionally you can specify and array that overrides some of the db's values, lets set
	 * that all up here
	 */

	/* override: graph start time */
	if ((!isset($graph_data_array["graph_start"])) || ($graph_data_array["graph_start"] == "0")) {
		$graph_start = -($rra["timespan"]);
	}else{
		$graph_start = $graph_data_array["graph_start"];
	}

	/* override: graph end time */
	if ((!isset($graph_data_array["graph_end"])) || ($graph_data_array["graph_end"] == "0")) {
		$graph_end = -($seconds_between_graph_updates);
	}else{
		$graph_end = $graph_data_array["graph_end"];
	}

	/* override: graph height (in pixels) */
	if (isset($graph_data_array["graph_height"])) {
		$graph_height = $graph_data_array["graph_height"];
	}else{
		$graph_height = $graph["height"];
	}

	/* override: graph width (in pixels) */
	if (isset($graph_data_array["graph_width"])) {
		$graph_width = $graph_data_array["graph_width"];
	}else{
		$graph_width = $graph["width"];
	}

	/* override: skip drawing the legend? */
	if (isset($graph_data_array["graph_nolegend"])) {
		$graph_legend = "--no-legend" . RRD_NL;
	}else{
		$graph_legend = "";
	}

	/* define: let's do the x grids */
	if (isset($graph_data_array["graph_start"])) {
	switch($graph_data_array["graph_start"]) {
	case -30:
	case -45:
		$graph_xlegend = "--x-grid SECOND:5:MINUTE:1:SECOND:5:0:%M:%S" . RRD_NL;

		break;
	case -60:
	case -90:
		$graph_xlegend = "--x-grid SECOND:10:MINUTE:1:SECOND:10:0:%M:%S" . RRD_NL;

		break;
	case -120:
		$graph_xlegend = "--x-grid SECOND:20:MINUTE:1:SECOND:20:0:%M:%S" . RRD_NL;

		break;
	case -300:
		$graph_xlegend = "--x-grid SECOND:30:MINUTE:1:SECOND:30:0:%M:%S" . RRD_NL;

		break;
	case -600:
		$graph_xlegend = "--x-grid SECOND:30:MINUTE:1:MINUTE:1:0:%H:%M" . RRD_NL;

		break;
	case -1200:
		$graph_xlegend = "--x-grid SECOND:30:MINUTE:1:MINUTE:2:0:%H:%M" . RRD_NL;

		break;
	case -1800:
	case -3600:
		$graph_xlegend = "";

		break;
	}
	}else{
		$graph_xlegend = "";
	}

	/* export options */
	if (isset($graph_data_array["export"])) {
		$graph_opts = read_config_option("path_html_export") . "/" . $graph_data_array["export_filename"] . RRD_NL;
	}else{
		if (empty($graph_data_array["output_filename"])) {
				$graph_opts = "-" . RRD_NL;
		}else{
			$graph_opts = $graph_data_array["output_filename"] . RRD_NL;
		}
	}

	/* setup date format */
	$date_fmt = read_graph_config_option("default_date_format");
	$datechar = read_graph_config_option("default_datechar");

	if ($datechar == GDC_HYPHEN) {
		$datechar = "-";
	}else {
		$datechar = "/";
	}

	switch ($date_fmt) {
		case GD_MO_D_Y:
			$graph_date = "m" . $datechar . "d" . $datechar . "Y H:i:s";
			break;
		case GD_MN_D_Y:
			$graph_date = "M" . $datechar . "d" . $datechar . "Y H:i:s";
			break;
		case GD_D_MO_Y:
			$graph_date = "d" . $datechar . "m" . $datechar . "Y H:i:s";
			break;
		case GD_D_MN_Y:
			$graph_date = "d" . $datechar . "M" . $datechar . "Y H:i:s";
			break;
		case GD_Y_MO_D:
			$graph_date = "Y" . $datechar . "m" . $datechar . "d H:i:s";
			break;
		case GD_Y_MN_D:
			$graph_date = "Y" . $datechar . "M" . $datechar . "d H:i:s";
			break;
	}

	/* display the timespan for zoomed graphs */
	if ((isset($graph_data_array["graph_start"])) && (isset($graph_data_array["graph_end"]))) {
		if (($graph_data_array["graph_start"] < 0) && ($graph_data_array["graph_end"] < 0)) {
			$graph_legend .= "COMMENT:\"From " . str_replace(":", "\:", date($graph_date, time()+$graph_data_array["graph_start"])) . " To " . str_replace(":", "\:", date($graph_date, time()+$graph_data_array["graph_end"])) . "\\c\"" . RRD_NL . "COMMENT:\"  \\n\"" . RRD_NL;
		}else if (($graph_data_array["graph_start"] >= 0) && ($graph_data_array["graph_end"] >= 0)) {
			$graph_legend .= "COMMENT:\"From " . str_replace(":", "\:", date($graph_date, $graph_data_array["graph_start"])) . " To " . str_replace(":", "\:", date($graph_date, $graph_data_array["graph_end"])) . "\\c\"" . RRD_NL . "COMMENT:\"  \\n\"" . RRD_NL;
		}
	}

	/* basic graph options */
	$graph_opts .=
		"--imgformat=" . $image_types{$graph["image_format_id"]} . RRD_NL .
		"--start=$graph_start" . RRD_NL .
		"--end=$graph_end" . RRD_NL .
		"--title=\"" . str_replace("\"", "\\\"", $graph["title_cache"]) . "\"" . RRD_NL .
		"$rigid" .
		"--base=" . $graph["base_value"] . RRD_NL .
		"--height=$graph_height" . RRD_NL .
		"--width=$graph_width" . RRD_NL .
		"$graph_xlegend" .
		"$scale" .
		"$unit_value" .
		"$unit_exponent_value" .
		"$graph_legend" .
		"--vertical-label=\"" . $graph["vertical_label"] . "\"" . RRD_NL;

	/* rrdtool 1.2.x does not provide smooth lines, let's force it */
	if ($graph["slope_mode"] == "on") {
		$graph_opts .= "--slope-mode" . RRD_NL;
	}

	/* rrdtool 1.2 font options */
	/* title fonts */
	$graph_opts .= rrdtool_set_font("title", ((!empty($graph_data_array["graph_nolegend"])) ? $graph_data_array["graph_nolegend"] : ""));

	/* axis fonts */
	$graph_opts .= rrdtool_set_font("axis");

	/* legend fonts */
	$graph_opts .= rrdtool_set_font("legend");

	/* unit fonts */
	$graph_opts .= rrdtool_set_font("unit");

	$i = 0; $j = 0;
	$last_graph_cf = array();
	if (sizeof($graph_items) > 0) {
		/* we need to add a new column "cf_reference", so unless PHP 5 is used, this foreach syntax is required */
		foreach ($graph_items as $key => $graph_item) {
			/* mimic the old behavior: LINE[123], AREA and STACK items use the CF specified in the graph item */
			if (($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_LINE1) ||
				($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_LINE2) ||
				($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_LINE3) ||
				($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_AREA)  ||
				($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_STACK)) {
				$graph_cf = $graph_item["consolidation_function_id"];
				/* remember the last CF for this data source for use with GPRINT
				 * if e.g. an AREA/AVERAGE and a LINE/MAX is used
				 * we will have AVERAGE first and then MAX, depending on GPRINT sequence */
				$last_graph_cf["data_source_name"]["local_data_template_rrd_id"] = $graph_cf;
				/* remember this for second foreach loop */
				$graph_items[$key]["cf_reference"] = $graph_cf;
			}elseif ($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_GPRINT) {
				/* ATTENTION!
				 * the "CF" given on graph_item edit screen for GPRINT is indeed NOT a real "CF",
				 * but an aggregation function
				 * see "man rrdgraph_data" for the correct VDEF based notation
				 * so our task now is to "guess" the very graph_item, this GPRINT is related to
				 * and to use that graph_item's CF */
				if (isset($last_graph_cf["data_source_name"]["local_data_template_rrd_id"])) {
					$graph_cf = $last_graph_cf["data_source_name"]["local_data_template_rrd_id"];
					/* remember this for second foreach loop */
					$graph_items[$key]["cf_reference"] = $graph_cf;
				} else {
					$graph_cf = generate_graph_best_cf($graph_item["local_data_id"], $graph_item["consolidation_function_id"]);
					/* remember this for second foreach loop */
					$graph_items[$key]["cf_reference"] = $graph_cf;
				}
			}else{
				/* all other types are based on the best matching CF */
				$graph_cf = generate_graph_best_cf($graph_item["local_data_id"], $graph_item["consolidation_function_id"]);
				/* remember this for second foreach loop */
				$graph_items[$key]["cf_reference"] = $graph_cf;
			}

			if ((!empty($graph_item["local_data_id"])) && (!isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$graph_cf]))) {
				/* use a user-specified ds path if one is entered */
				$data_source_path = read_config_option("realtime_cache_path") .
					"/realtime_" . $graph_item["local_data_id"] . "_5.rrd";

				/* FOR WIN32: Escape all colon for drive letters (ex. D\:/path/to/rra) */
				$data_source_path = str_replace(":", "\:", $data_source_path);

				if (!empty($data_source_path)) {
					/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
					to a function that matches the digits with letters. rrdtool likes letters instead
					of numbers in DEF names; especially with CDEF's. cdef's are created
					the same way, except a 'cdef' is put on the beginning of the hash */
					$graph_defs .= "DEF:" . generate_graph_def_name(strval($i)) . "=\"$data_source_path\":" . $graph_item["data_source_name"] . ":" . $consolidation_functions[$graph_cf] . RRD_NL;

					$cf_ds_cache{$graph_item["data_template_rrd_id"]}[$graph_cf] = "$i";

					$i++;
				}
			}

			/* cache cdef value here to support data query variables in the cdef string */
			if (empty($graph_item["cdef_id"])) {
				$graph_item["cdef_cache"] = "";
				$graph_items[$j]["cdef_cache"] = "";
			}else{
				$graph_item["cdef_cache"] = get_cdef($graph_item["cdef_id"]);
				$graph_items[$j]["cdef_cache"] = get_cdef($graph_item["cdef_id"]);
			}

			/* +++++++++++++++++++++++ LEGEND: TEXT SUBSITUTION (<>'s) +++++++++++++++++++++++ */

			/* note the current item_id for easy access */
			$graph_item_id = $graph_item["graph_templates_item_id"];

			/* the following fields will be searched for graph variables */
			$variable_fields = array(
				"text_format" => array(
					"process_no_legend" => false
					),
				"value" => array(
					"process_no_legend" => true
					),
				"cdef_cache" => array(
					"process_no_legend" => true
					)
				);

			/* loop through each field that we want to substitute values for:
			currently: text format and value */
			while (list($field_name, $field_array) = each($variable_fields)) {
				/* certain fields do not require values when the legend is not to be shown */
				if (($field_array["process_no_legend"] == false) && (isset($graph_data_array["graph_nolegend"]))) {
					continue;
				}

				$graph_variables[$field_name][$graph_item_id] = $graph_item[$field_name];

				/* date/time substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], "|date_time|")) {
					$graph_variables[$field_name][$graph_item_id] = str_replace("|date_time|", date('D d M H:i:s T Y', strtotime(db_fetch_cell("select value from settings where name='date'"))), $graph_variables[$field_name][$graph_item_id]);
				}

				/* data source title substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], "|data_source_title|")) {
					$graph_variables[$field_name][$graph_item_id] = trim(str_replace("|data_source_title|", get_data_source_title($graph_item["local_data_id"]), $graph_variables[$field_name][$graph_item_id]));
				}

				/* data query variables */
				$graph_variables[$field_name][$graph_item_id] = rrd_substitute_host_query_data($graph_variables[$field_name][$graph_item_id], $graph, $graph_item);

				/* Nth percentile */
				if (preg_match_all("/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate):(\d)?\|/", $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$graph_variables[$field_name][$graph_item_id] = str_replace($match[0], variable_nth_percentile($match, $graph_item, $graph_items, $graph_start, $graph_end), $graph_variables[$field_name][$graph_item_id]);
					}
				}

				/* bandwidth summation */
				if (preg_match_all("/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/", $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$graph_variables[$field_name][$graph_item_id] = str_replace($match[0], variable_bandwidth_summation($match, $graph_item, $graph_items, $graph_start, $graph_end, $rra["steps"], $ds_step), $graph_variables[$field_name][$graph_item_id]);
					}
				}
			}

			/* if we are not displaying a legend there is no point in us even processing the auto padding,
			text format stuff. */
			if (!isset($graph_data_array["graph_nolegend"])) {
				/* set hard return variable if selected (\n) */
				if ($graph_item["hard_return"] == "on") {
					$hardreturn[$graph_item_id] = "\\n";
				}else{
					$hardreturn[$graph_item_id] = "";
				}

				/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>'s) +++++++++++++++++++++++ */

				/* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
				AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
				of course it can be used in other situations, however may not work as intended.
				If you have any additions to this small peice of code, feel free to send them to me. */
				if ($graph["auto_padding"] == "on") {
					/* only applies to AREA, STACK and LINEs */
					if (ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_item["graph_type_id"]})) {
						$text_format_length = strlen($graph_variables["text_format"][$graph_item_id]);

						if ($text_format_length > $greatest_text_format) {
							$greatest_text_format = $text_format_length;
						}
					}
				}
			}

			$j++;
		}
	}

	/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's +++++++++++++++++++++++ */

	$i = 0;
	reset($graph_items);

	/* hack for rrdtool 1.2.x support */
	$graph_item_stack_type = "";

	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $graph_item) {
		/* first we need to check if there is a DEF for the current data source/cf combination. if so,
		we will use that */
		if (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}{$graph_item["consolidation_function_id"]})) {
			$cf_id = $graph_item["consolidation_function_id"];
		}else{
		/* if there is not a DEF defined for the current data source/cf combination, then we will have to
		improvise. choose the first available cf in the following order: AVERAGE, MAX, MIN, LAST */
			if (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[1])) {
				$cf_id = 1; /* CF: AVERAGE */
			}elseif (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[3])) {
				$cf_id = 3; /* CF: MAX */
			}elseif (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[2])) {
				$cf_id = 2; /* CF: MIN */
			}elseif (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[4])) {
				$cf_id = 4; /* CF: LAST */
			}else{
				$cf_id = 1; /* CF: AVERAGE */
			}
		}
		/* now remember the correct CF reference */
		$cf_id = $graph_item["cf_reference"];

		/* make cdef string here; a note about CDEF's in cacti. A CDEF is neither unique to a
		data source of global cdef, but is unique when those two variables combine. */
		$cdef_graph_defs = "";

		if ((!empty($graph_item["cdef_id"])) && (!isset($cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]}[$cf_id]))) {

			$cdef_string 	= $graph_variables["cdef_cache"]{$graph_item["graph_templates_item_id"]};
			$magic_item 	= array();
			$already_seen	= array();
			$sources_seen	= array();
			$count_all_ds_dups = 0;
			$count_all_ds_nodups = 0;
			$count_similar_ds_dups = 0;
			$count_similar_ds_nodups = 0;

			/* if any of those magic variables are requested ... */
			if (ereg("(ALL_DATA_SOURCES_(NO)?DUPS|SIMILAR_DATA_SOURCES_(NO)?DUPS)", $cdef_string) ||
				ereg("(COUNT_ALL_DS_(NO)?DUPS|COUNT_SIMILAR_DS_(NO)?DUPS)", $cdef_string)) {

				/* now walk through each case to initialize array*/
				if (ereg("ALL_DATA_SOURCES_DUPS", $cdef_string)) {
					$magic_item["ALL_DATA_SOURCES_DUPS"] = "";
				}
				if (ereg("ALL_DATA_SOURCES_NODUPS", $cdef_string)) {
					$magic_item["ALL_DATA_SOURCES_NODUPS"] = "";
				}
				if (ereg("SIMILAR_DATA_SOURCES_DUPS", $cdef_string)) {
					$magic_item["SIMILAR_DATA_SOURCES_DUPS"] = "";
				}
				if (ereg("SIMILAR_DATA_SOURCES_NODUPS", $cdef_string)) {
					$magic_item["SIMILAR_DATA_SOURCES_NODUPS"] = "";
				}
				if (ereg("COUNT_ALL_DS_DUPS", $cdef_string)) {
					$magic_item["COUNT_ALL_DS_DUPS"] = "";
				}
				if (ereg("COUNT_ALL_DS_NODUPS", $cdef_string)) {
					$magic_item["COUNT_ALL_DS_NODUPS"] = "";
				}
				if (ereg("COUNT_SIMILAR_DS_DUPS", $cdef_string)) {
					$magic_item["COUNT_SIMILAR_DS_DUPS"] = "";
				}
				if (ereg("COUNT_SIMILAR_DS_NODUPS", $cdef_string)) {
					$magic_item["COUNT_SIMILAR_DS_NODUPS"] = "";
				}

				/* loop over all graph items */
				for ($t=0;($t<count($graph_items));$t++) {

					/* only work on graph items, omit GRPINTs, COMMENTs and stuff */
					if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
						/* if the user screws up CF settings, PHP will generate warnings if left unchecked */

						/* matching consolidation function? */
						if (isset($cf_ds_cache{$graph_items[$t]["data_template_rrd_id"]}[$cf_id])) {
							$def_name = generate_graph_def_name(strval($cf_ds_cache{$graph_items[$t]["data_template_rrd_id"]}[$cf_id]));

							/* do we need ALL_DATA_SOURCES_DUPS? */
							if (isset($magic_item["ALL_DATA_SOURCES_DUPS"])) {
								$magic_item["ALL_DATA_SOURCES_DUPS"] .= ($count_all_ds_dups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
							}

							/* do we need COUNT_ALL_DS_DUPS? */
							if (isset($magic_item["COUNT_ALL_DS_DUPS"])) {
								$magic_item["COUNT_ALL_DS_DUPS"] .= ($count_all_ds_dups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
							}

							$count_all_ds_dups++;

							/* check if this item also qualifies for NODUPS  */
							if(!isset($already_seen[$def_name])) {
								if (isset($magic_item["ALL_DATA_SOURCES_NODUPS"])) {
									$magic_item["ALL_DATA_SOURCES_NODUPS"] .= ($count_all_ds_nodups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}
								if (isset($magic_item["COUNT_ALL_DS_NODUPS"])) {
									$magic_item["COUNT_ALL_DS_NODUPS"] .= ($count_all_ds_nodups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}
								$count_all_ds_nodups++;
								$already_seen[$def_name]=TRUE;
							}

							/* check for SIMILAR data sources */
							if ($graph_item["data_source_name"] == $graph_items[$t]["data_source_name"]) {

								/* do we need SIMILAR_DATA_SOURCES_DUPS? */
								if (isset($magic_item["SIMILAR_DATA_SOURCES_DUPS"]) && ($graph_item["data_source_name"] == $graph_items[$t]["data_source_name"])) {
									$magic_item["SIMILAR_DATA_SOURCES_DUPS"] .= ($count_similar_ds_dups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}

								/* do we need COUNT_SIMILAR_DS_DUPS? */
								if (isset($magic_item["COUNT_SIMILAR_DS_DUPS"]) && ($graph_item["data_source_name"] == $graph_items[$t]["data_source_name"])) {
									$magic_item["COUNT_SIMILAR_DS_DUPS"] .= ($count_similar_ds_dups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}

								$count_similar_ds_dups++;

								/* check if this item also qualifies for NODUPS  */
								if(!isset($sources_seen{$graph_items[$t]["data_template_rrd_id"]})) {
									if (isset($magic_item["SIMILAR_DATA_SOURCES_NODUPS"])) {
										$magic_item["SIMILAR_DATA_SOURCES_NODUPS"] .= ($count_similar_ds_nodups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}
									if (isset($magic_item["COUNT_SIMILAR_DS_NODUPS"]) && ($graph_item["data_source_name"] == $graph_items[$t]["data_source_name"])) {
										$magic_item["COUNT_SIMILAR_DS_NODUPS"] .= ($count_similar_ds_nodups == 0 ? "" : ",") . "TIME," . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}
									$count_similar_ds_nodups++;
									$sources_seen{$graph_items[$t]["data_template_rrd_id"]} = TRUE;
								}
							} # SIMILAR data sources
						} # matching consolidation function?
					} # only work on graph items, omit GRPINTs, COMMENTs and stuff
				} #  loop over all graph items

				/* if there is only one item to total, don't even bother with the summation.
				 * Otherwise cdef=a,b,c,+,+ is fine. */
				if ($count_all_ds_dups > 1 && isset($magic_item["ALL_DATA_SOURCES_DUPS"])) {
					$magic_item["ALL_DATA_SOURCES_DUPS"] .= str_repeat(",+", ($count_all_ds_dups - 2)) . ",+";
				}
				if ($count_all_ds_nodups > 1 && isset($magic_item["ALL_DATA_SOURCES_NODUPS"])) {
					$magic_item["ALL_DATA_SOURCES_NODUPS"] .= str_repeat(",+", ($count_all_ds_nodups - 2)) . ",+";
				}
				if ($count_similar_ds_dups > 1 && isset($magic_item["SIMILAR_DATA_SOURCES_DUPS"])) {
					$magic_item["SIMILAR_DATA_SOURCES_DUPS"] .= str_repeat(",+", ($count_similar_ds_dups - 2)) . ",+";
				}
				if ($count_similar_ds_nodups > 1 && isset($magic_item["SIMILAR_DATA_SOURCES_NODUPS"])) {
					$magic_item["SIMILAR_DATA_SOURCES_NODUPS"] .= str_repeat(",+", ($count_similar_ds_nodups - 2)) . ",+";
				}
				if ($count_all_ds_dups > 1 && isset($magic_item["COUNT_ALL_DS_DUPS"])) {
					$magic_item["COUNT_ALL_DS_DUPS"] .= str_repeat(",+", ($count_all_ds_dups - 2)) . ",+";
				}
				if ($count_all_ds_nodups > 1 && isset($magic_item["COUNT_ALL_DS_NODUPS"])) {
					$magic_item["COUNT_ALL_DS_NODUPS"] .= str_repeat(",+", ($count_all_ds_nodups - 2)) . ",+";
				}
				if ($count_similar_ds_dups > 1 && isset($magic_item["COUNT_SIMILAR_DS_DUPS"])) {
					$magic_item["COUNT_SIMILAR_DS_DUPS"] .= str_repeat(",+", ($count_similar_ds_dups - 2)) . ",+";
				}
				if ($count_similar_ds_nodups > 1 && isset($magic_item["COUNT_SIMILAR_DS_NODUPS"])) {
					$magic_item["COUNT_SIMILAR_DS_NODUPS"] .= str_repeat(",+", ($count_similar_ds_nodups - 2)) . ",+";
				}
			}

			$cdef_string = str_replace("CURRENT_DATA_SOURCE", generate_graph_def_name(strval((isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id]) ? $cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id] : "0"))), $cdef_string);

			/* ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
			if (isset($magic_item["ALL_DATA_SOURCES_DUPS"])) {
				$cdef_string = str_replace("ALL_DATA_SOURCES_DUPS", $magic_item["ALL_DATA_SOURCES_DUPS"], $cdef_string);
			}
			if (isset($magic_item["ALL_DATA_SOURCES_NODUPS"])) {
				$cdef_string = str_replace("ALL_DATA_SOURCES_NODUPS", $magic_item["ALL_DATA_SOURCES_NODUPS"], $cdef_string);
			}
			if (isset($magic_item["SIMILAR_DATA_SOURCES_DUPS"])) {
				$cdef_string = str_replace("SIMILAR_DATA_SOURCES_DUPS", $magic_item["SIMILAR_DATA_SOURCES_DUPS"], $cdef_string);
			}
			if (isset($magic_item["SIMILAR_DATA_SOURCES_NODUPS"])) {
				$cdef_string = str_replace("SIMILAR_DATA_SOURCES_NODUPS", $magic_item["SIMILAR_DATA_SOURCES_NODUPS"], $cdef_string);
			}

			/* COUNT_ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
			if (isset($magic_item["COUNT_ALL_DS_DUPS"])) {
				$cdef_string = str_replace("COUNT_ALL_DS_DUPS", $magic_item["COUNT_ALL_DS_DUPS"], $cdef_string);
			}
			if (isset($magic_item["COUNT_ALL_DS_NODUPS"])) {
				$cdef_string = str_replace("COUNT_ALL_DS_NODUPS", $magic_item["COUNT_ALL_DS_NODUPS"], $cdef_string);
			}
			if (isset($magic_item["COUNT_SIMILAR_DS_DUPS"])) {
				$cdef_string = str_replace("COUNT_SIMILAR_DS_DUPS", $magic_item["COUNT_SIMILAR_DS_DUPS"], $cdef_string);
			}
			if (isset($magic_item["COUNT_SIMILAR_DS_NODUPS"])) {
				$cdef_string = str_replace("COUNT_SIMILAR_DS_NODUPS", $magic_item["COUNT_SIMILAR_DS_NODUPS"], $cdef_string);
			}

			/* data source item variables */
			$cdef_string = str_replace("CURRENT_DS_MINIMUM_VALUE", (empty($graph_item["rrd_minimum"]) ? "0" : $graph_item["rrd_minimum"]), $cdef_string);
			$cdef_string = str_replace("CURRENT_DS_MAXIMUM_VALUE", (empty($graph_item["rrd_maximum"]) ? "0" : $graph_item["rrd_maximum"]), $cdef_string);
			$cdef_string = str_replace("CURRENT_GRAPH_MINIMUM_VALUE", (empty($graph["lower_limit"]) ? "0" : $graph["lower_limit"]), $cdef_string);
			$cdef_string = str_replace("CURRENT_GRAPH_MAXIMUM_VALUE", (empty($graph["upper_limit"]) ? "0" : $graph["upper_limit"]), $cdef_string);

			/* replace query variables in cdefs */
			$cdef_string = rrd_substitute_host_query_data($cdef_string, $graph, $graph_item);

			/* make the initial "virtual" cdef name: 'cdef' + [a,b,c,d...] */
			$cdef_graph_defs .= "CDEF:cdef" . generate_graph_def_name(strval($i)) . "=";
			$cdef_graph_defs .= $cdef_string;
			$cdef_graph_defs .= " \\\n";

			/* the CDEF cache is so we do not create duplicate CDEF's on a graph */
			$cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]}[$cf_id] = "$i";
		}

		/* add the cdef string to the end of the def string */
		$graph_defs .= $cdef_graph_defs;

		/* note the current item_id for easy access */
		$graph_item_id = $graph_item["graph_templates_item_id"];

		/* if we are not displaying a legend there is no point in us even processing the auto padding,
		text format stuff. */
		if ((!isset($graph_data_array["graph_nolegend"])) && ($graph["auto_padding"] == "on")) {
			/* only applies to AREA, STACK and LINEs */
			if (ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_item["graph_type_id"]})) {
				$text_format_length = strlen($graph_variables["text_format"][$graph_item_id]);

				/* we are basing how much to pad on area and stack text format,
				not gprint. but of course the padding has to be displayed in gprint,
				how fun! */
				$pad_number   = ($greatest_text_format - $text_format_length);
				$text_padding = str_pad("", $pad_number);

			/* two GPRINT's in a row screws up the padding, lets not do that */
			} else if (($graph_item_types{$graph_item["graph_type_id"]} == "GPRINT") && ($last_graph_type == "GPRINT")) {
				$text_padding = "";
			}

			$last_graph_type = $graph_item_types{$graph_item["graph_type_id"]};
		}

		/* we put this in a variable so it can be manipulated before mainly used
		if we want to skip it, like below */
		$current_graph_item_type = $graph_item_types{$graph_item["graph_type_id"]};

		/* IF this graph item has a data source... get a DEF name for it, or the cdef if that applies
		to this graph item */
		if ($graph_item["cdef_id"] == "0") {
			if (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id])) {
				$data_source_name = generate_graph_def_name(strval($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id]));
			}else{
				$data_source_name = "";
			}
		}else{
			$data_source_name = "cdef" . generate_graph_def_name(strval($cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]}[$cf_id]));
		}

		/* to make things easier... if there is no text format set; set blank text */
		if (!isset($graph_variables["text_format"][$graph_item_id])) {
			$graph_variables["text_format"][$graph_item_id] = "";
		}

		if (!isset($hardreturn[$graph_item_id])) {
			$hardreturn[$graph_item_id] = "";
		}

		/* +++++++++++++++++++++++ GRAPH ITEMS +++++++++++++++++++++++ */

		/* most of the calculations have been done above. now we have for print everything out
		in an RRDTool-friendly fashion */
		$need_rrd_nl = TRUE;

		if ($graph_item_types{$graph_item["graph_type_id"]} == "COMMENT") {
			$comment_string = $graph_item_types{$graph_item["graph_type_id"]} . ":\"" . str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]) . $hardreturn[$graph_item_id] . "\" ";
			if (trim($comment_string) == 'COMMENT:"\n"') {
				$txt_graph_items .= 'COMMENT:" \n"'; # rrdtool will skip a COMMENT that holds a NL only; so add a blank to make NL work
			} else if (trim($comment_string) != "COMMENT:\"\"") {
				$txt_graph_items .= rrd_substitute_host_query_data($comment_string, $graph, $graph_item);
			}
		}elseif (($graph_item_types{$graph_item["graph_type_id"]} == "GPRINT") && (!isset($graph_data_array["graph_nolegend"]))) {
			$graph_variables["text_format"][$graph_item_id] = str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . $data_source_name . ":" . $consolidation_functions{$graph_item["consolidation_function_id"]} . ":\"$text_padding" . $graph_variables["text_format"][$graph_item_id] . $graph_item["gprint_text"] . $hardreturn[$graph_item_id] . "\" ";
		}elseif (ereg("^(AREA|LINE[123]|STACK|HRULE|VRULE)$", $graph_item_types{$graph_item["graph_type_id"]})) {

			/* initialize any color syntax for graph item */
			if (empty($graph_item["hex"])) {
				$graph_item_color_code = "";
			}else{
				$graph_item_color_code = "#" . $graph_item["hex"];
				$graph_item_color_code .= $graph_item["alpha"];
			}

			if (ereg("^(AREA|LINE[123])$", $graph_item_types{$graph_item["graph_type_id"]})) {
				$graph_item_stack_type = $graph_item_types{$graph_item["graph_type_id"]};
				$graph_variables["text_format"][$graph_item_id] = str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]); /* escape colons */
				$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . $data_source_name . $graph_item_color_code . ":" . "\"" . $graph_variables["text_format"][$graph_item_id] . $hardreturn[$graph_item_id] . "\" ";
			}elseif ($graph_item_types{$graph_item["graph_type_id"]} == "STACK") {
				$graph_variables["text_format"][$graph_item_id] = str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]); /* escape colons */
				$txt_graph_items .= $graph_item_stack_type . ":" . $data_source_name . $graph_item_color_code . ":" . "\"" . $graph_variables["text_format"][$graph_item_id] . $hardreturn[$graph_item_id] . "\":STACK";
			}elseif ($graph_item_types{$graph_item["graph_type_id"]} == "HRULE") {
				$graph_variables["text_format"][$graph_item_id] = str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]); /* escape colons */
				$graph_variables["value"][$graph_item_id] = str_replace(":", "\:", $graph_variables["value"][$graph_item_id]); /* escape colons */
				/* perform variable substitution; if this does not return a number, rrdtool will FAIL! */
				$substitute = rrd_substitute_host_query_data($graph_variables["value"][$graph_item_id], $graph, $graph_item);
				if (is_numeric($substitute)) {
					$graph_variables["value"][$graph_item_id] = trim($substitute);
				}
				$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . $graph_variables["value"][$graph_item_id] . $graph_item_color_code . ":\"" . $graph_variables["text_format"][$graph_item_id] . $hardreturn[$graph_item_id] . "\" ";
			}elseif ($graph_item_types{$graph_item["graph_type_id"]} == "VRULE") {
				$graph_variables["text_format"][$graph_item_id] = str_replace(":", "\:", $graph_variables["text_format"][$graph_item_id]); /* escape colons */

				if (substr_count($graph_item["value"], ":")) {
					$value_array = explode(":", $graph_item["value"]);

					if ($value_array[0] < 0) {
						$value = date("U") - (-3600 * $value_array[0]) - 60 * $value_array[1];
					}else{
						$value = date("U", mktime($value_array[0],$value_array[1],0));
					}
				}else if (is_numeric($graph_item["value"])) {
					$value = $graph_item["value"];
				}

				$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . $value . $graph_item_color_code . ":\"" . $graph_variables["text_format"][$graph_item_id] . $hardreturn[$graph_item_id] . "\" ";
			}
		}else{
			$need_rrd_nl = FALSE;
		}

		$i++;

		if (($i < sizeof($graph_items)) && ($need_rrd_nl)) {
			$txt_graph_items .= RRD_NL;
		}
	}
	}

	/* either print out the source or pass the source onto rrdtool to get us a nice PNG */
	if (isset($graph_data_array["print_source"])) {
		print "<PRE>" . read_config_option("path_rrdtool") . " graph $graph_opts$graph_defs$txt_graph_items</PRE>";
	}else{
		if (isset($graph_data_array["export"])) {
			@rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, RRDTOOL_OUTPUT_NULL);
			return 0;
		}else{
			if (isset($graph_data_array["output_flag"])) {
				$output_flag = $graph_data_array["output_flag"];
			}else{
				$output_flag = RRDTOOL_OUTPUT_GRAPH_DATA;
			}

			return @rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, $output_flag);
		}
	}
}

