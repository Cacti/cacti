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

function title_trim($text, $max_length) {
	if (strlen($text) > $max_length) {
		return substr($text, 0, $max_length) . "...";
	}else{
		return $text;
	}
}

function read_graph_config_option($config_name) {
	include ("config_settings.php");
	
	if (isset($_SESSION["sess_graph_config_array"])) {
		$graph_config_array = unserialize($_SESSION["sess_graph_config_array"]);
	}
	
	if (!isset($graph_config_array[$config_name])) {
		$graph_config_array[$config_name] = db_fetch_cell("select value from settings_graphs where name='$config_name' and user_id=" . $_SESSION["sess_user_id"]);
		
		if (empty($graph_config_array[$config_name])) {
			$graph_config_array[$config_name] = $settings_graphs[$config_name]["default"];
		}
			
		$_SESSION["sess_graph_config_array"] = serialize($graph_config_array);
	}
	
	return $graph_config_array[$config_name];
}

function read_config_option($config_name) {
	if (isset($_SESSION["sess_config_array"])) {
		$config_array = unserialize($_SESSION["sess_config_array"]);
	}
	
	if (!isset($config_array[$config_name])) {
		$config_array[$config_name] = db_fetch_cell("select value from settings where name='$config_name'");
		$_SESSION["sess_config_array"] = serialize($config_array);
	}
	
	return $config_array[$config_name];
}

function form_input_validate($field_value, $field_name, $regexp_match, $allow_nulls, $custom_message = 3) {
	if (($allow_nulls == true) && ($field_value == "")) {
		if (isset($_SESSION["sess_field_values"])) {
			$array_field_names = unserialize($_SESSION["sess_field_values"]);
		}
		
		$array_field_names[$field_name] = $field_value;
		$_SESSION["sess_field_values"] = serialize($array_field_names);
		
		return $field_value;
	}
	
	/* php 4.2+ complains about empty regexps */
	if (empty($regexp_match)) { $regexp_match = ".*"; }
	
	if ((!ereg($regexp_match, $field_value) || (($allow_nulls == false) && ($field_value == "")))) {
		raise_message($custom_message);
		
		if (isset($_SESSION["sess_error_fields"])) {
			$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		}
		
		$array_error_fields[$field_name] = $field_name;
		$_SESSION["sess_error_fields"] = serialize($array_error_fields);
	}else{
		if (isset($_SESSION["sess_error_fields"])) {
			$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		}
		
		$array_field_names[$field_name] = $field_value;
		$_SESSION["sess_field_values"] = serialize($array_field_names);
	}
	
	return $field_value;
}

function is_error_message() {
	include("config_arrays.php");
	
	if (isset($_SESSION["sess_messages"])) {
		$array_messages = unserialize($_SESSION["sess_messages"]);
		
		if (is_array($array_messages)) {
			foreach (array_keys($array_messages) as $current_message_id) {
				if ($messages[$current_message_id]["type"] == "error") { return true; }
			}
		}
	}
	
	return false;
}

function raise_message($message_id) {
	if (isset($_SESSION["sess_messages"])) {
		$array_messages = unserialize($_SESSION["sess_messages"]);
	}
	
	$array_messages[$message_id] = $message_id;
	$_SESSION["sess_messages"] = serialize($array_messages);
}

