<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiSnmpStateStoreFailures;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

// Exercise the same replacement rules as Windows while using real temporary
// files. Native platform process contracts run separately in the Windows matrix.
const DIRECTORY_SEPARATOR = '\\';

$failure  = '';
$messages = [];
$renames  = 0;

function function_exists(string $function): bool {
	global $failure;

	return $function === 'posix_geteuid' ? $failure !== 'no-posix' : \function_exists($function);
}

function posix_geteuid(): int {
	return \function_exists('posix_geteuid') ? \posix_geteuid() : 0;
}

function fileowner(string $path): int|false {
	global $failure;

	if (($failure === 'file-owner' && \is_file($path)) || ($failure === 'directory-owner' && \is_dir($path))) {
		return -1;
	}

	return \fileowner($path);
}

function fileperms(string $path): int|false {
	global $failure;

	return $failure === 'stat' ? false : \fileperms($path);
}

function chmod(string $path, int $permissions): bool {
	global $failure;

	return in_array($failure, ['chmod-file', 'chmod-directory'], true) ? false : \chmod($path, $permissions);
}

function file_put_contents(string $filename, mixed $data, int $flags = 0, $context = null): int|false {
	global $failure;

	return $failure === 'short-write' ? 0 : \file_put_contents($filename, $data, $flags, $context);
}

function rename(string $from, string $to, $context = null): bool {
	global $failure, $renames;
	$renames++;

	if ($failure === 'rename' || ($failure === 'windows-replace' && $renames === 1)) {
		return false;
	}

	return \rename($from, $to, $context);
}

function cacti_log($message, $output = false, $environ = 'SYSTEM', $level = ''): void {
	global $messages;
	$messages[] = $message;
}

$root      = sys_get_temp_dir() . '/cacti-state-failures-' . bin2hex(random_bytes(8));
$dir       = $root . '/state';
$file      = $dir . '/counter';
$source    = dirname(__DIR__, 3) . '/lib/SnmpStateStore.php';
$temporary = tempnam(sys_get_temp_dir(), 'cacti-state-source-');

try {
	// Preserve the complete class, line numbers and source identity. Only its
	// filesystem boundary resolves to this fixture's fault-injection namespace.
	$code = file_get_contents($source);
	\file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . substr($code, 5));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($source);
	require $temporary;
	mkdir($root, 0700);
	mkdir($dir, 0700);
	\file_put_contents($file, '{"counter":7}');
	$checks = 0;

	foreach (['file-owner', 'directory-owner', 'stat'] as $failure) {
		if (CactiSnmpStateStore::read($file) !== false) {
			throw new \RuntimeException('State read must reject ' . $failure);
		}
		$checks++;
	}

	$failure = 'chmod-directory';
	\chmod($dir, 0777);

	if (CactiSnmpStateStore::directory($dir) !== false || !str_contains(end($messages), 'open to other users')) {
		throw new \RuntimeException('A state directory that cannot be made private must be rejected.');
	}
	$checks++;
	\chmod($dir, 0700);

	foreach (['short-write', 'chmod-file', 'rename'] as $failure) {
		$renames = 0;

		if (CactiSnmpStateStore::write($dir, $file, '{"counter":8}') !== false || glob($dir . '/state*')) {
			throw new \RuntimeException('Failed ' . $failure . ' must leave no staging file.');
		}
		$checks++;
	}

	$failure = 'windows-replace';
	$renames = 0;
	\file_put_contents($file, '{"counter":7}');

	if (!CactiSnmpStateStore::write($dir, $file, '{"counter":8}') || $renames !== 2 || CactiSnmpStateStore::read($file) !== ['counter' => 8]) {
		throw new \RuntimeException('A denied Windows replacement must retry and publish the complete new state.');
	}
	$checks++;
	$failure = 'no-posix';

	if (CactiSnmpStateStore::read($file) !== ['counter' => 8]) {
		throw new \RuntimeException('Platforms without POSIX ownership APIs must retain valid private state.');
	}
	printf("PASS: %d state storage failure contracts\n", $checks + 1);
} finally {
	\unlink($temporary);

	if (is_dir($dir)) {
		foreach (glob($dir . '/*') as $path) {
			\unlink($path);
		}
		rmdir($dir);
	}

	if (is_dir($root)) {
		rmdir($root);
	}
}
