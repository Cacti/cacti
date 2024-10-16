<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function support_view_tech() {
	global $database_hostname, $poller_options, $input_types, $local_db_cnn_id;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary'),
		'database'   => __('Database Tables'),
		'dbsettings' => __('Database Settings'),
		'dbstatus'   => __('Database Status'),
		'dbperms'    => __('Database Permissions'),
		'processes'  => __('Database Queries'),
		'background' => __('Background Processes'),
		'poller'     => __('Poller Stats'),
		'phpinfo'    => __('PHP Info'),
		'changelog'  => __('ChangeLog'),
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$page = 'support.php?tab=' . $current_tab;

	if ($current_tab != 'processes') {
		$refresh = array(
			'seconds' => 999999,
			'page'    => $page,
			'logout'  => 'false'
		);
	} else {
        $refresh = array(
			'seconds' => get_filter_request_var('refresh'),
			'page'    => $page,
			'logout'  => 'false'
		);
	}

	set_page_refresh($refresh);

	$header_label = __esc('Technical Support [%s]', $tabs[get_request_var('tab')]);

	top_header();

	if (cacti_sizeof($tabs)) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab pic " . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape(CACTI_PATH_URL .
				'support.php?' .
				'tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . '</a></li>';

			$i++;
		}

		api_plugin_hook('utilities_tab');

		print '</ul></nav></div>';
	}

	/* Display tech information */
	html_start_box($header_label, '100%', '', '3', 'center', '');

	switch (get_request_var('tab')) {
		case 'summary':
			show_tech_summary();
			break;
		case 'dbstatus':
			show_database_status();
			break;
		case 'dbperms':
			show_database_permissions();
			break;
		case 'dbsettings':
			show_database_settings();
			break;
		case 'changelog':
			show_cacti_changelog();
			break;
		case 'database':
			show_database_tables();
			break;
		case 'poller':
			show_cacti_poller();
			break;
		case 'phpinfo':
			show_php_modules();
			break;
		case 'processes':
			show_database_processes();
			break;
		case 'background':
			show_cacti_processes();
			break;
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

function show_database_processes() {
	global $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '5'
		),
		'poller' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
		),
		'length' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '180'
		),
		'tasks' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
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
			'default' => 'runtime',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_ts_processes');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<tr class='odd'>
		<td>
		<form name='form_db_stats'>
			<script type='text/javascript'>
			$(function() {
				refreshMSeconds=$('#refresh').val()*1000;
				refreshPage='support.php?tab=processes';

				$('#refresh, #poller, #length, #rows').change(function() {
					applyFilter();
				});

				$('#refreshbtn').click(function() {
					applyFilter();
				});
			});

			function applyFilter() {
				refreshMSeconds=$('#refresh').val()*1000;
				refreshPage='support.php?tab=processes';

				var strURL  = 'support.php';
				strURL += '?tab=processes';
				strURL += '&refresh=' + $('#refresh').val();
				strURL += '&poller=' + $('#poller').val();
				strURL += '&length=' + $('#length').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&page=' + $('#page').val();
				loadUrl({url: strURL});
			}
			</script>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh'>
							<?php
							$refresh_intervals = array(
								3  => __esc('%d Seconds', 3),
								5  => __esc('%d Seconds', 5),
								10 => __esc('%d Seconds', 10),
								15 => __esc('%d Seconds', 15),
								20 => __esc('%d Seconds', 20)
							);

							foreach ($refresh_intervals as $key => $interval) {
								print '<option value="' . $key . '"' . (get_filter_request_var('refresh') == $key ? ' selected':'') . '>' . $interval . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Include Poller');?>
					</td>
					<td>
						<select id='poller'>
							<?php
							print '<option value="0"' . (get_filter_request_var('poller') == '0' ? ' selected':'') . '>' . __('No') . '</option>';
							print '<option value="1"' . (get_filter_request_var('poller') == '1' ? ' selected':'') . '>' . __('Yes') . '</option>';
							?>
						</select>
					</td>
					<td>
						<?php print __('Info Length');?>
					</td>
					<td>
						<select id='length'>
							<?php
							$chars = array(
								150  => __esc('%d Chars', 150),
								180  => __esc('%d Chars', 180),
								300  => __esc('%d Chars', 300),
								500  => __esc('%d Chars', 500),
								1000 => __esc('%d Chars', 1000),
							);

							foreach ($chars as $key => $interval) {
								print '<option value="' . $key . '"' . (get_filter_request_var('length') == $key ? ' selected':'') . '>' . $interval . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Queries');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"' . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='refreshbtn' value='<?php print __esc('Refresh');?>' title='<?php print __esc('Refresh Values');?>'>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = 'WHERE info NOT LIKE "%FROM processlist%" AND info != "NULL"';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(command LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR info LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('poller') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'info NOT LIKE "%poller_output%" AND ' .
			'info NOT LIKE "%poller_item%" AND info NOT LIKE "%SQL_NO_CACHE%"';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM information_schema.processlist
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$info_len  = get_request_var('length');

	$processes = db_fetch_assoc("SELECT id, query_id, user, state, ROUND(time_ms/1000,2) AS runtime, LENGTH(info) AS query_len,
		SUBSTRING(REPLACE(REPLACE(REPLACE(info, '\n', ' '), ',', ', '), '\t', ' '), 1, $info_len) AS info
		FROM information_schema.processlist
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('support.php?tab=processes', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Queries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'id' => array(
			'display' => __('Process ID'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Connection ID of the running process.')
		),
		'query_id' => array(
			'display' => __('Query ID'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Query ID of the currently running Query.')
		),
		'user' => array(
			'display' => __('User'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The MariaDB/MySQL user currently running the Query.')
		),
		'runtime' => array(
			'display' => __('Run Time'),
			'align'   => 'right',
			'tip'     => __('The Runtime of the current query in seconds.')
		),
		'query_len' => array(
			'display' => __('Query Length'),
			'align'   => 'right',
			'tip'     => __('The total string length of the Query.')
		),
		'state' => array(
			'display' => __('Query State'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The MariaDB/MySQL process state.')
		),
		'info' => array(
			'display' => __('Query Details'),
			'align'   => 'left',
			'tip'     => __('The Query Details for the current query upto a maximum string length.')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'support.php?tab=processes', 'main');

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			form_alternate_row('line' . $p['id'], false);

			form_selectable_cell($p['id'], $p['id']);
			form_selectable_cell($p['query_id'], $p['id']);
			form_selectable_cell($p['user'], $p['id']);
			form_selectable_cell(number_format_i18n($p['runtime'], 2), $p['id'], '', 'right');
			form_selectable_cell(number_format_i18n($p['query_len']), $p['id'], '', 'right');
			form_selectable_cell($p['state'], $p['id'], '', 'center');
			form_selectable_cell($p['info'], $p['id'], '', 'white-space:pre-wrap');

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Database Queries Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($processes)) {
		print $nav;
	}
}

function show_cacti_processes() {
	global $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '5'
		),
		'tasks' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
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
			'default' => 'runtime',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_ts_processes');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* the full set of process tables known to Cacti */
	$tables = array(
		'poller_time'                   => __('Cacti Poller'),          // Core Cacti poller table
		'processes'                     => __('Cacti Process'),         // Cacti process table
		'grid_processes'                => __('RTM Process'),           // RTM process table
		'automation_processes'          => __('Automation Process'),    // Automation process table
		'plugin_hmib_processes'         => __('HMIB Process'),          // HMIB process table
		'plugin_microtik_processes'     => __('MikroTik Process'),      // Mikrotik process table
		'plugin_webseer_processes'      => __('WebSeer Process'),       // WebSeer process table
		'plugin_servcheck_processes'    => __('Service Check Process'), // Service Check process table
		'mac_track_processes'           => __('MacTrack Process'),      // WebSeer process table
	);

	/* reduce the set of tables based if they exist */
	foreach($tables as $table => $name) {
		if (!db_table_exists($table)) {
			unset($tables[$table]);
		}
	}

	?>
	<tr class='odd'>
		<td>
		<form name='form_processes'>
			<script type='text/javascript'>
			$(function() {
				refreshMSeconds=$('#refresh').val()*1000;
				refreshPage='support.php?tab=background';

				$('#refresh, #rows, #tasks').change(function() {
					applyFilter();
				});

				$('#refreshbtn').click(function() {
					applyFilter();
				});
			});

			function applyFilter() {
				refreshMSeconds=$('#refresh').val()*1000;
				refreshPage='support.php?tab=background';

				var strURL  = 'support.php?tab=background';
				strURL += '&refresh=' + $('#refresh').val();
				strURL += '&tasks='   + $('#tasks').val();
				strURL += '&rows='    + $('#rows').val();
				loadUrl({url: strURL});
			}
			</script>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Task Type');?>
					</td>
					<td>
						<select id='tasks'>
							<option value='-1'<?php print (get_request_var('tasks') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php

							foreach ($tables as $table => $name) {
								print '<option value="' . $table . '"' . (get_request_var('tasks') == $table ? ' selected':'') . '>' . $name . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh'>
							<?php
							$refresh_intervals = array(
								3  => __esc('%d Seconds', 3),
								5  => __esc('%d Seconds', 5),
								10 => __esc('%d Seconds', 10),
								15 => __esc('%d Seconds', 15),
								20 => __esc('%d Seconds', 20)
							);

							foreach ($refresh_intervals as $key => $interval) {
								print '<option value="' . $key . '"' . (get_filter_request_var('refresh') == $key ? ' selected':'') . '>' . $interval . '</option>';
							}
							?>
					</td>
					<td>
						<?php print __('Processes');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>

							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"' . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='refreshbtn' value='<?php print __esc('Refresh');?>' title='<?php print __esc('Refresh Values');?>'>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$poller_interval = read_config_option('poller_interval');

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (taskname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR tasktype LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('tasks') != '-1') {
		$tables = array(get_request_var('tasks'));
	}

	$total_rows_sql = 'SELECT COUNT(*) FROM (';
	$sql_inner      = '';

	foreach($tables as $table => $name) {
		switch($table) {
			case 'poller_time':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '" . __('Cacti Poller') . "' AS tasktype,
						CONCAT('PollerID:', poller_id) AS taskname,
						id AS taskid, '$poller_interval' AS timeout,
						start_time AS started,
						start_time AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(start_time) AS runtime
						FROM poller_time WHERE end_time = '0000-00-00'";

				break;
			case 'processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, CONCAT('$name (', tasktype, ')') AS tasktype,
						taskname, taskid, timeout,
						started, last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started) AS runtime
						FROM processes";

				break;
			case 'grid_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
						taskname, taskid, 'N/A' AS timeout,
						'-' AS started, heartbeat AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_updated) AS runtime
						FROM grid_processes";

				break;
			case 'automation_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
						CONCAT('" . __('Poller:') . "', an.poller_id) AS taskname,
						network_id AS taskid, 'N/A' AS timeout, an.last_started AS started, ap.heartbeat AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(an.last_started) AS runtime
						FROM automation_processes AS ap INNER JOIN automation_networks AS an ON an.id = ap.network_id";

				break;
			case 'mac_track_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT process_id AS pid, '$name' AS tasktype,
						CONCAT('" . __('Device:') . "', device_id) AS taskname, device_id AS taskid, 'N/A' AS timeout,
						start_date AS started, 'N/A' AS last_updated,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(start_date) AS runtime
						FROM mac_track_processes";

				break;
			case 'plugin_hmib_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
						'" . __('Collector') . "' AS taskname, taskid, 'N/A' AS timeout,
						started, 'N/A' AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started) AS runtime
						FROM plugin_hmib_processes";

				break;
			case 'plugin_microtik_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
					'" . __('Collector') . "' AS taskname, taskid, 'N/A' AS timeout,
					started, 'N/A' AS last_update,
					UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started) AS runtime
					FROM plugin_mikrotik_processes";

				break;
			case 'plugin_webseer_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
						CONCAT('" . __('Poller:') . "', poller_id) AS taskname, url_id AS taskid, 'N/A' AS timeout,
						time AS started, 'N/A' AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(time) AS runtime
						FROM plugin_webseer_processes";

				break;
			case 'plugin_servcheck_processes':
				$sql_inner .= ($sql_inner != '' ? ' UNION ':'') .
					"SELECT pid, '$name' AS tasktype,
						CONCAT('" . __('Poller:') . "', poller_id) AS taskname, test_id AS taskid, 'N/A' AS timeout,
						time AS started, 'N/A' AS last_update,
						UNIX_TIMESTAMP() - UNIX_TIMESTAMP(time) AS runtime
						FROM plugin_servcheck_processes";

				break;
		}
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM (
			$sql_inner
		) AS rs
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$processes = db_fetch_assoc("SELECT *
		FROM (
			$sql_inner
		) AS rs
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('support.php?tab=background', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Processes'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'tasktype' => array(
			'display' => __('Task Type'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Type of Task.  Generally represents the plugin and task within the plugin.')
		),
		'taskname' => array(
			'display' => __('Task Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of the Task.')
		),
		'taskid' => array(
			'display' => __('Task ID'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The ID of the Task which is often times the LSF clusterid or the License Service ID, but can be other metrics as well.')
		),
		'runtime' => array(
			'display' => __('Run/Update Time'),
			'align'   => 'right',
			'tip'     => __('The Process runtime or times since last heartbeat.')
		),
		'pid' => array(
			'display' => __('Process ID'),
			'align'   => 'right',
			'tip'     => __('The Process ID for the task.')
		),
		'timeout' => array(
			'display' => __('Timeout'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The background process timeout in seconds when a Cacti process.  Otherwise controlled by the individual plugins.')
		),
		'started' => array(
			'display' => __('Start Time'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Time the background process started for a Cacti process.  The start time may or may not be supported for various plugins.')
		),
		'last_update' => array(
			'display' => __('Last Updated'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Time the process last registered its status to the status tables.')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'support.php?tab=background', 'main');

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			form_alternate_row('line' . $p['pid'], false);

			form_selectable_cell($p['tasktype'], $p['pid']);
			form_selectable_cell(filter_value(strtoupper($p['taskname']), ''), $p['pid']);
			form_selectable_cell($p['taskid'], $p['pid'], '', 'right');
			form_selectable_cell($p['runtime'], $p['pid'], '', 'right');
			form_selectable_cell($p['pid'], $p['pid'], '', 'right');
			form_selectable_cell($p['timeout'], $p['pid'], '', 'right');
			form_selectable_cell($p['started'], $p['pid'], '', 'right');
			form_selectable_cell($p['last_update'], $p['pid'], '', 'right');

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Cacti or Plugin Background Processes Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($processes)) {
		print $nav;
	}
}

function show_php_modules() {
	$php_info = utilities_php_modules();

	html_section_header(__('PHP Module Information'), 2);
	$php_info = str_replace(
		array('width="600"', 'th colspan="2"', ','),
		array('', 'th class="subHeaderColumn"', ', '),
		$php_info
	);
	print "<tr><td colspan='2'>" . $php_info . '</td></tr>';
}

function show_cacti_poller() {
	if (db_column_exists('sites', 'disabled')) {
		$sql_where = 'AND IFNULL(s.disabled, "") != "on"';
	} else {
		$sql_where = '';
	}

	$problematic = db_fetch_assoc("SELECT h.id, h.description, h.polling_time, h.avg_time
		FROM host h
		LEFT JOIN sites s
		ON s.id = h.site_id
		WHERE IFNULL(h.disabled, '') != 'on'
		$sql_where
		ORDER BY polling_time DESC
		LIMIT 20");

	html_section_header(__('Worst 20 polling time hosts'), 2);

	form_alternate_row();

	print "<td colspan='2' class='left'>";

	if (cacti_sizeof($problematic)) {
		print "<table id='tables' class='cactiTable'>";
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

		print '</table>';
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
	print "<td colspan='2' class='left'>";

	if (cacti_sizeof($problematic)) {
		print "<table id='tables' class='cactiTable'>";
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

		print '</table>';
	} else {
		print __('No host found');
	}

	print '</td>';

	form_end_row();
}

function show_database_tables() {
	/* Get table status */
	if (POLLER_ID == 1) {
		$tables = db_fetch_assoc('SELECT *
			FROM information_schema.tables
			WHERE table_schema = SCHEMA()');
	} else {
		$tables = db_fetch_assoc('SELECT *
			FROM information_schema.tables
			WHERE table_schema = SCHEMA()', false, $local_db_cnn_id);
	}

	form_alternate_row();

	print "		<td colspan='2' class='left'>";

	if (cacti_sizeof($tables)) {
		print "<table id='tables' class='cactiTable'>";
		print '<thead>';
		print "<tr class='tableHeader'>";
		print "  <th class='tableSubHeaderColumn'>"       . __('Name')           . '</th>';
		print "  <th class='tableSubHeaderColumn'>"       . __('Engine')         . '</th>';
		print "  <th class='tableSubHeaderColumn right'>" . __('Rows')           . '</th>';
		print "  <th class='tableSubHeaderColumn right'>" . __('Avg Row Length') . '</th>';
		print "  <th class='tableSubHeaderColumn right'>" . __('Data Length')    . '</th>';
		print "  <th class='tableSubHeaderColumn right'>" . __('Index Length')   . '</th>';
		print "  <th class='tableSubHeaderColumn'>"       . __('Collation')      . '</th>';
		print "  <th class='tableSubHeaderColumn'>"       . __('Row Format')     . '</th>';
		print "  <th class='tableSubHeaderColumn'>"       . __('Comment')        . '</th>';
		print '</tr>';
		print '</thead>';

		foreach ($tables as $table) {
			form_alternate_row();
			print '<td>' . $table['TABLE_NAME'] . '</td>';
			print '<td>' . $table['ENGINE'] . '</td>';
			print '<td class="right">' . number_format_i18n($table['TABLE_ROWS'], -1)     . '</td>';
			print '<td class="right">' . number_format_i18n($table['AVG_ROW_LENGTH'], -1) . '</td>';
			print '<td class="right">' . number_format_i18n($table['DATA_LENGTH'], -1)    . '</td>';
			print '<td class="right">' . number_format_i18n($table['INDEX_LENGTH'], -1)   . '</td>';
			print '<td>' . $table['TABLE_COLLATION'] . '</td>';
			print '<td>' . $table['ROW_FORMAT']      . '</td>';
			print '<td>' . $table['TABLE_COMMENT']   . '</td>';
			form_end_row();
		}

		print '</table>';
	} else {
		print __('Unable to retrieve table status');
	}

	print '</td>';

	form_end_row();
}

function show_cacti_changelog() {
	$changelog = file(CACTI_PATH_BASE . '/CHANGELOG');

	foreach ($changelog as $s) {
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
}

function show_database_settings() {
	$status = db_fetch_assoc('show global variables');

	print "<table id='tables' class='cactiTable'>";
	print '<thead>';
	print "<tr class='tableHeader'>";
	print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
	print "  <th class='tableSubHeaderColumn'>" . __('Value')         . '</th>';
	print '</tr>';
	print '</thead>';

	foreach ($status as $s) {
		form_alternate_row();
		print '<td>' . html_escape($s['Variable_name']) . '</td>';

		if (strlen($s['Value']) > 70) {
			$s['Value'] = str_replace(',', ', ', $s['Value']);
		}

		print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):html_escape($s['Value'])) . '</td>';
		form_end_row();
	}
}

function show_database_permissions() {
	$status = db_get_permissions(true);

	print "<table id='tables' class='cactiTable'>";
	print '<thead>';
	print "<tr class='tableHeader'>";
	print "  <th class='tableSubHeaderColumn'>" . __('Permission Name') . '</th>';
	print "  <th class='tableSubHeaderColumn'>" . __('Value')           . '</th>';
	print "  <th class='tableSubHeaderColumn'>" . __('Permission Name') . '</th>';
	print "  <th class='tableSubHeaderColumn'>" . __('Value')           . '</th>';
	print '</tr>';
	print '</thead>';

	$r = 0;

	foreach ($status as $k => $v) {
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
}

function show_database_status() {
	$status = db_fetch_assoc('show global status');

	print "<table id='tables' class='cactiTable'>";
	print '<thead>';
	print "<tr class='tableHeader'>";
	print "  <th class='tableSubHeaderColumn'>" . __('Variable Name') . '</th>';
	print "  <th class='tableSubHeaderColumn'>" . __('Value')         . '</th>';
	print '</tr>';
	print '</thead>';

	foreach ($status as $s) {
		form_alternate_row();
		print '<td>' . html_escape($s['Variable_name']) . '</td>';
		print '<td>' . (is_numeric($s['Value']) ? number_format_i18n($s['Value'], -1):html_escape($s['Value'])) . '</td>';
		form_end_row();
	}
}

function show_tech_summary() {
	global $config, $database_hostname, $poller_options, $input_types, $local_db_cnn_id;

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
		AND local_data_id > 0
		GROUP BY i.type_id');

	/* Get RRDtool version */
	$rrdtool_version  = __('Unknown');
	$rrdtool_release  = __('Unknown');
	$storage_location = read_config_option('storage_location');

	$out_array = array();

	if ($storage_location == 0) {
		if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {
			exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);
		}
	} else {
		$rrdtool_pipe = rrd_init();
		$out_array[]  = rrdtool_execute('info', false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'WEBLOG');
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
		$snmp_version = "<span class='deviceDown'>" . __('NET-SNMP Not Installed or its paths are not set.  Please install if you wish to monitor SNMP enabled devices.') . '</span>';
	}

	/* Check RRDtool issues */
	$rrdtool_errors = array();

	if (cacti_version_compare($rrdtool_version, get_rrdtool_version(), '<')) {
		$rrdtool_errors[] = "<span class='deviceDown'>" . __('ERROR: Installed RRDtool version does not exceed configured version.<br>Please visit the %s and select the correct RRDtool Utility Version.', "<a href='" . html_escape('settings.php?tab=general') . "'>" . __('Configuration Settings') . '</a>') . '</span>';
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
	print '<td>' . CACTI_SERVER_OS . '</td>';
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
	print '<td>' . get_rrdtool_version() . '+</td>';
	form_end_row();

	form_alternate_row();
	print '<td>' . __('RRDtool Version') . ' ' . __('Found') . '</td>';
	print '<td>' . $rrdtool_release . '</td>';
	form_end_row();

	if (!empty($rrdtool_errors)) {
		form_alternate_row();
		print '<td>&nbsp;</td>';
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
		print "<span class='deviceDown'>" . __('No items to poll') . '</span>';
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

	if (POLLER_ID == 1) {
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
		if (POLLER_ID == 1) {
			$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
		} elseif ($config['connection'] == 'online') {
			$db_connections = '<span class="deviceDown">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
		} else {
			$db_connections = '';
		}
	} else {
		if (POLLER_ID == 1) {
			$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
		} elseif ($config['connection'] == 'online') {
			$db_connections = '<span class="deviceUp">' . __('Main Server: Current: %s, Min Required: %s', $max_connections, $recommend_mc) . '</span>';
		} else {
			$db_connections = '';
		}
	}

	if (POLLER_ID > 1) {
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

		foreach ($memInfo as $name => $value) {
			if (CACTI_SERVER_OS == 'win32') {
				form_alternate_row();
				print "<td>$name</td>";
				print '<td>' . number_format_i18n($value / 1000, 2) . ' MB</td>';
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
						print '<td>' . __('%s GB', number_format_i18n($value, 2, 1000)) . '</td>';
						form_end_row();

						if ($name == 'MemTotal') {
							$total_memory = $value;
						}
				}
			}
		}

		form_end_row();
	}

	$mysql_info = utilities_get_mysql_info(POLLER_ID);

	$database  = $mysql_info['database'];
	$version   = $mysql_info['version'];
	$link_ver  = $mysql_info['link_ver'];
	$variables = $mysql_info['variables'];
	$myhost    = php_uname('n');
	$dbhost    = $database_hostname;

	if ($dbhost == 'localhost' || $myhost == $dbhost) {
		$local_db = true;
	} else {
		$local_db = false;
	}

	// Get Maximum Memory in GB for MySQL/MariaDB
	if (POLLER_ID == 1) {
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

	if ($total_memory > 0 && $local_db) {
		if ($maxPossibleMyMemory > ($total_memory * 0.8)) {
			form_alternate_row();
			print '<td>' . __('Max Total Memory Possible') . '</td>';
			print '<td class="deviceDown">' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
			form_end_row();
			form_alternate_row();
			print '<td></td>';
			print '<td>' . __('Reduce MySQL/MariaDB Memory to less than 80% of System Memory.  Preserve additional Cache Memory for RRDfiles if the Database is on the same system as the RRDfiles.  See Core and Client Totals below for explanation of calculation method.') . '</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Total Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td>' . __('Max Total Memory Possible') . '</td>';
		print '<td>' . __('%s GB', number_format_i18n($maxPossibleMyMemory, 2, 1000)) . '</td>';
		form_end_row();
	}

	if ($total_memory > 0 && $local_db) {
		if ($systemMemory > ($total_memory * 0.8)) {
			form_alternate_row();
			print '<td>' . __('Max Core Memory Possible') . '</td>';
			print '<td class="deviceDown">' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Core Memory') . '</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Core Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		form_alternate_row();
		print '<td>' . __('Calculation Formula') . '</td>';
		print '<td>SELECT @@GLOBAL.key_buffer_size + <br>@@GLOBAL.query_cache_size + <br>@@GLOBAL.tmp_table_size + <br>@@GLOBAL.innodb_buffer_pool_size + <br>@@GLOBAL.innodb_log_buffer_size</td>';
		form_end_row();

		if ($clientMemory > ($total_memory * 0.8)) {
			form_alternate_row();
			print '<td>' . __('Max Connection Memory Possible') . '</td>';
			print '<td class="deviceDown">' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '&nbsp;&nbsp;(' . __('Reduce Total Client Memory') . ')</td>';
			form_end_row();
		} else {
			form_alternate_row();
			print '<td>' . __('Max Connection Memory Possible') . '</td>';
			print '<td class="deviceUp">' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
			form_end_row();
		}

		form_alternate_row();
		print '<td>' . __('Calculation Formula') . '</td>';
		print '<td>SELECT @@GLOBAL.max_connections * (<br>@@GLOBAL.sort_buffer_size + <br>@@GLOBAL.read_buffer_size + <br>@@GLOBAL.read_rnd_buffer_size + <br>@@GLOBAL.join_buffer_size + <br>@@GLOBAL.thread_stack + <br>@@GLOBAL.binlog_cache_size)</td>';
		form_end_row();
	} else {
		form_alternate_row();
		print '<td>' . __('Max Core Memory Possible') . '</td>';
		print '<td>' . __('%s GB', number_format_i18n($systemMemory, 2, 1000)) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Calculation Formula') . '</td>';
		print '<td>SELECT @@GLOBAL.key_buffer_size + <br>@@GLOBAL.query_cache_size + <br>@@GLOBAL.tmp_table_size + <br>@@GLOBAL.innodb_buffer_pool_size + <br>@@GLOBAL.innodb_log_buffer_size</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Max Connection Memory Possible') . '</td>';
		print '<td>' . __('%s GB', number_format_i18n($clientMemory, 2, 1000)) . '</td>';
		form_end_row();

		form_alternate_row();
		print '<td>' . __('Calculation Formula') . '</td>';
		print '<td>SELECT @@GLOBAL.max_connections * (<br>@@GLOBAL.sort_buffer_size + <br>@@GLOBAL.read_buffer_size + <br>@@GLOBAL.read_rnd_buffer_size + <br>@@GLOBAL.join_buffer_size + <br>@@GLOBAL.thread_stack + <br>@@GLOBAL.binlog_cache_size)</td>';
		form_end_row();
	}

	html_section_header(__('PHP Information'), 2);

	form_alternate_row();
	print '<td>' . __('PHP Version') . '</td>';

	if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
		print '<td>' . PHP_VERSION . '</td>';
	} else {
		print '<td>' . PHP_VERSION . "</br><span class='deviceDown'>" . __('PHP Version 5.5.0+ is recommended due to strong password hashing support.') . '</span></td>';
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
	print '<td>max_execution_time</td>';
	print '<td>' . ini_get('max_execution_time') . '</td>';
	form_end_row();

	form_alternate_row();
	print '<td>memory_limit</td>';
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
			print __("You've set memory limit to 'unlimited'.") . '<br>';
		}

		print __('It is highly suggested that you alter you php.ini memory_limit to %s or higher.', memory_readable($memory_suggestion)) . ' <br/>' .
			__('This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.');

		print '</span><br>';
	}

	print '</td>';

	form_end_row();

	utilities_get_mysql_recommendations();
}
