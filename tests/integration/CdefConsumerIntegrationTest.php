<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/rrd.php';
require_once dirname(__DIR__, 2) . '/lib/aggregate.php';

test('graph CDEF caching keeps resolver failures out of text substitution', function () : void {
	$state = rrdtool_normalize_graph_item_cdef(null);

	expect($state['cdef_invalid'])->toBeTrue()
		->and($state['cdef_cache'])->toBe('')
		->and(rrdtool_resolve_graph_text($state['cdef_cache'], [], [], fn (string $value) => $value))->toBe('');
});

test('graph CDEF caching preserves valid empty and non-empty definitions', function (?string $cdef) : void {
	$state = rrdtool_normalize_graph_item_cdef($cdef);

	expect($state['cdef_invalid'])->toBeFalse()
		->and($state['cdef_cache'])->toBe($cdef);
})->with(['empty definition' => '', 'resolved definition' => 'CURRENT_DATA_SOURCE,8,*']);

test('aggregate totalling rejects absent invalid and empty definitions', function () : void {
	$cdefs = [
		1 => ['id' => 1, 'name' => 'Invalid', 'cdef_text' => null],
		2 => ['id' => 2, 'name' => 'Empty', 'cdef_text' => ''],
		3 => ['id' => 3, 'name' => 'Valid', 'cdef_text' => 'CURRENT_DATA_SOURCE,8,*'],
	];

	expect(aggregate_cdef_for_totalling($cdefs, 999))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 1))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 2))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 3))->toBe('CURRENT_DATA_SOURCE,8,*');
});
