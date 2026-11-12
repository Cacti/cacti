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
 * Package files arrive from repositories whose manifest is not signed, and
 * the import decompresses them whole.  A manifest file name must name a file
 * directly inside the repository, and a Package that decompresses past
 * import_package_max_bytes() is refused before any reader loads it.
 */

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/import.php';
require_once dirname(__DIR__, 3) . '/lib/xml.php';

/*
 * Write $bytes of zeros as a gzip file in 1 MiB chunks, so the test never
 * holds the decompressed Package in memory.
 */
function oversizedPackage(int $bytes): string {
	$file = tempnam(sys_get_temp_dir(), 'cacti-pkg-limit-');
	$gz   = gzopen($file, 'wb9');
	$left = $bytes;

	while ($left > 0) {
		$size  = min($left, 1048576);
		gzwrite($gz, str_repeat("\0", $size));
		$left -= $size;
	}

	gzclose($gz);

	return $file;
}

describe('package_repo_filename_valid()', function () {
	it('accepts a file directly inside the repository', function () {
		expect(package_repo_filename_valid('package.manifest'))->toBeTrue()
			->and(package_repo_filename_valid('Cisco_Router.xml.gz'))->toBeTrue();
	});

	it('rejects a name that leaves the repository or is hidden', function (string $name) {
		expect(package_repo_filename_valid($name))->toBeFalse();
	})->with([
		'empty'        => [''],
		'parent'       => ['../package.xml.gz'],
		'subdirectory' => ['dir/package.xml.gz'],
		'absolute'     => ['/etc/passwd'],
		'backslash'    => ['..\\package.xml.gz'],
		'hidden'       => ['.htaccess'],
		'null byte'    => ["package.xml.gz\0.php"],
	]);
});

describe('import_package_within_size_limit()', function () {
	it('allows Packages far larger than any template set by default', function () {
		expect(import_package_max_bytes())->toBe(64 * 1024 * 1024);
	});

	it('accepts a Package within the limit and rejects one past it', function () {
		$file = tempnam(sys_get_temp_dir(), 'cacti-pkg-limit-');
		file_put_contents($file, gzencode(str_repeat('a', 4096)));

		expect(import_package_within_size_limit($file, 4096))->toBeTrue()
			->and(import_package_within_size_limit($file, 4095))->toBeFalse();

		unlink($file);
	});

	it('rejects a damaged or missing Package', function () {
		$file = tempnam(sys_get_temp_dir(), 'cacti-pkg-limit-');
		$good = gzencode(str_repeat('abc', 300));
		file_put_contents($file, substr($good, 0, 10) . str_repeat("\xff", 40) . substr($good, 50));

		expect(import_package_within_size_limit($file))->toBeFalse()
			->and(import_package_within_size_limit($file . '-missing'))->toBeFalse();

		unlink($file);
	});
});

describe('Package readers', function () {
	it('refuse a Package that decompresses past the limit', function () {
		$file       = oversizedPackage(import_package_max_bytes() + 1);
		$public_key = '';

		expect(import_read_package_data($file, $public_key, true))->toBeFalse()
			->and(import_get_package_info($file))->toBeFalse()
			->and(import_package_get_details($file))->toBe(['public_key' => ''])
			->and(import_package_get_public_key($file))->toBe('');

		unlink($file);
	});
});
