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

if (!defined('RRDTOOL_OUTPUT_STDOUT')) {
	define('RRDTOOL_OUTPUT_STDOUT', 1);
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', PHP_OS_FAMILY === 'Windows' ? 'win32' : 'unix');
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg(string $value) : string {
		return escapeshellarg(str_replace(["\r", "\n"], '', $value));
	}
}

require_once CACTI_PATH_LIBRARY . '/rrd.php';

/**
 * Execute RRDtool without a command shell.
 *
 * @param array<int, string> $arguments Complete process argument vector
 *
 * @return string|false Standard output, or false on process failure
 */
function rrd_mutation_run_process(array $arguments) : string|false {
	$process = proc_open(
		$arguments,
		[
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		],
		$pipes,
		null,
		null,
		['bypass_shell' => true]
	);

	if (!is_resource($process)) {
		return false;
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	return $status === 0 && $stdout !== false && $stderr === '' ? $stdout : false;
}

test('RRD XML mutation transaction restores through the real binary', function () {
	$binary = getenv('RRDTOOL_TEST_BINARY');

	if (!is_string($binary) || !is_executable($binary)) {
		$this->markTestSkipped('Set RRDTOOL_TEST_BINARY to an executable RRDtool binary.');
	}

	$directory = sys_get_temp_dir() . '/cacti-rrd-mutation-integration-' . bin2hex(random_bytes(6));
	mkdir($directory, 0700, true);
	$file = $directory . "/device's metrics.rrd";

	try {
		$created = rrd_mutation_run_process([
			$binary,
			'create',
			$file,
			'--start',
			'now-10s',
			'--step',
			'1',
			'DS:value:GAUGE:2:0:U',
			'RRA:AVERAGE:0.5:1:20'
		]);
		$dump = rrd_mutation_run_process([$binary, 'dump', $file]);
		$dom  = new DOMDocument();

		expect($created)->not->toBeFalse()
			->and($dump)->toBeString()
			->and($dom->loadXML($dump, LIBXML_NONET))->toBeTrue();

		$executor = static function (string $command) use ($binary) : string|false {
			if (!preg_match("/^restore -f '([^']+)' '([^']+)'$/", $command, $matches)) {
				return false;
			}

			return rrd_mutation_run_process([$binary, 'restore', '-f', $matches[1], $matches[2]]);
		};

		expect(rrdtool_restore_document($dom, $file, null, $executor))->toBeTrue()
			->and(rrd_mutation_run_process([$binary, 'info', $file]))->toBeString()
			->and(glob($directory . '/.cacti-rrd-restore-*'))->toBe([]);
	} finally {
		if (file_exists($file)) {
			unlink($file);
		}

		rmdir($directory);
	}
});
