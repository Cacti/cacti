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

namespace CactiMysqlClientFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

$GLOBALS['database_username'] = 'fixture_user';
$GLOBALS['database_password'] = 'fixture "quoted" # password\\value';
$GLOBALS['database_hostname'] = '127.0.0.1';
$GLOBALS['database_port']     = 3307;
$GLOBALS['database_default']  = 'fixture_db';
$GLOBALS['credential_files']  = [];
$GLOBALS['client_calls']      = 0;
$GLOBALS['client_throws']     = false;

foreach (['db_client_defaults_file', 'db_dump_data'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/database.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/ss_sql.php', 'ss_sql', __NAMESPACE__);

function check(bool $condition, string $message): void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}
function inspect_command(string $command): void {
	check(!str_contains($command, $GLOBALS['database_password']) && !str_contains($command, '--password') && !str_contains($command, 'fixture_user'), 'Credentials leaked into argv.');
	check((bool) preg_match('/^(?:mysqladmin|.*dump) --defaults-file=\'([^\']+)\' /', $command, $match), 'Private defaults file was not the first client option.');
	$path = $match[1];
	clearstatcache(true, $path);
	check((fileperms($path) & 0777) === 0600, 'Credential file permissions are not private.');
	$data = parse_ini_file($path, true, INI_SCANNER_RAW);
	check($data['client']['user'] === 'fixture_user', 'Client username changed.');
	check(stripcslashes($data['client']['password']) === $GLOBALS['database_password'], 'Client password quoting changed.');
	$GLOBALS['credential_files'][] = $path;
	$GLOBALS['client_calls']++;

	if ($GLOBALS['client_throws']) {
		throw new \RuntimeException('Fixture client failure');
	}
}
function shell_exec(string $command): string {
	inspect_command($command);

	return ($GLOBALS['client_empty'] ?? false) ? '' : 'Uptime: 10  Threads: 1  Questions: 9  Slow queries: 0';
}
function exec(string $command, &$output, &$status): void {
	inspect_command($command);
	$output = [];
	$status = 7;
}

$start = hrtime(true);

for ($pass = 0; $pass < 50; $pass++) {
	check(ss_sql() === 'Uptime:10 Threads:1 Questions:9 SlowQueries:0', 'SQL script output changed.');
}
$GLOBALS['client_empty'] = true;
check(ss_sql() === 'U', 'Empty client output must remain unknown.');
$GLOBALS['client_empty'] = false;
check(db_dump_data('fixture_db', 'first second', [], '/tmp/fixture.sql') === 7, 'Dump exit status changed.');
$GLOBALS['client_throws'] = true;

try {
	ss_sql();

	throw new \RuntimeException('Client exception was swallowed.');
} catch (\RuntimeException $error) {
	check($error->getMessage() === 'Fixture client failure', 'Unexpected script failure.');
}

try {
	db_dump_data('fixture_db', '', [], '/tmp/fixture.sql');

	throw new \RuntimeException('Dump exception was swallowed.');
} catch (\RuntimeException $error) {
	check($error->getMessage() === 'Fixture client failure', 'Unexpected dump failure.');
}

foreach ($GLOBALS['credential_files'] as $path) {
	check(!file_exists($path), 'Credential file survived client completion.');
}

foreach (["line\nbreak", "line\rbreak", "nul\0byte"] as $invalid) {
	check(db_client_defaults_file('fixture', $invalid) === false, 'Control characters accepted in an option file.');
}
$GLOBALS['database_password'] = "invalid\npassword";
check(ss_sql() === 'U', 'Invalid credentials did not return unknown output.');
check(db_dump_data() === 1, 'Invalid credentials did not fail the dump.');
check($GLOBALS['client_calls'] === 54, 'Unexpected client launches.');
// Exercise the standalone script's real shell pipeline with an isolated client.
$client_dir = sys_get_temp_dir() . '/cacti-mysql-client-' . bin2hex(random_bytes(8));
mkdir($client_dir, 0700);
$client_log = $client_dir . '/argv.json';
$client     = '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (!isset($argv[1]) || !str_starts_with($argv[1], '--defaults-file=')) { exit(1); }
$path = substr($argv[1], strlen('--defaults-file='));
if ((fileperms($path) & 0777) !== 0600) { exit(2); }
file_put_contents(getenv('CACTI_MYSQL_CLIENT_LOG'), json_encode(['argv' => $argv, 'path' => $path]));
print 'Uptime: 10  Threads: 1  Questions: 9  Slow queries: 0';
PHP;
file_put_contents($client_dir . '/mysqladmin', $client);
chmod($client_dir . '/mysqladmin', 0700);
$old_path = getenv('PATH');
putenv('PATH=' . $client_dir . PATH_SEPARATOR . $old_path);
putenv('CACTI_MYSQL_CLIENT_LOG=' . $client_log);
$GLOBALS['database_password'] = 'fixture standalone password';

try {
	ob_start();
	require dirname(__DIR__, 3) . '/scripts/sql.php';
	$single_output = ob_get_clean();
	check($single_output === '9', 'Standalone SQL polling output changed.');
	$record = json_decode(file_get_contents($client_log), true, 512, JSON_THROW_ON_ERROR);
	check(!file_exists($record['path']), 'Standalone script left credentials behind.');
	check(count($record['argv']) === 3 && $record['argv'][2] === 'status', 'Standalone script exposed extra client arguments.');
} finally {
	putenv('PATH=' . $old_path);
	putenv('CACTI_MYSQL_CLIENT_LOG');
	@unlink($client_log);
	unlink($client_dir . '/mysqladmin');
	rmdir($client_dir);
}
print json_encode(['calls' => $GLOBALS['client_calls'], 'cleaned_files' => count($GLOBALS['credential_files']), 'elapsed_seconds' => (hrtime(true) - $start) / 1e9]);
