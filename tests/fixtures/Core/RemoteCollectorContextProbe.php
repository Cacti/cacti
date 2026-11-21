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
 * Runs the unchanged get_default_contextoption() from lib/functions.php in its
 * own process, with the settings answered from the scenario, and prints the
 * stream context options it builds.
 */

namespace CactiRemoteCollectorContextFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

function read_config_option($name) {
	return isset($GLOBALS['scenario'][$name]) ? $GLOBALS['scenario'][$name] : '';
}

function get_url_type() {
	return $GLOBALS['scenario']['scheme'];
}

function api_plugin_hook_function($hook, $value) {
	return $value;
}

\cacti_fixture_source_function($root . '/lib/functions.php', 'get_default_contextoption', __NAMESPACE__);

function remote_collector_context(array $scenario) {
	$GLOBALS['scenario'] = $scenario + array('remote_agent_timeout' => '10');

	return get_default_contextoption();
}

print json_encode(remote_collector_context(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
