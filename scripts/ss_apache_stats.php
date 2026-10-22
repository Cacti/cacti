<?php

/**
 * Original Template by ApacheStats 0.8.2 by RCK - http://forums.cacti.net/about25227.html
 * Tested and packaged for Cacti 1.2.x  by Sean Mancini - bmfmancini - www.seanmancini.com
 */

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');
	include_once(__DIR__ . '/../lib/snmp.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_apache_stats', $_SERVER['argv']);
}

function ss_apache_stats(string $host = '', string $section = 'all') : mixed {
	// perform initial error checking
	if (empty($host)) {
		cacti_log('ERROR: ApacheStats08 - Host parameter missing, can not continue');

		return 'U';
	}

	$authority = ss_apache_stats_authority($host);

	if ($authority === false) {
		cacti_log('ERROR: ApacheStats08 - Host parameter is not a valid hostname or IP address');

		return 'U';
	}

	$result = ss_apache_stats_fetch('http://' . $authority . '/server-status?auto');

	if ($result === false || $result === '') {
		return 'U';
	}

	return ss_apache_stats_parse($result, $section);
}

/**
 * ss_apache_stats_authority - validate the host argument and build the URL authority
 *
 * The host is placed into a URL, so anything that could change the URL's
 * meaning, such as a path, query, fragment or user info, is refused.
 *
 * @param string $host A hostname or IP address, optionally followed by :port
 *
 * @return string|false The authority, with IPv6 addresses bracketed, or false
 */
function ss_apache_stats_authority(string $host) : string|false {
	if ($host === '' || preg_match('/[\/?#@\\\\\s]/', $host)) {
		return false;
	}

	// a bare IPv6 address carries no port
	if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
		return '[' . $host . ']';
	}

	if (preg_match('/^\[([0-9A-Fa-f:.]+)\](?::(\d{1,5}))?$/', $host, $matches)) {
		$name = $matches[1];
		$port = $matches[2] ?? '';

		if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
			return false;
		}

		$name = '[' . $name . ']';
	} elseif (preg_match('/^([A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?)(?::(\d{1,5}))?$/', $host, $matches)) {
		$name = $matches[1];
		$port = $matches[2] ?? '';
	} else {
		return false;
	}

	if ($port !== '' && ((int) $port < 1 || (int) $port > 65535)) {
		return false;
	}

	return $name . ($port !== '' ? ':' . $port : '');
}

/**
 * ss_apache_stats_fetch - read the server-status page
 *
 * @param string $url The server-status URL
 *
 * @return string|false The page, at most 64 KiB of it, or false on failure
 */
function ss_apache_stats_fetch(string $url) : string|false {
	$context = stream_context_create([
		'http' => [
			'timeout'         => 5,
			'follow_location' => 0,
			'max_redirects'   => 0
		]
	]);

	return @file_get_contents($url, false, $context, 0, 65536);
}

/**
 * ss_apache_stats_parse - turn a server-status page into Cacti output
 *
 * @param string $result  The server-status ?auto page
 * @param string $section The section to report, or 'all'
 *
 * @return string The name:value pairs, or 'U' when the page held no status
 */
function ss_apache_stats_parse(string $result, string $section) : string {
	$variables = ['apache_total_hits', 'apache_total_kbytes', 'apache_busy_workers', 'apache_idle_workers', 'apache_cpuload'];
	$threads   = ['_W' => 0, 'S' => 0, 'R'=>0, 'W'=>0, 'K'=>0, 'D'=>0, 'C'=>0, 'L'=>0, 'G'=>0, 'I'=>0, '_O'=>0];
	/*	***Types of Threads***
		_W --- waiting
		S  --- starting up
		R  --- reading request
		W  --- sending reply
		K  --- keepalive
		D  --- DNS lookup
		C  --- closing connection
		L  --- logging
		G  --- graceful finishing
		I  --- idle cleanup
		_O --- open slot
	*/
	$fields = [
		'Total Accesses' => [0, 'hits'],
		'Total kBytes'   => [1, 'kbytes'],
		'BusyServers'    => [2, 'workers'],
		'BusyWorkers'    => [2, 'workers'],
		'IdleServers'    => [3, 'workers'],
		'IdleWorkers'    => [3, 'workers'],
		'CPULoad'        => [4, 'cpuload']
	];

	$output = '';
	$found  = false;

	foreach (explode("\n", $result) as $newline) {
		$line = explode(':', $newline, 2);

		if (!isset($line[1])) {
			continue;
		}

		$key   = $line[0];
		$value = trim($line[1]);

		if (isset($fields[$key])) {
			$found = true;

			[$position, $name] = $fields[$key];

			if (($section == $name) || ($section == 'all')) {
				// the page comes from the monitored server, so only numbers pass through
				$output .= $variables[$position] . ':' . (is_numeric($value) ? $value : 'U') . ' ';
			}
		} elseif ($key == 'Scoreboard') {
			$found  = true;
			$length = strlen($value);

			for ($j = 0; $j < $length; $j++) {
				$char = $value[$j];

				if ($char == '_') {
					$threads['_W']++;
				} elseif ($char == '.') {
					$threads['_O']++;
				} elseif (isset($threads[$char])) {
					$threads[$char]++;
				}
			}
		}
	}

	if (!$found) {
		return 'U';
	}

	if (($section == 'threads') || ($section == 'all')) {
		foreach ($threads as $type => $num_threads) {
			$output .= 'thread' . $type . ':' . $num_threads . ' ';
		}
	}

	return trim(str_replace(["\r", "\n"], '', $output));
}
