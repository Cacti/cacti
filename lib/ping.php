#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Larry Adams & Ian Berry                                            |
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
	var $retries;
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
		/* ping me */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status = "down";
			$this->ping_response = "ICMP Ping timed out";

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

			if (@socket_connect($this->socket, $this->host["hostname"], NULL)) {
				// do nothing
			} else {
				$this->ping_response = "Cannot connect to host";
				$this->ping_status   = "down";
				return false;
			}

			/* build the packet */
			$this->build_icmp_packet();

   		$retry_count = 0;
			while (1) {
				if ($retry_count >= $this->retries) {
					$this->status = "down";
					$this->response = "ICMP ping Timed out";
					return false;
				}

				/* get start time */
				$this->start_time();

				socket_write($this->socket, $this->request, $this->request_len);
				$code = @socket_recv($this->socket, $this->reply, 256, 0);

				/* get the end time */
				$this->time = $this->get_time($this->precision);

				if ($code) {
					$this->ping_status = $this->time * 1000;
					$this->ping_response = "Host is alive";
					return true;
				}

            $retry_count++;
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
		$this->snmp_response = "Host did not respond to SNMP";
		$output = "";

		/* get start time */
		$this->start_time();

		/* poll ifDescription for status */
		$retry_count = 0;
		while (1) {
			if ($retry_count >= $this->retries) {
				$this->snmp_status   = "down";
				$this->snmp_response = "Host did not respond to SNMP";
				return false;
			}

			$output = cacti_snmp_get($this->host["hostname"],
				$this->host["snmp_community"],
				".1.3.6.1.2.1.1.1.0" ,
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
				$this->snmp_status = $this->time*1000;
				$this->snmp_response = "Host responded to SNMP";
				return true;
			}

			$retry_count++;
		}
	} /* ping_snmp */

	function ping_udp() {
		/* Host must be nonblank */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status   = "down";
			$this->ping_response = "default";

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

			if (@socket_connect($this->socket, $this->host["hostname"], 33439)) {
					// do nothing
			} else {
				$this->ping_status = "down";
				$this->ping_result = "Cannot connect to host";
				return false;
			}

			/* format packet */
			$this->build_udp_packet();

			$retry_count = 0;
   		while (1) {
				if ($retry_count >= $this->retries) {
					$this->ping_response = "UDP ping timed out";
					$this->ping_status   = "down";
					return false;
				}

				/* set start time */
				$this->start_time();

				/* send packet to destination */
				socket_write($this->socket, $this->request, $this->request_len);

				/* get packet response */
				$code = @socket_recv($this->socket, $this->reply, 256, 0);

				/* caculate total time */
				$this->time = $this->get_time($this->precision);

				if (($code) || (empty($code))) {
					if (($this->time*1000) <= $this->timeout) {
						$this->ping_response = "Host is Alive";
						$this->ping_status   = $this->time*1000;
						return true;
					}
				}
				$retry_count++;
			}
			$this->close_socket();
		} else {
			$this->ping_response = "Destination address not specified";
			$this->ping_status   = "down";
			return false;
		}
	} /* end ping_udp */

	function ping($avail_method = AVAIL_SNMP_AND_PING, $ping_type = ICMP_PING, $timeout=500, $retries=3)
	{
		/* initialize variables */
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status   = "down";
		$this->ping_response = "Ping not performed due to setting.";
		$this->snmp_status   = "down";
		$this->snmp_response = "SNMP not performed due to setting.";

		/* do parameter checking before call */
		/* apply defaults if parameters are spooky */
		if ((int)$avail_method <= 0) $avail_method=AVAIL_SNMP;
		if ((int)$ping_type <= 0) $ping_type=PING_UDP;

		if (((int)$retries <= 0) || ((int)$retries > 5))
			$this->retries = 2;
		else
			$this->retries = $retries;

		if ((int)$timeout <= 0)
			$this->timeout=500;
		else
			$this->timeout=$timeout;

		/* decimal precision is 0.0000 */
		$this->precision = 5;

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
			if ($this->host["snmp_community"] != "") {
				$snmp_result = $this->ping_snmp();
			} else {
				$snmp_result = true;
			}
		} else {
			/* ping ICMP/UDP only */
			if ($ping_ping) {
				if ($ping_type == PING_ICMP) {
					$ping_result = $this->ping_icmp();
				} else {
					$ping_result = $this->ping_udp();
				}
			}
		}

		switch ($avail_method) {
			case AVAIL_SNMP_AND_PING:
				if ($snmp_result)
					return true;
				if (!$ping_result)
					return false;
				else
					return false;
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

?>