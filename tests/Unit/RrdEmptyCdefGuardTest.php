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
 * Tests for the empty CDEF guard in rrd.php CDEF emission.
 *
 * When aggregate graphs include GPRINT items whose consolidation
 * function does not match any AREA/STACK/LINE item's DEF, the
 * magic-variable expansion produces an empty RPN string. Without a
 * guard, rrd.php emits "CDEF:cdefX=" which rrdtool rejects with
 * "ERROR: can not parse an empty rpn expression".
 *
 * The fix adds: if ($cdef_string === '') continue;
 *
 * These tests verify the guard logic using a stub that mirrors the
 * CDEF emission path without requiring rrdtool or database access.
 */

require_once __DIR__ . '/../../include/global_constants.php';

/**
 * Mirrors the CDEF emission logic in rrd.php.
 * Returns the CDEF line string, or null if the cdef_string is empty
 * (matching the continue guard).
 */
function emit_cdef_line(string $cdef_string, int $item_index): ?string {
	// guard: skip empty RPN expressions
	if ($cdef_string === '') {
		return null;
	}

	$def_name = chr(ord('a') + $item_index);

	return 'CDEF:cdef' . $def_name . '=' . $cdef_string;
}

/**
 * Simulates the magic-variable expansion for SIMILAR_DATA_SOURCES_NODUPS.
 * When no matching AREA/STACK/LINE items exist for a given CF, the
 * expansion produces an empty string.
 */
function expand_similar_nodups(array $graph_items, string $data_source_name, int $cf_id): string {
	$parts     = [];
	$rra_epoch = time() - 86400;

	foreach ($graph_items as $item) {
		/* only AREA/STACK/LINE items are eligible, matching the preg_match
		   filter at rrd.php:2120 */
		if (!preg_match('/(AREA|STACK|LINE[123])/', $item['type_name'])) {
			continue;
		}

		if ($item['data_source_name'] !== $data_source_name) {
			continue;
		}

		if ($item['cf_id'] !== $cf_id) {
			continue;
		}

		$def     = $item['def_name'];
		$parts[] = "TIME,{$rra_epoch},GT,{$def},{$def},UN,0,{$def},IF,IF";
	}

	if (count($parts) === 0) {
		return '';
	}

	$result = implode(',', $parts);

	if (count($parts) > 1) {
		$result .= str_repeat(',+', count($parts) - 1);
	}

	return $result;
}

// --- Empty CDEF guard ---

test('empty cdef string returns null (skipped)', function () {
	expect(emit_cdef_line('', 0))->toBeNull();
});

test('non-empty cdef string emits valid CDEF line', function () {
	$result = emit_cdef_line('a,8,*', 0);

	expect($result)->toBe('CDEF:cdefa=a,8,*');
});

test('whitespace-only cdef string is NOT treated as empty', function () {
	// the guard uses strict === '' comparison, so whitespace is not empty
	$result = emit_cdef_line(' ', 0);

	expect($result)->not->toBeNull();
});

// --- Magic-variable expansion produces empty string for unmatched CF ---

test('expansion returns empty when no AREA/LINE items match the CF', function () {
	// scenario: GPRINT with CF=MAX (3), but only AREA items with CF=AVERAGE (1)
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 3);

	expect($result)->toBe('');
});

test('expansion returns RPN when AREA items match the CF', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->not->toBe('')
		->and($result)->toContain('b,b,UN,0,b,IF,IF')
		->and($result)->toContain('f,f,UN,0,f,IF,IF')
		->and($result)->toContain(',+');
});

test('expansion skips GPRINT items even if CF matches', function () {
	$items = [
		['type_name' => 'GPRINT', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->toBe('');
});

test('expansion skips COMMENT items', function () {
	$items = [
		['type_name' => 'COMMENT', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->toBe('');
});

// --- Integration: empty expansion feeds into CDEF emission guard ---

test('unmatched CF expansion produces empty string that the guard skips', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_out', 'cf_id' => 1, 'def_name' => 'd'],
	];

	// GPRINT with CF=MAX tries to expand SIMILAR for traffic_in with cf=3
	$cdef_string = expand_similar_nodups($items, 'traffic_in', 3);
	$cdef_line   = emit_cdef_line($cdef_string, 7);

	expect($cdef_string)->toBe('')
		->and($cdef_line)->toBeNull();
});

test('matched CF expansion produces valid CDEF that emits correctly', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$cdef_string = expand_similar_nodups($items, 'traffic_in', 1);
	$cdef_line   = emit_cdef_line($cdef_string, 8);

	expect($cdef_string)->not->toBe('')
		->and($cdef_line)->toStartWith('CDEF:cdefi=')
		->and($cdef_line)->toContain(',+');
});

// --- Single data source: no trailing plus operator ---

test('single matching item produces RPN without trailing plus', function () {
	$items = [
		['type_name' => 'LINE1', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->not->toBe('')
		->and($result)->not->toContain(',+');
});
