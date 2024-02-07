<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

class Net_Ping {
	var $socket;

	var $host;

	var $port;

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

	function __construct() {
		$this->port = 33439;

		return true;
	}

	function __destruct() {
		return true;
	}

	function close_socket() {
		@socket_shutdown($this->socket, 2);
		socket_close($this->socket);
	}

	function start_time() {
		$this->timer_start_time = microtime(true);
	}

	function get_time($acc=2) {
		// format start time
		$start_time = $this->timer_start_time;
		// get and format end time
		$end_time = microtime(true);

		return number_format($end_time - $start_time, $acc);
	}

	function build_udp_packet() {
		$data  = 'cacti-monitoring-system'; // the actual test data

		// now lets build the actual UDP packet
		$this->request     = chr(0) . chr(1) . chr(0) . $data . chr(0);
		$this->request_len = strlen($this->request);
	}

	function ping_error_handler($errno, $errmsg, $filename, $linenum, $vars = array()) {
		return true;
	}

	function set_ping_error_handler() {
		set_error_handler(array($this, 'ping_error_handler'));
	}

	function restore_cacti_error_handler() {
		restore_error_handler();
	}

	function build_icmp_packet() {
		$seq_low   = rand(0,255);
		$seq_high  = rand(0,255);

		$data      = 'cacti-monitoring-system'; // the actual test data
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
		if (strlen($data) % 2) {
			$data .= "\x00";
		}

		$bit = unpack('n*', $data);
		$sum = array_sum($bit);

		while ($sum >> 16) {
			$sum = ($sum >> 16) + ($sum & 0xffff);
		}

		return pack('n*', ~$sum);
	}

	function ping_icmp() {
		global $config;

		/* ping me */
		if ($this->host['hostname']) {
			/* initialize variables */
			$this->ping_status   = 'down';
			$this->ping_response = __('ICMP Ping timed out');

			/* establish timeout variables */
			$to_sec  = floor($this->timeout / 1000);
			$to_usec = ($this->timeout % 1000) * 1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			/* determine the host's ip address
			 * this prevents from command injection as well*/
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				/* again, as a side effect, prevention from command injection */
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: ICMP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->response = 'ICMP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];

					return false;
				}
			}

			/* we have to use the real ping, in cases where windows failed or while using UNIX/Linux */
			$pattern  = bin2hex('cacti-monitoring-system'); // the actual test data

			$fping = read_config_option('path_fping');

