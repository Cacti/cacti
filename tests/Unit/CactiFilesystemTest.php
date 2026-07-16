<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
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
