#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* we are not talking to the browser */
$no_http_headers = true;

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

/* Start Initialization Section */
include_once('./include/global.php');
include_once('./lib/rrd.php');
include_once('./lib/poller.php');
include_once('./lib/boost.php');

/* suppress warnings */
error_reporting(0);

/* install the boost error handler */
set_error_handler('boost_error_handler');

/* in UNIX/LINUX we change the effective user of the process to the
 * cacti users specified in the <path_cacti>/include/global.php file.
 */
$username = read_config_option('boost_server_effective_user');
if (function_exists('posix_getpwnam')) {
	if (strlen($username)) {
		$user_info = posix_getpwnam($username);

		if (strlen($user_info['uid'])) {
			posix_setuid($user_info['uid']);
		}
	}
} elseif ($username != '') {
	boost_svr_log("INFO: no support for switching the effective user\n");
}

/* setup global variables */
$listen_port     = read_config_option('boost_server_listen_port');
$rrd_path        = read_config_option('path_rrdtool');
$rrd_update_path = read_config_option('boost_path_rrdupdate');
$php_binary_path = read_config_option('path_php_binary');
$log_file        = read_config_option('path_cactilog');

if (strstr(PHP_OS, 'WIN')) {
	$eol = "\r\n";
}else{
	$eol = "\n";
}

/* create a streaming socket, of type TCP/IP */
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if (!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
	boost_svr_log('WARNING: ' . socket_strerror(socket_last_error()));
}

/*---------------------------------------------------------------------------*/
/* "bind" the socket to the address to "localhost", on port $port            */
/* so this means that all connections on this port are now our resposibility */
/* to send/recv data, disconnect, etc..                                      */
/*---------------------------------------------------------------------------*/
socket_set_block($sock);
if (!socket_bind($sock, 0, $listen_port)) {
	boost_svr_log("FATAL: Socket bind to port '$listen_port' Failed, Socket likely in use!");
	exit -1;
}

/* start listen for connections */
socket_listen($sock);

/* list of all the clients that are connected to the server */
$clients = array($sock);

/* keep a buffer of commands and hosts that come into the sockets */
$commands = array();
$hosts = array();

/* give the debugging developer some indication that we are working */
boost_svr_log('Cacti RRDtool Service Started');

/* initially, we will block indefinately waiting for a client to connect. */
$wait_value = NULL;

/* initially, there are not rrdupdates in process */
$rrdupdates_in_process = array();

