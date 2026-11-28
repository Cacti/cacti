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
*/

require_once dirname(__DIR__, 2) . '/lib/float_rrd_temp.php';

test('float_rrd_create_private_temp_dir creates an owner-only 0700 directory and rejects invalid bases', function () : void {
	$base = sys_get_temp_dir() . '/cacti_float_parent_' . bin2hex(random_bytes(6));
	mkdir($base, 0755, true);
	$symlink = $base . '_link';
	symlink($base, $symlink);

	$created = false;

	try {
		expect(float_rrd_create_private_temp_dir($base . '/does_not_exist'))->toBeFalse();
		expect(float_rrd_create_private_temp_dir($symlink))->toBeFalse();

		$created = float_rrd_create_private_temp_dir($base);
		expect($created)->toBeString();
		expect(is_dir($created))->toBeTrue();
		expect(fileperms($created) & 0777)->toBe(0700);
	} finally {
		if (is_string($created)) {
			float_rrd_cleanup_temp_dir($created);
		}
		@unlink($symlink);
		@rmdir($base);
	}
});

test('float_rrd_build_xml_path generates unpredictable xml filenames inside the private directory', function () : void {
	$dir = '/tmp/cacti_float_example/';
	$a   = float_rrd_build_xml_path($dir, 42);
	$b   = float_rrd_build_xml_path($dir, 42);

	expect($a)->toMatch('#^/tmp/cacti_float_example/42_[0-9a-f]{16}\.xml$#');
	expect($b)->toMatch('#^/tmp/cacti_float_example/42_[0-9a-f]{16}\.xml$#');
	expect($a)->not->toBe($b);
});

test('float_rrd_open_private_file enforces 0600 permissions and rejects symlinks', function () : void {
	$dir = float_rrd_create_private_temp_dir();
	expect($dir)->toBeString();

	$regular = $dir . '/dump.xml';
	$target  = $dir . '/target.txt';
	$symlink = $dir . '/link.xml';
	file_put_contents($target, 'untouched');
	symlink($target, $symlink);

	try {
		$handle = float_rrd_open_private_file($regular, 'wb');
		expect(is_resource($handle))->toBeTrue();
		fwrite($handle, '<rrd/>');
		fclose($handle);

		expect(fileperms($regular) & 0777)->toBe(0600);
		expect(file_get_contents($regular))->toBe('<rrd/>');

		expect(float_rrd_open_private_file($symlink, 'wb'))->toBeFalse();
		expect(file_get_contents($target))->toBe('untouched');
		expect(float_rrd_open_private_file('', 'wb'))->toBeFalse();
		expect(float_rrd_open_private_file($dir . '/missing_sub/file.xml', 'wb'))->toBeFalse();
	} finally {
		float_rrd_cleanup_temp_dir($dir);
	}
});

test('float_rrd_cleanup_temp_dir removes temp xml files and optionally preserves the debug log', function () : void {
	$dir = float_rrd_create_private_temp_dir();
	expect($dir)->toBeString();

	$xml = $dir . '/7_abcdef0123456789.xml';
	$log = $dir . '/cacti_float_rrdfiles.log';
	file_put_contents($xml, '<rrd/>');
	file_put_contents($log, "debug line\n");

	try {
		float_rrd_cleanup_temp_dir($dir, true);
		expect(file_exists($xml))->toBeFalse();
		expect(file_exists($log))->toBeTrue();
		expect(is_dir($dir))->toBeTrue();

		float_rrd_cleanup_temp_dir($dir, false);
		expect(file_exists($log))->toBeFalse();
		expect(is_dir($dir))->toBeFalse();

		// Safe no-ops on empty or already-removed paths
		float_rrd_cleanup_temp_dir('');
		float_rrd_cleanup_temp_dir($dir);
	} finally {
		@unlink($xml);
		@unlink($log);
		@rmdir($dir);
	}
});

test('cli/float_rrdfiles.php enforces RRA containment and private temp directory usage', function () : void {
	$source = file_get_contents(dirname(__DIR__, 2) . '/cli/float_rrdfiles.php');
	expect($source)->not->toBeFalse();
	expect($source)->toContain("require_once(CACTI_PATH_LIBRARY . '/float_rrd_temp.php');");
	expect($source)->toContain('if (!data_source_path_within_rra($rrd_path))');
	expect($source)->toContain('float_rrd_create_private_temp_dir()');
	expect($source)->toContain('float_rrd_build_xml_path($tmp_dir, $local_data_id)');
	expect($source)->toContain("float_rrd_open_private_file(\$tmp_file, 'wb')");
	expect($source)->toContain("float_rrd_open_private_file(\$tmp_dir . '/cacti_float_rrdfiles.log', 'ab')");
	expect($source)->not->toContain("sys_get_temp_dir() . '/cacti_float_rrdfiles.log'");
});
