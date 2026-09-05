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

test('database reconnect contract propagates replacement handles', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/database.php');

	expect($source)->not->toBeFalse()
		->and($source)->toMatch('/function db_check_reconnect\s*\(\s*&\$db_conn\s*=\s*false/')
		->and($source)->toMatch('/if\s*\(\s*\$db_conn\s*!==\s*false\s*\)\s*{\s*\$db_conn\s*=\s*\$cnn_id;/s');
});

test('cactid passes a variable to the reference parameter', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/cactid.php');

	expect($source)->not->toBeFalse()
		->and($source)->not->toContain('db_check_reconnect(false, $logrecon)')
		->and($source)->toMatch('/\$reconnect_conn\s*=\s*false;\s*db_check_reconnect\(\$reconnect_conn,\s*\$logrecon\);/s');
});
