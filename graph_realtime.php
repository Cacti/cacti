<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

$guest_account = true;

include('./include/auth.php');
include_once('./lib/rrd.php');

$config['force_storage_location_local'] = true;

/* ================= input validation ================= */
get_filter_request_var('graph_start');
get_filter_request_var('graph_end');
get_filter_request_var('graph_height');
get_filter_request_var('graph_width');
get_filter_request_var('local_graph_id');
get_filter_request_var('size');
get_filter_request_var('ds_step');
get_filter_request_var('count');
get_filter_request_var('top');
get_filter_request_var('left');
/* ==================================================== */

set_default_action();

switch (get_request_var('action')) {
case 'init':
case 'timespan':
case 'interval':
case 'countdown':
	ob_start();

	$guest_account = true;

	switch (get_request_var('action')) {
	case 'init':
		load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_config_option('realtime_interval'));
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
		load_current_session_value('size',           'sess_realtime_size',        100);
		load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    'false');

		break;
	case 'timespan':
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_config_option('realtime_gwindow'));

		break;
	case 'interval':
		load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_config_option('realtime_interval'));
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
		load_current_session_value('size',           'sess_realtime_size',        100);
		load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    'false');

		break;
	case 'countdown':
		load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_config_option('realtime_interval'));
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
		load_current_session_value('size',           'sess_realtime_size',        100);
		load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    'false');

		break;
	default:
		load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_config_option('realtime_interval'));
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
		load_current_session_value('size',           'sess_realtime_size',        100);
		load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    'false');

		break;
	}

	$graph_data_array = array();

	/* ds */
	$graph_data_array['ds_step'] = read_config_option('realtime_interval');
	if (!isempty_request_var('ds_step')) {
		$graph_data_array['ds_step']      = get_request_var('ds_step');
		$_SESSION['sess_realtime_dsstep'] = get_request_var('ds_step');
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
	if (get_request_var('graph_nolegend') == 'true') {
		$graph_data_array['graph_nolegend'] = 'true';
	}

	if (isset_request_var('size') && get_request_var('size') > 0) {
		$_SESSION['sess_realtime_size'] = get_request_var('size');
		$size = get_request_var('size');
	} elseif (isset($_SESSION['sess_realtime_size']) && $_SESSION['sess_realtime_size'] != '') {
		$size = $_SESSION['sess_realtime_size'];
	} else {
		$size = 100;
	}

	if (isset_request_var('local_graph_id')) {
		$graph_data = db_fetch_row_prepared('SELECT width, height
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			array(get_request_var('local_graph_id')));

		if (cacti_sizeof($graph_data)) {
			$graph_data_array['graph_height'] = $graph_data['height'];
			$graph_data_array['graph_width']  = $graph_data['width'];
		}
	}

	if (isset_request_var('size') && get_request_var('size') < 100) {
		$graph_data_array['graph_height'] = $graph_data_array['graph_height'] * $size / 100;
		$graph_data_array['graph_width']  = $graph_data_array['graph_width']  * $size / 100;
	}

	/* override: graph start */
	if (!isempty_request_var('graph_start')) {
		$graph_data_array['graph_start']  = get_request_var('graph_start');
		if ($graph_data_array['graph_start'] < 0) {
			$graph_data_array['graph_start'] = time() + $graph_data_array['graph_start'];
		}
		$_SESSION['sess_realtime_window'] = abs(get_request_var('graph_start'));
	}

	/* override: graph end */
	if (!isempty_request_var('graph_end')) {
		$graph_data_array['graph_end'] = get_request_var('graph_end');
	} else {
		$graph_data_array['graph_end'] = time();
	}

	/* print RRDtool graph source? */
	if (!isempty_request_var('show_source')) {
		$graph_data_array['print_source'] = get_request_var('show_source');
	}

	/* check ds */
	if ($graph_data_array['ds_step'] < 1) {
		$graph_data_array['ds_step'] = read_config_option('realtime_interval');
	}

	/* call poller */
	$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . session_id() . '_lgi_' . get_request_var('local_graph_id') . '.png';
	$command   = read_config_option('path_php_binary');
	$args      = sprintf('poller_realtime.php --graph=%s --interval=%d --poller_id=' . session_id(), get_request_var('local_graph_id'), $graph_data_array['ds_step']);

	shell_exec("$command $args");

	/* construct the image name  */
	$graph_data_array['export_realtime'] = $graph_rrd;
	$graph_data_array['output_flag']     = RRDTOOL_OUTPUT_GRAPH_DATA;

	rrdtool_function_graph(get_request_var('local_graph_id'), '', $graph_data_array);

	if (file_exists($graph_rrd)) {
		$data = base64_encode(file_get_contents($graph_rrd));
	}

	/* send text information back to browser as well as image information */
	$return_array = array(
		'local_graph_id' => get_request_var('local_graph_id'),
		'top'            => get_request_var('top'),
		'left'           => get_request_var('left'),
		'ds_step'        => (isset($_SESSION['sess_realtime_ds_step']) ? $_SESSION['sess_realtime_ds_step']:$graph_data_array['ds_step']),
		'graph_start'    => (isset($_SESSION['sess_realtime_graph_start']) ? $_SESSION['sess_realtime_graph_start']:$graph_data_array['graph_start']),
		'size'           => (isset($_SESSION['sess_realtime_size']) ? $_SESSION['sess_realtime_size']:100),
		'thumbnails'     => (isset($_SESSION['sess_realtime_nolegend']) ? $_SESSION['sess_realtime_nolegend']:'false'),
		'data'           => (isset($data) ? $data:'')
	);

	print json_encode($return_array);

	exit;
	break;
case 'view':
	$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . session_id() . '_lgi_' . get_request_var('local_graph_id') . '.png';

	if (file_exists($graph_rrd)) {
		print base64_encode(file_get_contents($graph_rrd));
	}

	exit;
	break;
}

