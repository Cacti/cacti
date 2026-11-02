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
 * scripts/diskfree.pl and scripts/diskfree.sh pass their argument to df as
 * one argument after '--'. A missing argument, or one df could read as an
 * option, is answered with U values and a failing exit status instead of
 * running df. The scripts run with harmless arguments only.
 */

function diskfree_run(array $command) {
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

function diskfree_interpreter($name) {
	foreach (array('/usr/bin/', '/bin/') as $dir) {
		if (is_executable($dir . $name)) {
			return $dir . $name;
		}
	}

	return null;
}

$diskfree_scripts = array(
	'diskfree.pl' => array('perl', __DIR__ . '/../../../scripts/diskfree.pl'),
	'diskfree.sh' => array('sh',   __DIR__ . '/../../../scripts/diskfree.sh'),
);

foreach ($diskfree_scripts as $name => $script) {
	test("$name reports the root filesystem", function () use ($script) {
		$interpreter = diskfree_interpreter($script[0]);

		if ($interpreter === null) {
			$this->markTestSkipped($script[0] . ' is not installed');
		}

		/* diskfree.pl matches its argument against the df line, so it is
		 * given the device that holds '/', which must itself be a path */
		$argument = '/';

		if ($script[0] == 'perl') {
			list(, $df) = diskfree_run(array('df', '-k', '-P', '/'));
			$lines    = explode("\n", trim($df));
			$argument = strtok((string) end($lines), ' ');

			if (!preg_match('/^\/[\w.\/-]*$/', (string) $argument)) {
				$this->markTestSkipped("the root filesystem has no device path ('$argument')");
			}
		}

		list($status, $output) = diskfree_run(array($interpreter, $script[1], $argument));

		expect($status)->toBe(0);
		expect($output)->toMatch('/^megabytes:[0-9]+ percent:[0-9]+$/');
	});

	foreach (array('empty' => '', 'option' => '-a', 'long option' => '--version') as $label => $argument) {
		test("$name refuses an $label argument", function () use ($script, $argument) {
			$interpreter = diskfree_interpreter($script[0]);

			if ($interpreter === null) {
				$this->markTestSkipped($script[0] . ' is not installed');
			}

			list($status, $output) = diskfree_run(array($interpreter, $script[1], $argument));

			expect($status)->toBe(1);
			expect($output)->toBe('megabytes:U percent:U');
		});
	}
}

test('diskfree.pl refuses a path with characters outside the device name set', function () {
	$perl = diskfree_interpreter('perl');

	if ($perl === null) {
		$this->markTestSkipped('perl is not installed');
	}

	foreach (array('/ /', '/tmp;', '/$HOME', "/\n") as $argument) {
		list($status, $output) = diskfree_run(array($perl, __DIR__ . '/../../../scripts/diskfree.pl', $argument));

		expect($status)->toBe(1);
		expect($output)->toBe('megabytes:U percent:U');
	}
});

test('diskfree.pl runs df without a shell', function () {
	$source = file_get_contents(__DIR__ . '/../../../scripts/diskfree.pl');

	expect($source)->toContain("open(my \$process, '-|', 'df', '-k', '-P', '--', \$path)");
	expect($source)->toContain('\\Q$path\\E');
	expect($source)->not->toContain('$ARGV[0] |');
});
