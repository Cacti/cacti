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
		library_database_debug($table);
		library_database_uninitialized();
	} finally {
		db_execute('DROP TABLE IF EXISTS `' . $table . '`');
		unlink($dump);
	}
}

/** A connection-local temporary table simulates an incomplete installed schema. */
function library_database_uninitialized(): void {
	$directory = sys_get_temp_dir() . '/cacti-initialization-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Initialization fixture owns its evidence directory');
	$source = <<<'PHP'
require 'tests/Helpers/RequestCoverage.php';
require 'include/global.php';
$connection = $database_sessions["$database_hostname:$database_port:$database_default"];
$connection->exec('CREATE TEMPORARY TABLE version (fixture INT)');
db_cacti_initialized($argv[1] === 'web');
throw new RuntimeException('An incomplete schema must stop initialization');
PHP;
	$environment                          = getenv();
	$environment['CACTI_PR_RAW_COVERAGE'] = $directory;
	$environment['XDEBUG_MODE']           = 'coverage';

	try {
		foreach (['cli', 'web'] as $mode) {
			$process = proc_open([PHP_BINARY, '-r', $source, $mode], [0 => ['pipe', 'r'],
				1                                                          => ['file', $directory . '/output.txt', 'w'], 2 => ['file', $directory . '/error.txt', 'w']], $pipes, CACTI_PATH_BASE, $environment);
			library_expect(is_resource($process), true, 'Incomplete-schema probe starts in its own connection');
			fclose($pipes[0]);
			library_expect(proc_close($process), 0, 'Incomplete schema exits through the established installation response');
			$output = file_get_contents($directory . '/output.txt');
			library_expect(str_contains($output, 'The Cacti Database has not been initialized.'), true, 'Incomplete schema supplies initialization guidance');
			library_expect(str_contains($output, 'mysql -uroot -p cacti'), true, 'Initialization response includes the schema import command');
			library_expect(str_contains($output, '<head>'), $mode === 'web', 'Initialization response distinguishes HTML and CLI output');
			library_expect(file_get_contents($directory . '/error.txt'), '', 'Initialization stops without a PHP exception');
		}
		$reports = glob($directory . '/*.json');
		library_expect(count($reports), 2, 'Both initialization scenarios produce coverage');
		library_import_coverage($reports);
		library_expect(db_cacti_initialized(false), true, 'Child temporary schema never changes the parent database');
	} finally {
		foreach (glob($directory . '/*') as $path) {
			unlink($path);
		}
		rmdir($directory);
	}
}

/** SQL diagnostics must preserve results, failures, and parameter binding. */
function library_database_debug(string $table): void {
	global $config;
	require_once CACTI_PATH_LIBRARY . '/dbparallel.php';
	$flags   = ['DEBUG_SQL_CMD', 'DEBUG_SQL_FLOW'];
	$before  = [];
	$logfile = sys_get_temp_dir() . '/cacti-sql.log';
	$owned   = !file_exists($logfile);
	$offset  = $owned ? 0 : filesize($logfile);

	if ($owned) {
		$stream = fopen($logfile, 'x');
		library_expect(is_resource($stream), true, 'SQL diagnostics own their new log');
		fclose($stream);
	}

	foreach ($flags as $flag) {
		$before[$flag] = $config[$flag] ?? null;
		$config[$flag] = true;
	}

	try {
		$label = 'fixture diagnostic Café';
		library_expect(db_execute_prepared("UPDATE `$table` SET label = ?", [$label], timeout: 0.1), true, 'Diagnostics preserve an update when a SELECT timeout is inapplicable');
		library_expect(db_fetch_cell_prepared("SELECT label FROM `$table` WHERE label = ?", [$label]), $label, 'Diagnostic cell lookup preserves UTF-8 binding');
		library_expect(db_fetch_row("SELECT label FROM `$table`"), ['label' => $label], 'Diagnostic row lookup preserves its result');
		library_expect(db_fetch_row_parallel("SELECT label FROM `$table`"), ['label' => $label], 'Parallel compatibility wrapper preserves the row');
		library_expect(db_fetch_row_parallel_prepared("SELECT label FROM `$table` WHERE label = ?", [$label]), ['label' => $label], 'Parallel prepared wrapper preserves binding');
		library_expect(db_fetch_cell_parallel_prepared("SELECT label FROM `$table` WHERE label = ?", [$label]), $label, 'Parallel cell wrapper returns the bound value');
		library_expect(db_fetch_assoc_parallel_prepared("SELECT label FROM `$table` WHERE label = ?", [$label]), [['label' => $label]], 'Parallel collection wrapper returns its rows');
		library_expect(db_fetch_row("SELECT missing_fixture_column FROM `$table`"), false, 'Diagnostics do not turn a database failure into an empty success');
		library_expect(str_contains(db_error(), 'missing_fixture_column'), true, 'Database failure retains the actionable column error');
		library_expect(db_fetch_cell("SELECT label FROM `$table`"), $label, 'Failed diagnostic query preserves stored data');
		$contents = file_get_contents($logfile, false, null, $offset);
		library_expect(str_contains($contents, 'Memory [Before]') && str_contains($contents, 'Memory [ After]'), true, 'SQL diagnostics report both sides of execution');
		library_expect(str_contains($contents, 'fixture diagnostic') && str_contains($contents, 'db_fetch_row'), true, 'SQL diagnostics identify the fixture operation and result');
	} finally {
		foreach ($before as $flag => $value) {
			if ($value === null) {
				unset($config[$flag]);
			} else {
				$config[$flag] = $value;
			}
		}

		if ($owned) {
			unlink($logfile);
		}
	}
}
