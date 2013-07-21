<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
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

	function Net_Ping() {
		$this->port = 33439;
	}

	function close_socket() {
		@socket_shutdown($this->socket, 2);
		socket_close($this->socket);
	}

	function start_time() {
		$this->timer_start_time = microtime();
	}

	function get_time($acc=2) {
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
		$seq_low   = rand(0,255);
		$seq_high  = rand(0,255);

		$data      = "cacti-monitoring-system"; // the actual test data
		$type      = "\x08";                    // 8 echo message; 0 echo reply message
		$code      = "\x00";                    // always 0 for this program
		$chksm     = "\x00\x00";                // generate checksum for icmp request
		$id        = chr($seq_high) . chr($seq_low);
		$sqn       = chr($seq_high) . chr($seq_low);
		$this->sqn = $sqn;

		// now lets build the actual icmp packet
		$this->request = $type.$code.$chksm.$id.$sqn.$data;
		$chksm         = $this->get_checksum($this->request);

		$this->request     = $type.$code.$chksm.$id.$sqn.$data;
		$this->request_len = strlen($this->request);
	}

	function get_checksum($data) {
		if (strlen($data)%2) {
			$data .= "\x00";
		}

		$bit = unpack('n*', $data);
		$sum = array_sum($bit);

		while ($sum>>16) {
			$sum = ($sum >> 16) + ($sum & 0xffff);
		}

		return pack('n*', ~$sum);
	}

	function ping_icmp() {
		global $config;

		/* ping me */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status   = "down";
			$this->ping_response = "ICMP Ping timed out";

			/* establish timeout variables */
			$to_sec  = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("tcp:", "", strtolower($this->host["hostname"]));
			$this->host["hostname"] = str_replace("udp:", "", strtolower($this->host["hostname"]));

			/* determine the host's ip address
			 * this prevents from command injection as well*/
			if ($this->is_ipaddress($this->host["hostname"])) {
				$host_ip = $this->host["hostname"];
			}else{
				/* again, as a side effect, prevention from command injection */
				$host_ip = gethostbyname($this->host["hostname"]);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log("WARNING: ICMP Ping Error: gethostbyname failed for " . $this->host["hostname"]);
					$this->response = "ICMP Ping Error: gethostbyname failed for " . $this->host["hostname"];
					return false;
				}
			}

			/* we have to use the real ping, in cases where windows failed or while using UNIX/Linux */
			$pattern  = bin2hex("cacti-monitoring-system"); // the actual test data

			/* host timeout given in ms, recalculate to sec, but make it an integer
			 * we might consider to use escapeshellarh on hostname,
			 * but this field has already been verified.
			 * The other fields are numerical fields only and thus
			 * not vulnerable for command injection */
			if (substr_count(strtolower(PHP_OS), "sun")) {
				$result = shell_exec("ping " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "hpux")) {
				$result = shell_exec("ping -m " . ceil($this->timeout/1000) . " -n " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "mac")) {
				$result = shell_exec("ping -t " . ceil($this->timeout/1000) . " -c " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "freebsd")) {
				$result = shell_exec("ping -t " . ceil($this->timeout/1000) . " -c " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "darwin")) {
				$result = shell_exec("ping -t " . ceil($this->timeout/1000) . " -c " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "bsd")) {
				$result = shell_exec("ping -w " . ceil($this->timeout/1000) . " -c " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "aix")) {
				$result = shell_exec("ping -i " . ceil($this->timeout/1000) . " -c " . $this->retries . " " . $this->host["hostname"]);
			}else if (substr_count(strtolower(PHP_OS), "winnt")) {
				$result = shell_exec("ping -w " . $this->timeout . " -n " . $this->retries . " " . $this->host["hostname"]);
			}else{
				$result = shell_exec("ping -W " . ceil($this->timeout/1000) . " -c " . $this->retries . " -p " . $pattern . " " . $this->host["hostname"]);
			}

			if (strtolower(PHP_OS) != "winnt") {
				$position = strpos($result, "min/avg/max");

				if ($position > 0) {
					$output  = trim(str_replace(" ms", "", substr($result, $position)));
					$pieces  = explode("=", $output);
					$results = explode("/", $pieces[1]);

					$this->ping_status = $results[1];
					$this->ping_response = "ICMP Ping Success (" . $results[1] . " ms)";

					return true;
				}else{
					$this->status = "down";
					$this->ping_response = "ICMP ping Timed out";

					return false;
				}
			}else{
				$position = strpos($result, "Minimum");

				if ($position > 0) {
					$output  = trim(substr($result, $position));
					$pieces  = explode(",", $output);
					$results = explode("=", $pieces[2]);

					$this->ping_status = trim(str_replace("ms", "", $results[1]));
					$this->ping_response = "ICMP Ping Success (" . $this->ping_status . " ms)";

					return true;
				}else{
					$this->status = "down";
					$this->ping_response = "ICMP ping Timed out";

					return false;
				}
			}
		}else{
			$this->ping_status   = "down";
			$this->ping_response = "Destination address not specified";

			return false;
		}
	}

	function seteuid() {
		global $config;
		$cacti_user = "";

		/* if we are unix, set the effective userid to root and then create */
		if (($config["cacti_server_os"] == "unix") &&
			(function_exists("posix_getuid"))) {
			$cacti_user = posix_getuid();
			posix_seteuid(0);
		}

		return $cacti_user;
	}

	function setuid($cacti_poller_account) {
		global $config;

		/* if we are unix, set the effective userid to root and then create */
		if (($config["cacti_server_os"] == "unix") &&
			(function_exists("posix_getuid"))) {
			posix_seteuid($cacti_poller_account);
		}
	}

	function ping_snmp() {
		/* initialize variables */
		$this->snmp_status   = "down";
		$this->snmp_response = "Host did not respond to SNMP";
		$output              = "";

		/* get start time */
		$this->start_time();

		/* by default, we look at sysUptime */
		if ($this->avail_method == AVAIL_SNMP_GET_NEXT) {
			if (version_compare("5", phpversion(), "<")) {
				$oid = ".1.3";
			}else{
				$oid = ".1.3.6.1.2.1.1.3.0";
			}
		}else if ($this->avail_method == AVAIL_SNMP_GET_SYSDESC) {
			$oid = ".1.3.6.1.2.1.1.1.0";
		}else {
			$oid = ".1.3.6.1.2.1.1.3.0";
		}

		/* getnext does not work in php versions less than 5 */
		if (($this->avail_method == AVAIL_SNMP_GET_NEXT) &&
			(version_compare("5", phpversion(), "<"))) {
			$output = cacti_snmp_getnext($this->host["hostname"],
				$this->host["snmp_community"],
				$oid,
				$this->host["snmp_version"],
				$this->host["snmp_username"],
				$this->host["snmp_password"],
				$this->host["snmp_auth_protocol"],
				$this->host["snmp_priv_passphrase"],
				$this->host["snmp_priv_protocol"],
				$this->host["snmp_context"],
				$this->host["snmp_port"],
				$this->host["snmp_timeout"],
				$this->retries,
				SNMP_CMDPHP);
		}else{
			$output = cacti_snmp_get($this->host["hostname"],
				$this->host["snmp_community"],
				$oid,
				$this->host["snmp_version"],
				$this->host["snmp_username"],
				$this->host["snmp_password"],
				$this->host["snmp_auth_protocol"],
				$this->host["snmp_priv_passphrase"],
				$this->host["snmp_priv_protocol"],
				$this->host["snmp_context"],
				$this->host["snmp_port"],
				$this->host["snmp_timeout"],
				$this->retries,
				SNMP_CMDPHP);
		}

		/* determine total time +- ~10% */
		$this->time = $this->get_time($this->precision);

		/* check result for uptime */
		if (strlen($output)) {
			/* calculte total time */
			$this->snmp_status   = $this->time*1000;
			$this->snmp_response = "Host responded to SNMP";

			return true;
		}else{
			$this->snmp_status   = "down";
			$this->snmp_response = "Host did not respond to SNMP";

			return false;
		}
	} /* ping_snmp */

	function ping_udp() {
		/* Host must be nonblank */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status   = "down";
			$this->ping_response = "default";

			/* establish timeout variables */
			$to_sec  = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("tcp:", "", strtolower($this->host["hostname"]));
			$this->host["hostname"] = str_replace("udp:", "", strtolower($this->host["hostname"]));

			/* determine the host's ip address */
			if ($this->is_ipaddress($this->host["hostname"])) {
				$host_ip = $this->host["hostname"];
			}else{
				$host_ip = gethostbyname($this->host["hostname"]);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log("WARNING: UDP Ping Error: gethostbyname failed for " . $this->host["hostname"]);
					$this->response = "UDP Ping Error: gethostbyname failed for " . $this->host["hostname"];
					return false;
				}
			}

 			/* initialize the socket */
			if (substr_count($host_ip,":") > 0) {
				if (defined("AF_INET6")) {
					$this->socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
				}else{
					$this->ping_response = "PHP version does not support IPv6";
					$this->ping_status   = "down";
					cacti_log("WARNING: IPv6 host detected, PHP version does not support IPv6");

					return false;
				}
			}else{
				$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			}

			socket_set_nonblock($this->socket);
			socket_connect($this->socket, $host_ip, $this->port);

			/* format packet */
			$this->build_udp_packet();

			$error = "";
			$retry_count = 0;
			while (1) {
				if ($retry_count >= $this->retries) {
					$this->status = "down";
					if ($error == "timeout") {
						$this->ping_response = "UDP ping Timed out";
					}else{
						$this->ping_response = "UDP ping Refused";
					}
					$this->close_socket();

					return false;
				}

				/* get start time */
				$this->start_time();

				/* write to the socket */
				socket_write($this->socket, $this->request, $this->request_len);

				/* get the socket response */
				switch(socket_select($r = array($this->socket), $w = NULL, $f = NULL, $to_sec, $to_usec)) {
				case 2:
					/* connection refused */
					$error = "refused";
					break;
				case 1:
					/* get the end time */
					$this->time = $this->get_time($this->precision);

					/* get packet response */
					$code = @socket_recv($this->socket, $this->reply, 256, 0);

					/* get the error, if applicable */
					$err = socket_last_error($this->socket);

					/* set the return message */
					$this->ping_status = $this->time * 1000;
					$this->ping_response = "UDP Ping Success (" . $this->time*1000 . " ms)";

					$this->close_socket();
					return true;
				case 0:
					/* timeout */
					$error = "timeout";
					break;
				}

				$retry_count++;
			}
		} else {
			$this->ping_response = "Destination address not specified";
			$this->ping_status   = "down";

			return false;
		}
	} /* end ping_udp */

	function ping_tcp() {
		/* Host must be nonblank */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status   = "down";
			$this->ping_response = "default";

			/* establish timeout variables */
			$to_sec  = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("tcp:", "", strtolower($this->host["hostname"]));
			$this->host["hostname"] = str_replace("udp:", "", strtolower($this->host["hostname"]));

			/* determine the host's ip address */
			if ($this->is_ipaddress($this->host["hostname"])) {
				$host_ip = $this->host["hostname"];
			}else{
				$host_ip = gethostbyname($this->host["hostname"]);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log("WARNING: TCP Ping Error: gethostbyname failed for " . $this->host["hostname"]);
					$this->response = "TCP Ping Error: gethostbyname failed for " . $this->host["hostname"];
					return false;
				}
			}

			/* initilize the socket */
			if (substr_count($host_ip,":") > 0) {
				if (defined("AF_INET6")) {
					$this->socket = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
				}else{
					$this->ping_response = "PHP binary does not support IPv6";
					$this->ping_status   = "down";
					cacti_log("WARNING: IPv6 host detected, PHP version does not support IPv6");

					return false;
				}
			}else{
				$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			}

			while (1) {
				/* set start time */
				$this->start_time();

				/* allow immediate return */
				socket_set_nonblock($this->socket);
				@socket_connect($this->socket, $host_ip, $this->port);
				socket_set_block($this->socket);

				switch(socket_select($r = array($this->socket), $w = array($this->socket), $f = array($this->socket), $to_sec, $to_usec)){
				case 2:
					/* connection refused */
					$this->time = $this->get_time($this->precision);

					if (($this->time*1000) <= $this->timeout) {
						$this->ping_response = "TCP Ping connection refused (" . $this->time*1000 . " ms)";
						$this->ping_status   = $this->time*1000;
					}

					$this->close_socket();

					return true; /* "connection refused" says: host is alive (else ping would time out) */
				case 1:
					/* connected, so calculate the total time and return */
					$this->time = $this->get_time($this->precision);

					if (($this->time*1000) <= $this->timeout) {
						$this->ping_response = "TCP Ping Success (" . $this->time*1000 . " ms)";
						$this->ping_status   = $this->time*1000;
					}

					$this->close_socket();

					return true;
				case 0:
					/* timeout */
					$this->ping_response = "TCP ping timed out";
					$this->ping_status   = "down";

					$this->close_socket();

					return false;
				}
			}
		} else {
			$this->ping_response = "Destination address not specified";
			$this->ping_status   = "down";

			return false;
		}
	} /* end ping_tcp */

	function ping($avail_method = AVAIL_SNMP_AND_PING, $ping_type = PING_ICMP, $timeout=500, $retries=3) {
		/* initialize variables */
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status   = "down";
		$this->ping_response = "Ping not performed due to setting.";
		$this->snmp_status   = "down";
		$this->snmp_response = "SNMP not performed due to setting or ping result.";
		$this->avail_method  = $avail_method;

		/* short circuit for availability none */
		if ($avail_method == AVAIL_NONE) {
			$this->ping_status = "0.00";
			return true;
		}

		if ((!function_exists("socket_create")) && ($avail_method != AVAIL_NONE)) {
			$avail_method = AVAIL_SNMP;
			cacti_log("WARNING: sockets support not enabled in PHP, falling back to SNMP ping");
		}

		if (($retries <= 0) || ($retries > 5)) {
			$this->retries = 2;
		}else{
			$this->retries = $retries;
		}

		if ($timeout <= 0) {
			$this->timeout = 500;
		}else{
			$this->timeout = $timeout;
		}

		/* decimal precision is 0.0000 */
		$this->precision = 5;

		/* snmp pinging has been selected at a minimum */
		$ping_result = false;
		$snmp_result = false;

		/* icmp/udp ping test */
		if (($avail_method == AVAIL_SNMP_AND_PING) ||
			($avail_method == AVAIL_SNMP_OR_PING) ||
			($avail_method == AVAIL_PING)) {
			if ($ping_type == PING_ICMP) {
				$ping_result = $this->ping_icmp();
			}else if ($ping_type == PING_UDP) {
				$ping_result = $this->ping_udp();
			}else if ($ping_type == PING_TCP) {
				$ping_result = $this->ping_tcp();
			}
		}

		/* snmp test */
		if (($avail_method == AVAIL_SNMP_OR_PING) && ($ping_result == true)) {
			$snmp_result = true;
			$this->snmp_status = 0.000;
		}else if (($avail_method == AVAIL_SNMP_AND_PING) && ($ping_result == false)) {
			$snmp_result = false;
		}else if (($avail_method == AVAIL_SNMP) || ($avail_method == AVAIL_SNMP_AND_PING)) {
			if (($this->host["snmp_community"] == "") && ($this->host["snmp_version"] != 3)) {
				/* snmp version 1/2 without community string assume SNMP test to be successful
				   due to backward compatibility issues */
				$snmp_result = true;
				$this->snmp_status = 0.000;
			}else{
				$snmp_result = $this->ping_snmp();
			}
		}

		switch ($avail_method) {
			case AVAIL_SNMP_OR_PING:
				if (($this->host["snmp_community"] == "") && ($this->host["snmp_version"] != 3)) {
					if ($ping_result) {
						return true;
					}else{
						return false;
					}
				}elseif ($snmp_result) {
					return true;
				}elseif ($ping_result) {
					return true;
				}else{
					return false;
				}
			case AVAIL_SNMP_AND_PING:
				if (($this->host["snmp_community"] == "") && ($this->host["snmp_version"] != 3)) {
					if ($ping_result) {
						return true;
					}else{
						return false;
					}
				}elseif (($snmp_result) && ($ping_result)) {
					return true;
				}else{
					return false;
				}
			case AVAIL_SNMP:
				if ($snmp_result) {
					return true;
				}else{
					return false;
				}
			case AVAIL_PING:
				if ($ping_result) {
					return true;
				}else{
					return false;
				}
			default:
				return false;
		}
	} /* end_ping */

	function is_ipaddress($ip_address = '') {
		/* check for ipv4/v6 */
		if (substr_count($ip_address, ":")) {
			/* compressed dot format */
			if (substr_count($ip_address, "::")) {
				$ip_address = str_replace("::", ":", $ip_address);
				$segments   = explode(":", $ip_address);
			}else{
				$segments = explode(":", $ip_address);

				if (sizeof($segments) != 8) {
					/* should be 8 segments */
					return false;
				}
			}

			$i = 0;
			foreach ($segments as $segment) {
				$i++;

				if ((trim($segment) == "") && ($i == 1)) {
					continue;
				}elseif (!is_numeric("0x" . $segment)) {
					return false;
				}
			}

			return true;
		}else if (strlen($ip_address) <= 15) {
			$octets = explode('.', $ip_address);

			$i = 0;

			if (count($octets) != 4) {
				return false;
			}

			foreach($octets as $octet) {
				if ($i == 0 || $i == 3) {
					if(($octet < 0) || ($octet > 255)) {
						return false;
					}
				}else{
					if(($octet < 0) || ($octet > 255)) {
						return false;
					}
				}

				$i++;
			}

			return true;
		}else{
			return false;
		}
	}
}

?>
