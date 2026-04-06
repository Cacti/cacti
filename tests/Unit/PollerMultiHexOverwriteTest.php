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
 * Tests for the MULTI output parsing fix in lib/poller.php.
 *
 * PR #6981: after the if/elseif/else guard that coerces MULTI output
 * values (is_numeric -> keep, is_hexadecimal -> hexdec, else -> 'U'),
 * there was an unconditional line:
 *
 *   $rrd_update_array[...]['times'][...][...] = $matches[1];
 *
 * This overwrote the coerced value with the raw $matches[1], defeating
 * the hex conversion and the 'U' fallback.
 *
 * The fix removes that unconditional assignment line.
 *
 * These tests verify the fix by scanning the source of lib/poller.php,
 * checking that no unconditional $matches[1] assignment follows the
 * if/elseif/else coercion block in the MULTI output parsing section.
 */

// --- source scanning helper ---

function getPollerMultiBlock(): string {
	$pollerPhp = file_get_contents(__DIR__ . '/../../lib/poller.php');
	expect($pollerPhp)->not->toBeFalse('Failed to read lib/poller.php');

	return $pollerPhp;
}

/**
 * Extract all MULTI output parsing blocks from process_poller_output.
 * These blocks contain the is_numeric/is_hexadecimal/U coercion guard
 * followed by the $rrd_tmpl assignment.
 */
function getMultiCoercionBlocks(): array {
	$source = getPollerMultiBlock();

	// Find process_poller_output function
	$funcStart = strpos($source, 'function process_poller_output');
	expect($funcStart)->not->toBeFalse('process_poller_output() must exist in lib/poller.php');

	$funcBody = substr($source, $funcStart);

	// Match all coercion blocks: the if(is_numeric...)/elseif(is_hexadecimal...)/else pattern
	// followed by lines up to the $rrd_tmpl assignment
	$pattern = '/if\s*\(is_numeric\(\$matches\[1\]\).*?\$rrd_tmpl\b/s';

	preg_match_all($pattern, $funcBody, $matches);

	return $matches[0];
}

// --- the unconditional overwrite line must not exist ---

test('no unconditional $matches[1] assignment after coercion guard', function () {
	$blocks = getMultiCoercionBlocks();

	expect(count($blocks))->toBeGreaterThanOrEqual(1,
		'At least one MULTI coercion block must exist in process_poller_output'
	);

	foreach ($blocks as $i => $block) {
		/*
		 * The bug was an unconditional assignment AFTER the else clause:
		 *   } else {
		 *       ...['times'][...][...] = 'U';
		 *   }
		 *
		 *   $rrd_update_array[...]['times'][...][...] = $matches[1];  // <-- THIS LINE
		 *
		 * Check that between the closing brace of the else and $rrd_tmpl,
		 * there is no assignment of $matches[1] to the rrd_update_array.
		 */
		$elseUPos = strrpos($block, "= 'U'");
		if ($elseUPos === false) {
			continue;
		}

		$afterElse = substr($block, $elseUPos);

		// There should be no $matches[1] assignment after the 'U' fallback
		$pattern = '/\$rrd_update_array\[.*?\]\[\'times\'\]\[.*?\]\[.*?\]\s*=\s*\$matches\[1\]/';

		expect(preg_match($pattern, $afterElse))->toBe(0,
			"Block #{$i}: unconditional \$matches[1] assignment must not exist after coercion guard"
		);
	}
});

// --- the coercion guard structure is intact ---

test('MULTI coercion blocks contain is_numeric, is_hexadecimal, and U fallback', function () {
	$blocks = getMultiCoercionBlocks();

	foreach ($blocks as $i => $block) {
		expect(str_contains($block, 'is_numeric($matches[1])'))->toBeTrue(
			"Block #{$i}: must check is_numeric(\$matches[1])"
		);

		expect(str_contains($block, 'is_hexadecimal($matches[1])'))->toBeTrue(
			"Block #{$i}: must check is_hexadecimal(\$matches[1])"
		);

		expect(str_contains($block, "= 'U'"))->toBeTrue(
			"Block #{$i}: must have 'U' fallback for non-numeric non-hex values"
		);
	}
});

// --- hexdec conversion is present inside the coercion guard ---

test('MULTI coercion blocks use hexdec for hex values', function () {
	$blocks = getMultiCoercionBlocks();

	foreach ($blocks as $i => $block) {
		expect(str_contains($block, 'hexdec($matches[1])'))->toBeTrue(
			"Block #{$i}: hex values must be converted via hexdec()"
		);
	}
});

// --- broader scan: no stray $matches[1] assignments outside coercion guards ---

test('process_poller_output has balanced direct and hexdec $matches[1] assignments', function () {
	$source = getPollerMultiBlock();

	$funcStart = strpos($source, 'function process_poller_output');
	$funcBody = substr($source, $funcStart);

	/*
	 * Count all direct assignments of $matches[1] to rrd_update_array times
	 * (not wrapped in hexdec). In the fixed code, direct $matches[1] assignments
	 * only appear inside the is_numeric || 'U' check.
	 */
	$directAssignPattern = '/\[\'times\'\].*?=\s*\$matches\[1\]\s*;/';
	preg_match_all($directAssignPattern, $funcBody, $directAssigns);

	$hexdecAssignPattern = '/\[\'times\'\].*?=\s*hexdec\(\$matches\[1\]\)\s*;/';
	preg_match_all($hexdecAssignPattern, $funcBody, $hexdecAssigns);

	// Direct assigns should equal number of hexdec assigns (one per coercion block)
	expect(count($directAssigns[0]))->toBe(count($hexdecAssigns[0]),
		'Number of direct $matches[1] assigns must equal number of hexdec($matches[1]) assigns (one per block)'
	);
});