			if ($fping != '' && file_exists($fping) && is_executable($fping)) {
				if (strpos($this->host['hostname'], ':') !== false) {
					$result = shell_exec('/usr/sbin/fping6 -q -t ' . $this->timeout . ' -c 1 -r ' . $this->retries . ' ' . $this->host['hostname'] . ' 2>&1');
				} else {
					$result = shell_exec($fping . ' -q -t ' . $this->timeout . ' -c 1 -r ' . $this->retries . ' ' . $this->host['hostname'] . ' 2>&1');
				}
			} else {
				/* host timeout given in ms, recalculate to sec, but make it an integer
				 * we might consider to use escapeshellarg on hostname,
				 * but this field has already been verified.
				 * The other fields are numerical fields only and thus
				 * not vulnerable for command injection */
				if (substr_count(strtolower(PHP_OS), 'sun')) {
					$result = shell_exec('ping ' . $this->host['hostname']);
				} elseif (substr_count(strtolower(PHP_OS), 'hpux')) {
					$result = shell_exec('ping -m ' . ceil($this->timeout / 1000) . ' -n ' . $this->retries . ' ' . $this->host['hostname']);
				} elseif (substr_count(strtolower(PHP_OS), 'mac')) {
					$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
				} elseif (substr_count(strtolower(PHP_OS), 'freebsd')) {
					if (strpos($this->host['hostname'], ':') !== false) {
						$result = shell_exec('/usr/sbin/ping6 -X ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
					} else {
						$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
					}
				} elseif (substr_count(strtolower(PHP_OS), 'darwin')) {
					$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
				} elseif (substr_count(strtolower(PHP_OS), 'bsd')) {
					$result = shell_exec('ping -w ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
				} elseif (substr_count(strtolower(PHP_OS), 'aix')) {
					$result = shell_exec('ping -i ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . $this->host['hostname']);
				} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
					$result = shell_exec('chcp 437 && ping -w ' . $this->timeout . ' -n ' . $this->retries . ' ' . $this->host['hostname']);
				} else {
					/* please know, that when running SELinux, httpd will throw
					 * ping: cap_set_proc: Permission denied
					 * as it now tries to open an ICMP socket and fails
					 * $result will be empty, then. */
					if (strpos($host_ip, ':') !== false) {
						$result = shell_exec('/usr/sbin/ping6 -W ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' -p ' . $pattern . ' ' . $this->host['hostname']);
					} else {
						$result = shell_exec('ping -W ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' -p ' . $pattern . ' ' . $this->host['hostname'] . ' 2>&1');

						if ((strpos($result, 'unknown host') !== false || strpos($result, 'Address family') !== false) && file_exists('/usr/sbin/ping6')) {
							$result = shell_exec('/usr/sbin/ping6 -W ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' -p ' . $pattern . ' ' . $this->host['hostname']);
						}
					}
				}
			}

			if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
				$position = strpos($result, 'min/avg/max');

				if ($position > 0) {
					$output  = trim(str_replace(' ms', '', substr($result, $position)));
					$pieces  = explode('=', $output);
					$results = explode('/', $pieces[1]);

					$this->ping_status   = $results[1];
					$this->ping_response = __('ICMP Ping Success (%s ms)', $results[1]);

					return true;
				} else {
					$this->status        = 'down';
					$this->ping_response = __('ICMP ping Timed out');

					return false;
				}
			} else {
				$position = strpos($result, 'Minimum');

				if ($position > 0) {
					$output  = trim(substr($result, $position));
					$pieces  = explode(',', $output);
					$results = explode('=', $pieces[2]);

					$this->ping_status   = trim(str_replace('ms', '', $results[1]));
					$this->ping_response = __('ICMP Ping Success (%s ms)', $this->ping_status);

					return true;
				} else {
					$this->status        = 'down';
					$this->ping_response = __('ICMP ping Timed out');

					return false;
				}
			}
		} else {
			$this->ping_status   = 'down';
			$this->ping_response = __('Destination address not specified');

			return false;
		}
	}

	function seteuid() {
		global $config;
		$cacti_user = '';

		/* if we are unix, set the effective userid to root and then create */
		if (($config['cacti_server_os'] == 'unix') &&
			(function_exists('posix_getuid'))) {
			$cacti_user = posix_getuid();
			posix_seteuid(0);
		}

		return $cacti_user;
	}

	function setuid($cacti_poller_account) {
		global $config;

		/* if we are unix, set the effective userid to root and then create */
		if (($config['cacti_server_os'] == 'unix') &&
			(function_exists('posix_getuid'))) {
			posix_seteuid($cacti_poller_account);
		}
	}

	function ping_snmp() {
		/* initialize variables */
		$this->snmp_status   = 'down';
		$this->snmp_response = 'Device did not respond to SNMP';
		$output              = '';

		/* get start time */
		$this->start_time();

		/* by default, we look at sysUptime */
		if ($this->avail_method == AVAIL_SNMP_GET_NEXT) {
			$oid = '.1.3.6.1.2.1.1.3.0';
		} elseif ($this->avail_method == AVAIL_SNMP_GET_SYSDESC) {
			$oid = '.1.3.6.1.2.1.1.1.0';
		} else {
			$oid = '.1.3.6.1.2.1.1.3.0';
		}

		$session = cacti_snmp_session($this->host['hostname'], $this->host['snmp_community'],
			$this->host['snmp_version'], $this->host['snmp_username'],
			$this->host['snmp_password'], $this->host['snmp_auth_protocol'],
			$this->host['snmp_priv_passphrase'], $this->host['snmp_priv_protocol'],
			$this->host['snmp_context'], $this->host['snmp_engine_id'],
			$this->host['snmp_port'], $this->host['snmp_timeout'],
			$this->retries, read_config_option('max_get_size'));

		if ($session === false) {
			$this->snmp_status   = 'down';
			$this->snmp_response = 'Failed to make SNMP session';

			return false;
		}

		$result = $this->get_snmp_result($session, $oid);
		if (!$result && $oid == '.1.3.6.1.2.1.1.3.0') {
			$result = $this->get_snmp_result($session, '.1.3.6.1.6.3.10.2.1.3.0');
		}

		$session->close();
		return $result;
	}

	function get_snmp_result($session, $oid) {
		/* getnext does not work in php versions less than 5 */
		if (($this->avail_method == AVAIL_SNMP_GET_NEXT) &&
			(version_compare('5', phpversion(), '<'))) {
			$output = cacti_snmp_session_getnext($session, $oid);
		} else {
			$output = cacti_snmp_session_get($session, $oid);
		}

		/* determine total time +- ~10% */
		$this->time = $this->get_time($this->precision);

		/* check result for uptime */
		if ($output !== false && $output != 'U' && strlen($output)) {
			/* calculate total time */
			$this->snmp_status   = $this->time * 1000;
			$this->snmp_response = 'Device responded to SNMP';

			return true;
		} else {
			$this->snmp_status   = 'down';
			$this->snmp_response = 'Device did not respond to SNMP';

			return false;
		}
	} /* ping_snmp */

	function ping_udp() {
		$this->set_ping_error_handler();

		/* hostname must be nonblank */
		if ($this->host['hostname'] != '') {
			/* initialize variables */
			$this->ping_status   = 'down';
			$this->ping_response = __('default');

			/* establish timeout variables */
			$to_sec  = floor($this->timeout / 1000);
			$to_usec = ($this->timeout % 1000) * 1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			/* determine the host's ip address */
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: UDP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->response = 'UDP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];
					$this->restore_cacti_error_handler();

					return false;
				}
			}

			/* initialize the socket */
			if (strpos($host_ip, ':') !== false) {
				if (defined('AF_INET6')) {
					$this->socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
				} else {
					$this->ping_response = __('IPv6 support seems to be missing!');
					$this->ping_status   = 'down';
					$this->restore_cacti_error_handler();

					return false;
				}
			} else {
				$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			}

			socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $to_sec, 'usec' => $to_usec));
			socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $to_sec, 'usec' => $to_usec));

			socket_connect($this->socket, $host_ip, $this->port);

			/* format packet */
			$this->build_udp_packet();

			$error       = '';
			$retry_count = 0;

			while (true) {
				if ($retry_count >= $this->retries) {
					$this->status        = 'down';
					$this->ping_response = __('UDP ping error: %s', $error);
					$this->close_socket();
					$this->restore_cacti_error_handler();

					return false;
				}

				/* get start time */
				$this->start_time();

				/* write to the socket */
				socket_write($this->socket, $this->request, $this->request_len);

				/* get the socket response */
				$r = array($this->socket);
				$w = array($this->socket);
				$f = array($this->socket);

				$num_changed_sockets = socket_select($r, $w, $f, $to_sec, $to_usec);

				if ($num_changed_sockets === false) {
					$error = 'UDP ping: socket_select(), reason: ' . socket_strerror(socket_last_error($this->socket));
				} else {
					switch($num_changed_sockets) {
						case 2: /* response received, so host is available */
						case 1:
							/* get packet response */
							//$code = socket_recv($this->socket, $this->reply, 256, 0);
							$code = socket_recv($this->socket, $this->reply, 256, 0);

							/* get the end time after the packet was received */
							$this->time = $this->get_time($this->precision);

							$errno = socket_last_error($this->socket);
							socket_clear_error($this->socket);

							if (($code == -1 || empty($code)) &&
								($errno == EHOSTUNREACH || $errno == ECONNRESET || $errno == ECONNREFUSED)) {
								/* set the return message */
								$this->ping_status   = $this->time * 1000;
								$this->ping_response = __('UDP Ping Success (%s ms)', $this->time * 1000);

								$this->close_socket();
								$this->restore_cacti_error_handler();

								return true;
							} else {
								$error = socket_strerror($errno);
							}

							break;
						case 0:
							/* timeout */
							$error = 'timeout';

							break;
					}
				}

				$retry_count++;
			}
		} else {
			$this->ping_response = __('Destination address not specified');
			$this->ping_status   = 'down';
			$this->restore_cacti_error_handler();

			return false;
		}
	} /* end ping_udp */

	function ping_tcp() {
		$this->set_ping_error_handler();

		/* hostname must be nonblank */
		if ($this->host['hostname'] != '') {
			/* initialize variables */
			$this->ping_status   = 'down';
			$this->ping_response = __('default');

			/* establish timeout variables */
			$to_sec  = floor($this->timeout / 1000);
			$to_usec = ($this->timeout % 1000) * 1000;

			/* clean up hostname if specifying snmp_transport */
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			/* determine the host's ip address */
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: TCP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->response = 'TCP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];
					$this->restore_cacti_error_handler();

					return false;
				}
			}

			/* initialize the socket */
			if (strpos($host_ip, ':') !== false) {
				if (defined('AF_INET6')) {
					$this->socket = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
				} else {
					$this->ping_response = __('IPv6 support appears to be missing!');
					$this->ping_status   = 'down';
					$this->restore_cacti_error_handler();

					return false;
				}
			} else {
				$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			}

			while (1) {
				/* set start time */
				$this->start_time();

				socket_set_block($this->socket);
				socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $to_sec, 'usec' => $to_usec));
				socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $to_sec, 'usec' => $to_usec));

				socket_connect($this->socket, $host_ip, $this->port);

				$errno = socket_last_error($this->socket);

				if ($errno > 0) {
					$this->ping_response = __('TCP ping: socket_connect(), reason: %s', socket_strerror($errno));
					$this->ping_status   = 'down';

					socket_clear_error($this->socket);

					$this->close_socket();
					$this->restore_cacti_error_handler();

					return false;
				}

				$r = array($this->socket);
				$w = array($this->socket);
				$f = array($this->socket);

				$num_changed_sockets = socket_select($r, $w, $f, $to_sec, $to_usec);

				if ($num_changed_sockets === false) {
					$this->ping_response = __('TCP ping: socket_select() failed, reason: %s', socket_strerror(socket_last_error()));
					$this->ping_status   = 'down';

					$this->close_socket();
					$this->restore_cacti_error_handler();

					return false;
				} else {
					switch($num_changed_sockets) {
						case 2: /* response received, so host is available */
						case 1:
							/* connected, so calculate the total time and return */
							$this->time = $this->get_time($this->precision);

							if (($this->time * 1000) <= $this->timeout) {
								$this->ping_response = __('TCP Ping Success (%s ms)', $this->time * 1000);
								$this->ping_status   = $this->time * 1000;
							}

							$this->close_socket();
							$this->restore_cacti_error_handler();

							return true;
						case 0:
							/* timeout */
							$this->ping_response = __('TCP ping timed out');
							$this->ping_status   = 'down';

							$this->close_socket();
							$this->restore_cacti_error_handler();

							return false;
					}
				}
			}
		} else {
			$this->ping_response = __('Destination address not specified');
			$this->ping_status   = 'down';
			$this->restore_cacti_error_handler();

			return false;
		}
	} /* end ping_tcp */

	function ping($avail_method = AVAIL_SNMP_AND_PING, $ping_type = PING_ICMP, $timeout=500, $retries=3) {
		$this->set_ping_error_handler();

		/* initialize variables */
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status   = 'down';
		$this->ping_response = __('Ping not performed due to setting.');
		$this->snmp_status   = 'down';
		$this->snmp_response = 'SNMP not performed due to setting or ping result.';
		$this->avail_method  = $avail_method;

		/* short circuit for availability none */
		if ($avail_method == AVAIL_NONE) {
			$this->ping_status = '0.00';
			$this->restore_cacti_error_handler();

			return true;
		}

		if ((!function_exists('socket_create')) && ($avail_method != AVAIL_NONE)) {
			$avail_method = AVAIL_SNMP;
			cacti_log('WARNING: sockets support not enabled in PHP, falling back to SNMP ping');
		}

		if (($retries <= 0) || ($retries > 5)) {
			$this->retries = 2;
		} else {
			$this->retries = $retries;
		}

		if ($timeout <= 0) {
			$this->timeout = 500;
		} else {
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
			} elseif ($ping_type == PING_UDP) {
				$ping_result = $this->ping_udp();
			} elseif ($ping_type == PING_TCP) {
				$ping_result = $this->ping_tcp();
			}
		}

		/* snmp test */
		if (($avail_method == AVAIL_SNMP) ||
		   ($avail_method == AVAIL_SNMP_GET_SYSDESC) ||
		   ($avail_method == AVAIL_SNMP_GET_NEXT) ||
		   ($avail_method == AVAIL_SNMP_AND_PING) ||
		   ($avail_method == AVAIL_SNMP_OR_PING)) {

			/* If we are in AND mode and already have a failed ping result, we don't need SNMP */
			if (!$ping_result && $avail_method == AVAIL_SNMP_AND_PING) {
				$snmp_result = $ping_result;
			} else {
				/* Lets assume the host is up because if we are in OR mode then we have already
				* pinged the host successfully, or some when silly people have not entered an
				* snmp_community under v1/2, we assume that this was successfully anyway */
				$snmp_result = true;
				$this->snmp_status = 0.000;
				if ($avail_method != AVAIL_SNMP_OR_PING &&
				   (strlen($this->host['snmp_community']) > 0 || $this->host['snmp_version'] >= 3)) {
					$snmp_result = $this->ping_snmp();
				}
			}
		}

		$this->restore_cacti_error_handler();

		switch ($avail_method) {
			case AVAIL_SNMP_OR_PING:
				return ($snmp_result || $ping_result);
			case AVAIL_SNMP_AND_PING:
				return ($snmp_result && $ping_result);
			case AVAIL_SNMP:
			case AVAIL_SNMP_GET_NEXT:
			case AVAIL_SNMP_GET_SYSDESC:
				return $snmp_result;
			case AVAIL_PING:
				return $ping_result;
			default:
				return false;
		}
	} /* end_ping */

	function is_ipaddress($ip_address = '') {
		/* check for ipv4/v6 */
		if (function_exists('filter_var')) {
			if (filter_var($ip_address, FILTER_VALIDATE_IP) !== false) {
				return true;
			} else {
				return false;
			}
		} elseif (inet_pton($ip_address) !== false) {
			return true;
		} else {
			return false;
		}
	}

	function strip_ip_address($ip_address) {
		/* clean up hostname if specifying snmp_transport */
		if (strpos($ip_address, 'tcp6:') !== false) {
			$ip_address = str_replace('tcp6:', '', strtolower($ip_address));

			if (strpos($ip_address, '[') !== false) {
				$parts      = explode(']', $ip_address);
				$ip_address = trim($parts[0], '[');
			}
		} elseif (strpos($ip_address, 'udp6:') !== false) {
			$ip_address = str_replace('udp6:', '', strtolower($ip_address));

			if (strpos($ip_address, '[') !== false) {
				$parts      = explode(']', $ip_address);
				$ip_address = trim($parts[0], '[');
			}
		} elseif (strpos($ip_address, 'tcp:') !== false) {
			$ip_address = str_replace('tcp:', '', strtolower($ip_address));
		} elseif (strpos($ip_address, 'udp:') !== false) {
			$ip_address = str_replace('udp:', '', strtolower($ip_address));
		}

		return $ip_address;
	}
}
