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

function query_snmp_host($host_id, $snmp_query_id) {
	include_once ("xml_functions.php");
	
	global $paths;
	
	$host = db_fetch_row("select management_ip,snmp_community,snmp_version,snmp_username,snmp_password from host where id=$host_id");
	$snmp_query = db_fetch_row("select xml_path from snmp_query where id=$snmp_query_id");
	
	$xml_file_path = str_replace("<path_cacti>", $paths["cacti"], $snmp_query["xml_path"]);
	
	if ((empty($host["management_ip"])) || (!file_exists($xml_file_path))) {
		return 0;
	}
	
	$data = implode("",file($xml_file_path));
	$snmp_queries = xml2array($data);
	
	/* fetch specified index at specified OID */
	$snmp_index = cacti_snmp_walk($host["management_ip"], $host["snmp_community"], $snmp_queries["oid_index"]);
	
	//print "<pre>";print_r($snmp_index);print "</pre>";
	db_execute("delete from host_snmp_cache where host_id=$host_id");
	
	while (list($field_name, $field_array) = each($snmp_queries["fields"][0])) {
		$field_array = $field_array[0];
		
		if ($field_array["method"] == "get") {
			if ($field_array["source"] == "value") {
				for ($i=0;($i<sizeof($snmp_index));$i++) {
					$oid = $field_array["oid"] .  "." . $snmp_index[$i]["value"];
					
					$value = cacti_snmp_get($host["management_ip"], $host["snmp_community"], $oid, "", "");
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$value'," . $snmp_index[$i]["value"] . ",'$oid')");
				}
			}
		}elseif ($field_array["method"] == "walk") {
			$snmp_data = cacti_snmp_walk($host["management_ip"], $host["snmp_community"], $field_array["oid"]);
			
			if ($field_array["source"] == "value") {
				for ($i=0;($i<sizeof($snmp_data));$i++) {
					$snmp_index = ereg_replace('.*\.([0-9]+)$', "\\1", $snmp_data[$i]["oid"]);
					$oid = $field_array["oid"] . ".$snmp_index";
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','" . $snmp_data[$i]["value"] . "',$snmp_index,'$oid')");
				}
			}elseif (ereg("^OID/REGEXP:", $field_array["source"])) {
				for ($i=0;($i<sizeof($snmp_data));$i++) {
					$value = ereg_replace(ereg_replace("^OID/REGEXP:", "", $field_array["source"]), "\\1", $snmp_data[$i]["oid"]);
					$snmp_index = $snmp_data[$i]["value"];
					$oid = $field_array["oid"] .  "." . $value;
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$value',$snmp_index,'$oid')");
				}
			}
			
			//print "<pre>";print_r($snmp_data);print "</pre>";
		}
	}
}

function cacti_snmp_get($hostname, $community, $oid, $force_type, $force_version) {
	include ('include/config.php');
	include_once ('include/functions.php');
	
	if ($config["php_snmp_support"] == true) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);
		
		$snmp_value = snmpget($hostname, $community, $oid);
		
		/* sometimes a string is presenting in hex; not ASCII (ex. win32), in
		this case, convert the hex to ASCII */
		if ($force_type == "hex") {
			$snmp_value = convert_mac_address(trim(eregi_replace("hex:", "", $snmp_value)));
		}else{
			if (eregi("(.*)(hex:)(.*)", $snmp_value)) {
				/* grab the actual hex string */
				$snmp_value = trim(ereg_replace("(.*)(Hex:)(.*)", "\\3", $snmp_value));
				
				/* strip all formatting and convert the string */
				$snmp_value = hex2bin(ereg_replace("[^A-Fa-f0-9]", "", $snmp_value));
			}
		}
		
		/* remove ALL quotes */
		$snmp_value = str_replace("\"", "", $snmp_value);
	}else{
		$snmp_value = exec(read_config_option("path_snmpget") . " $hostname $community $oid");
		$snmp_value = trim(ereg_replace("(.*=)", "", $snmp_value));
	}
	
	$snmp_value = trim(eregi_replace("(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress):", "", $snmp_value));
	
	return $snmp_value;
}

function cacti_snmp_walk($hostname, $community, $oid) {
	include ('include/config.php');
	include_once ('include/functions.php');
	
	if ($config["php_snmp_support"] == true) {
		$temp_array = snmpwalkoid($hostname, $community, $oid);
		
		$o = 0;
		for (reset($temp_array); $i = key($temp_array); next($temp_array)) {
			$snmp_array[$o]["oid"] = ereg_replace("^\.", "", $i); 
			$snmp_array[$o]["value"] = $temp_array[$i];
			$o++;
		}
	}else{
		$temp_array = exec_into_array(read_config_option("path_snmpwalk") . " $hostname \"$community\" $oid");
		
		for ($i=0; $i < count($temp_array); $i++) {
			$snmp_array[$i]["oid"] = trim(ereg_replace("(.*) =.*", "\\1", $temp_array[$i]));
			$snmp_array[$i]["value"] = trim(ereg_replace(".*= ?", "", $temp_array[$i]));
			
			$snmp_array[$i]["value"] = trim(eregi_replace("(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress):", "", $snmp_array[$i]["value"]));
		}
	}
	
	return $snmp_array;
}

?>
