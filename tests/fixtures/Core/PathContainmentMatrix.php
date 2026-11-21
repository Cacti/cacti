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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/lib/html.php';

// Runs the production path helpers and the console external link renderer
// against a real temporary tree. No network or database is involved.
$checks = 0;
$check  = static function (mixed $actual, mixed $expected, string $label) use (&$checks) : void {
	if ($actual !== $expected) {
		throw new RuntimeException('Path containment contract failed: ' . $label . ' got ' . var_export($actual, true));
	}
	$checks++;
};

$root = sys_get_temp_dir() . '/cacti-path-matrix-' . bin2hex(random_bytes(6));
mkdir($root . '/base/sub', 0700, true);
mkdir($root . '/outside', 0700);
file_put_contents($root . '/base/sub/ok.txt', 'contained');
file_put_contents($root . '/outside/secret.txt', 'outside');
symlink($root . '/outside', $root . '/base/escape');
symlink($root . '/base/sub/ok.txt', $root . '/base/alias.txt');
symlink($root . '/base/later.txt', $root . '/base/dangling.txt');

try {
	$base = $root . '/base';
	$real = str_replace('\\', '/', (string) realpath($base));

	foreach ([
		['', 'sub/ok.txt', false],
		[$base, '', false],
		[$base . "\0", 'sub/ok.txt', false],
		[$base, "sub/ok.txt\0", false],
		[$base, 'php://filter/resource=sub/ok.txt', false],
		['relative/base', 'sub/ok.txt', false],
		[$base, '../outside/secret.txt', false],
		[$base, $root . '/outside/secret.txt', false],
		[$root . '/missing', 'sub/ok.txt', false],
		[$base . '/sub/ok.txt', 'x', false],
		[$base, '.', $real],
		[$base, 'sub/ok.txt', $real . '/sub/ok.txt'],
		[$base, 'sub\\..\\sub\\ok.txt', $real . '/sub/ok.txt'],
		[$base, $base . '/sub/ok.txt', $real . '/sub/ok.txt'],
		[$base, 'sub/new/page.txt', $real . '/sub/new/page.txt'],
		[$base, 'alias.txt', $real . '/sub/ok.txt'],
		[$base, 'escape/secret.txt', false],
		[$base, 'escape/new.txt', false],
		[$base, 'dangling.txt', false],
		[$base, 'C:/windows/system.ini', false],
	] as $index => [$from, $candidate, $expected]) {
		$check(cacti_path_within($from, $candidate), $expected, 'within ' . $index);
	}

	$check(cacti_path_within('/', $real . '/sub/ok.txt'), $real . '/sub/ok.txt', 'root base');
	$check(cacti_path_is_within($real . '/sub/ok.txt', '/'), true, 'root is_within');

	foreach ([
		['', ''],
		['/a/./b//c/', '/a/b/c'],
		['/../a', '/a'],
		['../a/../../b', '../../b'],
		['C:\\a\\..\\b', 'C:/b'],
		['C:', 'C:/'],
		['C:a', 'C:a'],
	] as $index => [$path, $expected]) {
		$check(cacti_path_canonicalize($path), $expected, 'canonicalize ' . $index);
	}

	foreach ([
		['/base', '/base/x', true],
		['/base', '/basex', false],
		['/', '/x', true],
		['', '/x', false],
		['/x', '', false],
	] as $index => [$from, $path, $expected]) {
		$check(cacti_path_is_base_path($from, $path), $expected, 'base path ' . $index);
	}

	require_once dirname(__DIR__) . '/ExternalLinkRendering.php';
	$rendered = Cacti\Test\ExternalLinks\render_fixture();
	$check(str_contains($rendered['html'], 'Contained fixture content'), true, 'render contained');
	$check(str_contains($rendered['html'], 'Linked fixture content'), true, 'render in-base link');
	$check(str_contains($rendered['html'], 'Outside fixture content'), false, 'render escaping link');
	$check(substr_count($rendered['html'], 'does not exist!!'), 3, 'render missing messages');
} finally {
	foreach (['/base/dangling.txt', '/base/alias.txt', '/base/escape', '/base/sub/ok.txt', '/outside/secret.txt'] as $leaf) {
		unlink($root . $leaf);
	}

	foreach (['/base/sub', '/base', '/outside', ''] as $directory) {
		rmdir($root . $directory);
	}
}

print json_encode(['passed' => $checks], JSON_THROW_ON_ERROR) . "\n";
