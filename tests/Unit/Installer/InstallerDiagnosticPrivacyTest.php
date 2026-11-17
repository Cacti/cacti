<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace InstallerDiagnosticPrivacyTest;

$root = dirname(__DIR__, 3);

require_once $root . '/tests/Helpers/SourceSlice.php';

foreach (['install/functions.php' => ['db_install_add_cache', 'db_install_execute'], 'cli/install_cacti.php' => ['set_install_option', 'debug_install_array']] as $file => $functions) {
	foreach ($functions as $name) {
		cacti_fixture_source_function($root . '/' . $file, $name, __NAMESPACE__);
	}
}

function set_config_option($name, $value) : void {
}

function log_install_high($section, $text, $background = false) : void {
	$GLOBALS['installer_diagnostic_logs'][] = $text;
}

function log_install_debug($section, $text, $background = false) : void {
	$GLOBALS['installer_diagnostic_logs'][] = $text;
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = [], $timeout = 0, $redact = false) : bool {
	$GLOBALS['installer_query_log_enabled'] = $log;
	$GLOBALS['installer_query_redacted']    = $redact;

	return $GLOBALS['installer_query_success'] ?? false;
}

test('installer diagnostics preserve status without parameter or server error values', function () {
	$GLOBALS['installer_query_success']   = false;
	$marker                               = 'synthetic-private-value';
	$cache                                = tempnam(sys_get_temp_dir(), 'cacti-diagnostic-');
	$GLOBALS['installer_diagnostic_logs'] = [];
	$GLOBALS['database_upgrade_status']   = ['file' => $cache];
	$GLOBALS['cacti_upgrade_version']     = 'test';
	$GLOBALS['database_last_error']       = 'Rejected value: ' . $marker;

	try {
		ob_start();
		$status  = db_install_execute('INSERT INTO example (value) VALUES (?)', [$marker]);
		$output  = ob_get_clean();
		$options = [];
		set_install_option($options, 'SnmpPassword', 'SNMP password', $marker);
		debug_install_array('Options', $options);

		expect($status)->toBe(DB_STATUS_ERROR)
			->and($GLOBALS['installer_query_log_enabled'])->toBeFalse()
			->and($GLOBALS['installer_query_redacted'])->toBeTrue()
			->and($options['SnmpPassword'])->toBe($marker)
			->and($output)->toBe('x')
			->and(json_encode($GLOBALS['database_upgrade_status']))->not->toContain($marker)
			->and(implode("\n", $GLOBALS['installer_diagnostic_logs']))->not->toContain($marker)
			->and(file_get_contents($cache))->not->toContain($marker)
			->and($GLOBALS['database_upgrade_status']['test'][0]['sql'])->toContain('VALUES (?)');
	} finally {
		unlink($cache);
	}
});

test('successful installer statements keep an empty diagnostic error', function ($empty_error) {
	$GLOBALS['installer_query_success'] = true;
	$GLOBALS['database_upgrade_status'] = [];
	$GLOBALS['cacti_upgrade_version']   = 'success';
	$GLOBALS['database_last_error']     = $empty_error;

	ob_start();
	$status = db_install_execute('CREATE TABLE example (id int)');
	$output = ob_get_clean();

	expect($status)->toBe(DB_STATUS_SUCCESS)
		->and($output)->toBe('+')
		->and($GLOBALS['installer_query_log_enabled'])->toBeTrue()
		->and($GLOBALS['database_upgrade_status']['success'][0]['error'])->toBe('');
})->with([[false], [null], ['']]);
