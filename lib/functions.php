<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
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
     in 'include/config_settings.php'
   @returns - the default value of the configuration option */
function read_default_graph_config_option($config_name) {
	global $config;
	
	include ($config["include_path"] . "/config_settings.php");
	
	while (list($tab_name, $tab_array) = each($settings_graphs)) {
		if (isset($tab_array[$config_name])) {
			return $tab_array[$config_name]["default"];
		}
	}
}

/* read_graph_config_option - finds the current value of a graph configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings_graphs array
     in 'include/config_settings.php'
   @returns - the current value of the graph configuration option */
function read_graph_config_option($config_name) {
	/* users must have cacti user auth turned on to use this */
	if (read_config_option("global_auth") != "on") {
		return read_default_graph_config_option($config_name);
	}
	
	if (isset($_SESSION["sess_graph_config_array"])) {
		$graph_config_array = $_SESSION["sess_graph_config_array"];
	}
	
	if (!isset($graph_config_array[$config_name])) {
		$db_setting = db_fetch_row("select value from settings_graphs where name='$config_name' and user_id=" . $_SESSION["sess_user_id"]);
		
		if (isset($db_setting["value"])) {
			$graph_config_array[$config_name] = $db_setting["value"];
		}else{
			$graph_config_array[$config_name] = read_default_graph_config_option($config_name);
		}
		
		$_SESSION["sess_graph_config_array"] = $graph_config_array;
	}
	
	return $graph_config_array[$config_name];
}

/* read_default_config_option - finds the default value of a Cacti configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/config_settings.php'
   @returns - the default value of the configuration option */
function read_default_config_option($config_name) {
	global $config;
	
	include ($config["include_path"] . "/config_settings.php");
	
	while (list($tab_name, $tab_array) = each($settings)) {
		if (isset($tab_array[$config_name]["default"])) {
			return $tab_array[$config_name]["default"];
		}
	}
}

/* read_config_option - finds the current value of a Cacti configuration setting
   @arg $config_name - the name of the configuration setting as specified $settings array
     in 'include/config_settings.php'
   @returns - the current value of the configuration option */
function read_config_option($config_name) {
	if (isset($_SESSION["sess_config_array"])) {
		$config_array = $_SESSION["sess_config_array"];
	}
	
	if (!isset($config_array[$config_name])) {
		$db_setting = db_fetch_row("select value from settings where name='$config_name'");
		
		if (isset($db_setting["value"])) {
			$config_array[$config_name] = $db_setting["value"];
		}else{
			$config_array[$config_name] = read_default_config_option($config_name);
		}
		
		$_SESSION["sess_config_array"] = $config_array;
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
     $messages array in 'include/config_arrays.php'
   @returns - the original $field_value */
function form_input_validate($field_value, $field_name, $regexp_match, $allow_nulls, $custom_message = 3) {
	/* write current values to the "field_values" array so we can retain them */
	$_SESSION["sess_field_values"][$field_name] = $field_value;
	
	if (($allow_nulls == true) && ($field_value == "")) {
		return $field_value;
	}
	
	/* php 4.2+ complains about empty regexps */
	if (empty($regexp_match)) { $regexp_match = ".*"; }
	
	if ((!ereg($regexp_match, $field_value) || (($allow_nulls == false) && ($field_value == "")))) {
		raise_message($custom_message);
		
		$_SESSION["sess_error_fields"][$field_name] = $field_name;
	}else{
		$_SESSION["sess_field_values"][$field_name] = $field_value;
	}
	
	return $field_value;
}

/* is_error_message - finds whether an error message has been raised and has not been outputted to the
     user
   @returns - (bool) whether the messages array contains an error or not */
function is_error_message() {
	global $config;
	
	include($config["include_path"] . "/config_arrays.php");
	
	if (isset($_SESSION["sess_messages"])) {
		if (is_array($_SESSION["sess_messages"])) {
			foreach (array_keys($_SESSION["sess_messages"]) as $current_message_id) {
				if ($messages[$current_message_id]["type"] == "error") { return true; }
			}
		}
	}
	
	return false;
}

/* raise_message - mark a message to be displayed to the user once display_output_messages() is called
   @arg $message_id - the ID of the message to raise as defined in $messages in 'include/config_arrays.php' */
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
				eval ('$message = "' . $messages[$current_message_id]["message"] . '";');
				
				switch ($messages[$current_message_id]["type"]) {
				case 'info':
					if ($error_message == false) {
						print "<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>";
						print "<tr><td bgcolor='#f5f5f5'><p class='textInfo'>$message</p></td></tr>";
						print "</table><br>";
						
						/* we don't need these if there are no error messages */
						kill_session_var("sess_field_values");
					}
					break;
				case 'error':
					print "<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #ff0000;'>";
					print "<tr><td bgcolor='#f5f5f5'><p class='textError'>Error: $message</p></td></tr>";
					print "</table><br>";
					break;
				}
			}
		}
	}
	
	kill_session_var("sess_messages");
}

