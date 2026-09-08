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

test('CDEF resolution executes against MariaDB with production table shapes', function () : void {
	if (getenv('CACTI_CDEF_REAL_DB') !== '1') {
		$this->markTestSkipped('Set CACTI_CDEF_REAL_DB=1 to run the MariaDB integration test.');
	}

	$path = dirname(__DIR__) . '/Helpers/CdefDatabaseProbe.php';
	$proc = proc_open([PHP_BINARY, $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($proc)) {
		throw new RuntimeException('failed to spawn CdefDatabaseProbe.php');
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($proc);

	expect($exit)->toBe(0, $stderr);
	$results = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

	expect($results)->toBe([
		'item'               => 'Base Definition',
		'base'               => 'CURRENT_DATA_SOURCE,8,*',
		'nested'             => 'CURRENT_DATA_SOURCE,8,*,2',
		'empty'              => '',
		'missing'            => null,
		'cycle'              => null,
		'invalid'            => null,
		'diamond'            => 'CURRENT_DATA_SOURCE,8,*,CURRENT_DATA_SOURCE,8,*',
		'missing_definition' => null,
		'in_use'             => true,
		'deleting_group'     => false,
	]);
});
