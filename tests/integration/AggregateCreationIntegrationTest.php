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

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

beforeEach(function () {
	if (!getenv('CACTI_AGGREGATE_TEST_DSN')) {
		$this->markTestSkipped('Set CACTI_AGGREGATE_TEST_DSN to an isolated cacti_aggregate_test database.');
	}
});

function aggregate_probe($case, ...$args) {
	return cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/aggregate_creation_probe.php', array_merge([$case], $args));
}

test('aggregate write failures roll back every table and permit retry', function ($stage) {
	$r = aggregate_probe('write-failure', $stage);
	expect($r['returned'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['restored_id'])->toBeTrue()
		->and($r['transaction_open'])->toBeFalse()->and($r['retry'])->toBeTrue();
})->with(['create-local', 'create-metadata', 'create-aggregate', 'local', 'metadata', 'aggregate', 'delete-items', 'insert-items', 'delete-members', 'insert-members', 'cdef', 'cdef-item', 'total-update']);

test('aggregate transaction boundaries preserve caller ownership and handle failures', function ($case) {
	$r = aggregate_probe('transaction', $case);
	expect($r['returned'])->toBe($case === 'caller')->and($r['unchanged'])->toBeTrue()
		->and($r['transaction_open'])->toBe(in_array($case, ['caller', 'nested']));
})->with(['begin', 'commit', 'exception', 'caller', 'nested']);

test('rebuild preserves saved member order', function () {
	$r = aggregate_probe('order');
	expect($r['returned'])->toBeTrue()->and($r['before'])->toBe([102, 101])
		->and($r['after'])->toBe($r['before'])->and($r['saved_after'])->toBe($r['saved_order']);
});

test('removing the final member clears generated graph items', function () {
	$r = aggregate_probe('empty');
	expect($r['members_remaining'])->toBe(0)->and($r['items_remaining'])->toBe(0);
});

test('membership edits preserve order and roll back with failed rebuilds', function ($case, $members) {
	$r = aggregate_probe('membership', $case);
	$failure = str_starts_with($case, 'failure-');
	expect($r['returned'])->toBe(!$failure)->and(array_map('intval', $r['members']))->toBe($members);
	if ($failure) {
		expect($r['unchanged'])->toBeTrue();
	}
})->with([
	'append' => ['add', [102, 101, 200]],
	'duplicate' => ['duplicate', [102, 101]],
	'remove' => ['remove', [102]],
	'failed append' => ['failure-add', [102, 101]],
	'failed removal' => ['failure-remove', [102, 101]]
]);

$aggregate_modes = [];
foreach ([1, 2, 3] as $total) {
	foreach ([1, 2] as $total_type) {
		foreach ([0, 4, 8, 50, 51, 52, 53] as $graph_type) {
			foreach ([1, 2, 3, 4] as $order) {
				$aggregate_modes["total=$total type=$total_type graph=$graph_type order=$order"] = [$total, $total_type, $graph_type, $order];
			}
		}
	}
}
test('totals stack and reorder combinations produce stable complete graphs', function ($total, $type, $graph, $order) {
	$r = aggregate_probe('matrix', $total, $type, $graph, $order);
	expect($r['returned'])->toBeTrue()->and($r['repeat'])->toBeTrue()->and($r['count'])->toBeGreaterThan(0)
		->and($r['sequences_unique'])->toBeTrue()->and($r['stable'])->toBeTrue();
	$members = $r['member_order'];
	sort($members);
	expect($members)->toBe([101, 102]);
	if ($total !== 1) {
		$expected = $type === 1 ? 'SIMILAR_DATA_SOURCES_NODUPS' : 'ALL_DATA_SOURCES_NODUPS';
		expect(implode(',', $r['expressions']))->toContain($expected);
	}
})->with($aggregate_modes);

$percentile_modes = [];
foreach ([1, 2] as $type) {
	foreach (['current', 'max', 'total', 'total_peak', 'all_max_current', 'all_max_peak', 'aggregate_max', 'aggregate_sum', 'aggregate_sum_peak', 'aggregate_current', 'aggregate_current_peak', 'aggregate', 'aggregate_peak'] as $mode) {
		$expected = $mode;
		if ($mode === 'current') {
			$expected = $type === 1 ? 'aggregate_current' : 'aggregate_sum';
		}
		if ($mode === 'max') {
			$expected = $type === 1 ? 'aggregate_current_peak' : 'aggregate_sum_peak';
		}
		if ($mode === 'total') {
			$expected = 'aggregate_sum';
		}
		if (in_array($mode, ['all_max_current', 'all_max_peak', 'aggregate_max'])) {
			$expected = 'aggregate_peak';
		}
		$percentile_modes["$mode type=$type"] = [$mode, $type, $expected];
	}
}
test('percentile comments and rules preserve whole calculation modes and unique sequences', function ($mode, $type, $expected) {
	$r = aggregate_probe('percentile-mode', $mode, $type);
	expect($r['returned'])->toBeTrue()->and($r['text'])->toBe("95th |95:bits:0:$expected:2|")
		->and($r['value'])->toBe("|95:bits:0:$expected:2|")->and(array_map('intval', $r['sequences']))->toBe([1, 2, 3, 4, 5]);
})->with($percentile_modes);

test('template checkbox changes include absent unchecked fields', function ($case, $skip, $total) {
	$r = aggregate_probe('checkbox', $case);
	expect($r['errors'])->toBeFalse()->and($r['saved'])->toBe(['item_skip' => $skip, 'item_total' => $total]);
})->with([['skip', '', 'on'], ['total', 'on', ''], ['both', '', ''], ['neither', 'on', 'on']]);

test('bulk CDEF snapshot preserves nested missing empty invalid and cyclic semantics', function () {
	$r = aggregate_probe('cdef-snapshot');
	expect($r['same'])->toBeTrue()->and($r['nested'])->toBe('CURRENT_DATA_SOURCE,0,*,2,*')
		->and($r['invalid'])->toBe([null, '', null, null]);
});

test('CDEF database calls stay bounded as unrelated definitions grow', function () {
	$small = aggregate_probe('cdef-scale', 2, 10);
	$large = aggregate_probe('cdef-scale', 2, 1000);
	expect($small['returned'])->toBeTrue()->and($large['returned'])->toBeTrue()
		->and($large['calls'])->toBeLessThanOrEqual($small['calls'] + 2)->and($large['calls'])->toBeLessThan(25);
});

test('totals-only rebuild query volume grows linearly with members', function () {
	$small = aggregate_probe('member-scale', 100);
	$large = aggregate_probe('member-scale', 400);
	expect($small['returned'])->toBeTrue()->and($large['returned'])->toBeTrue()
		->and($large['items'])->toBe(401)->and($large['calls'])->toBeLessThan($small['calls'] * 4 + 10)
		->and($large['sql_bytes'])->toBeLessThan($small['sql_bytes'] * 5);
});

test('graph form saves metadata and checkbox changes atomically', function ($case) {
	$r = aggregate_probe('graph-form', $case);
	expect($r['transaction_open'])->toBeFalse()->and($r['unchanged'])->toBe($case !== 'success');
	if ($case === 'success') {
		expect($r['title'])->toBe('Changed title')->and($r['flags'])->toBe(['item_skip' => '', 'item_total' => '']);
	}
})->with(['success', 'failure', 'metadata', 'title', 'settings', 'items', 'empty-title', 'missing-graph', 'invalid-width']);

test('template form propagates totals atomically', function ($case) {
	$r = aggregate_probe('template-form', $case);
	expect($r['transaction_open'])->toBeFalse()->and($r['unchanged'])->toBe($case !== 'success');
	if ($case === 'success') {
		expect($r['total'])->toBe(2)->and($r['total_type'])->toBe(1);
	}
})->with(['success', 'failure', 'template-row', 'child-settings', 'template-params', 'template-items', 'empty-name', 'missing-template', 'invalid-width']);

test('public aggregate creation and conversion propagate success and failure', function ($case) {
	$r = aggregate_probe('public-api', $case);
	$success = in_array($case, ['create', 'convert', 'combine']);
	expect($r['returned'])->toBe($success)->and($r['transaction_open'])->toBeFalse();
	if (!$success) {
		expect($r['unchanged'])->toBeTrue();
	} else {
		expect($r['graphs'])->toBe($case === 'convert' ? 1 : 2)->and($r['tasks'])->toHaveCount(2);
	}
})->with(['create', 'convert', 'combine', 'failure', 'missing', 'create-local', 'create-metadata', 'create-members', 'convert-write', 'convert-members', 'convert-rebuild', 'convert-empty', 'convert-missing-graph', 'convert-missing-template']);

test('invalid referenced CDEF leaves the existing aggregate intact', function ($case) {
	$r = aggregate_probe('invalid-cdef', $case);
	expect($r['returned'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['transaction_open'])->toBeFalse();
})->with(['missing', 'empty', 'cycle']);

test('palette cache preserves round robin and saved member CF precedence', function ($case) {
	$r = aggregate_probe('palette-cf', $case);
	expect($r['returned'])->toBeTrue()->and($r['palette_queries'])->toBe(1)
		->and(array_slice($r['cfs'], 0, 2))->toBe([3, 3])
		->and(array_slice($r['colors'], 0, 2))->toBe($case === 'empty' ? [1, 1] : [10, 20]);
})->with(['filled', 'empty']);

test('membership edit waits for rebuild and preserves every member', function () {
	$r = aggregate_probe('concurrent');
	expect($r['waited'])->toBeTrue()->and($r['rebuilt'])->toBeTrue()->and($r['child']['returned'])->toBeTrue()
		->and($r['members'])->toBe([102, 101, 200])->and($r['tasks'])->toHaveCount(3);
});

test('item overrides apply while totals-only member suppression remains intact', function ($total) {
	$r = aggregate_probe('overrides', $total);
	expect($r['returned'])->toBeTrue()->and(array_slice($r['cdefs'], 0, 2))->toBe($total === 3 ? [1, 1] : [2, 2]);
	if ($total === 1) {
		expect($r['types'])->toBe([7, 7]);
	}
})->with([1, 2, 3]);

test('graph action creation owns and rolls back its entire transaction', function ($stage) {
	$r = aggregate_probe('graph-action', $stage);
	expect($r['transaction_open'])->toBeFalse()->and($r['unchanged'])->toBe($stage !== 'success')
		->and($r['graphs'])->toBe($stage === 'success' ? 2 : 1);
})->with(['success', 'metadata', 'settings', 'items']);

test('failed derived graph operations preserve all stored rows', function ($stage) {
	$r = aggregate_probe('query-failure', $stage);
	expect($r['faults'])->toBeGreaterThan(0);
	expect($r['returned'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['transaction_open'])->toBeFalse();
})->with(['rebuild-options', 'graph-metadata', 'member-metadata', 'palette', 'consolidation', 'cdef-catalogue', 'cdef-items', 'make-zero', 'make-zero-item', 'blank-line', 'reorder', 'convert-type', 'line-width', 'line-prefix', 'line-total', 'line-missing', 'percentile']);

test('batch propagation shares CDEF resolution and rolls back every child on failure', function ($case) {
	$r = aggregate_probe('batch', $case);
	expect($r['returned'])->toBe($case === 'success')->and($r['unchanged'])->toBe($case === 'failure')
		->and($r['catalogue_reads'])->toBe(1)->and($r['definitions'])->toBe(2)
		->and($r['first'])->toBe(4)->and($r['second'])->toBe(4);
	if ($case === 'failure') {
		expect($r['retry'])->toBeTrue();
	}
})->with(['success', 'failure']);

test('invalid aggregate inputs leave existing graphs untouched', function ($case) {
	$r = aggregate_probe('invalid-request', $case);
	expect($r['returned'])->toBeFalse()->and($r['unchanged'])->toBeTrue()->and($r['transaction_open'])->toBeFalse();
})->with(['member', 'template', 'graph', 'empty-new', 'total-type', 'unowned']);

test('multiple data sources retain independent similar totals and combined all totals', function ($total, $type, $count, $sources) {
	$r = aggregate_probe('multiple-sources', $total, $type);
	expect($r['returned'])->toBeTrue()->and($r['count'])->toBe($count)
		->and($r['sources'])->toBe([102, 302, 101, 301])->and($r['total_sources'])->toBe($sources);
})->with([[1, 1, 8, []], [1, 2, 8, []], [2, 1, 13, [1, 2]], [2, 2, 11, [1]], [3, 1, 8, [1, 2]], [3, 2, 6, [1]]]);
