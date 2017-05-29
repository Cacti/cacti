<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

function clog_get_graphs_from_datasource($local_data_id) {
	return array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name
		FROM (graph_templates_graph
		INNER JOIN graph_templates_item
		ON graph_templates_graph.local_graph_id=graph_templates_item.local_graph_id)
		INNER JOIN data_template_rrd
		ON graph_templates_item.task_item_id=data_template_rrd.id
		WHERE graph_templates_graph.local_graph_id>0
		AND data_template_rrd.local_data_id = ?', array($local_data_id)), 'id', 'name');
}

function clog_purge_logfile() {
	global $config;

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			$timestamp = date('Y-m-d H:i:s');
			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, "$timestamp - WEBUI: Cacti Log Cleared from Web Management Interface\n");
			fclose($log_fh);
			raise_message('clog_purged');
		} else {
			raise_message('clog_permissions');
		}
	} else {
		raise_message('clog_missing');
	}
}

function clog_view_logfile() {
	global $config;

	//Filter filename input
	if (isset_request_var('filename')) {
		if (get_request_var('filename') !== basename(get_request_var('filename'))) {
			set_request_var('filename', '');
		}
	}

	if (isset_request_var('filename') && get_request_var('filename') !== '') {
		$logfile = realpath('./log/' . get_request_var('filename'));
	} else {
		$logfile = read_config_option('path_cactilog');

		if ($logfile == '') {
			$logfile = './log/cacti.log';
		}
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'tail_lines' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('num_rows_log')
		),
		'message_type' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('log_refresh_interval')
		),
		'reverse' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'default' => ''
		)
	);

	$clogAdmin = clog_admin();

	validate_store_request_vars($filters, 'sess_clog');
	/* ================= input validation ================= */

	/* enable page refreshes */
	kill_session_var('custom');

	set_request_var('page_referrer', 'view_logfile');
	load_current_session_value('page_referrer', 'page_referrer', 'view_logfile');

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = $config['url_path'] . 'clog' . (!$clogAdmin ? '_user' : '') . '.php?header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	if ($clogAdmin && isset_request_var('purge_continue')) {
		clog_purge_logfile();
	}

	general_header();

	if ($clogAdmin && isset_request_var('purge')) {
		form_start('clog.php');

		html_start_box(__('Purge'), '50%', '', '3', 'center', '');

		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the Cacti log file.<br><br><br>Note: If logging is set to Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' align='right'>
				<input id='cancel' type='button' value='" . __('Cancel') . "'>&nbsp
				<input id='pc' type='button' name='purge_continue' value='" . __('Continue') . "' title='" . __('Purge cacti.log') . "'>
				<script type='text/javascript'>
				$('#pc').click(function() {
					strURL = location.pathname+'?purge_continue=1&header=false';
					loadPageNoHeader(strURL);
				});

				$('#cancel').click(function() {
					strURL = location.pathname+'?header=false';
					loadPageNoHeader(strURL);
				});

				$(function() {
					applySkin();
				});
				</script>
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		return;
	}

	html_start_box(__('Log Filters'), '100%', '', '3', 'center', '');
	filter();
	html_end_box();

	/* read logfile into an array and display */
	$total_rows      = 0;
	$page_nr         = isset_request_var('page') ? get_request_var('page') : 1;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows);

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (!$clogAdmin) {
		$exclude_regex = read_config_option('clog_exclude', true);
		if ($exclude_regex != '') {
			$ad_filter = ' - Admin Filter in Affect';
		} else {
			$ad_filter = ' - No Admin Filter in Affect';
		}
	} else {
		$ad_filter = ' - Admin View';
	}

	if (get_request_var('message_type') > 0) {
		$start_string = __('Log [Total Lines: %d %s - Additional Filter in Affect]', $total_rows, $ad_filter);
	} else {
		$start_string = __('Log [Total Lines: %d %s - No Other Filter in Affect]', $total_rows, $ad_filter);
	}

	$rfilter      = get_request_var('rfilter');
	$reverse      = get_request_var('reverse');
	$refresh      = get_request_var('refresh');
	$message_type = get_request_var('message_type');
	$tail_lines   = get_request_var('tail_lines');
	$filename     = get_request_var('filename');
	$base_url     = 'clog.php?rfilter='.$rfilter.'&reverse='.$reverse.'&refresh='.$refresh.'&message_type='.$message_type.'&tail_lines='.$tail_lines.'&filename='.$filename;

	$nav          = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 13, __('Entries'), 'page');

	echo $nav;

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$linecolor = false;

	$hosts = db_fetch_assoc_prepared('SELECT id, description FROM host');
	$hostDescriptions = array();
	foreach ($hosts as $host) {
		$hostDescriptions[$host['id']] = cacti_htmlspecialchars($host['description']);
	}

	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		if (!$host_start && !$ds_start) {
			$new_item = cacti_htmlspecialchars($item);
		} else {
			$new_item = '';
			while ($host_start) {
				$host_end    = strpos($item, ']', $host_start);
				$host_id     = substr($item, $host_start + 7, $host_end - ($host_start + 7));
				$new_item   .= cacti_htmlspecialchars(substr($item, 0, $host_start + 7)) . "<a href='" . cacti_htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host_id) . "'>$host_id</a>";
				$new_item   .= '] Description[' . (isset($hostDescriptions[$host_id]) ? $hostDescriptions[$host_id] : '');
				$item        = substr($item, $host_end);
				$host_start  = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');
			while ($ds_start) {
				$ds_end    = strpos($item, ']', $ds_start);
				$ds_id     = substr($item, $ds_start + 3, $ds_end - ($ds_start + 3));
				$graph_ids = clog_get_graphs_from_datasource($ds_id);
				$graph_add = $config['url_path'] . 'graph_view.php?page=1&style=selective&action=preview&graph_add=';

				if (sizeof($graph_ids)) {
					$new_item  .= substr($item, 0, $ds_start + 3) .
						"<a href='" . cacti_htmlspecialchars($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . cacti_htmlspecialchars(substr($item, $ds_start + 3, $ds_end-($ds_start + 3))) . '</a>' .
						"] Graphs[<a href='";

					$i = 0;
					$titles = '';
					foreach($graph_ids as $key => $title) {
						$graph_add .= ($i > 0 ? '%2C' : '') . $key;
						$i++;
						if ($titles != '') {
							$titles .= ",'" . cacti_htmlspecialchars($title) . "'";
						} else {
							$titles .= "'"  . cacti_htmlspecialchars($title) . "'";
						}
					}
					$new_item .= cacti_htmlspecialchars($graph_add) . "' title='" . __('View Graphs') . "'>" . $titles . '</a>';
				}

				$item     = substr($item, $ds_end);
				$ds_start = strpos($item, 'DS[');
			}

			$new_item .= cacti_htmlspecialchars($item);
		}

		/* respect the exclusion filter */
		if (!$clogAdmin && @preg_match($exclude_regex, $new_item)) {
			continue;
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

		?>
		<tr class='<?php print $class;?>'>
			<td>
				<?php print $new_item;?>
			</td>
		</tr>
		<?php
	}

	html_end_box();

	if ($total_rows) {
		echo $nav;
	}

	bottom_footer();
}

