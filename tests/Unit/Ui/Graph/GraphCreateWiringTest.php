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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 4) . '/lib/database.php';

/*
 * #7380: the "connect the dots" step of create_complete_graph_from_template()
 * wires each new graph item to its new data source item (task_item_id). The
 * optimization pre-fetches the two id maps in bulk instead of running two
 * SELECTs plus an UPDATE per item. These tests prove the bulk version produces
 * byte-identical task_item_id wiring to the original per-item version, against a
 * real (sqlite-backed) database via FakeMySQLPDO.
 *
 * The optimized helper is extracted from lib/template.php and eval'd (as
 * issue7380_connect) so the test exercises the production source unmodified.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/lib/template.php');
if (preg_match('/^function graph_template_connect_task_items\([^)]*\).*?^\}/sm', $src, $m) !== 1) {
	throw new RuntimeException('could not locate graph_template_connect_task_items() in lib/template.php');
}
eval(preg_replace('/^function graph_template_connect_task_items\(/m', 'function issue7380_connect(', $m[0]));

/* the original per-item implementation, kept here as the equivalence reference */
function issue7380_reference(int $graph_template_id, array $cache_array, $db_conn): void {
	$template_item_list = db_fetch_assoc_prepared('SELECT
		gti.id, dtr.id AS data_template_rrd_id, dtr.data_template_id
		FROM graph_templates_item AS gti
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		WHERE gti.graph_template_id = ?
		AND local_graph_id=0
		AND task_item_id>0',
		[$graph_template_id], true, $db_conn);

	foreach ($template_item_list as $template_item) {
		if (isset($cache_array['local_data_id'][$template_item['data_template_id']])) {
			$local_data_id = $cache_array['local_data_id'][$template_item['data_template_id']];

			$graph_template_item_id = db_fetch_cell_prepared('SELECT id
				FROM graph_templates_item
				WHERE local_graph_template_item_id = ?
				AND local_graph_id = ?',
				[$template_item['id'], $cache_array['local_graph_id']], '', true, $db_conn);

			$data_template_rrd_id = db_fetch_cell_prepared('SELECT id
				FROM data_template_rrd
				WHERE local_data_template_rrd_id = ?
				AND local_data_id = ?',
				[$template_item['data_template_rrd_id'], $local_data_id], '', true, $db_conn);

			if (!empty($data_template_rrd_id)) {
				db_execute_prepared('UPDATE graph_templates_item
					SET task_item_id = ?
					WHERE id = ?',
					[$data_template_rrd_id, $graph_template_item_id], true, $db_conn);
			}
		}
	}
}

function issue7380_seed($conn): void {
	$conn->exec('DROP TABLE IF EXISTS graph_templates_item');
	$conn->exec('DROP TABLE IF EXISTS data_template_rrd');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, graph_template_id INTEGER, local_graph_id INTEGER, local_graph_template_item_id INTEGER, task_item_id INTEGER)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, data_template_id INTEGER, local_data_id INTEGER, local_data_template_rrd_id INTEGER)');

	// template side: two graph items (10,11) each wired to a template rrd (20,21)
	$conn->exec('INSERT INTO graph_templates_item VALUES (10,5,0,0,20),(11,5,0,0,21)');
	$conn->exec('INSERT INTO data_template_rrd     VALUES (20,7,0,0),(21,8,0,0)');

	// created side: graph local_graph_id=100 with two data sources (200 for dt 7, 201 for dt 8)
	$conn->exec('INSERT INTO graph_templates_item VALUES (110,5,100,10,0),(111,5,100,11,0)');
	$conn->exec('INSERT INTO data_template_rrd     VALUES (210,7,200,20),(211,8,201,21)');
}

function issue7380_wiring($conn): array {
	$rows = $conn->query('SELECT id, task_item_id FROM graph_templates_item WHERE local_graph_id = 100 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
	$out  = [];

	foreach ($rows as $r) {
		$out[(int) $r['id']] = (int) $r['task_item_id'];
	}

	return $out;
}

$cache = ['local_graph_id' => 100, 'local_data_id' => [7 => 200, 8 => 201]];

test('bulk connect wires each new graph item to its new data source item', function () use ($cache) {
	$conn = new FakeMySQLPDO();
	issue7380_seed($conn);

	issue7380_connect(5, $cache, $conn);

	// item 110 (from template item 10) -> created rrd 210; item 111 -> 211
	expect(issue7380_wiring($conn))->toBe([110 => 210, 111 => 211]);
});

test('bulk connect produces identical wiring to the per-item version', function () use ($cache) {
	$ref = new FakeMySQLPDO();
	issue7380_seed($ref);
	issue7380_reference(5, $cache, $ref);
	$reference_wiring = issue7380_wiring($ref);

	$bulk = new FakeMySQLPDO();
	issue7380_seed($bulk);
	issue7380_connect(5, $cache, $bulk);
	$bulk_wiring = issue7380_wiring($bulk);

	expect($bulk_wiring)->toBe($reference_wiring);
	// and it actually wired something (guards against both being all-zero)
	expect($bulk_wiring)->toBe([110 => 210, 111 => 211]);
});

test('a missing data source leaves that item unwired, same as before', function () {
	// only data template 7 got a data source; template item 11 (dt 8) must stay 0
	$cache = ['local_graph_id' => 100, 'local_data_id' => [7 => 200]];

	$ref = new FakeMySQLPDO();
	issue7380_seed($ref);
	issue7380_reference(5, $cache, $ref);

	$bulk = new FakeMySQLPDO();
	issue7380_seed($bulk);
	issue7380_connect(5, $cache, $bulk);

	expect(issue7380_wiring($bulk))->toBe(issue7380_wiring($ref));
	expect(issue7380_wiring($bulk))->toBe([110 => 210, 111 => 0]);
});

test('create path calls the bulk connect helper and uses a bulk rrd fetch', function () use ($src) {
	expect($src)->toContain('graph_template_connect_task_items($graph_template_id, $cache_array);');

	$fn = strstr($src, 'function graph_template_connect_task_items');
	$fn = substr($fn, 0, strpos($fn, "\nfunction ", 1));

	expect($fn)->toContain('WHERE local_data_id IN ('); // bulk pre-fetch
	expect($fn)->not->toContain('AND local_data_id = ?'); // no per-item rrd SELECT remains
});
