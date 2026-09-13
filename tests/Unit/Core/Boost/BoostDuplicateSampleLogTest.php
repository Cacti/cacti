<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The BOOST "ignored duplicate sample keys" warning used to name neither the
 * data source nor the timestamp, so an operator could not tell a retried
 * batch from a colliding writer. boost_describe_ignored_sample_keys() is the
 * message builder; flush only logs when INSERT IGNORE drops rows.
 */

$root = dirname(__DIR__, 4);

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/boost.php';

test('flush still logs through boost_log_ignored_sample_keys', function () use ($root) {
	$src = file_get_contents($root . '/lib/boost.php');

	expect($src)->toContain('boost_log_ignored_sample_keys($chunk, $rows, $inserted)');
	expect($src)->not->toContain('Boost staging ignored one or more duplicate sample keys.');
});

test('parser reads quoted boost_poller_on_demand tuples', function () {
	$row = boost_parse_output_tuple("(12,'traffic_in','2026-09-08 09:54:02','1.25')");

	expect($row)->toBe([
		'local_data_id' => 12,
		'rrd_name'      => 'traffic_in',
		'time'          => '2026-09-08 09:54:02',
	]);
});

test('parser reads cmd.php CURRENT_TIMESTAMP tuples', function () {
	$row = boost_parse_output_tuple("(7, 'ifHCInOctets', CURRENT_TIMESTAMP(), 'U')");

	expect($row)->toBe([
		'local_data_id' => 7,
		'rrd_name'      => 'ifHCInOctets',
		'time'          => 'CURRENT_TIMESTAMP()',
	]);
});

test('in-batch duplicates name the colliding DS, field, and time', function () {
	$message = boost_describe_ignored_sample_keys([
		"(12,'traffic_in','2026-09-08 09:54:02','1')",
		"(12,'traffic_in','2026-09-08 09:54:02','2')",
		"(12,'traffic_out','2026-09-08 09:54:02','3')",
	], 3, 2);

	expect($message)->toContain('ignored 1 of 3 duplicate sample keys')
		->and($message)->toContain('duplicate keys inside this INSERT')
		->and($message)->toContain('local_data_id=12')
		->and($message)->toContain('DS[12] traffic_in @ 2026-09-08 09:54:02 (x2)')
		->and($message)->not->toContain('traffic_out');
});

test('collisions against existing rows list the attempted keys', function () {
	$message = boost_describe_ignored_sample_keys([
		"(44,'ds','2026-09-08 09:54:06','9')",
	], 1, 0);

	expect($message)->toContain('ignored 1 of 1 duplicate sample keys')
		->and($message)->toContain('keys already in poller_output_boost')
		->and($message)->toContain('DS[44] ds @ 2026-09-08 09:54:06');
});

test('describe returns empty when every row was inserted', function () {
	expect(boost_describe_ignored_sample_keys(["(1,'ds','t','1')"], 1, 1))->toBe('');
});
