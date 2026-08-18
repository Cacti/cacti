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

$queries = [];

/**
 * Captures a prepared automation query without requiring a database.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return array Empty result set.
 */
function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	global $queries;

	$queries[] = [$sql, $params];

	return [];
}

/**
 * Returns the size of an array probe value.
 *
 * @param mixed $value Value to count.
 *
 * @return int Number of elements.
 */
function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

if (!defined('CACTI_PATH_LIBRARY')) {
	require_once dirname(__DIR__, 2) . '/include/global_path.php';
}

require_once CACTI_PATH_LIBRARY . '/api_automation_tools.php';

getHostsByDescription([1, '2']);
getHosts(3);
getGraphTemplatesByHostTemplate(['4', 5]);

$valid_queries = $queries;
$queries       = [];

$invalid_results = [
	getHostsByDescription([1, 'bad']),
	getHosts('1e3'),
	getGraphTemplatesByHostTemplate('1 OR 1=1'),
];

print json_encode([
	'queries'             => $valid_queries,
	'invalid_results'     => $invalid_results,
	'invalid_query_count' => count($queries),
], JSON_THROW_ON_ERROR);
