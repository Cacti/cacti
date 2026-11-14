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

// Probe: validate a cacti.sql schema that lacks the two Boost run-time tables, as an
// upgrade from 1.2.30 does, plus the constructor's completed-install branch with a
// missing install_version row.
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';

define('CACTI_VERSION', '1.2.99');
define('PROBE_STOP', 'probe-stop-after-version-check');

$settings = [];
$compare  = [];
$deleted  = [];
$pdo      = null;

function read_config_option($name, $force = false) { return $GLOBALS['settings'][$name] ?? null; }
function get_cacti_version() { return CACTI_VERSION; }
function cacti_version_compare($version1, $version2, $operator = '>') {
	$GLOBALS['compare'][] = [gettype($version1), gettype($version2)];

	return version_compare($version1, $version2, $operator);
}
function log_install_debug(...$args) {}
function log_install_medium(...$args) {}
function log_install_always(...$args) {}
function log_install_high($section, $message) {
	// the constructor logs 'After:' right after the version check; nothing later is under test
	if (str_starts_with($message, 'After:')) {
		throw new RuntimeException(PROBE_STOP);
	}
}
function clean_up_lines($value) { return $value; }
function cacti_sizeof($value) { return is_countable($value) ? count($value) : 0; }
function __($text, ...$args) { return $args ? sprintf($text, ...$args) : $text; }
function db_fetch_assoc_prepared($sql, $params = []) {
	$statement = $GLOBALS['pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}
function db_execute($sql) {
	if (str_starts_with($sql, 'DELETE FROM settings')) {
		$GLOBALS['deleted'][] = $sql;

		return true;
	}

	return $GLOBALS['pdo']->exec($sql) !== false;
}

require dirname(__DIR__, 2) . '/lib/installer.php';

$root     = dirname(__DIR__, 2);
$database = 'cacti_installer_probe';
$pdo      = new PDO(
	'mysql:host=' . (getenv('BOOST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';charset=utf8mb4',
	getenv('BOOST_DB_USER') ?: 'root',
	getenv('BOOST_DB_PASSWORD') ?: '',
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `$database`");
$pdo->exec("CREATE DATABASE `$database`");
$pdo->exec("USE `$database`");

$config           = ['base_path' => $root];
$database_default = $database;
$tables           = ['poller_output_boost_local_data_ids', 'poller_output_boost_failures'];
$schema           = file_get_contents($root . '/cacti.sql');

preg_match_all('/^CREATE TABLE .*?;$/ms', $schema, $statements);

foreach ($statements[0] as $statement) {
	$pdo->exec($statement);
}

$pdo->exec('DROP TABLE poller_output_boost_local_data_ids, poller_output_boost_failures');

$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
$validate  = new ReflectionMethod(Installer::class, 'validateCoreSchema');
$first     = $validate->invoke($installer);
// a second run must leave the tables, and the rows in them, alone
$pdo->exec("INSERT INTO poller_output_boost_failures (local_data_id, rrd_template, update_values) VALUES (1, 'a', '1:2')");
$second = $validate->invoke($installer);

$columns = [];

$count = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = SCHEMA() AND TABLE_NAME = ?');

foreach ($tables as $table) {
	$count->execute([$table]);
	$columns[$table] = (int) $count->fetchColumn();
}
$rows = (int) $pdo->query('SELECT COUNT(*) FROM poller_output_boost_failures')->fetchColumn();
$pdo->exec("DROP DATABASE `$database`");

// A completed install whose install_version row is missing (NULL) must reach the
// version comparison as a string and be treated as a mismatch.
$settings['install_step'] = Installer::STEP_COMPLETE;
$stopped = false;

try {
	new Installer(['Runtime' => 'Cli']);
} catch (RuntimeException $e) {
	$stopped = $e->getMessage() === PROBE_STOP;
}

print json_encode([
	'tables'  => count($statements[0]),
	'first'   => $first,
	'second'  => $second,
	'columns' => $columns,
	'rows'    => $rows,
	'stopped' => $stopped,
	'compare' => $compare,
	'deleted' => count($deleted),
]);
