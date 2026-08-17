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
 * Tests for the high/low word assembly in ss_nimble_alletra_volumes()
 * (scripts/ss_nimble_alletra_volumes.php).
 *
 * The walk-loop line was `$long = isset($high[$key]) ? $high[$key] : '' . $value;`.
 * Concatenation binds tighter than the ternary, so this parsed as
 * `isset($high[$key]) ? $high[$key] : ('' . $value)` -- whenever a high word
 * existed, the low word was silently dropped and $long was just the high
 * 32 bits. The fix parenthesizes the true branch:
 * `isset($high[$key]) ? ($high[$key] . $value) : $value`.
 *
 * The assembly line is pulled directly out of the real source file and
 * wrapped in a throwaway function so the test exercises the shipped
 * expression rather than a re-typed copy of it.
 */

$source = file_get_contents(__DIR__ . '/../../../scripts/ss_nimble_alletra_volumes.php');

preg_match('/\$long = isset\(\$high\[\$key\]\)[^;]*;/', $source, $matches);

test('the high/low word assembly line is present in the source', function () use ($matches) {
	expect($matches)->not->toBeEmpty();
});

$assemble = function (string $expr) {
	// eval() here only wraps a line regex-extracted from this repo's own
	// scripts/ss_nimble_alletra_volumes.php (not external/user input) into a
	// throwaway named function, matching the existing extract-and-eval
	// pattern used in Issue7070PercentileContractTest.php. Test-only.
	// Each call gets a unique function name since PHP cannot redeclare
	// a named function within the same process.
	$name = 'nimble_assemble_long_' . str_replace('.', '_', uniqid('', true));
	eval("function $name(\$high, \$key, \$value) { $expr return \$long; }");

	return $name;
};

test('64-bit value assembles the high word followed by the low word', function () use ($matches, $assemble) {
	$fn = $assemble($matches[0]);

	$high = ['1' => str_pad(decbin(1), 32, '0', STR_PAD_LEFT)];
	$low  = str_pad(decbin(500), 32, '0', STR_PAD_LEFT);

	$long = $fn($high, '1', $low);

	// Correct: (1 << 32) + 500
	expect(bindec($long))->toEqual(4294967796);

	// The pre-fix expression dropped the low word whenever a high word
	// existed, collapsing the result down to just the high bits (1).
	expect(bindec($long))->not->toEqual(1);
});

test('falls back to the low word alone when no high word exists for the key', function () use ($matches, $assemble) {
	$fn = $assemble($matches[0]);

	$low = str_pad(decbin(500), 32, '0', STR_PAD_LEFT);

	$long = $fn([], '1', $low);

	expect(bindec($long))->toEqual(500);
});
