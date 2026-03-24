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
 * Regression tests for cacti_snmp_validate_oid() bug fix.
 *
 * The original code used array_search(false, $validate, true) which
 * returns 0 (falsy) when false is the first element, causing
 * all-non-numeric OIDs to incorrectly pass validation.
 *
 * Fix: replaced array_search with in_array, added empty-string guard.
 *
 * @group regression
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

if (!function_exists('cacti_snmp_validate_oid')) {
	function cacti_snmp_validate_oid(string $oid): bool {
		$oid = ltrim($oid, '.');
		if ($oid === '') {
			return false;
		}
		$validate = array_map('is_numeric', explode('.', $oid));
		return !in_array(false, $validate, true);
	}
}

// ===========================================================================
// Valid OIDs
// ===========================================================================

describe('Valid OIDs', function () {
	test('standard numeric OID', function () {
		expect(cacti_snmp_validate_oid('.1.3.6.1.2.1.1.1.0'))->toBeTrue();
	});

	test('without leading dot', function () {
		expect(cacti_snmp_validate_oid('1.3.6.1.2.1.1.1.0'))->toBeTrue();
	});

	test('single number', function () {
		expect(cacti_snmp_validate_oid('1'))->toBeTrue();
	});

	test('two-component OID', function () {
		expect(cacti_snmp_validate_oid('1.3'))->toBeTrue();
	});
});

// ===========================================================================
// Invalid OIDs (these were the buggy cases)
// ===========================================================================

describe('Invalid OIDs (previously passed due to array_search bug)', function () {
	test('dot-only returns false', function () {
		expect(cacti_snmp_validate_oid('.'))->toBeFalse();
	});

	test('all-alpha returns false', function () {
		expect(cacti_snmp_validate_oid('abc.def'))->toBeFalse();
	});

	test('MIB name style returns false', function () {
		expect(cacti_snmp_validate_oid('IF-MIB::ifDescr.1'))->toBeFalse();
	});

	test('empty string returns false', function () {
		expect(cacti_snmp_validate_oid(''))->toBeFalse();
	});

	test('mixed alpha-numeric returns false', function () {
		expect(cacti_snmp_validate_oid('1.3.abc.4'))->toBeFalse();
	});

	test('special characters return false', function () {
		expect(cacti_snmp_validate_oid('.1.3.6;DROP TABLE'))->toBeFalse();
	});
});

// ===========================================================================
// Mutation killers
// ===========================================================================

describe('OID validation mutation killers', function () {
	test('valid=true, invalid=false - never inverted', function () {
		expect(cacti_snmp_validate_oid('1.3.6.1'))->toBeTrue();
		expect(cacti_snmp_validate_oid('1.3.6.1'))->not->toBeFalse();
		expect(cacti_snmp_validate_oid('abc'))->toBeFalse();
		expect(cacti_snmp_validate_oid('abc'))->not->toBeTrue();
	});

	test('empty string is false, not true', function () {
		expect(cacti_snmp_validate_oid(''))->toBeFalse();
		expect(cacti_snmp_validate_oid(''))->not->toBeTrue();
	});

	test('leading dot stripped correctly', function () {
		expect(cacti_snmp_validate_oid('.1.3'))->toBeTrue();
		expect(cacti_snmp_validate_oid('1.3'))->toBeTrue();
	});

	test('deterministic over 100 runs', function () {
		for ($i = 0; $i < 100; $i++) {
			expect(cacti_snmp_validate_oid('1.3.6'))->toBeTrue();
			expect(cacti_snmp_validate_oid('abc'))->toBeFalse();
		}
	});
});
