<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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
		return substr($text, 0, $max_length) . "...";
	}else{
		return $text;
	}
}

/* read_default_graph_config_option - finds the default value of a graph configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the default value of the configuration option */
function read_default_graph_config_option($config_name) {
	global $config, $settings_graphs;

	reset($settings_graphs);
	while (list($tab_name, $tab_array) = each($settings_graphs)) {
		if ((isset($tab_array[$config_name])) && (isset($tab_array[$config_name]["default"]))) {
			return $tab_array[$config_name]["default"];
		}else{
			while (list($field_name, $field_array) = each($tab_array)) {
				if ((isset($field_array["items"])) && (isset($field_array["items"][$config_name])) && (isset($field_array["items"][$config_name]["default"]))) {
					return $field_array["items"][$config_name]["default"];
				}
			}
		}
	}
}

/* read_graph_config_option - finds the current value of a graph configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings_graphs array
     in 'include/global_settings.php'
   @returns - the current value of the graph configuration option */
function read_graph_config_option($config_name, $force = FALSE) {
	global $config;

	/* users must have cacti user auth turned on to use this, or the guest account must be active */

	if (isset($_SESSION["sess_user_id"])) {
		$effective_uid = $_SESSION["sess_user_id"];
	}else if (isset($config["config_options_array"]["export_user_id"])) {
		$effective_uid = $config["config_options_array"]["export_user_id"];
	}else if ((read_config_option("auth_method") == 0)) {
		/* first attempt to get the db setting for guest */
		$effective_uid = db_fetch_cell("SELECT id FROM user_auth WHERE username='" . read_config_option("guest_user") . "'");

		if (strlen($effective_uid) == 0) {
			$effective_uid = 0;
		}

		$db_setting = db_fetch_row("select value from settings_graphs where name='$config_name' and user_id=" . $effective_uid);

		if (isset($db_setting["value"])) {
			return $db_setting["value"];
		}else{
			return read_default_graph_config_option($config_name);
		}
	}else{
		$effective_uid = 0;
	}

	if (!$force) {
		if (isset($_SESSION["sess_graph_config_array"])) {
			$graph_config_array = $_SESSION["sess_graph_config_array"];
		}else if (isset($config["config_options_array"]["export_user_id"])) {
			if (isset($config["config_graph_settings_array"])) {
				$graph_config_array = $config["config_graph_settings_array"];
			}
		}
	}

	if (!isset($graph_config_array[$config_name])) {
		$db_setting = db_fetch_row("select value from settings_graphs where name='$config_name' and user_id=" . $effective_uid);

		if (isset($db_setting["value"])) {
			$graph_config_array[$config_name] = $db_setting["value"];
		}else{
			$graph_config_array[$config_name] = read_default_graph_config_option($config_name);
		}

		if (isset($_SESSION)) {
			$_SESSION["sess_graph_config_array"]   = $graph_config_array;
		}else{
			$config["config_graph_settings_array"] = $graph_config_array;
		}
	}

	return $graph_config_array[$config_name];
}

/* config_value_exists - determines if a value exists for the current user/setting specified
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns (bool) - true if a value exists, false if a value does not exist */
function config_value_exists($config_name) {
	global $database_default;
	return sizeof(db_fetch_assoc("select value from `$database_default`.`settings` where name='$config_name'"));
}

/* graph_config_value_exists - determines if a value exists for the current user/setting specified
   @arg $config_name - the name of the configuration setting as specified $settings_graphs array
     in 'include/global_settings.php'
   @arg $user_id - the id of the user to check the configuration value for
   @returns (bool) - true if a value exists, false if a value does not exist */
function graph_config_value_exists($config_name, $user_id) {
	return sizeof(db_fetch_assoc("select value from settings_graphs where name='$config_name' and user_id='$user_id'"));
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
			if ((isset($tab_array[$config_name])) && (isset($tab_array[$config_name]["default"]))) {
				return $tab_array[$config_name]["default"];
			}else{
				while (list($field_name, $field_array) = each($tab_array)) {
					if ((isset($field_array["items"])) && (isset($field_array["items"][$config_name])) && (isset($field_array["items"][$config_name]["default"]))) {
						return $field_array["items"][$config_name]["default"];
					}
				}
			}
		}
	}
}

/* set_config_option - sets/updates a cacti config option with the given value.
   @arg $config_name - the name of the configuration setting as specified $settings array
   @arg $value       - the values to be saved
   @returns          - void */
function set_config_option($config_name, $value) {
	global $database_default;
	db_execute("REPLACE INTO `$database_default`.`settings` SET name='$config_name', value='$value'");
}

/* read_config_option - finds the current value of a Cacti configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/global_settings.php'
   @returns - the current value of the configuration option */
function read_config_option($config_name, $force = FALSE) {
	global $config, $database_default;

	if (isset($_SESSION["sess_config_array"])) {
		$config_array = $_SESSION["sess_config_array"];
	}else if (isset($config["config_options_array"])) {
		$config_array = $config["config_options_array"];
	}

	if ((!isset($config_array[$config_name])) || ($force)) {
		$db_setting = db_fetch_row("select value from `$database_default`.`settings` where name='$config_name'", FALSE);

		if (isset($db_setting["value"])) {
			$config_array[$config_name] = $db_setting["value"];
		}else{
			$config_array[$config_name] = read_default_config_option($config_name);
		}

		if (isset($_SESSION)) {
			$_SESSION["sess_config_array"]  = $config_array;
		}else{
			$config["config_options_array"] = $config_array;
		}
	}

	return $config_array[$config_name];
}

/* form_input_validate - validates the value of a form field and takes the appropriate action if the input
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
	$_SESSION["sess_field_values"][$field_name] = $field_value;

	if (($allow_nulls == true) && ($field_value == "")) {
		return $field_value;
	}

	/* php 4.2+ complains about empty regexps */
	if (empty($regexp_match)) { $regexp_match = ".*"; }

	if ((!preg_match('/' . $regexp_match . '/', $field_value) || (($allow_nulls == false) && ($field_value === "")))) {
		raise_message($custom_message);
		cacti_log("Validation Error on field '".$field_name."', value '".$field_value."': " . $messages[$custom_message]["message"], false);

		$_SESSION["sess_error_fields"][$field_name] = $field_name;
	}else{
		$_SESSION["sess_field_values"][$field_name] = $field_value;
	}

	return $field_value;
}

/* check_changed - determines if a request variable has changed between page loads
   @returns - (bool) true if the value changed between loads */
function check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

/* is_error_message - finds whether an error message has been raised and has not been outputted to the
     user
   @returns - (bool) whether the messages array contains an error or not */
