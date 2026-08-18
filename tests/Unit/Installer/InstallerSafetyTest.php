<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 3);

test('installer preserves failed state and initializes the configured csrf secret', function () use ($root) {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	expect($installer)->toContain("if (CACTI_CSRF_SECRET != '')")
		->and($installer)->toContain('catch (Throwable $e)')
		->and($installer)->toContain('if ($completed) {')
		->and($installer)->toContain("set_install_config_option('install_step', Installer::STEP_ERROR)");
});

test('installer isolates admin email changes and fails on package import errors', function () use ($root) {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	expect($installer)->toContain("db_execute_prepared('UPDATE user_auth")
		->and($installer)->toContain("WHERE username = \\'admin\\'")
		->and($installer)->toContain("\$failure = __('One or more template packages failed to import')")
		->and($installer)->toContain('return $failure;');
});
