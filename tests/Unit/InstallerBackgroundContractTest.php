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
 | Cacti: The Complete RRTool-based Graphing Solution                      |
 +-------------------------------------------------------------------------+
 */

$root = dirname(__DIR__, 2);

test('background installation always releases its lock and propagates failure', function () use ($root) {
	$source = file_get_contents($root . '/install/background.php');

	expect($source)->not->toBeFalse();
	expect($source)->toContain('$installer_process_timeout = 86400;');
	expect($source)->toContain("register_process_start('install', 'master', '0', \$installer_process_timeout)");
	expect($source)->not->toContain("register_process_start('install', 'master', '0', 600)");
	expect($source)->toContain('$registered_process = false;');
	expect($source)->toContain('$registered_process = true;');
	expect($source)->toMatch('/try\s*\{.*Installer::beginInstall\(\$params\[0\]\).*\}\s*finally\s*\{/s');
	expect($source)->toContain("unregister_process('install', 'master', 0);");
	expect($source)->toContain("set_config_option('installer_running', '');");
	expect($source)->toContain('exit($completed ? 0 : 1);');
});
