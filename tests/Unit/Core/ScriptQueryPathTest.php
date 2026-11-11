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

namespace CactiScriptQueryPathTests;

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('script query paths preserve separators and validate the resulting command path', function ($os, $path, $args, $expected) {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Core/ScriptQueryPath.php', array($os, $path, $args));
	expect($result['command'])->toBe($expected)->and($result['rejected'])->toBe($expected === false);
})->with(array(
	'Windows disk query' => array('win32', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php ss_host_disk', 'get total 2', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_disk.php ss_host_disk "get" "total" "2"'),
	'Windows CPU query placeholder' => array('win32', '|path_cacti|/scripts/ss_host_cpu.php ss_host_cpu', 'get usage 0', 'C:\\Apache24\\htdocs\\cacti\\scripts\\ss_host_cpu.php ss_host_cpu "get" "usage" "0"'),
	'Windows forward slashes' => array('win32', 'C:/Apache24/htdocs/cacti/scripts/win_services.php win_services', '', 'C:\\Apache24\\htdocs\\cacti\\scripts\\win_services.php win_services '),
	'Windows UNC separators' => array('win32', '\\\\server\\share\\cacti\\scripts\\test.php test', 'index', '\\\\server\\share\\cacti\\scripts\\test.php test "index"'),
	'Windows PHP binary placeholder' => array('win32', '|path_php_binary| -q |path_cacti|/scripts/test.php', '', 'C:\\PHP\\php.exe -q C:\\Apache24\\htdocs\\cacti\\scripts\\test.php '),
	'Windows quoted path' => array('win32', '"C:\\Program Files\\Cacti\\scripts\\test.php" test', '"two words"', '"C:\\Program Files\\Cacti\\scripts\\test.php" test "two words"'),
	'Windows metacharacters' => array('win32', 'C:\\cacti\\scripts\\a&b.php', '', 'C:\\cacti\\scripts\\ab.php '),
	'Windows traversal' => array('win32', 'C:\\cacti\\scripts\\..\\test.php', '', false),
	'Windows traversal after sanitizing' => array('win32', 'C:\\cacti\\scripts\\.;.\\test.php', '', false),
	'Windows nested traversal after sanitizing' => array('win32', 'C:\\cacti\\scripts\\.^.\\.;.\\test.php', '', false),
	'Unix placeholder' => array('unix', '|path_cacti|/scripts/ss_host_disk.php ss_host_disk', 'get total 2', "/srv/cacti/scripts/ss_host_disk.php ss_host_disk 'get' 'total' '2'"),
	'Unix shell metacharacter' => array('unix', '/srv/cacti/scripts/a&b.php', '', '/srv/cacti/scripts/a\\&b.php '),
	'Unix traversal' => array('unix', '/srv/cacti/scripts/../test.php', '', false),
	'Windows environment variable' => array('win32', 'C:\\cacti\\%COMSPEC%\\test.php', '', 'C:\\cacti\\COMSPEC\\test.php '),
	'Windows traversal joined by a percent sign' => array('win32', 'C:\\cacti\\scripts\\.%.\\test.php', '', false),
	'Windows stray quote' => array('win32', 'C:\\cacti\\scripts\\a"b.php', '', 'C:\\cacti\\scripts\\a"b.php '),
	'Windows traversal joined by quotes' => array('win32', 'C:\\cacti\\scripts\\."".\\test.php', '', false),
	'Unix traversal joined by quotes' => array('unix', '/srv/cacti/scripts/."."/x.php', '', false),
	'Windows drive-relative traversal' => array('win32', 'C:..\\test.php', '', false),
	'Windows dotted directory name' => array('win32', 'C:\\cacti\\scripts..old\\test.php', '', false),
	'Unix dotted directory name' => array('unix', '/srv/cacti/scripts..old/ss.php', '', false)
));
