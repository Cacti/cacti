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
 * The import decompresses a Package whole. A Package that decompresses past
 * import_package_max_bytes() is refused before any reader loads it.
 *
 * The probe runs the unchanged Package readers from lib/import.php with the
 * log and the key checks answered there.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function import_package_limits(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ImportPackageLimitsProbe.php', array(json_encode($arguments)));
}

function import_package_limits_file($contents) {
	$file = tempnam(sys_get_temp_dir(), 'cacti-pkg-limit-');

	file_put_contents($file, $contents);

	return $file;
}

/*
 * Write $bytes of zeros as a gzip file in 1 MiB chunks, so the test never
 * holds the decompressed Package in memory.
 */
function import_package_limits_oversized($bytes) {
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

test('a Package within the limit is accepted and one past it is not', function ($contents) {
	$file = import_package_limits_file($contents);

	try {
		$within = import_package_limits('limit', $file, 4096);
		$past   = import_package_limits('limit', $file, 4095);
	} finally {
		unlink($file);
	}

	expect($within['within'])->toBeTrue()
		->and($within['log'])->toBe(array())
		->and($past['within'])->toBeFalse()
		->and($past['log'])->toBe(array('IMPORT: WARNING: Package ' . $file . ' is damaged or decompresses past 4095 bytes'));
})->with(array(
	'compressed'     => array(gzencode(str_repeat('a', 4096))),
	'not compressed' => array(str_repeat('a', 4096)),
));

test('a damaged or missing Package is refused', function () {
	$good = gzencode(str_repeat('abc', 300));
	$file = import_package_limits_file(substr($good, 0, 10) . str_repeat("\xff", 40) . substr($good, 50));

	try {
		$damaged = import_package_limits('limit', $file);
		$missing = import_package_limits('limit', $file . '-missing');
	} finally {
		unlink($file);
	}

	expect($damaged['within'])->toBeFalse()
		->and($missing['within'])->toBeFalse();
});

test('a Package shipped with Cacti is within the limit, which is 64 MB', function () {
	$packages = glob(dirname(__DIR__, 4) . '/install/templates/*.xml.gz');

	expect($packages)->not->toBe(array());

	$result = import_package_limits('limit', $packages[0]);

	expect($result['within'])->toBeTrue()
		->and($result['max'])->toBe(64 * 1024 * 1024);

	foreach ($packages as $package) {
		expect(filesize($package))->toBeLessThan(1048576);
	}
});

test('the readers refuse a Package that decompresses past the limit', function () {
	$file = import_package_limits_oversized(64 * 1024 * 1024 + 1);

	try {
		$details = import_package_limits('details', $file);
		$read    = import_package_limits('read', $file);
	} finally {
		unlink($file);
	}

	expect($details['details'])->toBe(array('public_key' => ''))
		->and($details['key'])->toBe('')
		->and($read['read'])->toBeFalse()
		->and($read['public_key'])->toBe('unset')
		->and($read['keys'])->toBe(array());
});

test('the readers still read a Package within the limit', function () {
	$file = import_package_limits_file(gzencode("<cacti>\n<info><name>Local Linux</name><version>1.2.30</version></info>\n<publickey>" . base64_encode('cacti key') . "</publickey>\n</cacti>\n"));

	try {
		$result = import_package_limits('details', $file);
	} finally {
		unlink($file);
	}

	expect($result['details']['name'])->toBe('Local Linux')
		->and($result['details']['version'])->toBe('1.2.30')
		->and($result['details']['public_key'])->toBe('cacti key')
		->and($result['key'])->toBe('cacti key')
		->and($result['log'])->toBe(array());
});
