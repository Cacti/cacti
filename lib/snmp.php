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
		$snmp_value = exec($config["path_snmpget"]["value"] . " $hostname $community $oid");
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
		$temp_array = exec_into_array($config["path_snmpwalk"]["value"] . " $hostname \"$community\" $oid");
		
		for ($i=0; $i < count($temp_array); $i++) {
			$snmp_array[$i]["oid"] = trim(ereg_replace("(.*) =.*", "\\1", $temp_array[$i]));
			$snmp_array[$i]["value"] = trim(ereg_replace("(.*= )", "", $temp_array[$i]));
		}
	}
	
	return $snmp_array;
}

function get_snmp_interfaces($hostname,$community,$hostid) {
    global $cnn_id;
    #include_once("include/database.php");
	include_once ('include/functions.php');
	include ('include/config.php');
	
	if ($config["log_snmp"]["value"] == "on") {
		LogData("SNMP: Getting SNMP data for host: $hostname, community: $community, hostid: $hostid");
	}
	
	/* delete old stuff first */
	if ($hostid != 0) {
		$sql_id = mysql_query("delete from snmp_hosts_interfaces where hostid=$hostid", $cnn_id);
	}
	
	/* put interface id's into an array: interfaces.ifTable.ifEntry.ifIndex */
	$ifIndex = cacti_snmp_walk($hostname, $community, ".1.3.6.1.2.1.2.2.1.1");
	
	/* put interface ip's into an array: ip.ipAddrTable.ipAddrEntry.ipAdEntIfIndex */
	$ipAdEntIfIndex = cacti_snmp_walk($hostname, $community, ".1.3.6.1.2.1.4.20.1.2");
	
	/* we will need to parse the ip address out of the oid like so:
	ip.ipAddrTable.ipAddrEntry.ipAdEntIfIndex.192.168.1.2 */
	for ($i=0; $i < count($ipAdEntIfIndex); $i++) {
		$temp_ip_address = ereg_replace("((.[0-9]{1,3}){4})$", "", $ipAdEntIfIndex[$i]["oid"]);
		$temp_ip_address = str_replace($temp_ip_address,"",$ipAdEntIfIndex[$i]["oid"]);
		$temp_ip_address = ereg_replace("^\.", "", $temp_ip_address);
		
		$temp_interface_index = $ipAdEntIfIndex[$i]["value"];
		
		$array_ifip[$temp_interface_index] = $temp_ip_address;
	}
	
	for ($i=0; $i < count($ifIndex); $i++) {
		$interface_number = $ifIndex[$i]["value"];
		$interface_ip_address = $array_ifip[$interface_number];
		$interface_description = cacti_snmp_get($hostname, $community, ".1.3.6.1.2.1.2.2.1.2.$interface_number", "", "");
		$interface_type = cacti_snmp_get($hostname, $community, ".1.3.6.1.2.1.2.2.1.3.$interface_number", "", "");
		$interface_speed = cacti_snmp_get($hostname, $community, ".1.3.6.1.2.1.2.2.1.5.$interface_number", "", "");
		$interface_hardware_address = cacti_snmp_get($hostname, $community, ".1.3.6.1.2.1.2.2.1.6.$interface_number", "hex", "");
		
		$sql_id = mysql_query("replace into snmp_hosts_interfaces (id,hostid,description,
			type,speed,interfacenumber,hardwareaddress,ipaddress) values (0,\"$hostid\",
			\"$interface_description\",\"$interface_type\",\"$interface_speed\",
			\"$interface_number\",\"$interface_hardware_address\",\"$interface_ip_address\")",$cnn_id);
	}
}

