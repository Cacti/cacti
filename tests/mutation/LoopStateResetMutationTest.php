<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Mutation protection for the loop-scoped $oid / $script_path reset
 * fixes. Each foreach($outputs as $output) site must unset() the
 * variable at iteration top so an output without a mapping does not
 * inherit the previous iteration's value. A mutation that drops the
 * unset (or moves it after the conditional that assigns) is detectable
 * here.
 */

$utilitySource   = file_get_contents(__DIR__ . '/../../lib/utility.php');
$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

if (!function_exists('_mut_loop_extract_outputs_loops')) {
	function _mut_loop_extract_outputs_loops(string $source): array {
		$bodies = [];
		$offset = 0;
		while (($pos = strpos($source, 'foreach ($outputs as $output) {', $offset)) !== false) {
			$brace = strpos($source, '{', $pos);
			$depth = 1;
			$i     = $brace + 1;
			$len   = strlen($source);
			while ($depth > 0 && $i < $len) {
				$ch = $source[$i];
				if ($ch === '{')      { $depth++; }
				elseif ($ch === '}')  { $depth--; }
				$i++;
			}
			$bodies[] = substr($source, $brace + 1, $i - $brace - 2);
			$offset   = $i;
		}
		return $bodies;
	}
}

test('every $oid foreach($outputs) loop calls unset($oid) at iteration top (Mutation Protection)', function () use ($utilitySource, $functionsSource) {
	$loops = array_merge(
		_mut_loop_extract_outputs_loops($utilitySource),
		_mut_loop_extract_outputs_loops($functionsSource)
	);
	$oidLoops = array_values(array_filter($loops, fn($b) => strpos($b, '$oid') !== false));
	expect(count($oidLoops))->toBeGreaterThanOrEqual(2);

	foreach ($oidLoops as $body) {
		expect($body)->toContain('unset($oid)');

		/* The unset must precede the conditional that reads
		 * $output['snmp_field_name']['oid']. If a mutation moves the
		 * unset BELOW the conditional, the reset runs after the
		 * assignment and clobbers the freshly-read oid. */
		$unsetPos = strpos($body, 'unset($oid)');
		$readPos  = strpos($body, "['oid']");
		expect($unsetPos < $readPos)->toBeTrue('unset($oid) must precede the [oid] read');
	}
});

test('every $script_path foreach($outputs) loop calls unset($script_path) at iteration top (Mutation Protection)', function () use ($utilitySource, $functionsSource) {
	$loops = array_merge(
		_mut_loop_extract_outputs_loops($utilitySource),
		_mut_loop_extract_outputs_loops($functionsSource)
	);
	$spLoops = array_values(array_filter($loops, fn($b) => strpos($b, '$script_path') !== false));
	expect(count($spLoops))->toBeGreaterThanOrEqual(2);

	foreach ($spLoops as $body) {
		expect($body)->toContain('unset($script_path)');
		$unsetPos = strpos($body, 'unset($script_path)');
		$readPos  = strpos($body, "['query_name']");
		expect($unsetPos < $readPos)->toBeTrue('unset($script_path) must precede the [query_name] read');
	}
});

test('the lib/utility.php $script_path loop also resets $action (Mutation Protection)', function () use ($utilitySource) {
	/* update_poller_cache() emits a poller_item with both $script_path
	 * and $action; if a mutation removes only the $action reset, an
	 * output that maps to a different action ends up tagged with the
	 * previous iteration's action constant. */
	$loops    = _mut_loop_extract_outputs_loops($utilitySource);
	$spLoops  = array_values(array_filter($loops, fn($b) => strpos($b, '$script_path') !== false));
	expect(count($spLoops))->toBeGreaterThanOrEqual(1);
	foreach ($spLoops as $body) {
		expect($body)->toContain('unset($action)');
	}
});

test('behavioural fixture: dropping the unset reproduces the leak (Mutation Protection)', function () {
	/* Inline fixture that demonstrates what the mutation would look like
	 * and what behaviour it would re-introduce. If anyone wonders whether
	 * the unset() really matters, this test answers behaviourally. */
	$outputs = [
		['name' => 'a', 'oid' => '.1.3.6.1'],
		['name' => 'b', 'oid' => null],
	];

	/* Mutation: drop the unset(). Second iteration leaks first $oid. */
	$leaky = [];
	$oid   = null;
	foreach ($outputs as $output) {
		if (!empty($output['oid'])) {
			$oid = $output['oid'];
		}
		if (!empty($oid)) {
			$leaky[] = "{$output['name']}={$oid}";
		}
	}
	expect($leaky)->toBe(['a=.1.3.6.1', 'b=.1.3.6.1']);

	/* Fixed shape: unset() at iteration top. */
	$clean = [];
	$oid   = null;
	foreach ($outputs as $output) {
		unset($oid);
		if (!empty($output['oid'])) {
			$oid = $output['oid'];
		}
		if (!empty($oid)) {
			$clean[] = "{$output['name']}={$oid}";
		}
	}
	expect($clean)->toBe(['a=.1.3.6.1']);
});
