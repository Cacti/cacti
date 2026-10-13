<?php

dataset('mutation request methods', function () {
	$root   = dirname(__DIR__, 4);
	$source = file_get_contents($root . '/include/layout.js');
	preg_match('/var actions = (\{.*?\});/s', $source, $match);
	$actions = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

	foreach ($actions as $file => $names) {
		foreach ($names as $action) {
			foreach (['GET', 'HEAD', 'PUT', 'POST'] as $method) {
				yield "$file $action $method" => [$file, $action, $method];
			}
		}
	}
});

test('mutation endpoint guards reject other methods before dispatch', function (string $file, string $action, string $method) {
	$root    = dirname(__DIR__, 4);
	$fixture = dirname(__DIR__, 2) . '/fixtures/mutation_guard_probe.php';
	$process = proc_open([PHP_BINARY, $fixture, $root, $file, $action, $method], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect($process)->not->toBeFalse();
	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);
	expect($error)->toBe('')->and($status)->toBe(0);
	$result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	expect($result)->toBe(['status' => $method === 'POST' ? 200 : 405, 'reached' => $method === 'POST']);
})->with('mutation request methods');

test('the web bootstrap validates CSRF tokens before page handlers dispatch', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');
	expect($source)->toContain('csrf_startup();');
	expect(strpos($source, 'csrf_startup();'))->toBeLessThan(strpos($source, '$bad_actions ='));
});
