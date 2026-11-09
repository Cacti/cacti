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
 * db_query_parallelize() refuses SQL it cannot split into map and reduce
 * queries before it touches the database, so these cases need no connection.
 */

require_once dirname(__DIR__, 2) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 3) . '/lib/dbparallel.php';

test('disallowed SQL is refused with an unsplit response', function (string $sql) {
	$response = db_query_parallelize($sql, false);

	expect($response['error'])->toBeTrue()
		->and($response['error_message'])->toContain('disallowed SQL options')
		->and($response['map_queries'])->toBe([])
		->and($response['reduce_query'])->toBe('')
		->and($response['temp_create_syntax'])->toBe('');
})->with([
	'json function' => ["SELECT JSON_EXTRACT(info, '$.a') FROM data_debug"],
	'index hint'    => ['SELECT id FROM host FORCE INDEX (PRIMARY) WHERE id > 1'],
	'partition'     => ['SELECT id FROM poller_output_boost PARTITION (p1)'],
]);

test('each refused query still names a bounded result table', function () {
	$first  = db_query_parallelize('SELECT JSON_KEYS(info) FROM data_debug', false);
	$second = db_query_parallelize('SELECT JSON_KEYS(info) FROM data_debug', false);

	expect($first['temp_table_name'])->toMatch('/^par_[0-9a-f]+_res$/')
		->and(strlen($first['temp_table_name']))->toBeLessThanOrEqual(40)
		->and($second['temp_table_name'])->not->toBe($first['temp_table_name']);
});
