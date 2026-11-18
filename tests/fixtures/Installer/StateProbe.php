<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Browser-runtime installer requests, made from the command line against the installer
// database that tests/e2e/installer-coverage.sh prepares. Usage: StateProbe.php MODE
//   poll   the install page polling a run that failed          -> the failure report stays (99)
//   load   a fresh page load after a failed run                -> the wizard restarts (1), and
//          the template step renders its homepage links
//   jump   a request at the first step asking for the install  -> refused, the stored step stays
//   stale  a stored wizard step on an installed system         -> completion (98)
//   reach  requests that move between steps                    -> only the steps that follow
//   empty  a database without a version row                    -> setup guidance without the password
//   convert a table selected for conversion                     -> converted, and no longer selected
//   lock   a second installer started while one holds the lock -> refused
//   legacy a database that still holds the new_install marker  -> not a pre-0.8 database
define('IN_CACTI_INSTALL', 1);

chdir(dirname(__DIR__, 3));
require_once 'include/cli_check.php';
require_once 'install/functions.php';
require_once 'lib/api_automation.php';
require_once 'lib/api_automation_tools.php';
require_once 'lib/api_data_source.php';
require_once 'lib/api_device.php';
require_once 'lib/data_query.php';
require_once 'lib/import.php';
require_once 'lib/installer.php';
require_once 'lib/poller.php';
require_once 'lib/utility.php';

$mode     = $argv[1] ?? '';

if ($mode === 'diagnostics') {
	$marker = 'synthetic-private-value-' . bin2hex(random_bytes(8));
	$cache = tempnam(sys_get_temp_dir(), 'cacti-installer-diagnostic-');
	$database_upgrade_status = ['file' => $cache];
	$cacti_upgrade_version = 'diagnostic';

	try {
		db_execute('CREATE TEMPORARY TABLE installer_diagnostic_fixture (value varchar(100) PRIMARY KEY)');
		db_execute_prepared('INSERT INTO installer_diagnostic_fixture VALUES (?)', [$marker], false);
		ob_start();
		$status = db_install_execute('INSERT INTO installer_diagnostic_fixture VALUES (?)', [$marker]);
		ob_end_clean();
		$redacted = $status === DB_STATUS_ERROR && !str_contains((string) $database_last_error, $marker)
			&& !str_contains((string) file_get_contents($cache), $marker)
			&& !str_contains(json_encode($database_upgrade_status), $marker);

		// Ordinary callers retain their existing diagnostic contract.
		db_execute_prepared('INSERT INTO installer_diagnostic_fixture VALUES (?)', [$marker], false);
		$ordinary = str_contains((string) $database_last_error, $marker);

		$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
		(new ReflectionMethod(Installer::class, 'processParameters'))->invoke($installer, ['unknown-fixture' => $marker]);
		$unknown = true;
		foreach (['install-badkey.log', 'install-complete.log'] as $file) {
			$path = $config['base_path'] . '/log/' . $file;
			$unknown = $unknown && is_file($path) && !str_contains((string) file_get_contents($path), $marker);
		}
		print 'installer=' . ($redacted ? 'redacted' : 'bad') . ' ordinary=' . ($ordinary ? 'retained' : 'bad') . ' unknown=' . ($unknown ? 'redacted' : 'bad') . PHP_EOL;
	} finally {
		unlink($cache);
	}

	exit(0);
}

if ($mode === 'reach') {
	// Tool locations that exist, so the steps are not held back at the binary locations
	foreach (['php_binary', 'rrdtool', 'snmpwalk', 'snmpget', 'snmpbulkwalk', 'snmpgetnext', 'snmptrap', 'fping'] as $tool) {
		set_config_option('path_' . $tool, $tool === 'php_binary' ? PHP_BINARY : '/usr/bin/true');
	}

	// stored step, requested step, step the wizard ends on
	foreach ([[4, 8, 8], [5, 9, 9], [9, 10, 10], [10, 97, 97], [3, 97, 3], [2, 100, 2], [2, -1, 2], [8, 3, 3]] as [$stored, $requested, $expected]) {
		set_config_option('install_step', $stored);

		$candidate = new Installer(['Runtime' => 'Web', 'Step' => $requested]);
		$step = (int) $candidate->getStep();
		if ($step !== $expected) {
			fwrite(STDERR, json_encode($candidate->getErrors()) . PHP_EOL);
		}

		print "$stored>$requested=" . ($step === $expected ? 'ok' : "bad($step)") . PHP_EOL;
	}

	exit(0);
}

if ($mode === 'empty') {
	$installer = new Installer(['Runtime' => 'Web']);
	$html      = $installer->getData()['Html'] ?? '';

	print 'guidance=' . (str_contains($html, 'GRANT SELECT') && !str_contains($html, 'IDENTIFIED BY') ? 'ok' : 'bad') . PHP_EOL;

	exit(0);
}

