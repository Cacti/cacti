<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Hand-off tests for the OID trailing-zero stripping pipeline (issue #6108,
 * PR #7032).  Unit tests already cover detection and stripping in isolation.
 * These tests exercise the *full* pipeline end-to-end:
 *
 *   raw $snmp_indexes  ->  oid_index_should_strip_trailing_zero_padding()
 *                      ->  oid_index_strip_trailing_zero_padding()
 *                      ->  downstream consumer (parse last-octet via regex)
 *
 * The point is to prove that the reason this PR exists -- meaningful index
 * resolution after stripping -- actually flows through the contract between
 * producer (query_snmp_host walk) and consumer (oid_index_parse_regexp).
 */

if (strpos((string) @file_get_contents(dirname(__DIR__, 2) . '/lib/data_query.php'),
	'oid_index_should_strip_trailing_zero_padding') === false) {
	test('OID strip hand-off: feature not present on this branch', function () {})
		->skip('oid_index_should_strip_trailing_zero_padding absent — feature PR #7032 not merged into develop yet');
	return;
}

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
	require_once dirname(__DIR__, 2) . '/lib/data_query.php';
});

describe('OID strip hand-off pipeline', function () {
	$defaultRegex = '/.*\.([0-9]+)$/';

	/*
	 * Stand-in for the downstream consumer.  query_snmp_host hands the
	 * stripped indexes to oid_index_parse_regexp(), which applies the
	 * value_regex from the data query XML against each OID and returns
	 * the captured group as the index value.  The capture is the same
	 * regex shape used by the detection helper, so the inputs the
	 * consumer sees are exactly the stripped keys.
	 */
	$parseLastOctet = function (array $oids, string $regex): array {
		$indexes = [];

		foreach (array_keys($oids) as $oid) {
			if (preg_match($regex, $oid, $matches)) {
				$indexes[$oid] = $matches[1];
			}
		}

		return $indexes;
	};

	describe('full pipeline: detect -> strip -> consume', function () use ($defaultRegex, $parseLastOctet) {
		it('Netscreen multi-zero-padded walk resolves to meaningful indexes', function () use ($defaultRegex, $parseLastOctet) {
			// Realistic Netscreen zoneTable walk: ASCII-encoded zone names
			// followed by three trailing .0 padding octets.
			$snmp_indexes = [
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'         => 'Trust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0' => 'Untrust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.68.77.90.0.0.0'                   => 'DMZ',
			];

			// Stage 1: detection gate must fire for this input.
			expect(oid_index_should_strip_trailing_zero_padding($snmp_indexes, $defaultRegex))->toBeTrue();

			// Stage 2: strip helper produces keys with no trailing .0.
			$stripped = oid_index_strip_trailing_zero_padding($snmp_indexes);

			foreach (array_keys($stripped) as $oid) {
				expect($oid)->not->toEndWith('.0');
			}

			// Values must be preserved 1:1 in the original order.
			expect(array_values($stripped))->toBe(['Trust', 'Untrust', 'DMZ']);

			// Row count must be preserved -- a silent merge would drop rows.
			expect($stripped)->toHaveCount(count($snmp_indexes));

			// Stage 3: consumer applies the value_regex to the stripped keys.
			$indexes = $parseLastOctet($stripped, $defaultRegex);

			// Trust   -> last ASCII octet 116 ('t')
			// Untrust -> last ASCII octet 117 ('u')
			// DMZ     -> last ASCII octet 90  ('Z')
			expect(array_values($indexes))->toBe(['116', '117', '90']);

			// The whole reason the PR exists: indexes are no longer all '0'.
			expect(array_unique(array_values($indexes)))->toHaveCount(3);
		});
	});

	describe('collision detection blocks the pipeline', function () use ($defaultRegex, $parseLastOctet) {
		it('returns the original array unchanged when stripped keys would collide', function () use ($defaultRegex, $parseLastOctet) {
			// Both OIDs strip to '.1.2.3' -- a silent merge would lose a row.
			$snmp_indexes = [
				'.1.2.3.0'   => 'first',
				'.1.2.3.0.0' => 'second',
			];

			// Detection helper must reject the collision.
			expect(oid_index_should_strip_trailing_zero_padding($snmp_indexes, $defaultRegex))->toBeFalse();

			// Strip helper, called directly, must also be identity here.
			$stripped = oid_index_strip_trailing_zero_padding($snmp_indexes);

			expect($stripped)->toBe($snmp_indexes)
				->and($stripped)->toHaveCount(count($snmp_indexes));

			// Consumer still parses the unmodified keys -- both end in .0,
			// so this is the legacy (broken) behaviour, but no row is lost.
			$indexes = $parseLastOctet($stripped, $defaultRegex);

			expect(array_keys($indexes))->toBe(array_keys($snmp_indexes));
		});
	});

	describe('single-.0 boundary: conservative gate', function () use ($defaultRegex) {
		it('detection returns false when every OID ends in a single .0', function () use ($defaultRegex) {
			// Every OID ends in exactly one .0, so the regex captures '0'
			// for every row.  This is the ambiguous case the gate guards
			// against: we cannot tell a scalar-style trailing .0 apart from
			// a real trailing-zero pad without more rows of evidence.
			$snmp_indexes = [
				'.1.3.6.1.2.1.2.2.1.1.1.0' => 'eth0',
				'.1.3.6.1.2.1.2.2.1.1.2.0' => 'eth1',
				'.1.3.6.1.2.1.2.2.1.1.3.0' => 'lo0',
			];

			// The conservative gate: detection must NOT enable stripping
			// when only a single .0 is present, because the production
			// caller treats the detection helper's verdict as final.
			expect(oid_index_should_strip_trailing_zero_padding($snmp_indexes, $defaultRegex))->toBeFalse();
		});

		it('strip helper, called directly with single-.0 input, is identity', function () {
			// Even if the strip helper were invoked outside the gate, it
			// must not mangle single-.0 input.  This locks in the helper's
			// own conservative behaviour as a defence in depth.
			$snmp_indexes = [
				'.1.3.6.1.2.1.2.2.1.1.1.0' => 'eth0',
				'.1.3.6.1.2.1.2.2.1.1.2.0' => 'eth1',
			];

			expect(oid_index_strip_trailing_zero_padding($snmp_indexes))->toBe($snmp_indexes);
		});
	});

	describe('consumer hand-off: stripped keys parse to meaningful indexes', function () use ($defaultRegex, $parseLastOctet) {
		it('downstream regex parse against stripped keys yields non-zero last octets', function () use ($defaultRegex, $parseLastOctet) {
			$snmp_indexes = [
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'         => 'Trust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0' => 'Untrust',
			];

			// Pre-strip: every captured index is '0' -- the bug.
			$preStrip = $parseLastOctet($snmp_indexes, $defaultRegex);

			expect(array_unique(array_values($preStrip)))->toBe(['0']);

			// Run the gated pipeline.
			expect(oid_index_should_strip_trailing_zero_padding($snmp_indexes, $defaultRegex))->toBeTrue();

			$stripped = oid_index_strip_trailing_zero_padding($snmp_indexes);

			// Post-strip: captured indexes are meaningful and unique.
			$postStrip = $parseLastOctet($stripped, $defaultRegex);

			expect(array_values($postStrip))->toBe(['116', '117'])
				->and(array_unique(array_values($postStrip)))->toHaveCount(2);

			// And, critically, none of them is '0'.
			foreach ($postStrip as $oid => $index) {
				expect($index)->not->toBe('0');
			}
		});
	});
});
