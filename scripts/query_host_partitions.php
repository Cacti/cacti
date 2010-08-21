<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include(dirname(__FILE__) . "/../lib/snmp.php");

$oids = array(
	"total" 		=> ".1.3.6.1.2.1.25.2.3.1.5",
	"used" 			=> ".1.3.6.1.2.1.25.2.3.1.6",
	"failures" 		=> ".1.3.6.1.2.1.25.2.3.1.7",
	"index" 		=> ".1.3.6.1.2.1.25.2.3.1.1",
	"description" 	=> ".1.3.6.1.2.1.25.2.3.1.3",
	"sau" 			=> ".1.3.6.1.2.1.25.2.3.1.4"
	);

$hostname 	= $_SERVER["argv"][1];
$host_id 	= $_SERVER["argv"][2];
$snmp_auth 	= $_SERVER["argv"][3];
$cmd 		= $_SERVER["argv"][4];

/* support for SNMP V2 and SNMP V3 parameters */
$snmp = explode(":", $snmp_auth);
$snmp_version 	= $snmp[0];
$snmp_port    	= $snmp[1];
$snmp_timeout 	= $snmp[2];
$ping_retries 	= $snmp[3];
$max_oids		= $snmp[4];

$snmp_auth_username   	= "";
$snmp_auth_password   	= "";
$snmp_auth_protocol  	= "";
$snmp_priv_passphrase 	= "";
$snmp_priv_protocol   	= "";
$snmp_context         	= "";
$snmp_community 		= "";

if ($snmp_version == 3) {
	$snmp_auth_username   = $snmp[6];
	$snmp_auth_password   = $snmp[7];
	$snmp_auth_protocol   = $snmp[8];
	$snmp_priv_passphrase = $snmp[9];
	$snmp_priv_protocol   = $snmp[10];
	$snmp_context         = $snmp[11];
}else{
	$snmp_community = $snmp[5];
}

/*
 * process INDEX requests
 */
if ($cmd == "index") {
	$return_arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));

	for ($i=0;($i<sizeof($return_arr));$i++) {
		print $return_arr[$i] . "\n";
	}

/*
 * process NUM_INDEXES requests
 */
}elseif ($cmd == "num_indexes") {
	$return_arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));

	print sizeof($return_arr) . "\n";	

/*
 * process QUERY requests
 */
}elseif ($cmd == "query") {
	$arg = $_SERVER["argv"][5];

	$arr_index = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids["index"], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
	$arr = reindex(cacti_snmp_walk($hostname, $snmp_community, $oids[$arg], $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));

	for ($i=0;($i<sizeof($arr_index));$i++) {
		print $arr_index[$i] . "!" . $arr[$i] . "\n";
	}

/*
 * process GET requests
 */
}elseif ($cmd == "get") {
	$arg = $_SERVER["argv"][5];
	$index = $_SERVER["argv"][6];

	if (($arg == "total") || ($arg == "used")) {
		/* get hrStorageAllocationUnits from the snmp cache since it is faster */
		$sau = db_fetch_cell("select field_value from host_snmp_cache where host_id=$host_id and field_name='hrStorageAllocationUnits' and snmp_index='$index'");

		print (cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase,$snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, SNMP_POLLER)* $sau);
	}else{
		print (cacti_snmp_get($hostname, $snmp_community, $oids[$arg] . ".$index", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol,$snmp_priv_passphrase,$snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, SNMP_POLLER));
	}
}else {
	print "ERROR: Invalid command given\n";
}

function reindex($arr) {
	$return_arr = array();

	for ($i=0;($i<sizeof($arr));$i++) {
		$return_arr[$i] = $arr[$i]["value"];
	}

	return $return_arr;
}

?>