function display_output_messages() {
	include("config_arrays.php");
	include_once("form.php");
	
	if (isset($_SESSION["sess_messages"])) {
		$error_message = is_error_message();
		
		$array_messages = unserialize($_SESSION["sess_messages"]);
		
		if (is_array($array_messages)) {
			foreach (array_keys($array_messages) as $current_message_id) {
				eval ('$message = "' . $messages[$current_message_id]["message"] . '";');
				
				switch ($messages[$current_message_id]["type"]) {
				case 'info':
					if ($error_message == false) {
						print "<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>";
						print "<tr><td bgcolor='#f5f5f5'><p class='textInfo'>$message</p></td></tr>";
						print "</table><br>";
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
	
	session_unregister("sess_messages");
}

function clear_messages() {
	session_unregister("sess_messages");
}

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

function draw_menu() {
	global $colors;
	
	/* set up the available menu headers */
	$menu = array(
		"Graph Setup" => array(
			"graphs.php" => "Graph Management",
			"gprint_presets.php" => "GPRINT Presets",
			"tree.php" => "Graph Trees",
			"color.php" => "Colors"
			),
		"Templates" => array(
			"graph_templates.php" => "Graph Templates",
			"host_templates.php" => "Host Templates",
			"data_templates.php" => "Data Templates"
			),
		"Data Gathering" => array(
			"data_sources.php" => "Data Sources",
			"host.php" => 'Polling Hosts',
			"rra.php" => "Available RRA's",
			"data_input.php" => "Data Input Methods",
			"snmp.php" => "Data Queries",
			"cdef.php" => "CDEF's"
			),
		"Configuration"  => array(
			"utilities.php" => "Utilities",
			"settings.php" => "Cacti Settings"
			),
		"Utilities" => array(
			"user_admin.php" => "User Management",
			"logout.php" => "Logout User"
		)
	);
	
	print "<tr><td width='100%'><table cellpadding='3' cellspacing='0' border='0' width='100%'>\n";
	
	foreach (array_keys($menu) as $header) {
		print "<tr><td class='textMenuHeader'>$header</td></tr>\n";
		if (sizeof($menu[$header]) > 0) {
			foreach (array_keys($menu[$header]) as $url) {
				print "<tr><td class='textMenuItem' background='images/menu_line.gif'><a href='$url'>".$menu[$header][$url]."</a></td></tr>\n";
			}
		}
	}
	
	print '</table></td></tr>';
}

function LogData($string) {
    global $config,$colors,$paths;
    
    /* fill in the current date for printing in the log */
    $date = date("m/d/Y g:i A");
    
    /* echo the data to the log (append) */
    exec("echo '$date - $string' >> $paths[log]");
}

function get_full_script_path($local_data_id) {
	global $paths;
	
	$data_source = db_fetch_row("select
		data_template_data.id,
		data_template_data.data_input_id,
		data_input.type_id,
		data_input.input_string
		from data_template_data,data_input
		where data_template_data.data_input_id=data_input.id
		and data_template_data.local_data_id=$local_data_id");
	
	if ($data_source["type_id"] > 1) {
		return 0;
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
	
	$full_path = str_replace("<path_cacti>", $paths["cacti"], $full_path);
	$full_path = str_replace("<path_snmpget>", read_config_option("path_snmpget"), $full_path);
	$full_path = str_replace("<path_php_binary>", read_config_option("path_php_binary"), $full_path);
	
	/* sometimes a certain input value will not have anything entered... null out these fields
	in the input string so we don't mess up the script */
	$full_path = preg_replace("/(<[A-Za-z0-9_]+>)+/", "", $full_path);
	
	return $full_path;
}

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

function get_data_source_path($local_data_id, $expand_paths) {
	global $paths;
	
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
			$data_source_path = str_replace("<path_rra>", $paths["rra"], $data_source_path);
		}
		
		return $data_source_path;
	}
}

function stri_replace($find, $replace, $string) {
	$parts = explode(strtolower($find), strtolower($string));
	
	$pos = 0;
	
	foreach ($parts as $key=>$part) {
		$parts[$key] = substr($string, $pos, strlen($part));
		$pos += strlen($part) + strlen($find);
	}
	
	return (join($replace, $parts));
}

function clean_up_name($string) {
	$string = preg_replace("/[\s\.]+/", "_", $string);
	$string = preg_replace("/_{2,}/", "_", $string);
	$string = preg_replace("/[^a-zA-Z0-9_]+/", "", $string);
	
	return $string;
}

function subsitute_host_data($string, $l_escape_string, $r_escape_string, $host_id) {
	$host = db_fetch_row("select description,hostname,management_ip,snmp_community,snmp_version,snmp_username,snmp_password from host where id=$host_id");
	
	$string = str_replace($l_escape_string . "host_hostname" . $r_escape_string, $host["hostname"], $string);
	$string = str_replace($l_escape_string . "host_description" . $r_escape_string, $host["description"], $string);
	$string = str_replace($l_escape_string . "host_management_ip" . $r_escape_string, $host["management_ip"], $string);
	$string = str_replace($l_escape_string . "host_snmp_community" . $r_escape_string, $host["snmp_community"], $string);
	$string = str_replace($l_escape_string . "host_snmp_version" . $r_escape_string, $host["snmp_version"], $string);
	$string = str_replace($l_escape_string . "host_snmp_username" . $r_escape_string, $host["snmp_username"], $string);
	$string = str_replace($l_escape_string . "host_snmp_password" . $r_escape_string, $host["snmp_password"], $string);
	
	return $string;
}

function subsitute_snmp_query_data($string, $l_escape_string, $r_escape_string, $host_id, $snmp_query_id, $snmp_index) {
	$snmp_cache_data = db_fetch_assoc("select field_name,field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id and snmp_index='$snmp_index'");
	
	if (sizeof($snmp_cache_data) > 0) {
	foreach ($snmp_cache_data as $data) {
		if (!empty($data["field_value"])) {
			$string = stri_replace($l_escape_string . "squery_" . $data["field_name"] . $r_escape_string, substr($data["field_value"],0,15), $string);
		}
	}
	}
	
	return $string;
}

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

function generate_graph_def_name($graph_item_id) {
	$lookup_table = array("a","b","c","d","e","f","g","h","i","j");
	
	$result = "";
	for($i=0; $i<strlen($graph_item_id); $i++) {
		$current_charcter = $graph_item_id[$i];
		$result .= $lookup_table[$current_charcter];
	}
	
	return $result;
}

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
		return 0;
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

function move_item_down($table_name, $current_id, $group_query) {
	$next_item = get_item($table_name, "sequence", $current_id, $group_query, "next");
	
	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	$sequence_next = db_fetch_cell("select sequence from $table_name where id=$next_item");
	db_execute("update $table_name set sequence=$sequence_next where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$next_item");
}

function move_item_up($table_name, $current_id, $group_query) {
	$last_item = get_item($table_name, "sequence", $current_id, $group_query, "previous");
	
	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	$sequence_last = db_fetch_cell("select sequence from $table_name where id=$last_item");
	db_execute("update $table_name set sequence=$sequence_last where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$last_item");
}

function exec_into_array($command_line) {
	exec($command_line,$out,$err);
	
	$command_array = array();
	
	for($i=0; list($key, $value) = each($out); $i++) {
		$command_array[$i] = $value;
	}
	
	return $command_array;
}

function get_web_browser() {
	if (stristr($_SERVER["HTTP_USER_AGENT"], "Mozilla") && (!(stristr($_SERVER["HTTP_USER_AGENT"], "compatible")))) {
		return "moz";
	}elseif (stristr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
		return "ie";
	}else{
		return "other";
	}
}

function hex2bin($data) {
	$len = strlen($data);
	
	for($i=0;$i<$len;$i+=2) {
		$newdata .=  pack("C",hexdec(substr($data,$i,2)));
	}
	
	return $newdata;
}

?>
