<?/* 
   +-------------------------------------------------------------------------+
   | Copyright (C) 2002 Ian Berry                                            |
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
   | cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
   +-------------------------------------------------------------------------+
   | This code is currently maintained and debugged by Ian Berry, any        |
   | questions or comments regarding this code should be directed to:        |
   | - iberry@raxnet.net                                                     |
   +-------------------------------------------------------------------------+
   | - raXnet - http://www.raxnet.net/                                       |
   +-------------------------------------------------------------------------+
   */?>
<?

function form_input_validate($field_value, $field_name, $regexp_match, $allow_nulls, $custom_message = 3) {
	if (($allow_nulls == true) && ($field_value == "")) {
		$array_field_names = unserialize($_SESSION["sess_field_values"]);
		$array_field_names[$field_name] = $field_value;
		$_SESSION["sess_field_values"] = serialize($array_field_names);
		
		return $field_value;
	}
	
	if ((!ereg($regexp_match, $field_value) || (($allow_nulls == false) && ($field_value == "")))) {
		raise_message($custom_message);
		
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		$array_error_fields[$field_name] = $field_name;
		$_SESSION["sess_error_fields"] = serialize($array_error_fields);
	}else{
		$array_field_names = unserialize($_SESSION["sess_field_values"]);
		$array_field_names[$field_name] = $field_value;
		$_SESSION["sess_field_values"] = serialize($array_field_names);
	}
	
	return $field_value;
}

function is_error_message() {
	include("config_arrays.php");
	
	$array_messages = unserialize($_SESSION["sess_messages"]);
	
	if (is_array($array_messages)) {
		foreach (array_keys($array_messages) as $current_message_id) {
			if ($messages[$current_message_id]["type"] == "error") { return true; }
		}
	}
	
	return false;
}

function raise_message($message_id) {
	$array_messages = unserialize($_SESSION["sess_messages"]);
	$array_messages[$message_id] = $message_id;
	$_SESSION["sess_messages"] = serialize($array_messages);
}

function display_output_messages() {
	include("config_arrays.php");
	include_once("form.php");
	global $colors;
	
	$array_messages = unserialize($_SESSION["sess_messages"]);
	
	if (is_array($array_messages)) {
		foreach (array_keys($array_messages) as $current_message_id) {
			eval ('$message = "' . $messages[$current_message_id]["message"] . '";');
			
			switch ($messages[$current_message_id]["type"]) {
			case 'info':
				start_pagebox("", "98%", "00438C", "3", "center", "");
				print "<tr><td bgcolor='#f5f5f5'><p class='textInfo'>$message</p></td></tr>";
				end_box();
				break;
			case 'error':
				start_pagebox("", "98%", "ff0000", "3", "center", "");
				print "<tr><td bgcolor='#f5f5f5'><p class='textError'>Error: $message</p></td></tr>";
				end_box();
				break;
			}
		}
	}
	
	session_unregister("sess_messages");
}

function array_rekey($array, $key, $key_value) {
	if (sizeof($array) > 0) {
	foreach ($array as $item) {
		$item_key = $item[$key];
		
		$ret_array[$item_key] = $item[$key_value];
	}
	}
	
	return $ret_array;
}

function LogData($string) {
    global $config,$colors,$paths;
    
    /* fill in the current date for printing in the log */
    $date = date("m/d/Y g:i A");
    
    /* echo the data to the log (append) */
    exec("echo '$date - $string' >> $paths[log]");
}

