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
 * Regression test for VDEF emission in rrdtool_function_graph() (lib/rrd.php).
 *
 * The #6540 refactor moved the $vdef_cache reset into the per-item loop and
 * dropped the '!' from the isset() guard. The condition was then always
 * false: no VDEF: line was ever emitted, the cache entry was never written,
 * and the later 'vdef' . generate_graph_def_name($vdef_cache[$key]) passed
 * null to generate_graph_def_name(int), fataling any graph item with a VDEF.
 *
 * The fix restores !isset(), keeps the cache at function scope, guards the
 * dereference, and excludes VDEF-backed items from XPORT (rrdtool xport only
 * accepts DEF/CDEF references).
 *
 * @group regression
 */

function rrd_source(): string {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	return $src;
}

function vdef_block(string $src): string {
	$start = strpos($src, 'GRAPH ITEMS: VDEFs START');
	$end   = strpos($src, 'GRAPH ITEMS: VDEFs END');
	expect($start)->not->toBeFalse()
		->and($end)->toBeGreaterThan($start);

	return substr($src, $start, $end - $start);
}

test('the VDEF block builds only uncached VDEFs (!isset)', function () {
	$block = vdef_block(rrd_source());

	expect($block)->toContain('!isset($vdef_cache[$vdef_cache_key])')
		->and($block)->toContain('$vdef_cache[$vdef_cache_key] = $i');
});

test('the VDEF cache is not reset inside the item loop', function () {
	$src = rrd_source();

	// no reset between the loop-local markers (the per-item VDEF block)
	expect(preg_match('/\$vdef_cache\s*=\s*\[\]/', vdef_block($src)))->toBe(0);

	// exactly one initialization, and it precedes the item loop's CDEF/VDEF blocks
	expect(preg_match_all('/\$vdef_cache\s*=\s*\[\]/', $src, $m, PREG_OFFSET_CAPTURE))->toBe(1)
		->and($m[0][0][1])->toBeLessThan(strpos($src, 'GRAPH ITEMS: CDEF START'));
});

test('the vdef data source name is only used when cached', function () {
	$src = rrd_source();

	expect(preg_match(
		'/if \(\$graph_item\[\'vdef_id\'\] > 0 && isset\(\$vdef_cache\[\$vdef_cache_key\]\)\) \{\s*' .
		'\$data_source_name = \'vdef\' \. generate_graph_def_name\(\$vdef_cache\[\$vdef_cache_key\]\);/',
		$src
	))->toBe(1);
});

test('XPORT emission excludes VDEF-backed items', function () {
	$src = rrd_source();

	// the AREA/STACK/LINE selector for xport must gate on vdef_id == 0 so a
	// drawn VDEF item does not emit XPORT:vdefNN and fail the whole export
	expect(preg_match('/^.*preg_match\(\'\/\^\(AREA\|AREA:STACK\|LINE\[123\]\|STACK\)\$\/\'.*$/m', $src, $m))->toBe(1)
		->and($m[0])->toContain("\$graph_item['vdef_id'] == 0 &&");
});
