<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// Uses the disposable database and runtime described by AutomationCreationIntegrationTest.
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

beforeEach(function () {
	if (!getenv('CACTI_AUTOMATION_TEST_DSN')) {
		$this->markTestSkipped('Set CACTI_AUTOMATION_TEST_DSN to an isolated cacti_automation_test database.');
	}
});

test('propagation uses exact source ancestry and preserves graph-input overrides', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['binding']);

	foreach (['changed_binding', 'new_binding', 'retemplate_new_binding'] as $key) {
		expect($r[$key])->toBe(102);
	}
	expect($r['changed_text'])->toBe('Changed')->and($r['local_label'])->toBe('Local label')->and($r['ambiguous_binding'])->toBe(0)->and($r['errors'])->toBe([]);
});

test('resequence repairs correct-count graphs and follows later template edits across all graphs', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['resequence']);
	expect(array_map('intval', array_column($r['actual'], 'sequence')))->toBe([1, 2]);
	expect($r['repairs'])->toBe(2)->and($r['all_repaired'])->toBe(0)->and($r['duplicates'])->toBe(1);
});

test('replacement attaches existing RRD items only to the new compatible template', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['switch']);
	expect($r['heartbeat_after_new_template'])->toBe(1200)->and($r['heartbeat_after_old_template'])->toBe(1200);
	expect($r['data_local_template'])->toBe(2)->and($r['data_metadata_template'])->toBe(2)->and((int)$r['rrds'][0]['local_data_template_rrd_id'])->toBe(900001);
});

test('multi-template graphs reuse their own compatible sources and reject absent custom values', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['reuse']);
	expect($r['source_count'])->toBe(2)->and($r['graphs'])->toBe(2)->and(array_map('intval', $r['second_sources']))->toBe(array_map('intval', $r['first_sources']));

	foreach (['other_template', 'legacy', 'right_item'] as $key) {
		expect($r[$key])->toBeTrue();
	}

	foreach (['missing_zero', 'missing_empty', 'wrong_item'] as $key) {
		expect($r[$key])->toBeFalse();
	}
});

test('parent inheritance repairs missing rows and stale child override flags', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['custom']);
	expect($r['rows'])->toHaveCount(4);

	foreach ($r['rows'] as $row) {
		expect($row['value'])->toBe('new-default')->and($row['t_value'])->toBe('');
	}
	expect($r['identity'])->toBe(['data_template_id' => 1, 'local_data_id' => 3, 'host_id' => 1]);
});

test('source duplication rolls back every failed table write and succeeds on retry', function ($table, $nested) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['copy-atomic', $table, $nested]);
	expect($r['failed'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['struct_unchanged'])->toBeTrue();
	expect($r['nested_open'])->toBe($nested == 'nested')->and($r['outer_preserved'])->toBe($nested == 'nested' ? 1 : 0);
	expect((int)$r['retry'])->toBeGreaterThan(1)->and($r['retry_items'])->toBe(1)->and((int)$r['source_copy'])->toBeGreaterThan(0)->and($r['copy_path'])->toBe('');
})->with(['data_local', 'data_template', 'data_template_data', 'data_template_rrd', 'data_input_data'])->with(['owned', 'nested']);

test('template replacement rejects incompatible items and rolls back partial writes', function ($table, $nested) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['switch-atomic', $table, $nested]);
	expect($r['failed'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['struct_unchanged'])->toBeTrue();
	expect($r['nested_open'])->toBe($nested == 'nested')->and($r['outer_preserved'])->toBe($nested == 'nested' ? 1 : 0);
	expect($r['retry'])->toBeTrue()->and($r['path'])->toBe('keep.rrd')->and($r['maximum'])->toBe('999')->and($r['detached'])->toBeTrue()->and($r['detached_parent'])->toBe(0);
})->with(['data_local', 'data_template_data', 'data_template_rrd', 'data_input_data', 'incompatible', 'extra-item'])->with(['owned', 'nested']);

test('large custom propagation updates every field with bounded database calls', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['scale', '2000']);
	expect($r['updated'])->toBe(8000)->and($r['database_calls'])->toBeLessThanOrEqual(7)->and($r['errors'])->toBe([]);
});

test('propagation preserves per-source overrides query identities and output fields', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['custom-boundaries']);
	expect(array_values($r['values']))->toBe(['0', 'local', 'local-index', 'local-output'])->and($r['unchanged'])->toBeTrue();
});

test('multi-item replacement follows source names rather than template row order and retains local ids', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['switch-multiple']);
	expect($r['changed'])->toBeTrue()->and($r['stable_ids'])->toBeTrue()->and(array_map('intval', $r['parents']))->toBe(['second' => 900001, 'traffic' => 900002]);
	expect($r['nested_changed'])->toBeTrue()->and($r['nested_open'])->toBeTrue()->and($r['rolled_back_parent'])->toBe(2);
});

test('data-source form commits or rolls back metadata and ancestry together', function ($case) {
	$r       = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/template_propagation_probe.php', ['form-switch', $case]);
	$success = $case == 'success';
	expect($r['template'])->toBe($success ? 2 : 1)->and($r['metadata_template'])->toBe($success ? 2 : 1);
	expect($r['parent'])->toBe($success ? 900001 : 1)->and($r['error'])->toBe(!$success)->and($r['transaction_open'])->toBeFalse()->and($r['poller_updates'])->toBe($success ? 1 : 0);
})->with(['success', 'incompatible', 'write']);
