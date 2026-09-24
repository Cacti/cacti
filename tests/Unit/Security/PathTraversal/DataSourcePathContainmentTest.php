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

// realpath()-based checks require CACTI_PATH_RRA to be a real directory, so build
// one instead of pointing the constant at a fictional path.
if (!defined('CACTI_PATH_RRA')) {
	$rra_dir = sys_get_temp_dir() . '/cacti_rra_' . bin2hex(random_bytes(6));
	mkdir($rra_dir . '/1', 0755, true);
	file_put_contents($rra_dir . '/host_ds.rrd', 'x');
	file_put_contents($rra_dir . '/1/host_ds.rrd', 'x');

	define('CACTI_PATH_RRA', $rra_dir);
}

function data_source_path_test_base() : string {
	return realpath(CACTI_PATH_RRA);
}

test('a file directly under the RRA directory is contained', function () : void {
	expect(data_source_path_within_rra(data_source_path_test_base() . '/host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra(data_source_path_test_base() . '/1/host_ds.rrd'))->toBeTrue();
});

test('a not-yet-created file under the RRA directory is still contained', function () : void {
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
		->and(data_source_path_within_rra(data_source_path_test_base() . "/x\0.rrd"))->toBeFalse();
});

test('a symlink pivot below the RRA directory is rejected even for a not-yet-created file', function () : void {
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

