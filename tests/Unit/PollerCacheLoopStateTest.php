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
 * Source-level guards for the foreach($outputs as $output) loops in
 * the poller cache builders and the data source validator.
 *
 * Four sites had the same defect class: $oid (SNMP path) or
 * $script_path / $action (script-query path) were assigned inside an
 * `if (isset($outputs[...]['oid']))` guard, but the post-guard
 * `if (!empty($oid))` / `if (isset($script_path))` then read the
 * variable from the previous iteration when the current output had no
 * mapping. Result: a poller_item built from a different output's OID,
 * or a test-data validator that fired SNMP get against the wrong OID.
 *
 * Each site must reset the loop-scoped variable at the top of the
 * iteration. The test below extracts the four foreach bodies from the
 * source and asserts that pattern.
 */

$utilitySource   = file_get_contents(__DIR__ . '/../../lib/utility.php');
$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

/**
 * Slice every foreach($outputs as $output) body out of the source and
 * return the bodies as an array of strings. Brace-balanced extraction so
 * nested control structures inside the body don't truncate the slice.
 */
function _extract_outputs_loops(string $source): array {
	$bodies = [];
	$offset = 0;
	while (($pos = strpos($source, 'foreach ($outputs as $output) {', $offset)) !== false) {
		$bracePos = strpos($source, '{', $pos);
		$depth    = 1;
		$i        = $bracePos + 1;
		$len      = strlen($source);
		while ($depth > 0 && $i < $len) {
			$ch = $source[$i];
			if ($ch === '{') {
				$depth++;
			} elseif ($ch === '}') {
				$depth--;
			}
			$i++;
		}
		$bodies[] = substr($source, $bracePos + 1, $i - $bracePos - 2);
		$offset   = $i;
	}
	return $bodies;
}

test('lib/utility.php update_poller_cache loops reset $oid and $script_path between iterations', function () use ($utilitySource) {
	$loops = _extract_outputs_loops($utilitySource);

	/* update_poller_cache() carries two foreach($outputs) loops: the
	 * SNMP_QUERY branch (uses $oid) and the SCRIPT_QUERY branch (uses
	 * $script_path / $action). Both must reset the loop-scoped state. */
	$oidLoops    = array_values(array_filter($loops, function ($body) { return strpos($body, '$oid') !== false; }));
	$scriptLoops = array_values(array_filter($loops, function ($body) { return strpos($body, '$script_path') !== false; }));

	expect(count($oidLoops))->toBeGreaterThanOrEqual(1);
	expect(count($scriptLoops))->toBeGreaterThanOrEqual(1);

	foreach ($oidLoops as $body) {
		expect($body)->toContain('unset($oid)');
	}
	foreach ($scriptLoops as $body) {
		expect($body)->toContain('unset($script_path)');
	}
});

test('lib/functions.php test_data_source loops reset $oid and $script_path between iterations', function () use ($functionsSource) {
	$loops = _extract_outputs_loops($functionsSource);

	/* test_data_source() has the same two-branch shape and the same
	 * defect: an output without a mapping inherits the previous
	 * iteration's $oid / $script_path and validates against the wrong
	 * OID or script. */
	$oidLoops    = array_values(array_filter($loops, function ($body) { return strpos($body, '$oid') !== false; }));
	$scriptLoops = array_values(array_filter($loops, function ($body) { return strpos($body, '$script_path') !== false; }));

	expect(count($oidLoops))->toBeGreaterThanOrEqual(1);
	expect(count($scriptLoops))->toBeGreaterThanOrEqual(1);

	foreach ($oidLoops as $body) {
		expect($body)->toContain('unset($oid)');
	}
	foreach ($scriptLoops as $body) {
		expect($body)->toContain('unset($script_path)');
	}
});

test('reset happens BEFORE the conditional that assigns the variable', function () use ($utilitySource, $functionsSource) {
	/* The reset must be positioned at the top of the loop, before the
	 * `if (isset($outputs[...]['oid']))` / `if (isset(...query_name...))`
	 * that conditionally assigns. If the unset() landed after the
	 * conditional, it would clobber a freshly-assigned value. */
	foreach ([$utilitySource, $functionsSource] as $source) {
		foreach (_extract_outputs_loops($source) as $body) {
			if (strpos($body, '$oid') !== false) {
				$unsetPos = strpos($body, 'unset($oid)');
				$assignPos = strpos($body, "['oid']");
				expect($unsetPos !== false && $unsetPos < $assignPos)
					->toBeTrue('unset($oid) must precede the [oid] read in every $outputs loop');
			}
			if (strpos($body, '$script_path') !== false) {
				$unsetPos  = strpos($body, 'unset($script_path)');
				$assignPos = strpos($body, "['query_name']");
				expect($unsetPos !== false && $unsetPos < $assignPos)
					->toBeTrue('unset($script_path) must precede the [query_name] read in every $outputs loop');
			}
		}
	}
});

test('behavioural fixture: foreach without reset would leak; with reset it does not', function () {
	/* Demonstrate the defect class with a small inline fixture so the
	 * regression is documented at runtime, not just by source pattern.
	 * The buggy shape leaks $oid; the fixed shape unsets at the top. */
	$outputs = [
		['name' => 'first',  'has_oid' => true,  'oid' => '.1.3.6.1.2.1.1'],
		['name' => 'second', 'has_oid' => false, 'oid' => null],
	];

	$buggy = [];
	$oid   = null;
	foreach ($outputs as $output) {
		if ($output['has_oid']) {
			$oid = $output['oid'];
		}
		if (!empty($oid)) {
			$buggy[] = [$output['name'], $oid];
		}
	}

	$fixed = [];
	$oid   = null;
	foreach ($outputs as $output) {
		unset($oid);
		if ($output['has_oid']) {
			$oid = $output['oid'];
		}
		if (!empty($oid)) {
			$fixed[] = [$output['name'], $oid];
		}
	}

	/* The bug: the second output (no oid) inherits the first's $oid and
	 * gets a row built against the wrong OID. */
	expect($buggy)->toHaveCount(2);
	expect($buggy[1])->toBe(['second', '.1.3.6.1.2.1.1']);

	/* The fix: only outputs with their own OID produce a row. */
	expect($fixed)->toHaveCount(1);
	expect($fixed[0])->toBe(['first', '.1.3.6.1.2.1.1']);
});
