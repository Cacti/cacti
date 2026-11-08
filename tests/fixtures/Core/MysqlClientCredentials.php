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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiMysqlClientFixture;

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

/*
 * The SQL polling scripts read server status over Cacti's own connection and
 * must never start a client.  db_dump_data() still runs mysqldump, so it must
 * hand the credentials over in a private option file loaded with
 * --defaults-extra-file and remove that file however the client ends.
 */

$GLOBALS['database_username'] = 'fixture_user';
$GLOBALS['database_password'] = 'fixture "quoted" # password\\value';
$GLOBALS['database_hostname'] = '127.0.0.1';
$GLOBALS['database_port']     = 3307;
$GLOBALS['database_default']  = 'fixture_db';
$GLOBALS['credential_files']  = [];
$GLOBALS['client_calls']      = 0;
$GLOBALS['client_throws']     = false;
$GLOBALS['client_file']       = [];
$GLOBALS['client_command']    = '';
$GLOBALS['status_rows']       = [];
$GLOBALS['questions_row']     = false;

foreach (['db_client_defaults_file', 'db_dump_data'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/database.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/ss_sql.php', 'ss_sql', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/sql.php', 'sql_questions', __NAMESPACE__);

/**
 * Fail the probe with $message unless $condition holds.
 *
 * @param bool   $condition
 * @param string $message
 *
 * @return void
 */
function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

/**
 * Check a client command line and record the option file it was given.
 *
 * @param string $command the shell command db_dump_data() would run
 *
 * @return void
 */
function inspect_command(string $command) : void {
	check(!str_contains($command, $GLOBALS['database_password']) && !str_contains($command, '--password') && !str_contains($command, 'fixture_user'), 'Credentials leaked into argv.');
	check((bool) preg_match('/^\S*dump --defaults-extra-file=\'([^\']+)\' /', $command, $match), 'Private option file was not the first client option.');
	$path = $match[1];
	clearstatcache(true, $path);
	check((fileperms($path) & 0777) === 0600, 'Credential file permissions are not private.');
	$data = parse_ini_file($path, true, INI_SCANNER_RAW);
	check($data['client']['user'] === 'fixture_user', 'Client username changed.');
	check(stripcslashes($data['client']['password']) === $GLOBALS['database_password'], 'Client password quoting changed.');
	$GLOBALS['credential_files'][] = $path;
	$GLOBALS['client_file']        = $data['client'];
	$GLOBALS['client_command']     = $command;
	$GLOBALS['client_calls']++;

	if ($GLOBALS['client_throws']) {
		throw new \RuntimeException('Fixture client failure');
	}
}

/**
 * Stand in for the client mysqldump runs as.
 *
 * @param string $command
 * @param mixed  $output
 * @param mixed  $status
 *
 * @return void
 */
function exec(string $command, &$output, &$status) : void {
	inspect_command($command);
	$output = [];
	$status = 7;
}

/**
 * Fail if a polling script starts a client process.
 *
 * @param string $command
 *
 * @return never
 */
function shell_exec(string $command) {
	throw new \RuntimeException('SQL polling started a client: ' . $command);
}

/**
 * Answer ss_sql()'s status query from $GLOBALS['status_rows'].
 *
 * @param string $sql
 * @param bool   $log
 * @param mixed  $db_conn
 *
 * @return array<int, array<string, string>>
 */
function db_fetch_assoc(string $sql, bool $log = true, mixed $db_conn = false) : array {
	check(str_starts_with($sql, 'SHOW GLOBAL STATUS'), 'Unexpected status query.');

	return $GLOBALS['status_rows'];
}

/**
 * Answer sql_questions()'s status query from $GLOBALS['questions_row'].
 *
 * @param string $sql
 * @param bool   $log
 * @param mixed  $db_conn
 *
 * @return array<string, string>|false
 */
function db_fetch_row(string $sql, bool $log = true, mixed $db_conn = false) : array|false {
	check($sql === "SHOW GLOBAL STATUS LIKE 'Questions'", 'Unexpected Questions query.');

	return $GLOBALS['questions_row'];
}

/**
 * tempnam() that can be made to fail, remembering the file it created.
 *
 * @param string $directory
 * @param string $prefix
 *
 * @return string|false
 */
function tempnam(string $directory, string $prefix) : string|false {
	if (($GLOBALS['file_failure'] ?? '') === 'tempnam') {
		return false;
	}

	return $GLOBALS['temporary_file'] = \tempnam($directory, $prefix);
}

/**
 * chmod() that can be made to fail.
 *
 * @param string $path
 * @param int    $mode
 *
 * @return bool
 */
function chmod(string $path, int $mode) : bool {
	return ($GLOBALS['file_failure'] ?? '') === 'chmod' ? false : \chmod($path, $mode);
}

/**
 * file_put_contents() that can be made to write short.
 *
 * @param string $path
 * @param string $contents
 *
 * @return int|false
 */
function file_put_contents(string $path, string $contents) : int|false {
	return ($GLOBALS['file_failure'] ?? '') === 'write' ? 0 : \file_put_contents($path, $contents);
}

/**
 * Status rows the way SHOW GLOBAL STATUS returns them.
 *
 * @param array<string, string> $values
 *
 * @return array<int, array<string, string>>
 */
function status_rows(array $values) : array {
	$rows = [];

	foreach ($values as $name => $value) {
		$rows[] = ['Variable_name' => $name, 'Value' => $value];
	}

	return $rows;
}

// MySQL reports every mysqladmin status field; QPS is truncated like mysqladmin's
$GLOBALS['status_rows'] = status_rows(['Uptime' => '10', 'Threads_connected' => '1', 'Questions' => '9', 'Slow_queries' => '0', 'Opened_tables' => '2', 'Flush_commands' => '1', 'Open_tables' => '3']);
check(ss_sql() === 'Uptime:10 Threads:1 Questions:9 SlowQueries:0 Opens:2 FlushTables:1 OpenTables:3 QPS:0.900', 'MySQL status output changed: ' . ss_sql());

// MariaDB has no Flush_commands, and a server that just started has no uptime
$GLOBALS['status_rows'] = status_rows(['Uptime' => '0', 'Threads_connected' => '1', 'Questions' => '9', 'Slow_queries' => '0', 'Opened_tables' => '2', 'Open_tables' => '3']);
check(ss_sql() === 'Uptime:0 Threads:1 Questions:9 SlowQueries:0 Opens:2 OpenTables:3 QPS:0.000', 'MariaDB status output changed: ' . ss_sql());

$GLOBALS['status_rows'] = [];
check(ss_sql() === 'U', 'Unreadable status must remain unknown.');
$GLOBALS['status_rows'] = status_rows(['Uptime' => '10']);
check(ss_sql() === 'U', 'Status without Questions must remain unknown.');

foreach ([[false, 'U'], [['Variable_name' => 'Questions', 'Value' => ''], 'U'], [['Variable_name' => 'Questions', 'Value' => '9'], '9']] as [$row, $expected]) {
	$GLOBALS['questions_row'] = $row;
	check(sql_questions() === $expected, 'Questions output changed: ' . sql_questions());
}

// a dump without a host option goes to Cacti's database server
check(db_dump_data('fixture_db', 'first second', [], '/tmp/fixture.sql') === 7, 'Dump exit status changed.');
check(($GLOBALS['client_file']['host'] ?? '') === '127.0.0.1' && ($GLOBALS['client_file']['port'] ?? '') === '3307', 'Dump did not target the Cacti database server: ' . json_encode($GLOBALS['client_file']));

// a caller's own host option is left to the command line
check(db_dump_data('fixture_db', '', ['host' => 'db2.example'], '/tmp/fixture.sql') === 7, 'Dump with a host failed.');
check(!isset($GLOBALS['client_file']['host']) && str_contains($GLOBALS['client_command'], '--host=db2.example'), 'Caller host option was replaced.');

$GLOBALS['client_throws'] = true;

try {
	db_dump_data('fixture_db', '', [], '/tmp/fixture.sql');

	throw new \RuntimeException('Dump exception was swallowed.');
} catch (\RuntimeException $error) {
	check($error->getMessage() === 'Fixture client failure', 'Unexpected dump failure.');
}

foreach ($GLOBALS['credential_files'] as $path) {
	check(!file_exists($path), 'Credential file survived client completion.');
}

// line breaks cannot be written, and the client would strip trailing whitespace
foreach (["line\nbreak", "line\rbreak", "nul\0byte", 'trailing ', "trailing\t"] as $invalid) {
	check(db_client_defaults_file('fixture', $invalid) === false, 'Unrepresentable value accepted in an option file.');
}
check(db_client_defaults_file('fixture', ' leading') !== false, 'Leading whitespace refused.');
unlink($GLOBALS['temporary_file']);

// a file that cannot be created, protected or fully written is not handed out
foreach (['tempnam', 'chmod', 'write'] as $failure) {
	$GLOBALS['file_failure']   = $failure;
	$GLOBALS['temporary_file'] = '';

	check(db_client_defaults_file('fixture', 'secret') === false, "Option file returned after a $failure failure.");
	check($GLOBALS['temporary_file'] === '' || !file_exists($GLOBALS['temporary_file']), "Option file left behind after a $failure failure.");
}
$GLOBALS['file_failure']      = '';
$GLOBALS['database_password'] = "invalid\npassword";
check(db_dump_data() === 1, 'Invalid credentials did not fail the dump.');
check($GLOBALS['client_calls'] === 3, 'Unexpected client launches.');

// the standalone script queries Cacti's connection, which under the test
// bootstrap is a sentinel that throws when used, instead of starting a client
ob_start();

try {
	require dirname(__DIR__, 3) . '/scripts/sql.php';

	throw new \RuntimeException('Standalone script did not query the database connection.');
} catch (\RuntimeException $error) {
	check(str_contains($error->getMessage(), 'DB sentinel called'), 'Unexpected standalone failure: ' . $error->getMessage());
} finally {
	ob_end_clean();
}

print json_encode(['calls' => $GLOBALS['client_calls'], 'cleaned_files' => count($GLOBALS['credential_files'])]);
