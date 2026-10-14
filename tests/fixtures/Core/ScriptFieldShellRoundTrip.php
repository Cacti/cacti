<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

define('CACTI_SERVER_OS', 'unix');
$config = array('cacti_server_os' => 'unix');
require_once dirname(__DIR__, 3) . '/lib/functions.php';

$values = array('hello world', '$(printf unexpected)', 'back`printf unexpected`tick', '"quoted"', "single'quote", 'dollar$HOME');
$count = 0;

foreach ($values as $value) {
	foreach (array('', "'", '"') as $quote) {
		for ($slashes = 0; $slashes <= 4; $slashes++) {
			$prefix   = str_repeat('\\', $slashes);
			$template = $quote . 'prefix' . $prefix . '<field>suffix' . $quote;
			$argument = substitute_script_path($template, array('field' => cacti_escapeshellarg($value)));
			$expected = 'prefix' . str_repeat('\\', $quote === "'" ? $slashes : intdiv($slashes, 2)) . $value . 'suffix';
			$pipes    = array();
			$process  = proc_open('printf %s ' . $argument, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

			if (!is_resource($process)) {
				throw new RuntimeException('Unable to start shell round-trip');
			}

			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$status = proc_close($process);

			if ($status !== 0 || $stderr !== '' || $stdout !== $expected) {
				throw new RuntimeException('Shell round-trip failed at case ' . $count);
			}

			$count++;
		}
	}
}

print json_encode(array('passed' => $count), JSON_THROW_ON_ERROR) . "\n";

