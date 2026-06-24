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

class Net_Ping {
	public socket $socket;
	public array $host;
	public int $port;
	public mixed $ping_status;
	public string $ping_response;
	public mixed $snmp_status;
	public string $snmp_response;
	public string $request;
	public int $request_len;
	public mixed $reply;
	public int $timeout;
	public int $retries;
	public int $precision;
	public int $time;
	public float $timer_start_time;
	public string $sqn;
	public int $avail_method;
	public int $ping_type;

	function __construct() {
		$this->port = 33439;
	}

	function __destruct() {
	}

	function close_socket() : void {
		@socket_shutdown($this->socket, 2);
		socket_close($this->socket);
	}

	function start_time() : void {
		$this->timer_start_time = microtime(true);
	}

	function get_time(int $acc = 2) : mixed {
		// format start time
		$start_time = $this->timer_start_time;
		// get and format end time
		$end_time = microtime(true);

		return number_format($end_time - $start_time, $acc);
	}

	function build_udp_packet() : void {
		$data  = 'cacti-monitoring-system'; // the actual test data

		// now lets build the actual UDP packet
		$this->request     = chr(0) . chr(1) . chr(0) . $data . chr(0);
		$this->request_len = strlen($this->request);
	}

	function ping_error_handler(int $errno, string $errmsg, string $filename, int $linenum, array $vars = []) : bool {
		return true;
	}

	function set_ping_error_handler() : void {
		set_error_handler([$this, 'ping_error_handler']);
	}

	function restore_cacti_error_handler() : void {
		restore_error_handler();
	}

	function build_icmp_packet() : void {
		$seq_low   = random_int(0,255);
		$seq_high  = random_int(0,255);

		$data      = 'cacti-monitoring-system'; // the actual test data
		$type      = "\x08";                    // 8 echo message; 0 echo reply message
		$code      = "\x00";                    // always 0 for this program
		$chksm     = "\x00\x00";                // generate checksum for icmp request
		$id        = chr($seq_high) . chr($seq_low);
		$sqn       = chr($seq_high) . chr($seq_low);
		$this->sqn = $sqn;

		// now lets build the actual icmp packet
		$this->request = $type . $code . $chksm . $id . $sqn . $data;
		$chksm         = $this->get_checksum($this->request);

		$this->request     = $type . $code . $chksm . $id . $sqn . $data;
		$this->request_len = strlen($this->request);
	}

