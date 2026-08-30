<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/lib/database.php';

test('qualified table identifiers are validated and quoted per component', function () {
	expect(db_format_qualified_identifier('syslog_logs'))->toBe('`syslog_logs`')
		->and(db_format_qualified_identifier('syslog_prod.syslog_logs'))->toBe('`syslog_prod`.`syslog_logs`')
		->and(db_format_qualified_identifier('`syslog_prod`.`syslog_logs`'))->toBe('`syslog_prod`.`syslog_logs`');
});

test('unsafe qualified table identifiers are rejected', function () {
	foreach (array(
		'',
		'syslog_prod.',
		'.syslog_logs',
		'syslog_prod..syslog_logs',
		'`syslog_prod`.syslog_logs`',
		'syslog_prod.syslog_logs; DROP TABLE users',
	) as $identifier) {
		expect(db_format_qualified_identifier($identifier))->toBeFalse();
	}
});

test('column metadata uses the canonical qualified identifier', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/database.php');
	$start  = strpos($source, 'function db_get_table_column_types(');
	$end    = strpos($source, "\nfunction ", $start + 1);
	$body   = substr($source, $start, $end - $start);

	expect($body)->toContain('$table_identifier = db_format_qualified_identifier($table);')
		->and($body)->toContain('SHOW COLUMNS FROM $table_identifier')
		->and($body)->not->toContain('SHOW COLUMNS FROM `$table`');
});
