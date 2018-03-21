<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

function clog_get_datasource_titles($local_data_ids) {
	if (!is_array($local_data_ids)) {
		$local_data_ids = array($local_data_ids);
	}

	$titles = array();
	foreach ($local_data_ids as $local_data_id) {
		if (!array_key_exists($local_data_id, $titles)) {
			$titles[$local_data_id] = get_data_source_title($local_data_id);
		}
	}

	return $titles;
}

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

	$logfile = read_config_option('path_cactilog');
	$logbase = basename($logfile);
	if ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	if (get_nfilter_request_var('filename') != '') {
		if (strpos(get_nfilter_request_var('filename'), $logbase) === false) {
			raise_message('clog_invalid');
			header('Location: ' . get_current_page() . '?filename=' . $logbase);
			exit(0);
		}
	}

	$purgefile = dirname($logfile) . '/' . get_nfilter_request_var('filename');
	if (strstr($purgefile, $logfile) === false) {
		raise_message('clog_invalid');
		header('Location: ' . get_current_page() . '?header=false');
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

	$exclude_reported = false;

	$clogAdmin = clog_admin();
	$logfile   = read_config_option('path_cactilog');
	$logbase   = basename($logfile);

	if (is_base64_encoded(get_nfilter_request_var('rfilter'))) {
		set_request_var('rfilter', base64_decode(get_nfilter_request_var('rfilter')));
	}

	if (isset_request_var('filename')) {
		$requestedFile = dirname($logfile) . '/' . basename(get_nfilter_request_var('filename'));
		if (file_exists($requestedFile)) {
			$logfile = $requestedFile;
		}
	} elseif ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	if (get_nfilter_request_var('filename') != '') {
		if (strpos(get_nfilter_request_var('filename'), $logbase) === false) {
			raise_message('clog_invalid');
			header('Location: ' . get_current_page() . '?filename=' . $logbase);
			exit(0);
		}
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'tail_lines' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_config_option('num_rows_log'),
			'pageset' => true
		),
		'message_type' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
		),
		'refresh' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_config_option('log_refresh_interval')
		),
		'reverse' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'default' => '',
			'pageset' => true
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
		$exclude_regex = '';
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

	print $nav;

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$linecolor = false;

	$hosts = db_fetch_assoc_prepared('SELECT id, description FROM host');
	$hostDescriptions = array();
	foreach ($hosts as $host) {
		$hostDescriptions[$host['id']] = html_escape($host['description']);
	}

	$regex_array = clog_get_regex_array();
	foreach ($logcontents as $item) {
		$new_item = html_escape($item);

		$new_item = preg_replace_callback($regex_array['complete'],'clog_regex_parser',$new_item);

		/* respect the exclusion filter */
		if ($exclude_regex != '' && !$clogAdmin) {
			if (validate_is_regex($exclude_regex)) {
				if (preg_match($exclude_regex, $new_item)) {
					continue;
				}
			} elseif (!$exclude_reported) {
				cacti_log('Cacti Log Exclude Regex "' . $exclude_regex . '" is Invalid.  Update your Exclude Regex to be valid!');
				$exclude_reported = true;
			}
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

	html_end_box(false);

	if ($total_rows) {
		print $nav;
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
						<select id='filename'>
						<?php
						$configLogPath = read_config_option('path_cactilog');
						$configLogBase = basename($configLogPath);
						$selectedFile  = basename(get_nfilter_request_var('filename'));

						if ($configLogPath == '') {
							$logPath = $config['base_path'] . '/log/';
						} else {
							$logPath = dirname($configLogPath);
						}

						if (is_readable($logPath)) {
							$files = scandir($logPath);
						} else {
							$files = array('cacti.log');
						}

						if (sizeof($files)) {
							$logFileArray = array();
							foreach ($files as $logFile) {
								if (in_array($logFile, array('.', '..', '.htaccess'))) {
									continue;
								}

								$explode = explode('.', $logFile);
								if (substr($explode[max(array_keys($explode))], 0, 3) != 'log') {
									continue;
								}

								if (strpos($logFile, $configLogBase) === false) {
									continue;
								}

								if (strcmp($logFile, 'cacti.log') == 0) {
									continue;
								}
								$logFileArray[] = $logFile;
							}

							arsort($logFileArray);
							array_unshift($logFileArray,'cacti.log');

							foreach ($logFileArray as $logFile) {
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

			strURL = basename(location.pathname)+
				'?rfilter=' + base64_encode($('#rfilter').val())+
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

function clog_get_regex_array() {
	static $regex_array = array();

	if (!sizeof($regex_array)) {
		$regex_array = array(
			1 => array('name' => 'DS',     'regex' => '( DS\[)([, \d]+)(\])',      'func' => 'clog_regex_datasource'),
			2 => array('name' => 'DQ',     'regex' => '( DQ\[)([, \d]+)(\])',      'func' => 'clog_regex_dataquery'),
			3 => array('name' => 'Device', 'regex' => '( Device\[)([, \d]+)(\])',  'func' => 'clog_regex_device'),
			4 => array('name' => 'Poller', 'regex' => '( Poller\[)([, \d]+)(\])',  'func' => 'clog_regex_poller'),
			5 => array('name' => 'RRA',    'regex' => "([_\/])(\d+)(\.rrd&#039;)", 'func' => 'clog_regex_rra')
		);

		$regex_array = api_plugin_hook_function('clog_regex_array',$regex_array);
		$regex_complete = '';
		foreach ($regex_array as $regex_key => $regex_setting) {
			$regex_complete .= (strlen($regex_complete)?')|(':'').$regex_setting['regex'];
		}
		$regex_complete = '~('.$regex_complete.')~';
		$regex_array['complete'] = $regex_complete;
	}

	return $regex_array;
}

function clog_regex_parser($matches) {
	$result = $matches[0];
	$match = $matches[0];

	$key_match = -1;
	for ($index = 1; $index < sizeof($matches); $index++) {
		if ($match == $matches[$index]) {
			$key_match = $index;
		}
	}

	if ($key_match != -1) {
		$key_setting = ($key_match - 1) / 4 + 1;
		$regex_array = clog_get_regex_array();
		if (sizeof($regex_array)) {
			if (array_key_exists($key_setting, $regex_array)) {
				$regex_setting = $regex_array[$key_setting];

				$rekey_array = array();
				for ($j = 0; $j < 4; $j++) {
					$rekey_array[$j] = $matches[$key_match + $j];
				}

				$result=call_user_func_array($regex_setting['func'],array($rekey_array));
			}
		}
	}

	return $result;
}

function clog_regex_device($matches) {
	global $config;

	$result = $matches[0];

	$dev_ids = explode(',',str_replace(" ","",$matches[2]));
	if (sizeof($dev_ids)) {
		$hosts = db_fetch_assoc_prepared('SELECT id, description
			FROM host
			WHERE id in (?)',
			array(implode(',',$dev_ids)));

		$hostDescriptions = array();
		foreach ($hosts as $host) {
			$hostDescriptions[$host['id']] = html_escape($host['description']);
		}

		foreach ($dev_ids as $host_id) {
			$result .= $matches[1].'<a href=\'' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host_id) . '\'>' . (isset($hostDescriptions[$host_id]) ? $hostDescriptions[$host_id]:$host_id) . '</a>' . $matches[3];
		}
	}

	return $result;
}

function clog_regex_datasource($matches) {
	global $config;

	$result = $matches[0];

	$ds_ids = explode(',',str_replace(" ","",$matches[2]));
	if (sizeof($ds_ids)) {
		$result = '';
		$graph_add = $config['url_path'] . 'graph_view.php?page=1&style=selective&action=preview&graph_add=';

		$title = '';
		$i     = 0;

		foreach($ds_ids as $ds_id) {
			$graph_ids = clog_get_graphs_from_datasource($ds_id);

			if (sizeof($graph_ids)) {
				$result .= " Graphs[<a href='";

				foreach($graph_ids as $key => $title) {
					$graph_add .= ($i > 0 ? '%2C' : '') . $key;
					$title     .= ($title != '' ? ', ':'') . html_escape($title);

					$i++;
				}

				$result .= html_escape($graph_add) . "' title='" . __esc('View Graphs') . "'>" . $title . '</a>]';
			}
		}

		$result .= $matches[1];
		$i       = 0;

		$ds_titles = clog_get_datasource_titles($ds_ids);
		foreach($ds_ids as $ds_id) {
			$ds_title = $ds_id;
			if (array_key_exists($ds_id, $ds_titles)) {
				$ds_title = $ds_titles[$ds_id];
			}
			$result .= ($i == 0 ? '':', ') . "<a href='" . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . $ds_title . '</a>';

			$i++;
		}

		$result .= $matches[3];
	}

	return $result;
}

function clog_regex_poller($matches) {
	global $config;

	$result = $matches[0];

	$poller_ids = explode(',',str_replace(" ","",$matches[2]));
	if (sizeof($poller_ids)) {
		$result = '';
		$pollers = db_fetch_assoc_prepared('SELECT id, name
			FROM poller
			WHERE id in (?)',
			array(implode(',',$poller_ids)));

		$pollerDescriptions = array();
		foreach ($pollers as $poller) {
			$pollerDescriptions[$poller['id']] = html_escape($poller['name']);
		}

		foreach ($poller_ids as $poller_id) {
			$result .= $matches[1].'<a href=\'' . html_escape($config['url_path'] . 'pollers.php?action=edit&id=' . $poller_id) . '\'>' . (isset($pollerDescriptions[$poller_id]) ? $pollerDescriptions[$poller_id]:$poller_id) . '</a>' . $matches[3];
		}
	}

	return $result;
}

function clog_regex_dataquery($matches) {
	global $config;

	$result = $matches[0];

	$query_ids = explode(',',str_replace(" ","",$matches[2]));
	if (sizeof($query_ids)) {
		$result = '';
		$querys = db_fetch_assoc_prepared('SELECT id, name
			FROM snmp_query
			WHERE id in (?)',
			array(implode(',',$query_ids)));

		$queryDescriptions = array();
		foreach ($querys as $query) {
			$queryDescriptions[$query['id']] = html_escape($query['name']);
		}

		foreach ($query_ids as $query_id) {
			$result .= $matches[1].'<a href=\'' . html_escape($config['url_path'] . 'data_query.php?action=edit&id=' . $query_id) . '\'>' . (isset($queryDescriptions[$query_id]) ? $queryDescriptions[$query_id]:$query_id) . '</a>' . $matches[3];
		}
	}

	return $result;
}

function clog_regex_rra($matches) {
	global $config;

	$result = $matches[0];

	$local_data_ids = $matches[2];
	if (strlen($local_data_ids)) {
		$datasource_array = array( 0 => '', 1 => 'DS[', 2 => $local_data_ids, 3 => ']');
		$datasource_result = clog_regex_datasource($datasource_array);
		if (strlen($datasource_result)) {
			$result .= ' '. $datasource_result;
		}
	}

	return $result;
}

