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

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiFilesystem.php';

test('CactiFilesystem wraps Symfony filesystem operations', function () {
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
		expect(CactiFilesystem::canonicalize(CactiFilesystem::join($dir, 'nested', '..', 'nested', 'settings.txt')))->toBe($file);
	} finally {
		$filesystem->delete($dir);
	}
});

test('CactiFilesystem read throws when the file is missing', function () {
	$filesystem = new CactiFilesystem();
	$missing    = sys_get_temp_dir() . '/cacti-filesystem-missing-' . bin2hex(random_bytes(4));

	expect(fn () => $filesystem->read($missing))
		->toThrow(RuntimeException::class);
});

test('CactiFilesystem read rejects a path containing a null byte', function () {
	$filesystem = new CactiFilesystem();

	expect(fn () => $filesystem->read("/tmp/foo\0bar"))
		->toThrow(RuntimeException::class, 'null byte');
});
