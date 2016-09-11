<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

/* title_trim - takes a string of text, truncates it to $max_length and appends
     three periods onto the end
   @arg $text - the string to evaluate
   @arg $max_length - the maximum number of characters the string can contain
     before it is truncated
   @returns - the truncated string if len($text) is greater than $max_length, else
     the original string */
function title_trim($text, $max_length) {
	if (strlen($text) > $max_length) {
		return substr($text, 0, $max_length) . '...';
	}else{
		return $text;
	}
}

/* filter_value - a quick way to highlight text in a table from general filtering
   @arg $text - the string to filter
   @arg $filter - the search term to filter for
   @arg $href - the href if you wish to have an anchor returned
   @returns - the filtered string */
function filter_value($value, $filter, $href = '') {
	static $charset;

	if ($charset == '') {
		$charset = ini_get('default_charset');
	}

	$value =  htmlspecialchars($value, ENT_QUOTES, $charset, false);

	if ($filter != '') {
		$value = preg_replace('#(' . $filter . ')#i', "<span class='filteredValue'>\\1</span>", $value);
	}

	if ($href != '') {
		$value = '<a class="linkEditMain" href="' . htmlspecialchars($href, ENT_QUOTES, $charset, false) . '">' . $value  . '</a>';
	}

	return $value;
}

/* set_graph_config_option - deprecated - wrapper to set_user_setting().
   @arg $config_name - the name of the configuration setting as specified $settings array
   @arg $value       - the values to be saved
   @arg $user        - the user id, otherwise the session user
   @returns          - void */
function set_graph_config_option($config_name, $value, $user = -1) {
	set_user_setting($config_name, $value, $user);
}

/* graph_config_value_exists - deprecated - wrapper to user_setting_exists
   @arg $config_name - the name of the configuration setting as specified $settings_user array
     in 'include/global_settings.php'
   @arg $user_id - the id of the user to check the configuration value for
   @returns (bool) - true if a value exists, false if a value does not exist */
function graph_config_value_exists($config_name, $user_id) {
	return user_setting_exists($config_name, $user_id);
}

/* read_default_graph_config_option - deprecated - wrapper to read_default_user_setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the default value of the configuration option */
function read_default_graph_config_option($config_name) {
	return read_default_user_setting($config_name);
}

/* read_graph_config_option - deprecated - finds the current value of a graph configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings_user array
     in 'include/global_settings.php'
   @returns - the current value of the graph configuration option */
function read_graph_config_option($config_name, $force = FALSE) {
	return read_user_setting($config_name, false, $force);
}

/* set_user_setting - sets/updates a user setting with the given value.
   @arg $config_name - the name of the configuration setting as specified $settings array
   @arg $value       - the values to be saved
   @arg $user        - the user id, otherwise the session user
   @returns          - void */
function set_user_setting($config_name, $value, $user = -1) {
	if ($user == -1) {
		$user = $_SESSION['sess_user_id'];
	}
	db_execute_prepared('REPLACE INTO settings_user SET user_id = ?, name = ?, value = ?', array($user, $config_name, $value));

	unset($_SESSION['sess_user_config_array']);
	unset($settings_user);
}

/* user_setting_exists - determines if a value exists for the current user/setting specified
   @arg $config_name - the name of the configuration setting as specified $settings_user array
     in 'include/global_settings.php'
   @arg $user_id - the id of the user to check the configuration value for
   @returns (bool) - true if a value exists, false if a value does not exist */
function user_setting_exists($config_name, $user_id) {
	return sizeof(db_fetch_assoc_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($config_name, $user_id)));
}

/* read_default_user_setting - finds the default value of a user configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the default value of the configuration option */
function read_default_user_setting($config_name) {
	global $config, $settings_user;

	reset($settings_user);
	while (list($tab_name, $tab_array) = each($settings_user)) {
		if ((isset($tab_array[$config_name])) && (isset($tab_array[$config_name]['default']))) {
			return $tab_array[$config_name]['default'];
		}else{
			while (list($field_name, $field_array) = each($tab_array)) {
				if ((isset($field_array['items'])) && (isset($field_array['items'][$config_name])) && (isset($field_array['items'][$config_name]['default']))) {
					return $field_array['items'][$config_name]['default'];
				}
			}
		}
	}
}

/* read_user_setting - finds the current value of a graph configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings_user array
     in 'include/global_settings.php'
   @returns - the current value of the graph configuration option */
function read_user_setting($config_name, $default = false, $force = FALSE) {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */

	if (isset($_SESSION['sess_user_id'])) {
		$effective_uid = $_SESSION['sess_user_id'];
	}else if (isset($config['config_options_array']['export_user_id'])) {
		$effective_uid = $config['config_options_array']['export_user_id'];
	}else if ((read_config_option('auth_method') == 0)) {
		/* first attempt to get the db setting for guest */
		$effective_uid = db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username= ?', array(read_config_option('guest_user')));

		if (strlen($effective_uid) == 0) {
			$effective_uid = 0;
		}

		$db_setting = db_fetch_row_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($config_name, $effective_uid));

		if (sizeof($db_setting)) {
			return $db_setting['value'];
		}elseif ($default !== false) {
			return $default;
		}else{
			return read_default_user_setting($config_name);
		}
	}else{
		$effective_uid = 0;
	}

	if (!$force) {
		if (isset($_SESSION['sess_user_config_array'])) {
			$user_config_array = $_SESSION['sess_user_config_array'];
		}else if (isset($config['config_options_array']['export_user_id'])) {
			if (isset($config['config_user_settings_array'])) {
				$user_config_array = $config['config_user_settings_array'];
			}
		}
	}

	if (!isset($user_config_array[$config_name])) {
		$db_setting = db_fetch_row_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($config_name, $effective_uid));

		if (sizeof($db_setting)) {
			$user_config_array[$config_name] = $db_setting['value'];
		}elseif ($default !== false) {
			$user_config_array[$config_name] = $default;
		}else{
			$user_config_array[$config_name] = read_default_user_setting($config_name);
		}

		if (isset($_SESSION)) {
			$_SESSION['sess_user_config_array']   = $user_config_array;
		}else{
			$config['config_user_settings_array'] = $user_config_array;
		}
	}

	return $user_config_array[$config_name];
}

/* set_config_option - sets/updates a cacti config option with the given value.
   @arg $config_name - the name of the configuration setting as specified $settings array
   @arg $value       - the values to be saved
   @returns          - void */
function set_config_option($config_name, $value) {
	db_execute_prepared('REPLACE INTO settings SET name = ?, value = ?', array($config_name, $value));
}

/* config_value_exists - determines if a value exists for the current user/setting specified
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns (bool) - true if a value exists, false if a value does not exist */
function config_value_exists($config_name) {
	return sizeof(db_fetch_assoc_prepared('SELECT value FROM settings WHERE name= ?', array($config_name)));
}

/* read_default_config_option - finds the default value of a Cacti configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the default value of the configuration option */
function read_default_config_option($config_name) {
	global $config, $settings;

	if (is_array($settings)) {
		reset($settings);
		while (list($tab_name, $tab_array) = each($settings)) {
			if ((isset($tab_array[$config_name])) && (isset($tab_array[$config_name]['default']))) {
				return $tab_array[$config_name]['default'];
			}else{
				while (list($field_name, $field_array) = each($tab_array)) {
					if ((isset($field_array['items'])) && (isset($field_array['items'][$config_name])) && (isset($field_array['items'][$config_name]['default']))) {
						return $field_array['items'][$config_name]['default'];
					}
				}
			}
		}
	}
}

/* read_config_option - finds the current value of a Cacti configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the current value of the configuration option */
function read_config_option($config_name, $force = FALSE) {
	global $config;

	if (isset($_SESSION['sess_config_array'])) {
		$config_array = $_SESSION['sess_config_array'];
	}else if (isset($config['config_options_array'])) {
		$config_array = $config['config_options_array'];
	}

	if ((!isset($config_array[$config_name])) || ($force)) {
		$db_setting = db_fetch_row_prepared('SELECT value FROM settings WHERE name = ?', array($config_name), FALSE);

		if (isset($db_setting['value'])) {
			$config_array[$config_name] = $db_setting['value'];
		}else{
			$config_array[$config_name] = read_default_config_option($config_name);
		}

		if (isset($_SESSION)) {
			$_SESSION['sess_config_array']  = $config_array;
		}else{
			$config['config_options_array'] = $config_array;
		}
	}

	return $config_array[$config_name];
}

/* get_selected_theme - checks the user settings and if the user selected theme is set, returns it
     otherwise returns the system default.
   @returns - the themen name */
function get_selected_theme() {
	if (isset($_SESSION['selected_theme'])) {
		return $_SESSION['selected_theme'];
	}elseif (isset($_SESSION['sess_user_id'])) {
		$theme = db_fetch_cell_prepared("SELECT value FROM settings_user WHERE name='selected_theme' AND user_id = ?", array($_SESSION['sess_user_id']));

		if (!empty($theme)) {
			$_SESSION['selected_theme'] = $theme;

			return $theme;
		}
	}

	$_SESSION['selected_theme'] = read_config_option('selected_theme');

	return read_config_option('selected_theme');
}

/* form_input_validate - validates the value of a form field and Takes the appropriate action if the input
     is not valid
   @arg $field_value - the value of the form field
   @arg $field_name - the name of the $_POST field as specified in the HTML
   @arg $regexp_match - (optionally) enter a regular expression to match the value against
   @arg $allow_nulls - (bool) whether to allow an empty string as a value or not
   @arg $custom_message - (int) the ID of the message to raise upon an error which is defined in the
     $messages array in 'include/global_arrays.php'
   @returns - the original $field_value */
function form_input_validate($field_value, $field_name, $regexp_match, $allow_nulls, $custom_message = 3) {
	global $messages;

	/* write current values to the "field_values" array so we can retain them */
	$_SESSION['sess_field_values'][$field_name] = $field_value;

	if (($allow_nulls == true) && ($field_value == '')) {
		return $field_value;
	}

	if ($allow_nulls == false && $field_value == '') {
		if (read_config_option('developer_mode') == 'on') {
			cacti_log("Form Validation Failed: Variable '$field_name' does not allow nulls and variable is null", false);
		}

		raise_message($custom_message);

		$_SESSION['sess_error_fields'][$field_name] = $field_name;
	}else if ($regexp_match != '' && !preg_match('/' . $regexp_match . '/', $field_value)) {
		if (read_config_option('developer_mode') == 'on') {
			cacti_log("Form Validation Failed: Variable '$field_name' with Value '$field_value' Failed REGEX '$regexp_match'", false);
		}

		raise_message($custom_message);

		$_SESSION['sess_error_fields'][$field_name] = $field_name;
	}

	return $field_value;
}

/* check_changed - determines if a request variable has changed between page loads
   @returns - (bool) true if the value changed between loads */
function check_changed($request, $session) {
	if ((isset_request_var($request)) && (isset($_SESSION[$session]))) {
		if (get_nfilter_request_var($request) != $_SESSION[$session]) {
			return 1;
		}
	}
}

/* is_error_message - finds whether an error message has been raised and has not been outputted to the
     user
   @returns - (bool) whether the messages array contains an error or not */
function is_error_message() {
	global $config, $messages;

	if (isset($_SESSION['sess_messages'])) {
		if (is_array($_SESSION['sess_messages'])) {
			foreach (array_keys($_SESSION['sess_messages']) as $current_message_id) {
				if ($messages[$current_message_id]['type'] == 'error') { return true; }
			}
		}
	}

	return false;
}

/* is_valid_email - determines if an e-mail address passed is either a valid
     email address or distribution list.
   @arg $email - either email address or comma/semicolon delimited list of e-mails
   @returns - (bool) if true the email address is syntactically correct */
function is_valid_email($email) {
	/* check for distribution list */
	$comma = $semic = false;
	if (substr_count($email, ',')) {
		$comma = true;
		$delim = ',';
	}

	if (substr_count($email, ';')) {
		$semic = true;
		$delim = ';';
	}

	if ($semic && $comma) {
		return false;
	}elseif ($semic || $comma) {
		$members = explode($delim, $email);

		foreach ($members as $member) {
			if (preg_match("/^ *[0-9a-zA-Z]+[-_\.0-9a-zA-Z]*@([0-9a-zA-Z]+[-\.0-9a-zA-Z]+)+\.[a-zA-Z]+ *$/", $member)) {
				continue;
			}else{
				return false;
			}
		}

		return true;
	}else{
		return preg_match("/^ *[0-9a-zA-Z]+[-_\.0-9a-zA-Z]*@([0-9a-zA-Z]+[-\.0-9a-zA-Z]+)+\.[a-zA-Z]+ *$/", $email);
	}
}

/* raise_message - mark a message to be displayed to the user once display_output_messages() is called
   @arg $message_id - the ID of the message to raise as defined in $messages in 'include/global_arrays.php' */
function raise_message($message_id) {
	$_SESSION['sess_messages'][$message_id] = $message_id;
}

/* display_output_messages - displays all of the cached messages from the raise_message() function and clears
     the message cache */
function display_output_messages() {
	global $config, $messages;

	$debug_message = debug_log_return('new_graphs');

	if ($debug_message != '') {
		print "<div id='message' class='textInfo messageBox'>";
		print $debug_message;
		print '</div>';

		debug_log_clear('new_graphs');
	}elseif (isset($_SESSION['sess_messages'])) {
		$error_message = is_error_message();

		if (is_array($_SESSION['sess_messages'])) {
			foreach (array_keys($_SESSION['sess_messages']) as $current_message_id) {
				if (isset($messages[$current_message_id]['message'])) {
					eval ('$message = "' . $messages[$current_message_id]['message'] . '";');

					switch ($messages[$current_message_id]['type']) {
					case 'info':
						if ($error_message == false) {
							print "<div id='message' class='textInfo messageBox'>";
							print $message;
							print '</div>';

							/* we don't need these if there are no error messages */
							kill_session_var('sess_field_values');
						}
						break;
					case 'error':
						print "<div id='message' class='textError messageBox'>";
						print "Error: $message";
						print '</div>';
						break;
					}
				}else{
					cacti_log("ERROR: Cacti Error Message Id '$current_message_id' Not Defined", false, 'WEBUI');
				}
			}
		}else{
			display_custom_error_message($_SESSION['sess_messages']);
		}
	}

	kill_session_var('sess_messages');
}

/* display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $text - the actual text of the error message to display */
function display_custom_error_message($message) {
	print "<div id='message' class='textError messageBox'>";
	print "Error: $message";
	print '</div>';
}

/* clear_messages - clears the message cache */
function clear_messages() {
	kill_session_var('sess_messages');
}

/* kill_session_var - kills a session variable using unset() */
function kill_session_var($var_name) {
	/* register_global = on: reset local settings cache so the user sees the new settings */
	unset($_SESSION[$var_name]);
	/* register_global = off: reset local settings cache so the user sees the new settings */
	/* session_unregister is deprecated in PHP 5.3.0, unset is sufficient */
	if (version_compare(PHP_VERSION, '5.3.0', '<')) {
		session_unregister($var_name);
	} else {
		unset($var_name);
	}
}

/* array_rekey - changes an array in the form:
     '$arr[0] = array('id' => 23, 'name' => 'blah')'
     to the form
     '$arr = array(23 => 'blah')'
   @arg $array - (array) the original array to manipulate
   @arg $key - the name of the key
   @arg $key_value - the name of the key value
   @returns - the modified array */