if ($mode === 'convert') {
	// A table that still has the old character set is selected for conversion
	db_execute('ALTER TABLE cdef CONVERT TO CHARACTER SET latin1');
	set_config_option('path_php_binary', PHP_BINARY);
	set_config_option('install_table_cdef', 'cdef');

	(new ReflectionMethod(Installer::class, 'convertDatabase'))->invoke((new ReflectionClass(Installer::class))->newInstanceWithoutConstructor());

	print 'collation=' . db_fetch_cell("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdef'");
	print ' selected=' . db_fetch_cell("SELECT COUNT(*) FROM settings WHERE name = 'install_table_cdef' AND value <> ''") . PHP_EOL;

	exit(0);
}

if ($mode === 'lock') {
	// Hold settings writes so a real CLI installer keeps its process lock
	// while a different upgrade entry point attempts to start.
	$key = "$database_hostname:$database_port:$database_default";
	$pdo = $database_sessions[$key];
	$pdo->query("SELECT GET_LOCK('installer_fixture_owner', 0)")->fetchColumn();
	$pdo->exec("CREATE TRIGGER installer_lock_fixture BEFORE INSERT ON settings FOR EACH ROW BEGIN IF NEW.name = 'install_eula' THEN DO GET_LOCK('installer_fixture_owner', 15); DO RELEASE_LOCK('installer_fixture_owner'); END IF; END");
	$owner = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . ini_get('auto_prepend_file'), 'cli/install_cacti.php', '--accept-eula', '--install', '--force',
		'--path=php_binary:' . PHP_BINARY, '--path=rrdtool:/usr/bin/true', '--path=snmpwalk:/usr/bin/true',
		'--path=snmpget:/usr/bin/true', '--path=snmpbulkwalk:/usr/bin/true', '--path=snmpgetnext:/usr/bin/true', '--path=snmptrap:/usr/bin/true'],
		[1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $owner_pipes);
	$process = false;

	try {
		$deadline = microtime(true) + 10;

		do {
			$owner_pid = db_fetch_cell("SELECT pid FROM processes WHERE tasktype = 'install' AND taskname = 'master'");

			if ($owner_pid) {
				break;
			}

			usleep(50000);
		} while (microtime(true) < $deadline && proc_get_status($owner)['running']);

		if (!$owner_pid) {
			throw new RuntimeException('The real installer did not acquire its lock');
		}

		$process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'auto_prepend_file=' . ini_get('auto_prepend_file'), 'cli/upgrade_database.php', '--forcever=1.2.31'],
			[1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		stream_set_blocking($pipes[1], false);
		$output   = '';
		$deadline = microtime(true) + 10;

		do {
			$output .= stream_get_contents($pipes[1]);
			$status = proc_get_status($process);

			if (!$status['running']) {
				break;
			}

			usleep(50000);
		} while (microtime(true) < $deadline);

		if ($status['running']) {
			throw new RuntimeException('The second installer did not refuse the active lock');
		}

		$output .= stream_get_contents($pipes[1]);
		print 'exit=' . $status['exitcode'] . ' refused=' . (str_contains($output, 'already in progress') ? 'yes' : 'no') . PHP_EOL;
	} finally {
		if (is_resource($process)) {
			proc_terminate($process);
			fclose($pipes[1]);
			proc_close($process);
		}

		proc_terminate($owner);
		$pdo->query("SELECT RELEASE_LOCK('installer_fixture_owner')")->fetchColumn();
		proc_close($owner);
		$pdo->exec('DROP TRIGGER installer_lock_fixture');
		unregister_process('install', 'master', 0);
	}

	exit(0);
}

if ($mode === 'legacy') {
	print 'legacy=' . var_export((new Installer(['Runtime' => 'Web']))->isPre_v0_8_UpgradeNeeded(), true) . PHP_EOL;

	exit(0);
}

$requests = [
	'poll'  => ['Runtime' => 'Web', 'Step' => Installer::STEP_INSTALL],
	'load'  => ['Runtime' => 'Web'],
	'jump'  => ['Runtime' => 'Web', 'Step' => Installer::STEP_INSTALL],
	'stale' => ['Runtime' => 'Web', 'Step' => Installer::STEP_PERMISSION_CHECK],
];

if (!isset($requests[$mode])) {
	fwrite(STDERR, "unknown mode\n");

	exit(2);
}

$installer = new Installer($requests[$mode]);

print 'step=' . $installer->getStep() . PHP_EOL;

if ($mode === 'load') {
	$html = $installer->processStepTemplateInstall();

	print 'links=' . (str_contains($html, 'https://https://') ? 'doubled' : 'ok') . PHP_EOL;
}
