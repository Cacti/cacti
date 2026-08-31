<?php
declare(strict_types = 1);
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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

function rrd_atomic_test_directory(string $suffix) : string {
	$directory = sys_get_temp_dir() . '/cacti-rrd-atomic-' . $suffix . '-' . bin2hex(random_bytes(5));

	if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create atomic-write test directory');
	}

	return $directory;
}

test('atomic graph writes replace complete files inside the approved root', function () {
	$root = rrd_atomic_test_directory('success');
	$path = $root . '/graph.png';

	try {
		file_put_contents($path, 'old');

		expect(rrdtool_atomic_write($path, "new\0binary", $root))->toBeTrue()
			->and(file_get_contents($path))->toBe("new\0binary")
			->and(glob($root . '/.cacti-rrd-*'))->toBe([])
			->and(fileperms($path) & 0777)->toBe(0644);
	} finally {
		@unlink($path);
		@rmdir($root);
	}
});

test('atomic graph writes reject destinations outside the approved root', function () {
	$root    = rrd_atomic_test_directory('root');
	$outside = rrd_atomic_test_directory('outside');
	$path    = $outside . '/graph.png';

	try {
		expect(rrdtool_atomic_write($path, 'data', $root))->toBeFalse()
			->and(file_exists($path))->toBeFalse();
	} finally {
		if (file_exists($path)) {
			unlink($path);
		}

		@rmdir($outside);
		@rmdir($root);
	}
});

test('atomic graph writes reject missing roots and missing parents', function () {
	$base = rrd_atomic_test_directory('missing');

	try {
		expect(rrdtool_atomic_write($base . '/graph.png', 'data', $base . '/missing-root'))->toBeFalse()
			->and(rrdtool_atomic_write($base . '/missing-parent/graph.png', 'data', $base))->toBeFalse();
	} finally {
		@rmdir($base);
	}
});

test('RRDtool output filenames are escaped at command construction', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

	expect($source)->toContain("cacti_escapeshellarg((string) \$graph_data_array['export_filename'])")
		->and($source)->toContain("cacti_escapeshellarg((string) \$graph_data_array['output_filename'])");
});
