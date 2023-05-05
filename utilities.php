<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/api_data_source.php');
include_once('./lib/boost.php');
include_once('./lib/rrd.php');
include_once('./lib/clog_webapi.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'clear_poller_cache':
		/* obtain timeout settings */
		$max_execution = ini_get('max_execution_time');
		ini_set('max_execution_time', '0');
		repopulate_poller_cache();
		ini_set('max_execution_time', $max_execution);
		header('Location: utilities.php?action=view_poller_cache');exit;
		break;
	case 'rebuild_resource_cache':
		rebuild_resource_cache();
		header('Location: utilities.php?header=false');exit;
		break;
	case 'view_snmp_cache':
		top_header();
		utilities_view_snmp_cache();
		bottom_footer();
		break;
	case 'view_poller_cache':
		top_header();
		utilities_view_poller_cache();
		bottom_footer();
		break;
	case 'view_logfile':
		utilities_view_logfile();
		break;
	case 'clear_logfile':
		utilities_clear_logfile();
		utilities_view_logfile();
		break;
	case 'purge_logfile':
		clog_purge_logfile();
		utilities_view_logfile();
		break;
	case 'view_user_log':
		top_header();
		utilities_view_user_log();
		bottom_footer();
		break;
	case 'clear_user_log':
		utilities_clear_user_log();
		utilities_view_user_log();
		break;
	case 'view_tech':
		utilities_view_tech();
		break;
	case 'view_boost_status':
		top_header();
		boost_display_run_status();
		bottom_footer();
		break;
	case 'view_snmpagent_cache':
		top_header();
		snmpagent_utilities_run_cache();
		bottom_footer();
		break;
	case 'purge_data_source_statistics';
		purge_data_source_statistics();
		raise_message('purge_dss', __('Data Source Statistics Purged.'), MESSAGE_LEVEL_INFO);
		header('Location: utilities.php');
		break;
	case 'rebuild_snmpagent_cache';
		snmpagent_cache_rebuilt();
		header('Location: utilities.php?action=view_snmpagent_cache');exit;
		break;
	case 'view_snmpagent_events':
		top_header();
		snmpagent_utilities_run_eventlog();
		bottom_footer();
		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	default:
		if (!api_plugin_hook_function('utilities_action', get_request_var('action'))) {
			top_header();
			utilities();
			bottom_footer();
		}
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function rebuild_resource_cache() {
	db_execute('DELETE FROM settings WHERE name LIKE "md5dirsum%"');
	db_execute('TRUNCATE TABLE poller_resource_cache');

	raise_message('resource_cache_rebuild');

	cacti_log('NOTE: Poller Resource Cache scheduled for rebuild by user ' . get_username($_SESSION['sess_user_id']), false, 'WEBUI');
}

function utilities_view_tech() {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types, $local_db_cnn_id, $remote_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary'),
		'database'   => __('Database'),
		'dbsettings' => __('Database Settings'),
		'dbstatus'   => __('Database Status'),
		'phpinfo'    => __('PHP Info'),
		'changelog'  => __('ChangeLog'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'utilities.php?action=view_tech&header=false&tab=' . $current_tab;

	$refresh = array(
		'seconds' => 999999,
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	$header_label = __esc('Technical Support [%s]', $tabs[get_request_var('tab')]);

	top_header();

	if (cacti_sizeof($tabs)) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'utilities.php?action=view_tech' .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>";

			$i++;
		}

		api_plugin_hook('utilities_tab');

		print "</ul></nav></div>";
	}

	/* Display tech information */
	html_start_box($header_label, '100%', '', '3', 'center', '');

	if (get_request_var('tab') == 'summary') {
		/* Get poller stats */
		$poller_item = db_fetch_assoc('SELECT action, count(action) AS total
			FROM poller_item
			GROUP BY action');

		/* Get system stats */
		$host_count  = db_fetch_cell('SELECT COUNT(*) FROM host WHERE deleted = ""');
		$graph_count = db_fetch_cell('SELECT COUNT(*) FROM graph_local');
		$data_count  = db_fetch_assoc('SELECT i.type_id, COUNT(i.type_id) AS total
			FROM data_template_data AS d, data_input AS i
			WHERE d.data_input_id = i.id
			AND local_data_id <> 0
			GROUP BY i.type_id');

		/* Get RRDtool version */
		$rrdtool_version = __('Unknown');
		$rrdtool_release = __('Unknown');
		$storage_location = read_config_option('storage_location');

		$out_array = array();

		if ($storage_location == 0) {
			if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {
				exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);
			}
		}else {
			$rrdtool_pipe = rrd_init();
			$out_array[] = rrdtool_execute('info', false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'WEBLOG');
			rrd_close($rrdtool_pipe);
		}

		if (cacti_sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool ([0-9.]+)/', $out_array[0], $m)) {
				preg_match('/^([0-9]+\.[0-9]+\.[0.9]+)/', $m[1], $m2);
				$rrdtool_release = $m[1];
				$rrdtool_version = $rrdtool_release;
			}
		}

		/* Get SNMP cli version */
		if ((file_exists(read_config_option('path_snmpget'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_snmpget'))))) {
			$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) . ' -V 2>&1');
		} else {
			$snmp_version = "<span class='deviceDown'>" . __('NET-SNMP Not Installed or its paths are not set.  Please install if you wish to monitor SNMP enabled devices.') . "</span>";
		}

		/* Check RRDtool issues */
		$rrdtool_errors = array();
		if (cacti_version_compare($rrdtool_version, get_rrdtool_version(), '<')) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: Installed RRDtool version does not exceed configured version.<br>Please visit the %s and select the correct RRDtool Utility Version.', "<a href='" . html_escape('settings.php?tab=general') . "'>" . __('Configuration Settings') . '</a>') . "</span>";
		}

		$graph_gif_count = db_fetch_cell('SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2');
		if ($graph_gif_count > 0) {
			$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: RRDtool 1.2.x+ does not support the GIF images format, but %d" graph(s) and/or templates have GIF set as the image format.', $graph_gif_count) . '</span>';
		}

		/* Get spine version */
		$spine_version = 'Unknown';
		if ((file_exists(read_config_option('path_spine'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_spine'))))) {
			$out_array = array();
			exec(cacti_escapeshellcmd(read_config_option('path_spine')) . ' --version', $out_array);
			if (cacti_sizeof($out_array) > 0) {
				$spine_version = $out_array[0];
			}
		}

		html_section_header(__('General Information'), 2);
		form_alternate_row();
		print '<td>' . __('Date') . '</td>';
		print '<td>' . date('r') . '</td>';
		form_end_row();

		api_plugin_hook_function('custom_version_info');

		form_alternate_row();
		print '<td>' . __('Cacti Version') . '</td>';
		print '<td>' . CACTI_VERSION . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Cacti OS') . '</td>';
		print '<td>' . $config['cacti_server_os'] . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RSA Fingerprint') . '</td>';
		print '<td>' . read_config_option('rsa_fingerprint') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('NET-SNMP Version') . '</td>';
		print '<td>' . $snmp_version . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Configured') . '</td>';
		print '<td>' . get_rrdtool_version() . "+</td>";
		form_end_row();

		form_alternate_row();
		print '<td>' . __('RRDtool Version') . ' ' . __('Found') . '</td>';
		print '<td>' . $rrdtool_release . '</td>';
		form_end_row();

		if (!empty($rrdtool_errors)) {
			form_alternate_row();
			print "<td>&nbsp;</td>";
			$br = '';
			print '<td>';
			foreach ($rrdtool_errors as $rrdtool_error) {
				print $br . $rrdtool_error;
				$br = '<br/>';
			}
			print '</td>';
			form_end_row();
		}

		form_alternate_row();
		print '<td>' . __('Devices') . '</td>';
		print '<td>' . number_format_i18n($host_count) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Graphs') . '</td>';
		print '<td>' . number_format_i18n($graph_count) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Data Sources') . '</td>';
		print '<td>';
		$data_total = 0;
		if (cacti_sizeof($data_count)) {
			foreach ($data_count as $item) {
				print $input_types[$item['type_id']] . ': ' . number_format_i18n($item['total']) . '<br>';
				$data_total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($data_total));
		} else {
			print "<span class='deviceDown'>0</span>";
		}
		print '</td>';
		form_end_row();

		html_section_header(__('Poller Information'), 2);

		form_alternate_row();
		print '<td>' . __('Interval') . '</td>';
		print '<td>' . read_config_option('poller_interval') . '</td>';
		if (file_exists(read_config_option('path_spine')) && $poller_options[read_config_option('poller_type')] == 'spine') {
			$type = $spine_version;
		        if (!strpos($spine_version, CACTI_VERSION)) {
		    	    $type .= '<span class="textError"> (' . __('Different version of Cacti and Spine!') . ')</span>';
			}
		} else {
			$type = $poller_options[read_config_option('poller_type')];
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Type') . '</td>';
		print '<td>' . $type . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Items') . '</td>';
		print '<td>';
		$total = 0;
		if (cacti_sizeof($poller_item)) {
			foreach ($poller_item as $item) {
				print __('Action[%s]', $item['action']) . ': ' . number_format_i18n($item['total']) . '<br>';
				$total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($total));
		} else {
			print "<span class='deviceDown'>" . __('No items to poll') . "</span>";
		}
		print '</td>';
		form_end_row();

		$processes = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Procs: ') . '", processes) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		$threads = db_fetch_cell('SELECT
			GROUP_CONCAT(
				CONCAT("' . __('Name: ') . '", name, ", ' . __('Threads: ') . '", threads) SEPARATOR "<br>"
			) AS poller
			FROM poller
			WHERE disabled=""');

		form_alternate_row();
		print '<td>' . __('Concurrent Processes') . '</td>';
		print '<td>' . $processes . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max Threads') . '</td>';
		print '<td>' . $threads . '</td>';
		form_end_row();

		$script_servers = read_config_option('php_servers');

		form_alternate_row();
		print '<td>' . __('PHP Servers') . '</td>';
		print '<td>' . html_escape($script_servers) . '</td>';
		form_end_row();

		if ($config['poller_id'] == 1) {
			$max_connections       = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections = array();
		} elseif ($config['connection'] == 'online') {
			$max_connections        = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"', false, $local_db_cnn_id);
		} else {
			$max_connections        = array();
			$max_local_connections  = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "max_connections"');
		}

		if (cacti_sizeof($max_connections)) {
			$max_connections = $max_connections['Value'];
		} else {
			$max_connections = 0;
		}

		if (cacti_sizeof($max_local_connections)) {
			$max_local_connections = $max_local_connections['Value'];
		} else {
			$max_local_connections = 0;
		}

		$total_dc_threads = db_fetch_cell("SELECT
			SUM((processes * threads) + (processes * $script_servers)) AS threads
			FROM poller
			WHERE disabled = ''");

		$recommend_mc = $total_dc_threads + 100;

		if ($recommend_mc > $max_connections) {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		} else {
			if ($config['poller_id'] == 1) {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} elseif ($config['connection'] == 'online') {
				$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections = '';
			}
		}

		if ($config['poller_id'] > 1) {
			if ($recommend_mc > $max_local_connections) {
				$db_connections .= '<br><span class="deviceDown">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			} else {
				$db_connections .= '<br><span class="deviceUp">' . __('Local Server: Current: %s, Min Required: %s', $max_local_connections, $recommend_mc) . '</span>';
			}
		}

		form_alternate_row();
		print '<td>' . __('Minimum Connections:') . '</td>';
		print '<td>' . $db_connections . '<br>' .
			__('Assumes 100 spare connections for Web page users and other various connections.') . '<br>' .
			__('The minimum required can vary greatly if there is heavy user Graph viewing activity.') . '<br>' .
			__('Each browser tab can use upto 10 connections depending on the browser.') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Script Timeout') . '</td>';
		print '<td>' . read_config_option('script_timeout') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max OID') . '</td>';
		print '<td>' . read_config_option('max_get_size') . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Last Run Statistics') . '</td>';
		print '<td>' . read_config_option('stats_poller') . '</td>';
		form_end_row();

		/* Get System Memory */
		$memInfo = utilities_get_system_memory();

		//print '<pre>';print_r($memInfo);print '</pre>';

		$total_memory = 0;

		if (cacti_sizeof($memInfo)) {
			html_section_header(__('System Memory'), 2);

			foreach($memInfo as $name => $value) {
				if ($config['cacti_server_os'] == 'win32') {
					form_alternate_row();
					print "<td>$name</td>";
					print '<td>' . number_format_i18n($value/1000, 2) . " MB</td>";
					form_end_row();
				} else {
					switch($name) {
					case 'SwapTotal':
					case 'SwapFree':
					case 'Cached':
					case 'MemTotal':
					case 'MemFree':
					case 'MemAvailable':
					case 'Buffers':
					case 'Active':
					case 'Inactive':
						// Convert to GBi
						$value /= (1000 * 1000 * 1000);

						form_alternate_row();
						print "<td>$name</td>";
						print '<td>' . __('%0.2f GB', number_format_i18n($value, 2, 1000)) . '</td>';
						form_end_row();

						if ($name == 'MemTotal') {
							$total_memory = $value;
						}
					}
				}
			}

			form_end_row();
		}

		$mysql_info = utilities_get_mysql_info($config['poller_id']);

		$database  = $mysql_info['database'];
		$version   = $mysql_info['version'];
		$link_ver  = $mysql_info['link_ver'];
		$variables = $mysql_info['variables'];

		// Get Maximum Memory in GB for MySQL/MariaDB
		if ($config['poller_id'] == 1) {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			} else {
				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024');

				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)');
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024');
		} else {
			if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.query_cache_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			} else {
				$maxPossibleMyMemory = db_fetch_cell('SELECT (
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size
					+ @@GLOBAL.max_connections * (
						@@GLOBAL.sort_buffer_size
						+ @@GLOBAL.read_buffer_size
						+ @@GLOBAL.read_rnd_buffer_size
						+ @@GLOBAL.join_buffer_size
						+ @@GLOBAL.thread_stack
						+ @@GLOBAL.binlog_cache_size)
					) / 1024 / 1024 / 1024)', '', false, $local_db_cnn_id);

				$systemMemory = db_fetch_cell('SELECT
					(@@GLOBAL.key_buffer_size
					+ @@GLOBAL.tmp_table_size
					+ @@GLOBAL.innodb_buffer_pool_size
					+ @@GLOBAL.innodb_log_buffer_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
			}

			$clientMemory = db_fetch_cell('SELECT @@GLOBAL.max_connections * (
				@@GLOBAL.sort_buffer_size
				+ @@GLOBAL.read_buffer_size
				+ @@GLOBAL.read_rnd_buffer_size
				+ @@GLOBAL.join_buffer_size
				+ @@GLOBAL.thread_stack
				+ @@GLOBAL.binlog_cache_size) / 1024 / 1024 / 1024', '', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL/MariaDB Memory Statistics (Source: MySQL Tuner)'), 2);

		if ($total_memory > 0) {
			if ($maxPossibleMyMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
				form_alternate_row();
				print '<td></td>';
				print '<td>' . __('Reduce MySQL/MariaDB Memory to less than 80% of System Memory.  Preserve additional Cache Memory for RRDfiles if the Database is on the same system as the RRDfiles.  See Core and Client Totals below for explanation of calculation method.') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Total Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
				form_end_row();
			}
		} else {
			form_alternate_row();
			print '<td>' . __('Max Total Memory Possible') . '</td>';
			print '<td>' . __('%0.2f GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		if ($total_memory > 0) {
			if ($systemMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Core Memory') . '</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Core Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.key_buffer_size + <br>@@GLOBAL.query_cache_size + <br>@@GLOBAL.tmp_table_size + <br>@@GLOBAL.innodb_buffer_pool_size + <br>@@GLOBAL.innodb_log_buffer_size</td>';
			form_end_row();

			if ($clientMemory > ($total_memory * 0.8)) {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceDown">' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Client Memory') . ')</td>';
				form_end_row();
			} else {
				form_alternate_row();
				print '<td>' . __('Max Connection Memory Possible') . '</td>';
				print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
				form_end_row();
			}

			form_alternate_row();
			print '<td>' . __('Calculation Formula') . '</td>';
			print '<td>SELECT @@GLOBAL.max_connections * (<br>@@GLOBAL.sort_buffer_size + <br>@@GLOBAL.read_buffer_size + <br>@@GLOBAL.read_rnd_buffer_size + <br>@@GLOBAL.join_buffer_size + <br>@@GLOBAL.thread_stack + <br>@@GLOBAL.binlog_cache_size)</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Core Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%0.2f GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
			form_end_row();

			form_alternate_row();
			print '<td>' . __('Max Connection Memory Possible') . '</td>';
			print '<td>' . __('%0.2f GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		html_section_header(__('PHP Information'), 2);

		form_alternate_row();
		print '<td>' . __('PHP Version') . '</td>';
		if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
			print '<td>' . PHP_VERSION . '</td>';
		} else {
			print '<td>' . PHP_VERSION . "</br><span class='deviceDown'>" . __('PHP Version 5.5.0+ is recommended due to strong password hashing support.') . "</span></td>";
		}
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP OS') . '</td>';
		print '<td>' . PHP_OS . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP uname') . '</td>';
		print '<td>';
		if (function_exists('php_uname')) {
			print php_uname();
		} else {
			print __('N/A');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('PHP SNMP') . '</td>';
		print '<td>';
		if (function_exists('snmpget')) {
			print __('Installed. <span class="deviceDown">Note: If you are planning on using SNMPv3, you must remove php-snmp and use the Net-SNMP toolset.</span>');
		} else {
			print __('Not Installed');
		}
		print '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>max_execution_time</td>";
		print '<td>' . ini_get('max_execution_time') . '</td>';
		form_end_row();

		form_alternate_row();
		print "<td>memory_limit</td>";
		print '<td>' . ini_get('memory_limit');

		/* Calculate memory suggestion based off of data source count */
		$memory_suggestion = $data_total * 32768;

		/* Set minimum - 16M */
		if ($memory_suggestion < 16777216) {
			$memory_suggestion = 16777216;
		}

		/* Set maximum - 512M */
		if ($memory_suggestion > 536870912) {
			$memory_suggestion = 536870912;
		}

		/* Suggest values in 8M increments */
		$memory_suggestion = round($memory_suggestion / 8388608) * 8388608;
		if (memory_bytes(ini_get('memory_limit')) < $memory_suggestion) {
			print "<br><span class='deviceDown'>";
			if ((ini_get('memory_limit') == -1)) {
				print __("You've set memory limit to 'unlimited'.") . "<br>";
			}
			print __('It is highly suggested that you alter you php.ini memory_limit to %s or higher.', memory_readable($memory_suggestion)) . ' <br/>' .
				__('This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.');
			print '</span><br>';
		}
		print '</td>';
		form_end_row();

		utilities_get_mysql_recommendations();
	} elseif (get_request_var('tab') == 'dbstatus') {
		$status = db_fetch_assoc('show global status');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value']):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'dbsettings') {
		$status = db_fetch_assoc('show global variables');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . $s['Variable_name'] . '</td>';

			if (strlen($s['Value']) > 70) {
				$s['Value'] = str_replace(',', ', ', $s['Value']);
			}
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value']):$s['Value']) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'changelog') {
		$changelog = file($config['base_path'] . '/CHANGELOG');

		foreach($changelog as $s) {
			if (strlen(trim($s)) && stripos($s, 'CHANGELOG') === false) {
				if (strpos($s, '-') === false) {
					html_section_header(__('Version %s', $s), 2);
				} else {
					form_alternate_row();
					print '<td>' . $s . '</td>';
					form_end_row();
				}
			}
		}
	} elseif (get_request_var('tab') == 'database') {
		/* Get table status */
		if ($config['poller_id'] == 1) {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()');
		} else {
			$tables = db_fetch_assoc('SELECT *
				FROM information_schema.tables
				WHERE table_schema = SCHEMA()', false, $local_db_cnn_id);
		}

		html_section_header(__('MySQL Table Information - Sizes in KBytes'), 2);

		form_alternate_row();
		print "		<td colspan='2' style='text-align:left;padding:0px'>";

		if (cacti_sizeof($tables)) {
			print "<table id='tables' class='cactiTable' style='width:100%'>";
			print '<thead>';
			print "<tr class='tableHeader'>";
			print "  <th class='tableSubHeaderColumn'>" . __('Name') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Engine') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Rows') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Avg Row Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Data Length') . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Index Length') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Collation') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Row Format') . '</th>';
			print "  <th class='tableSubHeaderColumn'>" . __('Comment') . '</th>';
			print '</tr>';
			print '</thead>';
			foreach ($tables as $table) {
				form_alternate_row();
				print '<td>' . $table['TABLE_NAME'] . '</td>';
				print '<td>' . $table['ENGINE'] . '</td>';
				print '<td class="right">' . number_format_i18n($table['TABLE_ROWS']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['AVG_ROW_LENGTH']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['DATA_LENGTH']) . '</td>';
				print '<td class="right">' . number_format_i18n($table['INDEX_LENGTH']) . '</td>';
				print '<td>' . $table['TABLE_COLLATION'] . '</td>';
				print '<td>' . $table['ROW_FORMAT'] . '</td>';
				print '<td>' . $table['TABLE_COMMENT'] . '</td>';
				form_end_row();
			}

			print "</table>";
		} else {
			print __('Unable to retrieve table status');
		}
		print '</td>';

		form_end_row();
	} else {
		$php_info = utilities_php_modules();

		html_section_header(__('PHP Module Information'), 2);
		$php_info = str_replace(
			array('width="600"', 'th colspan="2"', ','),
			array('', 'th class="subHeaderColumn"', ', '),
			$php_info
		);
		print "<tr><td colspan='2'>" . $php_info . '</td></tr>';
	}

	html_end_box();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#tables').tablesorter({
			widgets: ['zebra'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-up',
			cssIconDesc: 'fa-sort-down',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});
	});
	</script>
	<?php

	bottom_footer();
}

function utilities_view_user_log() {
	global $auth_realms, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'time',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'username' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
			),
		'result' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_userlog');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function clearFilter() {
		strURL = urlPath+'utilities.php?action=view_user_log&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeLog() {
		strURL = urlPath+'utilities.php?action=clear_user_log&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_userlog').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = urlPath+'utilities.php?username=' + $('#username').val();
		strURL += '&result=' + $('#result').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&action=view_user_log';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}
	</script>
	<?php

	html_start_box(__('User Login History'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_userlog' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('User');?>
					</td>
					<td>
						<select id='username' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('username') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='-2'<?php if (get_request_var('username') == '-2') {?> selected<?php }?>><?php print __('Deleted/Invalid');?></option>
							<?php
							$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth ORDER BY username');

							if (cacti_sizeof($users)) {
								foreach ($users as $user) {
									print "<option value='" . html_escape($user['username']) . "'"; if (get_request_var('username') == $user['username']) { print ' selected'; } print '>' . html_escape($user['username']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Result');?>
					</td>
					<td>
						<select id='result' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('result') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='1'<?php if (get_request_var('result') == '1') {?> selected<?php }?>><?php print __('Success - Password');?></option>
							<option value='2'<?php if (get_request_var('result') == '2') {?> selected<?php }?>><?php print __('Success - Token');?></option>
							<option value='3'<?php if (get_request_var('result') == '3') {?> selected<?php }?>><?php print __('Success - Password Change');?></option>
							<option value='0'<?php if (get_request_var('result') == '0') {?> selected<?php }?>><?php print __('Failed');?></option>
						</select>
					</td>
					<td>
						<?php print __('Attempts');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge User Log');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_user_log'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by username */
	if (get_request_var('username') == '-2') {
		$sql_where = 'WHERE ul.username NOT IN (SELECT DISTINCT username FROM user_auth)';
	} elseif (get_request_var('username') != '-1') {
		$sql_where = "WHERE ul.username='" . get_request_var('username') . "'";
	}

	/* filter by result */
	if (get_request_var('result') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ul.result=' . get_request_var('result');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (
			ul.username LIKE '     . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ul.time LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ua.full_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ul.ip LIKE '        . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where");

	$user_log_sql = "SELECT ul.username, ua.full_name, ua.realm,
		ul.time, ul.result, ul.ip
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$user_log = db_fetch_assoc($user_log_sql);

	$nav = html_nav_bar('utilities.php?action=view_user_log&username=' . get_request_var('username') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('User Logins'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'username'  => array(__('User'), 'ASC'),
		'full_name' => array(__('Full Name'), 'ASC'),
		'realm'     => array(__('Authentication Realm'), 'ASC'),
		'time'      => array(__('Date'), 'DESC'),
		'result'    => array(__('Result'), 'DESC'),
		'ip'        => array(__('IP Address'), 'DESC')
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=view_user_log');

	$i = 0;
	if (cacti_sizeof($user_log)) {
		foreach ($user_log as $item) {
			form_alternate_row('line' . $i, true);
			?>
			<td class='nowrap'>
				<?php print filter_value($item['username'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php if (isset($item['full_name'])) {
						print filter_value($item['full_name'], get_request_var('filter'));
					} else {
						print __('(User Removed)');
					}
				?>
			</td>
			<td class='nowrap'>
				<?php if (isset($auth_realms[$item['realm']])) {
						print filter_value($auth_realms[$item['realm']], get_request_var('filter'));
					} else {
						print __('N/A');
					}
				?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['time'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php print ($item['result'] == 0 ? __('Failed'):($item['result'] == 1 ? __('Success - Password'):($item['result'] == 3 ? __('Success - Password Change'):__('Success - Token'))));?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['ip'], get_request_var('filter'));?>
			</td>
			</tr>
			<?php

			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($user_log)) {
		print $nav;
	}
}

function utilities_clear_user_log() {
	$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth');

	if (cacti_sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			$total_login_rows = db_fetch_cell_prepared('SELECT COUNT(username)
				FROM user_log
				WHERE username = ?
				AND result IN (1)',
				array($user['username']));

			$total_token_rows = db_fetch_cell_prepared('SELECT COUNT(username)
				FROM user_log
				WHERE username = ?
				AND result IN (2)',
				array($user['username']));

			if ($total_login_rows > 1) {
				db_execute_prepared('DELETE
					FROM user_log
					WHERE username = ?
					AND result IN(1)
					ORDER BY time LIMIT ' . ($total_login_rows - 1),
					array($user['username']));
			}

			if ($total_token_rows > 1) {
				db_execute_prepared('DELETE
					FROM user_log
					WHERE username = ?
					AND result IN(2)
					ORDER BY time
					LIMIT ' . ($total_token_rows - 1),
					array($user['username']));
			}

			db_execute_prepared('DELETE
				FROM user_log
				WHERE username = ?
				AND result = 0',
				array($user['username']));
		}

		/* delete inactive users */
		db_execute('DELETE
			FROM user_log
			WHERE user_id NOT IN (SELECT id FROM user_auth)
			OR username NOT IN (SELECT username FROM user_auth)');
	}
}

function utilities_view_logfile() {
	global $log_tail_lines, $page_refresh_interval, $config;

	$logfile = basename(get_nfilter_request_var('filename'));
	$logbase = basename(read_config_option('path_cactilog'));

	if ($logfile == '') {
		$logfile = $logbase;
	}

	if ($logfile == '') {
		$logfile = 'cacti.log';
	}

	$logname = '';
	$logpath = '';

	if (!clog_validate_filename($logfile, $logpath, $logname, true)) {
		raise_message('clog_invalid');
		header('Location: utilities.php?action=view_logfile&filename=' . $logbase);
		exit(0);
	} else {
		$logfile = $logpath . '/' . $logfile;
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'tail_lines' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => ''
			),
		'message_type' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'reverse' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'refresh' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_config_option('log_refresh_interval')
			)
	);

	validate_store_request_vars($filters, 'sess_log');
	/* ================= input validation ================= */

	$page_nr = get_request_var('page');

	$page = 'utilities.php?action=view_logfile&header=false';
	$page .= '&filename=' . basename($logfile) . '&page=' . $page_nr;

	$refresh = array(
		'seconds' => get_request_var('refresh'),
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	top_header();

	?>
	<script type='text/javascript'>

	function purgeLog() {
		strURL = urlPath+'utilities.php?action=purge_logfile&header=false&filename='+$('#filename').val();
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refreshme').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_logfile').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = urlPath+'utilities.php' +
			'?tail_lines=' + $('#tail_lines').val() +
			'&message_type=' + $('#message_type').val() +
			'&refresh=' + $('#refresh').val() +
			'&reverse=' + $('#reverse').val() +
			'&rfilter=' + base64_encode($('#rfilter').val()) +
			'&filename=' + $('#filename').val() +
			'&action=view_logfile' +
			'&header=false';
		refreshMSeconds=$('#refresh').val()*1000;
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL  = urlPath+'utilities.php?clear=1';
		strURL += '&action=view_logfile';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}
	</script>
	<?php

	html_start_box(__('Log Filters'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_logfile' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('File');?>
					</td>
					<td>
						<select id='filename' onChange='applyFilter()'>
							<?php
							$logFileArray = clog_get_logfiles();

							if (cacti_sizeof($logFileArray)) {
								foreach ($logFileArray as $logFile) {
									print "<option value='" . html_escape($logFile) . "'";

									if (get_nfilter_request_var('filename') == $logFile) {
										print ' selected';
									}

									$logParts = explode('-', $logFile);

									$logDate = cacti_count($logParts) < 2 ? '' : $logParts[1] . (isset($logParts[2]) ? '-' . $logParts[2]:'');
									$logName = $logParts[0];

									print '>' . html_escape($logName . ($logDate != '' ? ' [' . substr($logDate,4) . ']':'')) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Tail Lines');?>
					</td>
					<td>
						<select id='tail_lines' onChange='applyFilter()'>
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if (get_request_var('tail_lines') == $tail_lines) { print ' selected'; } print '>' . $display_text . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refreshme' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge Log');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Type');?>
					</td>
					<td>
						<select id='message_type' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('message_type') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('message_type') == '1') {?> selected<?php }?>><?php print __('Stats');?></option>
							<option value='2'<?php if (get_request_var('message_type') == '2') {?> selected<?php }?>><?php print __('Warnings');?></option>
							<option value='3'<?php if (get_request_var('message_type') == '3') {?> selected<?php }?>><?php print __('Errors');?></option>
							<option value='4'<?php if (get_request_var('message_type') == '4') {?> selected<?php }?>><?php print __('Debug');?></option>
							<option value='5'<?php if (get_request_var('message_type') == '5') {?> selected<?php }?>><?php print __('SQL Calls');?></option>
						</select>
					</td>
					<td>
						<?php print __('Display Order');?>
					</td>
					<td>
						<select id='reverse' onChange='applyFilter()'>
							<option value='1'<?php if (get_request_var('reverse') == '1') {?> selected<?php }?>><?php print __('Newest First');?></option>
							<option value='2'<?php if (get_request_var('reverse') == '2') {?> selected<?php }?>><?php print __('Oldest First');?></option>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if (get_request_var('refresh') == $seconds) { print ' selected'; } print '>' . $display_text . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='75' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_logfile'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* read logfile into an array and display */
	$total_rows      = 0;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows);

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (get_request_var('message_type') > 0) {
		$start_string = __('Log [Total Lines: %d - Non-Matching Items Hidden]', $total_rows);
	} else {
		$start_string = __('Log [Total Lines: %d - All Items Shown]', $total_rows);
	}

	$rfilter      = get_request_var('rfilter');
	$reverse      = get_request_var('reverse');
	$refreshTime  = get_request_var('refresh');
	$message_type = get_request_var('message_type');
	$tail_lines   = get_request_var('tail_lines');
	$base_url     = 'utilities.php?action=view_logfile&filename='.basename($logfile);

	$nav = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 13, __('Entries'), 'page', 'main');

	echo $nav;

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$linecolor = false;
	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		if (!$host_start && !$ds_start) {
			$new_item = html_escape($item);
		} else {
			$new_item = '';
			while ($host_start) {
				$host_end   = strpos($item, ']', $host_start);
				$host_id    = substr($item, $host_start + 7, $host_end - ($host_start + 7));
				$new_item  .= html_escape(substr($item, 0, $host_start + 7)) . "<a href='" . html_escape('host.php?action=edit&id=' . $host_id) . "'>" . html_escape(substr($item, $host_start + 7, $host_end - ($host_start + 7))) . '</a>';
				$item       = substr($item, $host_end);
				$host_start = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');
			while ($ds_start) {
				$ds_end    = strpos($item, ']', $ds_start);
				$ds_id     = substr($item, $ds_start + 3, $ds_end - ($ds_start + 3));
				$new_item .= html_escape(substr($item, 0, $ds_start + 3)) . "<a href='" . html_escape('data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . html_escape(substr($item, $ds_start + 3, $ds_end - ($ds_start + 3))) . '</a>';
				$item      = substr($item, $ds_end);
				$ds_start  = strpos($item, 'DS[');
			}

			$new_item .= html_escape($item);
		}

		/* get the background color */
		if (strpos($new_item, 'ERROR') !== false || strpos($new_item, 'FATAL') !== false) {
			$class = 'clogError';
		} elseif (strpos($new_item, 'WARN') !== false) {
			$class = 'clogWarning';
		} elseif (strpos($new_item, ' SQL ') !== false) {
			$class = 'clogSQL';
		} elseif (strpos($new_item, 'DEBUG') !== false) {
			$class = 'clogDebug';
		} elseif (strpos($new_item, 'STATS') !== false) {
			$class = 'clogStats';
		} else {
			if ($linecolor) {
				$class = 'odd';
			} else {
				$class = 'even';
			}
			$linecolor = !$linecolor;
		}

		print "<tr class='" . $class . "'><td>" . $new_item . "</td></tr>";
	}

	html_end_box();

	if ($total_rows) {
		echo $nav;
	}

	bottom_footer();
}

function utilities_clear_logfile() {
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'utilities.php?action=view_logfile&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	html_start_box(__('Clear Cacti Log'), '100%', '', '3', 'center', '');
	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			/* fill in the current date for printing in the log */
			if (defined('CACTI_DATE_TIME_FORMAT')) {
				$date = date(CACTI_DATE_TIME_FORMAT);
			} else {
				$date = date('Y-m-d H:i:s');
			}

			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, __('%s - WEBUI NOTE: Cacti Log Cleared from Web Management Interface.', $date) . PHP_EOL);
			fclose($log_fh);
			print '<tr><td>' . __('Cacti Log Cleared') . '</td></tr>';
		} else {
			print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, no write permissions.') . "<b></td></tr>";
		}
	} else {
		print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, file does not exist.'). "</b></td></tr>";
	}
	html_end_box();
}

function utilities_view_snmp_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'with_index' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'snmp_query_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'poller_action' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_usnmp');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page']    = 'utilities.php?action=view_snmp_cache&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL  = urlPath+'utilities.php?host_id=' + $('#host_id').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		if ($('#with_index').is(':checked')) {
			strURL += '&with_index=1';
		} else {
			strURL += '&with_index=0';
		}
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&action=view_snmp_cache';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = urlPath+'utilities.php?action=view_snmp_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpcache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Data Query Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_snmpcache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Query Name');?>
					</td>
					<td>
						<select id='snmp_query_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							if (get_request_var('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id=sq.id
									INNER JOIN host AS h
									ON hsc.host_id=h.id
									ORDER by sq.name');
							} else {
								$snmp_queries = db_fetch_assoc_prepared("SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id=sq.id
									INNER JOIN host AS h
									ON hsc.host_id=h.id
									ORDER by sq.name", array(get_request_var('host_id')));
							}

							if (cacti_sizeof($snmp_queries)) {
								foreach ($snmp_queries as $snmp_query) {
									print "<option value='" . $snmp_query['id'] . "'"; if (get_request_var('snmp_query_id') == $snmp_query['id']) { print ' selected'; } print '>' . html_escape($snmp_query['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='with_index' onChange='applyFilter()' title='<?php print __esc('Allow the search term to include the index column');?>' <?php if (get_request_var('with_index') == 1) { print ' checked '; }?>>
						<label for='with_index'><?php print __('Include Index') ?></label>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND h.id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND h.id=' . get_request_var('host_id');
	}

	/* filter by query name */
	if (get_request_var('snmp_query_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ' AND hsc.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			h.description LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . '
			OR sq.name LIKE '         . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_name LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_value LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.oid LIKE '         . db_qstr('%' . get_request_var('filter') . '%');

		if (get_request_var('with_index') == 1) {
			$sql_where .= ' OR hsc.snmp_index LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		}

		$sql_where .= ')';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		WHERE hsc.host_id = h.id
		AND hsc.snmp_query_id = sq.id
		$sql_where");

	$snmp_cache_sql = "SELECT hsc.*, h.description, sq.name
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		WHERE hsc.host_id = h.id
		AND hsc.snmp_query_id = sq.id
		$sql_where
		LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	$nav = html_nav_bar('utilities.php?action=view_snmp_cache&host_id=' . get_request_var('host_id') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(__('Device'), __('Data Query Name'), __('Index'), __('Field Name'), __('Field Value'), __('OID')));

	$i = 0;
	if (cacti_sizeof($snmp_cache)) {
	foreach ($snmp_cache as $item) {
		form_alternate_row();
		?>
		<td>
			<?php print filter_value($item['description'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['name'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print html_escape($item['snmp_index']);?>
		</td>
		<td>
			<?php print filter_value($item['field_name'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['field_value'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['oid'], get_request_var('filter'));?>
		</td>
		</tr>
		<?php
		}
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}
}

function utilities_view_poller_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'dtd.name_cache',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'poller_action' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		)
	);

	validate_store_request_vars($filters, 'sess_poller');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page']    = 'utilities.php?action=view_poller_cache';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = urlPath+'utilities.php?poller_action=' + $('#poller_action').val();
		strURL += '&action=view_poller_cache';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&template_id=' + $('#template_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&status=' + $('#status').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = urlPath+'utilities.php?action=view_poller_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_pollercache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Poller Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_pollercache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							if (get_request_var('host_id') > 0) {
								$sql_where = 'WHERE dl.host_id = ' . get_request_var('host_id');
							} else {
								$sql_where = '';
							}

							$templates = db_fetch_assoc("SELECT DISTINCT dt.id, dt.name
								FROM data_template AS dt
								INNER JOIN data_local AS dl
								ON dt.id=dl.data_template_id
								$sql_where
								ORDER BY name");

							if (cacti_sizeof($templates)) {
								foreach ($templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var('template_id') == $template['id']) { print ' selected'; } print '>' . html_escape($template['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Disabled');?></option>
						</select>
					</td>
					<td>
						<?php print __('Action');?>
					</td>
					<td>
						<select id='poller_action' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('poller_action') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('poller_action') == '0') {?> selected<?php }?>><?php print __('SNMP');?></option>
							<option value='1'<?php if (get_request_var('poller_action') == '1') {?> selected<?php }?>><?php print __('Script');?></option>
							<option value='2'<?php if (get_request_var('poller_action') == '2') {?> selected<?php }?>><?php print __('Script Server');?></option>
						</select>
					</td>
					<td>
						<?php print __('Entries');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_poller_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';

	if (get_request_var('poller_action') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . " pi.action='" . get_request_var('poller_action') . "'";
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' pi.host_id = 0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' pi.host_id = ' . get_request_var('host_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' dtd.data_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' dl.data_template_id=' . get_request_var('template_id');
	}

	if (get_request_var('status') == 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (h.disabled = "on" OR dtd.active = "")';
	} elseif (get_request_var('status') == 1) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (h.disabled = "" AND dtd.active = "on")';
	}

	if (get_request_var('filter') != '') {
		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR h.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.hostname LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
		}
	}

	$sql = "SELECT COUNT(*)
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION['sess_user_id'], $sql, array(), 'poller_item');

	$poller_sql = "SELECT pi.*, dtd.name_cache, h.description, h.id AS host_id
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . ', action ASC
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$poller_cache = db_fetch_assoc($poller_sql);

	$nav = html_nav_bar('utilities.php?action=view_poller_cache&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 3, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'dtd.name_cache' => array(__('Data Source Name'), 'ASC'),
		'h.description' => array(__('Device Description'), 'ASC'),
		'nosort' => array(__('Details'), 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=view_poller_cache');

	$i = 0;
	if (cacti_sizeof($poller_cache)) {
		foreach ($poller_cache as $item) {
			if ($i % 2 == 0) {
				$class = 'odd';
			} else {
				$class = 'even';
			}
			print "<tr class='$class'>";
				?>
				<td>
					<?php print filter_value($item['name_cache'], get_request_var('filter'), 'data_sources.php?action=ds_edit&id=' . $item['local_data_id']);?>
				</td>
				<td>
					<?php print filter_value($item['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $item['host_id']);?>
				</td>

				<td>
				<?php
				if ($item['action'] == 0) {
					if ($item['snmp_version'] != 3) {
						$details =
							__('SNMP Version:') . ' ' . $item['snmp_version'] . ', ' .
							__('Community:') . ' ' . html_escape($item['snmp_community']) . ', ' .
							__('OID:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
					} else {
						$details =
							__('SNMP Version:') . ' ' . $item['snmp_version'] . ', ' .
							__('User:') . ' ' . html_escape($item['snmp_username']) . ', ' . __('OID:') . ' ' . html_escape($item['arg1']);
					}
				} elseif ($item['action'] == 1) {
					$details = __('Script:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
				} else {
					$details = __('Script Server:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
				}

				print $details;

				?>
				</td>
			</tr>
			<?php
			print "<tr class='$class'>";
			?>
				<td colspan='2'>
				</td>
				<td>
					<?php print __('RRD:');?> <?php print html_escape($item['rrd_path']);?>
				</td>
			</tr>
			<?php
			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($poller_cache)) {
		print $nav;
	}
}

function utilities() {
	global $config, $utilities;

	$utilities[__('Technical Support')] = array(
		__('Technical Support') => array(
			'link'  => 'utilities.php?action=view_tech',
			'description' => __('Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.')
		),
		__('Log Administration') => array(
			'link'  => 'utilities.php?action=view_logfile',
			'description' => __('The Cacti Log stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.')
		),
		__('View User Log') => array(
			'link'  => 'utilities.php?action=view_user_log',
			'description' => __('Allows Administrators to browse the user log.  Administrators can filter and export the log as well.')
		)
	);

	$utilities[__('Poller Cache Administration')] = array(
		__('View Poller Cache') => array(
			'link'  => 'utilities.php?action=view_poller_cache',
			'description' => __('This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the RRDfiles for graphing or the database for display.')
		),
		__('View Data Query Cache') => array(
			'link'  => 'utilities.php?action=view_snmp_cache',
			'description' => __('The Data Query Cache stores information gathered from Data Query input types. The values from these fields can be used in the text area of Graphs for Legends, Vertical Labels, and GPRINTS as well as in CDEF\'s.')
		),
		__('Rebuild Poller Cache') => array(
			'link'  => 'utilities.php?action=clear_poller_cache',
			'mode'  => 'online',
			'description' => __('The Poller Cache will be re-generated if you select this option. Use this option only in the event of a database crash if you are experiencing issues after the crash and have already run the database repair tools.  Alternatively, if you are having problems with a specific Device, simply re-save that Device to rebuild its Poller Cache.  There is also a command line interface equivalent to this command that is recommended for large systems.'),
			'note'        => array (
				'message' => __('NOTE: On large systems, this command may take several minutes to hours to complete and therefore should not be run from the Cacti UI.  You can simply run \'php -q cli/rebuild_poller_cache.php --help\' at the command line for more information.'),
				'class'   => 'textWarning'
			)
		),
		__('Rebuild Resource Cache') => array(
			'link'  => 'utilities.php?action=rebuild_resource_cache',
			'mode'  => 'online',
			'description' => __('When operating multiple Data Collectors in Cacti, Cacti will attempt to maintain state for key files on all Data Collectors.  This includes all core, non-install related website and plugin files.  When you force a Resource Cache rebuild, Cacti will clear the local Resource Cache, and then rebuild it at the next scheduled poller start.  This will trigger all Remote Data Collectors to recheck their website and plugin files for consistency.')
		),
	);

	$utilities[__('Boost Utilities')] = array(
		__('View Boost Status') => array(
			'link'  => 'utilities.php?action=view_boost_status',
			'description' => __('This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.')
		),
	);

	$utilities[__('Data Source Statistics Utilities')] = array(
		__('Purge Data Source Statistics') => array(
			'link'  => 'utilities.php?action=purge_data_source_statistics',
			'mode'  => 'online',
			'description' => __('This menu pick will purge all existing Data Source Statistics from the Database.  If Data Source Statistics is enabled, the Data Sources Statistics will start collection again on the next Data Collector pass.')
		),
	);

	if ($config['poller_id'] == 1) {
		$utilities[__('RRD Utilities')] = array(
			__('RRDfile Cleaner') => array(
				'link'  => 'rrdcleaner.php',
				'mode'  => 'online',
				'description' => __('When you delete Data Sources from Cacti, the corresponding RRDfiles are not removed automatically.  Use this utility to facilitate the removal of these old files.')
			),
			__('RRDfile Checker') => array(
				'link'  => 'rrdcheck.php',
				'mode'  => 'online',
				'description' => __('Use this utility to display problems with missing rrd files or missing values in rrdfiles. You need enable rrdcheck in Configuration->Settings->Data')
			),
		);
	}

	if (snmpagent_enabled()) {
		$utilities[__('SNMP Agent Utilities')] = array(
			__('View SNMP Agent Cache') => array(
				'link'  => 'utilities.php?action=view_snmpagent_cache',
				'mode'  => 'online',
				'description' => __('This shows all objects being handled by the SNMP Agent.')
			),
			__('Rebuild SNMP Agent Cache') => array(
				'link'  => 'utilities.php?action=rebuild_snmpagent_cache',
				'mode'  => 'online',
				'description' => __('The SNMP cache will be cleared and re-generated if you select this option. Note that it takes another poller run to restore the SNMP cache completely.')
			),
			__('View SNMP Agent Notification Log') => array(
				'link'  => 'utilities.php?action=view_snmpagent_events',
				'mode'  => 'online',
				'description' => __('This menu pick allows you to view the latest events SNMP Agent has handled in relation to the registered notification receivers.')
			),
			__('SNMP Notification Receivers') => array(
				'link'  => 'managers.php',
				'mode'  => 'online',
				'description' => __('Allows Administrators to maintain SNMP notification receivers.')
			),
		);
	}

	api_plugin_hook('utilities_array');

	html_start_box(__('Cacti System Utilities'), '100%', '', '3', 'center', '');

	foreach($utilities as $header => $content) {
		$i = 0;

		foreach($content as $title => $details) {
			if ((isset($details['mode']) && $details['mode'] == 'online' && $config['connection'] == 'online') || !isset($details['mode'])) {
				if ($i == 0) {
					html_section_header($header, 2);
				}

				form_alternate_row();
				print "<td class='nowrap' style='vertical-align:top;'>";
				print "<a class='hyperLink' href='" . html_escape($details['link']) . "'>" . $title . '</a>';
				print '</td>';
				print '<td>';
				print html_escape($details['description']);

				if(isset($details['note'])) {
					print '<br/><i class="' . $details['note']['class'] . '">' . html_escape($details['note']['message']) . '</i>';
				}

				print '</td>';
				form_end_row();

				$i++;
			}
		}
	}

	api_plugin_hook('utilities_list');

	html_end_box();
}

function purge_data_source_statistics() {
	$tables = array(
		'data_source_stats_daily',
		'data_source_stats_hourly',
		'data_source_stats_hourly_cache',
		'data_source_stats_hourly_last',
		'data_source_stats_monthly',
		'data_source_stats_weekly',
		'data_source_stats_yearly'
	);

	foreach($tables as $table) {
		db_execute('TRUNCATE TABLE ' . $table);
	}

	if (isset($_SESSION['sess_user_id'])) {
		cacti_log('NOTE: Cacti DS Stats purged by user ' . get_username($_SESSION['sess_user_id']), false, 'WEBUI');
	} else {
		cacti_log('NOTE: Cacti DS Stats purged by cli script');
	}
}

function boost_display_run_status() {
	global $config, $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	/* ================= input validation ================= */
	get_filter_request_var('refresh');
	/* ==================================================== */

	load_current_session_value('refresh', 'sess_boost_utilities_refresh', '30');

	$last_run_time   = read_config_option('boost_last_run_time', true);
	$next_run_time   = read_config_option('boost_next_run_time', true);

	$rrd_updates     = read_config_option('boost_rrd_update_enable', true);
	$boost_cache     = read_config_option('boost_png_cache_enable', true);

	$max_records     = read_config_option('boost_rrd_update_max_records', true);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', true);
	$update_interval = read_config_option('boost_rrd_update_interval', true);
	$peak_memory     = read_config_option('boost_peak_memory', true);
	$detail_stats    = read_config_option('stats_detail_boost', true);

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'utilities.php?action=view_boost_status&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	html_start_box(__('Boost Status'), '100%', '', '3', 'center', '');

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL = urlPath+'utilities.php?action=view_boost_status&header=false&refresh=' + $('#refresh').val();
		loadPageNoHeader(strURL);
	}
	</script>
	<tr class='even'>
		<form id='form_boost_utilities_stats' method='post'>
		<td>
			<table>
				<tr>
					<td class='nowrap'>
						<?php print __('Refresh Interval');?>
					</td>
					<td>
						<select id='refresh' name='refresh' onChange='applyFilter()'>
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"'; if (get_request_var('refresh') == $key) { print ' selected'; } print '>' . $interval . '</option>';
						}
						?>
					</td>
					<td>
						<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Refresh');?>' onClick='applyFilter()'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(true);

	html_start_box('', '100%', '', '3', 'center', '');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");

	$pending_records = 0;
	$arch_records    = 0;
	$data_length     = 0;
	$engine          = '';
	$max_data_length = 0;

	foreach($boost_table_status as $table) {
		if ($table['TABLE_NAME'] == 'poller_output_boost') {
			$pending_records += $table['TABLE_ROWS'];
		} else {
			$arch_records += $table['TABLE_ROWS'];
		}

		$data_length    += $table['DATA_LENGTH'];
		$data_length    += $table['INDEX_LENGTH'];
		$engine          = $table['ENGINE'];
		$max_data_length = $table['MAX_DATA_LENGTH'];
	}

	if ($config['connection'] == 'online' && db_table_exists('poller_output_boost_local_data_ids')) {
		$pending_ds = db_fetch_cell('SELECT COUNT(local_data_id) FROM poller_output_boost_local_data_ids');
	} else {
		$pending_ds = 0;
	}

	$poller_items = db_fetch_cell('SELECT COUNT(local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$data_sources = db_fetch_cell('SELECT COUNT(DISTINCT local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$pi_ds = ($data_sources ? ($poller_items / $data_sources) : 0);

	if ($pending_ds == 0) {
		$remaining = $arch_records;
	} else {
		$remaining = $arch_records * (($pending_ds * $pi_ds) / $data_sources);
	}

	$total_records  = $pending_records + $remaining;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$boost_status = read_config_option('boost_poller_status', true);
	if ($boost_status != '' && $boost_status != 'Disabled') {
		$boost_status_array = explode(':', $boost_status);

		$boost_status_date  = $boost_status_array[1];

		if (substr_count($boost_status_array[0], 'complete')) {
			$status = '<span class="deviceRecovering">' . __('Idle') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'running')) {
			$status = '<span class="deviceUp">' . __('Running') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'overrun')) {
			$status = '<span class="deviceDown">' . __('Overrun Warning') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'timeout')) {
			$status = '<span class="deviceDown">' . __('Timed Out') . '</span>';
		} else {
			$status = '<span class="deviceDown">' . __('Other') . '</span>';
		}
	} else {
		$status = '<span class="deviceDisabled">' . __('Disabled') . '</span>';
		$boost_status_date = '';
	}

	$stats_boost = read_config_option('stats_boost', true);
	if ($stats_boost != '') {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	} else {
		$boost_last_run_duration = '';
		$boost_rrds_updated = '';
	}

	/* get cache directory size/contents */
	$cache_directory    = read_config_option('boost_png_cache_directory', true);
	$directory_contents = array();

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			/* This is the correct way to loop over the directory. */
			while (false !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			/* get size of directory */
			$directory_size = 0;
			$cache_files    = 0;

			if (cacti_sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach($directory_contents as $file) {
					/* only remove jpeg's and png's */
					if ((substr_count(strtolower($file), '.png')) ||
						(substr_count(strtolower($file), '.jpg'))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files    = $cache_files . ' Files';
		} else {
			$directory_size = '<strong>' . __('WARNING:') . '</strong>' . __('Cannot open directory');
			$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('Unknown');
		}
	} else {
		$directory_size = '<strong>' . __('WARNING:') . '</strong> ' . __('Directory Does NOT Exist!!');
		$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('N/A');
	}

	$running = db_fetch_cell('SELECT COUNT(*) FROM processes WHERE tasktype="boost" AND taskname="child"');

	$i = 0;

	/* boost status display */
	html_section_header(__('Current Boost Status'), 2);

	if ($config['connection'] == 'online') {
		form_alternate_row();
		print '<td>' . __('Boost On-demand Updating:') . '</td><td><b>' . $status . '</b></td>';

		if ($running > 0) {
			form_alternate_row();
			print '<td>' . __('Running Processes:') . '</td><td>' . ($running) . '</td>';
		}
	}

	form_alternate_row();
	print '<td>' . __('Total Poller Items:') . '</td><td>' . number_format_i18n($poller_items) . '</td>';

	$premaining = ($data_sources ? (round(($pending_ds / $data_sources) * 100, 1)) : 0);

	if ($total_records) {
		form_alternate_row();
		print '<td>' . __('Total Data Sources:') . '</td><td>' . number_format_i18n($data_sources) . '</td>';

		if ($config['connection'] == 'online') {
			form_alternate_row();
			print '<td>' . __('Remaining Data Sources:') . '</td><td>' . ($pending_ds > 0 ? number_format_i18n($pending_ds) . " ($premaining %)":__('TBD')) . '</td>';
		}

		form_alternate_row();
		print '<td>' . __('Queued Boost Records:') . '</td><td>' . number_format_i18n($pending_records) . '</td>';

		if ($config['connection'] == 'online') {
			form_alternate_row();
			print '<td>' . __('Approximate in Process:') . '</td><td>' . number_format_i18n($remaining) . '</td>';

			form_alternate_row();
			print '<td>' . __('Total Boost Records:') . '</td><td>' . number_format_i18n($total_records) . '</td>';
		}
	}

	/* boost status display */
	html_section_header(__('Boost Storage Statistics'), 2);

	/* describe the table format */
	form_alternate_row();
	print '<td>' . __('Database Engine:') . '</td><td>' . $engine . '</td>';

	/* tell the user how big the table is */
	form_alternate_row();
	print '<td>' . __('Current Boost Table(s) Size:') . '</td><td>' . boost_file_size_display($data_length, 2) . '</td>';

	/* tell the user about the average size/record */
	form_alternate_row();
	print '<td>' . __('Avg Bytes/Record:') . '</td><td>' . boost_file_size_display($avg_row_length, 0) . '</td>';

	/* tell the user about the average size/record */
	$output_length = read_config_option('boost_max_output_length');
	if ($output_length != '') {
		$parts = explode(':', $output_length);
		if ((time()-1200) > $parts[0]) {
			$ref = true;
		} else {
			$ref = false;
		}
	} else {
		$ref = true;
	}

	if ($ref) {
		if (strcmp($engine, 'MEMORY') == 0) {
			$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		} else {
			$max_length = '0';
		}
		db_execute("REPLACE INTO settings (name, value) VALUES ('boost_max_output_length', '" . time() . ':' . $max_length . "')");
	} else {
		$max_length = $parts[1];
	}

	if ($max_length != 0) {
		form_alternate_row();
		print '<td>' . __('Max Record Length:') . '</td><td>' . __('%d Bytes', number_format_i18n($max_length)) . '</td>';
	}

	/* tell the user about the "Maximum Size" this table can be */
	form_alternate_row();
	if (strcmp($engine, 'MEMORY')) {
		$max_table_allowed = __('Unlimited');
		$max_table_records = __('Unlimited');
	} else {
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = number_format_i18n(($avg_row_length ? $max_data_length/$avg_row_length : 0), 3, 1000);
	}
	print '<td>' . __('Max Allowed Boost Table Size:') . '</td><td>' . $max_table_allowed . '</td>';

	/* tell the user about the estimated records that "could" be held in memory */
	form_alternate_row();
	print '<td>' . __('Estimated Maximum Records:') . '</td><td>' . $max_table_records . ' Records</td>';

	if ($config['connection'] == 'online') {
		/* boost last runtime display */
		html_section_header(__('Previous Runtime Statistics'), 2);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last Start Time:') . '</td><td>' . (is_numeric($last_run_time) ? date('Y-m-d H:i:s', $last_run_time):$last_run_time) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last Run Duration:') . '</td><td>';

		if (is_numeric($boost_last_run_duration)) {
			print ($boost_last_run_duration > 60 ? __('%d minutes', (int)$boost_last_run_duration / 60) . ', ': '') . __('%d seconds', (int) $boost_last_run_duration % 60);

			if ($rrd_updates != ''){
				print ' (' . __('%0.2f percent of update frequency)', round(100 * $boost_last_run_duration / $update_interval / 60));
			}
		} else {
			print __('N/A');
		}
		print '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('RRD Updates:') . '</td><td>' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated):'-') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Peak Poller Memory:') . '</td><td>' . ((read_config_option('boost_peak_memory') != '' && is_numeric(read_config_option('boost_peak_memory'))) ? (round(read_config_option('boost_peak_memory')/1024/1024,2)) . ' ' . __('MBytes') : __('N/A')) . '</td>';

		form_alternate_row();

		$memory_limit = read_config_option('boost_poller_mem_limit');

		if ($memory_limit == -1) {
			$memory_limit = __('Unlimited');
		} elseif ($memory_limit != '') {
			$memory_limit = __('%s MBytes', number_format_i18n($memory_limit) );
		} else {
			$memory_limit = __('N/A');
		}

		print '<td class="utilityPick">' . __('Max Poller Memory Allowed:') . '</td><td>' . $memory_limit . '</td>';

		/* boost last runtime display */
		html_section_header(__('Detailed Runtime Statistics'), 2);

		form_alternate_row();

		if ($detail_stats == '') {
			$detail_stats = __('N/A');
		} else {
			$values = explode(' ', $detail_stats);
			$rows   = explode(':', $values[0])[1];
			$time   = explode(':', $values[1])[1];
			$recs   = explode(':', $values[2])[1];
			$rcycle = explode(':', $values[3])[1];
			$fandt  = explode(':', $values[4])[1];
			$lastu  = explode(':', $values[5])[1];
			$update = explode(':', $values[6])[1];
			$delete = explode(':', $values[7])[1];

			$detail_stats = __('Records: %s (ds rows), Time: %s (secs), GetRows: %s (secs), ResultsCycle: %s (secs), FileAndTemplate: %s (secs), LastUpdate: %s (secs), RRDUpdate: %s (secs), Delete: %s (secs)',
				number_format_i18n($rows),
				number_format_i18n($time),
				number_format_i18n($recs),
				number_format_i18n($rcycle),
				number_format_i18n($fandt),
				number_format_i18n($lastu),
				number_format_i18n($update),
				number_format_i18n($delete));
		}

		print '<td class="utilityPick">' . __('Previous Runtime Timers:') . '</td><td>' . (($detail_stats != '') ? $detail_stats:__('N/A')) . '</td>';

		$runtimes = db_fetch_assoc('SELECT name, value, CAST(replace(name, "stats_boost_", "") AS signed) AS ome
			FROM settings
			WHERE name LIKE "stats_boost_%"
			ORDER BY ome');

		if (cacti_sizeof($runtimes)) {
			foreach($runtimes as $r) {
				$process = str_replace('stats_boost_', '', $r['name']);

				if ($r['value'] != '') {
					$values = explode(' ', $r['value']);
					$time = explode(':', $values[0])[1];
					$rrds = explode(':', $values[2])[1];
				} else {
					$time = 0;
					$rrds = 0;
				}

				$rows_to_process = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM poller_output_boost_local_data_ids
					WHERE process_handler = ?',
					array($process));

				$runtime = db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started)
					FROM processes
					WHERE tasktype = "boost"
					AND taskname = "child"
					AND taskid = ?',
					array($process));

				form_alternate_row();

				if ($rows_to_process > 0) {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceUp"><b>Running</b></span>, Remaining: %s (dses), CurrentRuntime: %s (secs), PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n($rows_to_process), number_format_i18n($runtime), number_format_i18n($time), number_format_i18n($rrds)) . '</td>';
				} else {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceRecovering"><b>Idle</b></span>, PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n($time), number_format_i18n($rrds)) . '</td>';
				}
			}
		}

		/* boost runtime display */
		html_section_header(__('Run Time Configuration'), 2);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Update Frequency:') . '</td><td>' . ($rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval]) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Concurrent Processes:') . '</td><td>' . read_config_option('boost_parallel') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Next Start Time:') . '</td><td>' . (is_numeric($next_run_time) ? date('Y-m-d H:i:s', $next_run_time):$next_run_time) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Records:') . '</td><td>' . number_format_i18n($max_records) . ' ' . __('Records') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Allowed Runtime:') . '</td><td>' . $boost_max_runtime[$max_runtime] . '</td>';

		/* boost caching */
		html_section_header(__('Image Caching'), 2);

		form_alternate_row();
		print '<td>' . __('Image Caching Status:') . '</td><td>' . ($boost_cache == '' ? __('Disabled') : __('Enabled')) . '</td>';

		form_alternate_row();
		print '<td>' . __('Cache Directory:') . '</td><td>' . $cache_directory . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files:') . '</td><td>' . $cache_files . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files Size:') . '</td><td>' . $directory_size . '</td>';

		html_end_box(true);
	}
}

/**
 *
 *
 * snmpagent_utilities_run_cache()
 *
 * @param mixed
 * @return
 */
function snmpagent_utilities_run_cache() {
	global $item_rows;

	get_filter_request_var('mib', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	$mibs = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');
	$registered_mibs = array();
	if($mibs && $mibs >0) {
		foreach($mibs as $mib) { $registered_mibs[] = $mib['mib']; }
	}

	/* ================= input validation ================= */
	if(!in_array(get_request_var('mib'), $registered_mibs) && get_request_var('mib') != '-1' && get_request_var('mib') != '') {
		die_html_input_error();
	}
	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'mib' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_snmpac');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_cache';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_cache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('SNMP Agent Cache'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_cache' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('MIB');?>
						</td>
						<td>
							<select id='mib' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('mib') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								if (cacti_sizeof($mibs) > 0) {
									foreach ($mibs as $mib) {
										print "<option value='" . html_escape($mib['mib']) . "'"; if (get_request_var('mib') == $mib['mib']) { print ' selected'; } print '>' . html_escape($mib['mib']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('OIDs');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('mib') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('mib')) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var('mib') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			`oid` LIKE '           . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `name` LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `mib` LIKE '        . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `max-access` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_where .= ' ORDER by `oid`';

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$nav = html_nav_bar('utilities.php?action=view_snmpagent_cache&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(__('OID'), __('Name'), __('MIB'), __('Type'), __('Max-Access'), __('Value')));

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {

			$oid        = filter_value($item['oid'], get_request_var('filter'));
			$name       = filter_value($item['name'], get_request_var('filter'));
			$mib        = filter_value($item['mib'], get_request_var('filter'));
			$max_access = filter_value($item['max-access'], get_request_var('filter'));

			form_alternate_row('line' . $item['oid'], false);
			form_selectable_cell($oid, $item['oid']);
			if($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . html_escape($item['description']) . '</div>" class="tooltip">' . $name . '</a></td>';
			}else {
				print "<td>$name</td>";
			}
			form_selectable_cell($mib, $item['oid']);
			form_selectable_cell($item['kind'], $item['oid']);
			form_selectable_cell($max_access, $item['oid']);
			form_selectable_ecell((in_array($item['kind'], array(__('Scalar'), __('Column Data'))) ? $item['value'] : __('N/A')), $item['oid']);
			form_end_row();
		}
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}

	?>
	<script type='text/javascript'>
		$('.tooltip').tooltip({
			track: true,
			show: 250,
			hide: 250,
			position: { collision: 'flipfit' },
			content: function() { return $(this).attr('title'); }
		});
	</script>
	<?php
}

function snmpagent_utilities_run_eventlog(){
	global $item_rows;

	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	$receivers = db_fetch_assoc('SELECT DISTINCT manager_id, hostname
		FROM snmpagent_notifications_log
		INNER JOIN snmpagent_managers
		ON snmpagent_managers.id = snmpagent_notifications_log.manager_id');

	/* ================= input validation ================= */
	get_filter_request_var('receiver');

	if(!in_array(get_request_var('severity'), array_keys($severity_levels)) && get_request_var('severity') != '-1' && get_request_var('severity') != '') {
		die_html_input_error();
	}
	/* ==================================================== */

	if (isset_request_var('purge')) {
		db_execute('TRUNCATE table snmpagent_notifications_log');

		/* reset filters */
		set_request_var('clear', true);
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'severity' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'receiver' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_snmpl');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_events';
		strURL += '&severity=' + $('#severity').val();
		strURL += '&receiver=' + $('#receiver').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&purge=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeFilter();
		});

		$('#form_snmpagent_notifications').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php
	html_start_box(__('SNMP Agent Notification Log'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_notifications' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Severity');?>
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var('severity') == $level) { print ' selected'; } print '>' . html_escape($name) . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Receiver');?>
						</td>
						<td>
							<select id='receiver' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('receiver') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($receivers as $receiver) {
									print "<option value='" . $receiver['manager_id'] . "'"; if (get_request_var('receiver') == $receiver['manager_id']) { print ' selected'; } print '>' . html_escape($receiver['hostname']) . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Entries');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge Notification Log');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = ' 1';

	/* filter by severity */
	if(get_request_var('receiver') != '-1') {
		$sql_where .= " AND snl.manager_id='" . get_request_var('receiver') . "'";
	}

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
	/* Show all items */
	} elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snl.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	$sql_where .= ' ORDER by `time` DESC';

	$sql_query  = "SELECT snl.*, sm.hostname, sc.description
		FROM snmpagent_notifications_log AS snl
		INNER JOIN snmpagent_managers AS sm
		ON sm.id = snl.manager_id
		LEFT JOIN snmpagent_cache AS sc
		ON sc.name = snl.notification
		WHERE $sql_where
		LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM snmpagent_notifications_log AS snl
		WHERE $sql_where");

	$logs = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('utilities.php?action=view_snmpagent_events&severity='. get_request_var('severity').'&receiver='. get_request_var('receiver').'&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Log Entries'), 'page', 'main');

	form_start('managers.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(' ', __('Time'), __('Receiver'), __('Notification'), __('Varbinds')));

	if (cacti_sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = filter_value($item['varbinds'], get_request_var('filter'));
			form_alternate_row('line' . $item['id'], false);

			print "<td title='" . __esc('Severity Level: %s', $severity_levels[$item['severity']]) . "' style='width:10px;background-color: " . $severity_colors[$item['severity']] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td class='nowrap'>" . date('Y-m-d H:i:s', $item['time']) . '</td>';
			print '<td>' . html_escape($item['hostname']) . '</td>';

			if($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . html_escape($item['notification']) . '</div><div class=\'content preformatted\'>' . html_escape($item['description']) . '</div>" class="tooltip">' . html_escape($item['notification']) . '</a></td>';
			}else {
				print '<td>' . html_escape($item['notification']) . '</td>';
			}

			print "<td>$varbinds</td>";

			form_end_row();
		}
	} else {
		print '<tr><td colspan="5"><em>' . __('No SNMP Notification Log Entries') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($logs)) {
		print $nav;
	}

	?>

	<script type='text/javascript' >
	$('.tooltip').tooltip({
		track: true,
		position: { collision: 'flipfit' },
		content: function() { return $(this).attr('title'); }
	});
	</script>
	<?php
}
