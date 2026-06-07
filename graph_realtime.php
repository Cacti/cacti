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

$guest_account = true;

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

$config['force_storage_location_local'] = true;

// ================= input validation =================
gfrv('graph_start');
gfrv('graph_end');
gfrv('graph_height');
gfrv('graph_width');
gfrv('graph_nolegend', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '(true|false)']]);
gfrv('local_graph_id');
gfrv('size');
gfrv('ds_step');
gfrv('count');
gfrv('top');
gfrv('left');
// ====================================================

global $realtime_refresh, $realtime_window;

set_default_action();

switch (grv('action')) {
	case 'init':
	case 'timespan':
	case 'interval':
	case 'countdown':
		ob_start();

		$guest_account = true;

		switch (grv('action')) {
			case 'init':
				load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_user_setting('realtime_interval', 10));
				load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
				load_current_session_value('size',           'sess_realtime_size',        read_user_setting('realtime_size', 100));
				load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    read_user_setting('realtime_nolegend', 'false'));

				break;
			case 'timespan':
				load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));

				break;
			case 'interval':
				load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_user_setting('realtime_interval', 10));
				load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
				load_current_session_value('size',           'sess_realtime_size',        read_user_setting('realtime_size', 100));
				load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    read_user_setting('realtime_nolegend', 'false'));

				break;
			case 'countdown':
				load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_user_setting('realtime_interval', 10));
				load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
				load_current_session_value('size',           'sess_realtime_size',        read_user_setting('realtime_size', 100));
				load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    read_user_setting('realtime_nolegend', 'false'));

				break;
			default:
				load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_user_setting('realtime_interval', 10));
				load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
				load_current_session_value('size',           'sess_realtime_size',        read_user_setting('realtime_size', 100));
				load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    read_user_setting('realtime_nolegend', 'false'));

				break;
		}

		$graph_data_array = [];

		// ds
		$graph_data_array['ds_step'] = read_user_setting('realtime_interval', 10);

		if (!ierv('ds_step')) {
			$graph_data_array['ds_step']      = grv('ds_step');
			$_SESSION['sess_realtime_dsstep'] = grv('ds_step');
		}

		// override: graph height (in pixels)
		if (!ierv('graph_height') && grv('graph_height') < 3000) {
			$graph_data_array['graph_height'] = grv('graph_height');
		} else {
			$graph_data_array['graph_height'] = 125;
		}

		// override: graph width (in pixels)
		if (!ierv('graph_width') && grv('graph_width') < 3000) {
			$graph_data_array['graph_width'] = grv('graph_width');
		} else {
			$graph_data_array['graph_width'] = 425;
		}

		// override: skip drawing the legend?
		if (grv('graph_nolegend') == 'true') {
			$graph_data_array['graph_nolegend'] = 'true';
		}

		if (isrv('size') && grv('size') > 0) {
			$_SESSION['sess_realtime_size'] = grv('size');
			$size                           = grv('size');
		} elseif (isset($_SESSION['sess_realtime_size']) && $_SESSION['sess_realtime_size'] != '') {
			$size = $_SESSION['sess_realtime_size'];
		} else {
			$size = 100;
		}

		if (isrv('local_graph_id')) {
			$graph_data = db_fetch_row_prepared('SELECT width, height
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
				[grv('local_graph_id')]);

			if (cacti_sizeof($graph_data)) {
				$graph_data_array['graph_height'] = $graph_data['height'];
				$graph_data_array['graph_width']  = $graph_data['width'];
			}
		}

		if (isrv('size') && grv('size') < 100) {
			$graph_data_array['graph_height'] = $graph_data_array['graph_height'] * $size / 100;
			$graph_data_array['graph_width']  = $graph_data_array['graph_width'] * $size / 100;
		}

		// override: graph start
		if (!ierv('graph_start')) {
			$graph_data_array['graph_start']  = grv('graph_start');

			if ($graph_data_array['graph_start'] < 0) {
				$graph_data_array['graph_start'] = time() + $graph_data_array['graph_start'];
			}
			$_SESSION['sess_realtime_window'] = abs(grv('graph_start'));
		}

		// override: graph end
		if (!ierv('graph_end')) {
			$graph_data_array['graph_end'] = grv('graph_end');
		} else {
			$graph_data_array['graph_end'] = time();
		}

		// print RRDtool graph source?
		if (!ierv('show_source')) {
			$graph_data_array['print_source'] = grv('show_source');
		}

		// check ds
		if ($graph_data_array['ds_step'] < 1) {
			$graph_data_array['ds_step'] = read_user_setting('realtime_interval', 10);
		}

		$gtype = 'png';

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
				default:
					$gtype = 'png';

					break;
			}
		} else {
			switch(cacti_strtolower(gnrv('image_format'))) {
				case 'png':
					$graph_data_array['image_format'] = 'png';

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

		// call poller
		$local_graph_id = (int) gfrv('local_graph_id');
		$poller_id      = hash('sha256', session_id());
		$graph_rrd      = read_config_option('realtime_cache_path') . '/user_' . $poller_id . '_lgi_' . $local_graph_id . '.png';
		$php_binary     = cacti_escapeshellcmd(read_config_option('path_php_binary'));
		$script_path    = cacti_escapeshellarg(CACTI_PATH_BASE . '/poller_realtime.php');
		$args           = '--graph=' . $local_graph_id . ' --interval=' . ((int) $graph_data_array['ds_step']) . ' --poller_id=' . cacti_escapeshellarg($poller_id);

		shell_exec($php_binary . ' -q ' . $script_path . ' ' . $args);

		// construct the image name
		$graph_data_array['export_realtime'] = $graph_rrd;
		$graph_data_array['output_flag']     = RRDTOOL_OUTPUT_GRAPH_DATA;
		$null_param                          = [];

		$output = rrdtool_function_graph(grv('local_graph_id'), '', $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]);

		$error          = '';
		$graph_contents = false;

		if (file_exists($graph_rrd)) {
			$cached_graph_contents = file_get_contents($graph_rrd);

			if ($cached_graph_contents !== false && $cached_graph_contents !== '') {
				$graph_contents = $cached_graph_contents;

				if (preg_match('/^ERROR/', $graph_contents)) {
					$error  = $graph_contents;
					$output = '';
				}
			}
		}

		if ($graph_contents === false && $output !== false && $output != '') {
			if (preg_match('/^(ERROR|GRAPH ACCESS DENIED)/', $output)) {
				$error = $output;
			} else {
				$graph_contents = $output;
			}
		}

		if (empty($output) && empty($error)) {
			$graph_data_array['get_error'] = true;
			$null_param                    = [];
			rrdtool_function_graph(grv('local_graph_id'), '', $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]);

			$error = ob_get_contents();

			if (read_config_option('stats_poller') == '') {
				$error = __('The Cacti Poller has not run yet.');
			}
		}

		if (!empty($error)) {
			$graph_data_array['get_error'] = true;

			if (isset($graph_data_array['graph_width']) && isset($graph_data_array['graph_height'])) {
				$graph_contents = rrdtool_create_error_image($error, $graph_data_array['graph_width'], $graph_data_array['graph_height']);
			} else {
				$graph_contents = rrdtool_create_error_image($error);
			}

			ob_end_clean();

			if ($graph_contents === false) {
				$graph_contents = file_get_contents(__DIR__ . '/images/cacti_error_image.png');
			}
		}

		if ($graph_contents !== false) {
			$data = base64_encode($graph_contents);
		} else {
			$data = '';
		}

		// save user preferences
		set_user_setting('realtime_interval', grv('ds_step'));
		set_user_setting('realtime_gwindow', abs(grv('graph_start')));
		set_user_setting('realtime_size', grv('size'));
		set_user_setting('realtime_nolegend', grv('graph_nolegend'));

		$_SESSION['sess_realtime_ds_step']     = grv('ds_step');
		$_SESSION['sess_realtime_graph_start'] = grv('graph_start');
		$_SESSION['sess_realtime_size']        = grv('size');
		$_SESSION['sess_realtime_nolegend']    = grv('graph_nolegend');

		// send text information back to browser as well as image information
		$return_array = [
			'local_graph_id' => grv('local_graph_id'),
			'top'            => grv('top'),
			'left'           => grv('left'),
			'ds_step'        => htmle(isset($_SESSION['sess_realtime_ds_step']) ? $_SESSION['sess_realtime_ds_step'] : $graph_data_array['ds_step']),
			'graph_start'    => htmle(isset($_SESSION['sess_realtime_graph_start']) ? $_SESSION['sess_realtime_graph_start'] : $graph_data_array['graph_start']),
			'size'           => htmle(isset($_SESSION['sess_realtime_size']) ? $_SESSION['sess_realtime_size'] : read_user_setting('realtime_size', 100)),
			'thumbnails'     => htmle(isset($_SESSION['sess_realtime_nolegend']) ? $_SESSION['sess_realtime_nolegend'] : 'false'),
			'data'           => $data,
			'image_format'   => $graph_data_array['image_format']
		];

		print json_encode($return_array);

		exit;
	case 'view':
		$graph_rrd = read_config_option('realtime_cache_path') . '/user_' . hash('sha256',session_id()) . '_lgi_' . grv('local_graph_id') . '.png';

		if (file_exists($graph_rrd)) {
			print base64_encode(file_get_contents($graph_rrd));
		}

		exit;
	default:
		load_current_session_value('ds_step',        'sess_realtime_ds_step',     read_user_setting('realtime_interval', 10));
		load_current_session_value('graph_start',    'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
		load_current_session_value('size',           'sess_realtime_size',        read_user_setting('realtime_size', 100));
		load_current_session_value('graph_nolegend', 'sess_realtime_nolegend',    read_user_setting('realtime_nolegend', 'false'));

		break;
}

