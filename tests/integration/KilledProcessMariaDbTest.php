<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('process registration upgrades safely and kills only its owned database connection', function () {
	$output = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/fixtures/Core/Poller/KilledProcessMariaDb.php') . ' 2>&1', $output, $status);
	expect($status)->toBe(0, implode("\n", $output));
	expect(json_decode(implode("\n", $output), true))->toBe(['passed' => 14]);
})->skip(!getenv('PROCESS_DB_HOST'), 'Set PROCESS_DB_HOST to a disposable server with CREATE DATABASE and CREATE USER permission');
