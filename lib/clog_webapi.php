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

include_once('functions.php');

/**
 * Retrieves a list of graphs associated with a specific data source.
 *
 * This function queries the database to fetch all distinct graphs that are linked
 * to the provided local data ID. The result is returned as an associative array
 * where the keys are the graph IDs and the values are the graph names.
 *
 * @param int $local_data_id The ID of the local data source to fetch graphs for.
 *
 * @return array An associative array of graphs, where the keys are graph IDs
 *               and the values are graph names.
 */
function clog_get_graphs_from_datasource(int $local_data_id) : array {
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

/**
 * Validates and processes a given filename, determining its path and base name.
 *
 * This function checks if the provided filename matches specific log file patterns
 * (e.g., standard log, error log, or boost log) and extracts the corresponding
 * file path and base name. Optionally, it can verify if the file exists.
 *
 * @param string $file      The input filename to validate. This will be modified
 *                          to contain only the base name of the file.
 * @param string $filepath  The output variable that will hold the directory path
 *                          of the validated file.
 * @param string $filename  The output variable that will hold the base name of
 *                          the validated file.
 * @param bool   $filecheck If true, the function will check if the
 *                          resolved file exists. Defaults to false.
 *
 * @return bool Returns true if the file is valid (and exists if $filecheck is true),
 *              or false otherwise.
 */
function clog_validate_filename(string &$file, string &$filepath = '', string &$filename = '', bool $filecheck = false) : bool {
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

/**
 * Purges or clears a specified log file within the Cacti application.
 *
 * @param string $action The action to be taken 'purge' or 'rotate'
 *
 * @return void
 */
function clog_purge_logfile(string $action = 'purge') : void {
	$filename = gnrv('filename');

	$logpath = '';
	$logname = '';

	if (!clog_validate_filename($filename, $logpath, $logname)) {
		raise_message('clog_invalid');
		header('Location: ' . get_current_page());

		exit(0);
	}

	$purgefile  = $logpath . '/' . $filename;
	$logfile    = $logpath . '/' . $logname;
	$log_action = read_config_option('log_action');

	// get base filenames for rotate assessment
	$cactiLog  = basename(read_config_option('path_cactilog'));
	$errorLog  = basename(read_config_option('path_stderrlog'));

	// basic checking
	if ($action == 'rotate' && $log_action == LOG_ACTION_PURGE) {
		raise_message('rotate_failed', __('Cacti Log file rotation failed for Log File \'%s\'.  User \'%s\' wished to rotate, but rotating is disabled', basename($purgefile), get_username()), MESSAGE_LEVEL_ERROR);

		return;
	}

	if ($action == 'purge' && $log_action == LOG_ACTION_ROTATE) {
		raise_message('purge_failed', __('Cacti Log file purging failed for Log File \'%s\'.  User \'%s\' wished to purge, but purging is disabled', basename($purgefile), get_username()), MESSAGE_LEVEL_ERROR);

		return;
	}

	if ($filename != $cactiLog && $filename != $errorLog && $action == 'rotate') {
		raise_message('rotate_failed', __('Cacti Log file rotation failed for Log File \'%s\'.  User \'%s\' wished to rotate, but rotating is not allowed on already rotated files', basename($purgefile), get_username()), MESSAGE_LEVEL_ERROR);

		return;
	}

	if ($action == 'purge') {
		$imessage = __('The Cacti Log File \'%s\' was Removed by user \'%s\'', basename($purgefile), get_username());
		$message  = sprintf('The Cacti Log File \'%s\', Removed by user \'%s\'', basename($purgefile), get_username());
	} else {
		$imessage = __('The Cacti Log File \'%s\' was Rotated by user \'%s\'', basename($purgefile), get_username());
		$message  = sprintf('The Cacti Log File \'%s\', Rotated by user \'%s\'', basename($purgefile), get_username());
	}

	if (file_exists($purgefile)) {
		if (is_writable($purgefile)) {
			if ($logfile != $purgefile) {
				if ($action == 'purge') {
					unlink($purgefile);

					raise_message('clog_removed', $imessage, MESSAGE_LEVEL_INFO);

					cacti_log($message, false, 'WEBUI');
				} else {
					raise_message('clog_removed', __('Removal Failed due to the Administrator blocking removal of archived files.  The file \'%s\' can not be removed.', basename($purgefile)), MESSAGE_LEVEL_WARN);
				}
			} else {
				if ($action == 'rotate') {
					$ext = date('Ymd-His');
					rename($logfile, $logfile . '-' . $ext);
				}

				$log_fh = fopen($logfile, 'w');

				fclose($log_fh);

				raise_message('clog_removed', $imessage, MESSAGE_LEVEL_INFO);

				cacti_log($message, false, 'WEBUI');
			}
		} else {
			raise_message('clog_permissions');
		}
	} else {
		raise_message('clog_missing');
	}
}

/**
 * Displays the log file viewer for Cacti, allowing users to view, filter, and manage log entries.
 *
 * @return void
 */
function clog_view_logfile() : void {
	global $logfile_actions;

	$exclude_reported = false;

	$clogAdmin = clog_admin();

	// enable page refreshes
	kill_session_var('custom');

	if (isrv('filename')) {
		$logfile = basename(gnrv('filename'));
	} elseif (isset($_SESSION['sess_clog']['filename'])) {
		$logfile = basename($_SESSION['sess_clog']['filename']);
	} else {
		$logfile = 'cacti.log';
	}

	$logname = '';
	$logpath = '';

	if (!clog_validate_filename($logfile, $logpath, $logname, true)) {
		$logfile = read_config_option('path_cactilog');
	} else {
		$logfile = $logpath . '/' . $logfile;
	}

	if ($clogAdmin) {
		$redirect = false;

		if (isrv('purge_continue')) {
			$redirect = true;
			clog_purge_logfile('purge');
		} elseif (isrv('rotate_continue')) {
			$redirect = true;
			clog_purge_logfile('rotate');
		}

		if ($redirect) {
			$logfile = read_config_option('path_cactilog');

			header('Location: clog.php?filename=' . basename($logfile));

			exit;
		}
	}

	$page_nr = gnrv('page');

	if ($page_nr == '') {
		$page_nr = 1;
		srv('page', 1);
	}

	if (get_current_page() == 'clog.php' || get_current_page() == 'clog_user.php') {
		general_header();
	} else {
		top_header();
	}

	if ($clogAdmin) {
		if (gnrv('action') == 'purge' || gnrv('action') == 'rotate') {
			// Keep phpstan happy
			$action  = '';
			$title   = '';
			$message = '';
			$header  = '';

			if (gnrv('action') == 'purge') {
				$message = __('Click \'Continue\' to Purge the Log File \'' . htmle(basename($logfile)) . '\'.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.');
				$action  = 'purge_continue';
				$button  = __esc('Purge');
				$title   = __esc('Purge Log');
				$header  = $logfile_actions[LOG_ACTION_PURGE];
			} elseif (gnrv('action') == 'rotate') {
				$message = __('Click \'Continue\' to Rotate the existing Log File \'' . htmle(basename($logfile)) . '\'.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.');
				$action  = 'rotate_continue';
				$button  = __esc('Rotate');
				$title   = __esc('Rotate Log');
				$header  = $logfile_actions[LOG_ACTION_ROTATE];
			}

			form_start(get_current_page());

			html_start_box($header, '60%', false, 3, 'center', '');

			print "<tr>
				<td class='textArea'>
					<p>" . $message . "</p>
				</td>
			</tr>
			<tr class='saveRow'>
				<td colspan='2' class='right'>
					<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel'>" . __esc('Cancel') . "</button>
					<button type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' title='$title'>" . __esc('Continue') . "</button>
					<script type='text/javascript'>
					$('#pc').click(function() {
						strURL = location.pathname+'?$action=1&filename=" . basename($logfile) . "';
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
	}

	draw_clog_filter(true, $logfile, $clogAdmin);

	// read logfile into an array and display
	$total_rows      = 0;
	$number_of_lines = grv('tail_lines') < 0 ? read_config_option('max_display_rows') : grv('tail_lines');

	if (grv('expand') == 2) {
		$should_expand = false;
	} elseif (grv('expand') == 1) {
		$should_expand = true;
	} else {
		$should_expand = read_config_option('log_expand') != LOG_EXPAND_NONE;
	}

	$reverse = grv('reverse');

	$logcontents = tail_file($logfile, $number_of_lines, grv('message_type'), grv('rfilter'), $page_nr, $total_rows, grv('matches'), $should_expand, $reverse);

	if (grv('reverse') == 1) {
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

	if (grv('message_type') > 0 || grv('rfilter') != '') {
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
		$hostDescriptions[$host['id']] = htmle($host['description']);
	}

	foreach ($logcontents as $item) {
		$new_item = htmle($item);

		if ($should_expand) {
			$new_item = text_substitute($new_item, isHtml: true);
		}

		// respect the exclusion filter
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

		// get the background color
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

/**
 * Custom sorting function for log file names.
 *
 * @param string $a The first file name to compare.
 * @param string $b The second file name to compare.
 *
 * @return int Returns < 0 if $a is less than $b, 0 if they are equal, and > 0 if $a is greater than $b.
 */
function filter_sort(string $a, string $b) : int {
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
	return strcmp($b_date . '-' . str_replace('_', '+', $b_parts[0]), $a_date . '-' . str_replace('_', '+', $a_parts[0]));
}

/**
 * Retrieves a list of log files from the configured log directories.
 *
 * @return array An array of log file names, including standard, stderr, and boost logs.
 *
 * Notes:
 * - If the configured log path is not readable, it defaults to 'cacti.log'.
 * - The function ensures that archived log files are included in the result.
 */
function clog_get_logfiles() : array {
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

	$logName = '';

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

/**
 * Creates a filter configuration for the clog (custom log) web API.
 *
 * @param string $logfile   The name of the log file to be filtered.
 * @param bool   $clogAdmin Indicates whether the user has administrative
 *                          privileges for clog operations.
 *
 * @return array The filter configuration array, including rows of filter options
 *               and action buttons.
 */
function create_clog_filter(string $logfile, bool $clogAdmin) : array {
	global $log_tail_lines, $page_refresh_interval, $logfile_actions;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	// transform the log directory as required
	$logFileArray = clog_get_logfiles();
	$newLogArray  = [];
	$log_action   = read_config_option('log_action');

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

	$filter = [
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
				'title'   => __('Purge all data from the existing Cacti Log'),
				'url'     => 'clog.php?action=purge&filename=' . $logfile
			],
			'rotate' => [
				'method'  => 'button',
				'display' => __('Rotate'),
				'action'  => 'default',
				'title'   => __('Rotate the Cacti Log'),
				'url'     => 'clog.php?action=rotate&filename=' . $logfile
			],
		]
	];

	if (!$clogAdmin) {
		unset($filter['buttons']['purge']);
		unset($filter['buttons']['rotate']);
	} else {
		$cactiLog  = read_config_option('path_cactilog');
		$errorLog  = read_config_option('path_stderrlog');
		$logAction = read_config_option('log_action');

		if ($logfile == $cactiLog || $logfile == $errorLog) {
			if ($logAction == LOG_ACTION_PURGE) {
				unset($filter['buttons']['rotate']);
			} elseif ($logAction == LOG_ACTION_ROTATE) {
				unset($filter['buttons']['purge']);
			}
		} else {
			if ($logAction == LOG_ACTION_PURGE) {
				unset($filter['buttons']['rotate']);
			} elseif ($logAction == LOG_ACTION_ROTATE) {
				unset($filter['buttons']['rotate']);
				unset($filter['buttons']['purge']);
			} else {
				unset($filter['buttons']['rotate']);
			}
		}
	}

	return $filter;
}

/**
 * Draws the clog filter for log files and renders or sanitizes it based on the provided parameters.
 *
 * @param bool        $render    Determines whether to render the filter or sanitize it. Defaults to false.
 * @param bool|string $logfile   Specifies the logfile to filter. Defaults to false.
 * @param bool        $clogAdmin Indicates whether the filter is for an admin user. Defaults to false.
 *
 * @return void
 */
function draw_clog_filter(bool $render = false, bool|string $logfile = false, bool $clogAdmin = false) : void {
	$filters = create_clog_filter($logfile, $clogAdmin);

	$page_nr = gnrv('page');

	$current_page = get_current_page();

	if ($current_page == 'utilities.php') {
		$base_page  = 'utilities.php?action=view_logfile';
		$page       = $base_page . '&page=' . $page_nr;
	} else {
		$base_page  = 'clog' . (!$clogAdmin ? '_user' : '') . '.php';
		$page       = $base_page . '?page=' . $page_nr;
	}

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Log Filters'), $page, 'logfile', 'sess_clog', '', false, false);
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Retrieves an array of regular expressions.
 *
 * @deprecated Use text_get_regex_array() directly instead.
 *
 * @return array Returns an array of regular expressions.
 */
function clog_get_regex_array() : array {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_get_regex_array();
}

/**
 * Performs a regex replacement on the provided text using the specified parameters.
 *
 * @deprecated Use `text_regex_replace` directly instead.
 *
 * @param int    $id      An identifier for the operation or context.
 * @param string $link    A link or reference related to the operation.
 * @param string $url     The URL to be processed.
 * @param array  $matches An array of matches to be used in the replacement.
 * @param mixed  $cache   Cache data to be used during the operation.
 *
 * @return mixed The result of the regex replacement operation.
 */
function clog_regex_replace(int $id, string $link, string $url, array $matches, mixed $cache) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_replace($id, $link, $url, $matches, $cache);
}

/**
 * Parses HTML content using a regular expression parser.
 *
 * @deprecated This function is deprecated and should not be used in new code.
 *
 * @param array $matches An array of matches from a regular expression.
 *
 * @return mixed The result of the `text_regex_parser()` function.
 */
function clog_regex_parser_html(array $matches) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_parser($matches, true);
}

/**
 * Parses the given matches using a regular expression and optionally generates a link.
 *
 * @deprecated This function is deprecated. Use `text_regex_parser()` directly instead.
 *
 * @param array $matches The matches to be parsed, typically from a regular expression.
 * @param bool  $link    Optional. Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_parser` function.
 */
function clog_regex_parser(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_parser($matches, $link);
}

/**
 * Processes a regex match for a device and optionally generates a link.
 *
 * @deprecated Use `text_regex_device()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_device` function.
 */
function clog_regex_device(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_device($matches, $link);
}

/**
 * Processes a regex match for a data source and optionally generates a link.
 *
 * @deprecated Use `text_regex_datasource()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_datasource()` function.
 */
function clog_regex_datasource(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_datasource($matches, $link);
}

/**
 * Logs and processes regex matches for a poller.
 *
 * @deprecated Use `text_regex_poller()` directly instead.
 *
 * @param array $matches An array of regex matches to be processed.
 * @param bool  $link    Whether to include a link in the processing. Default is false.
 *
 * @return mixed The result of the `text_regex_poller()` function.
 */
function clog_regex_poller(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_poller($matches, $link);
}

/**
 * Processes regex matches for a data query and optionally generates a link.
 *
 * @deprecated Use `text_regex_dataquery` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_dataquery` function.
 */
function clog_regex_dataquery(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_dataquery($matches, $link);
}

/**
 * Processes regex matches for a RRA and optionally generates a link.
 *
 * @deprecated Use `text_regex_rra()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_rra` function.
 */
function clog_regex_rra(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_rra($matches, $link);
}

/**
 * Processes regex matches for graphs and optionally generates a link.
 *
 * @deprecated Use `text_regex_graphs()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_graphs` function.
 */
function clog_regex_graphs(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_graphs($matches, $link);
}

/**
 * Processes regex matches for graph templates and optionally generates a link.
 *
 * @deprecated Use `text_regex_graphtemplates()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_graphtemplates` function.
 */
function clog_regex_graphtemplates(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_graphtemplates($matches, $link);
}

/**
 * Processes regex matches for users and optionally generates a link.
 *
 * @deprecated Use `text_regex_users()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_users` function.
 */
function clog_regex_users(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_users($matches, $link);
}

/**
 * Processes regex matches for rules and optionally generates a link.
 *
 * @deprecated Use `text_regex_rule()` directly instead.
 *
 * @param array $matches An array of regex matches to process.
 * @param bool  $link    Whether to generate a link. Default is false.
 *
 * @return mixed The result of the `text_regex_rule` function.
 */
function clog_regex_rule(array $matches, bool $link = false) : mixed {
	cacti_depreciated(__FUNCTION__ . '()');

	return text_regex_rule($matches, $link);
}

/**
 * Retrieves the titles of data sources based on the provided local data IDs.
 *
 * @deprecated Use get_data_source_titles() directly instead.
 *
 * @param array $local_data_ids An array of local data IDs for which to retrieve titles.
 *
 * @return array An array of data source titles corresponding to the provided IDs.
 */
function clog_get_datasource_titles(array $local_data_ids) : array {
	cacti_depreciated(__FUNCTION__ . '()');

	return get_data_source_titles($local_data_ids);
}
