<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$databaseSource = file_get_contents(dirname(__DIR__, 2) . '/lib/database.php');

test('the commit path does not depend on the MariaDB in_transaction variable', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_commit_transaction(');
	$end   = strpos($databaseSource, "\nfunction ", $start + 1);
	$body  = substr($databaseSource, $start, $end - $start);

	// MySQL answers "Unknown system variable 'in_transaction'", so the guard was
	// always false there and the commit never ran.
	expect($body)->not->toContain("SELECT @@in_transaction")
		->and($body)->toContain('inTransaction()');
});

test('PDO reports transaction state identically regardless of engine', function () {
	$pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

	expect($pdo->inTransaction())->toBeFalse();

	$pdo->beginTransaction();
	expect($pdo->inTransaction())->toBeTrue();

	expect($pdo->commit())->toBeTrue()
		->and($pdo->inTransaction())->toBeFalse();
});
