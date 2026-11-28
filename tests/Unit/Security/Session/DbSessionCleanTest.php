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
 * SessionHandlerInterface::gc() reports the number of sessions removed, or
 * false when the cleanup failed. The DbSessionCleanCoverage fixture runs the
 * real include/session.php with a stub database in its own process, since
 * including it registers the session handler, and prints each result so the
 * same run also feeds the changed-line coverage gate.
 */

function db_session_clean_results() : array {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/DbSessionCleanCoverage.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

test('session cleanup returns the number of sessions removed', function () {
	$results = db_session_clean_results();

	expect($results['non_count'])->toBe(3)
		->and($results['sql'])->toBeTrue();
});

test('the session handler gc() returns the cleanup result', function () {
	$results = db_session_clean_results();

	expect($results['removed'])->toBe(3)
		->and($results['failed'])->toBeFalse();
});
