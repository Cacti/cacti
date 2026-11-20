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

namespace CactiScriptQueryPathFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
$config = array(
	'base_path' => $argv[1] === 'win32' ? 'C:\\Apache24\\htdocs\\cacti' : '/srv/cacti',
	'library_path' => $root . '/lib',
	'cacti_server_os' => $argv[1],
	'is_web' => false,
	'config_options_array' => array('path_php_binary' => $argv[1] === 'win32' ? 'C:\\PHP\\php.exe' : '/usr/bin/php')
);
$messages = array();

function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 2) {
	$GLOBALS['messages'][] = $message;
}

cacti_fixture_source_function($root . '/lib/data_query.php', 'get_script_query_path', __NAMESPACE__);
$result = get_script_query_path($argv[3], $argv[2], 0);
print json_encode(array('command' => $result, 'rejected' => count($messages) > 0), JSON_THROW_ON_ERROR);
