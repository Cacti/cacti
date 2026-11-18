<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/bootstrap-unit.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
$GLOBALS['cacti_fixture_contract'] = ['name' => 'sql-query-status', 'complete' => false, 'assertions' => 0, 'producers' => ['scripts/sql.php']];
require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

// SQLite executes the status lookup through Cacti's real database helpers.
// Translate only MySQL's SHOW syntax at the PDO boundary.
class ScriptQueryStatusPDO extends FakeMySQLPDO {
	public int $statusReads = 0;

	public function prepare(string $statement, array $options = []): PDOStatement|false {
		if ($statement === "SHOW GLOBAL STATUS LIKE 'Queries'") {
			$this->statusReads++;
			$statement = "SELECT Variable_name, Value FROM global_status WHERE Variable_name = 'Queries'";
		}

		return parent::prepare($statement, $options);
	}
}

$database_hostname = 'unit-script-status';
$database_port     = 0;
$database_default  = 'unit-script-status';
$db                = new ScriptQueryStatusPDO();
$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
$db->exec('CREATE TABLE global_status (Variable_name TEXT PRIMARY KEY, Value TEXT)');
$database_sessions = ["$database_hostname:$database_port:$database_default" => $db];

// Execute the complete unchanged query body after the CLI bootstrap boundary.
$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/sql.php');
$begin  = strpos($source, '/* mysqladmin status');

if ($begin === false) {
	throw new RuntimeException('Cannot locate the SQL status query body.');
}
$temporary = tempnam(sys_get_temp_dir(), 'cacti-sql-query-');
file_put_contents($temporary, '<?php ' . str_repeat("\n", substr_count(substr($source, 0, $begin), "\n")) . substr($source, $begin));
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath(dirname(__DIR__, 3) . '/scripts/sql.php');

try {
	foreach (['12345678901234567890' => '12345678901234567890', '0' => '0', 'invalid' => 'U', '' => 'U'] as $value => $expected) {
		$db->exec('DELETE FROM global_status');
		$insert = $db->prepare('INSERT INTO global_status VALUES (?, ?)');
		$insert->execute(['Queries', (string) $value]);
		ob_start();
		require $temporary;
		$result = ob_get_clean();

		if ($result !== $expected) {
			throw new RuntimeException('The SQL CLI must preserve numeric counters exactly and report invalid data as unknown.');
		}
	}
	$db->exec('DELETE FROM global_status');
	ob_start();
	require $temporary;
	$result = ob_get_clean();

	if ($result !== 'U' || $db->statusReads !== 5) {
		throw new RuntimeException('A missing counter must be unknown and each CLI query must use the existing connection exactly once.');
	}
	$GLOBALS['cacti_fixture_contract']['assertions'] = 5;
	$GLOBALS['cacti_fixture_contract']['complete']   = true;
	print "PASS: 5 SQL status entrypoint contracts\n";
} finally {
	unlink($temporary);
}
