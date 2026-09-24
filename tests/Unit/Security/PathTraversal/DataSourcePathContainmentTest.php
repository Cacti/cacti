<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for GHSA-9qff-xccr-rrc5.
 *
 * data_source_path is stored without path validation and used verbatim as the
 * RRD filename. data_source_path_within_rra() is the containment applied where
 * every consumer resolves the path, keeping the write inside the RRA directory,
 * including against a symlink pivot below an otherwise-contained ancestor.
 *
 * @group regression
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';

if (!defined('CACTI_PATH_RRA')) {
	define('CACTI_PATH_RRA', sys_get_temp_dir() . '/cacti_rra_' . bin2hex(random_bytes(6)));
}

// realpath()-based checks below require CACTI_PATH_RRA to be a real, existing
// directory. Another test file loaded earlier in the same process (e.g.
// RrdProxyProtocolTest.php) may already have defined the constant to a purely
// illustrative, non-existent path, so make sure it actually exists on disk
// here regardless of who defined it, and skip the fixture-dependent
// assertions below if this process cannot create it (e.g. no permission).
$rra_ready = is_dir(CACTI_PATH_RRA . '/1') || @mkdir(CACTI_PATH_RRA . '/1', 0755, true);

if ($rra_ready) {
	file_put_contents(CACTI_PATH_RRA . '/host_ds.rrd', 'x');
	file_put_contents(CACTI_PATH_RRA . '/1/host_ds.rrd', 'x');
	$rra_ready = realpath(CACTI_PATH_RRA) !== false;
}

function data_source_path_test_base() : string {
	return (string) realpath(CACTI_PATH_RRA);
}

test('a file directly under the RRA directory is contained', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	expect(data_source_path_within_rra(data_source_path_test_base() . '/host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/1/host_ds.rrd'))->toBeTrue();
});

test('a not-yet-created file under the RRA directory is still contained', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	expect(data_source_path_within_rra(data_source_path_test_base() . '/1/new_ds.rrd'))->toBeTrue();
});

test('an absolute path elsewhere is rejected', function () : void {
	expect(data_source_path_within_rra('/var/www/html/cacti/resource/x.php'))->toBeFalse()
		->and(data_source_path_within_rra('/tmp/x.rrd'))->toBeFalse();
});

test('traversal out of the RRA directory is rejected', function () : void {
	expect(data_source_path_within_rra(data_source_path_test_base() . '/../resource/x.rrd'))->toBeFalse()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/a/../../etc/x'))->toBeFalse();
});

test('a relative path or a lookalike prefix is rejected', function () : void {
	expect(data_source_path_within_rra('1/host_ds.rrd'))->toBeFalse()
		->and(data_source_path_within_rra(data_source_path_test_base() . '_evil/x.rrd'))->toBeFalse();
});

test('empty and null-byte paths are rejected', function () : void {
	expect(data_source_path_within_rra(''))->toBeFalse()
		->and(data_source_path_within_rra("/tmp/x\0.rrd"))->toBeFalse();
});

test('a symlink pivot below the RRA directory is rejected even for a not-yet-created file', function () use ($rra_ready) : void {
	if (!$rra_ready) {
		$this->markTestSkipped('CACTI_PATH_RRA does not resolve to a directory this process can create fixtures under');
	}

	$outside = sys_get_temp_dir() . '/cacti_rra_outside_' . bin2hex(random_bytes(6));
	mkdir($outside, 0755, true);

	$link = data_source_path_test_base() . '/linked';

	if (!@symlink($outside, $link)) {
		$this->markTestSkipped('symlink() is not available in this environment');
	}

	try {
		expect(data_source_path_within_rra($link . '/escaped.rrd'))->toBeFalse();
	} finally {
		unlink($link);
		rmdir($outside);
	}
});