/* start and endless loop waiting for clients to connect and issue commands  */
while (true) {
	if (read_config_option('boost_server_multiprocess', TRUE)) {
		$multiprocess = TRUE;
	}else{
		$multiprocess = FALSE;
	}

	/* if there were call's to generate rrdupdates, then let's see if any finished */
	if (sizeof($rrdupdates_in_process)) {
		$finished_rrds = db_fetch_assoc('SELECT * FROM poller_output_boost_processes');

		if (sizeof($finished_rrds)) {
		foreach($finished_rrds as $finished_rrd) {
			/* remove the finished entry from the table */
			db_execute("DELETE FROM poller_output_boost_processes WHERE sock_int_value='" . $finished_rrd['sock_int_value'] . "'");

			/* send the output back to the cleint.  make sure we mask errors from broken connections */
			@socket_write($rrdupdates_in_process[$finished_rrd['sock_int_value']]['socket'], $finished_rrd['status'] . $eol, strlen($finished_rrd['status'] . $eol));

			if (substr_count($finished_rrd['status'], 'OK') == 0) {
				cacti_log($finished_rrd['status'], true, 'BOOST_SERVER');
			}else{
				echo date('Y:m:d H:i:s') . " - RRDUpdate OK Message: '" . $finished_rrd['status'] . "'" . $eol;
			}

			/* remove the item from the rrdupdates_in_process array.  make sure we mask errors from broken connections */
			unset($rrdupdates_in_process[$finished_rrd['sock_int_value']]);
		}
		}

		if (sizeof($rrdupdates_in_process)) {
			usleep(100000);
			$wait_value = 0;
		}else{
			$wait_value = NULL;
		}
	}

	/* initialize the rrdtool command if this is a single process boost server */
	global $rrdtool_pipe;
	if (!$multiprocess) {
		if (empty($rrdtool_pipe)) {
			$rrdtool_pipe = rrd_init();
		}
	}else{
		if (!empty($rrdtool_pipe)) {
			rrd_close($rrdtool_pipe);
		}
	}

	/* create a copy, so $clients doesn't get modified by socket_select() */
	$read = $clients;

	/* get a list of all the clients that have data to be read from */
	/* if there are no clients with data, go to next iteration      */
	/* in windows, we read one character at a time                  */
	if (socket_select($read, $write = NULL, $except = NULL, $wait_value) < 1)
		continue;

	/* re-connect to the database server */
	db_close();
	db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port);

	/* check if there is a client trying to connect */
	if (in_array($sock, $read)) {
		/* accept the client, and add him to the $clients array */
		$clients[] = $newsock = socket_accept($sock);

		socket_getpeername($newsock, $ip);
		boost_svr_log("Host Connected '$ip'");

		/* remove the listening socket from the clients-with-data array */
		$key = array_search($sock, $read);
		unset($read[$key]);

		/* store the peer's ip address in the hosts array */
		$key = array_search($newsock, $clients);
		$hosts[$key] = $ip;

		/* verify that the client is authorized */
		$valid_hosts = explode(',', read_config_option('boost_server_clients', TRUE));

		if (!in_array($ip, $valid_hosts)) {
			$connect_hostname = gethostbyaddr($ip);

			if (!in_array($connect_hostname, $valid_hosts)) {
				close_connection($newsock, "WARNING: Host '$ip' Not Authorized.");
			}else{
				boost_svr_log("Host Validated '$connect_hostname'");
			}
		}else{
			boost_svr_log("Host Validated '$ip'");
		}
	}

	/* loop through all the clients that have data to read from */
	foreach ($read as $read_sock) {
		if (!isset($command[intval($read_sock)])) {
			$command[intval($read_sock)] = '';
		}

		/* read until newline or 100k bytes */
		$data = @socket_read($read_sock, 100000);

		/* check if the client is disconnected */
		if (ord($data) == 0) {
			/* remove client for $clients array */
			close_connection($read_sock, 'WARNING: Broken connection detected');

			/* let's make sure we don't enter into a race conditiion waiting on an rrdupdate that
			 * may never happen.
			 */
			if (isset($rrdupdates_in_process[intval($read_sock)])) {
				unset($rrdupdates_in_process[intval($read_sock)]);
			}

			/* continue to the next client to read from, if any */
			continue;
		}else if (ord($data) == 3 || ord($data) == 17) {
			/* client is attempting to disconnect, so do it */
			/* write message to the socket and close */
			close_connection($read_sock, 'WARNING: Host broke connection');

			/* let's make sure we don't enter into a race conditiion waiting on an rrdupdate that
			 * may never happen.
			 */
			if (isset($rrdupdates_in_process[intval($read_sock)])) {
				unset($rrdupdates_in_process[intval($read_sock)]);
			}
		}else if (ord($data) == 13) {
			$response = run_command($read_sock,$command[intval($read_sock)], $multiprocess);
			$command[intval($read_sock)] = '';
		}else if (strlen($data) > 1) {
			$response = run_command($read_sock,$data, $multiprocess);
			$command[intval($read_sock)] = '';
		}else{
			$command[intval($read_sock)] .= $data;
		}
	}
}

/* close the rrdtool command if this is single process boost */
if (!read_config_option('boost_server_multiprocess')) {
	rrd_close($rrdtool_pipe);
}

/* close the listening socket */
socket_close($sock);