function array_rekey($array, $key, $key_value) {
	$ret_array = array();

	if (sizeof($array) > 0) {
	foreach ($array as $item) {
		$item_key = $item[$key];

		if (is_array($key_value)) {
			for ($i=0; $i<count($key_value); $i++) {
				$ret_array[$item_key]{$key_value[$i]} = $item{$key_value[$i]};
			}
		}else{
			$ret_array[$item_key] = $item[$key_value];
		}
	}
	}

	return $ret_array;
}

/* timer start function */
function timer_start() {
	global $timer_start;

	$timer_start = microtime(true);
}

/* timer end/step function */
function timer_end($message = 'default') {
	global $timer_start;

	$timer_end = microtime(true);

	echo "TIMER: '$message' Time:'" . ($timer_end - $timer_start) . "' seconds\n";
	$timer_start = $timer_end;
}

/* strip_newlines - removes \n\r from lines
	@arg $string - the string to strip
*/
function strip_newlines($string) {
	return strtr(strtr($string, "\n", "\0"), "\r","\0");
}

/* cacti_log - logs a string to Cacti's log file or optionally to the browser
   @arg $string - the string to append to the log file
   @arg $output - (bool) whether to output the log line to the browser using print() or not
   @arg $environ - (string) tell's from where the script was called from
   @arg $level - (int) only log if above the specified log level */
function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	global $config;

	/* only log if the specificied level is reached */
	if ($level != '' && $level > read_config_option('log_verbosity')) {
		return;
	}

	/* fill in the current date for printing in the log */
	$date = date('Y-m-d H:i:s');

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = read_config_option('path_cactilog');

	/* format the message */
	if ($environ == 'POLLER') {
		$message = "$date - " . $environ . ': Poller[' . $config['poller_id'] . '] ' . $string . "\n";
	}else {
		$message = "$date - " . $environ . ' ' . $string . "\n";
	}

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option('log_verbosity') != POLLER_VERBOSITY_NONE)) {
		if ($logfile == '') {
			$logfile = $config['base_path'] . '/log/cacti.log';
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, 'a');

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$log_type = '';
		if (substr_count($string,'ERROR:'))
			$log_type = 'err';
		else if (substr_count($string,'WARNING:'))
			$log_type = 'warn';
		else if (substr_count($string,'STATS:'))
			$log_type = 'stat';
		else if (substr_count($string,'NOTICE:'))
			$log_type = 'note';

		if (strlen($log_type)) {
			if ($config['cacti_server_os'] == 'win32')
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_USER);
			else
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_SYSLOG);

			if (($log_type == 'err') && (read_config_option('log_perror'))) {
				syslog(LOG_CRIT, $environ . ': ' . $string);
			}

			if (($log_type == 'warn') && (read_config_option('log_pwarn'))) {
				syslog(LOG_WARNING, $environ . ': ' . $string);
			}

			if ((($log_type == 'stat') || ($log_type == 'note')) && (read_config_option('log_pstats'))) {
				syslog(LOG_INFO, $environ . ': ' . $string);
			}

			closelog();
		}
   }

	/* print output to standard out if required */
	if (($output == true) && (isset($_SERVER['argv'][0]))){
		print $message;
	}
}

/* tail_file - Emulates the tail function with PHP native functions.
	  It is used in 0.8.6 to speed the viewing of the Cacti log file, which
	  can be problematic in the 0.8.6 branch.

	@arg $file_name - (char constant) the name of the file to tail
		 $line_cnt  - (int constant)  the number of lines to count
		 $message_type - (int constant) the type of message to return
		 $filter - (char) the filtering expression to search for
		 $line_size - (int constant)  the average line size to use estimate bytes
									  to seek up from EOF.  Defaults to 256 bytes */
function tail_file($file_name, $number_of_lines, $message_type = -1, $filter = '', $line_size = 256) {
	$file_array = array();

	if (file_exists($file_name) && is_readable($file_name)) {
		$fp = fopen($file_name, 'r');

		/* reset back the number of bytes */
		if ($number_of_lines > 0) {
			$total_bytes = fseek($fp, -($number_of_lines * $line_size), SEEK_END);
		}

		/* load up the lines into an array */
		$i = 0;
		while (1) {
			$line    = fgets($fp);
			$display = true;

			/* determine if we are to display the line */
			switch ($message_type) {
			case -1: /* all */
				$display = true;
				break;
			case 5: /* sql calls */
				if (substr_count($line, ' SQL ')) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 1: /* stats */
				if (substr_count($line, 'STATS')) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 2: /* warnings */
				if (substr_count($line, 'WARN')) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 3: /* errors */
				if (substr_count($line, 'ERROR')) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 4: /* debug */
				if (substr_count($line, 'DEBUG')) {
					$display=true;
				}else{
					$display=false;
				}

				if (substr_count($line, ' SQL ')) {
					$display=false;
				}

				break;
			default: /* all other lines */
				$display=true;
				break;
			}

			/* match any lines that match the search string */
			if (strlen($filter)) {
				if ((substr_count(strtolower($line), strtolower($filter))) ||
					(preg_match('/' . $filter . '/i', $line))) {
					$display=true;
				}else{
					$display=false;
				}
			}

			if (feof($fp)) {
				break;
			}else if ($display) {
				$file_array[$i] = $line;
				$i++;
			}
		}

		$file_array = array_slice($file_array, -$number_of_lines, count($file_array));

		fclose($fp);
	}elseif (! file_exists($file_name)) {
		touch($file_name);
	}else{
		echo __('Error %s is not readable', $file_name);
	}

	return $file_array;
}

/* update_host_status - updates the host table with informaton about it's status.
	  It will also output to the appropriate log file when an event occurs.

	@arg $status - (int constant) the status of the host (Up/Down)
		  $host_id - (int) the host ID for the results
	     $hosts - (array) a memory resident host table for speed
		  $ping - (class array) results of the ping command			*/
