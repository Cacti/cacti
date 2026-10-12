<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

test('local package repositories cannot read outside their directory', function ($filename, $expected) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/package_import.php');
	preg_match('~\} else \{ // Server Directory\n(.*?)\n\t\t\}\n\t\}\n\n\treturn false;~s', $source, $block);
	expect($block)->not->toBeEmpty();
	$root = sys_get_temp_dir() . '/cacti-repo-' . bin2hex(random_bytes(8));
	mkdir($root, 0700);
	mkdir($root . '/repo', 0700);
	mkdir($root . '/outside', 0700);
	file_put_contents($root . '/repo/package.manifest', 'repo manifest');
	file_put_contents($root . '/outside/package.manifest', 'outside fixture');
	$link       = symlink($root . '/outside', $root . '/repo/escape');
	$repo       = ['repo_location' => $root . '/repo', 'name' => 'fixture'];
	$javascript = true;

	try {
		if (!$link && str_starts_with($filename, 'escape/')) {
			test()->markTestSkipped('Symlinks unavailable');
		}
		// Execute the actual directory branch with a controlled repository lookup.
		$data = eval($block[1]);
		expect($data)->toBe($expected);
	} finally {
		if ($link) {
			unlink($root . '/repo/escape');
		}
		unlink($root . '/repo/package.manifest');
		unlink($root . '/outside/package.manifest');
		rmdir($root . '/repo');
		rmdir($root . '/outside');
		rmdir($root);
	}
})->with([
	['package.manifest', 'repo manifest'],
	['../outside/package.manifest', false],
	['escape/package.manifest', false],
]);
