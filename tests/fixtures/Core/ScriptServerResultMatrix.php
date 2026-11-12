<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs the unchanged script_server_response() and script_server_log_response()
 * against null, scalar and array script results, with debug logging enabled,
 * and checks the reply line and the logged response.
 */

namespace CactiScriptServerResultFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once $root . '/include/global_constants.php';

$messages = [];

function cacti_effective_log_level() : int {
	return POLLER_VERBOSITY_DEBUG;
}

function cacti_log($message, $echo = false, $subsystem = 'SYSTEM', $verbosity = 2) {
	$GLOBALS['messages'][] = $message;
}

\cacti_fixture_source_function($root . '/script_server.php', 'script_server_response', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/poller.php', 'script_server_log_response', __NAMESPACE__);

$cases = [
	[null, '', "DEBUG: PID[1] CTR[0] RESPONSE:''"],
	["a:1\nb:2", 'a:1 b:2', "DEBUG: PID[1] CTR[1] RESPONSE:'a:1\nb:2'"],
	[['a' => 1], 'U', "DEBUG: PID[1] CTR[2] RESPONSE:'{\"a\":1}'"],
];

foreach ($cases as $index => [$result, $reply, $logged]) {
	script_server_log_response(1, $index, $result);

	if (script_server_response($result) !== $reply || end($messages) !== $logged) {
		throw new \RuntimeException('Script Server result case failed: ' . $index);
	}
}

print json_encode(['passed' => count($cases)], JSON_THROW_ON_ERROR) . "\n";
