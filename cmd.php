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
<?	include ("include/database.php");
	include ('include/config.php');
	include_once ("include/rrd_functions.php");
	include_once ("include/functions.php");

$sql_id = mysql_query("select d.id, d.name, d.srcid, 
	s.formatstrin, s.formatstrout, s.id as sid, s.type
	from rrd_ds d 
	left join src s 
	on d.srcid=s.id 
	where d.active=\"on\"
	and d.subdsid=0",$cnn_id);
$rows = mysql_num_rows($sql_id); $i = 0;

while ($i < $rows) {
	/* make the input string */
	$sql_id_field = mysql_query("select d.fieldid, d.dsid, d.value, 
		f.srcid, f.dataname
		from src_data d
		left join src_fields f
		on d.fieldid=f.id
		where d.dsid=" . mysql_result($sql_id, $i, "id") . "
		and f.srcid=" . mysql_result($sql_id, $i, "srcid"),$cnn_id);
	$rows_field = mysql_num_rows($sql_id_field); $i_field = 0;
	
	$str = mysql_result($sql_id, $i, "formatstrin");
	$str_out = mysql_result($sql_id, $i, "formatstrout");
	
	/* find the delimeter in the output string */
	$delimeter = strpos($str_out,">");
	$delimeter = substr($str_out,$delimeter+1,1);
	
	/* there are two ways to gather data within cacti. we either spawn a new
	process and get data from that or call an internal function and get data
	there. the second option is new (0.6.5) which allows things such as internal
	snmp support without the need to spawn any processes. */
	
	if (mysql_result($sql_id, $i, "type") == "") {
		/* we ARE going to spawn a process */
		while ($i_field < $rows_field) {
			$str = ereg_replace("<" . mysql_result($sql_id_field, $i_field, "dataname") . ">", mysql_result($sql_id_field, $i_field, "value"),$str);
			
			$i_field++;
		}
		
		$str = ereg_replace("<path_cacti>", $path_cacti,$str);
		$str = ereg_replace("<path_snmpget>", read_config_option("path_snmpget"),$str);
		$str = ereg_replace("<path_php_binary>", read_config_option("path_php_binary"),$str);
		
		/* take output string and parse it on the delimiter */
		$out_data_array = ParseDelimitedLine(`$str`,$delimeter);
		//print "\nDEBUG: CMD: " . `$str` . "\n";
	}else{
		/* we ARE NOT going to spawn a process */
		switch (mysql_result($sql_id, $i, "type")) {
			case 'snmp':
				include_once ("include/snmp_functions.php");
				
				$out_data_array = ParseDelimitedLine(internal_snmp_query(mysql_result($sql_id, $i, "id"), mysql_result($sql_id, $i, "srcid"), "snmp"), $delimeter);
				break;
			case 'snmp_net':
				include_once ("include/snmp_functions.php");
				
				$out_data_array = ParseDelimitedLine(internal_snmp_query(mysql_result($sql_id, $i, "id"), mysql_result($sql_id, $i, "srcid"), "snmp_net"), $delimeter);
				break;
		}
	}
	
	/* find out if this DS has multiple outputs */
	$sql_id_multi = mysql_query("select id from rrd_ds where subdsid=" . mysql_result($sql_id, $i, "id"), $cnn_id);
	
	if (mysql_num_rows($sql_id_multi) == 0) {
		$multi_data_source = false;
	}else{
		$multi_data_source = true;
	}
	
	/* make the output string */
	$out_array = ParseDelimitedLine($str_out, $delimeter);
	
	$o = 0;
	while ($o < count($out_array)) {
		if (mysql_result($sql_id, $i, "srcid") == 0) {
			LogData("ERROR: Data Source: '" . mysql_result($sql_id, $i, "name") . "' does not " . 
				"have a data input source assigned to it. No data will be gathered; if cacti " . 
				"does not gather data for this data source please deactivate it.");
		}else{
			/* remove all < and > characters from the output array */
			$out_array[$o] = ereg_replace("[<]|[>]", "" ,$out_array[$o]);
			
			/* UPDATE: in previous versions, we would parse the output string to see what
			output vars are in use. This is sort of hacked, since it has the potential to 
			break. Instead we are going to get the same data from the database, plus this
			saves us having to call ParseDelimitedLine() one extra time */
			if ($multi_data_source == true) {
				$sql_id_fields = mysql_query("select 
					f.id,
					d.id as dsid
					from src_fields f left join rrd_ds d on f.id=d.subfieldid
					where f.dataname=\"$out_array[$o]\"
					and f.srcid=" . mysql_result($sql_id, $i, "srcid") . "
					and d.subdsid=" . mysql_result($sql_id, $i, "id") . "
					and f.inputoutput=\"out\"", $cnn_id);
			}else{
				$sql_id_fields = mysql_query("select 
					id 
					from src_fields 
					where dataname=\"$out_array[$o]\"
					and srcid=" . mysql_result($sql_id, $i, "srcid") . "
					and inputoutput=\"out\"", $cnn_id);
			}
			
			/* get the current data source id depending on whether this is a multi-ds
			or not */
			if ($multi_data_source == true) {
				$current_data_source_id = mysql_result($sql_id_fields, 0, "dsid");
			}else{
				$current_data_source_id = mysql_result($sql_id, $i, "id");
			}
			
			//print "DEBUG: DS: $current_data_source_id, FIELD: $out_array[$o]=$out_data_array[$o]\n";
			
			$sql_id_data = mysql_query("select id from src_data 
				where fieldid=" . mysql_result($sql_id_fields, 0, "id") . "
				and dsid=$current_data_source_id",$cnn_id);
			
			if (mysql_num_rows($sql_id_data) == 0) {
				$new_data_id = 0;
			}else{
				$new_data_id = mysql_result($sql_id_data, 0, "id");
			}
			
			$sql_id_field_save = mysql_query("replace into src_data (id,fieldid,dsid,value) 
				values($new_data_id," . mysql_result($sql_id_fields, 0, "id") . ",
				$current_data_source_id,\"$out_data_array[$o]\")",$cnn_id);
		}
		
		$o++;
	}
	
	/* call cacti's functions; no not print source */
	rrdtool_function_create(mysql_result($sql_id, $i, "id"), false);
	
	/* only update data if we are gathering data for this data source */
	if (mysql_result($sql_id, $i, "srcid") != 0) {
		rrdtool_function_update(mysql_result($sql_id, $i, "id"), $multi_data_source, false);
	}
	
	$i++;
}

/* dump static images/html file if user wants it */
if (read_config_option("path_html_export") != "") {
	if (read_config_option("path_html_export_skip") == "1") {
		include("export.php");
	}else{
		if (read_config_option("path_html_export_skip") == read_config_option("path_html_export_ctr")) {
			mysql_query("update settings set value=1 where name=\"path_html_export_ctr\"", $cnn_id);
			include("export.php");
		}else{
			if (read_config_option("path_html_export_ctr") == "") {
				mysql_query("update settings set value=1 where name=\"path_html_export_ctr\"", $cnn_id);
			}else{
				mysql_query("update settings set value=" . (read_config_option("path_html_export_ctr") + 1) . " where name=\"path_html_export_ctr\"", $cnn_id);
			}
		}
	}
}

?>
