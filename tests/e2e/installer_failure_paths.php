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

// These child processes execute deliberately modified production files. Their
// injected code must not count as coverage of the checked-in implementation.
if (in_array($scenario, ['schema-postcondition', 'collector-exception'], true)) {
	putenv('CACTI_PR_RAW_COVERAGE=');
	$_ENV['CACTI_PR_RAW_COVERAGE']    = '';
	$_SERVER['CACTI_PR_RAW_COVERAGE'] = '';
}
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
	} elseif ($scenario === 'forced-upgrade-replay') {
		// Probe live metadata after each child process repairs the schema.
		define('IN_CACTI_INSTALL', 1);
		installer_fixture_check(!db_table_exists('user_domains') && !db_table_exists('user_domains_ldap'), 'Fixture is not an already upgraded schema');
		db_execute_prepared('UPDATE version SET cacti = ?', [CACTI_VERSION_FULL]);
		installer_fixture_check(db_execute_prepared('INSERT INTO login_providers (id, name, type, parameters) VALUES (?, ?, ?, ?)',
			[9876, 'Forced replay fixture', 3, '{"fixture":"preserve"}']), 'Could not seed the migrated provider fixture');
		installer_fixture_check(db_execute_prepared('INSERT INTO user_auth (id, username, realm) VALUES (?, ?, ?)',
			[9876, 'forced-replay-fixture', 10876]), 'Could not seed the provider realm fixture');
		installer_fixture_check(db_execute_prepared('INSERT INTO host_template (id, hash, name) VALUES (?, ?, ?)',
			[9876, '0123456789abcdef0123456789abcdef', 'Forced replay template fixture']), 'Could not seed the host template fixture');
		installer_fixture_check(db_execute_prepared('INSERT INTO host (id, host_template_id, description, hostname, disabled) VALUES (?, ?, ?, ?, ?)',
			[9876, 9876, 'Forced replay host fixture', '127.0.0.1', 'on']), 'Could not seed the disabled host fixture');
		$providers = db_fetch_assoc('SELECT * FROM login_providers ORDER BY id');
		$users     = db_fetch_assoc('SELECT id, username, realm FROM user_auth ORDER BY id');
		$hosts     = db_fetch_cell('SELECT COUNT(*) FROM host');
		$templates = db_fetch_cell('SELECT COUNT(*) FROM host_template');
		installer_fixture_check((int) $hosts > 0 && (int) $templates > 0, 'Monitoring fixture is not populated');

		foreach (['1.2.31', '1.2.31', '1.2.28', '1.2.22'] as $forced_version) {
			// A successful replay must still repair current schema changes.
			db_execute('ALTER TABLE host DROP COLUMN snmp_retries');
			$output = [];
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/upgrade_database.php') . ' --forcever=' . escapeshellarg($forced_version) . ' 2>&1', $output, $status);
			installer_fixture_check($status === 0, 'Forced replay failed: ' . implode(PHP_EOL, $output));
			installer_fixture_check(db_column_exists('host', 'snmp_retries', false), 'Forced replay skipped a required schema repair');
			installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') === CACTI_VERSION_FULL, 'Replay did not persist the current source version');
			installer_fixture_check(db_fetch_assoc('SELECT * FROM login_providers ORDER BY id') === $providers, 'Replay changed migrated providers');
			installer_fixture_check(db_fetch_assoc('SELECT id, username, realm FROM user_auth ORDER BY id') === $users, 'Replay changed authentication realms');
			installer_fixture_check(db_fetch_cell('SELECT COUNT(*) FROM host') === $hosts && db_fetch_cell('SELECT COUNT(*) FROM host_template') === $templates, 'Replay lost populated monitoring data');
			installer_fixture_check(!db_table_exists('user_domains') && !db_table_exists('user_domains_ldap'), 'Replay recreated obsolete authentication tables');
			installer_fixture_check((int) db_fetch_cell("SELECT COUNT(*) FROM processes WHERE tasktype = 'install' AND taskname = 'master'") === 0, 'Replay retained the installer lock');
		}
	} elseif ($scenario === 'forced-cli-failure') {
		$path           = CACTI_PATH_INSTALL . '/upgrades/1_2_30.php';
		$backups[$path] = is_file($path) ? file_get_contents($path) : false;
		file_put_contents($path, '<?php /* Fixture: missing migration function after a skipped version. */');
		db_execute_prepared('UPDATE version SET cacti = ?', [CACTI_VERSION_FULL]);
		$output = [];
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/upgrade_database.php') . ' --forcever=1.2.28 2>&1', $output, $status);
		installer_fixture_check($status !== 0 && str_contains(implode(PHP_EOL, $output), 'not found'), 'Forced replay swallowed a genuine migration failure');
		installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') === CACTI_VERSION_FULL, 'Failed replay downgraded the installed version');
		installer_fixture_check((int) db_fetch_cell("SELECT COUNT(*) FROM processes WHERE tasktype = 'install' AND taskname = 'master'") === 0, 'Failed replay retained the installer lock');
	} elseif (in_array($scenario, ['upgrade-warning', 'upgrade-report', 'cli-failure'], true)) {
		foreach (['1_2_29', '1_2_30'] as $target) {
			$path           = CACTI_PATH_INSTALL . '/upgrades/' . $target . '.php';
			$backups[$path] = is_file($path) ? file_get_contents($path) : false;
		}
		file_put_contents(CACTI_PATH_INSTALL . '/upgrades/1_2_29.php', '<?php /* Fixture: missing migration function. */');
		file_put_contents(CACTI_PATH_INSTALL . '/upgrades/1_2_30.php', '<?php function upgrade_to_1_2_30(): void { db_install_execute("SELECT 1"); }');
		db_execute("UPDATE version SET cacti = '1.2.28'");

		if (in_array($scenario, ['upgrade-warning', 'upgrade-report'], true)) {
			if ($scenario === 'upgrade-report') {
				file_put_contents(CACTI_PATH_INSTALL . '/upgrades/1_2_29.php', '<?php function upgrade_to_1_2_29(): void { db_install_execute("SELECT 1"); }');
			}
			$cacti_version_codes = array_intersect_key($cacti_version_codes, array_flip(['1.2.29', '1.2.30']));
			$reflection          = new ReflectionClass(Installer::class);
			$installer           = $reflection->newInstanceWithoutConstructor();
			$reflection->getProperty('old_cacti_version')->setValue($installer, '1.2.28');
			$reflection->getProperty('runReport')->setValue($installer, new \Cacti\Installer\RunReport());
			$error = $reflection->getMethod('upgradeDatabase')->invoke($installer);
			installer_fixture_check(($error === '') === ($scenario === 'upgrade-report'), 'Migration success was reported incorrectly');
			$report = json_decode(read_config_option('maintenance_lastrun_installer_report', true), true, flags: JSON_THROW_ON_ERROR);
			installer_fixture_check(array_column($report['operations'], 'version') === ($scenario === 'upgrade-report' ? ['1.2.29', '1.2.30'] : ['1.2.29']), 'Installer report omitted the migration versions');
			installer_fixture_check($report['operations'][0]['succeeded'] === ($scenario === 'upgrade-report'), 'Installer report lost the failed migration');
		} else {
			$output = [];
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(CACTI_PATH_CLI . '/upgrade_database.php') . ' --forcever=1.2.28 2>&1', $output, $status);
			installer_fixture_check($status !== 0, 'CLI migration failure returned success');
			installer_fixture_check(str_contains(implode(PHP_EOL, $output), 'not found'), 'CLI fixture did not exercise the missing migration');
		}

		if ($scenario !== 'upgrade-report') {
			installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') === '1.2.28', 'Incomplete migration advanced the recorded version');
		}
	} elseif ($scenario === 'dependency-preflight') {
		$asset           = CACTI_PATH_INCLUDE . '/js/jquery.js';
		$backups[$asset] = file_get_contents($asset);
		unlink($asset);
		$before = db_fetch_cell('SELECT cacti FROM version');
		$result = (new \Cacti\Installer\CommandRunner())->run([PHP_BINARY, CACTI_PATH_CLI . '/install_cacti.php', '--accept-eula', '--install', '--force']);
		installer_fixture_check($result['exitCode'] !== 0, 'Missing frontend asset did not block installation');
		installer_fixture_check(str_contains(implode(PHP_EOL, $result['output']), 'jquery.js'), 'Preflight omitted actionable asset details');
		installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') === $before, 'Dependency failure changed the installed version');
	} elseif ($scenario === 'schema-postcondition') {
		$path           = CACTI_PATH_INSTALL . '/upgrades/1_3_0.php';
		$backups[$path] = file_get_contents($path);
		$changed        = preg_replace("/^\\s*db_install_add_column\\('data_template', \\['name' => 'templates'.*\\);\\R/m", '', $backups[$path], 1, $changes);
		installer_fixture_check($changes === 1 && is_string($changed), 'Schema fixture did not find the template column migration');
		file_put_contents($path, $changed);
		$result = (new \Cacti\Installer\CommandRunner())->run([PHP_BINARY, CACTI_PATH_CLI . '/upgrade_database.php', '--forcever=' . $version]);
		installer_fixture_check($result['exitCode'] !== 0, 'Missing schema postcondition returned CLI success');
		installer_fixture_check(str_contains(implode(PHP_EOL, $result['output']), 'Missing required columns: data_template.templates'), 'CLI omitted the missing column diagnosis');
		installer_fixture_check(db_fetch_cell('SELECT cacti FROM version') !== CACTI_VERSION_FULL, 'Incomplete schema advanced to the target version');
		$report = json_decode((string) read_config_option('maintenance_lastrun_installer_report', true), true);
		installer_fixture_check(($report['state'] ?? '') === 'failed', 'Missing schema did not preserve failed diagnostics');
	} elseif ($scenario === 'collector-exception') {
		$path           = CACTI_PATH_LIBRARY . '/installer.php';
		$backups[$path] = file_get_contents($path);
		$changed        = str_replace('static fn () => Installer::fullSyncDataCollectors()', "static fn () => throw new RuntimeException('Installer collector fixture')", $backups[$path], $changes);
		installer_fixture_check($changes === 1, 'Collector exception fixture did not find its injection boundary');
		file_put_contents($path, $changed);
		$result = (new \Cacti\Installer\CommandRunner())->run([PHP_BINARY, CACTI_PATH_CLI . '/install_cacti.php', '--accept-eula', '--install', '--force', '--path=php_binary:' . PHP_BINARY]);
		installer_fixture_check($result['exitCode'] !== 0, 'Unexpected collector exception returned CLI success');
		$report = json_decode((string) read_config_option('maintenance_lastrun_installer_report', true), true);
		installer_fixture_check(($report['state'] ?? '') === 'failed', 'Collector exception did not preserve failed diagnostics');
		installer_fixture_check((int) db_fetch_cell("SELECT COUNT(*) FROM processes WHERE tasktype = 'install' AND taskname = 'master'") === 0, 'Collector exception retained the installer lock');
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
