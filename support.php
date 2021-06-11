<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

support_view_tech();

/* -----------------------
    Functions
   ----------------------- */

function support_view_tech() {
	global $config, $poller_options, $input_types, $local_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary'),
		'database'   => __('Database'),
		'dbsettings' => __('Database Settings'),
		'dbstatus'   => __('Database Status'),
		'dbperms'    => __('Database Permissions'),
		'phpinfo'    => __('PHP Info'),
		'changelog'  => __('ChangeLog'),
		'poller'     => __('Poller'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'support.php?tab=' . $current_tab;

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
			print "<li class='subTab'><a class='tab pic " . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'support.php?' .
				'tab=' . $tab_short_name) .
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
		print '<td>' . number_format_i18n($host_count, -1) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Graphs') . '</td>';
		print '<td>' . number_format_i18n($graph_count, -1) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Data Sources') . '</td>';
		print '<td>';
		$data_total = 0;
		if (cacti_sizeof($data_count)) {
			foreach ($data_count as $item) {
				print $input_types[$item['type_id']] . ': ' . number_format_i18n($item['total'], -1) . '<br>';
				$data_total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($data_total, -1));
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
				print __('Action[%s]', $item['action']) . ': ' . number_format_i18n($item['total'], -1) . '<br>';
				$total += $item['total'];
			}
			print __('Total: %s', number_format_i18n($total, -1));
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
		print "  <th class='tableSubHeaderColumn'>" . __('Value')         . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . html_escape($s['Variable_name']) . '</td>';
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):html_escape($s['Value'])) . '</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'dbperms') {
		$status = db_get_permissions(true);

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Permission Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Permission Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value') . '</th>';
		print '</tr>';
		print '</thead>';

		$r = 0;
		foreach($status as $k => $v) {
			if (($r % 2) == 0) {
				form_alternate_row();
			}

			print '<td>' . $k . '</td>';
			print '<td>' . ($v ? __('Yes') : __('No')) . '</td>';

			if (($r % 2) == 1) {
				form_end_row();
			}
			$r++;
		}

		if (($r % 2) == 1) {
			print '<td>&nbsp;</td>';
			print '<td>&nbsp;</td>';
			form_end_row();
		}
	} elseif (get_request_var('tab') == 'dbsettings') {
		$status = db_fetch_assoc('show global variables');

		print "<table id='tables' class='cactiTable' style='width:100%'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
		print "  <th class='tableSubHeaderColumn'>" . __('Value')         . '</th>';
		print '</tr>';
		print '</thead>';

		foreach($status as $s) {
			form_alternate_row();
			print '<td>' . html_escape($s['Variable_name']) . '</td>';

			if (strlen($s['Value']) > 70) {
				$s['Value'] = str_replace(',', ', ', $s['Value']);
			}
			print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):html_escape($s['Value'])) . '</td>';
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
				print '<td class="right">' . number_format_i18n($table['TABLE_ROWS'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['AVG_ROW_LENGTH'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['DATA_LENGTH'], -1) . '</td>';
				print '<td class="right">' . number_format_i18n($table['INDEX_LENGTH'], -1) . '</td>';
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
	} elseif (get_request_var('tab') == 'poller') {
		$problematic = db_fetch_assoc('SELECT h.id, h.description, h.polling_time, h.avg_time
			FROM host h
			LEFT JOIN sites s
			ON s.id = h.site_id
			WHERE IFNULL(h.disabled,"") != "on"
			AND IFNULL(s.disabled,"") != "on"
			ORDER BY polling_time DESC
			LIMIT 20');

		html_section_header(__('Worst 20 polling time hosts'), 2);

		form_alternate_row();

		print "<td colspan='2' style='text-align:left;padding:0px'>";

		if (cacti_sizeof($problematic)) {
			print "<table id='tables' class='cactiTable' style='width:100%'>";
			print '<thead>';
			print "<tr class='tableHeader'>";
			print "  <th class='tableSubHeaderColumn'>"       . __('Description')         . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('ID')                  . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Avg Polling Time')    . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Actual polling time') . '</th>';
			print '</tr>';
			print '</thead>';

			foreach ($problematic as $host) {
				form_alternate_row();
				print '<td>' . html_escape($host['description']) . '</td>';
				print '<td class="right">' . $host['id'] . '</td>';
				print '<td class="right">' . number_format_i18n($host['avg_time'],3) . '</td>';
				print '<td class="right">' . number_format_i18n($host['polling_time'],3) . '</td>';
				form_end_row();
			}

			print "</table>";
		} else {
			print __('No host found');
		}

		print '</td>';

		form_end_row();

		$problematic = db_fetch_assoc('SELECT h.id, h.description, h.failed_polls/h.total_polls AS ratio
			FROM host h
			LEFT JOIN sites s
			ON h.site_id = s.id
			WHERE IFNULL(h.disabled,"") != "on"
			AND IFNULL(s.disabled,"") != "on"
			ORDER BY ratio DESC
			LIMIT 20');

		html_section_header(__('Worst 20 failed/total polls ratio'), 2);

		form_alternate_row();
		print "<td colspan='2' style='text-align:left;padding:0px'>";

		if (cacti_sizeof($problematic)) {
			print "<table id='tables' class='cactiTable' style='width:100%'>";
			print '<thead>';
			print "<tr class='tableHeader'>";
			print "  <th class='tableSubHeaderColumn'>"       . __('Description')        . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('ID')                 . '</th>';
			print "  <th class='tableSubHeaderColumn right'>" . __('Failed/Total polls') . '</th>';
			print '</tr>';
			print '</thead>';

			foreach ($problematic as $host) {
				form_alternate_row();
				print '<td>' . $host['description'] . '</td>';
				print '<td class="right">' . $host['id'] . '</td>';
				print '<td class="right">' . number_format_i18n($host['ratio'],3) . '</td>';
				form_end_row();
			}

			print "</table>";
		} else {
			print __('No host found');
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
