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
	mkdir($root . '/repo/inner', 0700);
	file_put_contents($root . '/repo/inner/package.manifest', 'inner manifest');
	$inner = symlink($root . '/repo/inner', $root . '/repo/alias') && symlink($root . '/repo/inner/package.manifest', $root . '/repo/linked.manifest');
	file_put_contents($root . '/repo/package.manifest', 'repo manifest');
	file_put_contents($root . '/outside/package.manifest', 'outside fixture');
	$link       = symlink($root . '/outside', $root . '/repo/escape');
	$repo       = ['repo_location' => $root . '/repo', 'name' => 'fixture'];
	$javascript = true;
	$path       = tempnam(sys_get_temp_dir(), 'cacti-repo-test-');

	try {
		if ((!$link || !$inner) && (str_starts_with($filename, 'escape/') || str_starts_with($filename, 'alias/') || $filename === 'linked.manifest')) {
			test()->markTestSkipped('Symlinks unavailable');
		}
		// Execute the actual directory branch with a controlled repository lookup.
		file_put_contents($path, '<?php ' . $block[1] . "\nreturn false;");
		$data = include $path;
		expect($data)->toBe($expected);
	} finally {
		unlink($path);

		if ($link) {
			unlink($root . '/repo/escape');
		}

		if ($inner) {
			unlink($root . '/repo/alias');
			unlink($root . '/repo/linked.manifest');
		}
		unlink($root . '/repo/inner/package.manifest');
		rmdir($root . '/repo/inner');
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
	['alias/package.manifest', 'inner manifest'],
	['linked.manifest', 'inner manifest'],
	["package.manifest\0", false],
	['missing.manifest', false],
]);

test('an unreadable local repository file still reports the repo as missing', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/package_import.php');
	preg_match('~\} else \{ // Server Directory\n(.*?)\n\t\t\}\n\t\}\n\n\treturn false;~s', $source, $block);
	expect($block)->not->toBeEmpty()->and($block[1])->toContain("raise_message('repo_missing'");
	$root = sys_get_temp_dir() . '/cacti-repo-' . bin2hex(random_bytes(8));
	mkdir($root, 0700);
	$repo       = ['repo_location' => $root, 'name' => 'fixture'];
	$filename   = '../outside/package.manifest';
	$javascript = false;
	$path       = tempnam(sys_get_temp_dir(), 'cacti-repo-test-');
	$_SESSION   = [];

	try {
		file_put_contents($path, '<?php ' . $block[1] . "\nreturn false;");
		expect(include $path)->toBeFalse();
		expect(json_encode($_SESSION))->toContain('repo_missing');
	} finally {
		unlink($path);
		rmdir($root);
	}
});
