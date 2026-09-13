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

require_once CACTI_PATH_LIBRARY . '/rrd.php';

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

function integration_fake_rrdtool_binary() : string {
	return __DIR__ . '/fixtures/fake_rrdtool_argv.sh';
}

function integration_rrdtool_unlink(string $path) : void {
	if (is_file($path)) {
		unlink($path);
	}
}

test('local RRDtool process acknowledges create update and fetch commands', function () {
	$binary = integration_rrdtool_binary();

	if ($binary === '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	global $config;
	$config[OPTIONS_CLI]['path_rrdtool']               = $binary;
	$config[OPTIONS_CLI]['path_rrdtool_default_font']  = false;

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

test('Cacti fetch excludes the RRDtool bucket after the requested CSV window', function () {
	$binary = integration_rrdtool_binary();

	if ($binary === '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	global $config;
	$had_local_storage = array_key_exists('local_storage', $config);
	$local_storage     = $config['local_storage'] ?? null;
	$had_rrdtool_path  = isset($config[OPTIONS_CLI]) && array_key_exists('path_rrdtool', $config[OPTIONS_CLI]);
	$rrdtool_path      = $config[OPTIONS_CLI]['path_rrdtool'] ?? null;
	$had_default_font  = isset($config[OPTIONS_CLI]) && array_key_exists('path_rrdtool_default_font', $config[OPTIONS_CLI]);
	$default_font      = $config[OPTIONS_CLI]['path_rrdtool_default_font'] ?? null;

	$config[OPTIONS_CLI]['path_rrdtool']              = $binary;
	$config[OPTIONS_CLI]['path_rrdtool_default_font'] = false;
	$config['local_storage']                          = true;
	$directory                           = integration_rrdtool_directory();
	$rrd                                 = $directory . '/csv-window.rrd';
	$start                               = 1700000100;
	$end                                 = $start + 900;

	try {
		$create = __rrd_execute(
			'create ' . escapeshellarg($rrd) . ' --start ' . ($start - 300) .
			' --step 300 DS:value:GAUGE:600:0:U RRA:AVERAGE:0.5:1:20',
			false,
			RRDTOOL_OUTPUT_STDOUT
		);
		$update = __rrd_execute(
			'update ' . escapeshellarg($rrd) . ' ' .
			$start . ':1 ' . ($start + 300) . ':2 ' . ($start + 600) . ':3 ' .
			' ' . $end . ':4 ' . ($end + 300) . ':5',
			false,
			RRDTOOL_OUTPUT_BOOLEAN
		);
		$fetch = rrdtool_function_fetch(0, $start, $end, 300, true, $rrd);
		$xport = __rrd_execute(
			'xport --start ' . $start . ' --end ' . $end . ' --maxrows 10000 ' .
			'DEF:value=' . escapeshellarg($rrd) . ':value:AVERAGE:step=300 XPORT:value',
			false,
			RRDTOOL_OUTPUT_STDOUT
		);

		expect($create)->toBe('')
			->and($update)->toBeTrue()
			->and($fetch['timestamp']['end_time'])->toBeLessThanOrEqual($end)
			->and($fetch['timestamp']['step'])->toBe(300)
			->and(array_keys($fetch['values'][0]))->not->toContain($end + 300)
			->and($xport)->toBeString()->toContain('<step>300</step>');
	} finally {
		integration_rrdtool_unlink($rrd);
		rmdir($directory);

		if ($had_local_storage) {
			$config['local_storage'] = $local_storage;
		} else {
			unset($config['local_storage']);
		}

		if ($had_rrdtool_path) {
			$config[OPTIONS_CLI]['path_rrdtool'] = $rrdtool_path;
		} else {
			unset($config[OPTIONS_CLI]['path_rrdtool']);
		}

		if ($had_default_font) {
			$config[OPTIONS_CLI]['path_rrdtool_default_font'] = $default_font;
		} else {
			unset($config[OPTIONS_CLI]['path_rrdtool_default_font']);
		}
	}
});

test('local RRDtool process fails closed on errors and command framing characters', function () {
	$binary = integration_rrdtool_binary();

	if ($binary === '' || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	global $config;
	$config[OPTIONS_CLI]['path_rrdtool']               = $binary;
	$config[OPTIONS_CLI]['path_rrdtool_default_font']  = false;
	$process                                           = __rrd_init();

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

test('RRDtool is launched with a fixed argv and never interprets command payloads in a shell', function () {
	$argvFile  = tempnam(sys_get_temp_dir(), 'cacti-rrd-argv-');
	$stdinFile = tempnam(sys_get_temp_dir(), 'cacti-rrd-stdin-');
	$marker    = sys_get_temp_dir() . '/cacti-rrd-shell-marker-' . getmypid();

	expect($argvFile)->toBeString()
		->and($stdinFile)->toBeString();

	integration_rrdtool_unlink($marker);
	putenv('FAKE_RRD_ARGV_FILE=' . $argvFile);
	putenv('FAKE_RRD_STDIN_FILE=' . $stdinFile);

	global $config;
	$config[OPTIONS_CLI]['path_rrdtool']               = integration_fake_rrdtool_binary();
	$config[OPTIONS_CLI]['path_rrdtool_default_font']  = false;
	$process                                           = __rrd_init();

	try {
		expect(rrdtool_is_process($process))->toBeTrue();

		$payload = "/tmp/value; touch $marker; \$(touch $marker); `touch $marker`";
		$result  = __rrd_execute(['info', $payload], false, RRDTOOL_OUTPUT_STDOUT, $process, 'TEST');
		$argv    = array_values(array_filter(explode("\n", (string) file_get_contents($argvFile))));
		$stdin   = (string) file_get_contents($stdinFile);

		expect($result)->toBe('')
			->and($argv)->toBe(['-'])
			->and($stdin)->toContain('info ')
			->and($stdin)->toContain('touch')
			->and(file_exists($marker))->toBeFalse();
	} finally {
		__rrd_close($process);
		putenv('FAKE_RRD_ARGV_FILE');
		putenv('FAKE_RRD_STDIN_FILE');
		integration_rrdtool_unlink($argvFile);
		integration_rrdtool_unlink($stdinFile);
		integration_rrdtool_unlink($marker);
	}
});

test('RRDtool stdout and stderr are drained concurrently under pipe pressure', function () {
	putenv('FAKE_RRD_STDOUT_BYTES=200000');
	putenv('FAKE_RRD_STDERR_BYTES=200000');

	global $config;
	$config[OPTIONS_CLI]['path_rrdtool']               = integration_fake_rrdtool_binary();
	$config[OPTIONS_CLI]['path_rrdtool_default_font']  = false;
	$process                                           = __rrd_init();

	try {
		expect(rrdtool_is_process($process))->toBeTrue();

		$result = rrdtool_process_command($process, 'info /tmp/metrics.rrd', 5.0);

		expect($result['success'])->toBeTrue()
			->and(strlen($result['output']))->toBeGreaterThanOrEqual(200000)
			->and(strlen($result['error']))->toBeGreaterThanOrEqual(200000);
	} finally {
		__rrd_close($process);
		putenv('FAKE_RRD_STDOUT_BYTES');
		putenv('FAKE_RRD_STDERR_BYTES');
	}
});