/*---------------------------------------------------------------------------*/
/* this function will perform the appropriate RRDtool command and then       */
/* continue.                                                                 */
/*---------------------------------------------------------------------------*/
function run_command($socket, $command, $multiprocess) {
	global $config, $eol, $rrdtool_pipe, $rrd_path, $php_binary_path, $rrd_update_path, $rrdupdates_in_process;

	$output = 'OK';

	/* process the command, don't accept bad commands */
	if (substr_count(strtolower($command), 'quit')) {
		close_connection($socket, 'Host Disconnect Request Received.');
		return 'OK';
	}elseif (substr_count(strtolower(substr($command,0,10)), 'update')) {
		/* ok to run */
	}elseif (substr_count(strtolower(substr($command,0,10)), 'graph')) {
		/* ok to run */
	}elseif (substr_count(strtolower(substr($command,0,10)), 'tune')) {
		/* ok to run */
	}elseif (substr_count(strtolower(substr($command,0,10)), 'create')) {
		/* ok to run, check for structured paths */
		if (read_config_option('extended_paths') == 'on') {
			$parts = explode(' ', $command);
			$data_source_path = $parts[1];
			if (!is_dir(dirname($data_source_path))) {
				if (mkdir(dirname($data_source_path), 0775)) {
					if ($config['cacti_server_os'] != 'win32') {
						$owner_id      = fileowner($config['rra_path']);
						$group_id      = filegroup($config['rra_path']);

						if ((chown(dirname($data_source_path), $owner_id)) &&
							(chgrp(dirname($data_source_path), $group_id))) {
							/* permissions set ok */
						}else{
							cacti_log("ERROR: Unable to set directory permissions for '" . dirname($data_source_path) . "'", FALSE);
						}
					}
				}else{
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", FALSE);
				}
			}
		}
	}elseif (substr_count(strtolower(substr($command,0,10)), 'status')) {
		close_connection($socket, 'Server Status OK');
		return 'OK';
	}else{
		close_connection($socket, "WARNING: Unknown RRD Command '" . $command . "' This activity will be logged!! Goodbye.");
		return 'OK';
	}

	boost_svr_log("RRD Command '" . $command . "'");

	/* update/create the rrd */
	if (!$multiprocess) {
		boost_rrdtool_execute_internal($command, false, RRDTOOL_OUTPUT_STDOUT, 'BOOST SERVER');
	}else{
		/* store the correct information in the array */
		$rrdupdates_in_process[intval($socket)]['socket'] = $socket;

		if ((strlen($rrd_update_path)) && (!substr_count($command, 'create '))) {
			$command = str_replace('update ', '', $command);
			exec_background($php_binary_path, 'boost_rrdupdate.php ' . intval($socket) . ' ' . $rrd_update_path . ' ' . $command);
		}else{
			exec_background($php_binary_path, 'boost_rrdupdate.php ' . intval($socket) . ' ' . $rrd_path . ' ' . $command);
		}
	}

	/* send the output back to the cleint if not multiprocess */
	if (!$multiprocess) {
		socket_write($socket, $output . $eol, strlen($output . $eol));
	}
}

/*---------------------------------------------------------------------------*/
/* this function logs entries to the log file                                */
/*---------------------------------------------------------------------------*/
function boost_svr_log($message) {
	global $log_file, $eol;

	if (strlen($message)) {
		if ((substr_count($message, 'ERROR')) ||
			(substr_count($message, 'WARNING')) ||
			(substr_count($message, 'FATAL')) ||
			(read_config_option('poller_verbosity') >= POLLER_VERBOSITY_HIGH)) {
			cacti_log($message, true, 'BOOST SERVER');
		}else{
			echo date('Y:m:d H:i:s') . ' - ' . $message . $eol;
		}
	}
}

/*---------------------------------------------------------------------------*/
/* this function will close a connection to a peer.  Plain and simple        */
/*---------------------------------------------------------------------------*/
function close_connection($socket, $message = '') {
	global $clients, $hosts;

	/* remove the socket from the connected clients list */
	$key = array_search($socket, $clients);
	unset($clients[$key]);

	/* echo the output message to the screen */
	if (strlen($message)) {
		boost_svr_log($message . " '" . $hosts[$key] . "'");
		@socket_write($socket, $message);
	}

	/* close the socket of the peer */
	socket_close($socket);

	/* remove the ip from the hosts list */
	unset($hosts[$key]);
}

