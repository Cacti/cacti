<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Mutation protection for the four lib/utility.php poller-cache
 * integrity fixes. Each test pins one behaviour that a single-character
 * mutation would flip back to the broken shape.
 */

$utilitySource = file_get_contents(__DIR__ . '/../../lib/utility.php');

if (!function_exists('_mut_pcache_extract')) {
	/* Brace-balanced function-body extraction. The non-greedy ^\} regex
	 * trick used elsewhere fails on this file because push_out_host is
	 * long enough to include nested closing braces at column 1 inside
	 * heredoc-shaped strings; walk the source manually instead. */
	function _mut_pcache_extract(string $source, string $name): string {
		if (!preg_match('/^function\s+' . preg_quote($name, '/') . '\b[^{]*\{/sm', $source, $m, PREG_OFFSET_CAPTURE)) {
			return '';
		}
		$start = $m[0][1];
		$brace = strpos($source, '{', $start);
		$depth = 1;
		$i     = $brace + 1;
		$len   = strlen($source);
		while ($depth > 0 && $i < $len) {
			$ch = $source[$i];
			if ($ch === '{')      { $depth++; }
			elseif ($ch === '}')  { $depth--; }
			$i++;
		}
		return substr($source, $start, $i - $start);
	}

	/* Strip /* ... *‍/ block comments and // line comments so substring
	 * checks don't trip over English prose that quotes the same code
	 * patterns we're guarding against. */
	function _mut_pcache_strip_comments(string $body): string {
		$body = preg_replace('!/\*.*?\*/!s', '', $body);
		$body = preg_replace('!//[^\n]*!', '', $body);
		return $body;
	}
}

test('update_poller_cache commits even when $poller_items is empty (Mutation Protection)', function () use ($utilitySource) {
	/* If a mutation re-adds the `&& cacti_sizeof($poller_items)` guard
	 * before the buffer flush, a data source that lost its items will
	 * never have its stale poller_item rows DELETEd. Compare against
	 * the comment-stripped body so the docblock that mentions the
	 * pre-fix shape doesn't trip the substring check. */
	$body = _mut_pcache_strip_comments(_mut_pcache_extract($utilitySource, 'update_poller_cache'));
	expect($body)->toContain('if ($commit) {');
	expect(strpos($body, 'if ($commit && cacti_sizeof($poller_items))'))->toBeFalse(
		'the cacti_sizeof($poller_items) gate must not guard the commit-time flush'
	);
});

test('update_poller_cache whitelist failure falls through to commit, not early return (Mutation Protection)', function () use ($utilitySource) {
	/* The pre-fix shape was `if (!data_input_whitelist_check(...)) return $poller_items;`
	 * which bypassed the commit-time DELETE pass. After the fix, the
	 * whitelist check gates only the item-generation block; the outer
	 * if/else and the final `if ($commit)` flush still run. If a
	 * mutation reintroduces the early `return $poller_items;`
	 * adjacent to the whitelist check, stale rows will leak again. */
	$body = _mut_pcache_strip_comments(_mut_pcache_extract($utilitySource, 'update_poller_cache'));

	$whitelistPos = strpos($body, 'data_input_whitelist_check($data_input[\'id\'])');
	expect($whitelistPos)->not->toBeFalse('whitelist check must remain present');

	/* Slice the 200 chars following the whitelist call. No
	 * `return $poller_items;` may appear in that window. */
	$adjacent = substr($body, $whitelistPos, 200);
	expect(strpos($adjacent, 'return $poller_items;'))->toBeFalse(
		'whitelist failure must not early-return; the commit-time flush has to run'
	);
});

test('push_out_data_input_method appends current data source after every flush (Mutation Protection)', function () use ($utilitySource) {
	/* The pre-fix shape had the append in an `else` branch attached to
	 * the flush. The fixed shape always appends. A mutation that
	 * reintroduces the `else` makes the boundary data source disappear. */
	$body  = _mut_pcache_extract($utilitySource, 'push_out_data_input_method');
	expect($body)->not->toBe('');

	/* Look for the iteration body. The append must be unconditional
	 * (i.e., outside any `else` of the boundary if). */
	$iterationStart = strpos($body, 'foreach ($data_sources as $data_source)');
	$iterationSlice = substr($body, $iterationStart, 1500);

	/* The pre-fix shape was:
	 *   if ($prev_poller > 0 && ...) { flush; reset; } else { append }
	 * The fixed shape is:
	 *   if ($prev_poller > 0 && ...) { flush; reset; }
	 *   $_my_local_data_ids[] = ...;
	 *   $poller_items = array_merge(...);
	 * Confirm: no `} else {` immediately followed by the append. */
	expect($iterationSlice)
		->toContain('$_my_local_data_ids[] = $data_source[\'id\'];')
		->toContain('$poller_items = array_merge($poller_items, update_poller_cache($data_source));');

	/* The else-branch with the append must be gone. */
	expect(preg_match('/}\s*else\s*\{\s*\$_my_local_data_ids\[\]/', $iterationSlice))->toBe(0);
});

