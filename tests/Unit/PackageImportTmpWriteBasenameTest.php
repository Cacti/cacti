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

/*
 * Tests for the temp-write path containment in package_import.php.
 *
 * package_file_get_contents() wrote the downloaded package to
 *   $tmp_dir . '/' . $package_file
 * using the raw request-derived $package_file while four sibling write sites
 * applied basename(). A manifest filename containing separators or '..' could
 * escape $tmp_dir. The write path now passes $package_file through basename().
 */

function getPackageImportSource(): string {
	$src = file_get_contents(__DIR__ . '/../../package_import.php');
	expect($src)->not->toBeFalse('Failed to read package_import.php');

	return $src;
}

// --- source guard: the temp write uses basename() ---

test('package_import temp write applies basename to the package file', function () {
	$source  = getPackageImportSource();
	$pattern = '/\$xmlfile = \$tmp_dir \. \'\/\' \. basename\(\$package_file\);/';
	expect(preg_match($pattern, $source))->toBe(1,
		'the temp write must build the path with basename($package_file)'
	);
});

test('package_import no temp write uses a raw unprefixed package file', function () {
	$source  = getPackageImportSource();
	$pattern = '/\$xmlfile = \$tmp_dir \. \'\/\' \. \$package_file;/';
	expect(preg_match($pattern, $source))->toBe(0,
		'no temp write site may concatenate the raw $package_file'
	);
});

// --- basename() leaf containment, exercised directly ---

/*
 * Replicates the write-path construction so the containment property can be
 * checked against a real temp directory without the Cacti bootstrap.
 */
function packageTmpWritePath(string $tmp_dir, string $package_file): string {
	return $tmp_dir . '/' . basename($package_file);
}

test('basename keeps a traversal package file under the temp directory', function () {
	$tmp  = sys_get_temp_dir() . '/cacti_pkg_test_' . bin2hex(random_bytes(4));
	$path = packageTmpWritePath($tmp, '../evil');
	expect(dirname($path))->toBe($tmp);
	expect(basename($path))->toBe('evil');
});

test('basename strips directory separators from a nested package file', function () {
	$tmp  = sys_get_temp_dir() . '/cacti_pkg_test_' . bin2hex(random_bytes(4));
	$path = packageTmpWritePath($tmp, 'a/b');
	expect(dirname($path))->toBe($tmp);
	expect(basename($path))->toBe('b');
});

test('basename leaves a plain package filename unchanged', function () {
	$tmp  = sys_get_temp_dir() . '/cacti_pkg_test_' . bin2hex(random_bytes(4));
	$path = packageTmpWritePath($tmp, 'package.xml');
	expect($path)->toBe($tmp . '/package.xml');
});