function update_host_status($status, $host_id, &$hosts, &$ping, $ping_availability, $print_data_to_stdout) {
	$issue_log_message   = false;
	$ping_failure_count  = read_config_option('ping_failure_count');
	$ping_recovery_count = read_config_option('ping_recovery_count');
	/* initialize fail and recovery dates correctly */
	if ($hosts[$host_id]['status_fail_date'] == ''){
		$hosts[$host_id]['status_fail_date'] = '0000-00-00 00:00:00';
	}

	if ($hosts[$host_id]['status_rec_date'] == ''){
		$hosts[$host_id]['status_rec_date'] = '0000-00-00 00:00:00';
	}

	if ($status == HOST_DOWN) {
		/* update total polls, failed polls and availability */
		$hosts[$host_id]['failed_polls']++;
		$hosts[$host_id]['total_polls']++;
		$hosts[$host_id]['availability'] = 100 * ($hosts[$host_id]['total_polls'] - $hosts[$host_id]['failed_polls']) / $hosts[$host_id]['total_polls'];

		/* determine the error message to display */
		if (($ping_availability == AVAIL_SNMP_AND_PING) || ($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				/* snmp version 1/2 without community string assume SNMP test to be successful
				   due to backward compatibility issues */
				$hosts[$host_id]['status_last_error'] = $ping->ping_response;
			}else {
				$hosts[$host_id]['status_last_error'] = $ping->snmp_response . ', ' . $ping->ping_response;
			}
		}elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				$hosts[$host_id]['status_last_error'] = 'Device does not require SNMP';
			}else {
				$hosts[$host_id]['status_last_error'] = $ping->snmp_response;
			}
		}else {
			$hosts[$host_id]['status_last_error'] = $ping->ping_response;
		}

		/* determine if to send an alert and update remainder of statistics */
		if ($hosts[$host_id]['status'] == HOST_UP) {
			/* increment the event failure count */
			$hosts[$host_id]['status_event_count']++;

			/* if it's time to issue an error message, indicate so */
			if ($hosts[$host_id]['status_event_count'] >= $ping_failure_count) {
				/* host is now down, flag it that way */
				$hosts[$host_id]['status'] = HOST_DOWN;

				$issue_log_message = true;

				/* update the failure date only if the failure count is 1 */
				if ($hosts[$host_id]["status_event_count"] == 1 ) {
					$hosts[$host_id]['status_fail_date'] = date('Y-m-d H:i:s');
				}
			/* host is down, but not ready to issue log message */
			} else {
				/* host down for the first time, set event date */
				if ($hosts[$host_id]['status_event_count'] == 1) {
					$hosts[$host_id]['status_fail_date'] = date('Y-m-d H:i:s');
				}
			}
		/* host is recovering, put back in failed state */
		} elseif ($hosts[$host_id]['status'] == HOST_RECOVERING) {
			$hosts[$host_id]['status_event_count'] = 1;
			$hosts[$host_id]['status'] = HOST_DOWN;

		/* host was unknown and now is down */
		} elseif ($hosts[$host_id]['status'] == HOST_UNKNOWN) {
			$hosts[$host_id]['status'] = HOST_DOWN;
			$hosts[$host_id]['status_event_count'] = 0;
		} else {
			$hosts[$host_id]['status_event_count']++;
		}
	/* host is up!! */
	} else {
		/* update total polls and availability */
		$hosts[$host_id]['total_polls']++;
		$hosts[$host_id]['availability'] = 100 * ($hosts[$host_id]['total_polls'] - $hosts[$host_id]['failed_polls']) / $hosts[$host_id]['total_polls'];

		if ((($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING) ||
			($ping_availability == AVAIL_SNMP)) &&
			(!is_numeric($ping->snmp_status))) {
			$ping->snmp_status = 0.000;
		}

		if ((($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING) ||
			($ping_availability == AVAIL_PING)) &&
			(!is_numeric($ping->ping_status))) {
			$ping->ping_status = 0.000;
		}
		/* determine the ping statistic to set and do so */
		if (($ping_availability == AVAIL_SNMP_AND_PING) ||
			($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				$ping_time = 0.000;
			}else {
				/* calculate the average of the two times */
				$ping_time = ($ping->snmp_status + $ping->ping_status) / 2;
			}
		}elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
				$ping_time = 0.000;
			}else {
				$ping_time = $ping->snmp_status;
			}
		}elseif ($ping_availability == AVAIL_NONE) {
			$ping_time = 0.000;
		}else{
			$ping_time = $ping->ping_status;
		}

		/* update times as required */
		if (is_numeric($ping_time)) {
			$hosts[$host_id]['cur_time'] = $ping_time;
		}

		/* maximum time */
		if (is_numeric($ping_time) && $ping_time > $hosts[$host_id]['max_time'])
			$hosts[$host_id]['max_time'] = $ping_time;

		/* minimum time */
		if (is_numeric($ping_time) && $ping_time < $hosts[$host_id]['min_time'])
			$hosts[$host_id]['min_time'] = $ping_time;

		/* average time */
		$hosts[$host_id]['avg_time'] = (($hosts[$host_id]['total_polls']-1-$hosts[$host_id]['failed_polls'])
			* $hosts[$host_id]['avg_time'] + $ping_time) / ($hosts[$host_id]['total_polls']-$hosts[$host_id]['failed_polls']);

		/* the host was down, now it's recovering */
		if (($hosts[$host_id]['status'] == HOST_DOWN) || ($hosts[$host_id]['status'] == HOST_RECOVERING )) {
			/* just up, change to recovering */
			if ($hosts[$host_id]['status'] == HOST_DOWN) {
				$hosts[$host_id]['status'] = HOST_RECOVERING;
				$hosts[$host_id]['status_event_count'] = 1;
			} else {
				$hosts[$host_id]['status_event_count']++;
			}

			/* if it's time to issue a recovery message, indicate so */
			if ($hosts[$host_id]['status_event_count'] >= $ping_recovery_count) {
				/* host is up, flag it that way */
				$hosts[$host_id]['status'] = HOST_UP;

				$issue_log_message = true;

				/* update the recovery date only if the recovery count is 1 */
				if ($ping_recovery_count == 1) {
					$hosts[$host_id]['status_rec_date'] = date('Y-m-d H:i:s');
				}

				/* reset the event counter */
				$hosts[$host_id]['status_event_count'] = 0;
			/* host is recovering, but not ready to issue log message */
			} else {
				/* host recovering for the first time, set event date */
				if ($hosts[$host_id]['status_event_count'] == 1) {
					$hosts[$host_id]['status_rec_date'] = date('Y-m-d H:i:s');
				}
			}
		} else {
		/* host was unknown and now is up */
			$hosts[$host_id]['status'] = HOST_UP;
			$hosts[$host_id]['status_event_count'] = 0;
		}
	}
	/* if the user wants a flood of information then flood them */
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_HIGH) {
		if (($hosts[$host_id]['status'] == HOST_UP) || ($hosts[$host_id]['status'] == HOST_RECOVERING)) {
			/* log ping result if we are to use a ping for reachability testing */
			if ($ping_availability == AVAIL_SNMP_AND_PING) {
				cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
				cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} elseif ($ping_availability == AVAIL_SNMP) {
				if (($hosts[$host_id]['snmp_community'] == '') && ($hosts[$host_id]['snmp_version'] != 3)) {
					cacti_log("Device[$host_id] SNMP: Device does not require SNMP", $print_data_to_stdout);
				}else{
					cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
				}
			} else {
				cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
			}
		} else {
			if ($ping_availability == AVAIL_SNMP_AND_PING) {
				cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
				cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} elseif ($ping_availability == AVAIL_SNMP) {
				cacti_log("Device[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} else {
				cacti_log("Device[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
			}
		}
	}

	/* if there is supposed to be an event generated, do it */
	if ($issue_log_message) {
		if ($hosts[$host_id]['status'] == HOST_DOWN) {
			cacti_log("Device[$host_id] ERROR: HOST EVENT: Device is DOWN Message: " . $hosts[$host_id]['status_last_error'], $print_data_to_stdout);
		} else {
			cacti_log("Device[$host_id] NOTICE: HOST EVENT: Device Returned FROM DOWN State: ", $print_data_to_stdout);
		}
	}

	db_execute_prepared('UPDATE host SET
		status = ?,
		status_event_count = ?,
		status_fail_date = ?,
		status_rec_date = ?,
		status_last_error = ?,
		min_time = ?,
		max_time = ?,
		cur_time = ?,
		avg_time = ?,
		total_polls = ?,
		failed_polls = ?,
		availability = ?
		WHERE hostname = ?',
		array(
			$hosts[$host_id]['status'],
			$hosts[$host_id]['status_event_count'],
			$hosts[$host_id]['status_fail_date'],
			$hosts[$host_id]['status_rec_date'],
			$hosts[$host_id]['status_last_error'],
			$hosts[$host_id]['min_time'],
			$hosts[$host_id]['max_time'],
			$hosts[$host_id]['cur_time'],
			$hosts[$host_id]['avg_time'],
			$hosts[$host_id]['total_polls'],
			$hosts[$host_id]['failed_polls'],
			$hosts[$host_id]['availability'],
			$hosts[$host_id]['hostname']
		)

	);
}

/* strip_quotes - Strip single and double quotes from a string
	in addition remove non-numeric data from strings.
	@arg $result - (string) the result from the poll
	@returns - (string) the string with quotes stripped */
function strip_quotes($result) {
	/* first strip all single and double quotes from the string */
	$result = trim(trim($result), "'\"");

	/* clean off ugly non-numeric data */
	if ((!is_numeric($result)) && (!is_hexadecimal($result)) && ($result != 'U')) {
		$len = strlen($result);
		for($a=$len-1; $a>=0; $a--){
			$p = ord($result[$a]);
			if (($p > 47 && $p < 58) || ($p == 43 || $p == 45)) {
				break;
			}else{
				$result[$a] = ' ';
			}
		}

		$result = trim($result);
	}

	return($result);
}

/* is_hexadecimal - test whether a string represents a hexadecimal number,
     ignoring space and tab, and case insensitive.
   @arg $hexstr - the string to test
   @arg 1 if the argument is hex, 0 otherwise, and FALSE on error */
function is_hexadecimal($hexstr) {
	$hexstr = trim($hexstr);
	$i      = 0;
	$length = strlen($hexstr);
	while ($i < $length) {
		$part = substr($hexstr,$i,2);
		$i += 2;

		if (!preg_match('/[a-fA-F0-9]/', $part)) {
			return false;
		} elseif ($i < $length) {
			if (substr($hexstr,$i,1) != ':') {
				return false;
			}elseif ($i + 1 == $length) {
				return false;
			}
			$i++;
		}else{
			$i++;
		}
	}

	return true;
}

/* validate_result - determine's if the result value is valid or not.  If not valid returns a "U"
   @arg $result - (string) the result from the poll, the result can be modified in the call
   @returns - (int) either to result is valid or not */
function validate_result(&$result) {
	$delim_cnt = 0;
	$space_cnt = 0;

	$valid_result = false;
	$checked = false;

	/* check the easy cases first */
	/* it has no delimiters, and no space, therefore, must be numeric */
	if ((substr_count($result, ':') == 0) && (substr_count($result, '!') == 0) && (substr_count($result, ' ') == 0)) {
		$checked = true;
		if (is_numeric($result)) {
			$valid_result = true;
		} else if (is_float($result)) {
			$valid_result = true;
		} else {
			$valid_result = false;
			$result = 'U';
		}
	}
	/* it has delimiters and has no space */
	if (!$checked) {
		if (((substr_count($result, ':')) || (substr_count($result, '!')))) {
			if (substr_count($result, ' ') == 0) {
				$valid_result = true;
				$checked = true;
			}

			if (substr_count($result, ' ') != 0) {
				$checked = true;
				if (substr_count($result, ':')) {
					$delim_cnt = substr_count($result, ':');
				} else if (strstr($result, '!')) {
					$delim_cnt = substr_count($result, '!');
				}

				$space_cnt = substr_count($result, ' ');

				if ($space_cnt+1 == $delim_cnt) {
					$valid_result = true;
				} else {
					$valid_result = false;
				}
			}
		}
	}

	/* default handling */
	if (!$checked) {
		if (is_numeric($result)) {
			$valid_result = true;
		} else if (is_float($result)) {
			$valid_result = true;
		} else {
			$valid_result = false;
		}
	}

	return($valid_result);
}

/* get_full_script_path - gets the full path to the script to execute to obtain data for a
     given data source. this function does not work on SNMP actions, only script-based actions
   @arg $local_data_id - (int) the ID of the data source
   @returns - the full script path or (bool) false for an error */
function get_full_script_path($local_data_id) {
	global $config;

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		data_template_data.id,
		data_template_data.data_input_id,
		data_input.type_id,
		data_input.input_string
		FROM (data_template_data, data_input)
		WHERE data_template_data.data_input_id = data_input.id
		AND data_template_data.local_data_id = ?', array($local_data_id));

	/* snmp-actions don't have paths */
	if (($data_source['type_id'] == DATA_INPUT_TYPE_SNMP) || ($data_source['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY)) {
		return false;
	}

	$data = db_fetch_assoc_prepared("SELECT " . SQL_NO_CACHE . "
		data_input_fields.data_name,
		data_input_data.value
		FROM data_input_fields
		LEFT JOIN data_input_data
		ON (data_input_fields.id = data_input_data.data_input_field_id)
		WHERE data_input_fields.data_input_id  = ?
		AND data_input_data.data_template_data_id = ?
		AND data_input_fields.input_output = 'in'", array($data_source['data_input_id'], $data_source['id']));

	$full_path = $data_source['input_string'];

	if (sizeof($data) > 0) {
	foreach ($data as $item) {
		$full_path = str_replace('<' . $item['data_name'] . '>', escapeshellarg($item['value']), $full_path);
	}
	}

	$full_path = str_replace('<path_cacti>', $config['base_path'], $full_path);
	$full_path = str_replace('<path_snmpget>', read_config_option('path_snmpget'), $full_path);
	$full_path = str_replace('<path_php_binary>', read_config_option('path_php_binary'), $full_path);

	/* sometimes a certain input value will not have anything entered... null out these fields
	in the input string so we don't mess up the script */
	$full_path = preg_replace('/(<[A-Za-z0-9_]+>)+/', '', $full_path);

	return $full_path;
}

/* get_data_source_item_name - gets the name of a data source item or generates a new one if one does not
     already exist
   @arg $data_template_rrd_id - (int) the ID of the data source item
   @returns - the name of the data source item or an empty string for an error */
function get_data_source_item_name($data_template_rrd_id) {
	if (empty($data_template_rrd_id)) { return ''; }

	$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		data_template_rrd.data_source_name,
		data_template_data.name
		FROM (data_template_rrd, data_template_data)
		WHERE data_template_rrd.local_data_id = data_template_data.local_data_id
		AND data_template_rrd.id = ?', array($data_template_rrd_id));

	/* use the cacti ds name by default or the user defined one, if entered */
	if (empty($data_source['data_source_name'])) {
		/* limit input to 19 characters */
		$data_source_name = clean_up_name($data_source['name']);
		$data_source_name = substr(strtolower($data_source_name),0,(19-strlen($data_template_rrd_id))) . $data_template_rrd_id;

		return $data_source_name;
	}else{
		return $data_source['data_source_name'];
	}
}

/* get_data_source_path - gets the full path to the .rrd file associated with a given data source
   @arg $local_data_id - (int) the ID of the data source
   @arg $expand_paths - (bool) whether to expand the <path_rra> variable into its full path or not
   @returns - the full path to the data source or an empty string for an error */
function get_data_source_path($local_data_id, $expand_paths) {
	global $config;
	static $data_source_path_cache = array();

	if (empty($local_data_id)) { 
		return ''; 
	}

	if (!isset($data_source_path_cache[$local_data_id])) {
		$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' name, data_source_path FROM data_template_data WHERE local_data_id = ?', array($local_data_id));

		if (sizeof($data_source) > 0) {
			if (empty($data_source['data_source_path'])) {
				/* no custom path was specified */
				$data_source_path = generate_data_source_path($local_data_id);
			}else{
				if (!strstr($data_source['data_source_path'], '/')) {
					$data_source_path = '<path_rra>/' . $data_source['data_source_path'];
				}else{
					$data_source_path = $data_source['data_source_path'];
				}
			}

			/* whether to show the "actual" path or the <path_rra> variable name (for edit boxes) */
			if ($expand_paths == true) {
				$data_source_path = str_replace('<path_rra>/', (read_config_option('storage_location') ? './' : $config['rra_path'] . '/'), $data_source_path);
			}

			$data_source_path_cache[$local_data_id] = $data_source_path;

			return $data_source_path;
		}
	}else{
		return $data_source_path_cache[$local_data_id];
	}
}

/* stri_replace - a case insensitive string replace
   @arg $find - needle
   @arg $replace - replace needle with this
   @arg $string - haystack
   @returns - the original string with '$find' replaced by '$replace' */
function stri_replace($find, $replace, $string) {
	$parts = explode(strtolower($find), strtolower($string));

	$pos = 0;

	foreach ($parts as $key=>$part) {
		$parts[$key] = substr($string, $pos, strlen($part));
		$pos += strlen($part) + strlen($find);
	}

	return (join($replace, $parts));
}

/* clean_up_name - runs a string through a series of regular expressions designed to
     eliminate "bad" characters
   @arg $string - the string to modify/clean
   @returns - the modified string */
function clean_up_name($string) {
	$string = preg_replace('/[\s\.]+/', '_', $string);
	$string = preg_replace('/[^a-zA-Z0-9_]+/', '', $string);
	$string = preg_replace('/_{2,}/', '_', $string);

	return $string;
}

/* clean_up_file name - runs a string through a series of regular expressions designed to
     eliminate "bad" characters
   @arg $string - the string to modify/clean
   @returns - the modified string */
function clean_up_file_name($string) {
	$string = preg_replace('/[\s\.]+/', '_', $string);
	$string = preg_replace('/[^a-zA-Z0-9_-]+/', '', $string);
	$string = preg_replace('/_{2,}/', '_', $string);

	return $string;
}

/* clean_up_path - takes any path and makes sure it contains the correct directory
     separators based on the current operating system
   @arg $path - the path to modify
   @returns - the modified path */
function clean_up_path($path) {
	global $config;

	if ($config['cacti_server_os'] == 'unix' or read_config_option('using_cygwin') == 'on' or read_config_option('storage_location')) {
		$path = str_replace("\\", '/', $path);
	}elseif ($config['cacti_server_os'] == 'win32') {
		$path = str_replace('/', "\\", $path);

	}

	return $path;
}

/* get_data_source_title - returns the title of a data source without using the title cache
   @arg $local_data_id - (int) the ID of the data source to get a title for
   @returns - the data source title */
function get_data_source_title($local_data_id) {
	$data = db_fetch_row_prepared('SELECT
		data_local.host_id,
		data_local.snmp_query_id,
		data_local.snmp_index,
		data_template_data.name
		FROM (data_template_data, data_local)
		WHERE data_template_data.local_data_id = data_local.id
		AND data_local.id = ?', array($local_data_id));

	if ((strstr($data['name'], '|')) && (!empty($data['host_id']))) {
		$data['name'] = substitute_data_input_data($data['name'], '', $local_data_id);
		return expand_title($data['host_id'], $data['snmp_query_id'], $data['snmp_index'], $data['name']);
	}else{
		return $data['name'];
	}
}

/* get_device_name - returns the description of the device in cacti host table
   @arg $host_id - (int) the ID of the device to get a decription for
   @returns - the device name */
function get_device_name($host_id) {
	return db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id));
}

/* get_graph_title - returns the title of a graph without using the title cache
   @arg $local_graph_id - (int) the ID of the graph to get a title for
   @returns - the graph title */
function get_graph_title($local_graph_id) {
	$graph = db_fetch_row_prepared('SELECT
		graph_local.host_id,
		graph_local.snmp_query_id,
		graph_local.snmp_index,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title
		FROM (graph_templates_graph, graph_local)
		WHERE graph_templates_graph.local_graph_id = graph_local.id
		AND graph_local.id = ?', array($local_graph_id));

	if (sizeof($graph)) {
		if ((strstr($graph['title'], '|')) && (!empty($graph['host_id']))) {
			$graph['title'] = substitute_data_input_data($graph['title'], $graph, 0);
			return expand_title($graph['host_id'], $graph['snmp_query_id'], $graph['snmp_index'], $graph['title']);
		}else{
			return $graph['title'];
		}
	}else{
		return '';
	}
}

/* get_username - returns the username for the selected user
   @arg $user_id - (int) the ID of the user
   @returns - the username */
function get_username($user_id) {
	return db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id));
}

/* generate_data_source_path - creates a new data source path from scratch using the first data source
     item name and updates the database with the new value
   @arg $local_data_id - (int) the ID of the data source to generate a new path for
   @returns - the new generated path */
function generate_data_source_path($local_data_id) {
	global $config;

	$host_part = ''; $ds_part = '';

	$extended_paths = read_config_option('extended_paths');

	/* try any prepend the name with the host description */
	$host = db_fetch_row_prepared('SELECT
		host.id,
		host.description
		FROM (host, data_local)
		WHERE data_local.host_id = host.id
		AND data_local.id = ?
		LIMIT 1', array($local_data_id));

	$host_name = $host['description'];
	$host_id   = $host['id'];

	/* put it all together using the local_data_id at the end */
	if ($extended_paths == 'on') {
		$new_path = "<path_rra>/$host_id/$local_data_id.rrd";
	}else{
		if (!empty($host_name)) {
			$host_part = strtolower(clean_up_file_name($host_name)) . '_';
		}

		/* then try and use the internal DS name to identify it */
		$data_source_rrd_name = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE local_data_id = ?
			ORDER BY id', array($local_data_id));

		if (!empty($data_source_rrd_name)) {
			$ds_part = strtolower(clean_up_file_name($data_source_rrd_name));
		}else{
			$ds_part = 'ds';
		}

		$new_path = "<path_rra>/$host_part$ds_part" . '_' . "$local_data_id.rrd";
	}

	/* update our changes to the db */
	db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE local_data_id = ?', array($new_path, $local_data_id));

	return $new_path;
}

/* generate graph_best_cf - takes the requested consolidation function and maps against
     the list of available consolidation functions for the consolidation functions and returns
     the most appropriate.  Typically, this will be the requested value
    @arg $data_template_id
    @arg $requested_cf
    @returns - the best cf to use */
function generate_graph_best_cf($local_data_id, $requested_cf) {

	if ($local_data_id > 0) {
		$avail_cf_functions = get_rrd_cfs($local_data_id);
		/* workaround until we have RRA presets in 0.8.8 */
		if (sizeof($avail_cf_functions)) {
			/* check through the cf's and get the best */
			foreach($avail_cf_functions as $cf) {
				if ($cf == $requested_cf) {
					return $requested_cf;
				}
			}

			/* if none was found, take the first */
			return $avail_cf_functions[0];
		}
	}

	/* if you can not figure it out return average */
	return '1';
}

/* get_rrd_cfs - reads the RRDfile and get's the RRA's stored in it.
    @arg $local_data_id
    @returns - array of the CF functions */
function get_rrd_cfs($local_data_id) {
	global $rrd_cfs, $consolidation_functions;

	$rrdfile = get_data_source_path($local_data_id, TRUE);

	if (!isset($rrd_cfs)) {
		$rrd_cfs = array();
	}else if (array_key_exists($local_data_id, $rrd_cfs)) {
		return $rrd_cfs[$local_data_id];
	}

	$cfs = array();

	$output = @rrdtool_execute("info $rrdfile", FALSE, RRDTOOL_OUTPUT_STDOUT);

	/* search for
	 * 		rra[0].cf = 'LAST'
	 * or similar
	 */
	if (strlen($output)) {
		$output = explode("\n", $output);

		if (sizeof($output)) {
			foreach($output as $line) {
				if (substr_count($line, '.cf')) {
					$values = explode('=',$line);

					if (!in_array(trim($values[1], '" '), $cfs)) {
						$cfs[] = trim($values[1], '" ');
					}
				}
			}
		}
	}

	$new_cfs = array();

	if (sizeof($cfs)) {
		foreach($cfs as $cf) {
			switch($cf) {
			case 'AVG':
			case 'AVERAGE':
				$new_cfs[] = array_search('AVERAGE', $consolidation_functions);
				break;
			case 'MIN':
				$new_cfs[] = array_search('MIN', $consolidation_functions);
				break;
			case 'MAX':
				$new_cfs[] = array_search('MAX', $consolidation_functions);
				break;
			case 'LAST':
				$new_cfs[] = array_search('LAST', $consolidation_functions);
				break;
			}
		}
	}

	$rrd_cfs[$local_data_id] = $new_cfs;

	return $new_cfs;
}

/* generate_graph_def_name - takes a number and turns each digit into its letter-based
     counterpart for RRDTool DEF names (ex 1 -> a, 2 -> b, etc)
   @arg $graph_item_id - (int) the ID to generate a letter-based representation of
   @returns - a letter-based representation of the input argument */
function generate_graph_def_name($graph_item_id) {
	$lookup_table = array('a','b','c','d','e','f','g','h','i','j');

	$result = '';

	for ($i=0; $i<strlen(strval($graph_item_id)); $i++) {
		$result .= $lookup_table{substr(strval($graph_item_id), $i, 1)};
	}

	if ($result == 'cf') {
		return 'zcf';
	}else{
		return $result;
	}
}

/* generate_data_input_field_sequences - re-numbers the sequences of each field associated
     with a particular data input method based on its position within the input string
   @arg $string - the input string that contains the field variables in a certain order
   @arg $data_input_id - (int) the ID of the data input method */
function generate_data_input_field_sequences($string, $data_input_id) {
	global $config, $registered_cacti_names;

	if (preg_match_all('/<([_a-zA-Z0-9]+)>/', $string, $matches)) {
		$j = 0;
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$j++;
				db_execute_prepared("UPDATE data_input_fields set sequence = ? WHERE data_input_id = ? AND input_output = 'in' and data_name = ?", array($j, $data_input_id, $matches[1][$i]));
			}
		}
	}
}

