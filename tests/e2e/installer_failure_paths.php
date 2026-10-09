<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

// This fixture mutates schema and migration files in a disposable Docker copy.
if (getenv('CACTI_INSTALLER_FAILURE_FIXTURE') !== '1') {
	fwrite(STDERR, "Run through installer_resilience_docker.sh only.\n");
	exit(1);
}

require __DIR__ . '/../../include/cli_check.php';
require_once CACTI_PATH_INSTALL . '/functions.php';
require_once CACTI_PATH_LIBRARY . '/installer.php';

class InstallerFailureStatement extends PDOStatement {
	public static string $failQuery = '';

	public function execute(?array $params = null) : bool {
		$query = implode(' ', preg_split('/\s+/', trim($this->queryString)) ?: []);

		if (self::$failQuery !== '' && str_starts_with($query, self::$failQuery)) {
			throw new PDOException('Injected installer fixture read failure', 1142);
		}

		return parent::execute($params);
	}
}

function installer_fixture_check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$scenario = $_SERVER['argv'][1] ?? '';
$key      = "$database_hostname:$database_port:$database_default";
$pdo      = $database_sessions[$key];
$version  = db_fetch_cell('SELECT cacti FROM version');
$backups  = [];

try {
	if (in_array($scenario, ['domain-read', 'ldap-read', 'count-read'], true)) {
		require_once CACTI_PATH_INSTALL . '/upgrades/1_3_0.php';
		$pdo->exec('CREATE TABLE user_domains (domain_id INT PRIMARY KEY, domain_name VARCHAR(64), type INT, enabled VARCHAR(2), debug VARCHAR(2), defdomain INT, user_id INT)');
		$pdo->exec("INSERT INTO user_domains VALUES (987, 'Installer fixture', 1, 'on', '', 0, 0)");
		$pdo->exec('CREATE TABLE user_domains_ldap (domain_id INT PRIMARY KEY)');

		// LDAP reads fail before accessing the deliberately minimal fixture row.
		if ($scenario === 'ldap-read') {
			$pdo->exec('INSERT INTO user_domains_ldap VALUES (987)');
		}
		$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [InstallerFailureStatement::class]);
		InstallerFailureStatement::$failQuery = match ($scenario) {
			'domain-read' => 'SELECT * FROM user_domains',
			'ldap-read'   => 'SELECT * FROM user_domains_ldap',
			'count-read'  => 'SELECT COUNT(*) FROM login_providers',
		};
		$database_upgrade_status = [];
		$cacti_upgrade_version   = '1.3.0';
		login_providers_convert_1_3_0();
		InstallerFailureStatement::$failQuery = '';
		installer_fixture_check(count($pdo->query("SHOW TABLES LIKE 'user_domains%'")->fetchAll()) === 2, 'Failed read destroyed legacy authentication tables');
		installer_fixture_check(in_array(DB_STATUS_ERROR, array_column($database_upgrade_status['1.3.0'] ?? [], 'status'), true), 'Failed read was not recorded in migration status');
		installer_fixture_check((int) $pdo->query('SELECT COUNT(*) FROM user_domains')->fetchColumn() === 1, 'Legacy authentication row was lost');
	} elseif (in_array($scenario, ['upgrade-warning', 'cli-failure'], true)) {
		foreach (['1_2_29', '1_2_30'] as $target) {
			$path           = CACTI_PATH_INSTALL . '/upgrades/' . $target . '.php';
			$backups[$path] = is_file($path) ? file_get_contents($path) : false;
		}
		file_put_contents(CACTI_PATH_INSTALL . '/upgrades/1_2_29.php', '<?php /* Fixture: missing migration function. */');
		file_put_contents(CACTI_PATH_INSTALL . '/upgrades/1_2_30.php', '<?php function upgrade_to_1_2_30(): void { db_install_execute("SELECT 1"); }');
		db_execute("UPDATE version SET cacti = '1.2.28'");

		if ($scenario === 'upgrade-warning') {
			$cacti_version_codes = array_intersect_key($cacti_version_codes, array_flip(['1.2.29', '1.2.30']));
			$reflection          = new ReflectionClass(Installer::class);
			$installer           = $reflection->newInstanceWithoutConstructor();
			$reflection->getProperty('old_cacti_version')->setValue($installer, '1.2.28');
			$error = $reflection->getMethod('upgradeDatabase')->invoke($installer);
			installer_fixture_check($error !== '', 'Incomplete migration reported success');
		} else {
			$output = [];
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/upgrade_database.php') . ' --forcever=1.2.28 2>&1', $output, $status);
			installer_fixture_check($status !== 0, 'CLI migration failure returned success');
			installer_fixture_check(str_contains(implode(PHP_EOL, $output), 'not found'), 'CLI fixture did not exercise the missing migration');
		}
		installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') === '1.2.28', 'Incomplete migration advanced the recorded version');
	} elseif ($scenario === 'composer-failure') {
		$composer = tempnam(sys_get_temp_dir(), 'installer-composer-');
		installer_fixture_check($composer !== false, 'Could not create Composer fixture');
		file_put_contents($composer, '#!' . PHP_BINARY . PHP_EOL . '<?php if (in_array("--dry-run", $argv, true)) { echo "Package operations: 1 install, 0 updates, 0 removals\n"; exit(0); } echo "download failed\n"; exit(7);');
		chmod($composer, 0700);
		set_config_option('path_composer', $composer);
		$logPath = read_config_option('path_cactilog');

		if ($logPath === '') {
			$logPath = CACTI_PATH_LOG . '/cacti.log';
		}
		$offset     = is_file($logPath) ? filesize($logPath) : 0;
		$reflection = new ReflectionClass(Installer::class);
		$installer  = $reflection->newInstanceWithoutConstructor();
		$reflection->getMethod('refreshVendorDependencies')->invoke($installer);
		$log = substr(file_get_contents($logPath), $offset);
		installer_fixture_check(str_contains($log, 'Composer refresh did not complete'), 'Failed Composer command did not log a warning');
		installer_fixture_check(!str_contains($log, 'Composer dependencies refreshed.'), 'Failed Composer command reported success');
		unlink($composer);
	} elseif ($scenario === 'installer-lock') {
		installer_fixture_check(register_process_start('install', 'master', 0, 86400), 'Could not register lock fixture');
		set_config_option('install_eula', 'lock-owner-fixture');
		set_config_option('log_install_json', '3');

		try {
			$output = [];
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/upgrade_database.php') . ' --forcever=1.2.28 2>&1', $output, $status);
			installer_fixture_check($status !== 0 && str_contains(implode(PHP_EOL, $output), 'already in progress'), 'CLI upgrader ignored an active installer');
			$arguments = ['--accept-eula', '--install', '--force', '--debug=json:5', '--path=php_binary:' . PHP_BINARY, '--path=rrdtool:/usr/bin/rrdtool', '--path=snmpwalk:/usr/bin/snmpwalk', '--path=snmpget:/usr/bin/snmpget', '--path=snmpbulkwalk:/usr/bin/snmpbulkwalk', '--path=snmpgetnext:/usr/bin/snmpgetnext', '--path=fping:/usr/bin/fping'];
			$output    = [];
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/install_cacti.php') . ' ' . implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1', $output, $status);
			installer_fixture_check($status !== 0 && str_contains(implode(PHP_EOL, $output), 'already in progress'), 'CLI installer ignored an active installer');
			installer_fixture_check(db_fetch_cell("SELECT value FROM settings WHERE name = 'install_eula'") === 'lock-owner-fixture', 'Blocked CLI installer overwrote the active installer settings');
			installer_fixture_check(db_fetch_cell("SELECT value FROM settings WHERE name = 'log_install_json'") === '3', 'Blocked CLI installer overwrote the active installer logging settings');
		} finally {
			unregister_process('install', 'master', 0);
		}
	} else {
		throw new RuntimeException('Unknown fixture scenario');
	}

	print "PASS: $scenario" . PHP_EOL;
} finally {
	InstallerFailureStatement::$failQuery = '';

	foreach ($backups as $path => $contents) {
		if ($contents === false) {
			unlink($path);
		} else {
			file_put_contents($path, $contents);
		}
	}
	db_execute_prepared('UPDATE version SET cacti = ?', [$version]);
}
