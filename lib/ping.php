#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

class Net_Ping
{
	var $socket;
	var $host;
	var $ping_status;
	var $ping_response;
	var $snmp_status;
	var $snmp_response;
	var $request;
	var $request_len;
	var $reply;
	var $timeout;
	var $precision;
	var $time;
	var $timer_start_time;

	function Net_Ping()
	{
	}

	function close_socket()
	{
		socket_close($this->socket);
	}

	function start_time()
	{
		$this->timer_start_time = microtime();
	}

	function get_time($acc=2)
	{
		// format start time
		$start_time = explode (" ", $this->timer_start_time);
		$start_time = $start_time[1] + $start_time[0];
		// get and format end time
		$end_time = explode (" ", microtime());
		$end_time = $end_time[1] + $end_time[0];
		return number_format ($end_time - $start_time, $acc);
	}

	function build_udp_packet() {
		$data  = "cacti-monitoring-system"; // the actual test data

		// now lets build the actual icmp packet
		$this->request = chr(0) . chr(1) . chr(0) . $data . chr(0);
		$this->request_len = strlen($this->request);
	}

	function build_icmp_packet() {
		$data = "cacti-monitoring-system"; // the actual test data
		$type = "\x08"; // 8 echo message; 0 echo reply message
		$code = "\x00"; // always 0 for this program
		$chksm = "\xCE\x96"; // generate checksum for icmp request
		$id = "\x40\x00"; // we will have to work with this later
		$sqn = "\x00\x00"; // we will have to work with this later

		// now lets build the actual icmp packet
		$this->request = $type.$code.$chksm.$id.$sqn.$data;
		$this->request_len = strlen($this->request);
	}

	function ping_icmp()	{
		/* initialize variables */
		$this->snmp_status = "down";
		$this->snmp_respones = "default";

		/* initialize the socket */
		$this->socket = socket_create(AF_INET, SOCK_RAW, 1);
		socket_set_block($this->socket);

		/* set the timeout */
		socket_set_option($this->socket,
			SOL_SOCKET,  // socket level
			SO_RCVTIMEO, // timeout option
			array(
				"sec"=>$this->timeout, // Timeout in seconds
				"usec"=>0  // I assume timeout in microseconds
			));

		/* ping me */
		if ($this->host["hostname"]) {
			if (@socket_connect($this->socket, $this->host["hostname"], NULL)) {
				// do nothing
			} else {
				$this->errstr = "Cannot connect to host";
				return false;
			}

			/* build the packet */
			$this->build_icmp_packet();

			/* get start time */
			$this->start_time();

			socket_write($this->socket, $this->request, $this->request_len);
			$code = @socket_recv($this->socket, &$this->reply, 256, 0);

			/* get the end time */
			$this->time = $this->get_time($this->precision);

			if ($code) {
				$this->ping_status = $this->time;
				$this->ping_response = "Host is alive";
				return true;
			} else {
				$this->status = "down";
				$this->response = "ICMP Timed out";
				return FALSE;
			}
			$this->close_socket();
		} else {
			$this->ping_status = "down";
			$this->ping_response = "Destination address not specified";
			return false;
		}
	}

	function ping_snmp() {
		/* initialize variables */
		$this->snmp_status = "down";
		$this->snmp_respones = "default";
		$output = "";

		/* get start time */
		$this->start_time();

		/* poll ifDescription for status */
		$output = cacti_snmp_get($this->host["hostname"],
			$this->host["snmp_community"],
			".1.3.6.1.2.1.1.5.0" ,
			$this->host["snmp_version"],
			$this->host["snmp_username"],
			$this->host["snmp_password"],
			$this->host["snmp_port"],
			$this->host["snmp_timeout"]);

		/* determine total time +- ~10% */
		$this->time = $this->get_time($this->precision);

		/* check result for uptime */
		if (!empty($output)) {
			/* calculte total time */
			$this->time*1000;
			$this->snmp_status = $this->time;
			$this->snmp_response = $output;
		}else {
			$this->snmp_status = "down";
			$this->snmp_response = "SNMP did not respond";
		}
	} /* ping_snmp */

	function ping_udp() {
		/* initialize variables */
		$this->ping_status = "down";
		$this->ping_respones = "default";

		/* initilize the socket */
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_block($this->socket);

		/* set the socket timeout */
		socket_set_option($this->socket,
			SOL_SOCKET,  // socket level
			SO_RCVTIMEO, // timeout option
			array(
				"sec"=>$this->timeout, // Timeout in seconds
				"usec"=>0  // I assume timeout in microseconds
			));

		/* Host must be nonblank */
		if ($this->host["hostname"]) {
			if (@socket_connect($this->socket, $this->host["hostname"], 2336)) {
				// do nothing
			}else {
				$this->ping_status = "down";
				$this->ping_result = "Cannot connect to host";
				return FALSE;
			}

			/* format packet */
			$this->build_udp_packet();

			/* set start time */
			$this->start_time();

			/* send packet to destination */
			socket_connect($this->socket, $this->host["hostname"], 33439);
			socket_write($this->socket, $this->request, $this->request_len);

			/* get packet response */
			$code = @socket_recv($this->socket, &$this->reply, 256, 0);

			/* caculate total time */
			$this->time = $this->get_time($this->precision);

			if (($code) || (empty($code))) {
				if (($this->time*1000) <= $this->timeout) {
					$this->ping_status = $this->time;
					$this->ping_response = "Host responded within timeout period.";
				} else {
					$this->ping_response = "Destination address not specified";
					$thos->ping_status = "down";
				}
			} else {
				$this->ping_status = "down";
				$this->ping_response = "UDP Ping Timed out";
			}
			$this->close_socket();
		} else {
			$this->ping_response = "Destination address not specified";
			$this->ping_status = "down";
		}
	} /* end ping_udp */