	function get_checksum(string $data) : string {
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

	function ping_icmp() : bool {
		// ping me
		if ($this->host['hostname']) {
			// initialize variables
			$this->ping_status   = 'down';
			$this->ping_response = __('ICMP Ping timed out');

			// establish timeout variables
			$to_sec  = floor($this->timeout / 1000);
			$to_usec = ($this->timeout % 1000) * 1000;

			// clean up hostname if specifying snmp_transport
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			/* determine the host's ip address
			 * this prevents from command injection as well*/
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				// again, as a side effect, prevention from command injection
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: ICMP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->ping_response = 'ICMP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];

					return false;
				}
			}

			// we have to use the real ping, in cases where windows failed or while using UNIX/Linux
			$pattern  = bin2hex('cacti-monitoring-system'); // the actual test data

			$fping = read_config_option('path_fping');

			if ($fping != '' && file_exists($fping) && is_executable($fping)) {
				$using_fping = true;

				if (filter_var($this->host['hostname'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
					if (file_exists('/usr/sbin/fping6')) {
						$fping = '/usr/sbin/fping6';
					} elseif (file_exists('/usr/bin/fping6')) {
						$fping = '/usr/bin/fping6';
					} elseif (file_exists('/usr/local/sbin/fping6')) {
						$fping = '/usr/local/sbin/fping6';
					} elseif (file_exists('/usr/local/bin/fping6')) {
						$fping = '/usr/local/bin/fping6';
					}
				}

				$result = shell_exec(cacti_escapeshellarg($fping) . ' -q -t ' . $this->timeout . ' -c 1 -r ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']) . ' 2>&1');
			} else {
				$using_fping = false;

				/**
				 * The host timeout is given in ms; recalculate to seconds.
				 * All numeric fields are safe. The hostname is quoted via
				 * cacti_escapeshellarg() as defense-in-depth alongside the
				 * DNS resolution check above.
				 */
				if (substr_count(cacti_strtolower(PHP_OS), 'sun')) {
					$result = shell_exec('ping ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'hpux')) {
					$result = shell_exec('ping -m ' . ceil($this->timeout / 1000) . ' -n ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'mac')) {
					$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'freebsd')) {
					if (filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
						$result = shell_exec('ping6 -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
					} else {
						$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
					}
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'darwin')) {
					$result = shell_exec('ping -t ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'bsd')) {
					$result = shell_exec('ping -w ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (substr_count(cacti_strtolower(PHP_OS), 'aix')) {
					$result = shell_exec('ping -i ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} elseif (cacti_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
					$result = shell_exec('chcp 437 && ping -w ' . $this->timeout . ' -n ' . $this->retries . ' ' . cacti_escapeshellarg($this->host['hostname']));
				} else {
					/**
					 * Please know, that when running SELinux, httpd will throw
					 * ping: cap_set_proc: Permission denied
					 * as it now tries to open an ICMP socket and fails
					 * $result will be empty, then.
					 */
					if (filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
						$result = shell_exec('ping -6 -W ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' -p ' . $pattern . ' ' . cacti_escapeshellarg($this->host['hostname']));
					} else {
						$result = shell_exec('ping -W ' . ceil($this->timeout / 1000) . ' -c ' . $this->retries . ' -p ' . $pattern . ' ' . cacti_escapeshellarg($this->host['hostname']) . ' 2>&1');
					}
				}
			}

			if ($using_fping) {
				/**
				 * Prototype fping output below:
				 * www.google.com : xmt/rcv/%loss = 1/1/0%, min/avg/max = 25.6/25.6/25.6
				 * 172.24.254.2 : xmt/rcv/%loss = 1/1/0%, min/avg/max = 7.01/7.01/7.01
				 */
				$position = strpos($result, 'min/avg/max =');

				if ($position > 0) {
					$output              = substr($result, $position);
					$results             = explode('/', $output);
					$this->ping_status   = $results[3]; // avg
					$this->ping_response = __('ICMP Ping Success (fping.exe) (%s ms)', $results[1]);

					return true;
				} else {
					$this->ping_status   = 'down';
					$this->ping_response = __('ICMP Ping Timed Out (fping.exe) (' . $this->host['hostname'] . '), Result [' . $result . ']');

					return false;
				}
			} elseif (str_starts_with(PHP_OS, 'WIN')) {
				/**
				 * The native ping command was used for windows platform has output
				 * as follows.
				 *
				 * ping -w 500 -n 3 abc
				 *
				 * Pinging abc [216.239.38.120] with 32 bytes of data:
				 * Reply from 216.239.38.120: bytes=32 time=30ms TTL=53
				 * Reply from 216.239.38.120: bytes=32 time=22ms TTL=53
				 * Reply from 216.239.38.120: bytes=32 time=25ms TTL=53
				 *
				 * Ping statistics for 216.239.38.120:
				 * Packets: Sent = 3, Received = 3, Lost = 0 (0% loss),
				 * Approximate round trip times in milli-seconds:
				 * Minimum = 22ms, Maximum = 30ms, Average = 25ms
				 */
				$position = strpos($result, 'Minimum');

				if ($position > 0) {
					$output  = trim(substr($result, $position));
					$pieces  = explode(',', $output);
					$results = explode('=', $pieces[2]); // Average

					$this->ping_status   = trim(str_replace('ms', '', $output));
					$this->ping_response = __('ICMP Ping Success (ping.exe) (%s ms)', $this->ping_status);

					return true;
				} else {
					$this->ping_status   = 'down';
					$this->ping_response = __('ICMP Ping Timed Out (ping.exe) (' . $this->host['hostname'] . '), Result [' . $result . ']');

					return false;
				}
			} else {
				$position = strpos($result, 'min/avg/max');

				if ($position > 0) {
					$output  = trim(str_replace(' ms', '', substr($result, $position)));
					$pieces  = explode('=', $output);
					$results = explode('/', $pieces[1]);

					$this->ping_status   = $results[1];
					$this->ping_response = __('ICMP Ping Success (%s ms)', $results[1]);

					return true;
				} else {
					$this->ping_status   = 'down';
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

	function seteuid() : int {
		$cacti_user = '';

		// if we are unix, set the effective userid to root and then create
		if ((CACTI_SERVER_OS == 'unix') &&
			(function_exists('posix_getuid'))) {
			$cacti_user = posix_getuid();
			posix_seteuid(0);
		}

		return $cacti_user;
	}

	function setuid(int $cacti_poller_account) : void {
		// if we are unix, set the effective userid to root and then create
		if ((CACTI_SERVER_OS == 'unix') &&
			(function_exists('posix_getuid'))) {
			posix_seteuid($cacti_poller_account);
		}
	}

	function ping_snmp() : mixed {
		// initialize variables
		$this->snmp_status   = 'down';
		$this->snmp_response = 'Device did not respond to SNMP';
		$output              = '';

		// get start time
		$this->start_time();

		// by default, we look at sysUptime
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

	function get_snmp_result(object $session, string $oid) : bool {
		// getnext does not work in php versions less than 5
		if (($this->avail_method == AVAIL_SNMP_GET_NEXT) &&
			(version_compare('5', phpversion(), '<'))) {
			$output = cacti_snmp_session_getnext($session, $oid);
		} else {
			$output = cacti_snmp_session_get($session, $oid);
		}

		// determine total time +- ~10%
		$this->time = $this->get_time($this->precision);

		// check result for uptime
		if ($output !== false && $output != 'U' && strlen($output)) {
			// calculate total time
			$this->snmp_status   = $this->time * 1000;
			$this->snmp_response = 'Device responded to SNMP';

			return true;
		} else {
			$this->snmp_status   = 'down';
			$this->snmp_response = 'Device did not respond to SNMP';

			return false;
		}
	} // ping_snmp

	function ping_udp() : bool {
		$this->set_ping_error_handler();

		// hostname must be nonblank
		if ($this->host['hostname'] != '') {
			// initialize variables
			$this->ping_status   = 'down';
			$this->ping_response = __('default');

			// establish timeout variables
			$to_sec  = intval(floor($this->timeout / 1000));
			$to_usec = intval(($this->timeout % 1000) * 1000);

			// clean up hostname if specifying snmp_transport
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			// determine the host's ip address
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: UDP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->ping_response = 'UDP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];
					$this->restore_cacti_error_handler();

					return false;
				}
			}

			// initialize the socket
			if (filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
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

			socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $to_sec, 'usec' => $to_usec]);
			socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $to_sec, 'usec' => $to_usec]);

			socket_connect($this->socket, $host_ip, $this->port);

			// format packet
			$this->build_udp_packet();

			$error       = '';
			$retry_count = 0;

			while (true) {
				if ($retry_count >= $this->retries) {
					$this->ping_status   = 'down';
					$this->ping_response = __('UDP ping error: %s', $error);
					$this->close_socket();
					$this->restore_cacti_error_handler();

					return false;
				}

				// get start time
				$this->start_time();

				// write to the socket
				socket_write($this->socket, $this->request, $this->request_len);

				// get the socket response
				$r = [$this->socket];
				$w = [$this->socket];
				$f = [$this->socket];

				$num_changed_sockets = socket_select($r, $w, $f, $to_sec, $to_usec);

				if ($num_changed_sockets === false) {
					$error = 'UDP ping: socket_select(), reason: ' . socket_strerror(socket_last_error($this->socket));
				} else {
					switch($num_changed_sockets) {
						case 2: // response received, so host is available
						case 1:
							// get packet response
							// $code = socket_recv($this->socket, $this->reply, 256, 0);
							$code = socket_recv($this->socket, $this->reply, 256, 0);

							// get the end time after the packet was received
							$this->time = $this->get_time($this->precision);

							$errno = socket_last_error($this->socket);
							socket_clear_error($this->socket);

							if (($code == -1 || empty($code)) &&
								($errno == EHOSTUNREACH || $errno == ECONNRESET || $errno == ECONNREFUSED)) {
								// set the return message
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
							// timeout
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
	} // end ping_udp

	function ping_tcp() : bool {
		$this->set_ping_error_handler();

		// hostname must be nonblank
		if ($this->host['hostname'] != '') {
			// initialize variables
			$this->ping_status   = 'down';
			$this->ping_response = __('default');

			// establish timeout variables
			$to_sec  = intval(floor($this->timeout / 1000));
			$to_usec = intval(($this->timeout % 1000) * 1000);

			// clean up hostname if specifying snmp_transport
			$this->host['hostname'] = $this->strip_ip_address($this->host['hostname']);

			// determine the host's ip address
			if ($this->is_ipaddress($this->host['hostname'])) {
				$host_ip = $this->host['hostname'];
			} else {
				$host_ip = cacti_gethostbyname($this->host['hostname']);

				if (!$this->is_ipaddress($host_ip)) {
					cacti_log('WARNING: TCP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname']);
					$this->ping_response = 'TCP Ping Error: cacti_gethostbyname failed for ' . $this->host['hostname'];
					$this->restore_cacti_error_handler();

					return false;
				}
			}

			// initialize the socket
			if (filter_var($host_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
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
				// set start time
				$this->start_time();

				socket_set_block($this->socket);
				socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $to_sec, 'usec' => $to_usec]);
				socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $to_sec, 'usec' => $to_usec]);

				socket_connect($this->socket, $host_ip, $this->port);

				$errno = socket_last_error($this->socket);

				if ($errno > 0) {
					if ($errno == 111 && $this->ping_type == PING_TCP_CLOSED) {
						$this->time          = $this->get_time($this->precision);
						$this->ping_status   = 'up';
						$this->ping_response = __('TCP Ping Success Connection Refused (%s ms)', $this->time * 1000);
						$this->ping_status   = $this->time * 1000;
					} else {
						$this->ping_response = __('TCP Ping Failed: socket_connect(), reason: %s', socket_strerror($errno));
						$this->ping_status   = 'down';
					}

					socket_clear_error($this->socket);

					$this->close_socket();
					$this->restore_cacti_error_handler();

					if ($this->ping_status == 'down') {
						return false;
					} else {
						return true;
					}
				}

				$r = [$this->socket];
				$w = [$this->socket];
				$f = [$this->socket];

				$num_changed_sockets = socket_select($r, $w, $f, $to_sec, $to_usec);

				if ($num_changed_sockets === false) {
					$this->ping_response = __('TCP Ping Failed: socket_select() failed, reason: %s', socket_strerror(socket_last_error()));
					$this->ping_status   = 'down';

					$this->close_socket();
					$this->restore_cacti_error_handler();

					return false;
				} else {
					switch($num_changed_sockets) {
						case 2: // response received, so host is available
						case 1:
							// connected, so calculate the total time and return
							$this->time = $this->get_time($this->precision);

							if (($this->time * 1000) <= $this->timeout) {
								$this->ping_response = __('TCP Ping Success (%s ms)', $this->time * 1000);
								$this->ping_status   = $this->time * 1000;
							}

							$this->close_socket();
							$this->restore_cacti_error_handler();

							return true;
						case 0:
							// timeout
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
	} // end ping_tcp

	function ping(int $avail_method = AVAIL_SNMP_AND_PING, int $ping_type = PING_ICMP, int $timeout = 500, int $retries = 3) : bool {
		$this->set_ping_error_handler();

		// initialize variables
		$ping_ping = true;
		$ping_snmp = true;

		$this->ping_status   = 'down';
		$this->ping_response = __('Ping not performed due to setting.');
		$this->ping_type     = $ping_type;
		$this->snmp_status   = 'down';
		$this->snmp_response = 'SNMP not performed due to setting or ping result.';
		$this->avail_method  = $avail_method;

		// short circuit for availability none
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

		// decimal precision is 0.0000
		$this->precision = 5;

		// snmp pinging has been selected at a minimum
		$ping_result = false;
		$snmp_result = false;

		// icmp/udp ping test
		if (($avail_method == AVAIL_SNMP_AND_PING) ||
			($avail_method == AVAIL_SNMP_OR_PING) ||
			($avail_method == AVAIL_PING)) {
			if ($ping_type == PING_ICMP) {
				$ping_result = $this->ping_icmp();
			} elseif ($ping_type == PING_UDP) {
				$ping_result = $this->ping_udp();
			} elseif ($ping_type == PING_TCP || $ping_type == PING_TCP_CLOSED) {
				$ping_result = $this->ping_tcp();
			}
		}

		// snmp test
		if (($avail_method == AVAIL_SNMP) ||
		   ($avail_method == AVAIL_SNMP_GET_SYSDESC) ||
		   ($avail_method == AVAIL_SNMP_GET_NEXT) ||
		   ($avail_method == AVAIL_SNMP_AND_PING) ||
		   ($avail_method == AVAIL_SNMP_OR_PING)) {
			// If we are in AND mode and already have a failed ping result, we don't need SNMP
			if (!$ping_result && $avail_method == AVAIL_SNMP_AND_PING) {
				$snmp_result = $ping_result;
			} else {
				/* Lets assume the host is up because if we are in OR mode then we have already
				 * pinged the host successfully, or some when silly people have not entered an
				 * snmp_community under v1/2, we assume that this was successfully anyway */
				$snmp_result       = true;
				$this->snmp_status = 0.000;

				if ($avail_method != AVAIL_SNMP_OR_PING &&
				   (strlen($this->host['snmp_community']) > 0 || $this->host['snmp_version'] >= 3)) {
					$snmp_result = $this->ping_snmp();
				}
			}
		}

		$this->restore_cacti_error_handler();

		return match ($avail_method) {
			AVAIL_SNMP_OR_PING     => $snmp_result || $ping_result,
			AVAIL_SNMP_AND_PING    => $snmp_result && $ping_result,
			AVAIL_SNMP             => $snmp_result,
			AVAIL_SNMP_GET_NEXT    => $snmp_result,
			AVAIL_SNMP_GET_SYSDESC => $snmp_result,
			AVAIL_PING             => $ping_result,
			default                => false,
		};
	} // end_ping

	function is_ipaddress(string $ip_address = '') : bool {
		// check for ipv4/v6
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

	function strip_ip_address(string $ip_address) : string {
		// clean up hostname if specifying snmp_transport
		if (str_contains($ip_address, 'tcp6:')) {
			$ip_address = str_replace('tcp6:', '', cacti_strtolower($ip_address));

			if (str_contains($ip_address, '[')) {
				$parts      = explode(']', $ip_address);
				$ip_address = trim($parts[0], '[');
			}
		} elseif (str_contains($ip_address, 'udp6:')) {
			$ip_address = str_replace('udp6:', '', cacti_strtolower($ip_address));

			if (str_contains($ip_address, '[')) {
				$parts      = explode(']', $ip_address);
				$ip_address = trim($parts[0], '[');
			}
		} elseif (str_contains($ip_address, 'tcp:')) {
			$ip_address = str_replace('tcp:', '', cacti_strtolower($ip_address));
		} elseif (str_contains($ip_address, 'udp:')) {
			$ip_address = str_replace('udp:', '', cacti_strtolower($ip_address));
		}

		return $ip_address;
	}
}
