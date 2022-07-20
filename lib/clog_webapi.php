<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

function clog_validate_filename(&$file, &$filepath, &$filename, $filecheck = false) {
	global $config;

	$logfile = read_config_option('path_cactilog');
	if ($logfile == '') {
		$logfile = $config['base_path'] . '/log/cacti.log';
	}

	$errfile  = read_config_option('path_stderrlog');
	$errbase  = basename($errfile);

	$file     = basename($file);
	$logbase  = basename($logfile);

	$filepath = '';
	$filename = '';
	$filefull = '';

	if (!empty($errfile) && strpos($file, $errbase) === 0) {
		$filepath = dirname($errfile);
		$filename = $errbase;
		$filefull = $filepath . '/' . $file;
	} elseif (!empty($logfile) && strpos($file, $logbase) === 0) {
		$filepath = dirname($logfile);
		$filename = $logbase;
		$filefull = $filepath . '/' . $file;
	}

	return ($filecheck ? file_exists($filefull) : !empty($filefull));
}

function clog_purge_logfile() {
	global $config;

	$filename = get_nfilter_request_var('filename');

	if (!clog_validate_filename($filename, $logpath, $logname)) {
		raise_message('clog_invalid');
		header('Location: ' . get_current_page());
		exit(0);
	}

	$purgefile = $logpath . '/' . $filename;
	$logfile = $logpath . '/'. $logname;

	if (file_exists($purgefile)) {
		if (is_writable($purgefile)) {
			if ($logfile != $purgefile) {
				unlink($purgefile);
				raise_message('clog_remove');
			} else {
				/* fill in the current date for printing in the log */
				if (defined('CACTI_DATE_TIME_FORMAT')) {
					$date = date(CACTI_DATE_TIME_FORMAT);
				} else {
					$date = date('Y-m-d H:i:s');
				}

				$log_fh = fopen($logfile, 'w');
				fwrite($log_fh, __('%s - WEBUI NOTE: Cacti Log Cleared from Web Management Interface.', $date) . PHP_EOL);
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
		'filename' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => read_config_option('path_cactilog'),
			'pageset' => true,
			'options' => array('options' => 'sanitize_search_string')
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

	$logfile = basename(get_nfilter_request_var('filename'));
	$logname = '';

	if (!clog_validate_filename($logfile, $logpath, $logname, true)) {
		$logfile = read_config_option('path_cactilog');
	} else {
		$logfile = $logpath . '/' . $logfile;
	}

	if ($clogAdmin && isset_request_var('purge_continue')) {
		clog_purge_logfile();
		$logfile = read_config_option('path_cactilog');
	}

	$page_nr = get_request_var('page');

	$page = $config['url_path'] . 'clog' . (!$clogAdmin ? '_user' : '') . '.php';
	$page .= '?filename=' . basename($logfile) . '&page=' . $page_nr;

	$refresh = array(
		'seconds' => get_request_var('refresh'),
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	top_header(true);

	if ($clogAdmin && isset_request_var('purge')) {
		form_start('clog.php');

		html_start_box(__('Purge'), '50%', '', '3', 'center', '');

		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the Log File.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' class='right'>
				<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='" . __esc('Cancel') . "'>&nbsp
				<input type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' value='" . __esc('Continue') . "' title='" . __esc('Purge Log') . "'>
				<script type='text/javascript'>
				$('#pc').click(function() {
					strURL = location.pathname+'?purge_continue=1&filename=" . basename($logfile) . "';
					loadUrl({url:strURL})
				});

				$('#cancel').click(function() {
					strURL = location.pathname;
					loadUrl({url:strURL})
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
	filter($clogAdmin, basename($logfile));
	html_end_box();

	/* read logfile into an array and display */
	$total_rows      = 0;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');
	$should_expand   = read_config_option('log_expand') != LOG_EXPAND_NONE;

	if ($should_expand) {
		$regex_array = clog_get_regex_array();
		$regex_map   = array('data' => $regex_array['complete'], 'func' => 'clog_regex_parser');
	} else {
		$regex_map   = false;
	}

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows, $regex_map);

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (!$clogAdmin) {
		$exclude_regex = read_config_option('clog_exclude', true);
		if ($exclude_regex != '') {
			$ad_filter = __(' - Admin Filter active');
		} else {
			$ad_filter = __(' - Admin Unfiltered');
		}
	} else {
		$ad_filter = __(' - Admin view');
		$exclude_regex = '';
	}

	if (get_request_var('message_type') > 0 || get_request_var('rfilter') != '') {
		$start_string = __('Log [Total Lines: %d %s - Filter active]', $total_rows, $ad_filter);
	} else {
		$start_string = __('Log [Total Lines: %d %s - Unfiltered]', $total_rows, $ad_filter);
	}

	$rfilter      = get_request_var('rfilter');
	$reverse      = get_request_var('reverse');
	$refreshTime  = get_request_var('refresh');
	$message_type = get_request_var('message_type');
	$tail_lines   = get_request_var('tail_lines');
	$base_url     = $config['url_path'] . 'clog.php';

	$nav = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 1, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$linecolor = false;

	if (db_column_exists('sites', 'disabled')) {
		$sql_where = 'AND IFNULL(s.disabled,"") != "on"';
	} else {
		$sql_where = '';
	}

	$hosts = db_fetch_assoc("SELECT h.id, h.description
		FROM host h
		LEFT JOIN sites s
		ON s.id = h.site_id
		WHERE IFNULL(TRIM(h.disabled), '') != 'on'
		$sql_where
		AND deleted = ''");

	$hostDescriptions = array();
	foreach ($hosts as $host) {
		$hostDescriptions[$host['id']] = html_escape($host['description']);
	}

	foreach ($logcontents as $item) {
		$new_item = html_escape($item);
		if ($should_expand) {
			$new_item = preg_replace_callback($regex_map['data'],'clog_regex_parser_html',$new_item);
		}

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

function filter_sort($a, $b) {
	$a_parts = explode('-', $a);
	$b_parts = explode('-', $b);

	$a_date = '99999999';
	if (cacti_count($a_parts) > 1) {
		$a_date = $a_parts[1];
	}

	$b_date = '99999999';
	if (cacti_count($b_parts) > 1) {
		$b_date = $b_parts[1];
	}

	// Invert the order, replace _'s with +'s to make them sort after .'s, prefix the date
	// This makes cacti_stderr.log appear after cacti.log in date descending order with
	// no date files first
	return strcmp($b_date . '-' . str_replace('_','+',$b_parts[0]), $a_date . '-' . str_replace('_','+',$a_parts[0]));
}

function clog_get_logfiles() {
	global $config;

	$stdFileArray = $stdLogFileArray = $stdErrFileArray = array();
	$configLogPath = read_config_option('path_cactilog');
	$configLogBase = basename($configLogPath);
	$stderrLogPath = read_config_option('path_stderrlog');
	$stderrLogBase = basename($stderrLogPath);

	if ($configLogPath == '') {
		$logPath = $config['base_path'] . '/log/';
	} else {
		$logPath = dirname($configLogPath);
	}

	if (is_readable($logPath)) {
		$files = @scandir($logPath);
	} else {
		$files = array('cacti.log');
	}

	// Defaults go first and second
	$stdFileArray[] = basename($configLogPath);

	// After Defaults, do Cacti log first (of archived)
	if (cacti_sizeof($files)) {
		$stdLogFileArray = array();
		foreach ($files as $logFile) {
			if (in_array($logFile, array('.', '..', '.htaccess', $configLogBase, $stderrLogBase))) {
				continue;
			}

			$explode = explode('.', $logFile);
			if (substr($explode[max(array_keys($explode))], 0, 3) != 'log') {
				continue;
			}

			if (!clog_validate_filename($logFile, $logPath, $logName)) {
				continue;
			}

			if (!empty($stderrlogbase) && strpos($logFile, $stderrLogBase) === 0){
				$stdErrFileArray[] = $logFile;
			} else {
				$stdLogFileArray[] = $logFile;
			}
		}

		$stdErrFileArray = array_unique($stdErrFileArray);
		$stdLogFileArray = array_unique($stdLogFileArray);
	}

	// Defaults go first and second
	if (!empty($stderrLogPath)) {
		$stdFileArray[] = basename($stderrLogPath);

		// After Defaults, do Cacti StdErr log second (of archived)
		if (dirname($stderrLogPath) != $logPath) {
			$errFiles = @scandir(dirname($stderrLogPath));
			$files = $errFiles;
			if (cacti_sizeof($files)) {
				$stdErrFileArray = array();
				foreach ($files as $logFile) {
					if (in_array($logFile, array('.', '..', '.htaccess', $configLogBase, $stderrLogBase))) {
						continue;
					}

					$explode = explode('.', $logFile);
					if (substr($explode[max(array_keys($explode))], 0, 3) != 'log') {
						continue;
					}

					if (!clog_validate_filename($logFile, $logPath, $logName)) {
						continue;
					}

					$stdErrFileArray[] = $logFile;
				}

				$stdErrFileArray = array_unique($stdErrFileArray);
			}
		}
	}

	arsort($stdLogFileArray, SORT_NATURAL);
	arsort($stdErrFileArray, SORT_NATURAL);

	return array_unique(array_merge($stdFileArray, $stdLogFileArray, $stdErrFileArray));
}

function filter($clogAdmin, $selectedFile) {
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
						$logFileArray = clog_get_logfiles();

						if (cacti_sizeof($logFileArray)) {
							foreach ($logFileArray as $logFile) {
								print "<option value='" . $logFile . "'";

								if ($selectedFile == $logFile) {
									print ' selected';
								}

								$logParts = explode('-', $logFile);

								$logDate = cacti_count($logParts) < 2 ? '' : $logParts[1] . (isset($logParts[2]) ? '-' . $logParts[2]:'');
								$logName = $logParts[0];

								print '>' . $logName . ($logDate != '' ? ' [' . substr($logDate,4) . ']':'') . "</option>\n";
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
							<input type='button' class='ui-button ui-corner-all ui-widget' id='go' name='go' value='<?php print __esc('Go');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' name='clear' value='<?php print __esc('Clear');?>'>
						<?php if ($clogAdmin) {?><input type='button' class='ui-button ui-corner-all ui-widget' id='purge' name='purge' value='<?php print __esc('Purge');?>'><?php }?>
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
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='75' value='<?php print html_escape_request_var('rfilter');?>'>
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
			strURL = basename(location.pathname) + '?purge=1&filename=' + $('#filename').val();
			loadUrl({url:strURL})
		});

		$('#logfile').submit(function(event) {
			event.preventDefault();
			refreshFilter();
		});

		function clearFilter() {
			strURL = basename(location.pathname) + '?clear=1&nostate=true';
			loadUrl({url:strURL})
		}

		function refreshFilter() {
			refreshMSeconds=$('#refresh').val()*1000;

			strURL = basename(location.pathname)+
				'?rfilter=' + base64_encode($('#rfilter').val())+
				'&reverse='+$('#reverse').val()+
				'&refresh='+$('#refresh').val()+
				'&message_type='+$('#message_type').val()+
				'&tail_lines='+$('#tail_lines').val()+
				'&filename='+$('#filename').val();

			loadUrl({url:strURL})
		}
		</script>
		</td>
	</tr>
	<?php
}

function clog_get_regex_array() {
	static $regex_array = array();

	if (!cacti_sizeof($regex_array)) {
		$regex_array = array(
			1  => array('name' => 'DS',     'regex' => '( DS\[)([, \d]+)(\])',       'func' => 'clog_regex_datasource'),
			2  => array('name' => 'DQ',     'regex' => '( DQ\[)([, \d]+)(\])',       'func' => 'clog_regex_dataquery'),
			3  => array('name' => 'Device', 'regex' => '( Device\[)([, \d]+)(\])',   'func' => 'clog_regex_device'),
			4  => array('name' => 'Poller', 'regex' => '( Poller\[)([, \d]+)(\])',   'func' => 'clog_regex_poller'),
			5  => array('name' => 'RRA',    'regex' => "([_\/])(\d+)(\.rrd&#039;)",  'func' => 'clog_regex_rra'),
			6  => array('name' => 'GT',     'regex' => '( GT\[)([, \d]+)(\])',       'func' => 'clog_regex_graphtemplates'),
			7  => array('name' => 'Graph',  'regex' => '( Graph\[)([, \d]+)(\])',    'func' => 'clog_regex_graphs'),
			8  => array('name' => 'Graphs', 'regex' => '( Graphs\[)([, \d]+)(\])',   'func' => 'clog_regex_graphs'),
			9  => array('name' => 'User',   'regex' => '( User\[)([, \d]+)(\])',     'func' => 'clog_regex_users'),
			10 => array('name' => 'User',   'regex' => '( Users\[)([, \d]+)(\])',    'func' => 'clog_regex_users'),
			11 => array('name' => 'Rule',   'regex' => '( Rule\[)([, \d]+)(\])',   	 'func' => 'clog_regex_rule'),
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

function clog_regex_parser_html($matches) {
	return clog_regex_parser($matches, true);
}

function clog_regex_parser($matches, $link = false) {
	$result = $matches[0];
	$match = $matches[0];

	$key_match = -1;
	for ($index = 1; $index < cacti_sizeof($matches); $index++) {
		if ($match == $matches[$index]) {
			$key_match = $index;
			break;
		}
	}

	if ($key_match != -1) {
		$key_setting = ($key_match - 1) / 4 + 1;
		$regex_array = clog_get_regex_array();

		if (cacti_sizeof($regex_array)) {
			if (array_key_exists($key_setting, $regex_array)) {
				$regex_setting = $regex_array[$key_setting];

				$rekey_array = array();
				for ($j = 0; $j < 4; $j++) {
					$rekey_array[$j] = $matches[$key_match + $j];
				}

				if (function_exists($regex_setting['func'])) {
					$result=call_user_func_array($regex_setting['func'],array($rekey_array, $link));
				} else {
					$result=$match;
				}
			}
		}
	}

	return $result;
}

function clog_regex_device($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$dev_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($dev_ids)) {
		$result     = '';
		$wanted_ids = array();
		$dev_ids    = array_filter(array_unique($dev_ids));

		foreach($dev_ids as $dev_id) {
			if (!array_key_exists($dev_id, $cache)) {
				$wanted_ids []= $dev_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$hosts = db_fetch_assoc('SELECT id, description
				FROM host
				WHERE id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					$cache[$host['id']] = html_escape($host['description']);
				}
			}
		}

		foreach ($dev_ids as $host_id) {
			$result .= $matches[1];
			if ($link) {
				$result .= '<a href=\'' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host_id) . '\'>';
			}
			$result .= isset($cache[$host_id]) ? $cache[$host_id]:$host_id;
			if ($link) {
				$result .= '</a>';
			}
			$result .= $matches[3];
		}
	}

	return $result;
}

function clog_regex_datasource($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$ds_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($ds_ids)) {
		$result     = '';
		$wanted_ids = array();
		$ds_ids     = array_filter(array_unique($ds_ids));

		foreach($ds_ids as $ds_id) {
			if (!array_key_exists($ds_id, $cache)) {
				$wanted_ids []= $ds_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$graph_rows = array_rekey(db_fetch_assoc('SELECT DISTINCT
				dtr.local_data_id AS id,
				group_concat(distinct gtg.local_graph_id) AS graph_ids
				FROM graph_templates_graph AS gtg
				INNER JOIN graph_templates_item AS gti
				ON gtg.local_graph_id=gti.local_graph_id
				INNER JOIN data_template_rrd AS dtr
				ON gti.task_item_id=dtr.id
				WHERE gtg.local_graph_id>0
				AND dtr.local_data_id IN (' . implode(',',$wanted_ids) . ')
				GROUP BY dtr.local_data_id'),'id','graph_ids');

			$ds_ids    = array_filter(array_unique($ds_ids));
			$ds_titles = clog_get_datasource_titles($ds_ids);
			if (!isset($ds_titles)) {
				$ds_titles = array();
			}

			if (!cacti_sizeof($graph_rows)) {
				$graph_rows = array();
			}

			foreach($wanted_ids as $ds_id) {
				$ds_title = $ds_id;
				if (array_key_exists($ds_id, $ds_titles)) {
					$ds_title = $ds_titles[$ds_id];
				}

				if (!array_key_exists($ds_id, $cache)) {
					$cache[$ds_id] = array('graphs' => '');
				}

				$cache[$ds_id]['name'] = $ds_title;
				if (array_key_exists($ds_id, $graph_rows)) {
					$cache[$ds_id]['graphs'] = $graph_rows[$ds_id];
				}
			}
		}

		$i       = 0;


		$graph_ids = '';

		$result .= $matches[1];
		$first   = true;

		foreach ($ds_ids as $ds_id) {
			$result .= ($first ? '':', ');
			if ($link) {
				$result .= "<a href='" . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $ds_id) . "'>";
			}
			$result .= isset($cache[$ds_id]['name']) ? $cache[$ds_id]['name'] : $ds_id;
			if ($link) {
				$result .= '</a>';
			}

			if (!empty($cache[$ds_id]['graphs'])) {
				$graph_ids = (empty($graph_ids) ? '' : ',') . $cache[$ds_id]['graphs'];
			}
			$first = false;
		}

		$result .= $matches[3];
		if (!empty($graph_ids)) {
			$graph_array = array( 0 => '', 1 => ' Graphs[', 2 => $graph_ids, 3 => ']');
			$result .= clog_regex_graphs($graph_array, $link);
		}
	}

	return $result;
}

function clog_regex_poller($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$poller_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($poller_ids)) {
		$result     = '';
		$wanted_ids = array();
		$poller_ids = array_filter(array_unique($poller_ids));

		foreach($poller_ids as $poller_id) {
			if (!array_key_exists($poller_id, $cache)) {
				$wanted_ids []= $poller_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$pollers = db_fetch_assoc_prepared('SELECT id, name
				FROM poller
				WHERE id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($pollers)) {
				foreach ($pollers as $poller) {
					$cache[$poller['id']] = html_escape($poller['name']);
				}
			}
		}

		foreach ($poller_ids as $poller_id) {
			$result .= $matches[1];
			if ($link) {
				$result .= '<a href=\'' . html_escape($config['url_path'] . 'pollers.php?action=edit&id=' . $poller_id) . '\'>';
			}
			$result .= isset($cache[$poller_id]) ? $cache[$poller_id]:$poller_id;
			if ($link) {
				$result .= '</a>';
			}
			$result .= $matches[3];
		}
	}

	return $result;
}

function clog_regex_dataquery($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$query_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($query_ids)) {
		$result     = '';
		$wanted_ids = array();
		$query_ids  = array_filter(array_unique($query_ids));

		foreach($query_ids as $query_id) {
			if (!array_key_exists($query_id, $cache)) {
				$wanted_ids []= $query_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$querys = db_fetch_assoc('SELECT id, name
				FROM snmp_query
				WHERE id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($querys)) {
				foreach ($querys as $query) {
					$cache[$query['id']] = html_escape($query['name']);
				}
			}
		}

		foreach ($query_ids as $query_id) {
			$result .= $matches[1];
			if ($link) {
				$result .= '<a href=\'' . html_escape($config['url_path'] . 'data_queries.php?action=edit&id=' . $query_id) . '\'>';
			}
			$result .= isset($cache[$query_id]) ? $cache[$query_id]:$query_id;
			if ($link) {
				$result .= '</a>';
			}
			$result .= $matches[3];
		}
	}

	return $result;
}

function clog_regex_rra($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$local_data_ids = $matches[2];
	if (strlen($local_data_ids)) {
		$datasource_array = array( 0 => '', 1 => ' DS[', 2 => $local_data_ids, 3 => ']');
		$datasource_result = clog_regex_datasource($datasource_array, $link);
		if (strlen($datasource_result)) {
			$result .= ' '. $datasource_result;
		}
	}

	return $result;
}

function clog_regex_graphs($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$query_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($query_ids)) {
		$result     = $matches[1];
		$title      = '';
		$query_ids  = array_filter(array_unique($query_ids));
		$wanted_ids = array();
		$graph_add  = $config['url_path'] . 'graph_view.php?page=1&style=selective&action=preview&graph_add=';

		foreach ($query_ids as $query_id) {
			if (!array_key_exists($query_id, $cache)) {
				$wanted_ids [] = $query_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$querys = db_fetch_assoc('SELECT DISTINCT
				gtg.local_graph_id AS id,
				gtg.title_cache AS title
				FROM graph_templates_graph AS gtg
				INNER JOIN graph_templates_item AS gti
				ON gtg.local_graph_id=gti.local_graph_id
				INNER JOIN data_template_rrd AS dtr
				ON gti.task_item_id=dtr.id
				WHERE gtg.local_graph_id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($querys)) {
				foreach ($querys as $query) {
					$cache[$query['id']] = html_escape($query['title']);
				}
			}
		}

		$titles = array();
		foreach ($query_ids as $query_id) {
			$titles [] = isset($cache[$query_id]) ? $cache[$query_id]:$query_id;
		}

		if ($link) {
			$result .= "<a href='" . $graph_add . implode('%2C', $query_ids) ."'>";
		}
		$result .= implode(', ',$titles);
		if ($link) {
			$result .= '</a>';
		}

		$result .= $matches[3];
	}
	return $result;
}

function clog_regex_graphtemplates($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$graphtemplate_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($graphtemplate_ids)) {
		$result             = '';
		$wanted_ids         = array();
		$graphtemplate_ids  = array_filter(array_unique($graphtemplate_ids));

		foreach ($graphtemplate_ids as $graphtemplate_id) {
			if (!array_key_exists($graphtemplate_id, $cache)) {
				$wanted_ids []= $graphtemplate_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$querys = db_fetch_assoc('SELECT id, name
				FROM graph_templates
				WHERE id in ('  . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($querys)) {
				foreach ($querys as $query) {
					$cache[$query['id']] = html_escape($query['name']);
				}
			}
		}

		foreach ($graphtemplate_ids as $graphtemplate_id) {
			$result .= $matches[1];
			if ($link) {
				$result .= '<a href=\'' . html_escape($config['url_path'] . 'graph_templates.php?action=template_edit&id=' . $graphtemplate_id) . '\'>';
			}
			$result .= isset($cache[$graphtemplate_id]) ? $cache[$graphtemplate_id]:$graphtemplate_id;
			if ($link) {
				$result .= '</a>';
			}
			$result .= $matches[3];
		}
	}

	return $result;
}

function clog_regex_users($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$user_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($query_ids)) {
		$result     = '';
		$wanted_ids = array();
		$user_ids   = array_filter(array_unique($user_ids));

		foreach ($user_ids as $user_id) {
			if (!array_key_exists($user_id, $cache)) {
				$wanted_id []= $user_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$querys = db_fetch_assoc('SELECT DISTINCT
				id, username
				FROM user_auth
				WHERE id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($querys)) {
				foreach ($querys as $query) {
					$cache[$query['id']] = html_escape($query['username']);
				}
			}
		}

		foreach ($user_ids as $user_id) {
			$result .= $matches[1];
			if (isset($cache[$query_id])) {
				if ($link) {
					$result .= '<a href=\'' . html_escape($config['url_path'] . 'user_admin.php?action=user_edit&tab=general&id=' . $query_id) . '\'>';
				}
				$result .= $cache[$query_id];
				if ($link) {
					$result .= '</a>';
				}
			} else {
				$result .= $query_id;
			}
			$result .= $matches[3];
		}
	}
	return $result;
}

function clog_regex_rule($matches, $link = false) {
	global $config;
	static $cache;

	if (!isset($cache)) {
		$cache = array();
	}

	$result = $matches[0];

	$rule_ids = explode(',',str_replace(" ","",$matches[2]));
	if (cacti_sizeof($rule_ids)) {
		$result     = '';
		$wanted_ids = array();
		$rule_ids   = array_filter(array_unique($rule_ids));

		foreach ($rule_ids as $rule_id) {
			if (!array_key_exists($rule_id, $cache)) {
				$wanted_ids []= $rule_id;
			}
		}

		if (cacti_sizeof($wanted_ids)) {
			$rules = db_fetch_assoc('SELECT id, name
				FROM automation_graph_rules
				WHERE id in (' . implode(',',$wanted_ids) . ')');

			if (cacti_sizeof($rules)) {
				foreach ($rules as $rule) {
					$cache[$rule['id']] = html_escape($rule['name']);
				}
			}
		}

		foreach ($rule_ids as $rule_id) {
			$result .= $matches[1];
			if ($link) {
				$result .= '<a href=\'' . html_escape($config['url_path'] . 'automation_graph_rules.php?action=edit&id=' . $rule_id) . '\'>';
			}
			$result .= isset($ruleNames[$rule_id]) ? $ruleNames[$rule_id]:$rule_id;
			if ($link) {
				$result .= '</a>';
			}
			$result .= $matches[3];
		}
	}

	return $result;
}
