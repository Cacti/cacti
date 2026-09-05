<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace InstallerFileCleanupTest;

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_BASE . '/install/functions.php';

test('recursive installer cleanup removes symlinks without traversing their targets', function () {
	$root     = CACTI_PATH_BASE . '/cache/cacti-installer-cleanup-' . bin2hex(random_bytes(8));
	$external = sys_get_temp_dir() . '/cacti-installer-target-' . bin2hex(random_bytes(8));

	mkdir($root, 0700);
	mkdir($root . '/target', 0700);
	mkdir($external, 0700);
	file_put_contents($root . '/target/keep.txt', 'internal');
	file_put_contents($external . '/keep.txt', 'external');
	symlink($root . '/target', $root . '/internal-link');
	symlink($external, $root . '/external-link');

	try {
		install_rmdir_recursive($root . '/internal-link', true);
		install_rmdir_recursive($root . '/external-link', true);

		expect(file_exists($root . '/internal-link'))->toBeFalse()
			->and(file_exists($root . '/target/keep.txt'))->toBeTrue()
			->and(file_exists($root . '/external-link'))->toBeFalse()
			->and(file_exists($external . '/keep.txt'))->toBeTrue();
	} finally {
		@unlink($root . '/internal-link');
		@unlink($root . '/external-link');
		@unlink($root . '/target/keep.txt');
		@rmdir($root . '/target');
		@rmdir($root);
		@unlink($external . '/keep.txt');
		@rmdir($external);
	}
});

test('installer cleanup rejects regular files outside the Cacti tree', function () {
	$outside = sys_get_temp_dir() . '/cacti-installer-outside-' . bin2hex(random_bytes(8));
	file_put_contents($outside, 'keep');

	try {
		install_unlink($outside);
		expect(file_get_contents($outside))->toBe('keep');
	} finally {
		@unlink($outside);
	}
});
