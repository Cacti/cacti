<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit tests for OID trailing-zero stripping in data query index parsing.
 * See GitHub issue #6108: Juniper Netscreen devices pad OID indexes with
 * trailing .0 octets, causing all indexes to resolve to 0.
 *
 * Tests call oid_index_strip_trailing_zero_padding() directly so they
 * exercise the actual production code path, not a re-implementation.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
	require_once dirname(__DIR__, 2) . '/lib/data_query.php';
});

describe('OID trailing zero stripping', function () {
	$defaultRegex = '/.*\.([0-9]+)$/';

	describe('detection', function () use ($defaultRegex) {
		dataset('trailing zero OIDs', [
			'juniper netscreen pattern' => [
				[
					'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'          => 'Trust',
					'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0'  => 'Untrust',
					'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.68.77.90.0.0.0'                    => 'DMZ',
				],
			],
		]);

		it('detects all indexes resolving to zero', function (array $oids) use ($defaultRegex) {
			$indexes = [];

			foreach ($oids as $oid => $value) {
				if (preg_match($defaultRegex, $oid, $matches)) {
					$indexes[$oid] = $matches[1];
				}
			}

			$unique = array_unique(array_values($indexes));

			expect($unique)->toHaveCount(1)
				->and($unique[0])->toBe('0');
		})->with('trailing zero OIDs');

			it('enables stripping when multi-zero padding resolves to unique indexes', function () use ($defaultRegex) {
				$oids = [
					'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'         => 'Trust',
					'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0' => 'Untrust',
				];

				expect(oid_index_should_strip_trailing_zero_padding($oids, $defaultRegex))->toBeTrue();
			});

			it('enables stripping when single-zero padding resolves to unique indexes', function () use ($defaultRegex) {
				$oids = [
					'.1.2.3.10.0' => 'a',
					'.1.2.3.20.0' => 'b',
				];

				expect(oid_index_should_strip_trailing_zero_padding($oids, $defaultRegex))->toBeTrue();
			});

			it('does not enable stripping when stripped indexes collide', function () use ($defaultRegex) {
				$oids = [
					'.1.2.3.0'   => 'first',
					'.1.2.3.0.0' => 'second',
				];

				expect(oid_index_should_strip_trailing_zero_padding($oids, $defaultRegex))->toBeFalse();
			});
		});

	describe('stripping via oid_index_strip_trailing_zero_padding()', function () use ($defaultRegex) {
		it('strips trailing .0 octets from a padded multi-row walk', function () use ($defaultRegex) {
			$oids = [
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'         => 'Trust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0' => 'Untrust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.68.77.90.0.0.0'                   => 'DMZ',
			];

			$stripped = oid_index_strip_trailing_zero_padding($oids);

			// Keys must no longer end in .0
			foreach (array_keys($stripped) as $oid) {
				expect($oid)->not->toEndWith('.0');
			}

			// Values are preserved
			expect(array_values($stripped))->toBe(['Trust', 'Untrust', 'DMZ']);
		});

		it('produces unique last-octet indexes after stripping', function () use ($defaultRegex) {
			// Trust   -> ASCII T,r,u,s,t (84,114,117,115,116) -> last octet 116
			// Untrust -> ASCII U,n,t,r,u,s,t (85,110,116,114,117,115,117) -> last octet 117
			// DMZ     -> ASCII D,M,Z (68,77,90) -> last octet 90
			$oids = [
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.117.115.116.0.0.0'         => 'Trust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.116.114.117.115.117.0.0.0' => 'Untrust',
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.68.77.90.0.0.0'                   => 'DMZ',
			];

			$stripped = oid_index_strip_trailing_zero_padding($oids);

			// Sanity: stripping must preserve row count so no row is lost.
			expect($stripped)->toHaveCount(count($oids));

			// Extract last-octet indexes directly from the stripped keys.
			$indexes = array_map(function ($oid) use ($defaultRegex) {
				return preg_match($defaultRegex, $oid, $matches) ? $matches[1] : null;
			}, array_keys($stripped));

			expect($indexes)->toBe(['116', '117', '90'])
				->and(array_unique($indexes))->toHaveCount(3);
		});

			/* These cases exercise oid_index_strip_trailing_zero_padding() in
			   isolation. Real callers gate it behind oid_index_should_strip_trailing_zero_padding(),
			   which also requires the stripped OIDs to parse to unique indexes. */
			dataset('padded strip cases', [
				'helper strips single .0' => [
					['.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.0'           => 'a',
					 '.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.0'           => 'b'],
					['.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84'             => 'a',
					 '.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85'             => 'b'],
				],
			'multiple trailing zeros' => [
				['.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114.0.0.0'   => 'a',
				 '.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110.0.0.0'   => 'b'],
				['.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.114'         => 'a',
				 '.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.85.110'         => 'b'],
			],
		]);

		it('strips correctly for padded multi-row dataset', function (array $input, array $expected) {
			expect(oid_index_strip_trailing_zero_padding($input))->toBe($expected);
		})->with('padded strip cases');
	});

	describe('no false positives', function () use ($defaultRegex) {
		it('does not strip when indexes are already unique', function () use ($defaultRegex) {
			$oids = [
				'.1.3.6.1.2.1.2.2.1.1.1' => 'eth0',
				'.1.3.6.1.2.1.2.2.1.1.2' => 'eth1',
				'.1.3.6.1.2.1.2.2.1.1.3' => 'lo0',
			];

			// No .0 padding detected; function returns originals unchanged.
			$result = oid_index_strip_trailing_zero_padding($oids);

			expect($result)->toBe($oids);
		});

		it('does not strip when only one index exists', function () use ($defaultRegex) {
			// The cacti_sizeof($indexes) <= 1 guard in the helper short-circuits
			// for single-row walks. The detection helper must also report false
			// so query_snmp_host never invokes the strip helper. Both the strip
			// helper and the detection helper are exercised here so the guard
			// cannot regress unnoticed.
			$oids = [
				'.1.3.6.1.4.1.2636.3.39.1.8.1.1.1.1.1.84.0.0.0' => 'Trust',
			];

			$result = oid_index_strip_trailing_zero_padding($oids);

			// Strip helper is identity for a single-row input, even one
			// padded with multiple .0 octets.
			expect($result)
				->toBe($oids)
				->and(array_keys($result))->toBe(array_keys($oids));

			// Detection helper rejects single-row walks regardless of padding.
			expect(oid_index_should_strip_trailing_zero_padding($oids, $defaultRegex))->toBeFalse();
		});

		it('does not strip when a zero index is legitimate (scalar OID)', function () use ($defaultRegex) {
			// A single scalar OID like .1.3.6.1.2.1.1.1.0 legitimately ends
			// in .0. The > 1 guard means this never reaches the helper, but
			// confirm the helper is identity-safe for a single-element array.
			$oids = [
				'.1.3.6.1.2.1.1.1.0' => 'sysDescr',
			];

			$result = oid_index_strip_trailing_zero_padding($oids);

			// Input equals output; the .0 is preserved.
			expect($result)->toBe($oids);

			$indexes = [];

			foreach ($result as $oid => $value) {
				if (preg_match($defaultRegex, $oid, $matches)) {
					$indexes[] = $matches[1];
				}
			}

			expect($indexes[0])->toBe('0');
		});

		it('collision detection preserves original indexes', function () {
			// .1.2.3.0 stripped -> .1.2.3
			// .1.2.3.0.0 stripped -> .1.2.3  (collision)
			$oids = [
				'.1.2.3.0'   => 'first',
				'.1.2.3.0.0' => 'second',
			];

			$result = oid_index_strip_trailing_zero_padding($oids);

			// Collision detected; originals returned unchanged.
			expect($result)->toBe($oids);
		});

		it('does not strip when only some rows end in .0', function () use ($defaultRegex) {
			// Mixed padding: the $all_end_with_zero false branch must reject
			// the strip so partial padding never reshapes a legitimate walk.
			$oids = [
				'.1.3.6.1.2.1.2.2.1.1.1.0' => 'eth0',
				'.1.3.6.1.2.1.2.2.1.1.2'   => 'eth1',
			];

			expect(oid_index_should_strip_trailing_zero_padding($oids, $defaultRegex))->toBeFalse();
			expect(oid_index_strip_trailing_zero_padding($oids))->toBe($oids);
		});

		it('one-row guard: single row ending in .0 is preserved by the helper', function () {
			// Companion to the > 1 guard in query_snmp_host. Even if the
			// helper were called with one entry, it returns input unchanged.
			$oids = [
				'.1.3.6.1.2.1.47.1.1.1.1.2.0' => 'entPhysicalDescr',
			];

			$result = oid_index_strip_trailing_zero_padding($oids);

			expect($result)->toBe($oids);
		});
	});
});
