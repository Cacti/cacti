<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs the unchanged get_script_query_path() for one platform and prints the
 * command it builds. CACTI_SERVER_OS is a constant, so each platform needs
 * its own process.
 *
 * argv: <win32|unix> <script path> <arguments>
 */

namespace CactiScriptQueryPathFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);

define('CACTI_SERVER_OS', $argv[1]);
define('CACTI_WEB', false);
define('CACTI_PATH_BASE', $argv[1] === 'win32' ? 'C:\\Apache24\\htdocs\\cacti' : '/srv/cacti');
define('CACTI_PATH_LIBRARY', $root . '/lib');

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';

$config = [
	'cacti_server_os' => $argv[1],
	OPTIONS_CLI       => [
		'path_php_binary'  => $argv[1] === 'win32' ? 'C:\\PHP\\php.exe' : '/usr/bin/php',
		'using_cygwin'     => '',
		'storage_location' => 0,
	],
];

$messages = [];

function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 2) {
	$GLOBALS['messages'][] = $message;
}

\cacti_fixture_source_function($root . '/lib/data_query.php', 'get_script_query_path', __NAMESPACE__);

$command = get_script_query_path($argv[3], $argv[2], 0);

print json_encode(['command' => $command, 'rejected' => count($messages) > 0], JSON_THROW_ON_ERROR);
