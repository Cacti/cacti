<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$queries = array();

/**
 * Captures a prepared automation query without requiring a database.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return array Empty result set.
 */
function db_fetch_assoc_prepared($sql, $params = array()) {
	global $queries;

	$queries[] = array($sql, $params);

	return array();
}

/**
 * Returns the size of an array probe value.
 *
 * @param mixed $value Value to count.
 *
 * @return int Number of elements.
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

require_once dirname(__DIR__, 2) . '/lib/api_automation_tools.php';

getHostsByDescription(array(1, '2'));
getHosts(3);
getGraphTemplatesByHostTemplate(array('4', 5));

$valid_queries = $queries;
$queries       = array();

$invalid_results = array(
	getHostsByDescription(array(1, 'bad')),
	getHosts('1e3'),
	getGraphTemplatesByHostTemplate('1 OR 1=1'),
);

print json_encode(array(
	'queries'             => $valid_queries,
	'invalid_results'     => $invalid_results,
	'invalid_query_count' => count($queries),
));
