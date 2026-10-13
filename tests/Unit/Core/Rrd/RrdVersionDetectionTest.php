<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

function rrd_version_detection_fixture() {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/VersionDetection.php';
	$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture));

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

test('RRDtool detection preserves exact releases and selects supported installer values', function () {
	$result = rrd_version_detection_fixture();
	$expected = array(
		'1.3.0' => '1.3.0', '1.7.1' => '1.7.1', '1.7.2' => '1.7.2',
		'1.8.1' => '1.8.0', '1.9.0' => '1.9.0', '1.10.3' => '1.10.0',
		'1.11.0' => '1.11.0', '1.11.1' => '1.11.0', '1.12.0' => '1.11.0', '1.2.30' => false
	);
	foreach ($expected as $release => $supported) {
		expect($result['cases'][$release]['exact'])->toBe($release);
		expect($result['cases'][$release]['supported'])->toBe($supported);
		expect($result['cases'][$release]['commands'])->toBe(array("'/opt/RRD tools/" . $release . "/rrdtool' -v 2>&1"));
	}
});

test('failed and malformed RRDtool detection is cached without claiming capabilities', function () {
	$result = rrd_version_detection_fixture();
	foreach ($result['failures'] as $failure) {
		expect($failure)->toBe(array('first' => false, 'second' => false, 'probes' => 1));
	}
	// Failure below the supported floor stays false on subsequent calls too.
	$result = $result['cases']['1.2.30'];
	expect($result['supported'])->toBeFalse();
	expect($result['commands'])->toHaveCount(1);
});

test('RRDtool detection refreshes on force or executable changes and skips empty paths', function () {
	$result = rrd_version_detection_fixture();
	expect($result['retry'])->toBe(array(
		'failed' => false, 'cached_failure' => false, 'forced' => '1.11.0',
		'changed_path' => '1.10.3', 'probes' => 3
	));
	expect($result['empty'])->toBe(array('version' => false, 'probes' => 0));
});