/* move_graph_group - takes a graph group (parent+children) and swaps it with another graph
     group
   @arg $graph_template_item_id - (int) the ID of the (parent) graph item that was clicked
   @arg $graph_group_array - (array) an array containing the graph group to be moved
   @arg $target_id - (int) the ID of the (parent) graph item of the target group
   @arg $direction - ('next' or 'previous') whether the graph group is to be swapped with
      group above or below the current group */
function move_graph_group($graph_template_item_id, $graph_group_array, $target_id, $direction) {
	$graph_item = db_fetch_row_prepared('SELECT local_graph_id, graph_template_id FROM graph_templates_item WHERE id = ?', array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	}else{
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	$graph_items = db_fetch_assoc_prepared("SELECT id, sequence FROM graph_templates_item WHERE $sql_where ORDER BY sequence");

	/* get a list of parent+children of our target group */
	$target_graph_group_array = get_graph_group($target_id);

	/* if this "parent" item has no children, then treat it like a regular gprint */
	if (sizeof($target_graph_group_array) == 0) {
		if ($direction == 'next') {
			move_item_down('graph_templates_item', $graph_template_item_id, $sql_where);
		}elseif ($direction == 'previous') {
			move_item_up('graph_templates_item', $graph_template_item_id, $sql_where);
		}

		return;
	}

	/* start the sequence at '1' */
	$sequence_counter = 1;

	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $item) {
		/* check to see if we are at the "target" spot in the loop; if we are, update the sequences and move on */
		if ($target_id == $item['id']) {
			if ($direction == 'next') {
				$group_array1 = $target_graph_group_array;
				$group_array2 = $graph_group_array;
			}elseif ($direction == 'previous') {
				$group_array1 = $graph_group_array;
				$group_array2 = $target_graph_group_array;
			}

			while (list($sequence,$graph_template_item_id) = each($group_array1)) {
				db_execute_prepared('UPDATE graph_templates_item SET sequence = ? WHERE id = ?', array($sequence_counter, $graph_template_item_id));

				/* propagate to ALL graphs using this template */
				if (empty($graph_item['local_graph_id'])) {
					db_execute_prepared('UPDATE graph_templates_item SET sequence = ? WHERE local_graph_template_item_id = ?', array($sequence_counter, $graph_template_item_id));
				}

				$sequence_counter++;
			}

			while (list($sequence,$graph_template_item_id) = each($group_array2)) {
				db_execute_prepared('UPDATE graph_templates_item SET sequence = ? WHERE id = ?', array($sequence_counter, $graph_template_item_id));

				/* propagate to ALL graphs using this template */
				if (empty($graph_item['local_graph_id'])) {
					db_execute_prepared('UPDATE graph_templates_item SET sequence = ? WHERE local_graph_template_item_id = ?', array($sequence_counter, $graph_template_item_id));
				}

				$sequence_counter++;
			}
		}

		/* make sure to "ignore" the items that we handled above */
		if ((!isset($graph_group_array{$item['id']})) && (!isset($target_graph_group_array{$item['id']}))) {
			db_execute_prepared('UPDATE graph_templates_item SET sequence = ? WHERE id = ?', array($sequence_counter, $item['id']));
			$sequence_counter++;
		}
	}
	}
}

/* get_graph_group - returns an array containing each item in the graph group given a single
     graph item in that group
   @arg $graph_template_item_id - (int) the ID of the graph item to return the group of
   @returns - (array) an array containing each item in the graph group */
function get_graph_group($graph_template_item_id) {
	global $graph_item_types;

	$graph_item = db_fetch_row_prepared('SELECT graph_type_id, sequence, local_graph_id, graph_template_id FROM graph_templates_item WHERE id = ?', array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	}else{
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	/* a parent must NOT be the following graph item types */
	if (preg_match('/(GPRINT|VRULE|HRULE|COMMENT)/', $graph_item_types{$graph_item['graph_type_id']})) {
		return;
	}

	$graph_item_children_array = array();

	/* put the parent item in the array as well */
	$graph_item_children_array[$graph_template_item_id] = $graph_template_item_id;

	$graph_items = db_fetch_assoc("SELECT id, graph_type_id FROM graph_templates_item WHERE sequence > " . $graph_item['sequence'] . " AND $sql_where ORDER BY sequence");

	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $item) {
		if ($graph_item_types{$item['graph_type_id']} == 'GPRINT') {
			/* a child must be a GPRINT */
			$graph_item_children_array{$item['id']} = $item['id'];
		}else{
			/* if not a GPRINT then get out */
			return $graph_item_children_array;
		}
	}
	}

	return $graph_item_children_array;
}

/* get_graph_parent - returns the ID of the next or previous parent graph item id
   @arg $graph_template_item_id - (int) the ID of the current graph item
   @arg $direction - ('next' or 'previous') whether to find the next or previous parent
   @returns - (int) the ID of the next or previous parent graph item id */
function get_graph_parent($graph_template_item_id, $direction) {
	$graph_item = db_fetch_row_prepared('SELECT sequence, local_graph_id, graph_template_id FROM graph_templates_item WHERE id = ?', array($graph_template_item_id));

	if (empty($graph_item['local_graph_id'])) {
		$sql_where = 'graph_template_id = ' . $graph_item['graph_template_id'] . ' AND local_graph_id = 0';
	}else{
		$sql_where = 'local_graph_id = ' . $graph_item['local_graph_id'];
	}

	if ($direction == 'next') {
		$sql_operator = '>';
		$sql_order = 'ASC';
	}elseif ($direction == 'previous') {
		$sql_operator = '<';
		$sql_order = 'DESC';
	}

	$next_parent_id = db_fetch_cell("SELECT id FROM graph_templates_item WHERE sequence $sql_operator " . $graph_item['sequence'] . " AND graph_type_id != 9 AND $sql_where ORDER BY sequence $sql_order LIMIT 1");

	if (empty($next_parent_id)) {
		return 0;
	}else{
		return $next_parent_id;
	}
}

/* get_item - returns the ID of the next or previous item id
   @arg $tblname - the table name that contains the target id
   @arg $field - the field name that contains the target id
   @arg $startid - (int) the current id
   @arg $lmt_query - an SQL "where" clause to limit the query
   @arg $direction - ('next' or 'previous') whether to find the next or previous item id
   @returns - (int) the ID of the next or previous item id */
function get_item($tblname, $field, $startid, $lmt_query, $direction) {
	if ($direction == 'next') {
		$sql_operator = '>';
		$sql_order = 'ASC';
	}elseif ($direction == 'previous') {
		$sql_operator = '<';
		$sql_order = 'DESC';
	}

	$current_sequence = db_fetch_cell_prepared("SELECT $field FROM $tblname WHERE id = ?", array($startid));
	$new_item_id = db_fetch_cell("SELECT id FROM $tblname WHERE $field $sql_operator $current_sequence " . ($lmt_query != '' ? " AND $lmt_query":"") . " ORDER BY $field $sql_order LIMIT 1");

	if (empty($new_item_id)) {
		return $startid;
	}else{
		return $new_item_id;
	}
}

/* get_sequence - returns the next available sequence id
   @arg $id - (int) the current id
   @arg $field - the field name that contains the target id
   @arg $table_name - the table name that contains the target id
   @arg $group_query - an SQL "where" clause to limit the query
   @returns - (int) the next available sequence id */
function get_sequence($id, $field, $table_name, $group_query) {
	if (empty($id)) {
		$data = db_fetch_row("SELECT max($field)+1 AS seq FROM $table_name WHERE $group_query");

		if ($data['seq'] == '') {
			return 1;
		}else{
			return $data['seq'];
		}
	}else{
		$data = db_fetch_row_prepared("SELECT $field FROM $table_name WHERE id = ?", array($id));
		return $data[$field];
	}
}

/* move_item_down - moves an item down by swapping it with the item below it
   @arg $table_name - the table name that contains the target id
   @arg $current_id - (int) the current id
   @arg $group_query - an SQL "where" clause to limit the query */
function move_item_down($table_name, $current_id, $group_query = '') {
	$next_item = get_item($table_name, 'sequence', $current_id, $group_query, 'next');

	$sequence = db_fetch_cell_prepared("SELECT sequence FROM $table_name WHERE id = ?", array($current_id));
	$sequence_next = db_fetch_cell_prepared("SELECT sequence FROM $table_name WHERE id = ?", array($next_item));
	db_execute_prepared("UPDATE $table_name SET sequence = ? WHERE id = ?", array($sequence_next, $current_id));
	db_execute_prepared("UPDATE $table_name SET sequence = ? WHERE id = ?", array($sequence, $next_item));
}

/* move_item_up - moves an item down by swapping it with the item above it
   @arg $table_name - the table name that contains the target id
   @arg $current_id - (int) the current id
   @arg $group_query - an SQL "where" clause to limit the query */
function move_item_up($table_name, $current_id, $group_query = '') {
	$last_item = get_item($table_name, 'sequence', $current_id, $group_query, 'previous');

	$sequence = db_fetch_cell_prepared("SELECT sequence FROM $table_name WHERE id = ?", array($current_id));
	$sequence_last = db_fetch_cell_prepared("SELECT sequence FROM $table_name WHERE id = ?", array($last_item));
	db_execute_prepared("UPDATE $table_name set sequence = ? WHERE id = ?", array($sequence_last, $current_id));
	db_execute_prepared("UPDATE $table_name set sequence = ? WHERE id = ?", array($sequence, $last_item));
}

/* exec_into_array - executes a command and puts each line of its output into
     an array
   @arg $command_line - the command to execute
   @returns - (array) an array containing the command output */
function exec_into_array($command_line) {
	$out = array();
	$err = 0;
	exec($command_line,$out,$err);

	$command_array = array();

	for($i=0; list($key, $value) = each($out); $i++) {
		$command_array[$i] = $value;
	}

	return $command_array;
}

/* get_web_browser - determines the current web browser in use by the client
   @returns - ('ie' or 'moz' or 'other') */
function get_web_browser() {
	if (!empty($_SERVER['HTTP_USER_AGENT'])) {
		if (stristr($_SERVER['HTTP_USER_AGENT'], 'Mozilla') && (!(stristr($_SERVER['HTTP_USER_AGENT'], 'compatible')))) {
			return 'moz';
		}elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
			return 'ie';
		}else{
			return 'other';
		}
	}else{
		return 'other';
	}
}

/* get_graph_tree_array - returns a list of graph trees taking permissions into account if
     necessary
   @arg $return_sql - (bool) Whether to return the SQL to create the dropdown rather than an array
	@arg $force_refresh - (bool) Force the refresh of the array from the database
	@arg $edit - (bool) Shows the content even though the graph may not be published
   @returns - (array) an array containing a list of graph trees */
function get_graph_tree_array($return_sql = false, $force_refresh = false, $edit = false) {
	return get_allowed_trees($edit, $return_sql);
}

/* draw_login_status - provides a consistent login status page for all pages that use it */
function draw_login_status($using_guest_account = false) {
	global $config;

	$guest_account = db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ?', array(read_config_option('guest_user')));
	$auth_method   = read_config_option('auth_method');

	if (isset($_SESSION['sess_user_id']) && $_SESSION['sess_user_id'] == $guest_account) {
		api_plugin_hook('nav_login_before');
		print __('Logged in as') . " <span id='user' class='user usermenuup'>". __('guest') . "</span></div><div><ul class='menuoptions' style='display:none;'><li><a href='" . $config['url_path'] . "index.php'>" . __('Login as Regular User') . "</a></li></ul>\n";
		api_plugin_hook('nav_login_after');
	}elseif (isset($_SESSION['sess_user_id']) && $using_guest_account == false) {
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
		api_plugin_hook('nav_login_before');
		print __('Logged in as') . " <span id='user' class='user usermenuup'>" . htmlspecialchars($user['username'], ENT_QUOTES) . 
			"</span></div><div><ul class='menuoptions' style='display:none;'>" . 
				(is_realm_allowed(20) ? "<li><a href='" . $config['url_path'] . "auth_profile.php?action=edit'>" . __('Edit Profile') . "</a></li>":"") . 
				($user['password_change'] == 'on' && $user['realm'] == 0 ? "<li><a href='" . $config['url_path'] . "auth_changepassword.php'>" . __('Change Password') . "</a></li>":'') . 
				($auth_method > 0 ? "<li><a href='" . $config['url_path'] . "logout.php'>" . __('Logout') . "</a></li>":"") . 
			"</ul>\n";

		api_plugin_hook('nav_login_after');
	}
}

/* draw_navigation_text - determines the top header navigation text for the current page and displays it to
   @arg $type - (string) Either 'url' or 'title'
   @returns (string> Either the navigation text or title */
