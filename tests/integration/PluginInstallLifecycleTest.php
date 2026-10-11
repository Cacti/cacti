<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software under the GNU General Public License v2  |
 | or later.                                                             |
 +-------------------------------------------------------------------------+
*/

test('plugin install failures propagate through the real API and CLI without enabling hooks or granting permissions', function () {
	$process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=', __DIR__ . '/fixtures/plugin_install_lifecycle.php'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $output);
	expect($output)->toContain('"result":"passed"');
})->skip(getenv('CACTI_PLUGIN_INSTALL_TEST') !== '1', 'Requires the disposable cacti_plugin_test database; production functions are not stubbed.');
