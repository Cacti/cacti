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

/* Isolated MariaDB fixture for CdefDatabaseIntegrationTest.php. */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$host = getenv('CACTI_TEST_DB_HOST');
$port = getenv('CACTI_TEST_DB_PORT');
$name = getenv('CACTI_TEST_DB_NAME');
$user = getenv('CACTI_TEST_DB_USER');
$pass = getenv('CACTI_TEST_DB_PASS');

if ($host === false || $host === '' || $port === false || $port === '' || $name === false || $name === '' || $user === false || $user === '') {
	fwrite(STDERR, 'CdefDatabaseProbe: CACTI_TEST_DB_HOST, PORT, NAME, and USER are required.');
	exit(2);
}

$pass = $pass === false ? '' : $pass;

$GLOBALS['cdef_probe_connection'] = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, array(
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
));

function cdef_probe_query($sql, $params) {
	$statement = $GLOBALS['cdef_probe_connection']->prepare($sql);
	$statement->execute($params);

	return $statement;
}

function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	return cdef_probe_query($sql, $params)->fetch();
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	return cdef_probe_query($sql, $params)->fetchAll();
}

function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	return cdef_probe_query($sql, $params)->fetchColumn();
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	return true;
}

$conn = $GLOBALS['cdef_probe_connection'];
$conn->exec('CREATE TEMPORARY TABLE cdef (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
$conn->exec('CREATE TEMPORARY TABLE cdef_items (id INTEGER PRIMARY KEY, cdef_id INTEGER NOT NULL, sequence INTEGER NOT NULL, type VARCHAR(8) NOT NULL, value VARCHAR(150) NOT NULL)');
$conn->exec('CREATE TEMPORARY TABLE graph_templates_item (id INTEGER PRIMARY KEY, cdef_id INTEGER NOT NULL)');
$conn->exec('CREATE TEMPORARY TABLE aggregate_graph_templates_item (id INTEGER PRIMARY KEY, cdef_id INTEGER NOT NULL)');
$conn->exec('CREATE TEMPORARY TABLE aggregate_graphs_graph_item (id INTEGER PRIMARY KEY, cdef_id INTEGER NOT NULL)');
$conn->exec("INSERT INTO cdef (id, name) VALUES (1, 'Base Definition'), (2, 'Nested Definition'), (5, 'Empty Definition')");
$conn->exec("INSERT INTO cdef_items (id, cdef_id, sequence, type, value) VALUES
	(1, 1, 3, '2', '3'), (2, 1, 1, '4', 'CURRENT_DATA_SOURCE'), (3, 1, 2, '6', '8'),
	(4, 2, 1, '5', '1'), (5, 2, 2, '6', '2'), (6, 6, 1, '5', '6'),
	(7, 7, 1, '1', '999'), (8, 8, 1, '5', '1'), (9, 8, 2, '5', '1'),
	(10, 10, 1, '5', '999')");

$GLOBALS['cdef_functions'] = array(7 => 'Maximum');
$GLOBALS['cdef_operators'] = array(3 => '*');

require_once dirname(__DIR__, 2) . '/lib/cdef.php';

print json_encode(array(
	'item'    => get_cdef_item_name(4),
	'base'    => get_cdef(1),
	'nested'  => get_cdef(2),
	'empty'   => get_cdef(5),
	'missing' => get_cdef_item_name(999),
	'cycle'   => get_cdef(6),
	'invalid' => get_cdef(7),
	'diamond' => get_cdef(8),
	'missing_definition' => get_cdef_item_name(10),
	'in_use'             => cdef_is_in_use(1, array(1)),
	'deleting_group'     => cdef_is_in_use(1, array(1, 2, 8)),
), JSON_THROW_ON_ERROR);