function draw_navigation_text($type = 'url') {
	global $config;

	$nav_level_cache = (isset($_SESSION['sess_nav_level_cache']) ? $_SESSION['sess_nav_level_cache'] : array());

	$nav = array(
		'auth_profile.php:' => array(
			'title' => __('User Profile (Edit)'),
			'mapping' => '', 
			'url' => '', 
			'level' => '0'
			),
		'auth_profile.php:edit' => array(
			'title' => __('User Profile (Edit)'),
			'mapping' => '', 
			'url' => '', 
			'level' => '0'
			),
		'graph_view.php:' => array(
			'title' => __('Graphs'), 
			'mapping' => '', 
			'url' => 'graph_view.php', 
			'level' => '0'
			),
		'graph_view.php:tree' => array(
			'title' => __('Tree Mode'), 
			'mapping' => 'graph_view.php:', 
			'url' => 'graph_view.php?action=tree', 
			'level' => '0'
			),
		'graph_view.php:tree_content' => array(
			'title' => __('Tree Mode'), 
			'mapping' => 'graph_view.php:', 
			'url' => 'graph_view.php?action=tree', 
			'level' => '0'
			),
		'graph_view.php:list' => array(
			'title' => __('List Mode'), 
			'mapping' => '', 
			'url' => 'graph_view.php?action=list', 
			'level' => '0'
			),
		'graph_view.php:preview' => array(
			'title' => __('Preview Mode'), 
			'mapping' => '', 
			'url' => 'graph_view.php?action=preview', 
			'level' => '0'
			),
		'graph.php:' => array(
			'title' => '|current_graph_title|', 
			'mapping' => 'graph_view.php:,?', 
			'level' => '2'
			),
		'graph.php:view' => array(
			'title' => '|current_graph_title|', 
			'mapping' => 'graph_view.php:,?', 
			'level' => '2'
			),
		'index.php:' => array(
			'title' => __('Console'), 
			'mapping' => '', 
			'url' => $config['url_path'] . 'index.php', 
			'level' => '0'
			),
		'index.php:login' => array(
			'title' => __('Console'), 
			'mapping' => '', 
			'url' => $config['url_path'] . 'index.php', 
			'level' => '0'
			),
		'graphs.php:' => array(
			'title' => __('Graph Management'), 
			'mapping' => 'index.php:', 
			'url' => 'graphs.php', 
			'level' => '1'
			),
		'graphs.php:graph_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,graphs.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graphs.php:graph_diff' => array(
			'title' => __('Change Graph Template'), 
			'mapping' => 'index.php:,graphs.php:,graphs.php:graph_edit', 
			'url' => '', 
			'level' => '3'
			),
		'graphs.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,graphs.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graphs_items.php:item_edit' => array(
			'title' => __('Graph Items'), 
			'mapping' => 'index.php:,graphs.php:,graphs.php:graph_edit', 
			'url' => '', 
			'level' => '3'
			),
		'graphs_new.php:' => array(
			'title' => __('Create New Graphs'), 
			'mapping' => 'index.php:', 
			'url' => 'graphs_new.php', 
			'level' => '1'
			),
		'graphs_new.php:save' => array(
			'title' => __('Create Graphs from Data Query'), 
			'mapping' => 'index.php:,graphs_new.php:', 
			'url' => '', 
			'level' => '2'
			),
		'gprint_presets.php:' => array(
			'title' => __('GPRINT Presets'), 
			'mapping' => 'index.php:', 
			'url' => 'gprint_presets.php', 
			'level' => '1'
			),
		'gprint_presets.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,gprint_presets.php:', 
			'url' => '', 
			'level' => '2'
			),
		'gprint_presets.php:remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,gprint_presets.php:', 
			'url' => '', 
			'level' => '2'
			),
		'cdef.php:' => array(
			'title' => __('CDEFs'), 
			'mapping' => 'index.php:', 
			'url' => 'cdef.php', 
			'level' => '1'
			),
		'cdef.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,cdef.php:', 
			'url' => '', 
			'level' => '2'
			),
		'cdef.php:remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,cdef.php:', 
			'url' => '', 
			'level' => '2'
			),
		'cdef.php:item_edit' => array(
			'title' => __('CDEF Items'), 
			'mapping' => 'index.php:,cdef.php:,cdef.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'cdef.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,cdef.php:', 
			'url' => '', 
			'level' => '2'
			),
		'clog.php:' => array(
			'title' => __('View Cacti Log'), 
			'mapping' => '', 
			'url' => 'clog.php', 
			'level' => '0'
			),
		'clog.php:preview' => array(
			'title' => __('View Cacti Log'), 
			'mapping' => '', 
			'url' => 'clog.php', 
			'level' => '0'
			),
		'clog_user.php:' => array(
			'title' => __('View Cacti Log'), 
			'mapping' => '', 
			'url' => 'clog_user.php', 
			'level' => '0'
			),
		'clog_user.php:preview' => array(
			'title' => __('View Cacti Log'), 
			'mapping' => '', 
			'url' => 'clog_user.php', 
			'level' => '0'
			),
		'tree.php:' => array(
			'title' => __('Graph Trees'), 
			'mapping' => 'index.php:', 
			'url' => 'tree.php', 
			'level' => '1'
			),
		'tree.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,tree.php:', 
			'url' => '', 
			'level' => '2'
			),
		'color.php:' => array(
			'title' => __('Colors'), 
			'mapping' => 'index.php:', 
			'url' => 'color.php', 
			'level' => '1'
			),
		'color.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,color.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graph_templates.php:' => array(
			'title' => __('Graph Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'graph_templates.php', 
			'level' => '1'
			),
		'graph_templates.php:template_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,graph_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graph_templates.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,graph_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graph_templates_items.php:item_edit' => array(
			'title' => __('Graph Template Items'), 
			'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit', 
			'url' => '', 
			'level' => '3'
			),
		'graph_templates_inputs.php:input_edit' => array(
			'title' => __('Graph Item Inputs'), 
			'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit', 
			'url' => '', 
			'level' => '3'
			),
		'graph_templates_inputs.php:input_remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit', 
			'url' => '', 
			'level' => '3'
			),
		'host_templates.php:' => array(
			'title' => __('Device Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'host_templates.php', 
			'level' => '1'
			),
		'host_templates.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,host_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'host_templates.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,host_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'graph_templates.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,graph_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_templates.php:' => array(
			'title' => __('Data Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'data_templates.php', 
			'level' => '1'
			),
		'data_templates.php:template_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,data_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_templates.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,data_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_sources.php:' => array(
			'title' => __('Data Sources'), 
			'mapping' => 'index.php:', 
			'url' => 'data_sources.php', 
			'level' => '1'
			),
		'data_sources.php:ds_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,data_sources.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_sources.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,data_sources.php:', 
			'url' => '', 
			'level' => '2'
			),
		'host.php:' => array(
			'title' => __('Devices'), 
			'mapping' => 'index.php:', 
			'url' => 'host.php', 
			'level' => '1'
			),
		'host.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,host.php:', 
			'url' => '', 
			'level' => '2'
			),
		'host.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,host.php:', 
			'url' => '', 
			'level' => '2'
			),
		'rra.php:' => array(
			'title' => __('Round Robin Archives'), 
			'mapping' => 'index.php:', 
			'url' => 'rra.php', 
			'level' => '1'
			),
		'rra.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,rra.php:', 
			'url' => '', 
			'level' => '2'
			),
		'rra.php:remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,rra.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_input.php:' => array(
			'title' => __('Data Input Methods'), 
			'mapping' => 'index.php:', 
			'url' => 'data_input.php', 
			'level' => '1'
			),
		'data_input.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,data_input.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_input.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,data_input.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_input.php:remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,data_input.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_input.php:field_edit' => array(
			'title' => __('Data Input Fields'), 
			'mapping' => 'index.php:,data_input.php:,data_input.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'data_input.php:field_remove' => array(
			'title' => __('(Remove Item)'), 
			'mapping' => 'index.php:,data_input.php:,data_input.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'data_queries.php:' => array(
			'title' => __('Data Queries'), 
			'mapping' => 'index.php:', 
			'url' => 'data_queries.php', 
			'level' => '1'
			),
		'data_queries.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,data_queries.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_queries.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,data_queries.php:', 
			'url' => '', 
			'level' => '2'
			),
		'data_queries.php:item_edit' => array(
			'title' => __('Associated Graph Templates'), 
			'mapping' => 'index.php:,data_queries.php:,data_queries.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'data_queries.php:item_remove' => array(
			'title' => __('(Remove Item)'), 
			'mapping' => 'index.php:,data_queries.php:,data_queries.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'rrdcleaner.php:' => array(
			'title' => __('RRD Cleaner'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'rrdcleaner.php', 
			'level' => '2'
			),
		'rrdcleaner.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,rrdcleaner.php:', 
			'url' => 'rrdcleaner.php?action=actions', 
			'level' => '2'
			),
		'rrdcleaner.php:restart' => array(
			'title' => __('List unused Files'), 
			'mapping' => 'rrdcleaner.php:', 
			'url' => 'rrdcleaner.php?action=restart', 
			'level' => '2'
			),
		'utilities.php:' => array(
			'title' => __('Utilities'), 
			'mapping' => 'index.php:', 
			'url' => 'utilities.php', 
			'level' => '1'
			),
		'utilities.php:view_poller_cache' => array(
			'title' => __('View Poller Cache'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_snmp_cache' => array(
			'title' => __('View Data Query Cache'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:clear_poller_cache' => array(
			'title' => __('View Poller Cache'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_logfile' => array(
			'title' => __('View Cacti Log File'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:clear_logfile' => array(
			'title' => __('Clear Cacti Log File'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_user_log' => array(
			'title' => __('View User Log File'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:clear_user_log' => array(
			'title' => __('Clear User Log File'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_tech' => array(
			'title' => __('Technical Support'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_boost_status' => array(
			'title' => __('Boost Status'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_snmpagent_cache' => array(
			'title' => __('View SNMP Agent Cache'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'utilities.php:view_snmpagent_events' => array(
			'title' => __('View SNMP Agent Notification Log'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'utilities.php', 
			'level' => '2'
			),
		'vdef.php:' => array(
			'title' => __('VDEFs'), 
			'mapping' => 'index.php:', 
			'url' => 'vdef.php', 
			'level' => '1'
			),
		'vdef.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,vdef.php:', 
			'url' => 'vdef.php', 
			'level' => '2'
			),
		'vdef.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,vdef.php:', 
			'url' => 'vdef.php', 
			'level' => '2'
			),
		'vdef.php:remove' => array(
			'title' => __('(Remove)'), 
			'mapping' => 'index.php:,vdef.php:', 
			'url' => 'vdef.php', 
			'level' => '2'
			),
		'vdef.php:item_edit' => array(
			'title' => __('VDEF Items'), 
			'mapping' => 'index.php:,vdef.php:,vdef.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'managers.php:' => array(
			'title' => __('View SNMP Notification Receivers'), 
			'mapping' => 'index.php:,utilities.php:', 
			'url' => 'managers.php', 
			'level' => '2'
			),
		'managers.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,utilities.php:,managers.php:', 
			'url' => '', 
			'level' => '3'
			),
		'settings.php:' => array(
			'title' => __('Cacti Settings'), 
			'mapping' => 'index.php:', 
			'url' => 'settings.php', 
			'level' => '1'
			),
		'user_admin.php:' => array(
			'title' => __('Users'), 
			'mapping' => 'index.php:', 
			'url' => 'user_admin.php', 
			'level' => '1'
			),
		'user_admin.php:user_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,user_admin.php:', 
			'url' => '', 
			'level' => '2'
			),
		'user_admin.php:actions' => array(
			'title' => __('(Action)'), 
			'mapping' => 'index.php:,user_admin.php:', 
			'url' => '', 
			'level' => '2'
			),
		'user_domains.php:' => array(
			'title' => __('User Domains'), 
			'mapping' => 'index.php:', 
			'url' => 'user_domains.php', 
			'level' => '1'
			),
		'user_domains.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'user_domains.php:,index.php:', 
			'url' => 'user_domains.php:edit', 
			'level' => '2'
			),
		'user_group_admin.php:' => array(
			'title' => __('User Groups'), 
			'mapping' => 'index.php:', 
			'url' => 'user_group_admin.php', 
			'level' => '1'
			),
		'user_group_admin.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,user_group_admin.php:', 
			'url' => '', 
			'level' => '2'
			),
		'user_group_admin.php:actions' => array(
			'title' => __('(Action)'), 
			'mapping' => 'index.php:,user_group_admin.php:', 
			'url' => '', 
			'level' => '2'
			),
		'about.php:' => array(
			'title' => __('About Cacti'), 
			'mapping' => 'index.php:', 
			'url' => 'about.php', 
			'level' => '1'
			),
		'templates_export.php:' => array(
			'title' => __('Export Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'templates_export.php', 
			'level' => '1'
			),
		'templates_export.php:save' => array(
			'title' => __('Export Results'), 
			'mapping' => 'index.php:,templates_export.php:', 
			'url' => 'templates_export.php', 
			'level' => '2'
			),
		'templates_import.php:' => array(
			'title' => __('Import Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'templates_import.php', 
			'level' => '1'
			),
		'reports_admin.php:' => array(
			'title' => __('Reporting'), 
			'mapping' => '', 
			'url' => 'reports_admin.php', 
			'level' => '0'
			),
		'reports_admin.php:actions' => array(
			'title' => __('Report Add'), 
			'mapping' => 'reports_admin.php:', 
			'url' => 'reports_admin.php', 
			'level' => '1'
			),
		'reports_admin.php:delete' => array(
			'title' => __('Report Delete'), 
			'mapping' => 'reports_admin.php:', 
			'url' => 'reports_admin.php', 
			'level' => '1'
			),
		'reports_admin.php:edit' => array(
			'title' => __('Report Edit'), 
			'mapping' => 'reports_admin.php:', 
			'url' => 'reports_admin.php?action=edit', 
			'level' => '1'
			),
		'reports_admin.php:item_edit' => array(
			'title' => __('Report Edit Item'), 
			'mapping' => 'reports_admin.php:,reports_admin.php:edit', 
			'url' => '', 
			'level' => '2'
			),
		'reports_user.php:' => array(
			'title' => __('Reporting'), 
			'mapping' => '', 
			'url' => 'reports_user.php', 
			'level' => '0'
			),
		'reports_user.php:actions' => array(
			'title' => __('Report Add'), 
			'mapping' => 'reports_user.php:', 
			'url' => 'reports_user.php', 
			'level' => '1'
			),
		'reports_user.php:delete' => array(
			'title' => __('Report Delete'), 
			'mapping' => 'reports_user.php:', 
			'url' => 'reports_user.php', 
			'level' => '1'
			),
		'reports_user.php:edit' => array(
			'title' => __('Report Edit'), 
			'mapping' => 'reports_user.php:', 
			'url' => 'reports_user.php?action=edit', 
			'level' => '1'
			),
		'reports_user.php:item_edit' => array(
			'title' => __('Report Edit Item'), 
			'mapping' => 'reports_user.php:,reports_user.php:edit', 
			'url' => '', 
			'level' => '2'
			),
		'color_templates.php:' => array(
			'title' => __('Color Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'color_templates.php', 
			'level' => '1'
			),
		'color_templates.php:template_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,color_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'color_templates.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,color_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'color_templates_items.php:item_edit' => array(
			'title' => __('Color Template Items'), 
			'mapping' => 'index.php:,color_templates.php:,color_templates.php:template_edit', 
			'url' => '', 
			'level' => '3'
			),
		'aggregate_templates.php:' => array(
			'title' => __('Aggregate Templates'), 
			'mapping' => 'index.php:', 
			'url' => 'aggregate_templates.php', 
			'level' => '1'
			),
		'aggregate_templates.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,aggregate_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'aggregate_templates.php:actions'=> array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,aggregate_templates.php:', 
			'url' => '', 
			'level' => '2'
			),
		'aggregate_graphs.php:' => array(
			'title' => __('Aggregate Graphs'), 
			'mapping' => 'index.php:', 
			'url' => 'aggregate_graphs.php', 
			'level' => '1'
			),
		'aggregate_graphs.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,aggregate_graphs.php:', 
			'url' => '', 
			'level' => '2'
			),
		'aggregate_graphs.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,aggregate_graphs.php:', 
			'url' => '', 
			'level' => '2'
			),
		'aggregate_items.php:' => array(
			'title' => __('Aggregate Items'), 
			'mapping' => 'index.php:', 
			'url' => 'aggregate_items.php', 
			'level' => '1'
			),
		'aggregate_items.php:item_edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,aggregate_graphs.php:,aggregate_items.php:', 
			'url' => '', 
			'level' => '2'
			),
		'aggregate_items.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,aggregate_items.php:', 
			'url' => '', 
			'level' => '2'
			),
		'automation_graph_rules.php:' => array(
			'title' => __('Graph Rules'), 
			'mapping' => 'index.php:', 
			'url' => 'automation_graph_rules.php', 
			'level' => '1'
			),
		'automation_graph_rules.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,automation_graph_rules.php:', 
			'url' => '', 
			'level' => '2'
			),
		'automation_graph_rules.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,automation_graph_rules.php:', 
			'url' => '', 
			'level' => '2'
			),
		'automation_graph_rules.php:item_edit' => array(
			'title' => __('Graph Rule Items'), 
			'mapping' => 'index.php:,automation_graph_rules.php:,automation_graph_rules.php:edit', 
			'url' => '', 
			'level' => '3'
			),
		'automation_tree_rules.php:' => array(
			'title' => __('Tree Rules'), 
			'mapping' => 'index.php:', 
			'url' => 'automation_tree_rules.php', 
			'level' => '1'
			),
		'automation_tree_rules.php:edit' => array(
			'title' => __('(Edit)'), 
			'mapping' => 'index.php:,automation_tree_rules.php:', 
			'url' => '', 
			'level' => '2'
			),
		'automation_tree_rules.php:actions' => array(
			'title' => __('Actions'), 
			'mapping' => 'index.php:,automation_tree_rules.php:', 
			'url' => '', 
			'level' => '2'
			),
		'automation_tree_rules.php:item_edit' => array(
			'title' => __('Tree Rule Items'), 
			'mapping' => 'index.php:,automation_tree_rules.php:,automation_tree_rules.php:edit', 
			'url' => '', 
			'level' => '3'
			)
	);

	$nav =  api_plugin_hook_function('draw_navigation_text', $nav);

	$current_page = basename($_SERVER['PHP_SELF']);

	if (!isempty_request_var('action')) {
		get_filter_request_var('action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([-a-zA-Z0-9_\s]+)$/')));
	}

	$current_action = (isset_request_var('action') ? get_request_var('action') : '');

	/* find the current page in the big array */
	if (isset($nav[$current_page . ':' . $current_action])) {
		$current_array = $nav{$current_page . ':' . $current_action};
	}else{
		$current_array = array('mapping' => 'index.php:', 'title' => ucwords(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))), 'level' => 1);
	}

	$current_mappings = explode(',', $current_array['mapping']);
	$current_nav = "<ul id='breadcrumbs'>";
	$title       = '';
	$nav_count   = 0;

	/* resolve all mappings to build the navigation string */
	for ($i=0; ($i<count($current_mappings)); $i++) {
		if (empty($current_mappings[$i])) { continue; }

		if  ($i == 0) {
			/* always use the default for level == 0 */
			$url = $nav{$current_mappings[$i]}['url'];

			if (basename($url) == 'graph_view.php') continue;
		}elseif (isset($nav_level_cache{$i}) && !empty($nav_level_cache{$i}['url'])) {
			/* found a match in the url cache for this level */
			$url = $nav_level_cache{$i}['url'];
		}elseif (!empty($current_array['url'])) {
			/* found a default url in the above array */
			$url = $current_array['url'];
		}else{
			/* default to no url */
			$url = '';
		}

		if ($current_mappings[$i] == '?') {
			/* '?' tells us to pull title from the cache at this level */
			if (isset($nav_level_cache{$i})) {
				$current_nav .= (empty($url) ? '' : "<li><a id='nav_$i' href='" . htmlspecialchars($url) . "'>") . htmlspecialchars(resolve_navigation_variables($nav{$nav_level_cache{$i}['id']}['title'])) . (empty($url) ? '' : '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>');
				$title       .= htmlspecialchars(resolve_navigation_variables($nav{$nav_level_cache{$i}['id']}['title'])) . ' -> ';
			}
		}else{
			/* there is no '?' - pull from the above array */
			$current_nav .= (empty($url) ? '' : "<li><a id='nav_$i' href='" . htmlspecialchars($url) . "'>") . htmlspecialchars(resolve_navigation_variables($nav{$current_mappings[$i]}['title'])) . (empty($url) ? '' : '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>');
			$title       .= htmlspecialchars(resolve_navigation_variables($nav{$current_mappings[$i]}['title'])) . ' -> ';
		}

		$nav_count++;
	}

	if ($nav_count) {
		$current_nav .= "<li><a id='nav_$i' href=#>" . htmlspecialchars(resolve_navigation_variables($current_array['title'])) . '</a></li>';
	}else{
		$current_array = $nav[$current_page . ':' . $current_action];
		$url = (isset($current_array['url']) ? $current_array['url']:'');
		$current_nav .= "<li><a id='nav_$i' href='$url'>" . htmlspecialchars(resolve_navigation_variables($current_array['title'])) . '</a></li>';
	}

	if (isset_request_var('tree_id') || isset_request_var('leaf_id')) {
		$leaf_sub  = '';
		$leaf_name = '';
		$tree_name = '';
		if (isset_request_var('leaf_id') && get_nfilter_request_var('leaf_id') != '') {
			$leaf = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', array(get_filter_request_var('leaf_id')));

			if (sizeof($leaf)) {
				if ($leaf['host_id'] > 0) {
					$leaf_name = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($leaf['host_id']));
				}else{
					$leaf_name = $leaf['title'];
				}

				$tree_name = db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($leaf['graph_tree_id']));
			}else{
				$leaf_name = 'Leaf';
			}

			if (isset_request_var('host_group_data') && get_nfilter_request_var('host_group_data') != '') {
				$parts = explode(':', get_nfilter_request_var('host_group_data'));
				input_validate_input_number($parts[1]);

				if ($parts[0] == 'graph_template') {
					$leaf_sub = db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($parts[1]));
				}else{
					if ($parts[1] > 0) {
						$leaf_sub = db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', array($parts[1]));
					}else{
						$leaf_sub = __('Non Query Based');
					}
				}
			}else{
				$leaf_sub = '';
			}
		}else{
			$leaf_name = '';

			if (isset_request_var('tree_id')) {
				$tree_name = db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array(get_request_var('tree_id')));
			}else{
				$tree_name = '';
			}
		}

		$tree_title = $tree_name . ($leaf_name != '' ? ' (' . $leaf_name:'') . ($leaf_sub != '' ? ':' . $leaf_sub . ')':($leaf_name != '' ? ')':''));

		$current_nav .= "<li><a id='nav_title' href=#>" . htmlspecialchars($tree_title) . '</a></li></ul>';
	}elseif (preg_match('#link.php\?id=(\d+)#', $_SERVER['REQUEST_URI'], $matches)) {
        $title      = db_fetch_cell_prepared('SELECT title FROM external_links WHERE id = ?', array($matches[1]));
		$style      = db_fetch_cell_prepared('SELECT style FROM external_links WHERE id = ?', array($matches[1]));
		if ($style == 'CONSOLE') {
			$current_nav = "<ul id='breadcrumbs'><li><a id='nav_0' href='" . $config['url_path'] . 
				"index.php'>" . __('Console') . '</a>' . (get_selected_theme() == 'classic' ? ' -> ':'') . '</li>';
			$current_nav .= "<li><a id='nav_1' href='#'>Link " . $title . '</a></li></ul>';
		}else{
			$current_nav = "<ul id='breadcrumbs'><li><a id='nav_0'>" . $title . '</a></li></ul>';
		}
		$tree_title = '';
	}else{
		$current_nav .= '</ul>';
		$tree_title = '';
	}

	$title .= htmlspecialchars(resolve_navigation_variables($current_array['title']) . ' ' . $tree_title);

	/* keep a cache for each level we encounter */
	$nav_level_cache{$current_array['level']} = array('id' => $current_page . ':' . $current_action, 'url' => get_browser_query_string());
	$_SESSION['sess_nav_level_cache'] = $nav_level_cache;

	if ($type == 'url') {
		return $current_nav;
	}else{
		return $title;
	}
}

