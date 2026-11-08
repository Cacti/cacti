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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * CACTI_SERVER_OS is a constant, so the win32 branch of get_script_query_path()
 * cannot run in the unix test process. This probe sets it to 'win32' before
 * loading lib/functions.php so clean_up_path() really converts / to \ and the
 * Windows guard runs on the same strings a Windows poller would build.
 */

namespace CactiScriptQueryWin32Fixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);

define('CACTI_SERVER_OS', 'win32');
define('SNMP_ESCAPE_CHARACTER', '"');
define('CACTI_ESCAPE_CHARACTER', '"');
define('CACTI_PATH_BASE', 'C:/Program Files (x86)/Apache24/htdocs/cacti');
define('CACTI_PATH_LIBRARY', $root . '/lib');

require_once $root . '/include/vendor/autoload.php';
require_once $root . '/lib/functions.php';
require_once $root . '/tests/Helpers/SourceSlice.php';

$GLOBALS['query_command_logs'] = 0;

/**
 * Stand in for the configured PHP binary so no database is needed.
 *
 * @param string $config_name The option to read.
 *
 * @return string The fixture value for path_php_binary, empty otherwise.
 */
function read_config_option(string $config_name) : string {
	return $config_name === 'path_php_binary' ? 'C:/php/php.exe' : '';
}

/**
 * Count rejections instead of writing to the Cacti log.
 *
 * @param mixed ...$arguments The cacti_log() arguments, ignored.
 *
 * @return void
 */
function cacti_log(...$arguments) : void {
	$GLOBALS['query_command_logs']++;
}

\cacti_fixture_source_function($root . '/lib/variables.php', 'substitute_script_query_path', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/data_query.php', 'get_script_query_path', __NAMESPACE__);

$accepted = [
	'stock' => get_script_query_path('index', '|path_php_binary| -q "|path_cacti|/scripts/ss_host_disk.php"', 0),
	'plain' => get_script_query_path('', 'c:/php/php.exe -q "C:/Apache24/htdocs/cacti/scripts/query.php"', 0),
];

$rejected = [];

foreach (['|path_cacti|/scripts/a.php & calc', '|path_cacti|/scripts/a.php | more', '|path_cacti|/scripts/a.php > out', '|path_cacti|/scripts/a^b.php', '|path_cacti|/scripts/%PATH%.php', '"|path_cacti|/scripts/a.php', '|path_cacti|/../a.php'] as $path) {
	$rejected[] = get_script_query_path('', $path, 0);
}

print json_encode(['accepted' => $accepted, 'rejected' => $rejected, 'logs' => $GLOBALS['query_command_logs']], JSON_THROW_ON_ERROR) . "\n";
