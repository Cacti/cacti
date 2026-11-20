#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Test-only RRDtool protocol proxy. It delays update commands so an end-to-end test can terminate
 * a live Boost child at a deterministic point without changing production code.
 */

$arguments = $_SERVER['argv'];
array_shift($arguments);

if ($arguments !== array('-')) {
	$process = proc_open(
		array_merge(array('/usr/bin/rrdtool'), $arguments),
		array(0 => STDIN, 1 => STDOUT, 2 => STDERR),
		$pipes
	);

	exit(is_resource($process) ? proc_close($process) : 127);
}

$process = proc_open(array('/usr/bin/rrdtool', '-'), array(
	0 => array('pipe', 'r'),
	1 => array('pipe', 'w'),
	2 => array('redirect', 1),
), $pipes);

if (!is_resource($process)) {
	exit(127);
}

$delay = max(0, min(30000000, (int) (getenv('BOOST_E2E_RRD_DELAY_US') ?: 250000)));

while (($command = fgets(STDIN)) !== false) {
	if ($delay > 0 && preg_match('/^\s*update\s/', $command)) {
		usleep($delay);
	}

	if (fwrite($pipes[0], $command) === false) {
		break;
	}

	fflush($pipes[0]);

	while (($response = fgets($pipes[1])) !== false) {
		fwrite(STDOUT, $response);
		fflush(STDOUT);

		if (preg_match('/^(?:OK(?:\s|$)|ERROR:)/', $response)) {
			break;
		}
	}

	if (feof($pipes[1])) {
		break;
	}
}

fclose($pipes[0]);
fclose($pipes[1]);
exit(proc_close($process));