/* resolve_navigation_variables - substitute any variables contained in the navigation text
   @arg $text - the text to substitute in
   @returns - the original navigation text with all substitutions made */
function resolve_navigation_variables($text) {
	if (preg_match_all("/\|([a-zA-Z0-9_]+)\|/", $text, $matches)) {
		for ($i=0; $i<count($matches[1]); $i++) {
			switch ($matches[1][$i]) {
			case 'current_graph_title':
				$text = str_replace('|' . $matches[1][$i] . '|', get_graph_title(get_filter_request_var('local_graph_id')), $text);
				break;
			}
		}
	}

	return $text;
}

/* get_associated_rras - returns a list of all RRAs referenced by a particular graph
   @arg $local_graph_id - (int) the ID of the graph to retrieve a list of RRAs for
   @returns - (array) an array containing the name and id of each RRA found */
function get_associated_rras($local_graph_id, $sql_where = '') {
	return db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . "
		dspr.id, dsp.step, dspr.steps, dspr.rows, dspr.name, dtd.rrd_step
		FROM graph_templates_item AS gti
		LEFT JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		LEFT JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		AND dtd.local_data_id != 0
		WHERE gti.local_graph_id = ? $sql_where", array($local_graph_id));
}

/* get_browser_query_string - returns the full url, including args requested by the browser
   @returns - the url requested by the browser */
