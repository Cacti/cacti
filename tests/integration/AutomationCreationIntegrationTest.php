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
 * Import cacti.sql into a disposable local database named cacti_automation_test, or
 * cacti_automation_<name>_test. Set CACTI_AUTOMATION_TEST_DSN to
 * mysql:host=127.0.0.1;port=3306;dbname=cacti_automation_test or to
 * mysql:unix_socket=/path/to/mysql.sock;dbname=cacti_automation_test, and set
 * CACTI_AUTOMATION_TEST_USER / CACTI_AUTOMATION_TEST_PASSWORD as needed.
 * Run from tests/: php vendor/bin/pest integration/AutomationCreationIntegrationTest.php
 * The fixture deletes its test data. Never point it at an installed Cacti database.
 */
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

beforeEach(function () {
	if (!getenv('CACTI_AUTOMATION_TEST_DSN')) {
		$this->markTestSkipped('Set CACTI_AUTOMATION_TEST_DSN to an isolated cacti_automation_test database.');
	}
});

test('OR predicates stay restricted to the graph being placed', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['rule']);
	expect($r['nonmatching_placements'])->toBe(0)
		->and(array_map('intval', $r['matching_placements']))->toBe([1]);
});

test('failed header creation does not place a leaf at root or duplicate it on retry', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['tree-failure']);
	expect($r['after_failure'])->toBe(0)->and($r['leaves'])->toBe(1)->and($r['root_leaves'])->toBe(0);
});

test('deleted and foreign tree destinations do not create unreachable leaves', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['destination']);
	expect($r['deleted_parent_leaves'])->toBe(0)->and($r['foreign_parent_leaves'])->toBe(0);
});

test('graph creation commits complete sources and retries without duplicating graphs', function ($scenario) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', [$scenario]);
	expect($r['created'])->toBeArray()->and($r['graphs'])->toBe(1)->and($r['sources'])->toBe(1)
		->and($r['linked_sources'])->toBe(1)->and($r['hooks_inside_transaction'])->toBe(0)
		->and($r['second']['existing'])->toBeTrue()
		->and($r['transaction_open'])->toBeFalse();

	if ($scenario == 'graph-interruption') {
		expect($r['rows_after_failure'])->toBe(0)->and($r['failed'])->toBeFalse();
	}

	if ($scenario == 'graph-resume') {
		expect((int) $r['created']['local_graph_id'])->toBe(999);
	}
})->with(['graph', 'graph-interruption', 'graph-resume']);

test('graph creation inside a caller transaction fails and names the caller', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['nested']);
	expect($r['failed'])->toBeFalse()->and($r['graphs'])->toBe(0)
		->and($r['errors'])->toHaveCount(1)
		->and($r['errors'][0])->toContain('automation_creation_probe.php:')
		->and($r['errors'][0])->toContain('called it inside an open transaction');
});

test('failed graph or source inserts roll back all rows and allow a successful retry', function ($table) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['graph-failure', $table]);
	expect($r['failed'])->toBeFalse()->and($r['rows_after_failure'])->toBe(0)
		->and($r['sources_after_failure'])->toBe(0)->and($r['hooks_after_failure'])->toBe([])
		->and($r['created'])->toBeArray()->and($r['graphs'])->toBe(1)->and($r['linked_sources'])->toBe(1);
})->with(['graph_local', 'graph_templates_graph', 'graph_templates_item', 'data_local', 'data_template_data', 'data_template_rrd', 'data_input_data', 'graph_attachment']);

test('batched tree creation writes each final position once and preserves natural ordering', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['tree-scale', '200']);
	expect($r['position_updates'])->toBe(200)->and($r['ordered_graphs'])->toBe(range(200,1))
		->and($r['repeat_position_updates'])->toBe(0);
});

test('data-source reuse follows actual output mappings and preserves traffic width changes', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_source_reuse_probe.php');

	foreach (['before', 'after', 'multi', 'traffic32', 'traffic64'] as $key) {
		expect((int)$r[$key])->toBe(1);
	}
	expect($r['different'])->toBe([]);
});

test('concurrent workers converge on one graph or tree path', function ($kind) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_concurrency_probe.php', [$kind]);
	expect($r['ids'][0])->toBeGreaterThan(0)->and($r['ids'][1])->toBe($r['ids'][0]);

	if ($kind == 'graph') {
		expect($r['graphs'])->toBe(1)->and($r['sources'])->toBe(1);
	} else {
		expect($r['headers'])->toBe(1)->and($r['leaves'])->toBe(1);
	}
})->with(['graph', 'tree']);

test('unchanged data-query discovery uses bounded database calls', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_dq_scale_probe.php', ['1000', '8']);
	expect($r['database_calls'])->toBeLessThan(12)->and($r['duplicate_checks'])->toBe(0);
});

test('nested batches preserve inherited manual alphabetic numeric and natural ordering', function ($style) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_creation_probe.php', ['tree-order', (string)$style]);
	expect($r['batched'])->toBe($r['immediate']);
})->with([1, 2, 3, 4]);

test('data-query discovery creates only missing matching string indexes and preserves retry identity', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/automation_dq_scale_probe.php', ['10', '8', 'mixed']);
	expect($r['duplicate_checks'])->toBe(0);
});
