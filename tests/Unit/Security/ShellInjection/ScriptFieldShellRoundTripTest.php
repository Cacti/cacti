<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
*/

test('substituted shell fields retain literal values across quote and backslash contexts', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/ScriptFieldShellRoundTrip.php';
	$output  = [];
	$status  = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture), $output, $status);
	expect($status)->toBe(0);
	$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	expect($result['passed'])->toBe(126);
})->skip(stripos(PHP_OS, 'WIN') === 0 || !function_exists('exec') || !function_exists('proc_open'), 'POSIX shell required');
