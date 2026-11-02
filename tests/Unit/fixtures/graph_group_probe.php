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

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, sequence INTEGER, local_graph_id INTEGER, graph_template_id INTEGER, graph_type_id INTEGER, text_format TEXT, hard_return TEXT)');
$insert = $database->prepare('INSERT INTO graph_templates_item VALUES (?, ?, ?, ?, ?, ?, ?)');

foreach ([
	[101, 7, 42, 5, 4, 'Series', ''],
	[102, 8, 42, 5, 1, '|95:bytes:0:current:2|', ''],
	[103, 9, 42, 5, 1, '|sum:0:current:2:auto|', ''],
	[104, 10, 42, 5, 9, 'Last', 'on'],
	[105, 11, 42, 5, 9, 'Next group', ''],
	[201, 8, 7, 5, 9, 'Another graph', ''],
	[301, 7, 0, 9, 4, 'Template series', ''],
	[302, 8, 0, 9, 9, 'Template last', 'on'],
	[303, 9, 0, 10, 9, 'Another template', ''],
] as $row) {
	$insert->execute($row);
}
function db_fetch_row_prepared($sql, $params = []) {
	global $database;
	$statement = $database->prepare($sql);
	$statement->execute($params);

	return $statement->fetch(PDO::FETCH_ASSOC);
}
function db_fetch_assoc_prepared($sql, $params = []) {
	global $database;
	$statement = $database->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}
require_once dirname(__DIR__, 3) . '/lib/functions.php';
$graph_item_types = [1 => 'COMMENT', 4 => 'LINE1', 9 => 'GPRINT'];
$groups           = [
	'instance'   => array_values(get_graph_group(101)),
	'template'   => array_values(get_graph_group(301)),
	'non_parent' => array_values(get_graph_group(104)),
];

if ($groups !== ['instance' => [101, 102, 103, 104], 'template' => [301, 302], 'non_parent' => []]) {
	throw new RuntimeException('Graph grouping mixed identities or crossed its group boundary: ' . json_encode($groups));
}
print json_encode($groups, JSON_THROW_ON_ERROR);
