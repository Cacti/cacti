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

include_once('functions.php');

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
		$logfile = CACTI_PATH_LOG . '/cacti.log';
	}

	$errfile   = read_config_option('path_stderrlog');
	$errbase   = basename($errfile);

	$boostfile = read_config_option('path_boost_log');
	$boostbase = basename($boostfile);

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
	} elseif (!empty($boostfile) && strpos($file, $boostbase) === 0) {
		$filepath = dirname($boostfile);
		$filename = $boostbase;
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
	$logfile   = $logpath . '/'. $logname;

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

			cacti_log('NOTE: Cacti Log file ' . $purgefile . ', Removed by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
		} else {
			raise_message('clog_permissions');
		}
	} else {
		raise_message('clog_missing');
	}
}

function clog_view_logfile() {
	global $config, $base_page;

	$exclude_reported = false;

	$clogAdmin = clog_admin();

	/* enable page refreshes */
	kill_session_var('custom');

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

	$page_nr = get_nfilter_request_var('page');
	if ($page_nr == '') {
		$page_nr = 1;
		set_request_var('page', 1);
	}

	if (get_current_page() == 'clog.php' || get_current_page() == 'clog_user.php') {
		general_header(true);
	} else {
		top_header(true);
	}

	if ($clogAdmin && get_nfilter_request_var('action') == 'purge') {
		form_start(get_current_page());

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
		</tr>";

		html_end_box();

		return;
	}

	process_sanitize_draw_filter(true, $logfile, $clogAdmin);

	/* read logfile into an array and display */
	$total_rows      = 0;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');

	if (get_request_var('expand') == 2) {
		$should_expand = false;
	} elseif (get_request_var('expand') == 1) {
		$should_expand = true;
	} else {
		$should_expand = read_config_option('log_expand') != LOG_EXPAND_NONE;
	}

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows, get_request_var('matches'), $should_expand);

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
		$ad_filter     = __(' - Admin view');
		$exclude_regex = '';
	}

	if (get_request_var('message_type') > 0 || get_request_var('rfilter') != '') {
		$start_string = __('Log [Total Lines: %d %s - Filter active]', $total_rows, $ad_filter);
	} else {
		$start_string = __('Log [Total Lines: %d %s - Unfiltered]', $total_rows, $ad_filter);
	}

	$base_url     = CACTI_PATH_URL . 'clog.php';

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
			$new_item = text_substitute($new_item, isHtml: true);
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

	$stdFileArray  = $stdLogFileArray = $stdErrFileArray = $boostFileArray = array();
	$configLogPath = read_config_option('path_cactilog');
	$configLogBase = basename($configLogPath);
	$stderrLogPath = read_config_option('path_stderrlog');
	$stderrLogBase = basename($stderrLogPath);
	$boostLogPath  = read_config_option('path_boost_log');
	$boostLogBase  = basename($boostLogPath);

	if ($configLogPath == '') {
		$logPath = CACTI_PATH_LOG . '/';
	} else {
		$logPath = dirname($configLogPath);
	}

	if (is_readable($logPath)) {
		$files = scandir($logPath);
	} else {
		$files = array('cacti.log');
	}

	// Defaults go first and second
	$stdFileArray[] = basename($configLogPath);

	// After Defaults, do Cacti log first (of archived)
	if (cacti_sizeof($files)) {
		$stdLogFileArray = array();

		foreach ($files as $logFile) {
			if (in_array($logFile, array('.', '..', '.htaccess', $configLogBase, $stderrLogBase, $boostLogBase), true)) {
				continue;
			}

			$explode = explode('.', $logFile);

			if (substr($explode[max(array_keys($explode))], 0, 3) != 'log') {
				continue;
			}

			if (!clog_validate_filename($logFile, $logPath, $logName)) {
				continue;
			}

			if (!empty($stderrLogBase) && strpos($logFile, $stderrLogBase) === 0){
				$stdErrFileArray[] = $logFile;
			} elseif (!empty($boostLogBase) && strpos($logFile, $boostLogBase) === 0){
				$boostFileArray[] = $logFile;
			} else {
				$stdLogFileArray[] = $logFile;
			}
		}

		$stdErrFileArray = array_unique($stdErrFileArray);
		$stdLogFileArray = array_unique($stdLogFileArray);
		$boostFileArray  = array_unique($boostFileArray);
	}

	// Defaults go first and second
	if (!empty($stderrLogPath)) {
		$stdFileArray[] = basename($stderrLogPath);

		// After Defaults, do Cacti StdErr log second (of archived)
		if (dirname($stderrLogPath) != $logPath) {
			$errFiles = @scandir(dirname($stderrLogPath));
			$files    = $errFiles;

			if (cacti_sizeof($files)) {
				$stdErrFileArray = array();

				foreach ($files as $logFile) {
					if (in_array($logFile, array('.', '..', '.htaccess', $configLogBase, $stderrLogBase), true)) {
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
	arsort($boostFileArray, SORT_NATURAL);

	return array_unique(array_merge($stdFileArray, $stdLogFileArray, $stdErrFileArray, $boostFileArray));
}

function create_filter($logfile, $clogAdmin) {
	global $log_tail_lines, $page_refresh_interval;

	$all     = array('-1' => __('All'));
	$any     = array('-1' => __('Any'));
	$none    = array('0'  => __('None'));
	$deleted = array('-2' => __('Deleted/Invalid'));

	/* transform the log directory as required */
	$logFileArray = clog_get_logfiles();
	$newLogArray  = array();

	if (cacti_sizeof($logFileArray)) {
		foreach ($logFileArray as $index => $logFile) {
			$logParts = explode('-', $logFile);
			$logDate  = cacti_count($logParts) < 2 ? '' : $logParts[1] . (isset($logParts[2]) ? '-' . $logParts[2]:'');
			$logName  = $logParts[0];
			$newLogArray[$logFile] = $logName . ($logDate != '' ? ' [' . substr($logDate,4) . ']':'');
		}
	}

	$expands = array(
		'0' => __('System Default'),
		'1' => __('Expand Log'),
		'2' => __('Raw Log'),
	);

	$message_types = array(
		'-1' => __('All'),
		'1'  => __('Stats'),
		'2'  => __('Warnings'),
		'3'  => __('Warnings++'),
		'4'  => __('Errors'),
		'5'  => __('Errors++'),
		'6'  => __('Debug'),
		'7'  => __('SQL Calls'),
		'8'  => __('AutoM8'),
		'9'  => __('Non Stats'),
		'10' => __('Boost'),
		'11' => __('Device Up/Down'),
		'12' => __('Recaches'),
		'13' => __('Security Issues'),
	);

	if (api_plugin_is_enabled('thold')) {
		$message_types['99'] = __('Threshold');
	}

	$reverse = array(
		'1' => __('Newest First'),
		'2' => __('Oldest First')
	);

	$matches = array(
		'1' => __('Matches'),
		'0' => __('Does Not Match')
	);

	return array(
		'rows' => array(
			array(
				'filename' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('File'),
					'filter'        => FILTER_DEFAULT,
					'default'       => 'cacti.log',
					'array'         => $newLogArray,
					'value'         => $logfile
				),
				'tail_lines' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Tail Lines'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $log_tail_lines,
					'value'         => '-1'
				),
				'expand' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Expand Log'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $expands,
					'value'         => '-1'
				)
			),
			array(
				'message_type' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Type'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $message_types,
					'value'         => '-1'
				),
				'reverse' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Display'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '1',
					'pageset'       => true,
					'array'         => $reverse,
					'value'         => '1'
				),
				'refresh' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Refresh'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '300',
					'pageset'       => true,
					'array'         => $page_refresh_interval,
					'value'         => '300'
				)
			),
			array(
				'matches' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Search'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '1',
					'dynamic'       => false,
					'pageset'       => true,
					'array'         => $matches,
					'value'         => '1'
				),
				'rfilter' => array(
					'method'        => 'textbox',
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				)
			)
		),
		'buttons' => array(
			'go' => array(
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			),
			'clear' => array(
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			),
			'purge' => array(
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge User log of all but the last login attempt'),
			),
		)
	);

	if (!$clogAdmin) {
		unset($filter['buttons']['purge']);
	}
}