/* display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $text - the actual text of the error message to display */
function display_custom_error_message($message) {
	print "<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #ff0000;'>";
	print "<tr><td bgcolor='#f5f5f5'><p class='textError'>Error: $message</p></td></tr>";
	print "</table><br>";
}

/* clear_messages - clears the message cache */
function clear_messages() {
	kill_session_var("sess_messages");
}

/* kill_session_var - kills a session variable using two methods -- session_unregister() and unset() */
function kill_session_var($var_name) {
	/* register_global = off: reset local settings cache so the user sees the new settings */
	session_unregister($var_name);
	
	/* register_global = on: reset local settings cache so the user sees the new settings */
	unset($_SESSION[$var_name]);
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
		
		$ret_array[$item_key] = $item[$key_value];
	}
	}
	
	return $ret_array;
}

/* log_data - logs a string to Cacti's log file or optionally to the browser
   @arg $string - the string to append to the log file
   @arg $output - (bool) whether to output the log line to the browser using pring() or not */
function log_data($string, $output = false) {
	global $config;
	
	/* fill in the current date for printing in the log */
	$date = date("m/d/Y g:i A");
	
	/* echo the data to the log (append) */
	$fp = fopen($config["base_path"] . "/log/rrd.log", "a");
	@fwrite($fp, "$date - $string\n");
	fclose($fp);
	
	if ($output == true) {
		print "$string\n";
	}
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
		from data_template_data,data_input
		where data_template_data.data_input_id=data_input.id
		and data_template_data.local_data_id=$local_data_id");
	
	/* snmp-actions don't have paths */
	if (($data_source["type_id"] == "2") || ($data_source["type_id"] == "3")) {
		return false;
	}
	
	$data = db_fetch_assoc("select
		data_input_fields.data_name,
		data_input_data.value
		from data_input_fields
		left join data_input_data
		on data_input_fields.id=data_input_data.data_input_field_id
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

/* get_data_source_name - gets the name of a data source item or generates a new one if one does not
     already exist
   @arg $data_template_rrd_id - (int) the ID of the data source item
   @returns - the name of the data source item or an empty string for an error */
function get_data_source_name($data_template_rrd_id) {    
	if (empty($data_template_rrd_id)) { return ""; }
	
	$data_source = db_fetch_row("select
		data_template_rrd.data_source_name,
		data_template_data.name
		from data_template_rrd,data_template_data
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
			$data_source_path = str_replace("<path_rra>", $config["base_path"] . "/rra", $data_source_path);
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

/* update_data_source_title_cache_from_template - updates the title cache for all data sources
     that match a given data template
   @arg $data_template_id - (int) the ID of the data template to match */
function update_data_source_title_cache_from_template($data_template_id) {
	$data = db_fetch_assoc("select local_data_id from data_template_data where data_template_id=$data_template_id and local_data_id>0");
	
	if (sizeof($data) > 0) {
	foreach ($data as $item) {
		update_data_source_title_cache($item["local_data_id"]);
	}
	}
}

/* update_data_source_title_cache_from_query - updates the title cache for all data sources
     that match a given data query/index combination
   @arg $snmp_query_id - (int) the ID of the data query to match
   @arg $snmp_index - the index within the data query to match */
function update_data_source_title_cache_from_query($snmp_query_id, $snmp_index) {
	$data = db_fetch_assoc("select id from data_local where snmp_query_id=$snmp_query_id and snmp_index=$snmp_index");
	
	if (sizeof($data) > 0) {
	foreach ($data as $item) {
		update_data_source_title_cache($item["id"]);
	}
	}
}

/* update_data_source_title_cache_from_host - updates the title cache for all data sources
     that match a given host
   @arg $host_id - (int) the ID of the host to match */
function update_data_source_title_cache_from_host($host_id) {
	$data = db_fetch_assoc("select id from data_local where host_id=$host_id");
	
	if (sizeof($data) > 0) {
	foreach ($data as $item) {
		update_data_source_title_cache($item["id"]);
	}
	}
}

/* update_data_source_title_cache - updates the title cache for a single data source
   @arg $local_data_id - (int) the ID of the data source to update the title cache for */
function update_data_source_title_cache($local_data_id) {
	db_execute("update data_template_data set name_cache='" . get_data_source_title($local_data_id) . "' where local_data_id=$local_data_id");
}

/* update_graph_title_cache_from_template - updates the title cache for all graphs
     that match a given graph template
   @arg $graph_template_id - (int) the ID of the graph template to match */
function update_graph_title_cache_from_template($graph_template_id) {
	$graphs = db_fetch_assoc("select local_graph_id from graph_templates_graph where graph_template_id=$graph_template_id and local_graph_id>0");
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $item) {
		update_graph_title_cache($item["local_graph_id"]);
	}
	}
}

/* update_graph_title_cache_from_query - updates the title cache for all graphs
     that match a given data query/index combination
   @arg $snmp_query_id - (int) the ID of the data query to match
   @arg $snmp_index - the index within the data query to match */
function update_graph_title_cache_from_query($snmp_query_id, $snmp_index) {
	$graphs = db_fetch_assoc("select id from graph_local where snmp_query_id=$snmp_query_id and snmp_index=$snmp_index");
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $item) {
		update_graph_title_cache($item["id"]);
	}
	}
}

