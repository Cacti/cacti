<?php

test('plugin realm registration at file scope is rejected without PHP warnings (#7968)', function () {
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/fixtures/Plugin/entrypoint_file_scope.php'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0, $output);
	expect($output)->toContain('rejected without warnings');
});
