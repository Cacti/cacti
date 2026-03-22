<?php
declare(strict_types=1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for issue #6879: multi-DS output hex values
 * overwritten by raw string in process_poller_output().
 *
 * The bug was an unconditional assignment at lib/poller.php:643
 * that overwrote the coerced value (hexdec result or 'U') with
 * the raw $matches[1] string after the if/elseif/else block.
 *
 * @group regression
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

/**
 * Simulate the MULTI-output field value coercion logic from
 * process_poller_output() lines 635-641.
 *
 * This is the FIXED version: no unconditional overwrite after
 * the coercion block.
 *
 * @param string $raw_value The raw value from a key:value pair
 *
 * @return string|int The coerced value for RRD storage
 */
function coerce_multi_field_value_fixed(string $raw_value): string|int {
	if (is_numeric($raw_value) || $raw_value === 'U') {
		return $raw_value;
	}

	if (function_exists('is_hexadecimal') && is_hexadecimal($raw_value)) {
		return hexdec($raw_value);
	}

	return 'U';
}

/**
 * Simulate the BUGGY version that had the unconditional overwrite
 * at line 643.
 *
 * @param string $raw_value The raw value from a key:value pair
 *
 * @return string|int Always returns raw_value (bug)
 */
function coerce_multi_field_value_buggy(string $raw_value): string|int {
	if (is_numeric($raw_value) || $raw_value === 'U') {
		$result = $raw_value;
	} elseif (function_exists('is_hexadecimal') && is_hexadecimal($raw_value)) {
		$result = hexdec($raw_value);
	} else {
		$result = 'U';
	}

	// BUG: unconditional overwrite (was line 643)
	$result = $raw_value;

	return $result;
}

if (!function_exists('is_hexadecimal')) {
	function is_hexadecimal(string $result): bool {
		$hexstr = str_replace([' ', '-'], ':', trim($result));
		foreach (explode(':', $hexstr) as $part) {
			if (strlen($part) != 2 || !ctype_xdigit($part)) {
				return false;
			}
		}
		return true;
	}
}

// ===========================================================================
// Regression tests: fixed behavior
// ===========================================================================

describe('Regression #6879: multi-DS hex overwrite fix', function () {
	test('numeric value preserved by fixed version', function () {
		expect(coerce_multi_field_value_fixed('12345'))->toBe('12345');
	});

	test('U value preserved by fixed version', function () {
		expect(coerce_multi_field_value_fixed('U'))->toBe('U');
	});

	test('hex value converted to decimal by fixed version', function () {
		$r = coerce_multi_field_value_fixed('0A:0B');
		expect($r)->toBeInt();
		expect($r)->not->toBe('0A:0B');
	});

	test('non-numeric non-hex becomes U in fixed version', function () {
		expect(coerce_multi_field_value_fixed('garbage'))->toBe('U');
	});

	test('buggy version always returns raw value (demonstrates the bug)', function () {
		// The buggy version overwrites everything with raw input
		expect(coerce_multi_field_value_buggy('garbage'))->toBe('garbage');
		expect(coerce_multi_field_value_buggy('garbage'))->not->toBe('U');
	});

	test('fixed version returns U for garbage, buggy returns raw', function () {
		$fixed = coerce_multi_field_value_fixed('not_a_number');
		$buggy = coerce_multi_field_value_buggy('not_a_number');

		expect($fixed)->toBe('U');
		expect($buggy)->toBe('not_a_number');
		expect($fixed)->not->toBe($buggy);
	});
});

// ===========================================================================
// Mutation killers for the fix
// ===========================================================================

describe('Mutation killers for #6879 fix', function () {
	test('numeric stays numeric, never becomes U', function () {
		$r = coerce_multi_field_value_fixed('100');
		expect($r)->toBe('100');
		expect($r)->not->toBe('U');
	});

	test('U stays U, never becomes numeric', function () {
		$r = coerce_multi_field_value_fixed('U');
		expect($r)->toBe('U');
		expect($r)->not->toBeInt();
	});

	test('garbage becomes U, never passes through raw', function () {
		$r = coerce_multi_field_value_fixed('random_junk');
		expect($r)->toBe('U');
		expect($r)->not->toBe('random_junk');
	});

	test('fixed and buggy produce different results for garbage', function () {
		$fixed = coerce_multi_field_value_fixed('xyz');
		$buggy = coerce_multi_field_value_buggy('xyz');
		expect($fixed)->not->toBe($buggy);
	});

	test('coercion is deterministic over 100 runs', function () {
		$first = coerce_multi_field_value_fixed('test_value');
		for ($i = 0; $i < 100; $i++) {
			expect(coerce_multi_field_value_fixed('test_value'))->toBe($first);
		}
	});

	test('negative numeric is preserved', function () {
		expect(coerce_multi_field_value_fixed('-42.5'))->toBe('-42.5');
	});

	test('scientific notation is preserved', function () {
		expect(coerce_multi_field_value_fixed('1.5e+09'))->toBe('1.5e+09');
	});

	test('empty string becomes U', function () {
		expect(coerce_multi_field_value_fixed(''))->toBe('U');
	});
});

// ===========================================================================
// Source verification: the bug is actually fixed
// ===========================================================================

describe('Source verification: overwrite line removed', function () {
	test('no unconditional matches[1] overwrite after coercion block', function () {
		$c = file_get_contents(__DIR__ . '/../../lib/poller.php');

		// Find the first multi-DS coercion block (with data template)
		$pos = strpos($c, "if (is_numeric(\$matches[1]) || (\$matches[1] == 'U'))");
		$segment = substr($c, $pos, 500);

		// The segment should contain the coercion if/elseif/else
		expect($segment)->toContain('is_numeric($matches[1])');
		expect($segment)->toContain('is_hexadecimal');
		expect($segment)->toContain("= 'U'");

		// But should NOT have an unconditional overwrite right after
		// Look for the pattern: closing brace, blank line, then $matches[1] assignment
		expect($segment)->not->toMatch('/\}\s*\n\s*\n\s*\$rrd_update_array.*\$matches\[1\]/');
	});
});
