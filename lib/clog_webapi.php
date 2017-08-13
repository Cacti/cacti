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
	return array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT
		gtg.local_graph_id AS id,
		gtg.title_cache AS name
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_templates_item AS gti
		ON gtg.local_graph_id=gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		WHERE gtg.local_graph_id>0
		AND dtr.local_data_id = ?',
		array($local_data_id)), 'id', 'name');
}

function clog_purge_logfile() {
	global $config;

	$logfile   = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	$purgefile = dirname($logfile) . '/' . get_nfilter_request_var('filename');
	if (strstr($purgefile, $logfile) === false) {
		raise_message('clog_invalid');
		exit(0);
	}

	if (file_exists($purgefile)) {
		if (is_writable($purgefile)) {
			if ($logfile != $purgefile) {
				unlink($purgefile);
				raise_message('clog_remove');
			} else {
				$timestamp = date('Y-m-d H:i:s');
				$log_fh = fopen($logfile, 'w');
				fwrite($log_fh, "$timestamp - WEBUI: Cacti Log Cleared from Web Management Interface\n");
				fclose($log_fh);
				raise_message('clog_purged');
			}

			cacti_log('NOTE: Cacti Log file ' . $purgefile . ', Removed by user ' . get_username($_SESSION['sess_user_id']), false, 'WEBUI');
		} else {
			raise_message('clog_permissions');
		}
	} else {
		raise_message('clog_missing');
	}
}

