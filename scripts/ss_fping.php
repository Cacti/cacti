#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');
	include_once(__DIR__ . '/../lib/ping.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_fping', $_SERVER['argv']);
} else {
	include_once(__DIR__ . '/../lib/snmp.php');
	include_once(__DIR__ . '/../lib/ping.php');
}

function ss_fping(string $hostname = '', int $ping_sweeps = 6, string $ping_type = 'ICMP', int $port = 80) : string {
	global $called_by_script_server;

	// record start time
	$ss_fping_start = microtime(true);

	$ping = new Net_Ping;

	$time           = [];
	$total_time     = 0;
	$failed_results = 0;

	$ping->host['hostname'] = gethostbyname($hostname);
	$ping->retries          = 1;
	$ping->port             = $port;

	$max = 0.0;
	$min = 9999.99;
	$dev = 0.0;

	$script_timeout = read_config_option('script_timeout');

	$ping_timeout = db_fetch_cell_prepared('SELECT ping_timeout
		FROM host
		WHERE hostname = ?',
		[$hostname]);

	if (empty($ping_timeout)) {
		$ping_timeout = read_config_option('ping_timeout');
	}

	switch (cacti_strtoupper($ping_type)) {
		case 'ICMP':
			$method = PING_ICMP;

			break;
		case 'TCP':
			$method = PING_TCP;

			break;
		case 'UDP':
			$method = PING_UDP;

			break;
		default:
			$method = PING_ICMP;

			break;
	}

	$i = 0;

	while ($i < $ping_sweeps) {
		$start  = microtime(true);
		$result = $ping->ping(AVAIL_PING, $method, $ping_timeout, 1);
		$end    = microtime(true);

		if (!$result) {
			// Error response
			$failed_results++;

			$time[$i]    = $end - $start;
			$total_time += $end - $start;
		} elseif (is_numeric($ping->ping_status)) {
			$time[$i]    = $ping->ping_status;
			$total_time += $ping->ping_status;

			if ($ping->ping_status < $min) {
				$min = $ping->ping_status;
			}

			if ($ping->ping_status > $max) {
				$max = $ping->ping_status;
			}
		}

		$i++;

		// get current time
		$ss_fping_current = microtime(true);

		// if called from script server, end one second before a timeout occurs
		if (isset($called_by_script_server) && ($ss_fping_current - $ss_fping_start + ($ping_timeout / 1000) + 1) > $script_timeout) {
			$ping_sweeps = $i;

			break;
		}

		if ($failed_results > $ping_sweeps / 4) {
			$ping_sweeps = $failed_results;

			break;
		}
	}

	if ($failed_results == $ping_sweeps) {
		return 'min:U avg:U max:U dev:U loss:100.00';
	} else {
		$loss = ($failed_results / $ping_sweeps) * 100;
		$avg  = $total_time / ($ping_sweeps - $failed_results);

		// calculate standard deviation
		$predev = 0;

		foreach ($time as $sample) {
			$predev += ($sample - $avg) ** 2;
		}

		if (cacti_sizeof($time)) {
			$dev = sqrt($predev / cacti_count($time));
		} else {
			$dev = 0;
		}

		return sprintf('min:%0.4f avg:%0.4f max:%0.4f dev:%0.4f loss:%0.4f', $min, $avg, $max, $dev, $loss);
	}
}
