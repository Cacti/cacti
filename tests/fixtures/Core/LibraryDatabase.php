<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_database(): void {
	global $database_hostname, $database_username, $database_password, $database_default, $database_port;

	// Schema helpers run inside installation, where metadata caching is disabled.
	if (!defined('IN_CACTI_INSTALL')) {
		define('IN_CACTI_INSTALL', 1);
	}
	$table  = 'library_schema_' . bin2hex(random_bytes(6));
	$schema = ['type' => 'InnoDB', 'charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci',
		'columns'        => [
			['name' => 'id', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true],
			['name' => 'label', 'type' => 'varchar(80)', 'NULL' => false, 'default' => ''],
			['name' => 'optional', 'type' => 'int', 'NULL' => true],
		], 'primary' => 'id', 'unique_keys' => [['name' => 'label', 'columns' => ['label']]], 'comment' => 'Disposable schema regression'];
	$dump = tempnam(sys_get_temp_dir(), 'cacti-dump-');

	try {
		library_expect(db_update_table($table, $schema), true, 'Schema helper creates a missing table');
		library_expect(db_table_create($table, $schema), true, 'Repeated creation preserves an existing table');
		library_expect(db_execute_prepared("INSERT INTO `$table` (label) VALUES (?)", ['Café & fixture']), true, 'Prepared insert stores UTF-8 data');
		$row = db_fetch_row("SELECT * FROM `$table`");
		library_expect($row['label'], 'Café & fixture', 'Database roundtrip preserves UTF-8');
		library_expect($row['optional'], null, 'Nullable column preserves null');
		library_expect(db_execute_prepared("INSERT INTO `$table` (label) VALUES (?)", ['Café & fixture'], false), false, 'Duplicate identity reports failure');
		library_expect((int) db_fetch_cell("SELECT COUNT(*) FROM `$table`"), 1, 'Failed insertion preserves the existing row');
		$extra = ['name' => 'count_value', 'type' => 'int', 'unsigned' => true, 'NULL' => false, 'default' => 7, 'after' => 'label', 'comment' => 'Fixture count'];
		library_expect(db_add_column($table, $extra), true, 'Column addition succeeds');
		library_expect(db_add_column($table, $extra), true, 'Repeated column addition is harmless');
		library_expect((int) db_fetch_cell("SELECT count_value FROM `$table`"), 7, 'Added column supplies its declared default');
		$schema['columns'][]          = $extra;
		$schema['columns'][1]['type'] = 'varchar(120)';
		library_expect(db_update_table($table, $schema), true, 'Schema update widens the label without replacing data');
		$stored = db_fetch_row("SELECT * FROM `$table`");
		library_expect($stored['label'], $row['label'], 'Schema update preserves stored text');
		library_expect($stored['optional'], null, 'Schema update preserves null');
		library_expect(db_fetch_row("SELECT * FROM `$table` WHERE id = -1"), [], 'Empty row lookup returns an empty result');
		library_expect(db_fetch_row("SELECT missing_column FROM `$table`", false), false, 'Invalid row query remains distinguishable from an empty result');
		library_expect(db_fetch_cell_prepared('SELECT ?', ['bound'], timeout: 0.5), 'bound', 'Timed prepared lookup preserves parameter binding');
		library_expect(db_cacti_initialized(false), true, 'Initialized schema is recognized');

		foreach ([['host' => $database_hostname, 'user' => $database_username, 'password' => $database_password],
			['--host' => $database_hostname, '--user' => $database_username, '--password' => $database_password],
			['-h' => $database_hostname, '-u' => $database_username, '-p' => $database_password]] as $credentials) {
			library_expect(db_dump_data($database_default, $table, $credentials, $dump, '--skip-ssl --skip-lock-tables --extended-insert=FALSE'), 0, 'Database dump accepts each supported credential spelling');
			$sql = file_get_contents($dump);
			library_expect(is_string($sql) && str_contains($sql, 'CREATE TABLE `' . $table . '`'), true, 'Dump contains the actual schema');
			library_expect(str_contains($sql, 'Café & fixture'), true, 'Dump contains the actual stored row');
		}
		$dom = library_form_dom(static function (): void {
			$status = utilities_get_mysql_recommendations();
			library_expect(in_array($status, [DB_STATUS_SUCCESS, DB_STATUS_WARNING, DB_STATUS_ERROR], true), true, 'Database health reports a recognized status');
		});
		library_expect(str_contains($dom->document->textContent, 'max_connections'), true, 'Health output includes the live connection limit');
		library_expect(str_contains($dom->document->textContent, 'MariaDB'), true, 'Health output identifies the actual database family');
	} finally {
		db_execute('DROP TABLE IF EXISTS `' . $table . '`');
		unlink($dump);
	}
}