function is_error_message() {
	global $config, $messages;

	if (isset($_SESSION["sess_messages"])) {
		if (is_array($_SESSION["sess_messages"])) {
			foreach (array_keys($_SESSION["sess_messages"]) as $current_message_id) {
				if ($messages[$current_message_id]["type"] == "error") { return true; }
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
	if (substr_count($email, ",")) {
		$comma = true;
		$delim = ",";
	}

	if (substr_count($email, ";")) {
		$semic = true;
		$delim = ";";
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
	$_SESSION["sess_messages"][$message_id] = $message_id;
}

/* display_output_messages - displays all of the cached messages from the raise_message() function and clears
     the message cache */
function display_output_messages() {
	global $config, $messages;

	if (isset($_SESSION["sess_messages"])) {
		$error_message = is_error_message();

		if (is_array($_SESSION["sess_messages"])) {
			foreach (array_keys($_SESSION["sess_messages"]) as $current_message_id) {
				if (isset($messages[$current_message_id]["message"])) {
					eval ('$message = "' . $messages[$current_message_id]["message"] . '";');

					switch ($messages[$current_message_id]["type"]) {
					case 'info':
						if ($error_message == false) {
							print "<div id='message' class='textInfo' style='margin-bottom:5px;padding:5px;background-color:#FFFFFF;border:1px solid #BBBBBB;max-width:100%;position:relative;'>";
							print "$message";
							print "</div>";

							/* we don't need these if there are no error messages */
							kill_session_var("sess_field_values");
						}
						break;
					case 'error':
						print "<div id='message' class='textError' style='margin-bottom:5px;padding:5px;background-color:#FFFFFF;border:1px solid #BBBBBB;max-width:100%;position:relative;'>";
						print "Error: $message";
						print "</div>";
						break;
					}
				}else{
					cacti_log("ERROR: Cacti Error Message Id '$current_message_id' Not Defined", false, "WEBUI");
				}
			}
		}else{
			display_custom_error_message($_SESSION["sess_messages"]);
		}

		if (read_config_option("cacti_popup_messages") == "on") {
		?>
		<script type="text/javascript">
		<!--
		var obj = document.getElementById('message');

		if (obj) {
			if (window.innerHeight) {
				height = window.innerHeight;
				width  = window.innerWidth;
			}else{
				height = document.body.clientHeight;
				width  = document.body.clientWidth;
			}
			var opacity=1;

			obj.class = "popupBox";
			cw = obj.scrollWidth;
			// Adjust for IE6
			if (!cw) cw = 150;
			ch = obj.scrollHeight;
			obj.style.top = '65px';
			obj.style.left = ((width/2) - (cw/2) + 60)+'px';
			opacity = 1;
			obj.style.opacity = opacity;
			obj.zoom = "100%";
			if (document.getElementById('message')) {
				setTimeout("removeMessage()", 2000);
			}

			function removeMessage() {
				if (obj.style.opacity <= 0) return;
				opacity-=0.15;
				iopacity = opacity * 100;
				obj.style.opacity = opacity;
				obj.style.filter = 'alpha(opacity='+iopacity+')';

				setTimeout("removeMessage()",40);
			}
		}
		-->
		</script>
		<?php
		}
	}

	kill_session_var("sess_messages");
}

/* display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $text - the actual text of the error message to display */
function display_custom_error_message($message) {
	print "<div id='message' class='textError' style='margin-bottom:5px;padding:5px;background-color:#FFFFFF;border:1px solid #BBBBBB;max-width:100%;position:relative;'>";
	print "Error: $message";
	print "</div>";
}

/* clear_messages - clears the message cache */
function clear_messages() {
	kill_session_var("sess_messages");
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
     '$arr[0] = array("id" => 23, "name" => "blah")'
     to the form
     '$arr = array(23 => "blah")'
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

	list($micro,$seconds) = explode(" ", microtime());
	$timer_start = $seconds + $micro;
}

/* timer end/step function */
function timer_end($message = "default") {
	global $timer_start;

	list($micro,$seconds) = explode(" ", microtime());
	$timer_end = $seconds + $micro;

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
   @arg $environ - (string) tell's from where the script was called from */
function cacti_log($string, $output = false, $environ = "CMDPHP") {
	global $config;

	/* fill in the current date for printing in the log */
	$date = date("m/d/Y h:i:s A");

	/* determine how to log data */
	$logdestination = read_config_option("log_destination");
	$logfile        = read_config_option("path_cactilog");

	/* format the message */
	if (($environ != "SYSTEM") && ($environ != "EXPORT") && ($environ != "RECACHE") && ($environ != "AUTH")) {
		$message = "$date - " . $environ . ": Poller[0] " . $string . "\n";
	}else {
		$message = "$date - " . $environ . " " . $string . "\n";
	}

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option("log_verbosity") != POLLER_VERBOSITY_NONE)) {
		if ($logfile == "") {
			$logfile = $config["base_path"] . "/log/cacti.log";
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, "a");

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$log_type = "";
		if (substr_count($string,"ERROR:"))
			$log_type = "err";
		else if (substr_count($string,"WARNING:"))
			$log_type = "warn";
		else if (substr_count($string,"STATS:"))
			$log_type = "stat";
		else if (substr_count($string,"NOTICE:"))
			$log_type = "note";

		if (strlen($log_type)) {
			if ($config["cacti_server_os"] == "win32")
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_USER);
			else
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_SYSLOG);

			if (($log_type == "err") && (read_config_option("log_perror"))) {
				syslog(LOG_CRIT, $environ . ": " . $string);
			}

			if (($log_type == "warn") && (read_config_option("log_pwarn"))) {
				syslog(LOG_WARNING, $environ . ": " . $string);
			}

			if ((($log_type == "stat") || ($log_type == "note")) && (read_config_option("log_pstats"))) {
				syslog(LOG_INFO, $environ . ": " . $string);
			}

			closelog();
		}
   }

	/* print output to standard out if required */
	if (($output == true) && (isset($_SERVER["argv"][0]))){
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
function tail_file($file_name, $number_of_lines, $message_type = -1, $filter = "", $line_size = 256) {
	$file_array = array();

	if (file_exists($file_name) && is_readable($file_name)) {
		$fp = fopen($file_name, "r");

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
				if (substr_count($line, " SQL ")) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 1: /* stats */
				if (substr_count($line, "STATS")) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 2: /* warnings */
				if (substr_count($line, "WARN")) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 3: /* errors */
				if (substr_count($line, "ERROR")) {
					$display=true;
				}else{
					$display=false;
				}

				break;
			case 4: /* debug */
				if (substr_count($line, "DEBUG")) {
					$display=true;
				}else{
					$display=false;
				}

				if (substr_count($line, " SQL ")) {
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
					(@preg_match($filter, $line))) {
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
		echo "Error $file_name is not readable";
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
	$ping_failure_count  = read_config_option("ping_failure_count");
	$ping_recovery_count = read_config_option("ping_recovery_count");
	$hosts[$host_id]["status_fail_date"] = '0000-00-00 00:00:00';
	$hosts[$host_id]["status_rec_date"] = '0000-00-00 00:00:00';

	if ($status == HOST_DOWN) {
		/* update total polls, failed polls and availability */
		$hosts[$host_id]["failed_polls"]++;
		$hosts[$host_id]["total_polls"]++;
		$hosts[$host_id]["availability"] = 100 * ($hosts[$host_id]["total_polls"] - $hosts[$host_id]["failed_polls"]) / $hosts[$host_id]["total_polls"];

		/* determine the error message to display */
		if (($ping_availability == AVAIL_SNMP_AND_PING) || ($ping_availability == AVAIL_SNMP_OR_PING)) {
			if (($hosts[$host_id]["snmp_community"] == "") && ($hosts[$host_id]["snmp_version"] != 3)) {
				/* snmp version 1/2 without community string assume SNMP test to be successful
				   due to backward compatibility issues */
				$hosts[$host_id]["status_last_error"] = $ping->ping_response;
			}else {
				$hosts[$host_id]["status_last_error"] = $ping->snmp_response . ", " . $ping->ping_response;
			}
		}elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]["snmp_community"] == "") && ($hosts[$host_id]["snmp_version"] != 3)) {
				$hosts[$host_id]["status_last_error"] = "Device does not require SNMP";
			}else {
				$hosts[$host_id]["status_last_error"] = $ping->snmp_response;
			}
		}else {
			$hosts[$host_id]["status_last_error"] = $ping->ping_response;
		}

		/* determine if to send an alert and update remainder of statistics */
		if ($hosts[$host_id]["status"] == HOST_UP) {
			/* increment the event failure count */
			$hosts[$host_id]["status_event_count"]++;

			/* if it's time to issue an error message, indicate so */
			if ($hosts[$host_id]["status_event_count"] >= $ping_failure_count) {
				/* host is now down, flag it that way */
				$hosts[$host_id]["status"] = HOST_DOWN;

				$issue_log_message = true;

				/* update the failure date only if the failure count is 1 */
				if ($ping_failure_count == 1) {
					$hosts[$host_id]["status_fail_date"] = date("Y-m-d H:i:s");
				}
			/* host is down, but not ready to issue log message */
			} else {
				/* host down for the first time, set event date */
				if ($hosts[$host_id]["status_event_count"] == 1) {
					$hosts[$host_id]["status_fail_date"] = date("Y-m-d H:i:s");
				}
			}
		/* host is recovering, put back in failed state */
		} elseif ($hosts[$host_id]["status"] == HOST_RECOVERING) {
			$hosts[$host_id]["status_event_count"] = 1;
			$hosts[$host_id]["status"] = HOST_DOWN;

		/* host was unknown and now is down */
		} elseif ($hosts[$host_id]["status"] == HOST_UNKNOWN) {
			$hosts[$host_id]["status"] = HOST_DOWN;
			$hosts[$host_id]["status_event_count"] = 0;
		} else {
			$hosts[$host_id]["status_event_count"]++;
		}
	/* host is up!! */
	} else {
		/* update total polls and availability */
		$hosts[$host_id]["total_polls"]++;
		$hosts[$host_id]["availability"] = 100 * ($hosts[$host_id]["total_polls"] - $hosts[$host_id]["failed_polls"]) / $hosts[$host_id]["total_polls"];

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
			if (($hosts[$host_id]["snmp_community"] == "") && ($hosts[$host_id]["snmp_version"] != 3)) {
				$ping_time = 0.000;
			}else {
				/* calculate the average of the two times */
				$ping_time = ($ping->snmp_status + $ping->ping_status) / 2;
			}
		}elseif ($ping_availability == AVAIL_SNMP) {
			if (($hosts[$host_id]["snmp_community"] == "") && ($hosts[$host_id]["snmp_version"] != 3)) {
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
		$hosts[$host_id]["cur_time"] = $ping_time;

		/* maximum time */
		if ($ping_time > $hosts[$host_id]["max_time"])
			$hosts[$host_id]["max_time"] = $ping_time;

		/* minimum time */
		if ($ping_time < $hosts[$host_id]["min_time"])
			$hosts[$host_id]["min_time"] = $ping_time;

		/* average time */
		$hosts[$host_id]["avg_time"] = (($hosts[$host_id]["total_polls"]-1-$hosts[$host_id]["failed_polls"])
			* $hosts[$host_id]["avg_time"] + $ping_time) / ($hosts[$host_id]["total_polls"]-$hosts[$host_id]["failed_polls"]);

		/* the host was down, now it's recovering */
		if (($hosts[$host_id]["status"] == HOST_DOWN) || ($hosts[$host_id]["status"] == HOST_RECOVERING )) {
			/* just up, change to recovering */
			if ($hosts[$host_id]["status"] == HOST_DOWN) {
				$hosts[$host_id]["status"] = HOST_RECOVERING;
				$hosts[$host_id]["status_event_count"] = 1;
			} else {
				$hosts[$host_id]["status_event_count"]++;
			}

			/* if it's time to issue a recovery message, indicate so */
			if ($hosts[$host_id]["status_event_count"] >= $ping_recovery_count) {
				/* host is up, flag it that way */
				$hosts[$host_id]["status"] = HOST_UP;

				$issue_log_message = true;

				/* update the recovery date only if the recovery count is 1 */
				if ($ping_recovery_count == 1) {
					$hosts[$host_id]["status_rec_date"] = date("Y-m-d H:i:s");
				}

				/* reset the event counter */
				$hosts[$host_id]["status_event_count"] = 0;
			/* host is recovering, but not ready to issue log message */
			} else {
				/* host recovering for the first time, set event date */
				if ($hosts[$host_id]["status_event_count"] == 1) {
					$hosts[$host_id]["status_rec_date"] = date("Y-m-d H:i:s");
				}
			}
		} else {
		/* host was unknown and now is up */
			$hosts[$host_id]["status"] = HOST_UP;
			$hosts[$host_id]["status_event_count"] = 0;
		}
	}
	/* if the user wants a flood of information then flood them */
	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
		if (($hosts[$host_id]["status"] == HOST_UP) || ($hosts[$host_id]["status"] == HOST_RECOVERING)) {
			/* log ping result if we are to use a ping for reachability testing */
			if ($ping_availability == AVAIL_SNMP_AND_PING) {
				cacti_log("Host[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
				cacti_log("Host[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} elseif ($ping_availability == AVAIL_SNMP) {
				if (($hosts[$host_id]["snmp_community"] == "") && ($hosts[$host_id]["snmp_version"] != 3)) {
					cacti_log("Host[$host_id] SNMP: Device does not require SNMP", $print_data_to_stdout);
				}else{
					cacti_log("Host[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
				}
			} else {
				cacti_log("Host[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
			}
		} else {
			if ($ping_availability == AVAIL_SNMP_AND_PING) {
				cacti_log("Host[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
				cacti_log("Host[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} elseif ($ping_availability == AVAIL_SNMP) {
				cacti_log("Host[$host_id] SNMP: " . $ping->snmp_response, $print_data_to_stdout);
			} else {
				cacti_log("Host[$host_id] PING: " . $ping->ping_response, $print_data_to_stdout);
			}
		}
	}

	/* if there is supposed to be an event generated, do it */
	if ($issue_log_message) {
		if ($hosts[$host_id]["status"] == HOST_DOWN) {
			cacti_log("Host[$host_id] ERROR: HOST EVENT: Host is DOWN Message: " . $hosts[$host_id]["status_last_error"], $print_data_to_stdout);
		} else {
			cacti_log("Host[$host_id] NOTICE: HOST EVENT: Host Returned from DOWN State: ", $print_data_to_stdout);
		}
	}

	db_execute("update host set
		status = '" . $hosts[$host_id]["status"] . "',
		status_event_count = '" . $hosts[$host_id]["status_event_count"] . "',
		status_fail_date = '" . $hosts[$host_id]["status_fail_date"] . "',
		status_rec_date = '" . $hosts[$host_id]["status_rec_date"] . "',
		status_last_error = '" . $hosts[$host_id]["status_last_error"] . "',
		min_time = '" . $hosts[$host_id]["min_time"] . "',
		max_time = '" . $hosts[$host_id]["max_time"] . "',
		cur_time = '" . $hosts[$host_id]["cur_time"] . "',
		avg_time = '" . $hosts[$host_id]["avg_time"] . "',
		total_polls = '" . $hosts[$host_id]["total_polls"] . "',
		failed_polls = '" . $hosts[$host_id]["failed_polls"] . "',
		availability = '" . $hosts[$host_id]["availability"] . "'
		where hostname = '" . $hosts[$host_id]["hostname"] . "'");
}

/* strip_quotes - Strip single and double quotes from a string
	in addition remove non-numeric data from strings.
	@arg $result - (string) the result from the poll
	@returns - (string) the string with quotes stripped */
function strip_quotes($result) {
	/* first strip all single and double quotes from the string */
	$result = trim(trim($result), "'\"");

	/* clean off ugly non-numeric data */
	if ((!is_numeric($result)) && (!is_hexadecimal($result)) && ($result != "U")) {
		$len = strlen($result);
		for($a=$len-1; $a>=0; $a--){
			$p = ord($result[$a]);
			if ((($p > 47) && ($p < 58)) || ($p==85)) {
				$result = substr($result,0,$a+1);
				break;
			}
		}
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
			if (substr($hexstr,$i,1) != ":") {
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
	if ((substr_count($result, ":") == 0) && (substr_count($result, "!") == 0) && (substr_count($result, " ") == 0)) {
		$checked = true;
		if (is_numeric($result)) {
			$valid_result = true;
		} else if (is_float($result)) {
			$valid_result = true;
		} else {
			$valid_result = false;
			$result = "U";
		}
	}
	/* it has delimiters and has no space */
	if (!$checked) {
		if (((substr_count($result, ":")) || (substr_count($result, "!")))) {
			if (substr_count($result, " ") == 0) {
				$valid_result = true;
				$checked = true;
			}

			if (substr_count($result, " ") != 0) {
				$checked = true;
				if (substr_count($result, ":")) {
					$delim_cnt = substr_count($result, ":");
				} else if (strstr($result, "!")) {
					$delim_cnt = substr_count($result, "!");
				}

				$space_cnt = substr_count($result, " ");

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

	$data_source = db_fetch_row("select
		data_template_data.id,
		data_template_data.data_input_id,
		data_input.type_id,
		data_input.input_string
		from (data_template_data,data_input)
		where data_template_data.data_input_id=data_input.id
		and data_template_data.local_data_id=$local_data_id");

	/* snmp-actions don't have paths */
	if (($data_source["type_id"] == DATA_INPUT_TYPE_SNMP) || ($data_source["type_id"] == DATA_INPUT_TYPE_SNMP_QUERY)) {
		return false;
	}

	$data = db_fetch_assoc("select
		data_input_fields.data_name,
		data_input_data.value
		from data_input_fields
		left join data_input_data
		on (data_input_fields.id=data_input_data.data_input_field_id)
		where data_input_fields.data_input_id=" . $data_source["data_input_id"] . "
		and data_input_data.data_template_data_id=" . $data_source["id"] . "
		and data_input_fields.input_output='in'");

	$full_path = $data_source["input_string"];

	if (sizeof($data) > 0) {
	foreach ($data as $item) {
		$full_path = str_replace("<" . $item["data_name"] . ">", $item["value"], $full_path);
	}
	}

	$full_path = str_replace("<path_cacti>", $config["base_path"], $full_path);
	$full_path = str_replace("<path_snmpget>", read_config_option("path_snmpget"), $full_path);
	$full_path = str_replace("<path_php_binary>", read_config_option("path_php_binary"), $full_path);

	/* sometimes a certain input value will not have anything entered... null out these fields
	in the input string so we don't mess up the script */
	$full_path = preg_replace("/(<[A-Za-z0-9_]+>)+/", "", $full_path);

	return $full_path;
}

/* get_data_source_item_name - gets the name of a data source item or generates a new one if one does not
     already exist
   @arg $data_template_rrd_id - (int) the ID of the data source item
   @returns - the name of the data source item or an empty string for an error */
function get_data_source_item_name($data_template_rrd_id) {
	if (empty($data_template_rrd_id)) { return ""; }

	$data_source = db_fetch_row("select
		data_template_rrd.data_source_name,
		data_template_data.name
		from (data_template_rrd,data_template_data)
		where data_template_rrd.local_data_id=data_template_data.local_data_id
		and data_template_rrd.id=$data_template_rrd_id");

	/* use the cacti ds name by default or the user defined one, if entered */
	if (empty($data_source["data_source_name"])) {
		/* limit input to 19 characters */
		$data_source_name = clean_up_name($data_source["name"]);
		$data_source_name = substr(strtolower($data_source_name),0,(19-strlen($data_template_rrd_id))) . $data_template_rrd_id;

		return $data_source_name;
	}else{
		return $data_source["data_source_name"];
	}
}

/* get_data_source_path - gets the full path to the .rrd file associated with a given data source
   @arg $local_data_id - (int) the ID of the data source
   @arg $expand_paths - (bool) whether to expand the <path_rra> variable into its full path or not
   @returns - the full path to the data source or an empty string for an error */
function get_data_source_path($local_data_id, $expand_paths) {
	global $config;

	if (empty($local_data_id)) { return ""; }

	$data_source = db_fetch_row("select name,data_source_path from data_template_data where local_data_id=$local_data_id");

	if (sizeof($data_source) > 0) {
		if (empty($data_source["data_source_path"])) {
			/* no custom path was specified */
			$data_source_path = generate_data_source_path($local_data_id);
		}else{
			if (!strstr($data_source["data_source_path"], "/")) {
				$data_source_path = "<path_rra>/" . $data_source["data_source_path"];
			}else{
				$data_source_path = $data_source["data_source_path"];
			}
		}

		/* whether to show the "actual" path or the <path_rra> variable name (for edit boxes) */
		if ($expand_paths == true) {
			$data_source_path = str_replace('<path_rra>', $config['rra_path'], $data_source_path);
		}

		return $data_source_path;
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
	$string = preg_replace("/[\s\.]+/", "_", $string);
	$string = preg_replace("/[^a-zA-Z0-9_]+/", "", $string);
	$string = preg_replace("/_{2,}/", "_", $string);

	return $string;
}

/* clean_up_file name - runs a string through a series of regular expressions designed to
     eliminate "bad" characters
   @arg $string - the string to modify/clean
   @returns - the modified string */
function clean_up_file_name($string) {
	$string = preg_replace("/[\s\.]+/", "_", $string);
	$string = preg_replace("/[^a-zA-Z0-9_-]+/", "", $string);
	$string = preg_replace("/_{2,}/", "_", $string);

	return $string;
}

/* clean_up_path - takes any path and makes sure it contains the correct directory
     separators based on the current operating system
   @arg $path - the path to modify
   @returns - the modified path */
function clean_up_path($path) {
	global $config;

	if ($config["cacti_server_os"] == "unix" or read_config_option("using_cygwin") == "on") {
		$path = str_replace("\\", "/", $path);
	}elseif ($config["cacti_server_os"] == "win32") {
		$path = str_replace("/", "\\", $path);

	}

	return $path;
}

/* get_data_source_title - returns the title of a data source without using the title cache
   @arg $local_data_id - (int) the ID of the data source to get a title for
   @returns - the data source title */
function get_data_source_title($local_data_id) {
	$data = db_fetch_row("select
		data_local.host_id,
		data_local.snmp_query_id,
		data_local.snmp_index,
		data_template_data.name
		from (data_template_data,data_local)
		where data_template_data.local_data_id=data_local.id
		and data_local.id=$local_data_id");

	if ((strstr($data["name"], "|")) && (!empty($data["host_id"]))) {
		$data["name"] = substitute_data_input_data($data["name"], "", $local_data_id);
		return expand_title($data["host_id"], $data["snmp_query_id"], $data["snmp_index"], $data["name"]);
	}else{
		return $data["name"];
	}
}

/* get_graph_title - returns the title of a graph without using the title cache
   @arg $local_graph_id - (int) the ID of the graph to get a title for
   @returns - the graph title */
function get_graph_title($local_graph_id) {
	$graph = db_fetch_row("select
		graph_local.host_id,
		graph_local.snmp_query_id,
		graph_local.snmp_index,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title
		from (graph_templates_graph,graph_local)
		where graph_templates_graph.local_graph_id=graph_local.id
		and graph_local.id=$local_graph_id");

	if ((strstr($graph["title"], "|")) && (!empty($graph["host_id"]))) {
		$graph["title"] = substitute_data_input_data($graph["title"], $graph, 0);
		return expand_title($graph["host_id"], $graph["snmp_query_id"], $graph["snmp_index"], $graph["title"]);
	}else{
		return $graph["title"];
	}
}

/* generate_data_source_path - creates a new data source path from scratch using the first data source
     item name and updates the database with the new value
   @arg $local_data_id - (int) the ID of the data source to generate a new path for
   @returns - the new generated path */
function generate_data_source_path($local_data_id) {
	global $config;

	$host_part = ""; $ds_part = "";

	$extended_paths = read_config_option("extended_paths");

	/* try any prepend the name with the host description */
	$host = db_fetch_row("SELECT
		host.id,
		host.description
		FROM (host, data_local)
		WHERE data_local.host_id=host.id
		AND data_local.id=$local_data_id
		LIMIT 1");

	$host_name = $host["description"];
	$host_id   = $host["id"];

	/* put it all together using the local_data_id at the end */
	if ($extended_paths == "on") {
		$new_path = "<path_rra>/$host_id/$local_data_id.rrd";
	}else{
		if (!empty($host_name)) {
			$host_part = strtolower(clean_up_file_name($host_name)) . "_";
		}

		/* then try and use the internal DS name to identify it */
		$data_source_rrd_name = db_fetch_cell("SELECT data_source_name
			FROM data_template_rrd
			WHERE local_data_id=$local_data_id
			ORDER BY id");

		if (!empty($data_source_rrd_name)) {
			$ds_part = strtolower(clean_up_file_name($data_source_rrd_name));
		}else{
			$ds_part = "ds";
		}

		$new_path = "<path_rra>/$host_part$ds_part" . "_" . "$local_data_id.rrd";
	}

	/* update our changes to the db */
	db_execute("UPDATE data_template_data SET data_source_path='$new_path' WHERE local_data_id=$local_data_id");

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
	return "1";
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
	 * 		rra[0].cf = "LAST"
	 * or similar
	 */
	if (strlen($output)) {
		$output = explode("\n", $output);

		if (sizeof($output)) {
			foreach($output as $line) {
				if (substr_count($line, ".cf")) {
					$values = explode("=",$line);

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
			case "AVG":
			case "AVERAGE":
				$new_cfs[] = array_search('AVERAGE', $consolidation_functions);
				break;
			case "MIN":
				$new_cfs[] = array_search('MIN', $consolidation_functions);
				break;
			case "MAX":
				$new_cfs[] = array_search('MAX', $consolidation_functions);
				break;
			case "LAST":
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
	$lookup_table = array("a","b","c","d","e","f","g","h","i","j");

	$result = "";

	for ($i=0; $i<strlen(strval($graph_item_id)); $i++) {
		$result .= $lookup_table{substr(strval($graph_item_id), $i, 1)};
	}

	return $result;
}

/* generate_data_input_field_sequences - re-numbers the sequences of each field associated
     with a particular data input method based on its position within the input string
   @arg $string - the input string that contains the field variables in a certain order
   @arg $data_input_id - (int) the ID of the data input method */
function generate_data_input_field_sequences($string, $data_input_id) {
	global $config, $registered_cacti_names;

	if (preg_match_all("/<([_a-zA-Z0-9]+)>/", $string, $matches)) {
		$j = 0;
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=$data_input_id and input_output='in' and data_name='" . $matches[1][$i] . "'");
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
	$graph_item = db_fetch_row("select local_graph_id,graph_template_id from graph_templates_item where id=$graph_template_item_id");

	if (empty($graph_item["local_graph_id"])) {
		$sql_where = "graph_template_id = " . $graph_item["graph_template_id"] . " and local_graph_id=0";
	}else{
		$sql_where = "local_graph_id = " . $graph_item["local_graph_id"];
	}

	$graph_items = db_fetch_assoc("select id,sequence from graph_templates_item where $sql_where order by sequence");

	/* get a list of parent+children of our target group */
	$target_graph_group_array = get_graph_group($target_id);

	/* if this "parent" item has no children, then treat it like a regular gprint */
	if (sizeof($target_graph_group_array) == 0) {
		if ($direction == "next") {
			move_item_down("graph_templates_item", $graph_template_item_id, $sql_where);
		}elseif ($direction == "previous") {
			move_item_up("graph_templates_item", $graph_template_item_id, $sql_where);
		}

		return;
	}

	/* start the sequence at '1' */
	$sequence_counter = 1;

	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $item) {
		/* check to see if we are at the "target" spot in the loop; if we are, update the sequences and move on */
		if ($target_id == $item["id"]) {
			if ($direction == "next") {
				$group_array1 = $target_graph_group_array;
				$group_array2 = $graph_group_array;
			}elseif ($direction == "previous") {
				$group_array1 = $graph_group_array;
				$group_array2 = $target_graph_group_array;
			}

			while (list($sequence,$graph_template_item_id) = each($group_array1)) {
				db_execute("update graph_templates_item set sequence=$sequence_counter where id=$graph_template_item_id");

				/* propagate to ALL graphs using this template */
				if (empty($graph_item["local_graph_id"])) {
					db_execute("update graph_templates_item set sequence=$sequence_counter where local_graph_template_item_id=$graph_template_item_id");
				}

				$sequence_counter++;
			}

			while (list($sequence,$graph_template_item_id) = each($group_array2)) {
				db_execute("update graph_templates_item set sequence=$sequence_counter where id=$graph_template_item_id");

				/* propagate to ALL graphs using this template */
				if (empty($graph_item["local_graph_id"])) {
					db_execute("update graph_templates_item set sequence=$sequence_counter where local_graph_template_item_id=$graph_template_item_id");
				}

				$sequence_counter++;
			}
		}

		/* make sure to "ignore" the items that we handled above */
		if ((!isset($graph_group_array{$item["id"]})) && (!isset($target_graph_group_array{$item["id"]}))) {
			db_execute("update graph_templates_item set sequence=$sequence_counter where id=" . $item["id"]);
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

	$graph_item = db_fetch_row("select graph_type_id,sequence,local_graph_id,graph_template_id from graph_templates_item where id=$graph_template_item_id");

	if (empty($graph_item["local_graph_id"])) {
		$sql_where = "graph_template_id = " . $graph_item["graph_template_id"] . " and local_graph_id=0";
	}else{
		$sql_where = "local_graph_id = " . $graph_item["local_graph_id"];
	}

	/* a parent must NOT be the following graph item types */
	if (preg_match("/(GPRINT|VRULE|HRULE|COMMENT)/", $graph_item_types{$graph_item["graph_type_id"]})) {
		return;
	}

	$graph_item_children_array = array();

	/* put the parent item in the array as well */
	$graph_item_children_array[$graph_template_item_id] = $graph_template_item_id;

	$graph_items = db_fetch_assoc("select id,graph_type_id from graph_templates_item where sequence > " . $graph_item["sequence"] . " and $sql_where order by sequence");

	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $item) {
		if ($graph_item_types{$item["graph_type_id"]} == "GPRINT") {
			/* a child must be a GPRINT */
			$graph_item_children_array{$item["id"]} = $item["id"];
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
	$graph_item = db_fetch_row("select sequence,local_graph_id,graph_template_id from graph_templates_item where id=$graph_template_item_id");

	if (empty($graph_item["local_graph_id"])) {
		$sql_where = "graph_template_id = " . $graph_item["graph_template_id"] . " and local_graph_id=0";
	}else{
		$sql_where = "local_graph_id = " . $graph_item["local_graph_id"];
	}

	if ($direction == "next") {
		$sql_operator = ">";
		$sql_order = "ASC";
	}elseif ($direction == "previous") {
		$sql_operator = "<";
		$sql_order = "DESC";
	}

	$next_parent_id = db_fetch_cell("select id from graph_templates_item where sequence $sql_operator " . $graph_item["sequence"] . " and graph_type_id != 9 and $sql_where order by sequence $sql_order limit 1");

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
	if ($direction == "next") {
		$sql_operator = ">";
		$sql_order = "ASC";
	}elseif ($direction == "previous") {
		$sql_operator = "<";
		$sql_order = "DESC";
	}

	$current_sequence = db_fetch_cell("select $field from $tblname where id=$startid");
	$new_item_id = db_fetch_cell("select id from $tblname where $field $sql_operator $current_sequence and $lmt_query order by $field $sql_order limit 1");

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
		$data = db_fetch_row("select max($field)+1 as seq from $table_name where $group_query");

		if ($data["seq"] == "") {
			return 1;
		}else{
			return $data["seq"];
		}
	}else{
		$data = db_fetch_row("select $field from $table_name where id=$id");
		return $data[$field];
	}
}

/* move_item_down - moves an item down by swapping it with the item below it
   @arg $table_name - the table name that contains the target id
   @arg $current_id - (int) the current id
   @arg $group_query - an SQL "where" clause to limit the query */
function move_item_down($table_name, $current_id, $group_query) {
	$next_item = get_item($table_name, "sequence", $current_id, $group_query, "next");

	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	$sequence_next = db_fetch_cell("select sequence from $table_name where id=$next_item");
	db_execute("update $table_name set sequence=$sequence_next where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$next_item");
}

/* move_item_up - moves an item down by swapping it with the item above it
   @arg $table_name - the table name that contains the target id
   @arg $current_id - (int) the current id
   @arg $group_query - an SQL "where" clause to limit the query */
function move_item_up($table_name, $current_id, $group_query) {
	$last_item = get_item($table_name, "sequence", $current_id, $group_query, "previous");

	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	$sequence_last = db_fetch_cell("select sequence from $table_name where id=$last_item");
	db_execute("update $table_name set sequence=$sequence_last where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$last_item");
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
	if (!empty($_SERVER["HTTP_USER_AGENT"])) {
		if (stristr($_SERVER["HTTP_USER_AGENT"], "Mozilla") && (!(stristr($_SERVER["HTTP_USER_AGENT"], "compatible")))) {
			return "moz";
		}elseif (stristr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
			return "ie";
		}else{
			return "other";
		}
	}else{
		return "other";
	}
}

/* get_graph_tree_array - returns a list of graph trees taking permissions into account if
     necessary
   @arg $return_sql - (bool) Whether to return the SQL to create the dropdown rather than an array
	@arg $force_refresh - (bool) Force the refresh of the array from the database
   @returns - (array) an array containing a list of graph trees */
function get_graph_tree_array($return_sql = false, $force_refresh = false) {

	/* set the tree update time if not already set */
	if (!isset($_SESSION["tree_update_time"])) {
		$_SESSION["tree_update_time"] = time();
	}

	/* build tree array */
	if (!isset($_SESSION["tree_array"]) || ($force_refresh) ||
		(($_SESSION["tree_update_time"] + read_graph_config_option("page_refresh")) < time())) {

		if (read_config_option("auth_method") != 0) {
			$current_user = db_fetch_row("select policy_trees from user_auth where id=" . $_SESSION["sess_user_id"]);

			if ($current_user["policy_trees"] == "1") {
				$sql_where = "where user_auth_perms.user_id is null";
			}elseif ($current_user["policy_trees"] == "2") {
				$sql_where = "where user_auth_perms.user_id is not null";
			}

			$sql = "select
				graph_tree.id,
				graph_tree.name,
				user_auth_perms.user_id
				from graph_tree
				left join user_auth_perms on (graph_tree.id=user_auth_perms.item_id and user_auth_perms.type=2 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")
				$sql_where
				order by graph_tree.name";
		}else{
			$sql = "select * from graph_tree order by name";
		}

		$_SESSION["tree_array"] = $sql;
		$_SESSION["tree_update_time"] = time();
	} else {
		$sql = $_SESSION["tree_array"];
	}

	if ($return_sql == true) {
		return $sql;
	}else{
		return db_fetch_assoc($sql);
	}
}

/* get_host_array - returns a list of hosts taking permissions into account if necessary
   @returns - (array) an array containing a list of hosts */
function get_host_array() {
	if (read_config_option("auth_method") != 0) {
		$current_user = db_fetch_row("select policy_hosts from user_auth where id=" . $_SESSION["sess_user_id"]);

		if ($current_user["policy_hosts"] == "1") {
			$sql_where = "where user_auth_perms.user_id is null";
		}elseif ($current_user["policy_hosts"] == "2") {
			$sql_where = "where user_auth_perms.user_id is not null";
		}

		$host_list = db_fetch_assoc("select
			host.id,
			CONCAT_WS('',host.description,' (',host.hostname,')') as name,
			user_auth_perms.user_id
			from host
			left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")
			$sql_where
			order by host.description,host.hostname");
	}else{
		$host_list = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
	}

	return $host_list;
}

/* draw_navigation_text - determines the top header navigation text for the current page and displays it to
   @arg $type - (string) Either 'url' or 'title'
   @returns (string> Either the navigation text or title */
function draw_navigation_text($type = "url") {
	global $config;

	$nav_level_cache = (isset($_SESSION["sess_nav_level_cache"]) ? $_SESSION["sess_nav_level_cache"] : array());

	$nav = array(
		"graph_view.php:" => array("title" => "Graphs", "mapping" => "", "url" => "graph_view.php", "level" => "0"),
		"graph_view.php:tree" => array("title" => "Tree Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=tree", "level" => "1"),
		"graph_view.php:list" => array("title" => "List Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=list", "level" => "1"),
		"graph_view.php:preview" => array("title" => "Preview Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=preview", "level" => "1"),
		"graph.php:" => array("title" => "|current_graph_title|", "mapping" => "graph_view.php:,?", "level" => "2"),
		"graph.php:view" => array("title" => "|current_graph_title|", "mapping" => "graph_view.php:,?", "level" => "2"),
		"graph.php:zoom" => array("title" => "Zoom", "mapping" => "graph_view.php:,?,graph.php:view", "level" => "3"),
		"graph.php:properties" => array("title" => "Properties", "mapping" => "graph_view.php:,?,graph.php:view", "level" => "3"),
		"graph_settings.php:" => array("title" => "Settings", "mapping" => "graph_view.php:", "url" => "graph_settings.php", "level" => "1"),
		"index.php:" => array("title" => "Console", "mapping" => "", "url" => $config['url_path'] . "index.php", "level" => "0"),
		"index.php:login" => array("title" => "Console", "mapping" => "", "url" => $config['url_path'] . "index.php", "level" => "0"),
		"graphs.php:" => array("title" => "Graph Management", "mapping" => "index.php:", "url" => "graphs.php", "level" => "1"),
		"graphs.php:graph_edit" => array("title" => "(Edit)", "mapping" => "index.php:,graphs.php:", "url" => "", "level" => "2"),
		"graphs.php:graph_diff" => array("title" => "Change Graph Template", "mapping" => "index.php:,graphs.php:,graphs.php:graph_edit", "url" => "", "level" => "3"),
		"graphs.php:actions" => array("title" => "Actions", "mapping" => "index.php:,graphs.php:", "url" => "", "level" => "2"),
		"graphs_items.php:item_edit" => array("title" => "Graph Items", "mapping" => "index.php:,graphs.php:,graphs.php:graph_edit", "url" => "", "level" => "3"),
		"graphs_new.php:" => array("title" => "Create New Graphs", "mapping" => "index.php:", "url" => "graphs_new.php", "level" => "1"),
		"graphs_new.php:save" => array("title" => "Create Graphs from Data Query", "mapping" => "index.php:,graphs_new.php:", "url" => "", "level" => "2"),
		"gprint_presets.php:" => array("title" => "GPRINT Presets", "mapping" => "index.php:", "url" => "gprint_presets.php", "level" => "1"),
		"gprint_presets.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,gprint_presets.php:", "url" => "", "level" => "2"),
		"gprint_presets.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,gprint_presets.php:", "url" => "", "level" => "2"),
		"cdef.php:" => array("title" => "CDEF's", "mapping" => "index.php:", "url" => "cdef.php", "level" => "1"),
		"cdef.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,cdef.php:", "url" => "", "level" => "2"),
		"cdef.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,cdef.php:", "url" => "", "level" => "2"),
		"cdef.php:item_edit" => array("title" => "CDEF Items", "mapping" => "index.php:,cdef.php:,cdef.php:edit", "url" => "", "level" => "3"),
		"cdef.php:actions" => array("title" => "Actions", "mapping" => "index.php:,cdef.php:", "url" => "", "level" => "2"),
		"tree.php:" => array("title" => "Graph Trees", "mapping" => "index.php:", "url" => "tree.php", "level" => "1"),
		"tree.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,tree.php:", "url" => "", "level" => "2"),
		"tree.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,tree.php:", "url" => "", "level" => "2"),
		"tree.php:item_edit" => array("title" => "Graph Tree Items", "mapping" => "index.php:,tree.php:,tree.php:edit", "url" => "", "level" => "3"),
		"tree.php:item_remove" => array("title" => "(Remove Item)", "mapping" => "index.php:,tree.php:,tree.php:edit", "url" => "", "level" => "3"),
		"color.php:" => array("title" => "Colors", "mapping" => "index.php:", "url" => "color.php", "level" => "1"),
		"color.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,color.php:", "url" => "", "level" => "2"),
		"graph_templates.php:" => array("title" => "Graph Templates", "mapping" => "index.php:", "url" => "graph_templates.php", "level" => "1"),
		"graph_templates.php:template_edit" => array("title" => "(Edit)", "mapping" => "index.php:,graph_templates.php:", "url" => "", "level" => "2"),
		"graph_templates.php:actions" => array("title" => "Actions", "mapping" => "index.php:,graph_templates.php:", "url" => "", "level" => "2"),
		"graph_templates_items.php:item_edit" => array("title" => "Graph Template Items", "mapping" => "index.php:,graph_templates.php:,graph_templates.php:template_edit", "url" => "", "level" => "3"),
		"graph_templates_inputs.php:input_edit" => array("title" => "Graph Item Inputs", "mapping" => "index.php:,graph_templates.php:,graph_templates.php:template_edit", "url" => "", "level" => "3"),
		"graph_templates_inputs.php:input_remove" => array("title" => "(Remove)", "mapping" => "index.php:,graph_templates.php:,graph_templates.php:template_edit", "url" => "", "level" => "3"),
		"host_templates.php:" => array("title" => "Host Templates", "mapping" => "index.php:", "url" => "host_templates.php", "level" => "1"),
		"host_templates.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,host_templates.php:", "url" => "", "level" => "2"),
		"host_templates.php:actions" => array("title" => "Actions", "mapping" => "index.php:,host_templates.php:", "url" => "", "level" => "2"),
		"graph_templates.php:actions" => array("title" => "Actions", "mapping" => "index.php:,graph_templates.php:", "url" => "", "level" => "2"),
		"data_templates.php:" => array("title" => "Data Templates", "mapping" => "index.php:", "url" => "data_templates.php", "level" => "1"),
		"data_templates.php:template_edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_templates.php:", "url" => "", "level" => "2"),
		"data_templates.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_templates.php:", "url" => "", "level" => "2"),
		"data_sources.php:" => array("title" => "Data Sources", "mapping" => "index.php:", "url" => "data_sources.php", "level" => "1"),
		"data_sources.php:ds_edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_sources.php:", "url" => "", "level" => "2"),
		"data_sources.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_sources.php:", "url" => "", "level" => "2"),
		"host.php:" => array("title" => "Devices", "mapping" => "index.php:", "url" => "host.php", "level" => "1"),
		"host.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,host.php:", "url" => "", "level" => "2"),
		"host.php:actions" => array("title" => "Actions", "mapping" => "index.php:,host.php:", "url" => "", "level" => "2"),
		"rra.php:" => array("title" => "Round Robin Archives", "mapping" => "index.php:", "url" => "rra.php", "level" => "1"),
		"rra.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,rra.php:", "url" => "", "level" => "2"),
		"rra.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,rra.php:", "url" => "", "level" => "2"),
		"data_input.php:" => array("title" => "Data Input Methods", "mapping" => "index.php:", "url" => "data_input.php", "level" => "1"),
		"data_input.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_input.php:", "url" => "", "level" => "2"),
		"data_input.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_input.php:", "url" => "", "level" => "2"),
		"data_input.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,data_input.php:", "url" => "", "level" => "2"),
		"data_input.php:field_edit" => array("title" => "Data Input Fields", "mapping" => "index.php:,data_input.php:,data_input.php:edit", "url" => "", "level" => "3"),
		"data_input.php:field_remove" => array("title" => "(Remove Item)", "mapping" => "index.php:,data_input.php:,data_input.php:edit", "url" => "", "level" => "3"),
		"data_queries.php:" => array("title" => "Data Queries", "mapping" => "index.php:", "url" => "data_queries.php", "level" => "1"),
		"data_queries.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_queries.php:", "url" => "", "level" => "2"),
		"data_queries.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_queries.php:", "url" => "", "level" => "2"),
		"data_queries.php:item_edit" => array("title" => "Associated Graph Templates", "mapping" => "index.php:,data_queries.php:,data_queries.php:edit", "url" => "", "level" => "3"),
		"data_queries.php:item_remove" => array("title" => "(Remove Item)", "mapping" => "index.php:,data_queries.php:,data_queries.php:edit", "url" => "", "level" => "3"),
		"utilities.php:" => array("title" => "Utilities", "mapping" => "index.php:", "url" => "utilities.php", "level" => "1"),
		"utilities.php:view_poller_cache" => array("title" => "View Poller Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:view_snmp_cache" => array("title" => "View SNMP Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:clear_poller_cache" => array("title" => "Clear Poller Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:view_logfile" => array("title" => "View Cacti Log File", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:clear_logfile" => array("title" => "Clear Cacti Log File", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:view_user_log" => array("title" => "View User Log File", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:clear_user_log" => array("title" => "Clear User Log File", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:view_tech" => array("title" => "Technical Support", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"settings.php:" => array("title" => "Cacti Settings", "mapping" => "index.php:", "url" => "settings.php", "level" => "1"),
		"user_admin.php:" => array("title" => "User Management", "mapping" => "index.php:", "url" => "user_admin.php", "level" => "1"),
		"user_admin.php:user_edit" => array("title" => "(Edit)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:actions" => array("title" => "(Action)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:graph_perms_edit" => array("title" => "Edit (Graph Permissions)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:user_realms_edit" => array("title" => "Edit (Realm Permissions)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:graph_settings_edit" => array("title" => "Edit (Graph Settings)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"about.php:" => array("title" => "About Cacti", "mapping" => "index.php:", "url" => "about.php", "level" => "1"),
		"templates_export.php:" => array("title" => "Export Templates", "mapping" => "index.php:", "url" => "templates_export.php", "level" => "1"),
		"templates_export.php:save" => array("title" => "Export Results", "mapping" => "index.php:,templates_export.php:", "url" => "templates_export.php", "level" => "2"),
		"templates_import.php:" => array("title" => "Import Templates", "mapping" => "index.php:", "url" => "templates_import.php", "level" => "1"),
		);

	$nav =  api_plugin_hook_function('draw_navigation_text', $nav);

	$current_page = basename($_SERVER["PHP_SELF"]);

	input_validate_input_regex(get_request_var_request("action"), "^([a-zA-Z0-9_-]+)$");

	$current_action = (isset($_REQUEST["action"]) ? $_REQUEST["action"] : "");

	/* find the current page in the big array */
	if (isset($nav[$current_page . ":" . $current_action])) {
		$current_array = $nav{$current_page . ":" . $current_action};
	}else{
		$current_array = array('mapping' => 'index.php:', 'title' => ucwords(str_replace("_", " ", basename($_SERVER['PHP_SELF'], '.php'))), 'level' => 1);
	}

	$current_mappings = explode(",", $current_array["mapping"]);
	$current_nav = "";
	$title       = "";

	/* resolve all mappings to build the navigation string */
	for ($i=0; ($i<count($current_mappings)); $i++) {
		if (empty($current_mappings[$i])) { continue; }

		if  ($i == 0) {
			/* always use the default for level == 0 */
			$url = $nav{$current_mappings[$i]}["url"];
		}elseif (!empty($nav_level_cache{$i}["url"])) {
			/* found a match in the url cache for this level */
			$url = $nav_level_cache{$i}["url"];
		}elseif (!empty($current_array["url"])) {
			/* found a default url in the above array */
			$url = $current_array["url"];
		}else{
			/* default to no url */
			$url = "";
		}

		if ($current_mappings[$i] == "?") {
			/* '?' tells us to pull title from the cache at this level */
			if (isset($nav_level_cache{$i})) {
				$current_nav .= (empty($url) ? "" : "<a href='" . htmlspecialchars($url) . "'>") . htmlspecialchars(resolve_navigation_variables($nav{$nav_level_cache{$i}["id"]}["title"])) . (empty($url) ? "" : "</a>") . " -> ";
				$title       .= htmlspecialchars(resolve_navigation_variables($nav{$nav_level_cache{$i}["id"]}["title"])) . " -> ";
			}
		}else{
			/* there is no '?' - pull from the above array */
			$current_nav .= (empty($url) ? "" : "<a href='" . htmlspecialchars($url) . "'>") . htmlspecialchars(resolve_navigation_variables($nav{$current_mappings[$i]}["title"])) . (empty($url) ? "" : "</a>") . " -> ";
			$title       .= htmlspecialchars(resolve_navigation_variables($nav{$current_mappings[$i]}["title"])) . " -> ";
		}
	}

	$current_nav .= htmlspecialchars(resolve_navigation_variables($current_array["title"]));
	$title       .= htmlspecialchars(resolve_navigation_variables($current_array["title"]));

	/* keep a cache for each level we encounter */
	$nav_level_cache{$current_array["level"]} = array("id" => $current_page . ":" . $current_action, "url" => get_browser_query_string());
	$_SESSION["sess_nav_level_cache"] = $nav_level_cache;

	if ($type == "url") {
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
				$text = str_replace("|" . $matches[1][$i] . "|", get_graph_title($_GET["local_graph_id"]), $text);
				break;
			}
		}
	}

	return $text;
}

/* get_associated_rras - returns a list of all RRAs referenced by a particular graph
   @arg $local_graph_id - (int) the ID of the graph to retrieve a list of RRAs for
   @returns - (array) an array containing the name and id of each RRA found */
function get_associated_rras($local_graph_id) {
	return db_fetch_assoc("select
		rra.id,
		rra.steps,
		rra.rows,
		rra.name,
		rra.timespan,
		data_template_data.rrd_step
		from graph_templates_item
		LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
		LEFT JOIN data_template_data ON (data_template_rrd.local_data_id=data_template_data.local_data_id)
		LEFT JOIN data_template_data_rra ON (data_template_data.id=data_template_data_rra.data_template_data_id)
		LEFT JOIN rra ON (data_template_data_rra.rra_id=rra.id)
                where graph_templates_item.local_graph_id=$local_graph_id
		AND data_template_rrd.local_data_id != 0
		group by rra.id
		order by rra.timespan");
}

/* get_browser_query_string - returns the full url, including args requested by the browser
   @returns - the url requested by the browser */
function get_browser_query_string() {
	if (!empty($_SERVER["REQUEST_URI"])) {
		return sanitize_uri($_SERVER["REQUEST_URI"]);
	}else{
		return sanitize_uri(basename($_SERVER["PHP_SELF"]) . (empty($_SERVER["QUERY_STRING"]) ? "" : "?" . $_SERVER["QUERY_STRING"]));
	}
}

/* get_hash_graph_template - returns the current unique hash for a graph template
   @arg $graph_template_id - (int) the ID of the graph template to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_graph_template($graph_template_id, $sub_type = "graph_template") {
	if ($sub_type == "graph_template") {
		$hash = db_fetch_cell("select hash from graph_templates where id=$graph_template_id");
	}elseif ($sub_type == "graph_template_item") {
		$hash = db_fetch_cell("select hash from graph_templates_item where id=$graph_template_id");
	}elseif ($sub_type == "graph_template_input") {
		$hash = db_fetch_cell("select hash from graph_template_input where id=$graph_template_id");
	}

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_template - returns the current unique hash for a data template
   @arg $graph_template_id - (int) the ID of the data template to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_template($data_template_id, $sub_type = "data_template") {
	if ($sub_type == "data_template") {
		$hash = db_fetch_cell("select hash from data_template where id=$data_template_id");
	}elseif ($sub_type == "data_template_item") {
		$hash = db_fetch_cell("select hash from data_template_rrd where id=$data_template_id");
	}

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_input - returns the current unique hash for a data input method
   @arg $graph_template_id - (int) the ID of the data input method to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_input($data_input_id, $sub_type = "data_input_method") {
	if ($sub_type == "data_input_method") {
		$hash = db_fetch_cell("select hash from data_input where id=$data_input_id");
	}elseif ($sub_type == "data_input_field") {
		$hash = db_fetch_cell("select hash from data_input_fields where id=$data_input_id");
	}

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_cdef - returns the current unique hash for a cdef
   @arg $graph_template_id - (int) the ID of the cdef to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_cdef($cdef_id, $sub_type = "cdef") {
	if ($sub_type == "cdef") {
		$hash = db_fetch_cell("select hash from cdef where id=$cdef_id");
	}elseif ($sub_type == "cdef_item") {
		$hash = db_fetch_cell("select hash from cdef_items where id=$cdef_id");
	}

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_gprint - returns the current unique hash for a gprint preset
   @arg $graph_template_id - (int) the ID of the gprint preset to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_gprint($gprint_id) {
	$hash = db_fetch_cell("select hash from graph_templates_gprint where id=$gprint_id");

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_host_template - returns the current unique hash for a gprint preset
   @arg $host_template_id - (int) the ID of the host template to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_host_template($host_template_id) {
	$hash = db_fetch_cell("select hash from host_template where id=$host_template_id");

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_data_query - returns the current unique hash for a data query
   @arg $graph_template_id - (int) the ID of the data query to return a hash for
   @arg $sub_type (optional) return the hash for a particlar sub-type of this type
   @returns - a 128-bit, hexadecimal hash */
function get_hash_data_query($data_query_id, $sub_type = "data_query") {
	if ($sub_type == "data_query") {
		$hash = db_fetch_cell("select hash from snmp_query where id=$data_query_id");
	}elseif ($sub_type == "data_query_graph") {
		$hash = db_fetch_cell("select hash from snmp_query_graph where id=$data_query_id");
	}elseif ($sub_type == "data_query_sv_data_source") {
		$hash = db_fetch_cell("select hash from snmp_query_graph_rrd_sv where id=$data_query_id");
	}elseif ($sub_type == "data_query_sv_graph") {
		$hash = db_fetch_cell("select hash from snmp_query_graph_sv where id=$data_query_id");
	}

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_round_robin_archive - returns the current unique hash for a round robin archive
   @arg $rra_id - (int) the ID of the round robin archive to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_round_robin_archive($rra_id) {
	$hash = db_fetch_cell("select hash from rra where id=$rra_id");

	if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
		return $hash;
	}else{
		return generate_hash();
	}
}

/* get_hash_version - returns the item type and cacti version in a hash format
   @arg $type - the type of item to represent ('graph_template','data_template',
     'data_input_method','cdef','gprint_preset','data_query','host_template')
   @returns - a 24-bit hexadecimal hash (8-bits for type, 16-bits for version) */
function get_hash_version($type) {
	global $hash_type_codes, $hash_version_codes, $config;

	return $hash_type_codes[$type] . $hash_version_codes{$config["cacti_version"]};
}

/* generate_hash - generates a new unique hash
   @returns - a 128-bit, hexadecimal hash */
function generate_hash() {
	global $config;

	return md5(session_id() . microtime() . rand(0,1000));
}

/* debug_log_insert - inserts a line of text into the debug log
   @arg $type - the 'category' or type of debug message
   @arg $text - the actual debug message */
function debug_log_insert($type, $text) {
	if (!isset($_SESSION["debug_log"][$type])) {
		$_SESSION["debug_log"][$type] = array();
	}

	array_push($_SESSION["debug_log"][$type], $text);
}

/* debug_log_clear - clears the debug log for a particular category
   @arg $type - the 'category' to clear the debug log for. omitting this argument
     implies all categories */
function debug_log_clear($type = "") {
	if ($type == "") {
		kill_session_var("debug_log");
	}else{
		if (isset($_SESSION["debug_log"])) {
			unset($_SESSION["debug_log"][$type]);
		}
	}
}

/* debug_log_return - returns the debug log for a particular category
   @arg $type - the 'category' to return the debug log for.
   @returns - the full debug log for a particular category */
function debug_log_return($type) {
	$log_text = "";

	if ($type == "new_graphs") {
		if (isset($_SESSION["debug_log"][$type])) {
			for ($i=0; $i<count($_SESSION["debug_log"][$type]); $i++) {
				$log_text .= "<li>" . $_SESSION["debug_log"][$type][$i] . "</li>";
			}
		}
	}else{
		if (isset($_SESSION["debug_log"][$type])) {
			for ($i=0; $i<count($_SESSION["debug_log"][$type]); $i++) {
				$log_text .= "+ " . $_SESSION["debug_log"][$type][$i] . "<br>";
			}
		}
	}

	return $log_text;
}

/* sanitize_search_string - cleans up a search string submitted by the user to be passed
     to the database. NOTE: some of the code for this function came from the phpBB project.
   @arg $string - the original raw search string
   @returns - the sanitized search string */
function sanitize_search_string($string) {
	static $drop_char_match =   array('^', '$', '<', '>', '`', '\'', '"', '|', ',', '?', '+', '[', ']', '{', '}', '#', ';', '!', '=', '*');
	static $drop_char_replace = array(' ', ' ', ' ', ' ',  '',   '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ');

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

function cacti_escapeshellcmd($string) {
	global $config;

	if ($config["cacti_server_os"] == "unix") {
		return escapeshellcmd($string);
	}else{
		$replacements = "#&;`|*?<>^()[]{}$\\";

		for ($i=0; $i < strlen($replacements); $i++) {
			$string = str_replace($replacements[$i], " ", $string);
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
	if ($config["cacti_server_os"] == "unix") {
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

?>
