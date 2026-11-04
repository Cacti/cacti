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
	if (!is_dir($path)) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach($iterator as $entry) {
		$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
	}

	rmdir($path);
}

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
			->and($state['file_missing'])->toBeFalse()
			->and($state['file_no_base'])->toBeFalse();

		if ($state['symlink'] !== null) {
			expect($state['symlink'])->toBeFalse();
		}
	} finally {
		boostProbeRemoveTree($root);
	}
});