/* ================= input validation ================= */
get_filter_request_var('ds_step');
get_filter_request_var('local_graph_id');
get_filter_request_var('graph_start');
get_filter_request_var('size');
/* ==================================================== */

$init = '';

if (!isset($_SESSION['sess_realtime_ds_step'])) {
	load_current_session_value('ds_step', 'sess_realtime_ds_step', read_config_option('realtime_interval'));
} else {
	set_request_var('ds_step', $_SESSION['sess_realtime_ds_step']);
}

if (!isset($_SESSION['sess_realtime_graph_start'])) {
	load_current_session_value('graph_start', 'sess_realtime_graph_start', read_config_option('realtime_gwindow'));
} else {
	set_request_var('graph_start', $_SESSION['sess_realtime_graph_start']);
}

if (read_config_option('realtime_enabled') == '') {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>" . __('Real-time has been disabled by your administrator.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
} elseif (!is_dir(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>" . __('The Image Cache Directory does not exist.  Please first create it and set permissions and then attempt to open another Real-time graph.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
} elseif (!is_writable(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>" . __('The Image Cache Directory is not writable.  Please set permissions and then attempt to open another Real-time graph.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
}

$selectedTheme = get_selected_theme();

$sizes = array(
	'100' => '100%',
	'90'  => '90%',
	'80'  => '80%',
	'70'  => '70%',
	'60'  => '60%',
	'50'  => '50%',
	'40'  => '40%'
);

?>
<html>
<head>
	<?php html_common_header(__('Cacti Real-time Graphing'));?>
    <?php include($config['base_path'] . '/include/global_session.php'); ?>
</head>
<body>
<form method='post' action='graph_popup_rt.php' id='gform'>
	<div id='rtfilter' class='cactiTable'>
		<table class='filterTable center'>
			<tr>
				<td>
					<?php print __('Window');?>
				</td>
				<td>
					<select id='graph_start' onChange='imageOptionsChanged("timespan")'>
					<?php
					foreach ($realtime_window as $interval => $text) {
						printf('<option value="%d"%s>%s</option>',
							$interval, $interval == abs(get_request_var('graph_start')) ? ' selected="selected"' : '', $text
						);
					}
					?>
					</select>
				</td>
				<td>
					<?php print __('Refresh');?>
				</td>
				<td>
					<select id='ds_step' onChange='imageOptionsChanged("interval")'>
					<?php
					foreach ($realtime_refresh as $interval => $text) {
						printf('<option value="%d"%s>%s</option>',
							$interval, $interval == get_request_var('ds_step') ? ' selected="selected"' : '', $text
						);
					}
					?>
					</select>
				</td>
			</td>
		</table>
		<table class='filterTable center'>
			<tr>
				<td>
					<?php print __('Size');?>
				</td>
				<td>
					<select id='size' onChange='imageOptionsChanged("interval")'>
					<?php
					foreach ($sizes as $key => $value) {
						printf('<option value="%d"%s>%s</option>',
							$key, $key == get_request_var('size') ? ' selected="selected"' : '', $value
						);
					}
					?>
					</select>
				</td>
				<td>
					<label for='thumbnails'><?php print __('Thumbnails');?></label>
				</td>
				<td>
					<input type='checkbox' id='thumbnails' onChange='imageOptionsChanged("interval")' <?php print get_request_var('graph_nolegend') == 'true' ? 'checked':'';?>>
				</td>
			</tr>
			<tr>
				<td class='center' colspan='6'>
					<span id='countdown'><?php print __('%d seconds left.',  get_request_var('ds_step')); ?></span>
				</td>
			</tr>
		</table>
	</div>
	<div id='image' class='center' style='padding:2px;'>
		<i id='imaging' style='font-size:40px;' class='fa fa-spin fa-circle-notch'></i>
	</div>
	<input type='hidden' id='url_path' name='url_path' value='<?php echo $config['url_path'];?>'/>
	<input type='hidden' id='local_graph_id' name='local_graph_id' value='<?php echo get_request_var('local_graph_id'); ?>'/>
	<script type='text/javascript'>

	var url;
	var ds_step = 0;
	var sizeset = false;
	var count   = 0;
	var realtimePopout = true;
	var refreshIsLogout= false;
	var refreshPage=urlPath+'/graph_realtime.php?action=countdown';
	var refreshMSeconds=999999999;
	var myCountdown = {};
	var secondsLeft = '<?php print __(' seconds left.');?>';

	function countdown_update() {
		ds_step--;

		if (ds_step < 0) {
			ds_step = $('#ds_step').val();
			imageOptionsChanged('countdown');
			sizeset = false;
		}

		setRealtimeWindowSize();

		$('#countdown').empty().html(ds_step + secondsLeft);

		count++;

		destroy(myCountdown);

		myCountdown = setTimeout('countdown_update()', 1000);
	}

	$(function() {
		imageOptionsChanged('init');
		myCountdown = setTimeout('countdown_update()', 1000);
	});

	</script>
</form>
</body>
</html>

