#!/usr/bin/env php
<?php

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	include_once(dirname(__FILE__) . '/../lib/snmp.php');
	include_once(dirname(__FILE__) . '/../lib/ping.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_fping', $_SERVER['argv']);
}

include_once(dirname(__FILE__) . '/../lib/snmp.php');
include_once(dirname(__FILE__) . '/../lib/ping.php');

function ss_fping($hostname, $ping_sweeps=6, $ping_type='ICMP', $port=80) {
	/* record start time */
	list($micro,$seconds) = explode(' ', microtime());
	$ss_fping_start = $seconds + $micro;

	$ping = new Net_Ping;

	$time = array();
	$total_time = 0;
	$failed_results = 0;

	$ping->host['hostname'] = gethostbyname($hostname);
	$ping->retries = 1;
	$ping->port = $port;
	$max = 0.0;
	$min = 9999.99;
	$dev = 0.0;

	$script_timeout = read_config_option('script_timeout');
	$ping_timeout = read_config_option('ping_timeout');

	switch ($ping_type) {
	case 'ICMP':
		$method = PING_ICMP;
		break;
	case 'TCP':
		$method = PING_TCP;
		break;
	case 'UDP':
		$method = PING_UDP;
		break;
	}

	$i = 0;
	while ($i < $ping_sweeps) {
		$result = $ping->ping(AVAIL_PING, $method, read_config_option('ping_timeout'), 1);

		if (!$result) {
			$failed_results++;
		} else {
			$time[$i] = $ping->ping_status;
			$total_time += $ping->ping_status;
			if ($ping->ping_status < $min) $min = $ping->ping_status;
			if ($ping->ping_status > $max) $max = $ping->ping_status;
		}

		$i++;

		/* get current time */
		list($micro,$seconds) = explode(' ', microtime());
		$ss_fping_current = $seconds + $micro;

		/* if called from script server, end one second before a timeout occurs */
		if (isset($called_by_script_server) && ($ss_fping_current - $ss_fping_start + ($ping_timeout/1000) + 1) > $script_timeout) {
			$ping_sweeps = $i;
			break;
		}
	}

	if ($failed_results == $ping_sweeps) {
		return 'min:U avg:U max:U dev:U loss:100.00';
	} else {
		$loss = ($failed_results/$ping_sweeps) * 100;
		$avg = $total_time/($ping_sweeps-$failed_results);

		/* calculate standard deviation */
		$predev = 0;
		foreach($time as $sample) {
			$predev += pow(($sample-$avg),2);
		}
		$dev = sqrt($predev / cacti_count($time));

		return sprintf('min:%0.4f avg:%0.4f max:%0.4f dev:%0.4f loss:%0.4f', $min, $avg, $max, $dev, $loss);
	}
}

