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
*/

require_once dirname(__DIR__, 3) . '/lib/audit.php';

test('legacy audit identifiers are safely quoted', function () {
	expect(audit_quote_identifier('max-access'))->toBe('`max-access`')
		->and(audit_quote_identifier('legacy`name'))->toBe('`legacy``name`');
});

test('missing core tables are deduplicated and sorted', function () {
	expect(audit_missing_core_tables(
		array('version', 'user_auth_row_cache', 'host', 'host', null),
		array('version', 'host', 'plugin_table', false)
	))->toBe(array('user_auth_row_cache'));
});

test('canonical schema table names support quoted and legacy unquoted declarations', function () {
	$schema = "CREATE TABLE `host` (\n  `id` int NOT NULL\n);\n"
		. "CREATE TABLE user_auth_row_cache (\n  `user_id` int NOT NULL\n);\n"
		. "CREATE TABLE `host` (\n  `id` int NOT NULL\n);";

	expect(audit_schema_table_names($schema))->toBe(array('host', 'user_auth_row_cache'))
		->and(audit_schema_table_names('SELECT 1;'))->toBe(array());
});

test('legacy hyphenated columns are matched exactly', function () {
	$columns = array(
		array('Field' => 'kind'),
		array('Field' => 'max-access'),
		array('NotField' => 'ignored'),
	);

	expect(audit_column_exists($columns, 'max-access'))->toBeTrue()
		->and(audit_column_exists($columns, 'max_access'))->toBeFalse();
});

test('the canonical row cache create statement can be extracted', function () {
	$schema = file_get_contents(dirname(__DIR__, 3) . '/cacti.sql');

	expect($schema)->not->toBeFalse();

	if ($schema === false) {
		throw new RuntimeException('Unable to read cacti.sql');
	}

	$create = audit_extract_create_table($schema, 'user_auth_row_cache');

	expect($create)->toBeString()
		->and($create)->toContain('CREATE TABLE user_auth_row_cache (')
		->and($create)->toContain('PRIMARY KEY (`user_id`,`class`,`hash`)')
		->and($create)->toEndWith('ROW_FORMAT=Dynamic;');
});

test('create statement extraction fails closed', function () {
	$schema = "CREATE TABLE `host` (\n  `id` int NOT NULL\n) ENGINE=InnoDB;";

	expect(audit_extract_create_table($schema, 'host'))->toBe($schema)
		->and(audit_extract_create_table($schema, 'does_not_exist'))->toBeFalse()
		->and(audit_extract_create_table($schema, 'host; DROP TABLE host'))->toBeFalse();
});

test('database audit query failures fail closed', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/cli/audit_database.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain('if (!is_array($tables)) {')
		->and($source)->toContain('if (!is_array($expected_tables)) {')
		->and($source)->toContain('if ($alters === false) {')
		->and(substr_count($source, "audit_quote_identifier(\$dbc['table_field'])"))->toBe(2)
		->and($source)->toContain('audit_quote_identifier($after)')
		->and($source)->toContain('$exit_code = report_audit_results() === false ? 1 : 0;');
});
