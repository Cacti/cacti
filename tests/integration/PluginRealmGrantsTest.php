<?php

test('CLI --allperms grants existing realms and reports permission failures (#8290)', function () {
	$process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=', __DIR__ . '/fixtures/plugin_realm_grants.php'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $output);
	expect($output)->toContain('"result":"passed"');
})->skip(getenv('CACTI_PLUGIN_GRANT_TEST') !== '1', 'Requires the disposable cacti_plugin_grant_test database; production functions are not stubbed.');
