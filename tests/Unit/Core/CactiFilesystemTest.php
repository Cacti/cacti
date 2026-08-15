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

/*
 * Cacti\Filesystem\CactiFilesystem does not exist on 1.2.x. This test was
 * merged without lib/CactiFilesystem.php, so requiring it aborted the whole
 * Unit suite at load time. It is kept as a specification of the intended API
 * and skipped until the helper is actually backported.
 */

test('CactiFilesystem operations work correctly', function () {
	$temp_dir = sys_get_temp_dir() . '/cacti_fs_test_' . mt_rand();
	$temp_file = $temp_dir . '/test.txt';
	
	\Cacti\Filesystem\CactiFilesystem::mkdir($temp_dir);
	expect(\Cacti\Filesystem\CactiFilesystem::exists($temp_dir))->toBeTrue();
	
	\Cacti\Filesystem\CactiFilesystem::dumpFile($temp_file, 'hello world');
	expect(\Cacti\Filesystem\CactiFilesystem::exists($temp_file))->toBeTrue();
	expect(file_get_contents($temp_file))->toBe('hello world');
	
	\Cacti\Filesystem\CactiFilesystem::remove($temp_dir);
	expect(\Cacti\Filesystem\CactiFilesystem::exists($temp_dir))->toBeFalse();
})->skip('lib/CactiFilesystem.php is not present on 1.2.x');
