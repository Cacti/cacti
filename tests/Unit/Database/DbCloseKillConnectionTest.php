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
 * db_close() forcibly disconnects non-persistent connections by issuing
 * 'KILL CONNECTION CONNECTION_ID()'. The old code called the nonexistent
 * MySQL function CONNECTIION_ID() (extra "I"), so every non-persistent
 * db_close() emitted a SQL error. Structural check: the file must contain
 * the corrected function name and must not contain the typo'd one.
 */

test('db_close() issues the corrected KILL CONNECTION statement', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/database.php');

	expect($src)->toContain("db_execute('KILL CONNECTION CONNECTION_ID()', false, \$db_conn)");
});

test('db_close() no longer contains the CONNECTIION_ID typo', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/database.php');

	expect($src)->not->toContain('CONNECTIION_ID');
});