	function ping($avail_method = AVAIL_SNMP_AND_PING, $ping_type = ICMP_PING, $timeout=500,$precision=3)
	{
		/* initialize variables */
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status = "down";
		$this->ping_response = "Ping not performed due to setting.";
		$this->snmp_status = "down";
		$this->snmp_response = "SNMP not performed due to setting.";

		/* do parameter checking before call */
		if ((int)$timeout <= 0)
			$this->timeout=500;
		else
			$this->timeout=$timeout;

		if ((int)$precision <= 0)
			$this->precision=3;
		else
			$this->precision=$precision;

		if ((int)$avail_method <= 0) $avail_method=PING_ICMP;
		if ((int)$ping_type <= 0) $ping_type=PING_UDP;

		/* set but don't check yet */
		if ($avail_method <= AVAIL_SNMP) {
			$ping_snmp = true;
		} else {
			$ping_snmp = false;
		}

		if (($avail_method == AVAIL_SNMP_AND_PING) || ($avail_method == AVAIL_PING)) {
			$ping_ping = true;
		} else {
			$ping_ping = false;
		}

		/* snmp pinging has been selected at a minimum */
		$ping_result = false;
		$snmp_result = false;

echo "Ping_snmp>>".(int)$ping_snmp."<<\n";
echo "Ping_ping>>".(int)$ping_ping."<<\n";


		/* snmp pinging has been selected */
		if ($ping_snmp) {
			/* ping ICMP/UDP first */
			if ($ping_ping) {
				if ($ping_type == 1) {
					$ping_result = $this->ping_icmp();
				} else {
					$ping_result = $this->ping_udp();
				}
   		}

			/* SNMP always goes last */
			if (is_numeric($this->ping_status)) {
				$snmp_result = $this->ping_snmp();
			}
		} else {
			/* ping ICMP/UDP only */
			if ($ping_ping) {
				if ($ping_type == 1) {
					$ping_result = $this->ping_icmp();
				} else {
					$ping_result = $this->ping_udp();
				}
   		}
		}

		switch ($this->avail_method) {
			case AVAIL_SNMP_AND_PING:
				if (!$snmp_result)
					return false;
				if (!$ping_result)
					return false;
				if (!$ping_result)
					return true;
			case AVAIL_SNMP:
				if ($snmp_result)
					return true;
				else
					return false;
			case AVAIL_PING:
				if ($ping_result)
					return true;
				else
					return false;
			default:
				return false;
		}
	} /* end_ping */
}

/* test driver - must be in a separate file */
print $start . "\n";
ini_set("max_execution_time", "0");
$no_http_headers = true;

include_once("c:/wwwroot/cacti/include/config.php");
include_once("c:/wwwroot/cacti/include/config_constants.php");
include_once("c:/wwwroot/cacti/include/config_arrays.php");
include_once("c:/wwwroot/cacti/include/config_form.php");
include_once("c:/wwwroot/cacti/lib/snmp.php");
include_once("c:/wwwroot/cacti/lib/functions.php");
include_once("c:/wwwroot/cacti/lib/database.php");
include_once("c:/wwwroot/cacti/lib/variables.php");

$ping = new Net_Ping;

$i = 0;
$j = 0;

/* initialize calling parms */
$ping->host["hostname"] = "192.168.0.2";
$ping->host["snmp_community"] = "public";
$ping->host["snmp_version"] = 1;
$ping->host["snmp_username"] = "";
$ping->host["snmp_password"] = "";
$ping->host["snmp_port"] = "161";
$ping->host["snmp_timeout"] = "500";

$ping->avail_method = AVAIL_SNMP_AND_PING;
$ping->ping_method = PING_ICMP;

while (1) {
	$i++;
	if ($i == 100) {
		$j++;
		$k = $j * 100;
		echo "Number of Hosts Polled->" . $k . "\n";
		$i = 0;
	}


	echo "Pinging>>\n";
	$ping->ping(1,1,5000, 4);
	echo "SNMP Result>>".$ping->snmp_status."<<\n";
	echo "PING Result>>".$ping->ping_status."<<\n";
	echo "\n";
}

?>