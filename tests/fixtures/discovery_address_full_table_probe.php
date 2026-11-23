<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

$dsn = getenv('CACTI_MATCH_DB_DSN');

if (!$dsn || !preg_match('/^mysql:(unix_socket=|host=(127\.0\.0\.1|localhost)(;|$))/', $dsn)) {
	throw new RuntimeException('Use an isolated local discovery fixture database.');
}
$pdo    = new PDO($dsn, getenv('CACTI_MATCH_DB_USER') ?: 'root', getenv('CACTI_MATCH_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = file_get_contents(dirname(__DIR__, 2) . '/cacti.sql');

if ($schema === false || !preg_match('/CREATE TABLE `automation_ips` \(.*?\) ENGINE=MEMORY[^;]*;/s', $schema, $match)) {
	throw new RuntimeException('Cannot locate native discovery address schema.');
}
// A MEMORY table takes its size limit from the session value at creation, so the
// smallest limit makes the server refuse rows long before a /22 is primed.
$pdo->exec('SET SESSION max_heap_table_size = 16384');
// Both names are connection-local temporary shadows, never persistent tables.
$pdo->exec(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $match[0]));
$pdo->exec('CREATE TEMPORARY TABLE automation_networks (id INT PRIMARY KEY, subnet_range VARCHAR(1024))');
$pdo->exec("INSERT INTO automation_networks VALUES (7, '192.0.2.0/30'), (8, '10.20.0.0/22,192.0.2.8/30')");
$writes = 0;
$errors = [];
$logs   = [];

const CACTI_WEB               = true;
const POLLER_VERBOSITY_MEDIUM = 3;
const POLLER_VERBOSITY_NONE   = 0;

function cacti_sizeof(mixed $value): int {
	return is_array($value) ? count($value) : 0;
}

function db_fetch_cell_prepared(string $sql, array $params): mixed {
	$statement = $GLOBALS['pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchColumn();
}

// Like the real helper, a failed statement is reported as false, not thrown.
function db_execute_prepared(string $sql, array $params = []): bool {
	$GLOBALS['writes']++;

	try {
		return $GLOBALS['pdo']->prepare($sql)->execute($params);
	} catch (PDOException $error) {
		$GLOBALS['errors'][] = (int) $error->errorInfo[1];

		return false;
	}
}

// automation_debug() also logs through cacti_log(); only errors matter here.
function cacti_log(mixed ...$args): void {
	if (str_starts_with((string) $args[0], 'ERROR')) {
		$GLOBALS['logs'][] = (string) $args[0];
	}
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';
// Suppress ordinary debug output, while preserving the actual DB operation.
ob_start();

try {
	$small       = automation_primeIPAddressTable(7);
	$small_rows  = (int) $pdo->query('SELECT COUNT(*) FROM automation_ips WHERE network_id = 7')->fetchColumn();
	$small_logs  = $logs;
	$before      = $writes;
	$large       = automation_primeIPAddressTable(8);
	$later_range = (int) $pdo->query("SELECT COUNT(*) FROM automation_ips WHERE ip_address LIKE '192.0.2.%' AND network_id = 8")->fetchColumn();
	$result      = ['small' => $small, 'small_rows' => $small_rows, 'small_logs' => $small_logs, 'large' => $large,
		'large_writes'     => $writes - $before, 'errors' => $errors, 'logs' => $logs, 'later_range' => $later_range];
} finally {
	ob_end_clean();
}
echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