function clog_view_logfile() {
	global $config;

	$clogAdmin = clog_admin();
	$logfile   = read_config_option('path_cactilog');

	if (isset_request_var('filename')) {
		$requestedFile = dirname($logfile) . '/' . basename(get_nfilter_request_var('filename'));
		if (file_exists($requestedFile)) {
			$logfile = $requestedFile;
		}
	} elseif ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
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

	validate_store_request_vars($filters, 'sess_clog');
	/* ================= input validation ================= */

	/* enable page refreshes */
	kill_session_var('custom');

	set_request_var('page_referrer', 'view_logfile');
	load_current_session_value('page_referrer', 'page_referrer', 'view_logfile');

	if ($clogAdmin && isset_request_var('purge_continue')) {
		clog_purge_logfile();
		$logfile   = read_config_option('path_cactilog');
	}

	$page_nr = get_request_var('page');

	$page = $config['url_path'] . 'clog' . (!$clogAdmin ? '_user' : '') . '.php?header=false';
	$page .= '&filename=' . basename($logfile) . '&page=' . $page_nr;

	$refresh = array(
		'seconds' => get_request_var('refresh'),
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	general_header();

	if ($clogAdmin && isset_request_var('purge')) {
		form_start('clog.php');

		html_start_box(__('Purge'), '50%', '', '3', 'center', '');

		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the Log File.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' align='right'>
				<input id='cancel' type='button' value='" . __esc('Cancel') . "'>&nbsp
				<input id='pc' type='button' name='purge_continue' value='" . __esc('Continue') . "' title='" . __esc('Purge Log') . "'>
				<script type='text/javascript'>
				$('#pc').click(function() {
					strURL = location.pathname+'?purge_continue=1&header=false&filename=" . basename($logfile) . "';
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

		return;
	}

	html_start_box(__('Log Filters'), '100%', '', '3', 'center', '');
	filter($clogAdmin);
	html_end_box();

	/* read logfile into an array and display */
	$total_rows      = 0;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows);

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (!$clogAdmin) {
		$exclude_regex = read_config_option('clog_exclude', true);
		if ($exclude_regex != '') {
			$ad_filter = __(' - Admin Filter in Affect');
		} else {
			$ad_filter = __(' - No Admin Filter in Affect');
		}
	} else {
		$ad_filter = __(' - Admin View');
	}

	if (get_request_var('message_type') > 0) {
		$start_string = __('Log [Total Lines: %d %s - Additional Filter in Affect]', $total_rows, $ad_filter);
	} else {
		$start_string = __('Log [Total Lines: %d %s - No Other Filter in Affect]', $total_rows, $ad_filter);
	}

	$rfilter      = get_request_var('rfilter');
	$reverse      = get_request_var('reverse');
	$refreshTime  = get_request_var('refresh');
	$message_type = get_request_var('message_type');
	$tail_lines   = get_request_var('tail_lines');
	$base_url     = 'clog.php?rfilter='.$rfilter.'&reverse='.$reverse.'&refresh='.$refreshTime.'&message_type='.$message_type.'&tail_lines='.$tail_lines.'&filename='.basename($logfile);

	$nav = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 13, __('Entries'), 'page', 'main');

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
			if ($host_start) {
				$host_end    = strpos($item, ']', $host_start);
				$host_id     = substr($item, $host_start + 7, $host_end - ($host_start + 7));
				$new_item   .= substr($item, 0, $host_start) . " Device[<a href='" . cacti_htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host_id) . "'>" . (isset($hostDescriptions[$host_id]) ? $hostDescriptions[$host_id]:'') . '</a>]';
				$item        = substr($item, $host_end + 1);
			}

			$ds_start   = strpos($item, 'DS[');
			if ($ds_start) {
				$ds_end    = strpos($item, ']', $ds_start);
				$ds_id     = substr($item, $ds_start + 3, $ds_end - ($ds_start + 3));
				$ds_ids    = explode(', ', $ds_id);
				if (sizeof($ds_ids)) {
					$graph_add = $config['url_path'] . 'graph_view.php?page=1&style=selective&action=preview&graph_add=';

					$new_item  .= " Graphs[<a href='";
					$titles = '';
					$i = 0;
					foreach($ds_ids as $ds_id) {
						$graph_ids = clog_get_graphs_from_datasource($ds_id);

						if (sizeof($graph_ids)) {
							foreach($graph_ids as $key => $title) {
								$graph_add .= ($i > 0 ? '%2C' : '') . $key;
								if ($titles != '') {
									$titles .= ", '" . cacti_htmlspecialchars($title) . "'";
								} else {
									$titles .= "'"  . cacti_htmlspecialchars($title) . "'";
								}
								$i++;
							}
						}
					}

					$new_item .= cacti_htmlspecialchars($graph_add) . "' title='" . __esc('View Graphs') . "'>" . $titles . '</a>]';

					$new_item .= ' DS[';
					$i = 0;
					foreach($ds_ids as $ds_id) {
						$new_item .= ($i == 0 ? '':', ') . "<a href='" . cacti_htmlspecialchars($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . $ds_id . '</a>';
						$i++;
					}
					$new_item .= ']';
				}
			}else{
				$new_item .= cacti_htmlspecialchars($item);
			}
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

function filter($clogAdmin) {
	global $page_refresh_interval, $log_tail_lines, $config;
	?>
	<tr class='even'>
		<td>
		<form id='logfile'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('File');?>
					</td>
					<td>
						<?php
						$configLogPath = read_config_option('path_cactilog');

						if ($configLogPath == '') {
							$logPath = $config['base_path'] . '/log/';
						} else {
							$logPath = dirname($configLogPath);
						}

						if (is_readable($logPath)) {
							$files = scandir($logPath);
						} else {
							$files = false;
						}

						if ($files === false) {
							echo '<select id="filename" name="filename">
									<option value="cacti.log">cacti.log</option>';
						} else {
							echo '<select id="filename" name="filename">';
							$selectedFile = basename(get_nfilter_request_var('filename'));

							foreach ($files as $logFile) {
								if (in_array($logFile, array('.', '..', '.htaccess'))) {
									continue;
								}

								$explode = explode('.', $logFile);
								if (substr($explode[max(array_keys($explode))], 0, 3) != 'log') {
									continue;
								}

								print "<option value='" . $logFile . "'";
								if ($selectedFile == $logFile) {
									print ' selected';
								}
								print '>' . $logFile . "</option>\n";
							}
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
						<span>
							<input type='button' id='go' name='go' value='<?php print __esc('Go');?>'>
							<input type='button' id='clear' name='clear' value='<?php print __esc('Clear');?>'>
						<?php if ($clogAdmin) {?><input type='button' id='purge' name='purge' value='<?php print __esc('Purge');?>'><?php }?>
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
						<select id='message_type' name='message_type'>
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
						<select id='reverse' name='reverse'>
							<option value='1'<?php if (get_request_var('reverse') == '1') {?> selected<?php }?>><?php print __('Newest First');?></option>
							<option value='2'<?php if (get_request_var('reverse') == '2') {?> selected<?php }?>><?php print __('Oldest First');?></option>
						</select>
					</td>
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
						<input id='rfilter' type='text' size='75' value='<?php print html_escape_request_var('rfilter');?>'>
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
			strURL = basename(location.pathname) + '?purge=1&header=false&filename=' + $('#filename').val();
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