function get_browser_query_string() {
	if (!empty($_SERVER['REQUEST_URI'])) {
		return sanitize_uri($_SERVER['REQUEST_URI']);
	}else{
		return sanitize_uri(basename($_SERVER['PHP_SELF']) . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
	}
}

/* get_hash_graph_template - returns the current unique hash for a graph template
   @arg $graph_template_id - (int) the ID of the graph template to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_graph_template($graph_template_id, $sub_type = 'graph_template') {
	if ($sub_type == 'graph_template') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates WHERE id = ?', array($graph_template_id));
	}elseif ($sub_type == 'graph_template_item') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates_item WHERE id = ?', array($graph_template_id));
	}elseif ($sub_type == 'graph_template_input') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM graph_template_input WHERE id = ?', array($graph_template_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_template - returns the current unique hash for a data template
   @arg $graph_template_id - (int) the ID of the data template to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_template($data_template_id, $sub_type = 'data_template') {
	if ($sub_type == 'data_template') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM data_template WHERE id = ?', array($data_template_id));
	}elseif ($sub_type == 'data_template_item') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM data_template_rrd WHERE id = ?', array($data_template_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_input - returns the current unique hash for a data input method
   @arg $graph_template_id - (int) the ID of the data input method to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_input($data_input_id, $sub_type = 'data_input_method') {
	if ($sub_type == 'data_input_method') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM data_input WHERE id = ?', array($data_input_id));
	}elseif ($sub_type == 'data_input_field') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM data_input_fields WHERE id = ?', array($data_input_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_cdef - returns the current unique hash for a cdef
   @arg $graph_template_id - (int) the ID of the cdef to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_cdef($cdef_id, $sub_type = 'cdef') {
	if (! is_numeric($cdef_id)) {
		$cdef_id = -1;
	}
	if ($sub_type == 'cdef') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM cdef WHERE id = ?', array($cdef_id));
	}elseif ($sub_type == 'cdef_item') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM cdef_items WHERE id = ?', array($cdef_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_gprint - returns the current unique hash for a gprint preset
   @arg $graph_template_id - (int) the ID of the gprint preset to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_gprint($gprint_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM graph_templates_gprint WHERE id = ?', array($gprint_id));

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/**
 * returns the current unique hash for a vdef
 * @param $graph_template_id - (int) the ID of the vdef to return a hash for
 * @param $sub_type (optional) return the hash for a particlar sub-type of this type
 * @returns - a 128-bit, hexadecimal hash */
function get_hash_vdef($vdef_id, $sub_type = "vdef") {
	if ($sub_type == "vdef") {
		$hash = db_fetch_cell_prepared('SELECT hash FROM vdef WHERE id = ?', array($vdef_id));
	}elseif ($sub_type == "vdef_item") {
		$hash = db_fetch_cell_prepared('SELECT hash FROM vdef_items WHERE id = ?', array($vdef_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/**
 * returns the current unique hash for a vdef
 * @param $data_source_profile_id - (int) the ID of the data_source_profile to return a hash for
 * @returns - a 128-bit, hexadecimal hash */
function get_hash_data_source_profile($data_source_profile_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM data_source_profiles WHERE id = ?', array($data_source_profile_id));

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_host_template - returns the current unique hash for a gprint preset
   @arg $host_template_id - (int) the ID of the host template to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_host_template($host_template_id) {
	$hash = db_fetch_cell_prepared('SELECT hash FROM host_template WHERE id = ?', array($host_template_id));

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_query - returns the current unique hash for a data query
   @arg $graph_template_id - (int) the ID of the data query to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_query($data_query_id, $sub_type = 'data_query') {
	if ($sub_type == 'data_query') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query WHERE id = ?', array($data_query_id));
	}elseif ($sub_type == 'data_query_graph') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph WHERE id = ?', array($data_query_id));
	}elseif ($sub_type == 'data_query_sv_data_source') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph_rrd_sv WHERE id = ?', array($data_query_id));
	}elseif ($sub_type == 'data_query_sv_graph') {
		$hash = db_fetch_cell_prepared('SELECT hash FROM snmp_query_graph_sv WHERE id = ?', array($data_query_id));
	}

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_version - returns the item type and cacti version in a hash format
   @arg $type - the type of item to represent ('graph_template','data_template',
     'data_input_method','cdef','vdef','gprint_preset','data_query','host_template')
   @returns - a 24-bit hexadecimal hash (8-bits for type, 16-bits for version) */
function get_hash_version($type) {
	global $hash_type_codes, $hash_version_codes, $config;

	return $hash_type_codes[$type] . $hash_version_codes{$config['cacti_version']};
}

/* generate_hash - generates a new unique hash
   @returns - a 128-bit, hexadecimal hash */
function generate_hash() {
	global $config;

	return md5(session_id() . microtime() . rand(0,1000));
}

/* debug_log_insert_section_start - creates a header item for breaking down the debug log
   @arg $type - the 'category' or type of debug message
   @arg $text - section header */
function debug_log_insert_section_start($type, $text) {
	debug_log_insert($type, "<table class='cactiTable debug'><tr class='tableHeader'><td class='textHeaderDark'>$text</td></tr><tr><td style='padding:0px;'><table style='display:none;'><tr><td><div style='font-family: monospace;'>");
}

/* debug_log_insert_section_end - finalizes the header started with the start function
   @arg $type - the 'category' or type of debug message */
function debug_log_insert_section_end($type) {
	debug_log_insert($type, "</div></td></tr></table></td></tr></td></table>");
}

/* debug_log_insert - inserts a line of text into the debug log
   @arg $type - the 'category' or type of debug message
   @arg $text - the actual debug message */
function debug_log_insert($type, $text) {
	if (!isset($_SESSION['debug_log'][$type])) {
		$_SESSION['debug_log'][$type] = array();
	}

	array_push($_SESSION['debug_log'][$type], $text);
}

/* debug_log_clear - clears the debug log for a particular category
   @arg $type - the 'category' to clear the debug log for. omitting this argument
     implies all categories */
function debug_log_clear($type = '') {
	if ($type == '') {
		kill_session_var('debug_log');
	}else{
		if (isset($_SESSION['debug_log'])) {
			unset($_SESSION['debug_log'][$type]);
		}
	}
}

/* debug_log_return - returns the debug log for a particular category
   @arg $type - the 'category' to return the debug log for.
   @returns - the full debug log for a particular category */
function debug_log_return($type) {
	$log_text = '';

	if ($type == 'new_graphs') {
		if (isset($_SESSION['debug_log'][$type])) {
			$log_text .= "<table style='width:100%;'>";
			for ($i=0; $i<count($_SESSION['debug_log'][$type]); $i++) {
				$log_text .= '<tr><td>' . $_SESSION['debug_log'][$type][$i] . '</td></tr>';
			}
			$log_text .= '</table>';
		}
	}else{
		if (isset($_SESSION['debug_log'][$type])) {
			$log_text .= "<table style='width:100%;'>";
			for ($i=0; $i<count($_SESSION['debug_log'][$type]); $i++) {
				$log_text .= '<tr><td>' . $_SESSION['debug_log'][$type][$i] . '</td></tr>';
			}
			$log_text .= '</table>';
		}
	}

	return $log_text;
}

/* sanitize_search_string - cleans up a search string submitted by the user to be passed
     to the database. NOTE: some of the code for this function came from the phpBB project.
   @arg $string - the original raw search string
   @returns - the sanitized search string */
function sanitize_search_string($string) {
	static $drop_char_match = array('(',')','^', '$', '<', '>', '`', '\'', '"', '|', ',', '?', '+', '[', ']', '{', '}', '#', ';', '!', '=', '*');
	static $drop_char_replace = array('','',' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '); 

	/* Replace line endings by a space */
	$string = preg_replace('/[\n\r]/is', ' ', $string);

	/* HTML entities like &nbsp; */
	$string = preg_replace('/\b&[a-z]+;\b/', ' ', $string);

	/* Remove URL's */
	$string = preg_replace('/\b[a-z0-9]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/]+)?/', ' ', $string);

	/* Filter out strange characters like ^, $, &, change "it's" to "its" */
	for($i = 0; $i < count($drop_char_match); $i++) {
		$string =  str_replace($drop_char_match[$i], $drop_char_replace[$i], $string);
	}

	return $string;
}

/** cleans up a URI, e.g. from REQUEST_URI and/or QUERY_STRING
 * in case of XSS attac, expect the result to be broken
 * we do NOT sanitize in a way, that attacs are converted to valid HTML
 * it is ok, when the result is broken but the application stays alive
 * @arg string $uri   - the uri to be sanitized
 * @returns string    - the sanitized uri
 */
function sanitize_uri($uri) {
	static $drop_char_match =   array('^', '$', '<', '>', '`', '\'', '"', '|', '+', '[', ']', '{', '}', ';', '!');
	static $drop_char_replace = array( '', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '',  '');

	return str_replace($drop_char_match, $drop_char_replace, urldecode($uri));
}

/** cleans up a CDEF/VDEF string
 * the CDEF/VDEF must have passed all magic string replacements beforehand
 * @arg string $cdef   - the CDEF/VDEF to be sanitized
 * @returns string    - the sanitized CDEF/VDEF
 */
function sanitize_cdef($cdef) {
	static $drop_char_match =   array('^', '$', '<', '>', '`', '\'', '"', '|', '[', ']', '{', '}', ';', '!');
	static $drop_char_replace = array( '', '',  '',  '',  '',  '',   '',  '',  '',  '',  '',  '',  '',  '');

	return str_replace($drop_char_match, $drop_char_replace, $cdef);
}

/** verifies all selected items are numeric to guard against injection
 * @arg array $items   - an array of serialized items from a post
 * @returns array      - the sanitized selected items array
 */
function sanitize_unserialize_selected_items($items) {
	if ($items != '') {
		$items = unserialize(stripslashes($items));

		if (is_array($items)) {
			for ($i=0;($i<count($items));$i++) {
				if (is_array($items[$i])) {
					return false;
				}elseif (!is_numeric($items[$i]) && ($items[$i] != '')) {
					return false;
				}
			}
		}else{
			return false;
		}
	}else{
		return false;
	}

	return $items;
}

function cacti_escapeshellcmd($string) {
	global $config;

	if ($config['cacti_server_os'] == 'unix') {
		return escapeshellcmd($string);
	}else{
		$replacements = "#&;`|*?<>^()[]{}$\\";

		for ($i=0; $i < strlen($replacements); $i++) {
			$string = str_replace($replacements[$i], ' ', $string);
		}
		return $string;
	}
}


/**
 * mimics escapeshellarg, even for windows
 * @param $string 	- the string to be escaped
 * @param $quote 	- true: do NOT remove quotes from result; false: do remove quotes
 * @return			- the escaped [quoted|unquoted] string
 */
function cacti_escapeshellarg($string, $quote=true) {
	global $config;
	/* we must use an apostrophe to escape community names under Unix in case the user uses
	characters that the shell might interpret. the ucd-snmp binaries on Windows flip out when
	you do this, but are perfectly happy with a quotation mark. */
	if ($config['cacti_server_os'] == 'unix') {
		$string = escapeshellarg($string);
		if ( $quote ) {
			return $string;
		} else {
			# remove first and last char
			return substr($string, 1, (strlen($string)-2));
		}
	}else{
		/* escapeshellarg takes care of different quotation for both linux and windows,
		 * but unfortunately, it blanks out percent signs
		 * we want to keep them, e.g. for GPRINT format strings
		 * so we need to create our own escapeshellarg
		 * on windows, command injection requires to close any open quotation first
		 * so we have to escape any quotation here */
		if (substr_count($string, CACTI_ESCAPE_CHARACTER)) {
			$string = str_replace(CACTI_ESCAPE_CHARACTER, "\\" . CACTI_ESCAPE_CHARACTER, $string);
		}

		/* ... before we add our own quotation */
		if ( $quote ) {
			return CACTI_ESCAPE_CHARACTER . $string . CACTI_ESCAPE_CHARACTER;
		} else {
			return $string;
		}
	}
}

function bottom_footer() {
	global $config, $refresh;

	include($config['base_path'] . '/include/global_session.php');

	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'true') {
		include($config['base_path'] . '/include/bottom_footer.php');
	}else{
		?>
		<script type='text/javascript'>
		var message = "<?php print display_output_messages();?>";

		$(function() {
			if (message != '') {
				$('#message_container').html(message).show().delay(2000).slideUp('fast');
				window.scrollTo(0,0);
			}

			if (refreshMSeconds == null || refreshMSeconds < 5000) {
				refreshMSeconds=999999999;
			}
		});

		</script>
		<?php

		/* we use this session var to store field values for when a save fails,
		this way we can restore the field's previous values. we reset it here, because
		they only need to be stored for a single page */
		kill_session_var('sess_field_values');

		/* close the session */
		session_write_close();

		/* make sure the debug log doesn't get too big */
		debug_log_clear();

		/* close the database connection */
		db_close();
	}
}

function top_header() {
	global $config;

	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'true') {
		include($config['base_path'] . '/include/top_header.php');
	}
}

function top_graph_header() {
	global $config;

	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'true') {
		include($config['base_path'] . '/include/top_graph_header.php');
	}
}

function general_header() {
	global $config;
	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'true') {
		include($config['base_path'] . '/include/top_general_header.php');
	}
}

function send_mail($to, $from, $subject, $body, $attachments = '', $headers = '', $html = false) {
	$full_name = db_fetch_cell_prepared('SELECT full_name FROM user_auth WHERE email_address = ?', array($from));
	if (empty($full_name)) {
		$fromname = $from;
	}else{
		$fromname = $full_name;
	}

	$from = array($from, $fromname);

	mailer($from, $to, '', '', '', $subject, $body, '', $attachments, $headers, $html);
}

/** mailer - function to send mails to users 
 *  @arg $from - a string email address, or an array in array(email_address, name format)
 *  @arg $to - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $cc - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $bcc - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $replyto - a string email address, or an array in array(email_address, name format)
 *  @arg $subject - the email subject
 *  @arg $body - the email body, in HTML format.  If content_text is not set, the function will attempt to extract
 *       from the HTML format.
 *  @arg $body_text - the email body in TEXT format.  If set, it will override the stripping tags method
 *  @arg $attachments - the emails attachments as an array
 *  @arg $headers - an array of name value pairs representing custom headers.
 *  @arg $html - if set to true, html is the default, otherwise text format will be used
 */
function mailer($from, $to, $cc, $bcc, $replyto, $subject, $body, $body_text = '', $attachments, $headers, $html = true) {
	global $config;

	include_once($config['include_path'] . '/phpmailer/PHPMailerAutoload.php');

	// Set the to informaiotn
	if ($to == '') {
		return __('Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.');
	}

	if (is_array($to)) {
		$toText = $to[1] . ' <' . $to[0] . '>';
	}else{
		$toText = $to;
	}

	if (is_array($from)) {
		$fromText = $from[1] . ' <' . $from[0] . '>';
	}else{
		$fromText = $from;
	}

	$body = str_replace('<SUBJECT>', $subject, $body);
	$body = str_replace('<TO>',      $toText, $body);
	$body = str_replace('<FROM>',    $fromText, $body);

	// Create the PHPMailer instance
	$mail = new PHPMailer;

	// Set a reasonable timeout of 5 seconds
	$timeout = read_config_option('settings_smtp_timeout');
	if (empty($timeout) || $timeout < 0 || $timeout > 300) {
		$mail->Timeout = 5;
	}else{
		$mail->Timeout = $timeout;
	}

	// Set the subject
	$mail->Subject = $subject;

	$how = read_config_option('settings_how');
	if ($how < 0 || $how > 2) {
		$how = 0;
	}

	if ($how == 0) {
		$mail->isMail();
	} else if ($how == 1) {
		$mail->Sendmail = read_config_option('settings_sendmail_path');
		$mail->isSendmail();
	} else if ($how == 2) {
		$mail->isSMTP();
		$mail->Host     = read_config_option('settings_smtp_host');
		$mail->Port     = read_config_option('settings_smtp_port');
		$mail->Username = read_config_option('settings_smtp_username');
		$mail->Password = read_config_option('settings_smtp_password');
		if ($mail->Username != '') {
			$mail->SMTPAuth = true;
		}

		// Set a reasonable timeout of 5 seconds
		$timeout = read_config_option('settings_smtp_timeout');
		if (empty($timeout) || $timeout < 0 || $timeout > 300) {
			$mail->Timeout = 5;
		}else{
			$mail->Timeout = $timeout;
		}

		$secure  = read_config_option('settings_smtp_secure');
		if (!empty($secure) && $secure != 'none') {
			$mail->SMTPSecure = $secure;
			if (substr_count($mail->Host, ':') == 0) {
				$mail->Host = $secure . '://' . $mail->Host;
			}
		}
	}

	// Set the from information
	if (!is_array($from)) {
		$fromname = '';
		if ($from == '') {
			$from     = read_config_option('settings_from_email');
			$fromname = read_config_option('settings_from_name');
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cacti.net';
			}

			if ($fromname == '') {
				$fromname = 'Cacti';
			}
		}

		$mail->setFrom($from, $fromname);
	}else{
		$mail->setFrom($from[0], $from[1]);
	}

	if (!is_array($to)) {
		$to = explode(',', $to);

		foreach($to as $t) {
			$t = trim($t);
			if ($t != '') {
				$mail->addAddress($t);
			}
		}
	}else{
		foreach($to as $email => $name) {
			$mail->addAddress($email, $name);
		}
	}

	if (!is_array($cc)) {
		if ($cc != '') {
			$cc = explode(',', $cc);
			foreach($cc as $c) {
				$c = trim($c);
				$mail->addCC($c);
			}
		}
	}else{
		foreach($cc as $email => $name) {
			$mail->addCC($email, $name);
		}
	}

	if (!is_array($bcc)) {
		if ($bcc != '') {
			$bcc = explode(',', $bcc);
			foreach($bcc as $bc) {
				$bc = trim($bc);
				$mail->addBCC($bc);
			}
		}
	}else{
		foreach($bcc as $email => $name) {
			$mail->addBCC($email, $name);
		}
	}

	if (!is_array($replyto)) {
		if ($replyto != '') {
			$mail->replyTo($replyto);
		}
	}else{
		$mail->replyTo($replyto[0], $replyto[1]);
	}

	// Set the wordwrap limits
	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '') {
		$wordwrap = 76;
	}elseif ($wordwrap > 9999) {
		$wordwrap = 9999;
	}elseif ($wordwrap < 0) {
		$wordwrap = 76;
	}

	$mail->WordWrap = $wordwrap;
	$mail->setWordWrap();

	$i = 0;

	// Handle Graph Attachments
	if (is_array($attachments) && sizeof($attachments) && substr_count($body, '<GRAPH>') > 0) {
		foreach($attachments as $attachment) {
			if ($attachment['attachment'] != '') {
				/* get content id and create attachment */
				$cid = getmypid() . '_' . $i . '@' . 'localhost';

				/* attempt to attach */
				if ($mail->addStringEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']) === false) {
					cacti_log('ERROR: ' . $mail->ErrorInfo, false);

					return $mail->ErrorInfo;
				}

				$body = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $body);

				$i++;
			} else {
				$body = str_replace('<GRAPH>' . $attachment['local_graph_id'] . '>', "<img src='" . $attachment['filename'] . "' ><br>Could not open!<br>" . $attachment['filename'], $body);
			}
		}
	}elseif (is_array($attachments) && sizeof($attachments) && substr_count($body, '<GRAPH:') > 0) {
		foreach($attachments as $attachment) {
			if ($attachment['attachment'] != '') {
				/* get content id and create attachment */
				$cid = getmypid() . '_' . $i . '@' . 'localhost';

				/* attempt to attach */
				if ($mail->addStringEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']) === false) {
					cacti_log('ERROR: ' . $mail->ErrorInfo, false);

					return $mail->ErrorInfo;
				}

				/* handle the body text */
				switch ($attachment['inline']) {
					case 'inline':
						$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', "<img src='cid:$cid' >", $body);
						break;
					case 'attachment':
						$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', '', $body);
						break;
				}

				$i++;
			} else {
				$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', "<img src='" . $attachment['filename'] . "' ><br>Could not open!<br>" . $attachment['filename'], $body);
			}
		}
	}

	/* process custom headers */
	if (is_array($headers) && sizeof($headers)) {
	foreach($headers as $name => $value) {
		$mail->addCustomHeader($name, $value);
	}
	}

	// Set both html and non-html bodies
	$text = array('text' => '', 'html' => '');
	if ($body_text != '' && $html == true) {
		$text['html']  = $body . '<br>';
		$text['text']  = $body_text;
		$mail->isHTML(true);
		$mail->Body    = $text['html'];
		$mail->AltBody = $text['text'];
	}elseif ($attachments == '' && $html == false) {
		if ($body_text != '') {
			$body = $body_text;
		}else{
			$body = str_replace('<br>',  "\n", $body);
			$body = str_replace('<BR>',  "\n", $body);
			$body = str_replace('</BR>', "\n", $body);
		}

		$text['text']  = strip_tags($body);
		$mail->isHTML(false);
		$mail->Body    = $text['text'];
		$mail->AltBody = $text['text'];
	} elseif ($html == false) {
		$text['text']  = strip_tags($body);
		$mail->isHTML(false);
		$mail->Body    = $text['text'];
		$mail->AltBody = $text['text'];
	} else {
		$text['html']  = $body . '<br>';
		$text['text']  = strip_tags(str_replace('<br>', "\n", $body));
		$mail->isHTML(true);
		$mail->Body    = $text['html'];
		$mail->AltBody = $text['text'];
	}

	if ($mail->send()) {
		cacti_log("Mail Sucessfully Sent to '" . $toText . "', Subject: '" . $mail->Subject . "'", false, 'MAILER'); 

		return '';
	}else{
		cacti_log("Mail Failed to '" . $toText . "', Subject: '" . $mail->Subject . "'", false, 'MAILER');

		return $mail->ErrorInfo;
	}
}

function ping_mail_server($host, $port, $user, $password, $timeout = 5, $secure = 'none') {
	global $config;

	include_once($config['include_path'] . '/phpmailer/PHPMailerAutoload.php');

	//Create a new SMTP instance
	$smtp = new SMTP;

	if ($secure != 'tls' && $secure != 'none') {
		$smtp->SMTPSecure = $secure;
		if (substr_count($host, ':') == 0) {
			$host = $secure . '://' . $host;
		}
	}

	//Enable connection-level debug output
	$smtp->do_debug = 0;
	//$smtp->do_debug = SMTP::DEBUG_LOWLEVEL;

	$results = true;
	try {
		//Connect to an SMTP server
		if ($smtp->connect($host, $port, $timeout)) {
			//Say hello
			if ($smtp->hello(gethostbyname(gethostname()))) { //Put your host name in here
				//Authenticate
				if ($smtp->authenticate($user, $password)) {
					$results = true;
				} else {
					throw new Exception(__('Authentication failed: %s', $smtp->getLastReply()));
				}
			} else {
				throw new Exception(__('HELO failed: %s', $smtp->getLastReply()));
			}
		} else {
			throw new Exception(__('Connect failed'));
		}
	} catch (Exception $e) {
		$results = __('SMTP error: ') . $e->getMessage();
	}

	//Whatever happened, close the connection.
	$smtp->quit(true);

	return $results;
}

