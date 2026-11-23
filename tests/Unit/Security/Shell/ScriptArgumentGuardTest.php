<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ping.pl, scripts/unix_users.pl and scripts/linux_memory.pl put
 * their argument on a command line. An argument the script does not accept,
 * including one the command could read as an option, is answered with U and
 * a failing exit status instead of running the command. The scripts run with
 * harmless arguments only.
 */

function script_argument_run(array $command) {
	$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (!is_resource($process)) {
		return array(null, '');
	}

	$output = stream_get_contents($pipes[1]);
	stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return array(proc_close($process), $output);
}

function script_argument_perl() {
	foreach (array('/usr/bin/perl', '/bin/perl') as $perl) {
		if (is_executable($perl)) {
			return $perl;
		}
	}

	return null;
}

function script_argument_path($name) {
	return __DIR__ . '/../../../../scripts/' . $name;
}

$script_argument_refused = array(
	'ping.pl' => array(
		'none'          => null,
		'empty'         => '',
		'option'        => '-f',
		'long option'   => '--version',
		'two words'     => '127.0.0.1 -f',
		'command text'  => '127.0.0.1;id',
	),
	'unix_users.pl' => array(
		'option'        => '-v',
		'two words'     => 'root -v',
		'command text'  => 'root;id',
		'dotted'        => 'first.last',
	),
	'linux_memory.pl' => array(
		'none'          => null,
		'empty'         => '',
		'option'        => '-r',
		'two words'     => 'MemFree: /etc/hostname',
		'command text'  => 'MemFree:;id',
	),
);

foreach ($script_argument_refused as $name => $arguments) {
	foreach ($arguments as $label => $argument) {
		test("$name answers U for a refused argument ($label)", function () use ($name, $argument) {
			$perl = script_argument_perl();

			if ($perl === null) {
				$this->markTestSkipped('perl is not installed');
			}

			$command = array($perl, script_argument_path($name));

			if ($argument !== null) {
				$command[] = $argument;
			}

			[$status, $output] = script_argument_run($command);

			expect($status)->toBe(1);
			expect($output)->toBe('U');
		});
	}
}

test('unix_users.pl still counts all users or one user', function () {
	$perl = script_argument_perl();

	if ($perl === null) {
		$this->markTestSkipped('perl is not installed');
	}

	foreach (array(array(), array(''), array('root')) as $arguments) {
		[$status, $output] = script_argument_run(array_merge(array($perl, script_argument_path('unix_users.pl')), $arguments));

		expect($status)->toBe(0);
		expect($output)->toMatch('/^[0-9]+$/');
	}
});

test('linux_memory.pl still reports a key it accepts', function () {
	$perl = script_argument_perl();

	if ($perl === null) {
		$this->markTestSkipped('perl is not installed');
	}

	if (!is_readable('/proc/meminfo')) {
		$this->markTestSkipped('/proc/meminfo is not available');
	}

	[$status, $output] = script_argument_run(array($perl, script_argument_path('linux_memory.pl'), 'MemTotal:'));

	expect($status)->toBe(0);
	expect($output)->toMatch('/^[0-9]+$/');
});
