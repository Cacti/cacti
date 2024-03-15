<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

/**
 * db_fetch_cell - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (string)        Use this column name instead of the first one
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool)  The output of the sql query as a single variable
 */
function db_fetch_cell_parallel($sql, $col_name = '', $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell($sql, $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_cell_prepared($sql, array(), $col_name, $log, $db_conn);
}

/**
 * db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (string)        Use this column name instead of the first one
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool) The output of the sql query as a single variable
 */
function db_fetch_cell_parallel_prepared($sql, $params = array(), $col_name = '', $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell_prepared($sql, $params = ' . clean_up_lines(var_export($params, true)) . ', $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Cell', false, 'db_fetch_cell_return', $col_name);
}

/**
 * db_fetch_row - run a 'select' sql query and return the first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool|array) The first row of the result or false if failed
 */
function db_fetch_row_parallel($sql, $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row(\'' . clean_up_lines($sql) . '\', $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_fetch_row_prepared($sql, array(), $log, $db_conn);
}

/**
 * db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool|array) The first row of the result or false if failed
 */
function db_fetch_row_parallel_prepared($sql, $params = array(), $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_prepared(\'' . clean_up_lines($sql) . '\', $params = (\'' . implode('\', \'', $params) . '\'), $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', false, 'db_fetch_row_return');
}

/**
 * db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param  (string)        The SQL query to execute
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool|array)    The entire result set or false on error
 */
function db_fetch_assoc_parallel($sql, $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc($sql, $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_assoc_prepared($sql, array(), $log, $db_conn);
}

/**
 * db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param  (string)        The sql query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (int)           The number of parallel threads to use
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (bool|array)    The entire result or false on error
 */
function db_fetch_assoc_parallel_prepared($sql, $params = array(), $threads = 2, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', array(), 'db_fetch_assoc_return');
}

/**
 * db_query_parallelize - break a query into pieces to parallelize and return
 *   components to the parent.  It will only work with very simple level of
 *   parallelism around both UNION and PARTITION queries including only
 *   certain aggregation functions.
 *
 * Cases that are not currently supported are:
 * - Queries with aggregation other than MIN, MAX, AVG, SUM, and COUNT
 *
 * More specifically, it will not handle the following aggregators:
 * - BIT_OR, BIT_AND, or BIT_XOR
 * - GROUP_CONCAT
 * - COUNT DISTINCT
 * - JSON*
 * - STD, STDDEV, STDDEV_POP, STDDEV_SAMP
 * - VARIANCE, VAR_POP, VAR_SAMP
 *
 * At some point, additional support may be added, but at a later date
 *
 * The components that are returned are as follows:
 *
 * $response = array(
 *    'error'              => boolean true or false
 *    'error_message'      => a message to the caller about the error
 *    'temp_table_name'    => string of the temp table to be removed after query complete
 *    'temp_create_syntax' => '',
 *    'reduce_query'       => string of the final reduce query
 *    'map_queries'        => an array of map queries decomposed from the original query
 * );
 *
 * @params (string)        The query to execute
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @params (bool)          If set to true, the temporary table will be created
 *
 * @return (array)         The query pieces, the map and reduce components with
 *                         and error and error_message
 */
function db_query_parallelize($sql, $log = true, $db_conn = false) {
	global $debug;

	/**
	 * For simplicity, In our tokenization of the query, we will ignore
	 * any query that includes these custom keyword options.
	 *
	 */
	$ignores = array(
		'DISTINCTROW',
		'HIGH_PRIORITY',
		'SQL_SMALL_RESULT',
		'SQL_BIG_RESULT',
		'SQL_BUFFER_RESULT',
		'SQL_CACHE',
		'SQL_NO_CACHE',
		'SQL_CALC_FOUND_ROWS',
		'FOR UPDATE',
		'LOCK IN SHARE MODE'
	);

	$replaces = array('', '', '', '', '', '', '', '', '');

	$sql = str_replace($ignores, $replaces, $sql);

	while (substr_count($sql, '  ')) {
		$str = str_replace('  ', ' ', $str);
	}

	/**
	 * Additionally, we run a quick and dirty Query cleanup
	 * to remove some extra spaces, line breaks and tabs that
	 * may compromise the further tokenization from
	 * being handled properly.
	 *
	 * I know this will break some exotic queries,
	 * and should in general be avoided, but I'm writing
	 * this and not you ;)
	 *
	 * This cleanup has two phases.
	 * - Remove some odd formatting, line breaks and tabs
	 * - Remove extra spaces
	 */
	$sql = str_replace(
		array("\n", "\t", "\r", ';'),
		array(' ', ' ', ' ', ''),
		$sql
	);

	while (substr_count($sql, '  ')) {
		$sql = str_replace('  ', ' ', $sql);
	}

	if ($debug) {
		print PHP_EOL . 'Pre-processed Query: ' . $sql . PHP_EOL . PHP_EOL;
	}

	/**
	 * In this prototype, we will reject a number of allowed SQL forms
	 * to simply keep it simple for the moment.  In the future, we may support
	 * more elaborate forms of SQL based upon a valid feature request.
	 */
	$disallowed =
		// Import and export
		'/(INFILE '             . // Import Option
		'|OUTFILE '            . // Export Option
		'|INTO DUMPFILE '      . // Export Option
		'|INTO '               . // Export Option

		// Aggregation Functions
		'|VARIANCE'            . // Aggregation Function
		'|VAR_'                . // Aggregation Function
 		'|STD'                 . // Aggregation Function
		'|STDDEV'              . // Aggregation Function
		'|COUNT\(DISTINCT'     . // Aggregation Function

		// Oddball Query Options
		'|LOCK IN SHARE MODE ' . // Query Option
		'|FOR UPDATE '         . // Update Option
		'|FOR SYSTEM TIME '    . // System Table Support
		'|PROCEDURE '          . // Stored Procedures
		'|PARTITION '          . // PARTITION specific query

		// JSON Functions
		'|JSON_'               . // Any JSON Functions

		// Index Options
		'|FORCE '              . // FORCE Index
		'|IGNORE '             . // IGNORE Index
		'| USE )/i';             // USE Index

	/**
	 * Track UNION queries as these queries must be handled using
	 * extra logic to properly move the WHERE clause into the
	 * map queries to improve performance of those map queries.
	 */
	$is_union = false;

	/**
	 * Our reponse object which can then be used by the caller
	 * to create the table, perform queries, etc.
	 */
 	$response = array(
		'error'              => false,
		'error_message'      => '',
		'temp_table_name'    => 'par_' . generate_hash() . '_res', // Limited to 40 characters due to process table length limit
		'temp_create_syntax' => '',
		'reduce_query'       => '',
		'map_queries'        => array()
	);

	// Check for disallowed tokens
	if (preg_match($disallowed, $sql)) {
		$response['error']         = true;
		$response['error_message'] = 'The SQL Query included one of the disallowed SQL options, functions, or modifiers.';

		return $response;
	}

	// Create a split on the FROM clause
	$from_split   = explode('FROM ', $sql, 2);

	/**
	 * We need to track the partitions for operating on partitioned
	 * tables for the MAP phase.
	 */
	$partitions = array();

	/**
	 * Perform some pre-checks, UNION queries, by their nature are parallelizable.
	 * However, queries without UNIONS must come from a partitioned tables.
	 */
	if (stripos($sql, 'UNION') === false && stripos($sql, 'UNION ALL') === false) {
		// Find the table name if not a union query
		if (sizeof($from_split) > 1) {
			$bits = explode(' ', trim($from_split[1]));
			$table_name = trim($bits[0], '`');

			if (!db_table_partitioned($table_name, $log, $db_conn, $partitions)) {
				$response['error']         = true;
				$response['error_message'] = 'The SQL query main table is not partitioned.';

				return $response;
			}
		} else {
			$response['error']         = true;
			$response['error_message'] = 'The SQL query lacks a FROM clause.';

			return $response;
		}
	} else {
		$is_union     = true;
		$is_union_all = (stripos($sql, 'UNION ALL') !== false) ? true : false;

		/**
		 * Here we check if the UNION is properly nested where the
		 * GROUP BY, HAVING, LIMIT, etc. are processed inside or outside
		 * the table select/from logic. If outside or simple, then we need
		 * to skip the checks below GROUP BY, LIMIT, ORDER, etc. below as
		 * they are each contained within the UNION's themselves.
		 */
		if (substr(trim($from_split[1]), 0, 1) == '(') {
			$simple_union = false;
		} else {
			$simple_union = true;
		}
	}

	/**
	 * Query clauses must come in a specific order.  That order is always the same, though
	 * with sub-queries, it can get a bit more complicated.  Here is the general
	 * order. We are not supporting Sub-Queries with the exception of UNION queries.
	 * So, we will make broad assumptions about the structure of the queries and
	 * reject them otherwise.
	 *
	 * SELECT
	 * FROM
	 * JOIN(S)
	 * WHERE
	 * GROUP BY [ WITH ROLLUP ]
	 * HAVING
	 * ORDER BY
	 * LIMIT
	 *
	 * Knowning this information, we can tokenize the SQL
	 * statement and prepare for paralellization.  The MySQL/MariaDB language
	 * syntax has a very many modifiers that we hope not to have to cover
	 * in this exercise.  They include, but are not limited to the items included
	 * in the following two links:
	 *
	 * Select Syntax:
	 * https://mariadb.com/kb/en/select/
	 *
	 * Table References and Join Options:
	 * https://mariadb.com/kb/en/join-syntax/
	 *
	 */

	$tokens = array(
		'raw_select'   => '',
		'outer_select' => '',
		'inner_select' => '',
		'from'         => '',
		'where'        => '',
		'groupby'      => '',
		'having'       => '',
		'orderby'      => '',
		'limit'        => ''
	);

	/**
	 * Tokenizing the SQL query can be handled in many ways.  I'm taking the
	 * approach of handing UNION queries separately from PARTITIONED queries
	 * and I'll be working in reverse breaking the query into the various
	 * tokens.
	 *
	 * I know that this may be inneficient, but it the way I will handle
	 * for now.  At some point it can be improved/optimized.
	 */
	if (!$is_union || ($is_union && !$simple_union)) {
		$selectstr = $from_split[0];
		$tokenstr  = $from_split[1];

		if (stripos($tokenstr, 'LIMIT') !== false) {
			$tempstr = str_ireplace('limit', 'LIMIT', $tokenstr);
			$parts   = explode('LIMIT', $tempstr);

			$elements        = cacti_sizeof($parts);
			$tokens['limit'] = 'LIMIT ' . trim($parts[$elements-1]);

			unset($parts[$elements-1]);

			$tokenstr = trim(implode('LIMIT', $parts));
		}

		if (stripos($tokenstr, 'ORDER BY') !== false) {
			$tempstr = str_ireplace('order by', 'ORDER BY', $tokenstr);
			$parts   = explode('ORDER BY', $tempstr);

			$elements          = cacti_sizeof($parts);
			$tokens['orderby'] = 'ORDER BY ' . trim($parts[$elements-1]);

			unset($parts[$elements-1]);

			$tokenstr = trim(implode('ORDER BY', $parts));
		}

		if (stripos($tokenstr, 'HAVING') !== false) {
			$tempstr = str_ireplace('having', 'HAVING', $tokenstr);
			$parts   = explode('HAVING', $tempstr);

			$elements          = cacti_sizeof($parts);
			$tokens['having']  = 'HAVING ' . trim($parts[$elements-1]);

			unset($parts[$elements-1]);

			$tokenstr = trim(implode('HAVING', $parts));
		}

		if (stripos($tokenstr, 'GROUP BY') !== false) {
			$tempstr = str_ireplace('group by', 'GROUP BY', $tokenstr);
			$parts   = explode('GROUP BY', $tempstr);

			$elements          = cacti_sizeof($parts);
			$tokens['groupby'] = 'GROUP BY ' . trim($parts[$elements-1]);

			unset($parts[$elements-1]);

			$tokenstr = trim(implode('GROUP BY', $parts));
		}

		if (stripos($tokenstr, 'WHERE') !== false) {
			$tempstr = str_ireplace('where', 'WHERE', $tokenstr);
			$parts   = explode('WHERE', $tempstr);

			$elements          = cacti_sizeof($parts);
			$tokens['where']   = 'WHERE ' . trim($parts[$elements-1]);

			unset($parts[$elements-1]);

			$tokenstr = trim(implode('WHERE', $parts));
		}

		$tokens['from'] = 'FROM ' . trim($tokenstr);

		// Format the raw inner and outer selectors
		if (stripos($selectstr, 'SELECT') !== false) {
			$tempstr = str_ireplace('select', '', $selectstr);
			$tokens['raw_select'] = trim($tempstr);
		}

		// Modify the outer selector to remove aliases
		$outer = explode(',', $tokens['raw_select']);
		$inner = '';
		foreach($outer as $index => $o) {
			$aggregate = db_get_aggregate($o);

			if ($aggregate != '' && $debug) {
				print 'Aggregate: ' . $aggregate . PHP_EOL;
			}

			if ($aggregate == '') {
				$parts = explode('.', $o);
				if (cacti_sizeof($parts) > 1) {
					if (stripos($parts[1], 'AS') !== false) {
						$parts[1] = str_ireplace('as', 'AS', $parts[1]);
						$bits = explode('AS', $parts[1]);
						$outer[$index] = trim($bits[1]);
					} else {
						$outer[$index] = trim($parts[1]);
					}
				} elseif (stripos($o, 'AS') !== false) {
					$o = str_ireplace('as', 'AS', $o);
					$bits = explode('AS', $o);
					$outer[$index] = trim($bits[1]);
				} else {
					$outer[$index] = trim($parts[0]);
				}
			} else {
				$alias = $index;
				if (stripos($o, 'AS') !== false) {
					$o = str_ireplace('as', 'AS', $o);
					$bits = explode('AS', $o);
					$alias = trim($bits[1]);
				}

				if ($aggregate == 'AVG') {
					// Handle AVG Aggregator
				} elseif ($aggregate == 'COUNT') {
					// Handle COUNT Aggregator
				} else {
					// Handle Aggregators MIN, MAX, SUM
				}
			}

			$inner .= ($inner != '' ? ', ':'') . $outer[$index];
		}

		// Handle UNIONS into Partitions

		// Determine CREATE Table Syntax from UNION or PARTITION columns

		$tokens['outer_select'] = implode(',', $outer);
	} else {
		$sql = str_ireplace(array('union all', 'union'), array('UNION ALL', 'UNION'), $sql);

		if ($is_union_all) {
			$partitions = explode('UNION ALL', $sql);
		} else {
			$partitions = explode('UNION', $sql);
		}

		if (cacti_sizeof($partitions)) {
			foreach($partitions as $index => $p) {
				$partitions[$index] = trim($p);
			}
		}

		$tokens['raw_select']   = '*';
		$tokens['outer_select'] = '*';

		// Determine CREATE Table Syntax from Partition logic
	}

	if ($debug) {
		print 'SQL Tokens:';
		print PHP_EOL;
		print_r($tokens);

		print PHP_EOL;
		print 'Partitions:';
		print PHP_EOL;
		print_r($partitions);
	}

	return array('tokens' => $tokens, 'partitions' => $partitions);
}

/**
 * db_get_aggregate - Return the aggregate function from a SQL select item
 *
 * @param  (string)        The SQL column string
 *
 * @return (string)        The column aggregate function
 */
function db_get_aggregate($colsql) {
	// Decompose and prepare the query for parallization
	$aggregators = array(
		'SUM(',
		'COUNT(',
		'MIN(',
		'MAX(',
		'AVG('
	);

	foreach($aggregators as $a) {
		if (stripos($colsql, $a) !== false) {
			return trim($a, '(');
		}
	}

	return '';
}

/**
 * db_table_partitioned - checks whether a table is partitioned and returns partition numbers
 *
 * @param  (string)        The name of the table
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param  (array)         The connection to use or false to use the default
 *
 * @return (bool) The output of the sql query as a single variable
 */
function db_table_partitioned($table, $log = true, $db_conn = false, &$partitions = array()) {
	static $results;

	if (isset($results[$table]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		$partitions = $results[$table]['partitions'];

		return $results[$table]['result'];
	}

	// Separate the database from the table and remove backticks
	preg_match("/([`]{0,1}(?<database>[\w_]+)[`]{0,1}\.){0,1}[`]{0,1}(?<table>[\w_]+)[`]{0,1}/", $table, $matches);

	if ($matches !== false && db_table_exists($table, false, $db_conn) && array_key_exists('table', $matches)) {
		$sql = 'SHOW CREATE TABLE ' . $matches['table'];

		$create = db_fetch_row($sql, $log, $db_conn);

		if (cacti_sizeof($create)) {
			$create = $create['Create Table'];
		}

		if (stripos($create, 'PARTITION ') !== false) {
			$results[$table]['result'] = true;

			$parts = explode("\n", $create);

			foreach($parts as $l) {
				if (stripos($l, 'PARTITION ') !== false && stripos($l, 'PARTITION BY') === false) {
					$comp = explode(' ', trim($l));
					$partitions[] = trim($comp[1], '`');
				}
			}

			$results[$table]['partitions'] = $partitions;
		} else {
			$results[$table]['partitions'] = array();
			$results[$table]['result']     = false;
		}

		return $results[$table]['result'];
	}

	return false;
}

