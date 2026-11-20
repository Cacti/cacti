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

function boostProbeRun(array $arguments): array {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($arguments, $descriptors, $pipes);

	if (!is_resource($process)) {
		return array('status' => -1, 'stdout' => '', 'stderr' => 'proc_open failed');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return array('status' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr);
}

function boostProbeRemoveTree($path): void {
	if (is_link($path) || is_file($path)) {
		unlink($path);

		return;
	}

	if (!is_dir($path)) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach($iterator as $entry) {
		if ($entry->isLink() || !$entry->isDir()) {
			unlink($entry->getPathname());
		} else {
			rmdir($entry->getPathname());
		}
	}

	rmdir($path);
}

test('probe cleanup unlinks symlink pivots without traversing their targets', function () {
	if (PHP_OS_FAMILY === 'Windows') {
		$this->markTestSkipped('POSIX symlink semantics are required');
	}

	$root = sys_get_temp_dir() . '/cacti-boost-clean-' . bin2hex(random_bytes(8));
	$outside = sys_get_temp_dir() . '/cacti-boost-clean-outside-' . bin2hex(random_bytes(8));
	mkdir($root, 0700);
	mkdir($outside, 0700);
	file_put_contents($outside . '/keep', 'outside');

	try {
		if (!symlink($outside, $root . '/pivot')) {
			$this->markTestSkipped('Unable to create a symlink fixture');
		}

		boostProbeRemoveTree($root);

		expect(file_exists($root))->toBeFalse()
			->and(is_link($root))->toBeFalse()
			->and(file_get_contents($outside . '/keep'))->toBe('outside');
	} finally {
		boostProbeRemoveTree($root);
		boostProbeRemoveTree($outside);
	}
});

test('real RRD benchmark rejects stale timestamps instead of counting skipped samples', function () {
	$rrdtool = trim((string) shell_exec('command -v rrdtool 2>/dev/null'));

	if ($rrdtool === '' || !extension_loaded('pcntl')) {
		$this->markTestSkipped('rrdtool and pcntl are required');
	}

	$root = sys_get_temp_dir() . '/cacti-boost-bench-' . bin2hex(random_bytes(8));
	$script = dirname(__DIR__, 3) . '/performance/boost_archive/rrd_write.php';
	$arguments = array(
		'/usr/bin/env',
		'BOOST_BENCH_RRDTOOL=' . $rrdtool,
		PHP_BINARY,
		$script,
		'--rrd-dir=' . $root,
		'--sources=2',
		'--samples=2',
		'--samples-per-command=2',
		'--workers=1',
		'--base-time=1800000000',
	);

	try {
		$first = boostProbeRun(array_merge($arguments, array('--prepare')));
		$first_result = json_decode($first['stdout'], true, 512, JSON_THROW_ON_ERROR);

		expect($first['status'])->toBe(0, $first['stderr'])
			->and($first_result['valid'])->toBeTrue()
			->and($first_result['successful_samples'])->toBe(4)
			->and($first_result['failed_commands'])->toBe(0);

		$marker_path = $root . '/.cacti-boost-rrd-benchmark';
		$marker = json_decode((string) file_get_contents($marker_path), true, 512, JSON_THROW_ON_ERROR);
		$marker['last_update'] = 1799999940;
		file_put_contents($marker_path, json_encode($marker, JSON_THROW_ON_ERROR));

		$stale = boostProbeRun($arguments);
		$stale_result = json_decode($stale['stdout'], true, 512, JSON_THROW_ON_ERROR);

		expect($stale['status'])->toBe(1)
			->and($stale['stderr'])->toContain('stale, overlapping, or failed')
			->and($stale_result['valid'])->toBeFalse()
			->and($stale_result['successful_samples'])->toBe(0)
			->and($stale_result['failed_commands'])->toBe(2);
	} finally {
		boostProbeRemoveTree($root);
	}
});

test('the reusable RRDtool worker acknowledges successes and exposes rejected updates', function () {
	$rrdtool = trim((string) shell_exec('command -v rrdtool 2>/dev/null'));

	if ($rrdtool === '') {
		$this->markTestSkipped('rrdtool is not installed');
	}

	$root = sys_get_temp_dir() . '/cacti-boost-ack-' . bin2hex(random_bytes(8));
	mkdir($root, 0700);
	$probe = dirname(__DIR__, 3) . '/fixtures/Core/BoostRrdAcknowledgementProbe.php';

	try {
		$result = boostProbeRun(array(PHP_BINARY, $probe, $rrdtool, $root . '/sample.rrd'));
		$responses = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

		expect($result['status'])->toBe(0, $result['stderr'])
			->and($responses)->toHaveCount(4)
			->and($responses[0])->toStartWith('OK ')
			->and($responses[1])->toStartWith('OK ')
			->and($responses[2])->toContain('illegal attempt to update')
			->and($responses[3])->toStartWith('OK ');
	} finally {
		boostProbeRemoveTree($root);
	}
});
test('structured RRD paths preserve inherited group and setgid through a restrictive umask', function () {
	if (PHP_OS_FAMILY === 'Windows') {
		$this->markTestSkipped('POSIX permission semantics are required');
	}

	$root  = sys_get_temp_dir() . '/cacti-boost-mode-' . bin2hex(random_bytes(8));
	$probe = dirname(__DIR__, 3) . '/fixtures/Core/BoostStructuredPermissionsProbe.php';
	mkdir($root, 0700);

	try {
		$result = boostProbeRun(array(PHP_BINARY, $probe, $root));
		$state = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

		expect($result['status'])->toBe(0, $result['stderr'])
			->and($state['created'])->toBeTrue()
			->and($state['permissions_ok'])->toBeTrue()
			->and($state['nested_group'])->toBe($state['expected_group'])
			->and($state['file_group'])->toBe($state['expected_group'])
			->and($state['file_mode'] & 0060)->toBe(0060)
			->and($state['file_permissions'])->toBeTrue();

		if (PHP_OS_FAMILY !== 'Darwin') {
			expect($state['nested_mode'] & 02000)->toBe(02000);
		}
	} finally {
		boostProbeRemoveTree($root);
	}
});

test('structured RRD validation rejects traversal prefix confusion and symlink pivots', function () {
	$root  = sys_get_temp_dir() . '/cacti-boost-path-' . bin2hex(random_bytes(8));
	$probe = dirname(__DIR__, 3) . '/fixtures/Core/BoostRrdPathValidationProbe.php';
	mkdir($root, 0700);

	try {
		$result = boostProbeRun(array(PHP_BINARY, $probe, $root));
		$state = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

		expect($result['status'])->toBe(0, $result['stderr'])
			->and($state['invalid_empty'])->toBeFalse()
			->and($state['missing_base'])->toBeFalse()
			->and($state['must_exist'])->toBeFalse()
			->and($state['existing_base'])->toBeTrue()
			->and($state['valid_missing'])->toBeTrue()
			->and($state['dotdot'])->toBeFalse()
			->and($state['prefix'])->toBeFalse()
			->and($state['create_outside'])->toBeFalse()
			->and($state['create_existing'])->toBeTrue()
			->and($state['file_missing']['result'])->toBeFalse()
			->and($state['file_missing']['message'])->toContain('does not exist')
			->and($state['file_missing']['level'])->toBe(3)
			->and($state['file_no_base']['result'])->toBeFalse()
			->and($state['file_no_base']['message'])->toContain('No RRA path was given')
			->and($state['file_no_base']['level'])->toBe('');

		/* only a not-yet-created RRD is downgraded; every other rejection is
		 * logged at the default level with its own reason */
		foreach (array('file_dotdot', 'file_outside', 'file_pivot') as $case) {
			if ($state[$case] !== null) {
				expect($state[$case]['result'])->toBeFalse($case)
					->and($state[$case]['message'])->toContain('is not a valid path below the RRA path')
					->and($state[$case]['level'])->toBe('', $case);
			}
		}

		expect($state['file_dir']['result'])->toBeFalse()
			->and($state['file_dir']['message'])->toContain('is not a regular file')
			->and($state['file_dir']['level'])->toBe('');

		if ($state['symlink'] !== null) {
			expect($state['symlink'])->toBeFalse()
				->and($state['file_symlink']['result'])->toBeFalse()
				->and($state['file_symlink']['message'])->toContain('is a symbolic link')
				->and($state['file_symlink']['level'])->toBe('');
		}
	} finally {
		boostProbeRemoveTree($root);
	}
});
