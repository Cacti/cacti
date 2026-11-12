<?php

// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

/* A CDEF can name a graph's DEFs directly (a,b,-). An aggregate graph numbers its DEFs across
 * every member graph, so each member's names have to be pointed at that member's own DEFs. */

namespace RrdAggregateCdefDefsTest;

/** Run the real lib/rrd.php helpers in their own process, with fake database rows. */
function results() {
	static $results = null;

	if ($results === null) {
		$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/AggregateCdefDefs.php';
		exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg($fixture), $output, $status);
		expect($status)->toBe(0);
		$results = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
	}

	return $results;
}

test('each member lists its own DEFs in the order its graph names them', function () {
	expect(results()['map'])->toBe([
		'owner'   => [201 => 0, 202 => 0, 351 => 1, 352 => 1],
		'members' => [[[201, 1], [202, 1], [201, 3]], [[351, 1], [352, 1], [351, 3]]],
	]);
});

test('a graph that is not an aggregate needs no mapping', function () {
	expect(results()['not_aggregate'])->toBe([]);
});

test('a member item names the DEFs of its own member graph', function () {
	expect(results()['first_member'])->toBe('b,a,-')
		->and(results()['second'])->toBe('e,d,-')
		->and(results()['special'])->toBe('CURRENT_DATA_SOURCE,f,/');
});

test('a totalling item sums the named DEF across every member', function () {
	$now = results()['total_now'];

	expect(results()['total'])->toBeIn([
		"SIMILAR_DATA_SOURCES_NODUPS,TIME,$now,GT,a,a,UN,0,a,IF,IF,TIME,$now,GT,d,d,UN,0,d,IF,IF,+,-",
		'SIMILAR_DATA_SOURCES_NODUPS,TIME,' . ($now + 1) . ',GT,a,a,UN,0,a,IF,IF,TIME,' . ($now + 1) . ',GT,d,d,UN,0,d,IF,IF,+,-',
	])->and(results()['single_total'])->toBe('a,8,*');
});

test('names that are not DEFs of the member are left alone', function () {
	expect(results()['untouched'])->toBe('cdefe,0,EQ,j,ab,UN,IF')
		->and(results()['unresolved'])->toBe('a,8,*');
});

test('graph items keep the consolidation function their DEFs are built with', function () {
	// a GPRINT follows the CF of the graph item before it, once there is one
	expect(results()['cfs'])->toBe([4, 3, 3, 1, 4, 3, 2, 3]);
});

test('a member DEF that falls back to another CF is not listed twice', function () {
	expect(results()['average_only'])->toBe([[351, 1], [352, 1]]);
});
