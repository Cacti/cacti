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
*/

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

function remove_tree(string $path): void {
	if (!file_exists($path) && !is_link($path)) {
		return;
	}

	if (is_file($path) || is_link($path)) {
		@unlink($path);

		return;
	}

	$entries = scandir($path);

	if ($entries !== false) {
		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			remove_tree($path . DIRECTORY_SEPARATOR . $entry);
		}
	}

	@rmdir($path);
}

function make_temp_dir(string $prefix): string {
	$path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
	mkdir($path, 0700, true);

	$resolved = realpath($path);

	return $resolved !== false ? $resolved : $path;
}

test('validate_path_within allows safe create path under base', function () {
	$base = make_temp_dir('cacti-safe-base');

	try {
		$result = validate_path_within('new-file.txt', $base);

		expect($result)->toBe($base . '/new-file.txt');
	} finally {
		remove_tree($base);
	}
});

test('validate_path_within rejects path separators and traversal-like names', function () {
	$base = make_temp_dir('cacti-sep-base');

	try {
		expect(validate_path_within('../oops', $base))->toBeFalse();
		expect(validate_path_within('nested/path.txt', $base))->toBeFalse();
		expect(validate_path_within("nested\\path.txt", $base))->toBeFalse();
	} finally {
		remove_tree($base);
	}
});

test('validate_path_within rejects windows drive and UNC-like path forms', function () {
	$base = make_temp_dir('cacti-win-base');

	try {
		expect(validate_path_within('C:\\Windows\\Temp\\evil.txt', $base))->toBeFalse();
		expect(validate_path_within('\\\\server\\share\\evil.txt', $base))->toBeFalse();
	} finally {
		remove_tree($base);
	}
});

test('validate_relative_path_within rejects unix and windows traversal segments', function () {
	$base = make_temp_dir('cacti-rel-base');

	try {
		expect(validate_relative_path_within('../etc/passwd', $base))->toBeFalse();
		expect(validate_relative_path_within('scripts/..\\..\\secret.txt', $base))->toBeFalse();
	} finally {
		remove_tree($base);
	}
});

test('validate_relative_path_within blocks symlink escape for existing and create paths', function () {
	$base    = make_temp_dir('cacti-link-base');
	$outside = make_temp_dir('cacti-link-outside');
	$link    = $base . '/scripts';

	try {
		file_put_contents($outside . '/secret.txt', 'secret');

		// Symlink creation may be restricted on some environments.
		if (!@symlink($outside, $link)) {
			test()->markTestSkipped('Symlink creation unavailable in this environment');
		}

		expect(validate_relative_path_within('scripts/secret.txt', $base))->toBeFalse();
		expect(validate_relative_path_within('scripts/new-file.txt', $base))->toBeFalse();
	} finally {
		remove_tree($base);
		remove_tree($outside);
	}
});