function process_sanitize_draw_filter($render = false, $logfile = false, $clogAdmin = false) {
	$filters = create_filter($logfile, $clogAdmin);

	$page_nr = get_nfilter_request_var('page');

	$current_page = get_current_page();

	if ($current_page == 'utilities.php') {
		$base_page  = 'utilities.php?action=view_logfile';
		$page = $base_page . '&page=' . $page_nr;
	} else {
		$base_page  = 'clog' . (!$clogAdmin ? '_user' : '') . '.php';
		$page = $base_page . '?page=' . $page_nr;
	}

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Log Filters'), $page, 'logfile', 'sess_clog');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function clog_get_regex_array() {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_get_regex_array();
}

function clog_regex_replace($id, $link, $url, $matches, $cache) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_replace($id, $link, $url, $matches, $cache);
}

function clog_regex_parser_html($matches) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_parser($matches, true);
}

function clog_regex_parser($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_parser($matches, $link);
}

function clog_regex_device($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_device($matches, $link);
}

function clog_regex_datasource($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_datasource($matches, $link);
}

function clog_regex_poller($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_poller($matches, $link);
}

function clog_regex_dataquery($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_dataquery($matches, $link);
}

function clog_regex_rra($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_rra($matches, $link);
}

function clog_regex_graphs($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_graphs($matches, $link);
}

function clog_regex_graphtemplates($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_graphtemplates($matches, $link);
}

function clog_regex_users($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_users($matches, $link);
}

function clog_regex_rule($matches, $link = false) {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_rule($matches, $link);
}

function clog_get_datasource_titles($local_data_ids) {
	cacti_depreciated(__FUNCTION__ . '()');

	return get_data_source_titles($local_data_ids);
}
