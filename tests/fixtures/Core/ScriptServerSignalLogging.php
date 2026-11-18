<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerSignalLogging;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$terminating     = ($argv[1] ?? 'terminate') === 'terminate';
$closed          = false;
$messages        = [];
$include_file    = '/scripts/example.php';
$function        = 'sample';
$parameter_array = $terminating ? ['first value', 'second value'] : null;

// The terminating handler exits. Verify its observable effects before the
// coverage collector publishes evidence from the shutdown sequence.
register_shutdown_function(static function () use ($terminating): void {
	global $closed, $messages;
	$expected = $terminating ? '2 argument(s)' : '0 argument(s)';

	if ($closed !== $terminating || count($messages) !== 1 || !str_contains($messages[0], $expected)
		|| str_contains($messages[0], 'first value') || str_contains($messages[0], 'second value')) {
		throw new \RuntimeException('Signal logging must preserve argument confidentiality and close only on terminating signals.');
	}
	print "PASS: Script Server signal logging contract\n";
});

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	global $messages;
	$messages[] = $string;

	return true;
}

function db_close(mixed &$db_conn = false): bool {
	global $closed;
	$closed = true;

	return true;
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'cacti_count', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/script_server.php', 'script_server_log_token', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/script_server.php', 'sig_handler', __NAMESPACE__);

sig_handler($terminating ? SIGTERM : 0);
