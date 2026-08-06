<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 * Returns the size of a countable probe value.
 *
 * @param mixed $value Value to count.
 *
 * @return int Number of elements.
 */
function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

require_once dirname(__DIR__, 2) . '/lib/api_automation_tools.php';

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
