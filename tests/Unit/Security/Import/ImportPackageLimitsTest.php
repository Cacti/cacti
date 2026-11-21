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
 * The functions are the unchanged ones from lib/import.php, loaded into this
 * namespace so the log and the key checks can be answered here.
 */

namespace ImportPackageLimitsTest;

function __($text) {
	return $text;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['import_package_limits']['log'][] = $facility . ': ' . $message;
}

function get_public_key() {
	return 'cacti key';
}

function is_cacti_public_key($key) {
	$GLOBALS['import_package_limits']['keys'][] = $key;

	return $key === 'cacti key';
}

function xml_to_array($xml) {
	return json_decode(json_encode($xml), true);
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

foreach (array('import_package_max_bytes', 'import_package_within_size_limit', 'import_package_get_details', 'import_package_get_public_key', 'import_read_package_data') as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/import.php', $function, __NAMESPACE__);
}

function package_file($contents) {
	$file = tempnam(sys_get_temp_dir(), 'cacti-pkg-limit-');

	file_put_contents($file, $contents);

	return $file;
}

/*
 * Write $bytes of zeros as a gzip file in 1 MiB chunks, so the test never
 * holds the decompressed Package in memory.
 */
function oversized_package($bytes) {
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

beforeEach(function () {
	$GLOBALS['import_package_limits'] = array('log' => array(), 'keys' => array());
});

test('the limit is far larger than any template set', function () {
	expect(import_package_max_bytes())->toBe(64 * 1024 * 1024);
});

test('a Package within the limit is accepted and one past it is not', function () {
	$file = package_file(gzencode(str_repeat('a', 4096)));

	try {
		expect(import_package_within_size_limit($file, 4096))->toBeTrue()
			->and($GLOBALS['import_package_limits']['log'])->toBe(array())
			->and(import_package_within_size_limit($file, 4095))->toBeFalse()
			->and($GLOBALS['import_package_limits']['log'])->toBe(array('IMPORT: WARNING: Package ' . $file . ' is damaged or decompresses past 4095 bytes'));
	} finally {
		unlink($file);
	}
});

test('a Package that is not compressed is measured as it is', function () {
	$file = package_file(str_repeat('a', 4096));

	try {
		expect(import_package_within_size_limit($file, 4096))->toBeTrue()
			->and(import_package_within_size_limit($file, 4095))->toBeFalse();
	} finally {
		unlink($file);
	}
});

test('a damaged or missing Package is refused', function () {
	$good = gzencode(str_repeat('abc', 300));
	$file = package_file(substr($good, 0, 10) . str_repeat("\xff", 40) . substr($good, 50));

	try {
		expect(import_package_within_size_limit($file))->toBeFalse()
			->and(import_package_within_size_limit($file . '-missing'))->toBeFalse();
	} finally {
		unlink($file);
	}
});

test('the Packages shipped with Cacti are within the limit', function () {
	$packages = glob(dirname(__DIR__, 4) . '/install/templates/*.xml.gz');

	expect($packages)->not->toBe(array());

	foreach ($packages as $package) {
		expect(import_package_within_size_limit($package))->toBeTrue();
	}
});

test('the readers refuse a Package that decompresses past the limit', function () {
	$file       = oversized_package(import_package_max_bytes() + 1);
	$public_key = 'unset';

	try {
		expect(import_package_get_details($file))->toBe(array('public_key' => ''))
			->and(import_package_get_public_key($file))->toBe('')
			->and(import_read_package_data($file, $public_key))->toBeFalse()
			->and($public_key)->toBe('unset')
			->and($GLOBALS['import_package_limits']['keys'])->toBe(array());
	} finally {
		unlink($file);
	}
});

test('the readers still read a Package within the limit', function () {
	$file = package_file(gzencode("<cacti>\n<info><name>Local Linux</name><version>1.2.30</version></info>\n<publickey>" . base64_encode('cacti key') . "</publickey>\n</cacti>\n"));

	try {
		$details = import_package_get_details($file);

		expect($details['name'])->toBe('Local Linux')
			->and($details['version'])->toBe('1.2.30')
			->and($details['public_key'])->toBe('cacti key')
			->and(import_package_get_public_key($file))->toBe('cacti key');
	} finally {
		unlink($file);
	}
});
