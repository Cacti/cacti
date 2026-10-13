<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

test('SpikeKill requires POST before any non-dry-run RRD mutation', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/spikekill.php');
	expect($source)->toMatch("/if \(!(?:isset_request_var|isrv)\('dryrun'\)\) \{\s*csrf_require_post\(\);\s*\}/");
	expect(strpos($source, 'csrf_require_post();'))->toBeLessThan(strpos($source, '$spiker->remove_spikes()'));
});

test('SpikeKill preference writes require POST before dispatch', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/html.php');
	expect($source)->toMatch("/case 'spikesave':\s*csrf_require_post\(\);/");
});

test('the actual SpikeKill request guards reject unsafe methods before dispatch', function ($method, $mode, $route, $status) {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/SpikekillRequestGate.php';
	$process = proc_open([PHP_BINARY, $fixture, $method, $mode, $route], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe(['status' => $status, 'continued' => $status === 200]);
})->with([
	['GET', '', 'mutation', 405],
	['GET', 'dryrun', 'mutation', 200],
	['POST', '', 'mutation', 200],
	['PUT', '', 'mutation', 405],
	['DELETE', '', 'mutation', 405],
	['GET', '', 'settings', 405],
	['POST', '', 'settings', 200],
]);
