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
 * every consumer resolves the path, keeping the write inside the RRA directory.
 *
 * @group regression
 */

if (!defined('CACTI_PATH_RRA')) {
	define('CACTI_PATH_RRA', '/var/lib/cacti/rra');
}

require_once dirname(__DIR__, 2) . '/lib/functions.php';

test('a file directly under the RRA directory is contained', function () : void {
	expect(data_source_path_within_rra('/var/lib/cacti/rra/host_ds.rrd'))->toBeTrue()
		->and(data_source_path_within_rra('/var/lib/cacti/rra/1/host_ds.rrd'))->toBeTrue();
});

test('an absolute path elsewhere is rejected', function () : void {
	expect(data_source_path_within_rra('/var/www/html/cacti/resource/x.php'))->toBeFalse()
		->and(data_source_path_within_rra('/tmp/x.rrd'))->toBeFalse();
});

test('traversal out of the RRA directory is rejected', function () : void {
	expect(data_source_path_within_rra('/var/lib/cacti/rra/../resource/x.rrd'))->toBeFalse()
		->and(data_source_path_within_rra('/var/lib/cacti/rra/a/../../etc/x'))->toBeFalse();
});

test('a relative path or a lookalike prefix is rejected', function () : void {
	expect(data_source_path_within_rra('1/host_ds.rrd'))->toBeFalse()
		->and(data_source_path_within_rra('/var/lib/cacti/rra_evil/x.rrd'))->toBeFalse();
});

test('empty and null-byte paths are rejected', function () : void {
	expect(data_source_path_within_rra(''))->toBeFalse()
		->and(data_source_path_within_rra("/var/lib/cacti/rra/x\0.rrd"))->toBeFalse();
});
