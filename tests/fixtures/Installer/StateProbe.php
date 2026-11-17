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

if ($mode === 'defaults') {
	$settings = ['general' => ['installer_fixture_default' => ['default' => 'fixture']]];
	unset($_SESSION['settings_primed']);
	$success = prime_default_settings();
	print ($success ? 'saved' : 'failed') . ':' . (isset($_SESSION['settings_primed']) ? 'primed' : 'retryable') . PHP_EOL;

	exit(0);
}

if ($mode === 'diagnostics') {
	$marker                  = 'synthetic-private-value-' . bin2hex(random_bytes(8));
	$cache                   = tempnam(sys_get_temp_dir(), 'cacti-installer-diagnostic-');
	$database_upgrade_status = ['file' => $cache];
	$cacti_upgrade_version   = 'diagnostic';

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
			$path    = $config['base_path'] . '/log/' . $file;
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

		$step = (new Installer(['Runtime' => 'Web', 'Step' => $requested]))->getStep();

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
