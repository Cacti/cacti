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
	 * runs a JOIN against data_local. Pin both halves. */
	expect($body)->toContain('elseif (cacti_sizeof($local_data_ids))');
	expect($body)->toContain('INNER JOIN data_local AS dl');
	expect($body)->toContain('array($local_data_ids[0])');
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