/* update_graph_title_cache_from_host - updates the title cache for all graphs
     that match a given host
   @arg $host_id - (int) the ID of the host to match */
function update_graph_title_cache_from_host($host_id) {
	$graphs = db_fetch_assoc("select id from graph_local where host_id=$host_id");
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $item) {
		update_graph_title_cache($item["id"]);
	}
	}
}

/* update_graph_title_cache - updates the title cache for a single graph
   @arg $local_graph_id - (int) the ID of the graph to update the title cache for */
function update_graph_title_cache($local_graph_id) {
	db_execute("update graph_templates_graph set title_cache='" . get_graph_title($local_graph_id) . "' where local_graph_id=$local_graph_id");
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
		from data_template_data,data_local
		where data_template_data.local_data_id=data_local.id
		and data_local.id=$local_data_id");
	
	if ((strstr($data["name"], "|")) && (!empty($data["host_id"]))) {
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
		graph_templates_graph.title
		from graph_templates_graph,graph_local
		where graph_templates_graph.local_graph_id=graph_local.id
		and graph_local.id=$local_graph_id");
	
	if ((strstr($graph["title"], "|")) && (!empty($graph["host_id"]))) {
		return expand_title($graph["host_id"], $graph["snmp_query_id"], $graph["snmp_index"], $graph["title"]);
	}else{
		return $graph["title"];
	}
}

/* null_out_subsitions - takes a string and cleans out any host variables that do not have values
   @arg $string - the string to clean out unsubsituted variables for
   @returns - the cleaned up string */
function null_out_subsitions($string) {
	return eregi_replace("\|host_(hostname|description|snmp_community|snmp_version|snmp_username|snmp_password)\|( - )?", "", $string);
}

/* expand_title - takes a string and subsitutes all data query variables contained in it or cleans
     them out if no data query is in use
   @arg $host_id - (int) the host ID to match
   @arg $snmp_query_id - (int) the data query ID to match
   @arg $snmp_index - the data query index to match
   @arg $title - the original string that contains the data query variables
   @returns - the original string with all of the variable subsitutions made */
function expand_title($host_id, $snmp_query_id, $snmp_index, $title) {
	if ((strstr($title, "|")) && (!empty($host_id))) {
		if (($snmp_query_id != "0") && ($snmp_index != "")) {
			return subsitute_snmp_query_data(null_out_subsitions(subsitute_host_data($title, "|", "|", $host_id)), "|", "|", $host_id, $snmp_query_id, $snmp_index);
		}else{
			return null_out_subsitions(subsitute_host_data($title, "|", "|", $host_id));
		}
	}else{
		return null_out_subsitions($title);
	}
}

/* subsitute_data_query_path - takes a string and subsitutes all path variables contained in it
   @arg $path - the string to make path variable subsitutions on
   @returns - the original string with all of the variable subsitutions made */
function subsitute_data_query_path($path) {
	global $config;
	
	$path = str_replace("|path_cacti|", $config["base_path"], $path);
	$path = str_replace("|path_php_binary|", read_config_option("path_php_binary"), $path);
	
	return $path;
}

/* subsitute_host_data - takes a string and subsitutes all host variables contained in it
   @arg $string - the string to make host variable subsitutions on
   @arg $l_escape_string - the character used to escape each variable on the left side
   @arg $r_escape_string - the character used to escape each variable on the right side
   @arg $host_id - (int) the host ID to match
   @returns - the original string with all of the variable subsitutions made */
function subsitute_host_data($string, $l_escape_string, $r_escape_string, $host_id) {
	if (!isset($_SESSION["sess_host_cache_array"][$host_id])) {
		$host = db_fetch_row("select description,hostname,snmp_community,snmp_version,snmp_username,snmp_password from host where id=$host_id");
		$_SESSION["sess_host_cache_array"][$host_id] = $host;
	}
	
	$string = str_replace($l_escape_string . "host_management_ip" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["hostname"], $string); /* for compatability */
	$string = str_replace($l_escape_string . "host_hostname" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["hostname"], $string);
	$string = str_replace($l_escape_string . "host_description" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["description"], $string);
	$string = str_replace($l_escape_string . "host_snmp_community" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["snmp_community"], $string);
	$string = str_replace($l_escape_string . "host_snmp_version" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["snmp_version"], $string);
	$string = str_replace($l_escape_string . "host_snmp_username" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["snmp_username"], $string);
	$string = str_replace($l_escape_string . "host_snmp_password" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["snmp_password"], $string);
	
	return $string;
}

/* subsitute_snmp_query_data - takes a string and subsitutes all data query variables contained in it 
   @arg $string - the original string that contains the data query variables
   @arg $l_escape_string - the character used to escape each variable on the left side
   @arg $r_escape_string - the character used to escape each variable on the right side
   @arg $host_id - (int) the host ID to match
   @arg $snmp_query_id - (int) the data query ID to match
   @arg $snmp_index - the data query index to match
   @returns - the original string with all of the variable subsitutions made */
function subsitute_snmp_query_data($string, $l_escape_string, $r_escape_string, $host_id, $snmp_query_id, $snmp_index) {
	$snmp_cache_data = db_fetch_assoc("select field_name,field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id and snmp_index='$snmp_index'");
	
	if (sizeof($snmp_cache_data) > 0) {
	foreach ($snmp_cache_data as $data) {
		if ($data["field_value"] != "") {
			$string = stri_replace($l_escape_string . "query_" . $data["field_name"] . $r_escape_string, substr($data["field_value"],0,read_config_option("max_data_query_field_length")), $string);
		}
	}
	}
	
	return $string;
}

/* data_query_index - returns an array containing the data query ID and index value given
     a data query index type/value combination and a host ID
   @arg $index_type - the name of the index to match
   @arg $index_value - the value of the index to match
   @arg $host_id - (int) the host ID to match
   @returns - (array) the data query ID and index that matches the three arguments */
function data_query_index($index_type, $index_value, $host_id) {
	return db_fetch_row("select
		host_snmp_cache.snmp_query_id,
		host_snmp_cache.snmp_index
		from host_snmp_cache
		where host_snmp_cache.field_name='$index_type'
		and host_snmp_cache.field_value='$index_value'
		and host_snmp_cache.host_id=$host_id");
}

/* data_query_field_list - returns an array containing data query information for a given data source
   @arg $data_template_data_id - the ID of the data source to retrieve information for
   @returns - (array) an array that looks like:
     Array
     (
        [index_type] => ifIndex
        [index_value] => 3
        [output_type] => 13
     ) */
function data_query_field_list($data_template_data_id) {
	$field = db_fetch_assoc("select
		data_input_fields.type_code,
		data_input_data.value
		from data_input_fields,data_input_data
		where data_input_fields.id=data_input_data.data_input_field_id
		and data_input_data.data_template_data_id=$data_template_data_id
		and (data_input_fields.type_code='index_type' or data_input_fields.type_code='index_value' or data_input_fields.type_code='output_type')");
	$field = array_rekey($field, "type_code", "value");
	
	if ((!isset($field["index_type"])) || (!isset($field["index_value"])) || (!isset($field["output_type"]))) {
		return 0;
	}else{
		return $field;
	}
}

/* generate_data_source_path - creates a new data source path from scratch using the first data source
     item name and updates the database with the new value
   @arg $local_data_id - (int) the ID of the data source to generate a new path for
   @returns - the new generated path */
function generate_data_source_path($local_data_id) {
	$host_part = ""; $ds_part = "";
	
	/* try any prepend the name with the host description */
	$host_name = db_fetch_cell("select host.description from host,data_local where data_local.host_id=host.id and data_local.id=$local_data_id");
	
	if (!empty($host_name)) {
		$host_part = strtolower(clean_up_name($host_name)) . "_";
	}
	
	/* then try and use the internal DS name to identify it */
	$data_source_rrd_name = db_fetch_cell("select data_source_name from data_template_rrd where local_data_id=$local_data_id order by id");
	
	if (!empty($data_source_rrd_name)) {
		$ds_part = strtolower(clean_up_name($data_source_rrd_name));
	}else{
		$ds_part = "ds";
	}
	
	/* put it all together using the local_data_id at the end */
	$new_path = "<path_rra>/$host_part$ds_part" . "_" . "$local_data_id.rrd";
	
	/* update our changes to the db */
	db_execute("update data_template_data set data_source_path='$new_path' where local_data_id=$local_data_id");
	
	return $new_path;
}

/* generate_graph_def_name - takes a number and turns each digit into its letter-based
     counterpart for RRDTool DEF names (ex 1 -> a, 2 -> b, etc)
   @arg $graph_item_id - (int) the ID to generate a letter-based representation of
   @returns - a letter-based representation of the input argument */
function generate_graph_def_name($graph_item_id) {
	$lookup_table = array("a","b","c","d","e","f","g","h","i","j");
	
	$result = "";
	for($i=0; $i<strlen($graph_item_id); $i++) {
		$current_charcter = $graph_item_id[$i];
		$result .= $lookup_table[$current_charcter];
	}
	
	return $result;
}

/* generate_data_input_field_sequences - re-numbers the sequences of each field associated
     with a particular data input method based on its position within the input string
   @arg $string - the input string that contains the field variables in a certain order
   @arg $data_input_id - (int) the ID of the data input method
   @arg $inout - ('in' or 'out') whether these fields are from the input or output string */
function generate_data_input_field_sequences($string, $data_input_id, $inout) {
	global $config;
	
	include ($config["include_path"] . "/config_arrays.php");
	
	if (preg_match_all("/<([_a-zA-Z0-9]+)>/", $string, $matches)) {
		$j = 0;
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=$data_input_id and input_output='$inout' and data_name='" . $matches[1][$i] . "'");
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
	if (ereg("(GPRINT|VRULE|HRULE|COMMENT)", $graph_item_types{$graph_item["graph_type_id"]})) {
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
	if (stristr($_SERVER["HTTP_USER_AGENT"], "Mozilla") && (!(stristr($_SERVER["HTTP_USER_AGENT"], "compatible")))) {
		return "moz";
	}elseif (stristr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
		return "ie";
	}else{
		return "other";
	}
}

/* get_graph_permissions_sql - creates SQL that reprents the current graph, host and graph
     template policies
   @arg $policy_graphs - (int) the current graph policy
   @arg $policy_hosts - (int) the current host policy
   @arg $policy_graph_templates - (int) the current graph template policy
   @returns - an SQL "where" statement */
function get_graph_permissions_sql($policy_graphs, $policy_hosts, $policy_graph_templates) {
	$sql = "";
	$sql_or = "";
	$sql_and = "";
	$sql_policy_or = "";
	$sql_policy_and = "";
	
	if ($policy_graphs == "1") {
		$sql_policy_and .= "$sql_and(user_auth_perms.type != 1 OR user_auth_perms.type is null)";
		$sql_and = " AND ";
		$sql_null = "is null";
	}elseif ($policy_graphs == "2") {
		$sql_policy_or .= "$sql_or(user_auth_perms.type = 1 OR user_auth_perms.type is not null)";
		$sql_or = " OR ";
		$sql_null = "is not null";
	}
	
	if ($policy_hosts == "1") {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 3) OR (user_auth_perms.type is null))";
		$sql_and = " AND ";
	}elseif ($policy_hosts == "2") {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 3) OR (user_auth_perms.type is not null))";
		$sql_or = " OR ";
	}
	
	if ($policy_graph_templates == "1") {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 4) OR (user_auth_perms.type is null))";
		$sql_and = " AND ";
	}elseif ($policy_graph_templates == "2") {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 4) OR (user_auth_perms.type is not null))";
		$sql_or = " OR ";
	}
	
	$sql_and = "";
	
	if (!empty($sql_policy_or)) {
		$sql_and = "AND ";
		$sql .= $sql_policy_or;
	}
	
	if (!empty($sql_policy_and)) {
		$sql .= "$sql_and$sql_policy_and";
	}
	
	if (empty($sql)) {
		return "";
	}else{
		return "(" . $sql . ")";
	}
}

