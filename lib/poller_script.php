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

/*
 * Process and HTTP access for the scripts/ss_*.php data input scripts. These
 * run inside the long-lived script server, so nothing here may exit, print or
 * leave a pipe, stream or child process open.
 */

/**
 * Returns the directories searched for the system utilities the data input
 * scripts run. This is the PATH the Perl data input scripts set for
 * themselves, so a poller started with an unusual PATH finds the same tools.
 *
 * @return array<int,string>
 */
function poller_script_dirs() : array {
	return ['/usr/bin', '/bin', '/usr/sbin', '/sbin', '/usr/local/bin', '/usr/local/sbin'];
}

/**
 * Finds a program by its bare name in a fixed list of directories.
 *
 * @param string            $name A bare program name such as 'df'.
 * @param array<int,string> $dirs Directories to search in order, or
 *                                poller_script_dirs() when empty.
 *
 * @return string|null The absolute path, or null when none is executable.
 */
function poller_script_find(string $name, array $dirs = []) : ?string {
	if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
		return null;
	}

	foreach (($dirs === [] ? poller_script_dirs() : $dirs) as $dir) {
		$path = $dir . '/' . $name;

		if (is_file($path) && is_executable($path)) {
			return $path;
		}
	}

	return null;
}

/**
 * Runs a system utility without a shell and collects its output.
 *
 * The program is named, not given as a path, and is resolved through
 * poller_script_find(), so a caller cannot run a binary chosen by script
 * input. Arguments go to the child as an argument vector and are never
 * parsed by a shell. The child gets an empty stdin, the fixed search PATH and
 * LC_ALL=C, so its output does not depend on the poller's locale, and it is
 * killed if it is still running when the timeout expires. Returns null when
 * the program is missing or could not be started.
 *
 * @param string            $name    Bare program name, such as 'df'.
 * @param array<int,string> $args    Arguments passed to the program.
 * @param int               $timeout Seconds to wait before the child is killed.
 * @param array<int,string> $dirs    Directories to search for the program, or
 *                                   poller_script_dirs() when empty.
 *
 * @return array{stdout: string, stderr: string, pid: int, timed_out: bool}|null
 */
function poller_script_exec(string $name, array $args = [], int $timeout = 10, array $dirs = []) : ?array {
	$program = poller_script_find($name, $dirs);

	if ($program === null) {
		return null;
	}

	$descriptors = [
		0 => ['file', '/dev/null', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	];

	$env = [
		'PATH'   => implode(':', poller_script_dirs()),
		'LC_ALL' => 'C'
	];

	$command = array_merge([$program], array_map('strval', array_values($args)));
	$process = @proc_open($command, $descriptors, $pipes, null, $env);

	if (!is_resource($process)) {
		return null;
	}

	$pid       = proc_get_status($process)['pid'];
	$output    = [1 => '', 2 => ''];
	$open      = [1 => $pipes[1], 2 => $pipes[2]];
	$deadline  = hrtime(true) + $timeout * 1000000000;
	$timed_out = false;

	foreach ($open as $pipe) {
		stream_set_blocking($pipe, false);
	}

	while (count($open)) {
		$remaining = $deadline - hrtime(true);

		if ($remaining <= 0) {
			$timed_out = true;

			proc_terminate($process, 9);

			break;
		}

		$read   = array_values($open);
		$write  = null;
		$except = null;

		if (@stream_select($read, $write, $except, intdiv($remaining, 1000000000), intdiv($remaining % 1000000000, 1000)) === false) {
			break;
		}

		foreach ($open as $fd => $pipe) {
			if (!in_array($pipe, $read, true)) {
				continue;
			}

			$chunk = fread($pipe, 65536);

			if ($chunk !== false && $chunk !== '') {
				$output[$fd] .= $chunk;
			} elseif (feof($pipe)) {
				fclose($pipe);
				unset($open[$fd]);
			}
		}
	}

	foreach ($open as $pipe) {
		fclose($pipe);
	}

	proc_close($process);

	return [
		'stdout'    => $output[1],
		'stderr'    => $output[2],
		'pid'       => $pid,
		'timed_out' => $timed_out
	];
}

/**
 * Fetches a plain HTTP URL and returns the body of a 2xx response.
 *
 * Redirects are not followed, so a device cannot send the poller to another
 * host. The devices these scripts read sit on private addresses, which is why
 * cacti_http() and its private-range refusal do not fit here.
 *
 * @param string $url     An http:// URL.
 * @param int    $timeout Seconds allowed for the connection and each read.
 *
 * @return string|null The body, or null on a transport error or non-2xx status.
 */
function poller_script_http_get(string $url, int $timeout = 10) : ?string {
	if (!str_starts_with($url, 'http://')) {
		return null;
	}

	$context = stream_context_create([
		'http' => [
			'method'          => 'GET',
			'timeout'         => $timeout,
			'ignore_errors'   => true,
			'follow_location' => 0
		]
	]);

	$stream = @fopen($url, 'rb', false, $context);

	if ($stream === false) {
		return null;
	}

	stream_set_timeout($stream, $timeout);

	$body = stream_get_contents($stream);
	$meta = stream_get_meta_data($stream);

	fclose($stream);

	$status = 0;

	foreach ((array) ($meta['wrapper_data'] ?? []) as $header) {
		if (is_string($header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
			$status = (int) $matches[1];
		}
	}

	if ($body === false || $meta['timed_out'] || $status < 200 || $status > 299) {
		return null;
	}

	return $body;
}
