<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/install/functions.php';
require_once dirname(__DIR__, 2) . '/lib/installer.php';

test('remote database version results expose success rather than the failure flag', function () {
	$compatible = install_remote_database_version_result('1.3.0', '1.3.0');
	$tooNew     = install_remote_database_version_result('1.2.31', '1.3.0');

	expect($compatible)
		->toMatchArray(['status' => 'true'])
		->and($tooNew)
		->toMatchArray(['status' => 'false'])
		->and($tooNew['message'])
		->toContain('Main Primary at 1.2.31')
		->toContain('Remote at 1.3.0');
});

test('template selection reads and writes the same installer setting namespace', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/installer.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain('$this->setTemplates($this->getTemplates());')
		->and(substr_count($source, "LIKE 'install_tp_%'"))->toBeGreaterThanOrEqual(2)
		->and($source)->not->toContain("LIKE \\'install_template_%\\'")
		->and($source)->toContain('key: install_tp_$key');
});

test('package imports fail when their result or any file handoff is incomplete', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$method    = new ReflectionMethod(Installer::class, 'packageImportSucceeded');

	expect($method->invoke($installer, false))->toBeFalse()
		->and($method->invoke($installer, []))->toBeFalse()
		->and($method->invoke($installer, [[], []]))->toBeTrue()
		->and($method->invoke($installer, [[], ['/tmp/file' => __('written')]]))->toBeTrue()
		->and($method->invoke($installer, [[], ['/tmp/file' => __('not writable')]]))->toBeFalse();
});

test('installer completion is guarded by schema and version postconditions', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/installer.php');

	expect($source)->not->toBeFalse()
		->and(substr_count($source, '$this->validateRequiredSchema()'))->toBe(2)
		->and($source)->toContain('private function writeInstalledVersion() : bool')
		->and($source)->toContain('SELECT COUNT(*) AS row_count, MAX(cacti) AS cacti FROM version')
		->and($source)->not->toContain('TRUNCATE TABLE version');
});

test('installer completion validates schema and version with one query each', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/installer.php');

	expect($source)->not->toBeFalse();

	$schemaStart  = strpos($source, 'private function validateRequiredSchema()');
	$schemaEnd    = strpos($source, 'private function writeInstalledVersion()', $schemaStart);
	$schema       = substr($source, $schemaStart, $schemaEnd - $schemaStart);
	$versionStart = strpos($source, 'private function writeInstalledVersion()');
	$versionEnd   = strpos($source, 'private function installTemplate()', $versionStart);
	$version      = substr($source, $versionStart, $versionEnd - $versionStart);

	expect($schemaStart)->not->toBeFalse()
		->and($schemaEnd)->not->toBeFalse()
		->and(substr_count($schema, 'information_schema.TABLES'))->toBe(1)
		->and($schema)->not->toContain('foreach ($requiredTables as $table)')
		->and($schema)->toContain('array_diff($requiredTables, $actualTables)')
		->and($versionStart)->not->toBeFalse()
		->and($versionEnd)->not->toBeFalse()
		->and(substr_count($version, 'SELECT '))->toBe(1);
});

test('table conversion loads schema metadata once instead of once per table', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/install/functions.php');

	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function install_setup_get_tables()');
	$end   = strpos($source, "\n}\n", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);

	expect(substr_count($function, 'information_schema.TABLES'))->toBe(1)
		->and($function)->toContain("'Name', ['Engine', 'Rows', 'Collation', 'Row_format']")
		->and($function)->toContain('$table_status = $table_statuses[$table] ?? false;')
		->and($function)->not->toContain('SHOW TABLE STATUS LIKE');
});

test('installer command-line adapters propagate failures and release locks', function () {
	$cli        = file_get_contents(dirname(__DIR__, 2) . '/cli/install_cacti.php');
	$background = file_get_contents(dirname(__DIR__, 2) . '/install/background.php');
	$conversion = file_get_contents(dirname(__DIR__, 2) . '/cli/convert_tables.php');
	$installer  = file_get_contents(dirname(__DIR__, 2) . '/lib/installer.php');
	$import     = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');
	$utility    = file_get_contents(dirname(__DIR__, 2) . '/lib/utility.php');

	expect($cli)->toContain('exit($exitCode);')
		->and($cli)->toContain('if (!isset($options[$key])) {')
		->and($cli)->toContain('$options[$key] = [];')
		->and($cli)->toContain("set_install_multioption(\$options, 'Tables', 'Table', \$value, 'chk_table_');")
		->and($background)->toContain('$installerProcessTimeout = 86400;')
		->and($background)->toContain("register_process_start('install', 'master', 0, \$installerProcessTimeout)")
		->and($background)->not->toContain("register_process_start('install', 'master', 0, 600)")
		->and($background)->toContain('finally {')
		->and($background)->toContain('exit($completed ? 0 : 1);')
		->and($conversion)->toContain('exit($conversion_failed ? 1 : 0);')
		->and($installer)->toContain("['bypass_shell' => true]")
		->and($import)->toContain('if (file_exists($filename) && !unlink($filename)) {')
		->and($utility)->toContain('is_file($config_file) && is_readable($config_file)')
		->and(substr_count($installer, 'shell_exec('))->toBe(4)
		->and($installer)->not->toContain("cacti_escapeshellarg(CACTI_PATH_CLI . '/add_tree.php')")
		->and($installer)->not->toContain("cacti_escapeshellarg(CACTI_PATH_CLI . '/convert_tables.php')");
});

test('installer CLI handoff bypasses the shell and preserves output and status', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$method    = new ReflectionMethod(Installer::class, 'runCliCommand');
	$script    = dirname(__DIR__) . '/fixtures/InstallerCliProbe.php';

	$result = $method->invoke($installer, $script, ['value with spaces', '--exit=7'], PHP_BINARY);

	expect($result['exitCode'])->toBe(7)
		->and($result['output'])->toContain('value with spaces|--exit=7')
		->toContain('probe-error')
		->and($method->invoke($installer, $script, [], '/does/not/exist'))
		->toMatchArray(['exitCode' => 127]);
});
