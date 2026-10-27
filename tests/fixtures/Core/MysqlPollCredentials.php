<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
$root = dirname(__DIR__, 3);
$directory = sys_get_temp_dir() . '/cacti-mysql-poll-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
foreach (['scripts', 'include', 'bin'] as $name) { mkdir($directory . '/' . $name, 0700); }
foreach (['sql.php', 'ss_sql.php'] as $script) {
    $source = $root . '/scripts/' . $script;
    copy($source, $directory . '/scripts/' . $script);
    if (hash_file('sha256', $source) !== hash_file('sha256', $directory . '/scripts/' . $script)) { throw new RuntimeException('Polling source copy changed.'); }
}
// Run byte-identical polling scripts with a disposable bootstrap and actual command transport.
$bootstrap = '<?php $root = ' . var_export($root, true) . ';' . <<<'PHP'
require_once $root . '/tests/Helpers/RequestCoverage.php';
require_once $root . '/tests/Helpers/SourceSlice.php';
require_once $root . '/include/global_constants.php';
$GLOBALS['cacti_fixture_source_map'][dirname(__DIR__) . '/scripts/sql.php'] = $root . '/scripts/sql.php';
$GLOBALS['cacti_fixture_source_map'][dirname(__DIR__) . '/scripts/ss_sql.php'] = $root . '/scripts/ss_sql.php';
cacti_fixture_source_function($root . '/lib/database.php', 'db_client_defaults_file', '');
foreach (['cacti_exec', 'cacti_exec_string', 'cacti_exec_log_describe'] as $function) { cacti_fixture_source_function($root . '/lib/functions.php', $function, ''); }
function cacti_log(...$arguments) {}
$GLOBALS['database_username'] = 'fixture_user';
$GLOBALS['database_password'] = base64_decode(getenv('CACTI_MYSQL_TEST_PASSWORD'), true);
$GLOBALS['database_hostname'] = '127.0.0.1';
PHP;
file_put_contents($directory . '/include/cli_check.php', $bootstrap);
$client = '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (count($argv) !== 3 || !str_starts_with($argv[1], '--defaults-file=') || $argv[2] !== 'status') { exit(3); }
$path = substr($argv[1], strlen('--defaults-file='));
if ((fileperms($path) & 0777) !== 0600) { exit(4); }
$data = parse_ini_file($path, true, INI_SCANNER_RAW);
if ($data['client']['user'] !== 'fixture_user' || stripcslashes($data['client']['password']) !== base64_decode(getenv('CACTI_MYSQL_TEST_PASSWORD'), true) || $data['client']['host'] !== '127.0.0.1') { exit(5); }
file_put_contents(getenv('CACTI_MYSQL_TEST_LOG'), json_encode(['path' => $path, 'argv' => $argv]) . "\n", FILE_APPEND);
if (getenv('CACTI_MYSQL_TEST_MODE') === 'fail') { exit(7); }
if (getenv('CACTI_MYSQL_TEST_MODE') !== 'empty') { print 'Uptime: 10  Threads: 1  Questions: 9  Slow queries: 0'; }
PHP;
file_put_contents($directory . '/bin/mysqladmin', $client);
chmod($directory . '/bin/mysqladmin', 0700);
$cases = 0;
try {
    foreach (['', 'fixture "quoted" # password\\value café', "line\nbreak", "line\rbreak", "nul\0byte"] as $password) {
        foreach (['sql.php', 'ss_sql.php'] as $script) {
            $environment = getenv();
            $environment['PATH'] = $directory . '/bin' . PATH_SEPARATOR . $environment['PATH'];
            $environment['CACTI_MYSQL_TEST_PASSWORD'] = base64_encode($password);
            $environment['CACTI_MYSQL_TEST_LOG'] = $directory . '/calls.jsonl';
            $environment['CACTI_MYSQL_TEST_MODE'] = '';
            $process = proc_open([PHP_BINARY, $directory . '/scripts/' . $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
            if (!is_resource($process)) { throw new RuntimeException('Polling script did not start.'); }
            $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $valid = strpbrk($password, "\r\n\0") === false;
            $expected = !$valid ? 'U' : ($script === 'sql.php' ? '9' : 'Uptime:10 Threads:1 Questions:9 SlowQueries:0');
            if (proc_close($process) !== 0 || $output !== $expected) { throw new RuntimeException('Polling credentials or output changed: ' . $errors); }
            $cases++;
        }
    }
    foreach (['fail', 'empty'] as $mode) {
        foreach (['sql.php', 'ss_sql.php'] as $script) {
            $environment['CACTI_MYSQL_TEST_PASSWORD'] = base64_encode('fixture failure password');
            $environment['CACTI_MYSQL_TEST_MODE'] = $mode;
            $process = proc_open([PHP_BINARY, $directory . '/scripts/' . $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
            if (!is_resource($process)) { throw new RuntimeException('Polling failure request did not start.'); }
            $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($process) !== 0 || $output !== 'U') { throw new RuntimeException('Polling failure must remain unknown: ' . $errors); }
            $cases++;
        }
    }
    $calls = file($directory . '/calls.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($calls) !== 8) { throw new RuntimeException('Invalid credentials launched a client or a status request was lost.'); }
    foreach ($calls as $call) {
        $record = json_decode($call, true, 512, JSON_THROW_ON_ERROR);
        if (file_exists($record['path']) || count($record['argv']) !== 3) { throw new RuntimeException('Credentials survived polling or reached argv.'); }
    }
    print json_encode(['requests' => $cases, 'client_calls' => count($calls), 'credential_files_removed' => true]);
} finally {
    foreach (['scripts/sql.php', 'scripts/ss_sql.php', 'include/cli_check.php', 'bin/mysqladmin', 'calls.jsonl'] as $name) { if (file_exists($directory . '/' . $name)) { unlink($directory . '/' . $name); } }
    foreach (['scripts', 'include', 'bin'] as $name) { rmdir($directory . '/' . $name); }
    rmdir($directory);
}