/* is_graph_allowed - determines whether the current user is allowed to view a certain graph
   @arg $local_graph_id - (int) the ID of the graph to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph or not */
function is_graph_allowed($local_graph_id) {
	$current_user = db_fetch_row("select policy_graphs,policy_hosts,policy_graph_templates from user_auth where id=" . $_SESSION["sess_user_id"]);
	
	/* get policy information for the sql where clause */
	$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
	
	$graphs = db_fetch_assoc("select
		graph_templates_graph.local_graph_id
		from graph_templates_graph,graph_local
		left join host on host.id=graph_local.host_id
		left join graph_templates on graph_templates.id=graph_local.graph_template_id
		left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
		where graph_templates_graph.local_graph_id=graph_local.id
		" . (empty($sql_where) ? "" : "and $sql_where") . "
		and graph_templates_graph.local_graph_id=$local_graph_id
		group by graph_templates_graph.local_graph_id");
	
	if (sizeof($graphs) > 0) {
		return true;
	}else{
		return false;
	}
}

/* is_tree_allowed - determines whether the current user is allowed to view a certain graph tree
   @arg $tree_id - (int) the ID of the graph tree to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph tree or not */
function is_tree_allowed($tree_id) {
	$current_user = db_fetch_row("select policy_trees from user_auth where id=" . $_SESSION["sess_user_id"]);
	
	$trees = db_fetch_assoc("select
		user_id
		from user_auth_perms
		where user_id=" . $_SESSION["sess_user_id"] . "
		and type=2
		and item_id=$tree_id");
	
	/* policy == allow AND matches = DENY */
	if ((sizeof($trees) > 0) && ($current_user["policy_trees"] == "1")) {
		return false;
	/* policy == deny AND matches = ALLOW */
	}elseif ((sizeof($trees) > 0) && ($current_user["policy_trees"] == "2")) {
		return true;
	/* policy == allow AND no matches = ALLOW */
	}elseif ((sizeof($trees) == 0) && ($current_user["policy_trees"] == "1")) {
		return true;
	/* policy == deny AND no matches = DENY */
	}elseif ((sizeof($trees) == 0) && ($current_user["policy_trees"] == "2")) {
		return false;
	}
}

/* get_graph_tree_array - returns a list of graph trees taking permissions into account if
     necessary
   @arg $return_sql - (bool) Whether to return the SQL to create the dropdown rather than an array
   @returns - (array) an array containing a list of graph trees */
function get_graph_tree_array($return_sql = false) {
	if (read_config_option("global_auth") == "on") {
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
	
	if ($return_sql == true) {
		return $sql;
	}else{
		return db_fetch_assoc($sql);
	}
}

/* get_host_array - returns a list of hosts taking permissions into account if necessary
   @returns - (array) an array containing a list of hosts */
function get_host_array() {
	if (read_config_option("global_auth") == "on") {
		$current_user = db_fetch_row("select policy_trees from user_auth where id=" . $_SESSION["sess_user_id"]);
		
		if ($current_user["policy_trees"] == "1") {
			$sql_where = "where user_auth_perms.user_id is null";
		}elseif ($current_user["policy_trees"] == "2") {
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
     the browser */
function draw_navigation_text() {
	$nav_level_cache = (isset($_SESSION["sess_nav_level_cache"]) ? $_SESSION["sess_nav_level_cache"] : array());
	
	$nav = array(
		"graph_view.php:" => array("title" => "Graphs", "mapping" => "", "url" => "graph_view.php", "level" => "0"),
		"graph_view.php:tree" => array("title" => "Tree Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=tree", "level" => "1"),
		"graph_view.php:list" => array("title" => "List Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=list", "level" => "1"),
		"graph_view.php:preview" => array("title" => "Preview Mode", "mapping" => "graph_view.php:", "url" => "graph_view.php?action=preview", "level" => "1"),
		"graph.php:" => array("title" => "", "mapping" => "graph_view.php:,?", "level" => "2"),
		"graph_settings.php:" => array("title" => "Settings", "mapping" => "graph_view.php:", "url" => "graph_settings.php", "level" => "1"),
		"index.php:" => array("title" => "Console", "mapping" => "", "url" => "index.php", "level" => "0"),
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
		"host_templates.php:edit" => array("title" => "(Edit)", "mapping" => "host_templates.php:,host_templates.php:", "url" => "", "level" => "2"),
		"host_templates.php:remove" => array("title" => "(Remove)", "mapping" => "host_templates.php:,host_templates.php:", "url" => "", "level" => "2"),
		"data_templates.php:" => array("title" => "Data Templates", "mapping" => "index.php:", "url" => "data_templates.php", "level" => "1"),
		"data_templates.php:template_edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_templates.php:", "url" => "", "level" => "2"),
		"data_templates.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_templates.php:", "url" => "", "level" => "2"),
		"data_sources.php:" => array("title" => "Data Sources", "mapping" => "index.php:", "url" => "data_sources.php", "level" => "1"),
		"data_sources.php:ds_edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_sources.php:", "url" => "", "level" => "2"),
		"data_sources.php:actions" => array("title" => "Actions", "mapping" => "index.php:,data_sources.php:", "url" => "", "level" => "2"),
		"host.php:" => array("title" => "Polling Hosts", "mapping" => "index.php:", "url" => "host.php", "level" => "1"),
		"host.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,host.php:", "url" => "", "level" => "2"),
		"host.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,host.php:", "url" => "", "level" => "2"),
		"rra.php:" => array("title" => "Round Robin Archives", "mapping" => "index.php:", "url" => "rra.php", "level" => "1"),
		"rra.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,rra.php:", "url" => "", "level" => "2"),
		"rra.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,rra.php:", "url" => "", "level" => "2"),
		"data_input.php:" => array("title" => "Data Input Methods", "mapping" => "index.php:", "url" => "data_input.php", "level" => "1"),
		"data_input.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,data_input.php:", "url" => "", "level" => "2"),
		"data_input.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,data_input.php:", "url" => "", "level" => "2"),
		"data_input.php:field_edit" => array("title" => "Data Input Fields", "mapping" => "index.php:,data_input.php:,data_input.php:edit", "url" => "", "level" => "3"),
		"data_input.php:field_remove" => array("title" => "(Remove Item)", "mapping" => "index.php:,data_input.php:,data_input.php:edit", "url" => "", "level" => "3"),
		"snmp.php:" => array("title" => "Data Queries", "mapping" => "index.php:", "url" => "snmp.php", "level" => "1"),
		"snmp.php:edit" => array("title" => "(Edit)", "mapping" => "index.php:,snmp.php:", "url" => "", "level" => "2"),
		"snmp.php:remove" => array("title" => "(Remove)", "mapping" => "index.php:,snmp.php:", "url" => "", "level" => "2"),
		"snmp.php:item_edit" => array("title" => "Associated Graph Templates", "mapping" => "index.php:,snmp.php:,snmp.php:edit", "url" => "", "level" => "3"),
		"snmp.php:item_remove" => array("title" => "(Remove Item)", "mapping" => "index.php:,snmp.php:,snmp.php:edit", "url" => "", "level" => "3"),
		"utilities.php:" => array("title" => "Utilities", "mapping" => "index.php:", "url" => "utilities.php", "level" => "1"),
		"utilities.php:view_poller_cache" => array("title" => "View Poller Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:view_snmp_cache" => array("title" => "View SNMP Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"utilities.php:clear_poller_cache" => array("title" => "Clear Poller Cache", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2"),
		"settings.php:" => array("title" => "Cacti Settings", "mapping" => "index.php:", "url" => "settings.php", "level" => "1"),
		"user_admin.php:" => array("title" => "User Management", "mapping" => "index.php:", "url" => "user_admin.php", "level" => "1"),
		"user_admin.php:user_edit" => array("title" => "User Configuration", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:user_remove" => array("title" => "(Remove)", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"user_admin.php:graph_perms_edit" => array("title" => "Graph Permissions", "mapping" => "index.php:,user_admin.php:", "url" => "", "level" => "2"),
		"about.php:" => array("title" => "About Cacti", "mapping" => "index.php:", "url" => "about.php", "level" => "1"),
		"templates_export.php:" => array("title" => "Export Templates", "mapping" => "index.php:", "url" => "templates_export.php", "level" => "1"),
		"templates_export.php:save" => array("title" => "Export Results", "mapping" => "index.php:,templates_export.php:", "url" => "templates_export.php", "level" => "2"),
		"templates_import.php:" => array("title" => "Import Templates", "mapping" => "index.php:", "url" => "templates_import.php", "level" => "1"),
		);
	
	$current_page = basename($_SERVER["PHP_SELF"]);
	$current_action = (isset($_REQUEST["action"]) ? $_REQUEST["action"] : "");
	
	/* find the current page in the big array */
	$current_array = $nav{$current_page . ":" . $current_action};
	$current_mappings = split(",", $current_array["mapping"]);
	$current_nav = "";
	
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
			$current_nav .= (empty($url) ? "" : "<a href='$url'>") . $nav{$nav_level_cache{$i}["id"]}["title"] . (empty($url) ? "" : "</a>") . " -> ";
		}else{
			/* there is no '?' - pull from the above array */
			$current_nav .= (empty($url) ? "" : "<a href='$url'>") . $nav{$current_mappings[$i]}["title"] . (empty($url) ? "" : "</a>") . " -> ";
		}
	}
	
	/* put on the last entry (current) */
	if (empty($current_array["title"])) {
		/* if no title is specified, try to resolve one */
		$current_nav .= resolve_navigation_title($current_page . ":" . $current_action);
	}else{
		/* use the title specified in the above array */
		$current_nav .= $current_array["title"];
	}
	
	/* keep a cache for each level we encounter */
	$nav_level_cache{$current_array["level"]} = array("id" => $current_page . ":" . $current_action, "url" => get_browser_query_string());
	$_SESSION["sess_nav_level_cache"] = $nav_level_cache;
	
	print $current_nav;
}