function email_test() {
	global $config;

	$message =  __('This is a test message generated from Cacti.  This message was sent to test the configuration of your Mail Settings.') . '<br><br>';
	$message .= __('Your email settings are currently set as follows') . '<br><br>';
	$message .= '<b>' . __('Method') . '</b>: ';

	print __('Checking Configuration...<br>');

	$ping_results = true;
	$how = read_config_option('settings_how');
	if ($how < 0 || $how > 2)
		$how = 0;
	if ($how == 0) {
		$mail = __('PHP\'s Mailer Class');
	} else if ($how == 1) {
		$mail = __('Sendmail') . '<br><b>' . __('Sendmail Path'). '</b>: ';
		$sendmail = read_config_option('settings_sendmail_path');
		$mail .= $sendmail;
	} else if ($how == 2) {
		print __('Method: SMTP') . '<br>';
		$mail = __('SMTP') . '<br>';
		$smtp_host = read_config_option('settings_smtp_host');
		$smtp_port = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');
		$smtp_secure   = read_config_option('settings_smtp_secure');
		$smtp_timeout  = read_config_option('settings_smtp_timeout');

		$mail .= "<b>" . __('Device') . "</b>: $smtp_host<br>";
		$mail .= "<b>" . __('Port') . "</b>: $smtp_port<br>";

		if ($smtp_username != '' && $smtp_password != '') {
			$mail .= '<b>' . __('Authentication') . '</b>: true<br>';
			$mail .= '<b>' . __('Username') . "</b>: $smtp_username<br>";
			$mail .= '<b>' . __('Password') . '</b>: (' . _('Not Shown for Security Reasons') . ')<br>';
			$mail .= '<b>' . __('Security') . "</b>: $smtp_secure<br>";
		} else {
			$mail .= '<b>' . __('Authentication') . '</b>: false<br>';
		}

		$ping_results = ping_mail_server($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_timeout, $smtp_secure);

		print __('Ping Results:') . ' ' . ($ping_results == 1 ? __('Success'):$ping_results) . '<br>';

		if ($ping_results != 1) {
			$mail .= '<b>' . __('Ping Results') . '</b>: ' . $ping_results . '<br>';
		}else{
			$mail .= '<b>' . __('Ping Results') . '</b>: ' . __('Success') . '<br>';
		}
	}
	$message .= $mail;
	$message .= '<br>';

	$errors = '';
	if ($ping_results == 1) {
		print __('Creating Message Text...') . '<br><br>';
		print "<center><table><tr><td>";
		print "<table style='width:100%;'><tr><td>$message</td><tr></table></table></center><br>";
		print __('Sending Message...') . '<br><br>';

		$global_alert_address = read_config_option('settings_test_email');

		$errors = send_mail($global_alert_address, '', __('Cacti Test Message'), $message, '', '', true);
		if ($errors == '') {
			$errors = __('Success!');
		}
	}else{
		print __('Message Not Sent due to ping failure.'). '<br><br>';
	}

	print "<center><table><tr><td>";
	print "<table><tr><td>$errors</td><tr></table></table></center>";
}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function get_dns_from_ip ($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode('.', $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return 'ERROR';

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return 'ERROR';
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info['timed_out']) {
		return 'timed_out';
	}

	/* more error handling */
	if ($response == '') { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack('s', substr($response, $requestsize+2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = '';
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack('c', substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return strtoupper(substr($host, 0, strlen($host) -1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . '.';

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return strtoupper($ip);
	}

	/* error - return the hostname */
	return strtoupper($ip);
}

function poller_maintenance () {
	global $config;

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/poller_maintenance.php';

	exec_background($command_string, $extra_args);
}

function clog_admin() {
	if (!isset($_SESSION['sess_clog_level'])) {
		clog_authorized();
	}

	if ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN) {
		return true;
	}else{
		return false;
	}
}

function clog_authorized() {
	if (!isset($_SESSION['sess_clog_level'])) {
		if (isset($_SESSION['sess_user_id'])) {
			if (is_realm_allowed(18)) {
				$_SESSION['sess_clog_level'] = CLOG_PERM_ADMIN;
			}else{
				if (is_realm_allowed(19)) {
					$_SESSION['sess_clog_level'] = CLOG_PERM_USER;
				}else {
					$_SESSION['sess_clog_level'] = CLOG_PERM_NONE;
				}
			}
		}else{
			$_SESSION['sess_clog_level'] = CLOG_PERM_NONE;
		}
	}

	if ($_SESSION['sess_clog_level'] == CLOG_PERM_USER) {
		return true;
	}elseif ($_SESSION['sess_clog_level'] == CLOG_PERM_ADMIN) {
		return true;
	}else{
		return false;
	}
}

function update_system_mibs($host_id) {
	$system_mibs = array(
		'snmp_sysDescr' => '.1.3.6.1.2.1.1.1.0',
		'snmp_sysObjectID' => '.1.3.6.1.2.1.1.2.0',
		'snmp_sysUpTimeInstance' => '.1.3.6.1.2.1.1.3.0',
		'snmp_sysContact' => '.1.3.6.1.2.1.1.4.0',
		'snmp_sysName' => '.1.3.6.1.2.1.1.5.0',
		'snmp_sysLocation' => '.1.3.6.1.2.1.1.6.0'
	);

	$h = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($host_id));

	if (sizeof($h)) {
		foreach($system_mibs as $name => $oid) {
			$value = cacti_snmp_get($h['hostname'], $h['snmp_community'], $oid,
				$h['snmp_version'], $h['snmp_username'], $h['snmp_password'],
				$h['snmp_auth_protocol'], $h['snmp_priv_passphrase'], $h['snmp_priv_protocol'],
				$h['snmp_context'], $h['snmp_port'], $h['snmp_timeout'], read_config_option('snmp_retries'), SNMP_CMDPHP);

			if (!empty($value)) {
				db_execute_prepared("UPDATE host SET $name = ? WHERE id = ?",
					array($value, $host_id));
			}
		}
	}
}

function cacti_debug_backtrace($entry = '', $html = false) {
	global $config;

	$callers = debug_backtrace();
	$s = '';
	foreach ($callers as $c) {
		if (isset($c['file'])) {
			$file = str_replace($config['base_path'], '', $c['file']);
		}else{
			$file = '';
		}

		if (isset($c['line'])) {
			$line = $c['line'];
		}else{
			$line = '';
		}
		$func = $c['function'];
		$s = "(" . ($file != '' ? "$file: ":'') . ($line != '' ? "$line ":'') . "$func)" . $s;
	}

	if ($html) {
		echo "<table style='width:100%;text-align:center;'><tr><td>$s</td></tr></table>\n";
	}
	cacti_log(trim("$entry Backtrace: $s"), false);
}

/*	calculate_percentiles - Given and array of numbers, calculate the Nth percentile,
 *  optionally, return an array of numbers containing elements required for 
 *  a whisker chart.
 *
 *  @arg $data       - an array of data
 *  @arg $percentile - the Nth percentile to calculate.  By default 95th.
 *  #arg $whisker    - if whisker is true, an array of values will be returne
 *                     including 25th, median, 75th, and 90th perecentiles.
 *
 *  @returns - either the Nth percentile, the elements for a whisker chart,
 *            or false if there is insufficient data to determine. */
function calculate_percentiles($data, $percentile = 95, $whisker = false) {
    if ($percentile > 0 && $percentile < 1) {
        $p = $percentile;
    }elseif ($percentile > 1 && $percentile <= 100) {
        $p = $percentile * .01;
    }else {
        return false;
    }

	if ($whisker) {
		$tiles = array(
			'25th' => 0.25,
			'50th' => 0.50,
			'75th' => 0.75,
			'90th' => 0.90,
			'95th' => 0.95,
		);
	}else{
		$tiles = array(
			'custom' => $p
		);
	}

	$results  = array();
	$elements = sizeof($data);

	/* sort the array to return */
	sort($data);

	foreach($tiles as $index => $p) {
		/* calculate offsets into the array */
		$allindex    = ($elements - 1) * $p;
		$intvalindex = intval($allindex);
		$floatval    = $allindex - $intvalindex;

		if (!is_float($floatval)) {
			$ptile = $data[$intvalindex];
		} else {
			if ($elements > $intvalindex + 1) {
				$ptile = $floatval * ($data[$intvalindex + 1] - $data[$intvalindex]) + $data[$intvalindex];
			} else {
				$ptile = $data[$intvalindex];
			}
		}

		if ($index == 'custom') {
			return $ptile;
		}else{
			$results[$index] = $ptile;
		}
	}

    return $result;
}

function get_timeinstate($host) {
	$interval = read_config_option('poller_interval');
	if ($host['status_event_count'] > 0) {
		$time = $host['status_event_count'] * $interval;
	}elseif (strtotime($host['status_rec_date']) > 943916400) {
		$time = time() - strtotime($host['status_rec_date']);
	}else{
		$time = $host['snmp_sysUpTimeInstance']/100;
	}

	if ($time > 86400) {
		$days  = floor($time/86400);
		$time %= 86400;
	}else{
		$days  = 0;
	}

	if ($time > 3600) {
		$hours = floor($time/3600);
		$time  %= 3600;
	}else{
		$hours = 0;
	}

	$minutes = floor($time/60);

	return $days . 'd:' . $hours . 'h:' . $minutes . 'm';
}

function get_classic_tabimage($text, $down = false) {
	global $config;

	$images = array(
		false => 'tab_template_blue.gif',
		true  => 'tab_template_red.gif'
	);

	if ($text == '') return false;

	$text         = strtolower($text);

	$possibles = array(
		array('DejaVuSans-Bold.ttf', 9, true),
		array('DejaVuSansCondensed-Bold.ttf', 9, false),
		array('DejaVuSans-Bold.ttf', 9, false),
		array('DejaVuSansCondensed-Bold.ttf', 9, false),
		array('DejaVuSans-Bold.ttf', 8, false),
		array('DejaVuSansCondensed-Bold.ttf', 8, false),
		array('DejaVuSans-Bold.ttf', 7, false),
		array('DejaVuSansCondensed-Bold.ttf', 7, true),
	);

	$y        = 30;
	$x        = 44;
	$wlimit   = 72;
	$wrapsize = 12;

	if (file_exists($config['base_path'] . '/images/' . $images[$down])) {
		$originalpath = getenv('GDFONTPATH');
		putenv('GDFONTPATH=' . $config['base_path'] . '/include/fonts/');

		$template = imagecreatefromgif($config['base_path'] . '/images/' . $images[$down]);

		$w = imagesx($template);
		$h = imagesy($template);

		$tab = imagecreatetruecolor($w, $h);
		imagecopy($tab, $template, 0, 0, 0, 0, $w, $h);

		$txcol = imagecolorat($tab, 0, 0);
		imagecolortransparent($tab,$txcol);

		$white = imagecolorallocate($tab, 255, 255, 255);

		foreach ($possibles as $variation) {
			$font     = $variation[0];
			$fontsize = $variation[1];

			$lines = array();

			// if no wrapping is requested, or no wrapping is possible...
			if((!$variation[2]) || ($variation[2] && strpos($text,' ') === false)) {
				$bounds  = imagettfbbox($fontsize, 0, $font, $text);
				$w       = $bounds[4] - $bounds[0];
				$h       = $bounds[1] - $bounds[5];
				$realx   = $x - $w/2 -1;
				$lines[] = array($text, $font, $fontsize, $realx, $y);
				$maxw    = $w;
			} else {
				$texts = explode("\n", wordwrap($text, $wrapsize), 2);
				$line  = 1;
				$maxw  = 0;
				foreach ($texts as $txt) {
					$bounds  = imagettfbbox($fontsize, 0, $font, $txt);
					$w       = $bounds[4] - $bounds[0];
					$h       = $bounds[1] - $bounds[5];
					$realx   = $x - $w/2 -1;
					$realy   = $y - $h * $line + 3;
					$lines[] = array($txt, $font, $fontsize, $realx, $realy);
					if ($maxw < $w) {
						$maxw = $w;
					}

					$line--;
				}
			}
						
			if($maxw<$wlimit) break;
		}

		foreach ($lines as $line) {
			imagettftext($tab, $line[2], 0, $line[3], $line[4], $white, $line[1], $line[0]);
		}

		putenv('GDFONTPATH=' . $originalpath);

		imagetruecolortopalette($tab, true, 256);

		// generate the image an return the data directly
		ob_start();
		imagegif($tab);
		$image = ob_get_contents();
		ob_end_clean();

		return("data:image/gif;base64," . base64_encode($image));
	}else{
		return false;
	}
}

function cacti_oid_numeric_format() {
    if (function_exists('snmp_set_oid_output_format')) {
		snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
	}elseif (function_exists("snmp_set_oid_numeric_print")) {
		snmp_set_oid_numeric_print(TRUE);
	}
}

function IgnoreErrorHandler($message) {
	global $snmp_error;

	$snmp_ignore = array(
		'No response from',
		'noSuchName',
		'This name does not exist',
		'End of MIB',
	);

	foreach ($snmp_ignore as $i) {
		if (strpos($message, $i)) {
			$snmp_error = trim($message, "\\\n\t ");
			return true;
		}
	}

	$ignore = array();

	foreach ($ignore as $i) {
		if (strpos($message, $i)) {
			return true;
		}
	}

	return false;
}


function CactiErrorHandler($level, $message, $file, $line, $context) {
	global $phperrors;

	if (IgnoreErrorHandler($message)) {
		return true;
	}

	preg_match("/.*\/plugins\/([\w-]*)\/.*/", $file, $output_array);

	$plugin = (isset($output_array[1]) ? $output_array[1] : '');
	$error  = 'PHP ' . $phperrors[$level] . ($plugin != '' ? " in  Plugin '$plugin'" : '') . ": $message in file: $file  on line: $line";

	switch ($level) {
		case E_COMPILE_ERROR:
		case E_CORE_ERROR:
		case E_ERROR:
		case E_PARSE:
			if ($plugin != '') {
				api_plugin_disable_all($plugin);
				cacti_log("ERRORS DETECTED - DISABLING PLUGIN '$plugin'");
			}
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR PARSE');
			break;
		case E_RECOVERABLE_ERROR:
		case E_USER_ERROR:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR');
			break;
		case E_COMPILE_WARNING:
		case E_CORE_WARNING:
		case E_USER_WARNING:
		case E_WARNING:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR WARNING');
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR NOTICE');
			break;
		case E_STRICT:
			cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR STRICT');
			break;
		default:
       		cacti_log($error, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR');
	}

	return false;
}

function CactiShutdownHandler () {
	global $phperrors;
	$error = error_get_last();

	if (IgnoreErrorHandler($error['message'])) {
		return true;
	}

	switch ($error['type']) {
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_PARSE:
			preg_match('/.*\/plugins\/([\w-]*)\/.*/', $error['file'], $output_array);

			$plugin = (isset($output_array[1]) ? $output_array[1] : '' );

			$message = 'PHP ' . $phperrors[$error['type']] . 
				($plugin != '' ? " in  Plugin '$plugin'" : '') . ': ' . $error['message'] . 
				' in file: ' .  $error['file'] . ' on line: ' . $error['line'];

			cacti_log($message, false, 'ERROR');
			cacti_debug_backtrace('PHP ERROR');

			if ($plugin != '') {
				api_plugin_disable_all($plugin);
				cacti_log("ERRORS DETECTED - DISABLING PLUGIN '$plugin'");
			}
	}
}
