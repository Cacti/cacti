<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// SECRET_DB_HOST/PORT/USER/PASSWORD select a disposable MariaDB server.
// The account needs CREATE DATABASE; the fixture removes its unique database.
test('complete secret save handlers persist and replicate keep replace and clear values', function () {
	$output = [];
	$status = 0;
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/fixtures/SecretFieldSave.php') . ' 2>&1', $output, $status);
	expect($status)->toBe(0, implode("\n", $output));
	expect(json_decode(implode("\n", $output), true))->toBe(['passed' => getenv('SECRET_LDAP_URI') ? 44 : 39]);
})->skip(!getenv('SECRET_DB_HOST'), 'Set SECRET_DB_HOST to a disposable MariaDB server');