/* resolve_navigation_title - apply any special functions that are necessary to the navigation text, this
     function is only called if no other title is available
   @arg $id - the special function to use
   @returns - the original navigation text with all subsitutions made */
function resolve_navigation_title($id) {
	switch ($id) {
	case 'graph.php:':
		return get_graph_title($_GET["local_graph_id"]);
		break;
	}
	
	return;
}

/* get_associated_rras - returns a list of all RRAs referenced by a particular graph
   @arg $local_graph_id - (int) the ID of the graph to retrieve a list of RRAs for
   @returns - (array) an array containing the name and id of each RRA found */
function get_associated_rras($local_graph_id) {
	return db_fetch_assoc("select
		rra.id,
		rra.name
		from graph_templates_item,data_template_data_rra,data_template_rrd,data_template_data,rra
		where graph_templates_item.task_item_id=data_template_rrd.id
		and data_template_rrd.local_data_id=data_template_data.local_data_id
		and data_template_data.id=data_template_data_rra.data_template_data_id
		and data_template_data_rra.rra_id=rra.id
		and graph_templates_item.local_graph_id=$local_graph_id
		group by rra.id
		order by rra.timespan");
}

/* get_browser_query_string - returns the full url, including args requested by the browser
   @returns - the url requested by the browser */
function get_browser_query_string() {
	if (isset($_SERVER["REQUEST_URI"])) {
		return basename($_SERVER["REQUEST_URI"]);
	}else{
		return basename($_SERVER["PHP_SELF"]) . (empty($_SERVER["QUERY_STRING"]) ? "" : "?" . $_SERVER["QUERY_STRING"]);
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
	
	if (ereg("[a-fA-F0-9]{32}", $hash)) {
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
		unset($_SESSION["debug_log"][$type]);
	}
}

/* debug_log_return - returns the debug log for a particular category
   @arg $type - the 'category' to return the debug log for.
   @returns - the full debug log for a particular category */
function debug_log_return($type) {
	$log_text = "";
	
	if (isset($_SESSION["debug_log"][$type])) {
		for ($i=0; $i<count($_SESSION["debug_log"][$type]); $i++) {
			$log_text .= "+ " . $_SESSION["debug_log"][$type][$i] . "<br>";
		}
	}
	
	return $log_text;
}

?>
