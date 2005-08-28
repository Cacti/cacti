<?php
#!/usr/bin/php -q

//STANDARD SCRIPT SERVER HEADER!!!
$no_http_headers = true;

/* display No errors */
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../include/config.php");
include_once(dirname(__FILE__) . "/../lib/snmp.php");
include_once(dirname(__FILE__) . "/../lib/ping.php");

if (!isset($called_by_script_server)) {
	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_fping", $_SERVER["argv"]);
}
//End header.

function ss_fping($hostname, $ping_sweeps=6, $ping_type="ICMP", $port=80) {
	$ping = new Net_Ping;

	$time = array();
	$total_time = 0;
	$failed_results = 0;

	$ping->host["hostname"] = $hostname;
	$ping->retries = 1;
	$ping->port = $port;
	$max = 0.0;
	$min = 9999.99;
	$dev = 0.0;

	switch ($ping_type) {
	case "ICMP":
		$method = PING_ICMP;
		break;
	case "TCP":
		$method = PING_TCP;
		break;
	case "UDP":
		$method = PING_UDP;
		break;
	}

	$i = 0;
	while ($i < $ping_sweeps) {
		$result = $ping->ping(AVAIL_PING,
					$method,
					read_config_option("ping_timeout"),
					1);

		if (!$result) {
			$failed_results++;
		}else{
			$time[$i] = $ping->ping_status;
			$total_time += $ping->ping_status;
			if ($ping->ping_status < $min) $min = $ping->ping_status;
			if ($ping->ping_status > $max) $max = $ping->ping_status;
		}

		$i++;
	}

	if ($failed_results == $ping_sweeps) {
		return "loss:100.00";
	}else{
		$loss = ($failed_results/$ping_sweeps) * 100;
	    $avg = $total_time/($ping_sweeps-$failed_results);

		/* calculate standard deviation */
		$predev = 0;
		foreach($time as $sample) {
			$predev += pow(($sample-$avg),2);
		}
		$dev = sqrt($predev / count($time));

		return sprintf("min:%0.4f avg:%0.4f max:%0.4f dev:%0.4f loss:%0.4f", $min, $avg, $max, $dev, $loss);
	}
}
?>