function internal_snmp_query($data_source_id, $source_id, $data_input_type) {
    global $cnn_id;
    #include_once("include/database.php");
	
	$sql_id_field = mysql_query("select d.fieldid, d.dsid, d.value, 
		f.srcid, f.dataname
		from src_data d
		left join src_fields f
		on d.fieldid=f.id
		where d.dsid=$data_source_id
		and f.srcid=$source_id",$cnn_id);
	$rows_field = mysql_num_rows($sql_id_field); $i_field = 0;
	
	while ($i_field < $rows_field) {
		${mysql_result($sql_id_field, $i_field, "dataname")} = mysql_result($sql_id_field, $i_field, "value");
		
		$i_field++;
	}
	
	/* depending on what kind of data input source this is; do something */
	switch ($data_input_type) {
		case 'snmp_net':
			return get_snmp_network_data($inout,$ip,$community,$ifdesc,$ifnum,$ifmac,$ifip);
			break;
		case 'snmp':
			return get_snmp_data($ip,$community,$oid);
			break;
	}
}

function get_snmp_network_data($snmp_inout, $snmp_ip, $snmp_community, $if_description,
	$if_number, $if_macaddress, $if_ipaddress) {
	include ("include/config.php");
    global $cnn_id;
    #include_once("include/database.php");
	
	/* default snmp community */
	if ($snmp_community == "") { $snmp_community = "public"; }
	
	/* first lets check to make sure mysql knows about this host, if it 
	does not, make sure it does! */
	$sql_id = mysql_query("select * from snmp_hosts where hostname=\"$snmp_ip\"", $cnn_id);
	
	if (mysql_num_rows($sql_id) == 0) {
		/* get and save new snmp data */
		
		$sql_id = mysql_query("replace into snmp_hosts (id,hostname,community) values 
			(0,\"$snmp_ip\",\"$snmp_community\")",$cnn_id);
		$sql_id = mysql_query("select LAST_INSERT_ID()",$cnn_id);
		$hostid = mysql_result($sql_id,"",0);
		
		get_snmp_interfaces($snmp_ip,$snmp_community,$hostid);
	}
	
	$first = " WHERE";
	if ($if_number != "") {
		$sql_where = $sql_where . "$first i.interfacenumber=\"$if_number\"";
		$first = " AND";
	}
	
	if ($if_description != "") {
		$sql_where = $sql_where . "$first i.description=\"$if_description\"";
		$first = " AND";
	}
	
	if ($if_macaddress != "") {
		$sql_where = $sql_where . "$first i.hardwareaddress=\"$if_macaddress\"";
		$first = " AND";
	}
	
	if ($if_ipaddress != "") {
		$sql_where = $sql_where . "$first i.ipaddress=\"$if_ipaddress\"";
		$first = " AND";
	}
	
	if ($snmp_ip != "") {
		$sql_where = $sql_where . "$first h.hostname=\"$snmp_ip\"";
		$first = " AND";
	}
	
	/* hcin and hcout - 64bit interface counter for high speed interfaces */
	switch(strtolower($snmp_inout)) {
		case 'in':
			$snmp_octet_name = "2.2.1.10";
			$snmp_ver = "-v1";
			break;
		case 'out':
			$snmp_octet_name = "2.2.1.16";
			$snmp_ver = "-v1";
			break;
		case 'hcin':
			$snmp_octet_name = "31.1.1.1.ifHCInOctets";
			$snmp_ver = "-v2c";
			break;
		case 'hcout':
			$snmp_octet_name = "31.1.1.1.ifHCOutOctets";
			$snmp_ver = "-v2c";
			break;
	}
	
	$sql_id = mysql_query("select i.* from snmp_hosts_interfaces i left join snmp_hosts h 
		on h.id=i.hostid $sql_where", $cnn_id);
	
	if (mysql_num_rows($sql_id) != 0) {
		$snmp_interface_number = mysql_result($sql_id, 0, "interfacenumber");
		
		/* it all comes down to this ... if snmp support has been compiled into php then
		used php's builtin functions, otherwise call snmpget */
		$snmp_output_octets = cacti_snmp_get($snmp_ip, $snmp_community, "$snmp_octet_name.$snmp_interface_number", "", $snmp_ver);
		
		return $snmp_output_octets;
	}
}

function get_snmp_data($snmp_ip, $snmp_community, $snmp_oid) {
	include ("include/config.php");
	
	/* default snmp community */
	if ($snmp_community == "") { $snmp_community = "public"; }
	
	/* it all comes down to this ... if snmp support has been compiled into php then
	used php's builtin functions, otherwise call snmpget */
	$snmp_output_octets = cacti_snmp_get($snmp_ip, $snmp_community, $snmp_oid, "", "");
	
	return $snmp_output_octets;
}

?>
