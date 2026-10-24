<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs tests/fixtures/ScriptServerHardening.php in its own process. The
 * fixture drives the script server request reader, dispatch containment,
 * argument parsing and credential masking, the poller writer limit, and the
 * hardened poller scripts, and reports every failed check by name.
 */

test('script server and poller script hardening holds in a separate process', function ($mode) {
	$fixture = dirname(__DIR__, 2) . '/fixtures/ScriptServerHardening.php';

	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' ' . escapeshellarg($mode), $output, $status);

	$result = json_decode((string) end($output), true, 512, JSON_THROW_ON_ERROR);

	expect($result['failures'])->toBe(array());
	expect($result['mode'])->toBe($mode);
	expect($result['checks'])->toBeGreaterThan(70);
	expect($status)->toBe(0);
})->with(array('io', 'bytes'));
