<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$root            = dirname(__DIR__, 3);
$installerSource = file_get_contents($root . '/lib/installer.php');
$cliSource       = file_get_contents($root . '/cli/install_cacti.php');

if ($installerSource === false || $cliSource === false) {
	throw new RuntimeException('Unable to read installer sources');
}

test('installer validates the complete core schema before reporting success', function () use ($installerSource) {
	$validation = strpos($installerSource, '$failure = $this->validateCoreSchema();');
	$version    = strpos($installerSource, "db_execute('TRUNCATE TABLE version');");
	$complete   = strpos($installerSource, '$this->setStep(Installer::STEP_COMPLETE);');

	expect($validation)->not->toBeFalse()
		->and($version)->not->toBeFalse()
		->and($complete)->not->toBeFalse();

	if ($validation === false || $version === false || $complete === false) {
		throw new RuntimeException('Unable to locate installer completion sequence');
	}

	expect($validation)->toBeLessThan($version)
		->and($validation)->toBeLessThan($complete)
		->and($installerSource)->toContain('ERROR: Required core database tables are missing: %s')
		->and($installerSource)->toContain('ERROR: Unable to query the installed database schema');
});

test('background and CLI installation paths preserve failure state', function () use ($installerSource, $cliSource) {
	expect($installerSource)->toContain('$success = $installer->getStep() === Installer::STEP_COMPLETE;')
		->and($installerSource)->not->toContain("set_install_config_option('install_step', Installer::STEP_COMPLETE);")
		->and($installerSource)->toContain('catch (Throwable $e)')
		->and($cliSource)->toContain('$install_failed = !Installer::beginInstall($time, $installer);')
		->and($cliSource)->toContain('if ($install_failed || $installer->getStep() === Installer::STEP_ERROR) {');
});
