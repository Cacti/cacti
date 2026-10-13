<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$coverage = getenv('CACTI_COVERAGE_FILE');

if ($coverage !== false && $coverage !== '') {
	if (!function_exists('xdebug_start_code_coverage')) {
		throw new RuntimeException('Xdebug coverage required');
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
	register_shutdown_function(static function () use ($coverage) : void {
		file_put_contents($coverage, json_encode(xdebug_get_code_coverage(), JSON_THROW_ON_ERROR));
	});
}

$directory = realpath(sys_get_temp_dir()) . '/cacti-containment-' . bin2hex(random_bytes(8));
$rra       = $directory . '/rra';
$outside   = $directory . '/outside';
mkdir($directory, 0700);
mkdir($rra, 0700);
mkdir($outside, 0700);
mkdir($rra . '/existing', 0700);
file_put_contents($rra . '/existing/file.rrd', 'fixture');
$mode    = $argv[1] ?? 'normal';
$develop = str_contains(file_get_contents(dirname(__DIR__, 3) . '/lib/functions.php'), '(string) CACTI_PATH_RRA');
$root    = $mode === 'missing' ? $directory . '/absent' : ($mode === 'empty' ? '' : ($mode === 'slash' ? '/' : $rra . ($develop ? '/' : '')));

if ($develop) {
	define('CACTI_PATH_RRA', $root);
}
$config = ['rra_path' => $root];
require_once dirname(__DIR__, 3) . '/lib/functions.php';
$count = 0;
$check = static function (string $path, bool $expected) use (&$count) : void {
	if (data_source_path_within_rra($path) !== $expected) {
		throw new RuntimeException('Containment mismatch at case ' . $count);
	}
	$count++;
};

try {
	if ($mode !== 'normal') {
		$check($root . '/new/file.rrd', false);
	} else {
		foreach (['existing/file.rrd', 'existing/new.rrd', 'new/nested/file.rrd', 'new.rrd'] as $relative) {
			$check($rra . '/' . $relative, true);
		}
		$check($rra . '/existing//new.rrd', $develop);

		foreach (['', '/tmp/outside.rrd', 'relative.rrd', $rra, $rra . '/', $rra . '/.', $rra . '/..', $rra . '/new/../escape.rrd', $rra . '/new/./file.rrd', $rra . '-other/file.rrd', $rra . "/nul\0.rrd"] as $path) {
			$check($path, false);
		}

		foreach (['outside' => $outside, 'inside' => $rra . '/existing', 'dangling' => $outside . '/absent', 'leaf.rrd' => $outside . '/absent.rrd'] as $name => $destination) {
			if (!symlink($destination, $rra . '/' . $name)) {
				throw new RuntimeException('Cannot create symlink fixture');
			}
			$check($rra . '/' . $name, false);
			$check($rra . '/' . $name . '/new/nested/file.rrd', false);
		}
		mkdir($outside . '/absent', 0700);
		clearstatcache();
		$check($rra . '/dangling/new/file.rrd', false);
		// Validate through the same helper before materializing a missing tree.
		$target = $rra . '/new/nested/file.rrd';
		$check($target, true);
		mkdir(dirname($target), 0700, true);
		file_put_contents($target, 'rrd fixture');
		clearstatcache();
		$check($target, true);

		if (realpath($target) !== $target) {
			throw new RuntimeException('Created target escaped RRA root');
		}
	}
} finally {
	foreach (['outside', 'inside', 'dangling', 'leaf.rrd'] as $name) {
		if (is_link($rra . '/' . $name)) {
			unlink($rra . '/' . $name);
		}
	}

	if (is_file($rra . '/new/nested/file.rrd')) {
		unlink($rra . '/new/nested/file.rrd');
		rmdir($rra . '/new/nested');
		rmdir($rra . '/new');
	}

	if (is_dir($outside . '/absent')) {
		rmdir($outside . '/absent');
	}
	unlink($rra . '/existing/file.rrd');
	rmdir($rra . '/existing');
	rmdir($rra);
	rmdir($outside);
	rmdir($directory);
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