// ================= input validation =================
gfrv('ds_step');
gfrv('local_graph_id');
gfrv('graph_start');
gfrv('size');
// ====================================================

$init = '';

if (!isset($_SESSION['sess_realtime_ds_step'])) {
	load_current_session_value('ds_step', 'sess_realtime_ds_step', read_user_setting('realtime_interval', 10));
} else {
	srv('ds_step', $_SESSION['sess_realtime_ds_step']);
}

if (!isset($_SESSION['sess_realtime_graph_start'])) {
	load_current_session_value('graph_start', 'sess_realtime_graph_start', read_user_setting('realtime_gwindow', 60));
} else {
	srv('graph_start', $_SESSION['sess_realtime_graph_start']);
}

// save user preferences
set_user_setting('realtime_interval', grv('ds_step'));
set_user_setting('realtime_gwindow', abs(grv('graph_start')));
set_user_setting('realtime_size', grv('size'));
set_user_setting('realtime_nolegend', grv('graph_nolegend'));

if (read_config_option('realtime_enabled') == '') {
	print "<html>\n";
	print "<body>\n";
	print '	<p><strong>' . __('Real-time has been disabled by your administrator.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";

	exit;
}

if (!is_dir(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print '	<p><strong>' . __('The Image Cache Directory does not exist.  Please first create it and set permissions and then attempt to open another Real-time graph.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";

	exit;
}

if (!is_writable(read_config_option('realtime_cache_path'))) {
	print "<html>\n";
	print "<body>\n";
	print '	<p><strong>' . __('The Image Cache Directory is not writable.  Please set permissions and then attempt to open another Real-time graph.') . "</strong></p>\n";
	print "</body>\n";
	print "</html>\n";

	exit;
}

$selectedTheme = get_selected_theme();

$sizes = [
	'100' => '100%',
	'90'  => '90%',
	'80'  => '80%',
	'70'  => '70%',
	'60'  => '60%',
	'50'  => '50%',
	'40'  => '40%'
];

?>
<html>
<head>
	<?php html_common_header(__('Cacti Real-time Graphing')); ?>
    <?php require(CACTI_PATH_INCLUDE . '/global_session.php'); ?>
</head>
<body style='font-size:12px;'>
	<form method='post' action='graph_realtime.php' id='gform'>
		<div id='rtfilter' class='cactiTable center'>
			<div class='filterTable even'>
				<select id='graph_start' onChange='imageOptionsChanged("timespan")'>
					<?php
					foreach ($realtime_window as $interval => $text) {
						printf('<option value="%d"%s>%s</option>',
							$interval, $interval == abs(grv('graph_start')) ? ' selected="selected"' : '', $text
						);
					}
?>
				</select>
				<select id='ds_step' onChange='imageOptionsChanged("interval")'>
					<?php
$min_refresh = read_config_option('realtime_interval');

foreach ($realtime_refresh as $interval => $text) {
	if ($interval >= $min_refresh) {
		printf('<option value="%d"%s>%s</option>',
			$interval, $interval == grv('ds_step') ? ' selected="selected"' : '', $text
		);
	}
}
?>
				</select>
				<select id='size' onChange='imageOptionsChanged("interval")'>
					<?php
foreach ($sizes as $key => $value) {
	printf('<option value="%d"%s>%s</option>', $key, $key == grv('size') ? ' selected="selected"' : '', $value);
}
?>
				</select>
				<input type='checkbox' id='thumbnails' onChange='imageOptionsChanged("interval")' <?php print grv('graph_nolegend') == 'true' ? 'checked' : ''; ?>>
				<label for='thumbnails'><?php print __('Thumbnails'); ?></label>
			</div>
		</div>
		<div class='cactiTable center'>
			<span id='countdown'><?php print __('%d seconds left.',  grv('ds_step')); ?></span>
		</div>
		<div id='image' class='center' style='padding:2px;'></div>
		<input type='hidden' id='url_path' name='url_path' value='<?php print CACTI_PATH_URL; ?>'/>
		<input type='hidden' id='local_graph_id' name='local_graph_id' value='<?php print grv('local_graph_id'); ?>'/>
		<script type='text/javascript'>

		var url;
		var ds_step = 0;
		var sizeset = false;
		var count   = 0;
		var realtimePopout = true;
		var refreshIsLogout= false;
		var refreshPage=urlPath+'/graph_realtime.php?action=countdown&size='+$('#size').val();
		var refreshMSeconds=999999999;
		var myCountdown = {};
		var secondsLeft = '<?php print __(' seconds left.'); ?>';

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

			myCountdown = setTimeout(function() {
				countdown_update();
			}, 1000);
		}

		$(function() {
			imageOptionsChanged('init');
			myCountdown = setTimeout(function() {
				countdown_update();
			}, 1000);
		});

		</script>
	</form>
</body>
</html>
