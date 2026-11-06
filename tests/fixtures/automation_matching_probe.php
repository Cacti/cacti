<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Isolated process: execute the actual matcher and its SQL against temporary tables.
$dsn = getenv('CACTI_MATCH_DB_DSN');

if (!$dsn || !preg_match('/^mysql:(unix_socket=|host=(127\.0\.0\.1|localhost)(;|$))/', $dsn)) {
	throw new RuntimeException('Set CACTI_MATCH_DB_DSN to an isolated local test database.');
}
$matching_pdo = new PDO($dsn, getenv('CACTI_MATCH_DB_USER') ?: 'root', getenv('CACTI_MATCH_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$matching_pdo->exec("CREATE TEMPORARY TABLE host_template (id INT UNSIGNED PRIMARY KEY, name VARCHAR(150) NOT NULL DEFAULT '')");
$matching_pdo->exec("CREATE TEMPORARY TABLE automation_templates (
	id INT UNSIGNED PRIMARY KEY, host_template INT UNSIGNED NOT NULL DEFAULT 0,
	sysDescr VARCHAR(255) DEFAULT '', sysName VARCHAR(255) DEFAULT '',
	sysOid VARCHAR(60) DEFAULT '', sequence INT UNSIGNED DEFAULT 0)");
$matching_queries = 0;

function db_fetch_row_prepared($sql, $params) {
	global $matching_pdo, $matching_queries;
	$matching_queries++;
	$statement = $matching_pdo->prepare($sql);
	$statement->execute($params);

	return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';
$scenarios = require __DIR__ . '/automation_matching_cases.php';
$results   = [];

foreach ($scenarios as $name => $scenario) {
	$matching_pdo->exec('DELETE FROM automation_templates');
	$matching_pdo->exec('DELETE FROM host_template');
	$insert_template = $matching_pdo->prepare('INSERT INTO host_template (id, name) VALUES (?, ?)');
	$insert_rule     = $matching_pdo->prepare('INSERT INTO automation_templates (id, host_template, sysDescr, sysOid, sysName, sequence) VALUES (?, ?, ?, ?, ?, ?)');

	foreach ($scenario['rules'] as $rule) {
		$insert_template->execute([$rule[0], 'Template ' . $rule[0]]);
		$insert_rule->execute([$rule[0], $rule[0], $rule[1], $rule[2], $rule[3], $rule[4]]);
	}
	$before         = $matching_queries;
	$result         = automation_find_os(...$scenario['device']);
	$actual         = $result === false ? false : (int) $result['id'];
	$results[$name] = ['expected' => $scenario['expected'], 'actual' => $actual, 'queries' => $matching_queries - $before];
}
print json_encode($results, JSON_THROW_ON_ERROR) . PHP_EOL;
