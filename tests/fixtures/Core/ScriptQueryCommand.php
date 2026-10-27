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

namespace CactiScriptQueryFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

$GLOBALS['config']['config_options_array']['path_php_binary'] = '/usr/bin/php';
$GLOBALS['query_command_logs']                                = 0;
function cacti_log(...$arguments): void {
	$GLOBALS['query_command_logs']++;
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/data_query.php', 'get_script_query_path', __NAMESPACE__);
$commands = [];

foreach (['/opt/cacti/query.php', '/opt/cacti/label;literal', '|path_php_binary| -q "|path_cacti|/scripts/query file.php"'] as $path) {
	$commands[] = get_script_query_path('', $path, 0);
}

foreach (['/opt/cacti/../outside.php', "/opt/cacti/line\nname", "/opt/cacti/nul\0name"] as $path) {
	if (get_script_query_path('', $path, 0) !== '') {
		throw new \RuntimeException('Unsafe script query path accepted.');
	}
}
$arguments = get_script_query_path('alpha "two words" semi;colon', '/opt/cacti/query.php', 0);

if ($commands[0] !== '/opt/cacti/query.php ' || $commands[1] !== '/opt/cacti/label\\;literal ' || $GLOBALS['query_command_logs'] !== 3) {
	throw new \RuntimeException('Script query command boundary failed.');
}

if ($arguments !== "/opt/cacti/query.php 'alpha' 'two words' 'semi;colon'") {
	throw new \RuntimeException('Script query argument quoting changed.');
}

if (!str_contains($commands[2], '/usr/bin/php -q "') || !str_contains($commands[2], '/scripts/query file.php"')) {
	throw new \RuntimeException('Configured interpreter or quoted path changed.');
}
$start = hrtime(true);

for ($pass = 0; $pass < 10000; $pass++) {
	get_script_query_path('', '/opt/cacti/query.php', 0);
}
print json_encode(['commands' => $commands, 'arguments' => $arguments, 'rejected' => $GLOBALS['query_command_logs'], 'passes' => 10000, 'elapsed_seconds' => (hrtime(true) - $start) / 1e9]);
