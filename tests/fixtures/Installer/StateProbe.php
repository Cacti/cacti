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

if ($mode === 'reach') {
	// Tool locations that exist, so the steps are not held back at the binary locations
	foreach (['php_binary', 'rrdtool', 'snmpwalk', 'snmpget', 'snmpbulkwalk', 'snmpgetnext', 'snmptrap', 'fping'] as $tool) {
		set_config_option('path_' . $tool, $tool === 'php_binary' ? PHP_BINARY : '/bin/true');
	}

	// stored step, requested step, step the wizard ends on
	foreach ([[4, 8, 8], [5, 9, 9], [9, 10, 10], [10, 97, 97], [3, 97, 3], [2, 100, 2], [2, -1, 2], [8, 3, 3]] as [$stored, $requested, $expected]) {
		set_config_option('install_step', $stored);

		$step = (int) (new Installer(['Runtime' => 'Web', 'Step' => $requested]))->getStep();

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
	// This process takes the install lock, then a second installer is started
	register_process_start('install', 'master', '0', 60);

	$command = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'auto_prepend_file=' . ini_get('auto_prepend_file'), 'cli/install_cacti.php', '--accept-eula', '--install'];
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output  = stream_get_contents($pipes[1]);
	$code    = proc_close($process);

	unregister_process('install', 'master', 0);

	print 'exit=' . $code . ' refused=' . (str_contains($output, 'already in progress') ? 'yes' : 'no') . PHP_EOL;

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
