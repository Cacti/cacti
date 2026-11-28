<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerOutputGuardFailures;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$failure = 'start';

function ob_start(?callable $callback = null, int $chunk_size = 0, int $flags = PHP_OUTPUT_HANDLER_STDFLAGS): bool {
	global $failure;

	return $failure === 'start' ? false : \ob_start($callback, $chunk_size, $flags);
}

function ob_get_length(): int|false {
	global $failure;

	return $failure === 'length' ? false : \ob_get_length();
}

foreach (['script_server_start_output_guard', 'script_server_reset_output_guard'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/script_server.php', $function, __NAMESPACE__);
}

if (script_server_start_output_guard() !== 0) {
	throw new \RuntimeException('A failed output buffer must not publish a usable guard.');
}

$failure = '';
$level   = script_server_start_output_guard();

if ($level < 1) {
	throw new \RuntimeException('The real output guard must start after a failed attempt.');
}

\ob_start();
print 'nested script output';

if (!script_server_reset_output_guard($level) || \ob_get_level() !== $level || \ob_get_contents() !== '') {
	throw new \RuntimeException('A removable nested script buffer must be discarded without reaching the protocol stream.');
}

$failure = 'length';

if (script_server_reset_output_guard($level)) {
	throw new \RuntimeException('An unreadable buffer must not be accepted as a clean guard.');
}

fwrite(STDOUT, "PASS: 4 Script Server output guard failure contracts\n");
