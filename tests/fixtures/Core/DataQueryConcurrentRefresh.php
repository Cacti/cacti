<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Test\DataQuery;

// The parent records its own coverage; child processes must not overwrite it.
putenv('CACTI_COVERAGE_FILE');
require dirname(__DIR__, 2) . '/Helpers/DataQueryHarness.php';
connect_fixture();
$GLOBALS['dq_calls'] = 0;
$GLOBALS['dq_logs'] = array();
$GLOBALS['dq_pause_after_mark'] = ($argv[1] ?? '') === 'pause';
sql('SET SESSION innodb_lock_wait_timeout=3');
print sql('SELECT CONNECTION_ID()')->fetchColumn() . "\n";
fflush(STDOUT);
$records = array();
$label = $GLOBALS['dq_pause_after_mark'] ? 'first' : 'second';
for ($i = 1; $i <= 100; $i++) $records[] = data_query_format_record(1, 1, 'ifName', null, $label . $i, (string) $i, '');
$empty = array();
$result = data_query_update_host_cache_from_buffer(1, 1, $records, $empty);
print json_encode(array('result' => $result, 'transaction' => $GLOBALS['db']->inTransaction()), JSON_THROW_ON_ERROR) . "\n";
exit($result ? 0 : 1);