function filter() {
	global $page_refresh_interval, $log_tail_lines;
	?>
	<tr class='even'>
		<td>
		<form id='logfile'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('File to show');?>
					</td>
					<td>
						<select id='filename' name='filename'>
							<?php
							$logPath = dirname(read_config_option('path_cactilog'));
							$files = scandir($logPath);

							foreach($files as $logFile) {
								if (in_array($logFile, array('.', '..', '.htaccess'))) {
									continue;
								}
								print "<option value='" . $logFile . "'";
								if (get_request_var('filename') == $logFile) {
									print ' selected';
								}
								print '>' . $logFile . "</option>\n";
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Tail Lines');?>
					</td>
					<td>
						<select id='tail_lines' name='tail_lines'>
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'";
								if (get_request_var('tail_lines') == $tail_lines) {
									print ' selected';
								}
								print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='go' name='go' value='<?php print __('Go');?>'>
					</td>
					<td>
						<input type='button' id='clear' name='clear' value='<?php print __('Clear');?>'>
					</td>
					<td>
						<?php if (clog_admin()) {?><input type='button' id='purge' name='purge' value='<?php print __('Purge');?>'><?php }?>
					</td>
				</tr>
				<tr>
					<td class='nowrap'>
						<?php print __('Message Type');?>
					</td>
					<td>
						<select id='message_type' name='message_type'>
							<option value='-1'<?php if (get_request_var('message_type') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('message_type') == '1') {?> selected<?php }?>><?php print __('Stats');?></option>
							<option value='2'<?php if (get_request_var('message_type') == '2') {?> selected<?php }?>><?php print __('Warnings');?></option>
							<option value='3'<?php if (get_request_var('message_type') == '3') {?> selected<?php }?>><?php print __('Errors');?></option>
							<option value='4'<?php if (get_request_var('message_type') == '4') {?> selected<?php }?>><?php print __('Debug');?></option>
							<option value='5'<?php if (get_request_var('message_type') == '5') {?> selected<?php }?>><?php print __('SQL Calls');?></option>
						</select>
					</td>
					<td class='nowrap'>
						<?php print __('Display Order');?>
					</td>
					<td>
						<select id='reverse' name='reverse'>
							<option value='1'<?php if (get_request_var('reverse') == '1') {?> selected<?php }?>><?php print __('Newest First');?></option>
							<option value='2'<?php if (get_request_var('reverse') == '2') {?> selected<?php }?>><?php print __('Oldest First');?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' name='refresh'>
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'";
								if (get_request_var('refresh') == $seconds) {
									print ' selected';
								}
								print '>' . $display_text . "</option>\n";
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
						<input id='rfilter' type='text' size='75' value='<?php print get_request_var('rfilter');?>'>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		$('#rfilter').change(function() {
			refreshFilter();
		});

		$('#reverse').change(function() {
			refreshFilter();
		});

		$('#refresh').change(function() {
			refreshFilter();
		});

		$('#message_type').change(function() {
			refreshFilter();
		});

		$('#filename').change(function() {
			refreshFilter();
		});

		$('#tail_lines').change(function() {
			refreshFilter();
		});

		$('#go').click(function() {
			refreshFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			strURL = basename(location.pathname) + '?purge=1&header=false';
			loadPageNoHeader(strURL);
		});

		$('#logfile').submit(function(event) {
			event.preventDefault();
			refreshFilter();
		});

		function clearFilter() {
			strURL = basename(location.pathname) + '?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		function refreshFilter() {
			refreshMSeconds=$('#refresh').val()*1000;

			strURL = basename(location.pathname) + '?rfilter='+ $('#rfilter').val()+
				'&reverse='+$('#reverse').val()+
				'&refresh='+$('#refresh').val()+
				'&message_type='+$('#message_type').val()+
				'&tail_lines='+$('#tail_lines').val()+
				'&filename='+$('#filename').val()+
				'&header=false';

			loadPageNoHeader(strURL);
		}
		</script>
		</td>
	</tr>
	<?php
}