test('push_out_host derives poller_id from data_local when host_id is 0 (Mutation Protection)', function () use ($utilitySource) {
	/* The pre-fix shape was a single SELECT poller_id WHERE id = $host_id.
	 * The fixed shape branches: if $host_id > 0 use the host lookup; else
	 * if we have $local_data_ids fall back to data_local. A mutation that
	 * drops the elseif branch reintroduces the host_id=0 → poller_id=0
	 * trap. */
	$body = _mut_pcache_extract($utilitySource, 'push_out_host');
	expect($body)->not->toBe('');

	/* The fix: an `elseif (cacti_sizeof($local_data_ids))` clause that
	 * looks up each local_data_id's poller via LEFT JOIN against host.
	 * host_id=0 rows have no host record, so COALESCE keeps them on
	 * the main poller instead of dropping them from the buffer. */
	expect($body)->toContain('elseif (cacti_sizeof($local_data_ids))');
	expect($body)->toContain('LEFT JOIN host AS h');
	expect($body)->toContain('COALESCE(h.poller_id, 1) AS poller_id');
	expect($body)->toContain('without a device have host_id=0 and belong to the main poller');
});

test('no-device data sources default to main poller in all rebuild paths (Mutation Protection)', function () use ($utilitySource) {
	/* Full repopulate, direct update_poller_cache(..., true), data input
	 * method pushes, and push_out_host(host_id=0) all have to preserve
	 * local_data rows with host_id=0. Those rows cannot INNER JOIN host,
	 * so every data_local->host poller lookup must be nullable and
	 * default to poller 1. */
	expect(substr_count($utilitySource, 'COALESCE(h.poller_id, 1) AS poller_id'))->toBeGreaterThanOrEqual(4);
	expect(substr_count($utilitySource, 'LEFT JOIN host AS h'))->toBeGreaterThanOrEqual(4);
	expect(strpos($utilitySource, 'INNER JOIN host AS h
		ON h.id = dl.host_id'))->toBeFalse();
});

test('push_out_host groups host_id=0 batches by poller before flush (Mutation Protection)', function () use ($utilitySource) {
	/* When push_out_host is called with $host_id = 0 and $local_data_ids
	 * span multiple pollers (e.g. a $data_template_id push that touches
	 * data sources on different remote pollers), the buffer must flush
	 * per-poller. A mutation that reintroduces a single
	 * poller_update_poller_cache_from_buffer call would write/delete
	 * later pollers' rows under the first poller's id. */
	$body = _mut_pcache_extract($utilitySource, 'push_out_host');

	/* The grouping shape: an $ids_by_poller map keyed by poller_id. */
	expect($body)->toContain('$ids_by_poller');
	expect($body)->toContain('$items_by_poller');

	/* The flush must iterate per poller, not call the buffer flush
	 * once with all local_data_ids. */
	expect($body)->toContain('foreach ($ids_by_poller as $pid => $ldid_set)');
	expect($body)->toContain('poller_update_poller_cache_from_buffer($pid_local_data_ids, $pid_items, $pid);');
});

test('push_out_host parses leading local_data_id from poller_items (Mutation Protection)', function () use ($utilitySource) {
	/* poller_items entries are SQL VALUES tuples emitted by
	 * api_poller_cache_item_add() that begin with `($local_data_id, ...)`.
	 * The grouping logic relies on that shape; if a future refactor of
	 * api_poller_cache_item_add changes the leading column, this test
	 * fires and forces a coupled update. */
	$body = _mut_pcache_extract($utilitySource, 'push_out_host');
	/* Loose match: the regex shape may evolve, but the leading-digit
	 * extraction must remain. */
	expect(preg_match('/preg_match\([\'"]\/\^\\\\\(\(\\\\d\+\),/', $body))->toBe(1);
});

test('push_out_host PCACHE change log uses $old_data not $old_value (Mutation Protection)', function () use ($utilitySource) {
	/* Single-character mutation: $old_data ↔ $old_value. The pre-fix
	 * shape had isset($old_value['value']), an undefined variable, so
	 * the change log never fired. Pin the post-fix shape. */
	$body = _mut_pcache_extract($utilitySource, 'push_out_host');
	expect($body)->toContain("isset(\$old_data['value']) && \$old_data['value'] != \$host[\$field]");
	expect(strpos($body, "isset(\$old_value["))->toBeFalse();
});

test('lib/utility.php has no dangling $old_value reference (Mutation Protection)', function () use ($utilitySource) {
	/* Whole-file scan: any `$old_value[` reference outside a comment
	 * would be the pre-fix typo or a future regression. */
	$matches = [];
	preg_match_all('/\$old_value\[/', $utilitySource, $matches);
	expect($matches[0])->toBeEmpty();
});
