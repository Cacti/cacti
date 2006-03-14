<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Larry Adams & Ian Berry                              |
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
		$seq_low = rand(0,255);
		$seq_high = rand(0,255);

		$data = "cacti-monitoring-system"; // the actual test data
		$type = "\x08"; // 8 echo message; 0 echo reply message
		$code = "\x00"; // always 0 for this program
		$chksm = "\x00\x00"; // generate checksum for icmp request
		$id = "\x00\x00";
		$sqn = chr($seq_high) . chr($seq_low);

		// now lets build the actual icmp packet
		$this->request = $type.$code.$chksm.$id.$sqn.$data;
		$chksm = $this->get_checksum($this->request);

		$this->request = $type.$code.$chksm.$id.$sqn.$data;
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
		/* ping me */
		if ($this->host["hostname"]) {
			/* initialize variables */
			$this->ping_status = "down";
			$this->ping_response = "ICMP Ping timed out";

			/* establish timeout variables */
			$to_sec = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("TCP:", "", $this->host["hostname"]);
			$this->host["hostname"] = str_replace("UDP:", "", $this->host["hostname"]);

			/* determine the host's ip address */
			$host_ip = gethostbyname($this->host["hostname"]);

			/* set the effective user of root if unix */
			$cacti_poller_account = $this->seteuid();

			/* initilize the socket */
			if (substr_count($host_ip,":") > 0) {
				if (defined("AF_INET6")) {
					$this->socket = socket_create(AF_INET6, SOCK_RAW, 1);
				}else{
					$this->ping_response = "PHP version does not support IPv6";
					$this->ping_status   = "down";
					cacti_log("WARNING: IPv6 host detected, PHP version does not support IPv6\n");

					/* return to real user account */
					$this->setuid($cacti_poller_account);
					return false;
				}
			}else{
				$this->socket = socket_create(AF_INET, SOCK_RAW, 1);
			}
			socket_set_block($this->socket);

			if (!(@socket_connect($this->socket, $host_ip, NULL))) {
				$this->ping_response = "Cannot connect to host";
				$this->ping_status   = "down";

				/* return to real user account */
				$this->setuid($cacti_poller_account);
				return false;
			}

			/* build the packet */
			$this->build_icmp_packet();

			$retry_count = 0;
			while (1) {
				if ($retry_count >= $this->retries) {
					$this->status = "down";
					if ($error == "timeout") {
						$this->response = "ICMP ping Timed out";
					}else{
						$this->response = "ICMP ping Refused";
					}
					$this->close_socket();

					/* return to real user account */
					$this->setuid($cacti_poller_account);
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

					/* set the return message */
					$this->ping_status = $this->time * 1000;
					$this->ping_response = "Host is alive";

					$this->close_socket();

					/* return to real user account */
					$this->setuid($cacti_poller_account);

					return true;
				case 0:
					/* timeout */
					$error = "timeout";
					break;
				}

				$retry_count++;
			}
		}else{
			$this->ping_status = "down";
			$this->ping_response = "Destination address not specified";
			return false;
		}
	}

	function seteuid() {
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
		/* if we are unix, set the effective userid to root and then create */
		if (($config["cacti_server_os"] == "unix") &&
			(function_exists("posix_getuid"))) {
			posix_seteuid($cacti_poller_account);
		}
	}

	function ping_snmp() {
		/* initialize variables */
		$this->snmp_status = "down";
		$this->snmp_response = "Host did not respond to SNMP";
		$output = "";

		/* get start time */
		$this->start_time();

		/* poll sysUptime for status */
		$retry_count = 0;
		while (1) {
			if ($retry_count >= $this->retries) {
				$this->snmp_status   = "down";
				$this->snmp_response = "Host did not respond to SNMP";
				return false;
			}

			$output = cacti_snmp_get($this->host["hostname"],
				$this->host["snmp_community"],
				".1.3.6.1.2.1.1.3.0" ,
				$this->host["snmp_version"],
				$this->host["snmp_username"],
				$this->host["snmp_password"],
				$this->host["snmp_port"],
				$this->host["snmp_timeout"],
				SNMP_CMDPHP);

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

			/* establish timeout variables */
			$to_sec = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("TCP:", "", $this->host["hostname"]);
			$this->host["hostname"] = str_replace("UDP:", "", $this->host["hostname"]);

			/* determine the host's ip address */
			$host_ip = gethostbyname($this->host["hostname"]);

			/* initilize the socket */
			if (substr_count($host_ip,":") > 0) {
				if (defined("AF_INET6")) {
					$this->socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
				}else{
					$this->ping_response = "PHP version does not support IPv6";
					$this->ping_status   = "down";
					cacti_log("WARNING: IPv6 host detected, PHP version does not support IPv6\n");
					return false;
				}
			}else{
				$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			}

			socket_set_nonblock($this->socket);
			socket_connect($this->socket, $host_ip, $this->port);
			socket_set_nonblock($this->socket);

			/* format packet */
			$this->build_udp_packet();

			$retry_count = 0;
			while (1) {
				if ($retry_count >= $this->retries) {
					$this->status = "down";
					if ($error == "timeout") {
						$this->response = "UDP ping Timed out";
					}else{
						$this->response = "UDP ping Refused";
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
					echo $code = @socket_recv($this->socket, $this->reply, 256, 0);
					echo "->";

					if (empty($code)) { echo "refused"; }

					/* get the error, if applicable */
					$err = socket_last_error($this->socket);

					/* set the return message */
					$this->ping_status = $this->time * 1000;
					$this->ping_response = "Host is alive";

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
			$to_sec = floor($this->timeout/1000);
			$to_usec = ($this->timeout%1000)*1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host["hostname"] = str_replace("TCP:", "", $this->host["hostname"]);
			$this->host["hostname"] = str_replace("UDP:", "", $this->host["hostname"]);

			/* determine the host's ip address */
			$host_ip = gethostbyname($this->host["hostname"]);

			/* initilize the socket */
			if (substr_count($host_ip,":") > 0) {
				if (defined("AF_INET6")) {
					$this->socket = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
				}else{
					$this->ping_response = "PHP binary does not support IPv6";
					$this->ping_status   = "down";
					cacti_log("WARNING: IPv6 host detected, PHP version does not support IPv6\n");
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
					$this->ping_response = "TCP ping connection refused";
					$this->ping_status   = "down";

					$this->close_socket();
					return false;
				case 1:
					/* connected, so calculate the total time and return */
					$this->time = $this->get_time($this->precision);

					if (($this->time*1000) <= $this->timeout) {
						$this->ping_response = "TCP ping success";
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

	function ping($avail_method = AVAIL_SNMP_AND_PING, $ping_type = ICMP_PING, $timeout=500, $retries=3)
	{
		/* initialize variables */
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status   = "down";
		$this->ping_response = "Ping not performed due to setting.";
		$this->snmp_status   = "down";
		$this->snmp_response = "SNMP not performed due to setting or ping result.";

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

		/* snmp pinging has been selected at a minimum */
		$ping_result = false;
		$snmp_result = false;

		/* icmp/udp ping test */
		if (($avail_method == AVAIL_SNMP_AND_PING) || ($avail_method == AVAIL_PING)) {
			if ($ping_type == PING_ICMP) {
				$ping_result = $this->ping_icmp();
			}else if ($ping_type == PING_UDP) {
				$ping_result = $this->ping_udp();
			}else if ($ping_type == PING_TCP) {
				$ping_result = $this->ping_tcp();
			}
		}

		/* snmp test */
		if (($avail_method == AVAIL_SNMP) || (($avail_method == AVAIL_SNMP_AND_PING) && ($ping_result == true))) {
			if ($this->host["snmp_community"] != "") {
				$snmp_result = $this->ping_snmp();
			}else{
				$snmp_result = true;
			}
		}else if (($avail_method == AVAIL_SNMP_AND_PING) && ($ping_result == false)) {
			$snmp_result = false;
		}

		switch ($avail_method) {
			case AVAIL_SNMP_AND_PING:
				if ($snmp_result)
					return true;
				if ($ping_result)
					return true;
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