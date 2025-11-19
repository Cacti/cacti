<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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
		[$local_data_id]), 'id', 'name');
}

function clog_validate_filename(&$file, &$filepath, &$filename, $filecheck = false) {
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

	if (!empty($errfile) && str_starts_with($file, $errbase)) {
		$filepath = dirname($errfile);
		$filename = $errbase;
		$filefull = $filepath . '/' . $file;
	} elseif (!empty($logfile) && str_starts_with($file, $logbase)) {
		$filepath = dirname($logfile);
		$filename = $logbase;
		$filefull = $filepath . '/' . $file;
	} elseif (!empty($boostfile) && str_starts_with($file, $boostbase)) {
		$filepath = dirname($boostfile);
		$filename = $boostbase;
		$filefull = $filepath . '/' . $file;
	}

	return ($filecheck ? file_exists($filefull) : !empty($filefull));
}

function clog_purge_logfile() {
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
				raise_message('clog_removed', __('The Cacti Log File \'%s\' was Removed by user \'%s\'', basename($purgefile), get_username()), MESSAGE_LEVEL_INFO);
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
				raise_message('clog_removed', __('The Cacti Log File \'%s\' was Removed by user \'%s\'', basename($logfile), get_username()), MESSAGE_LEVEL_INFO);
			}

			cacti_log(sprintf('NOTE: Cacti Log file \'%s\', Removed by user \'%s\'', basename($purgefile), get_username()), false, 'WEBUI');
		} else {
			raise_message('clog_permissions');
		}
	} else {
		raise_message('clog_missing');
	}
}

