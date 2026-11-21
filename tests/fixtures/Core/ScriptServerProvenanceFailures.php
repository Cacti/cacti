<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiScriptServerProvenanceFailures;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

class_alias(\ReflectionException::class, __NAMESPACE__ . '\\ReflectionException');

$messages = [];

function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	global $messages;
	$messages[] = $string;

	return true;
}

// Preserve real reflection except for one explicit metadata failure. A failed
// introspection boundary must never approve a callable's source binding.
class ReflectionFunction extends \ReflectionFunction {
	public function __construct(\Closure|string $function) {
		if ($function === ($GLOBALS['unavailable_function'] ?? null)) {
			throw new \ReflectionException('metadata unavailable');
		}
		parent::__construct($function);
	}
}

foreach (['script_server_log_token', 'script_server_function_rejection', 'script_server_function_name_is_valid', 'script_server_split_request',
	'script_server_path_is_allowed', 'script_server_resolve_include', 'script_server_validate_request'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/script_server.php', $function, __NAMESPACE__);
}

$token     = bin2hex(random_bytes(8));
$directory = sys_get_temp_dir() . '/cacti-provenance-' . $token;
mkdir($directory, 0700);
$files = [];

try {
	foreach (['cached', 'uncached', 'unavailable'] as $name) {
		$file     = $directory . '/' . $name . '.php';
		$function = 'cacti_provenance_' . $token . '_' . $name;
		file_put_contents($file, '<?php function ' . $function . '(): int { return 1; }');
		require $file;
		$files[$name] = [$file, $function];
	}
	[$file, $function] = $files['cached'];

	if (script_server_function_rejection($function, $file) !== '') {
		throw new \RuntimeException('A function must initially bind to its actual declaring file.');
	}
	unlink($file);

	if (script_server_function_rejection($function, $file) !== 'does not have a resolvable source file') {
		throw new \RuntimeException('A cached binding must not outlive its source file.');
	}

	[$file, $function] = $files['uncached'];
	unlink($file);

	if (script_server_function_rejection($function, $file) !== 'does not have a resolvable source file') {
		throw new \RuntimeException('An unbound function with a deleted source must be rejected.');
	}

	[$file, $function]               = $files['unavailable'];
	$GLOBALS['unavailable_function'] = $function;

	if (script_server_function_rejection($function, $file) !== 'is not introspectable') {
		throw new \RuntimeException('Unavailable reflection metadata must fail closed.');
	}

	if (script_server_validate_request($directory . '/missing.php sample', [$directory]) !== false || count($messages) !== 1
		|| !str_contains($messages[0], 'missing.php') || str_contains($messages[0], $directory)) {
		throw new \RuntimeException('An otherwise valid request must reject a missing include before dispatch.');
	}
	print "PASS: 5 Script Server provenance failure contracts\n";
} finally {
	foreach ($files as [$file]) {
		if (is_file($file)) {
			unlink($file);
		}
	}
	rmdir($directory);
}
