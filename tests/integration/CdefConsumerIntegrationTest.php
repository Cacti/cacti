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

test('invalid graph CDEFs use visible output-specific failure responses', function () : void {
	$image = fn (string $message) => 'IMAGE:' . $message;

	expect(rrdtool_invalid_cdef_response(['export_csv' => true], 7, 42, $image))->toBeFalse()
		->and(rrdtool_invalid_cdef_response(['get_error' => true], 7, 42, $image))->toBe('ERROR: Invalid CDEF 7 for graph 42.')
		->and(rrdtool_invalid_cdef_response(['print_source' => true], 7, 42, $image))->toBe('ERROR: Invalid CDEF 7 for graph 42.')
		->and(rrdtool_invalid_cdef_response([], 7, 42, $image))->toBe('IMAGE:ERROR: Invalid CDEF 7 for graph 42.');
});

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

	$items = [
		['id' => 10, 'cdef_id' => 3],
		['id' => 11, 'cdef_id' => 1],
	];

	expect(aggregate_prepare_cdef_totalling($items, $cdefs))->toBe([
		'items'           => [],
		'invalid_cdef_id' => 1,
	]);
});
