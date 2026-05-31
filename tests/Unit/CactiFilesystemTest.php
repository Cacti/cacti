<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

test('CactiFilesystem wraps Symfony filesystem operations', function () {
	require_once __DIR__ . '/../../include/vendor/autoload.php';
	require_once __DIR__ . '/../../lib/CactiFilesystem.php';

	$filesystem = new CactiFilesystem();
	$dir        = sys_get_temp_dir() . '/cacti-filesystem-' . bin2hex(random_bytes(4));
	$file       = CactiFilesystem::join($dir, 'nested', 'settings.txt');
	$copy       = CactiFilesystem::join($dir, 'nested', 'settings.copy');

	try {
		$filesystem->ensureDirectory(dirname($file));
		$filesystem->writeFile($file, 'alpha');
		$filesystem->appendFile($file, "\nbeta");
		$filesystem->copyFile($file, $copy);

		expect($filesystem->has([$file, $copy]))->toBeTrue();
		expect($filesystem->read($file))->toBe("alpha\nbeta");
		expect(CactiFilesystem::canonicalize($dir . '/nested/../nested/settings.txt'))->toBe($file);
	} finally {
		$filesystem->delete($dir);
	}
});
