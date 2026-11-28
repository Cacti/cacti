<?php

test('login throttling fails closed when a shared reservation is unavailable', function (string $mode) {
	$fixture = dirname(__DIR__) . '/fixtures/LoginThrottleAvailability.php';
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' ' . escapeshellarg($mode), $output, $status);
	expect($status)->toBe(0)->and($output)->toBe(['ok']);
})->with(['disconnected', 'invalid-address', 'begin', 'write']);
