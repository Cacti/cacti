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

/**
 * Centralized shell execution gateway.
 *
 * Every shell command in Cacti MUST route through cacti_exec() or
 * cacti_exec_background(). Direct calls to shell_exec(), system(),
 * exec(), passthru(), popen(), and proc_open() are banned outside
 * this file.
 */

/**
 * Execute a command as an argv array. Each element is escaped individually.
 *
 * @param  array       $argv    Command + arguments as separate array elements
 * @param  string      $output  Captured stdout (passed by reference)
 * @param  int         $retval  Exit code (passed by reference)
 * @param  int         $timeout Max execution seconds (0 = no limit)
 * @param  string|null $cwd     Working directory (null = inherit)
 *
 * @return string|false stdout on success, false on failure
 */
function cacti_exec(array $argv, &$output = '', &$retval = 0, $timeout = 0, $cwd = null) {
	if (empty($argv)) {
		cacti_log('ERROR: cacti_exec called with empty argv', false, 'SYSTEM');

		return false;
	}

	$cmd = '';

	foreach ($argv as $i => $arg) {
		$arg = (string) $arg;

		if ($i === 0) {
			$cmd = cacti_escapeshellcmd($arg);
		} else {
			$cmd .= ' ' . cacti_escapeshellarg($arg);
		}
	}

	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		$safe_cmd = cacti_exec_redact_cmd($cmd);
		cacti_log('DEBUG: cacti_exec: ' . $safe_cmd, false, 'SYSTEM', POLLER_VERBOSITY_DEBUG);
	}

	if ($timeout > 0) {
		return cacti_exec_with_timeout($cmd, $output, $retval, $timeout, $cwd);
	}

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	$process = proc_open($cmd, $descriptors, $pipes, $cwd);

	if (!is_resource($process)) {
		cacti_log('ERROR: cacti_exec: proc_open failed for: ' . cacti_exec_redact_cmd($cmd), false, 'SYSTEM');

		return false;
	}

	fclose($pipes[0]);

	$output = (string) stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	$retval = proc_close($process);

	if ($retval !== 0 && read_config_option('log_verbosity') >= POLLER_VERBOSITY_MEDIUM) {
		cacti_log('WARNING: cacti_exec: exit code ' . $retval . ' for: ' . cacti_exec_redact_cmd($cmd), false, 'SYSTEM');
	}

	return $output;
}

/**
 * Execute with a timeout via proc_open + stream_select.
 *
 * @param  string      $cmd     Pre-escaped command string
 * @param  string      $output  Captured stdout (passed by reference)
 * @param  int         $retval  Exit code (passed by reference)
 * @param  int         $timeout Max execution seconds
 * @param  string|null $cwd     Working directory (null = inherit)
 *
 * @return string|false stdout on success, false on timeout/failure
 */
function cacti_exec_with_timeout($cmd, &$output, &$retval, $timeout, $cwd = null) {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	$process = proc_open($cmd, $descriptors, $pipes, $cwd);

	if (!is_resource($process)) {
		return false;
	}

	fclose($pipes[0]);
	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);

	$output = '';
	$stderr = '';
	$start  = time();

	while (true) {
		$status = proc_get_status($process);

		if (!$status['running']) {
			$output .= stream_get_contents($pipes[1]);
			$stderr .= stream_get_contents($pipes[2]);
			$retval  = $status['exitcode'];

			break;
		}

		if (time() - $start > $timeout) {
			proc_terminate($process, 9);
			cacti_log('ERROR: cacti_exec: timeout after ' . $timeout . 's for: ' . cacti_exec_redact_cmd($cmd), false, 'SYSTEM');

			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);

			$retval = -1;

			return false;
		}

		$read   = array($pipes[1], $pipes[2]);
		$write  = null;
		$except = null;

		if (stream_select($read, $write, $except, 1) > 0) {
			foreach ($read as $pipe) {
				$data = fread($pipe, 8192);

				if ($pipe === $pipes[1]) {
					$output .= $data;
				} else {
					$stderr .= $data;
				}
			}
		}
	}

	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	return $output;
}

/**
 * Redact sensitive patterns from command strings before logging.
 *
 * Covers password/key/secret/token/auth flags and SNMP community strings.
 *
 * @param  string $cmd The command string to redact
 *
 * @return string The redacted command string
 */
function cacti_exec_redact_cmd($cmd) {
	/* redact long-form password/key/secret/token/auth flags */
	$cmd = preg_replace('/(--?(?:password|pass|key|secret|token|auth|community)[= ])\S+/i', '$1[REDACTED]', $cmd);

	/* redact SNMP -c community strings */
	$cmd = preg_replace('/(-c\s+)\S+/', '$1[REDACTED]', $cmd);

	return $cmd;
}

/**
 * cacti_exec_with_redirect - Execute a command with stdout/stderr file redirection.
 *
 * Builds the command from an argv array using cacti_escapeshellcmd/arg,
 * then runs it via proc_open with optional file descriptors for stdout
 * and stderr.
 *
 * @param  array       $argv         Command and arguments
 * @param  string|null $stdout_file  File path for stdout (null = pipe)
 * @param  string|null $stderr_file  File path for stderr (null = pipe)
 * @param  bool        $append       Append to files instead of truncating
 *
 * @return int|false  Exit code, or false on failure
 */
function cacti_exec_with_redirect(array $argv, $stdout_file = null, $stderr_file = null, $append = false) {
	if (empty($argv)) {
		cacti_log('ERROR: cacti_exec_with_redirect: empty argv', false, 'SYSTEM');
		return false;
	}

	$cmd = cacti_escapeshellcmd($argv[0]);

	for ($i = 1; $i < count($argv); $i++) {
		$cmd .= ' ' . cacti_escapeshellarg($argv[$i]);
	}

	$mode = $append ? 'a' : 'w';

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => $stdout_file !== null ? array('file', $stdout_file, $mode) : array('pipe', 'w'),
		2 => $stderr_file !== null ? array('file', $stderr_file, $mode) : array('pipe', 'w'),
	);

	$process = proc_open($cmd, $descriptors, $pipes);

	if (!is_resource($process)) {
		return false;
	}

	fclose($pipes[0]);

	if (!isset($descriptors[1][0]) || $descriptors[1][0] === 'pipe') {
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
	}

	if (!isset($descriptors[2][0]) || $descriptors[2][0] === 'pipe') {
		fclose($pipes[2]);
	}

	$retval = proc_close($process);

	return $retval;
}