function clog_view_logfile() {
	global $base_page;

	$exclude_reported = false;

	$clogAdmin = clog_admin();

	/* enable page refreshes */
	kill_session_var('custom');

	if (isset_request_var('filename')) {
		$logfile = basename(get_nfilter_request_var('filename'));
	} elseif (isset($_SESSION['sess_clog']['filename'])) {
		$logfile = basename($_SESSION['sess_clog']['filename']);
	} else {
		$logfile = 'cacti.log';
	}

	$logname = '';

	if (!clog_validate_filename($logfile, $logpath, $logname, true)) {
		$logfile = read_config_option('path_cactilog');
	} else {
		$logfile = $logpath . '/' . $logfile;
	}

	if ($clogAdmin && isset_request_var('purge_continue')) {
		clog_purge_logfile();

		$logfile = read_config_option('path_cactilog');

		header('Location: clog.php?filename=' . basename($logfile));

		exit;
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

		html_start_box(__('Purge'), '60%', false, 3, 'center', '');

		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the Log File \'' . html_escape(basename($logfile)) . '\'.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' class='right'>
				<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel'>" . __esc('Cancel') . "</button>
				<button type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' title='" . __esc('Purge Log') . "'>" . __esc('Continue') . "</button>
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

	draw_clog_filter(true, $logfile, $clogAdmin);

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

	$reverse = get_request_var('reverse');

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows, get_request_var('matches'), $should_expand, $reverse);

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

	$base_url = CACTI_PATH_URL . 'clog.php';

	$nav = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 1, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box($start_string, '100%', false, 3, 'center', '');

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

	$hostDescriptions = [];

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
		if (str_contains($new_item, 'ERROR') || str_contains($new_item, 'FATAL')) {
			$class = 'clogError';
		} elseif (str_contains($new_item, 'WARN')) {
			$class = 'clogWarning';
		} elseif (str_contains($new_item, ' SQL ')) {
			$class = 'clogSQL';
		} elseif (str_contains($new_item, 'DEBUG')) {
			$class = 'clogDebug';
		} elseif (str_contains($new_item, 'STATS')) {
			$class = 'clogStats';
		} else {
			if ($linecolor) {
				$class = 'odd';
			} else {
				$class = 'even';
			}
			$linecolor = !$linecolor;
		}

		print "<tr class='$class'><td>$new_item</td></tr>";
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
	$stdFileArray  = $stdLogFileArray = $stdErrFileArray = $boostFileArray = [];
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
		$files = ['cacti.log'];
	}

	// Defaults go first and second
	$stdFileArray[] = basename($configLogPath);

	// After Defaults, do Cacti log first (of archived)
	if (cacti_sizeof($files)) {
		$stdLogFileArray = [];

		foreach ($files as $logFile) {
			if (in_array($logFile, ['.', '..', '.htaccess', $configLogBase, $stderrLogBase, $boostLogBase], true)) {
				continue;
			}

			$explode = explode('.', $logFile);

			if (!str_starts_with($explode[max(array_keys($explode))], 'log')) {
				continue;
			}

			if (!clog_validate_filename($logFile, $logPath, $logName)) {
				continue;
			}

			if (!empty($stderrLogBase) && str_starts_with($logFile, $stderrLogBase)) {
				$stdErrFileArray[] = $logFile;
			} elseif (!empty($boostLogBase) && str_starts_with($logFile, $boostLogBase)) {
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
				$stdErrFileArray = [];

				foreach ($files as $logFile) {
					if (in_array($logFile, ['.', '..', '.htaccess', $configLogBase, $stderrLogBase], true)) {
						continue;
					}

					$explode = explode('.', $logFile);

					if (!str_starts_with($explode[max(array_keys($explode))], 'log')) {
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

function create_clog_filter($logfile, $clogAdmin) {
	global $log_tail_lines, $page_refresh_interval;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	/* transform the log directory as required */
	$logFileArray = clog_get_logfiles();
	$newLogArray  = [];

	if (cacti_sizeof($logFileArray)) {
		foreach ($logFileArray as $logFile) {
			$logParts              = explode('-', $logFile);
			$logDate               = cacti_count($logParts) < 2 ? '' : $logParts[1] . (isset($logParts[2]) ? '-' . $logParts[2] : '');
			$logName               = $logParts[0];
			$newLogArray[$logFile] = $logName . ($logDate != '' ? ' [' . substr($logDate,4) . ']' : '');
		}
	}

	$expands = [
		'0' => __('System Default'),
		'1' => __('Expand Log'),
		'2' => __('Raw Log'),
	];

	$message_types = [
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
	];

	if (api_plugin_is_enabled('thold')) {
		$message_types['99'] = __('Threshold');
	}

	$reverse = [
		'1' => __('Newest First'),
		'2' => __('Oldest First')
	];

	$matches = [
		'1' => __('Matches'),
		'0' => __('Does Not Match')
	];

	return [
		'rows' => [
			[
				'filename' => [
					'method'        => 'drop_array',
					'friendly_name' => __('File'),
					'filter'        => FILTER_DEFAULT,
					'default'       => 'cacti.log',
					'array'         => $newLogArray,
					'value'         => $logfile
				],
				'tail_lines' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Tail Lines'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => read_config_option('max_display_rows'),
					'pageset'       => true,
					'array'         => $log_tail_lines,
					'value'         => ''
				],
				'expand' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Expand Log'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $expands,
					'value'         => '-1'
				]
			],
			[
				'message_type' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Type'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $message_types,
					'value'         => '-1'
				],
				'reverse' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Display'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '1',
					'pageset'       => true,
					'array'         => $reverse,
					'value'         => '1'
				],
				'refresh' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Refresh'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '300',
					'pageset'       => true,
					'array'         => $page_refresh_interval,
					'value'         => '300'
				]
			],
			[
				'matches' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Search'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '1',
					'dynamic'       => false,
					'pageset'       => true,
					'array'         => $matches,
					'value'         => '1'
				],
				'rfilter' => [
					'method'         => 'textbox',
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			],
			'purge' => [
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge User log of all but the last login attempt'),
				'url'     => 'clog.php?action=purge&filename=' . $logfile
			],
		]
	];

	if (!$clogAdmin) {
		unset($filter['buttons']['purge']);
	}
}

function draw_clog_filter($render = false, $logfile = false, $clogAdmin = false) {
	$filters = create_clog_filter($logfile, $clogAdmin);

	$page_nr = get_nfilter_request_var('page');

	$current_page = get_current_page();

	if ($current_page == 'utilities.php') {
		$base_page  = 'utilities.php?action=view_logfile';
		$page       = $base_page . '&page=' . $page_nr;
	} else {
		$base_page  = 'clog' . (!$clogAdmin ? '_user' : '') . '.php';
		$page       = $base_page . '?page=' . $page_nr;
	}

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Log Filters'), $page, 'logfile', 'sess_clog', '', false, false);
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
