<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

if (!defined('CACTI_LOCALE')) {
	define('CACTI_LOCALE', 'en-US');
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', PHP_OS_FAMILY === 'Windows' ? 'win32' : 'unix');
}

if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

foreach ([
	'RRDTOOL_OUTPUT_NULL'          => 0,
	'RRDTOOL_OUTPUT_STDOUT'        => 1,
	'RRDTOOL_OUTPUT_STDERR'        => 2,
	'RRDTOOL_OUTPUT_GRAPH_DATA'    => 3,
	'RRDTOOL_OUTPUT_BOOLEAN'       => 4,
	'RRDTOOL_OUTPUT_RETURN_STDERR' => 5
] as $constant => $value) {
	if (!defined($constant)) {
		define($constant, $value);
	}
}

if (!function_exists('cacti_session_close')) {
	function cacti_session_close() : void {
	}
}

require_once dirname(__DIR__, 2) . '/lib/rrd.php';

function integration_rrdtool_binary() : string {
	$binary = getenv('RRDTOOL_TEST_BINARY');

	return is_string($binary) ? $binary : '';
}

function integration_rrdtool_directory() : string {
	$directory = sys_get_temp_dir() . '/cacti-rrd-integration-' . bin2hex(random_bytes(6));

	if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create RRDtool integration directory.');
	}

	return $directory;
}

test('local RRDtool process acknowledges create update and fetch commands', function () {
	$binary = integration_rrdtool_binary();

	if ($binary === '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	global $unit_config_options;
	$unit_config_options['path_rrdtool']              = $binary;
	$unit_config_options['path_rrdtool_default_font'] = false;

	$directory = integration_rrdtool_directory();
	$rrd       = $directory . "/device's metrics.rrd";
	$png       = $directory . "/device's graph.png";
	$process   = __rrd_init();

	try {
		expect(rrdtool_is_process($process))->toBeTrue();

		$create = __rrd_execute(
			'create ' . escapeshellarg($rrd) . ' --start now-10s --step 1 DS:value:GAUGE:2:0:U RRA:AVERAGE:0.5:1:20',
			false,
			RRDTOOL_OUTPUT_STDOUT,
			$process,
			'TEST'
		);
		$update = __rrd_execute(
			'update ' . escapeshellarg($rrd) . ' N:7',
			false,
			RRDTOOL_OUTPUT_BOOLEAN,
			$process,
			'TEST'
		);
		$fetch = __rrd_execute(
			['fetch', $rrd, 'AVERAGE', '--start', 'now-2s'],
			false,
			RRDTOOL_OUTPUT_STDOUT,
			$process,
			'TEST'
		);
		$graph = __rrd_execute(
			'graph - --start now-10s DEF:value=' . escapeshellarg($rrd) . ':value:AVERAGE LINE1:value#00AA00',
			false,
			RRDTOOL_OUTPUT_GRAPH_DATA,
			$process,
			'TEST'
		);
		$graph_file = __rrd_execute(
			'graph ' . escapeshellarg($png) . ' --start now-10s DEF:value=' . escapeshellarg($rrd) . ':value:AVERAGE LINE1:value#00AA00',
			false,
			RRDTOOL_OUTPUT_BOOLEAN,
			$process,
			'TEST'
		);

		expect($create)->toBe('')
			->and($update)->toBeTrue()
			->and($fetch)->toBeString()->toContain('value')
			->and($graph)->toBeString()
			->and(substr($graph, 1, 3))->toBe('PNG')
			->and($graph_file)->toBeTrue()
			->and(file_exists($rrd))->toBeTrue()
			->and(file_exists($png))->toBeTrue();
	} finally {
		__rrd_close($process);

		if (file_exists($png)) {
			unlink($png);
		}

		if (file_exists($rrd)) {
			unlink($rrd);
		}

		rmdir($directory);
	}
});

test('local RRDtool process fails closed on errors and command framing characters', function () {
	$binary = integration_rrdtool_binary();

	if ($binary === '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	global $unit_config_options;
	$unit_config_options['path_rrdtool']              = $binary;
	$unit_config_options['path_rrdtool_default_font'] = false;
	$process                                          = __rrd_init();

	try {
		$error = __rrd_execute('not-a-real-command', false, RRDTOOL_OUTPUT_STDOUT, $process, 'TEST');
		$frame = rrdtool_process_command($process, "info /tmp/one\nquit");

		expect($error)->toBeFalse()
			->and($frame['success'])->toBeFalse()
			->and($frame['error'])->toContain('framing character')
			->and($process->alive)->toBeTrue();
	} finally {
		__rrd_close($process);
	}
});
