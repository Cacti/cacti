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
// Both names are connection-local temporary shadows, never persistent tables.
$pdo->exec(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $match[0]));
$pdo->exec('CREATE TEMPORARY TABLE automation_networks (id INT PRIMARY KEY, subnet_range VARCHAR(1024), ignore_ips TEXT)');
$pdo->exec("INSERT INTO automation_networks VALUES (7, '192.0.2.0/30,192.0.2.0/29', ''), (8, '192.0.2.2,192.0.2.9', '')");
$writes = 0;

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

function db_execute(string $sql): bool {
	$GLOBALS['writes']++;

	return $GLOBALS['pdo']->exec($sql) !== false;
}

function cacti_log(mixed ...$args): void {
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';
// Suppress ordinary debug output, while preserving the actual DB operation.
ob_start();

try {
	automation_primeIPAddressTable(7);
	$first = $pdo->query('SELECT * FROM automation_ips ORDER BY ip_address')->fetchAll(PDO::FETCH_ASSOC);
	$pdo->exec("UPDATE automation_ips SET hostname='claimed-fixture', pid=123, status=1, thread=3 WHERE ip_address='192.0.2.2'");
	$before = $pdo->query('SELECT * FROM automation_ips ORDER BY ip_address')->fetchAll(PDO::FETCH_ASSOC);
	automation_primeIPAddressTable(7);
	$repeated = $pdo->query('SELECT * FROM automation_ips ORDER BY ip_address')->fetchAll(PDO::FETCH_ASSOC);
	automation_primeIPAddressTable(8);
	$other  = $pdo->query("SELECT * FROM automation_ips WHERE ip_address='192.0.2.2'")->fetch(PDO::FETCH_ASSOC);
	$new    = $pdo->query("SELECT * FROM automation_ips WHERE ip_address='192.0.2.9'")->fetch(PDO::FETCH_ASSOC);
	$result = ['completed' => true, 'first' => $first, 'before' => $before, 'repeated' => $repeated,
		'other'               => $other, 'new' => $new, 'writes' => $writes];
} catch (PDOException $error) {
	$result = ['completed' => false, 'sqlstate' => $error->getCode(),
		'addresses'           => $pdo->query('SELECT ip_address FROM automation_ips ORDER BY ip_address')->fetchAll(PDO::FETCH_COLUMN)];
} finally {
	ob_end_clean();
}
echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