function GetCronPath($dsid) {
    global $cnn_id,$config,$paths;
	
    $data = db_fetch_row("select d.ID, d.SrcID,
		s.FormatStrIn, s.FormatStrOut
	    from rrd_ds d 
	    left join src s 
	    on d.srcid=s.id 
	    where d.id=$dsid");
	
    if (sizeof($data) > 0) {
		/* make the input string */
		$fields = db_fetch_assoc("select d.FieldID, d.DSID, d.Value,
					   f.SrcID, f.DataName
					   from src_data d
					   left join src_fields f
					   on d.fieldid=f.id
					   where d.dsid=$data[ID]
					   and f.srcid=$data[SrcID]");
		$rows_fields = sizeof($fields);
		
		/* put the input string into a variable for easy access (r) */
		$str = $data[FormatStrIn];
		
		/* loop through each input field we find in the database and do a replace on
		 each one accordingly. */
	if (sizeof($fields) > 0) {
	    foreach ($fields as $field) {
		$str = ereg_replace ("<$field[DataName]>","$field[Value]",$str);
	    }
	}
		
		/* do a little path subsitution */
		$str = ereg_replace ("<path_cacti>", $paths[cacti],$str);
		$str = ereg_replace ("<path_snmpget>", $config["path_snmpget"]["value"],$str);
		$str = ereg_replace ("<path_php_binary>", $config["path_php_binary"]["value"],$str);
		
		return $str;
    }
}

function GetDataSourceName($dsid) {    
    if ($dsid == 0) { return ""; }
    
    $data = db_fetch_row("select Name, DSname from rrd_ds where id=$dsid");
    
    /* use the cacti ds name by default or the user defined one, if entered */
    if ($data[DBName] == "") {
		/* limit input to 19 characters */
		return CheckDataSourceName($data[Name]);
    }else{
		return $data[Name];
    }
}

function GetDataSourceType($dstypeid) {    
    if ($dstypeid == 0) { return ""; }
    
    $data = db_fetch_row("select Name from def_ds where id=$dstypeid");
    
    if (sizeof($data) >  0) {
		return $data[Name];
    }
    
    return "";
}

function GetDataSourcePath($data_source_id, $expand_paths) {
    global $config,$paths;
    if ($data_source_id == 0) { return ""; }
    
    $data = db_fetch_row("select Name, DSPath from rrd_ds where id=$data_source_id");
    
    if (sizeof($data) > 0) {
		if ($data[DSPath] == "") {
		    /* no custom path was specified */
		    $data_source_path = "<path_rra>/" . CheckDataSourceName($data[Name]) . ".rrd";
		}else{
		    $pos = strpos($data[DSPath],"/");
		    
		    /* make sure we represent the path correctly */
		    if (is_string ($pos) && !$pos) {
				$data_source_path = "<path_rra>/$data[DSPath]";
		    }else{
				$data_source_path = $data[DSPath];
		    }
		}
		
		/* whether to show the "actual" path or the <path_rra> variable name (for edit boxes) */
		if ($expand_paths == true) {
		    $data_source_path = ereg_replace ("<path_rra>", $paths[rra], $data_source_path);
		}
		
		return $data_source_path;
    }
}

function CheckDataSourceName($data_source_name) {
    $new_data_source_name = str_replace(" ","_",$data_source_name);
    $new_data_source_name = ereg_replace("[*]|[/]|[\]|[*]|[&]|[%]|[\"]|[\']|[,]|[.]","",$new_data_source_name);
    $new_data_source_name = substr($new_data_source_name,0,19);
    
    return strtolower($new_data_source_name);
}

function CleanUpName($string) {
    $new_string = ereg_replace("[ ]|[.]","_",$string);
    $new_string = ereg_replace("[*]|[/]|[\]|[*]|[&]|[%]|[\"]|[\']|[,]","",$new_string);
    
    return $new_string;
}

function CreateGraphDefName($graph_item_id) {
    $lookup_table = array("a","b","c","d","e","f","g","h","i","j");
    
    for($i=0; $i<strlen($graph_item_id); $i++) {
		$current_charcter = $graph_item_id[$i];
		$result .= $lookup_table[$current_charcter];
    }
    
    return $result;
}

function GetMultiCdefID($type, $value) {
    /* 	Type codes:
     3 - Total	 staggered datasources.
     5 - Average staggered datasources. */
    
    if ($type == 3 || $type == 5) {
		parse_str($value);
		return $start;
    }
    
    return "";
}

function GetGraphDefID($graph_item_id, $def_items) {
    $result = "";
    
    for($i=0; $i<sizeof($def_items); $i++) {
		if ($def_items[$i] == $graph_item_id) {
		    $result = "345" . $i;
		}
    }
    
    if ($result == "") {
		$result = $graph_item_id;
    }
    
    return $result;
}


function SyncDataSourceName($data_source_id, $saved_custom_data_source_name, $saved_custom_data_source_path) {
    global $cnn_id;
    #include_once("include/database.php");
    
    /* get old data for comparison */
    if ($data_source_id != 0) {
		$data = db_fetch_row("select dsname,dspath,name from rrd_ds where id=$data_source_id");
		
		if (sizeof($data) > 0) {
		    $old_data_source_name = $data[Name];
		    $old_custom_data_source_path = $data[DSPath];
		    $old_custom_data_source_name = $data[DSName];
		}
    }
    
    $dsname = $saved_custom_data_source_name;
    $dspath = $saved_custom_data_source_path;
    
    /* only update the dspath if there isn't already one */
    if ($old_custom_data_source_path == "") {
		$dspath = GetDataSourcePath($data_source_id, false);
    }
    
    /* only update the dsname if there isn't already one */
    if ($old_custom_data_source_name == "") {
		$dsname = GetDataSourceName($data_source_id);
    }
    
    db_execute("update rrd_ds set dsname=\"$dsname\", dspath=\"$dspath\" where id=$data_source_id");
    
    /* find out if this DS has children; if it does, then write the dspath to each child */
    db_execute("update rrd_ds set dspath=\"$dspath\" where subdsid=$data_source_id");
}

function CreateList($data,$name,$value,$prev) {
    if (($name == "") && ($value == "")) {
	foreach (array_keys($data) as $id) {
	    print '<option value="' . $id . '"';
	    
		if ($prev == $id) {
			print " selected";
	    }
		
	    print ">" . $data[$id] . "</option>\n";
	}
    }else{
	foreach ($data as $row) {
	    print "<option value='$row[$value]'";
	    
		if ($prev == $row[$value]) {
			print " selected";
	    }
		
	    print ">$row[$name]</option>\n";
	}
    }
}

function get_next_item($tblname,$field,$startid,$lmt_query) {
    $data1 = db_fetch_row("select max($field) mymax from $tblname where $lmt_query");
    $end_seq = $data1[mymax];
    $data2 = db_fetch_row("select $field from $tblname where id=$startid");
    $start_seq = $data2[$field];
    
    $i = $start_seq;
    if ($end_seq != $start_seq) {
		while ($i < $end_seq) {
		    $data3 = db_fetch_row("select $field from $tblname where $field=$i+1 and $lmt_query");
		    if (sizeof($data3) > 0) { return $data3[$field]; }
		    $i++;
		}
    }
	
    return $start_seq;
}

function get_last_item($tblname,$field,$startid,$lmt_query) {
    $data1 = db_fetch_row("select min($field) mymin from $tblname where $lmt_query");
    $end_seq = $data1[mymin];
    $data2 = db_fetch_row("select $field from $tblname where id=$startid");
    $start_seq = $data2[$field];
    
    $i = $start_seq;
    if ($end_seq != $start_seq) {
		while ($i > $end_seq) {
		    $data3 = db_fetch_row("select $field from $tblname where $field=$i-1 and $lmt_query");
		    if (sizeof($data3) > 0 && $data3[$field] != 0) {
				return $data3[$field];
		    }
		    $i--;
		}
    }
	
    return $start_seq;
}

function get_sequence($id, $field, $table_name, $group_query) {
    if (($id=="0") || ($id == "")) {
		$data = db_fetch_row("select max($field)+1 as seq from $table_name where $group_query");
		
		if ($data[seq] == "") {
		    return 1;
		}else{
		    return $data[seq];
		}
	}else{
		$data = db_fetch_row("select $field from $table_name where id=$id");
		return $data[$field];
    }
}

function move_item_down($table_name, $current_id, $group_query) {
	$next_item = get_next_item($table_name, "sequence", $current_id, $group_query);
	
	$id = db_fetch_cell("select id from $table_name where sequence=$next_item and $group_query");
	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	db_execute("update $table_name set sequence=$next_item where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$id");
}

function move_item_up($table_name, $current_id, $group_query) {
	$last_item = get_last_item($table_name, "sequence", $current_id, $group_query);
	
	$id = db_fetch_cell("select id from $table_name where sequence=$last_item and $group_query");
	$sequence = db_fetch_cell("select sequence from $table_name where id=$current_id");
	db_execute("update $table_name set sequence=$last_item where id=$current_id");
	db_execute("update $table_name set sequence=$sequence where id=$id");
}

function LoadSettingsIntoArray($user_id, $guest_account) {
    /* get settings, use guest account if there is no user cookie */
    $user_id = GetCurrentUserID($user_id, $guest_account);
    
    $settings = db_fetch_row("select * from settings_graphs where userid=$user_id");
    
    /* whether to revert to defaults or not */
    if ($user_id == "") {
		$use_default_settings = true;
    }else{
	    if (sizeof($settings) == 0) {
		    $use_default_settings = true;
	    }
    }
    
    if ($use_default_settings == true) {
		/* use defaults */
		$array_settings[height] = 100;
		$array_settings[width] = 300;
		$array_settings[time_span] = 60000;
		$array_settings[rra] = 1;
		$array_settings[column_number] = 2;
		$array_settings[page_refresh] = 300;
		$array_settings[list_view_type] = 1;
		$array_settings[view_type] = 1;
    }else{
		$array_settings[height] = $settings[Height];
		$array_settings[width] = $settings[Width];
		$array_settings[time_span] = $settings[Timespan];
		$array_settings[rra] = $settings[RRAID];
		$array_settings[column_number] = $settings[ColumnNumber];
		$array_settings[page_refresh] = $settings[PageRefresh];
		$array_settings[list_view_type] = $settings[ListViewType];
		$array_settings[view_type] = $settings[ViewType];
		$array_settings[tree_id] = $settings[TreeID];
    }
    
    return $array_settings;
}

function GetCurrentUserID($user_id, $guest_account) {
    if (($user_id == "") || ($user_id == 0)) {
		$data = db_fetch_row("select id from auth_users where username=\"$guest_account\"");
		if (sizeof($data) >  0) { $user_id = $data[ID]; }
    }
    
    return $user_id;
}

function ParseDelimitedLine($str,$delimiter) {
    if ($delimiter == "") {
		$fill_array[0] = $str;
		return $fill_array;
    }else{
		$fill_array = explode($delimiter, $str);
		return $fill_array;
    }
}

function exec_into_array($command_line) {
    exec($command_line,$out,$err);
    
    for($i=0; list($key, $value) = each($out); $i++) {
		$command_array[$i] = $value;
    }
    
    return $command_array;
}

function convert_mac_address($mac_address) {
    return strtolower(str_replace(" ", ":", $mac_address));
}

function hex2bin($data) {
    $len = strlen($data);
    
    for($i=0;$i<$len;$i+=2) {
		$newdata .=  pack("C",hexdec(substr($data,$i,2)));
    }
    
    return $newdata;
}

function BuildGraphTitleFromSNMP($graph_parameters) {
    $data = db_fetch_row("select * from snmp_hosts_interfaces where id=" . $graph_parameters["snmp_interface_id"]);
    
    if ($data > 0) {
		$graph_parameters["unparsed_graph_title"] = str_replace("<data_source_name>", $graph_parameters["data_source_name"], $graph_parameters["unparsed_graph_title"]);
		$graph_parameters["unparsed_graph_title"] = str_replace("<snmp_description>", $data[Description], $graph_parameters["unparsed_graph_title"]);
		$graph_parameters["unparsed_graph_title"] = str_replace("<snmp_interface_number>", $data[InterfaceNumber], $graph_parameters["unparsed_graph_title"]);
		$graph_parameters["unparsed_graph_title"] = str_replace("<snmp_interface_speed>", $data[Speed], $graph_parameters["unparsed_graph_title"]);
		$graph_parameters["unparsed_graph_title"] = str_replace("<snmp_hardware_address>", $data[HardwareAddress], $graph_parameters["unparsed_graph_title"]);
		$graph_parameters["unparsed_graph_title"] = str_replace("<snmp_ip_address>", $data[IPAddress], $graph_parameters["unparsed_graph_title"]);
    }
    
    return $graph_parameters["unparsed_graph_title"];
}

?>
