<?php

$no_http_headers = true;
include(dirname(__FILE__) . "/../include/config.php");
include(dirname(__FILE__) . "/../lib/snmp.php");

$oids = array(
	"total" => ".1.3.6.1.2.1.25.2.3.1.5",
	"used" => ".1.3.6.1.2.1.25.2.3.1.6",
	"failures" => ".1.3.6.1.2.1.25.2.3.1.7",
	"index" => ".1.3.6.1.2.1.25.2.3.1.1",
	"description" => ".1.3.6.1.2.1.25.2.3.1.3",
	"sau" => ".1.3.6.1.2.1.25.2.3.1.4"
	);

$hostname = $_SERVER["argv"][1];
$snmp_community = $_SERVER["argv"][2];
$snmp_version = $_SERVER["argv"][3];
$cmd = $_SERVER["argv"][4];

if ($cmd == "index") {
	$return_arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, "", "", 161, 1000));
	
	for ($i=0;($i<sizeof($return_arr));$i++) {
		print $return_arr[$i] . "\n";
	}
}elseif ($cmd == "query") {
	$arg = $_SERVER["argv"][5];
	
	$arr_index = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, "", "", 161, 1000));
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg], $snmp_version, "", "", 161, 1000));
	
	for ($i=0;($i<sizeof($arr_index));$i++) {
		print $arr_index[$i] . "!" . $arr[$i] . "\n";
	}
}elseif ($cmd == "get") {
	$arg = $_SERVER["argv"][5];
	$index = $_SERVER["argv"][6];
	
	if (($arg == "total") || ($arg == "used")) {
		/* get hrStorageAllocationUnits from the snmp cache since it is faster */
		$host_id = db_fetch_cell("select id from host where hostname='$hostname' and snmp_community='$snmp_community'");
		$sau = db_fetch_cell("select field_value from host_snmp_cache where host_id=$host_id and field_name='hrStorageAllocationUnits' and snmp_index='$index'");
		
		print (cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version, "", "", 161, 1000) * $sau);
	}else{
		print (cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version, "", "", 161, 1000));
	}
}

function reindex($arr) {
	$return_arr = array();
	
	for ($i=0;($i<sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]["value"];
	}
	
	return $return_arr;
}

?>
