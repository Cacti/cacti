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
		foreach ($fields as $field) {
		    $str = ereg_replace ("<$field[DataName]>","$field[Value]",$str);
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
    /* if this array appears to be empty, try the alternate method */
    if (sizeof($data[$value]) == 0) {
	foreach ($data as $row) {
	    print "<option value='$row[$value]'";
	    
		if ($prev == $row[$value]) {
			print " selected";
	    }
		
	    print ">$row[$name]</option>\n";
	}
    }else{
	for ($i=0; ($i < sizeof($data[$value])); $i++) {
	    print '<option value="' . $data[$value][$i] . '"';
	    
		if ($prev == $data[$value][$i]) {
			print " selected";
	    }
		
	    print ">" . $data[$name][$i] . "</option>\n";
	}
    }
}

function CreateMultipleList($sql,$name,$value,$prevsql,$prevsqlvalue) {
    /* make sure you order by eqivilant columns in your sql strings!
     for instance: 'order by id', 'order by cfid'
     NOT: 'order by id', 'order by name'
     these values must line up! */
    $data = db_fetch_assoc("$sql order by $value");
    $data_prev = db_fetch_assoc("$prevsql order by $prevsqlvalue");
    $rows = sizeof($data);
    $rows_prev = sizeof($data_prev);
    $i = 0;
    $i_prev = 0;
    
    while ($i < $rows) {
		print "<option value='".$data[$i][$value]."'";
		
		if ($i_prev < $rows_prev) {
		    if ($data_prev[$i_prev][$prevsqlvalue] == $data[$i][$value]) {
				print " selected ";
				$i_prev++;
		    }
		}
		
		print ">".$data[$i][$name];
		$i++;
		print "</option>\n";
    }
}

function GetNextItem($tblname,$field,$startid,$lmt_field,$lmt_val) {
    $data1 = db_fetch_row("select max($field) mymax from $tblname where $lmt_field=$lmt_val");
    $end_seq = $data1[mymax];
    $data2 = db_fetch_row("select $field from $tblname where id=$startid");
    $start_seq = $data2[$field];
    
    $i = $start_seq;
    if ($end_seq != $start_seq) {
		while ($i < $end_seq) {
		    $data3 = db_fetch_row("select $field from $tblname where $field=$i+1 and $lmt_field=$lmt_val");
		    if (sizeof($data3) > 0) { return $data3[$field]; }
		    $i++;
		}
    }
	
    return $start_seq;
}

function GetLastItem($dbid,$tblname,$field,$startid,$lmt_field,$lmt_val) {
    $data1 = db_fetch_row("select min($field) myminfrom $tblname where $lmt_field=$lmt_val");
    $end_seq = $data1[mymin];
    $data2 = db_fetch_row("select $field from $tblname where id=$startid");
    $start_seq = $data2[$field];
    
    $i = $start_seq;
    if ($end_seq != $start_seq) {
		while ($i > $end_seq) {
		    $data3 = db_fetch_row("select $field from $tblname where $field=$i-1 and $lmt_field=$lmt_val");
		    if (sizeof($data3) > 0 && $data3[$field] != 0) {
				return $data3[$field];
		    }
		    $i--;
		}
    }
	
    return $start_seq;
}

function GetSequence($id, $field, $table_name, $gid, $gid_value) {
    if ($id=="0") {
		$data = db_fetch_row("select max($field)+1 as seq from $table_name where $gid=$gid_value");
		
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
		$array_settings[Height] = 100;
		$array_settings[Width] = 300;
		$array_settings[TimeSpan] = 60000;
		$array_settings[RRA] = 1;
		$array_settings[ColumnNumber] = 2;
		$array_settings[PageRefresh] = 300;
		$array_settings[ListViewType] = 1;
		$array_settings[ViewType] = 1;
    }else{
		$array_settings[Height] = $settings[Height];
		$array_settings[Width] = $settings[Width];
		$array_settings[TimeSpan] = $settings[Timespan];
		$array_settings[RRA] = $settings[RRAID];
		$array_settings[ColumnNumber] = $settings[ColumnNumber];
		$array_settings[PageRefresh] = $settings[PageRefresh];
		$array_settings[ListViewType] = $settings[ListViewType];
		$array_settings[ViewType] = $settings[ViewType];
		$array_settings[TreeID] = $settings[TreeID];
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
