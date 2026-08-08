<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the core schema tables that are absent from the live database.
 *
 * @param array<int, mixed> $expected_tables Tables recorded in the canonical audit schema
 * @param array<int, mixed> $actual_tables   Tables present in the selected database
 *
 * @return array<int, string> Missing table names in deterministic order
 */
function audit_missing_core_tables(array $expected_tables, array $actual_tables) : array {
	$expected_tables = array_values(array_unique(array_filter($expected_tables, 'is_string')));
	$actual_tables   = array_values(array_unique(array_filter($actual_tables, 'is_string')));
	$missing_tables  = array_values(array_diff($expected_tables, $actual_tables));

	sort($missing_tables, SORT_STRING);

	return $missing_tables;
}

/**
 * Return the core table names declared by cacti.sql.
 *
 * @return array<int, string> Canonical table names in schema order
 */
function audit_schema_table_names(string $schema_sql) : array {
	preg_match_all('/^CREATE TABLE\s+`?([A-Za-z0-9_]+)`?\s*\(/m', $schema_sql, $matches);

	return array_values(array_unique($matches[1]));
}

/**
 * Check an already-fetched SHOW COLUMNS result for an exact column name.
 *
 * Legacy Cacti schemas contain names such as "max-access" that are not valid
 * inputs to the generic identifier-based database helpers.
 *
 * @param array<int, array<string, mixed>> $columns SHOW COLUMNS rows
 * @param string                           $column  Expected column name
 */
function audit_column_exists(array $columns, string $column) : bool {
	foreach($columns as $candidate) {
		if (isset($candidate['Field']) && $candidate['Field'] === $column) {
			return true;
		}
	}

	return false;
}

/**
 * Extract one canonical CREATE TABLE statement from cacti.sql.
 *
 * @param string $schema_sql Full contents of cacti.sql
 * @param string $table      Valid Cacti table identifier
 *
 * @return string|false The CREATE TABLE statement, or false when unavailable
 */
function audit_extract_create_table(string $schema_sql, string $table) {
	if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
		return false;
	}

	$quoted_table = preg_quote($table, '~');
	$pattern      = '~^CREATE TABLE\s+`?' . $quoted_table . '`?\s*\(.*?^\)[^;]*;\h*$~ms';

	if (preg_match($pattern, $schema_sql, $matches) !== 1) {
		return false;
	}

	return trim($matches[0]);
}
