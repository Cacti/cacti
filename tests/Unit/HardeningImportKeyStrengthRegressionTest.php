<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for GHSA-8w9r-xmpr-5q3v.
 *
 * Fix: reject RSA signing keys below 2048 bits in import_read_package_data()
 *      before calling openssl_verify(), preventing signature forgery via a
 *      practically factorable 512-bit key.
 *
 * Source-scan invariants — each assertion targets a pattern introduced by the
 * fix; reverting the patch causes at least one test to fail.
 */

$importSource = file_get_contents(__DIR__ . '/../../lib/import.php');

$start = strpos($importSource, 'function import_read_package_data(');
$end   = strpos($importSource, "\nfunction ", $start + 1);
$body  = substr($importSource, $start, $end - $start);

test('GHSA-8w9r-xmpr-5q3v: key strength check uses 2048-bit threshold', function () use ($body) {
	expect($body)->toContain('2048');
});

test('GHSA-8w9r-xmpr-5q3v: openssl_pkey_get_public result is checked before use', function () use ($body) {
	// The fix adds a strict false-check on the resource returned by
	// openssl_pkey_get_public() so an unparseable key is rejected early.
	expect($body)->toContain('=== false');
});

test('GHSA-8w9r-xmpr-5q3v: key strength check appears before openssl_verify call', function () use ($body) {
	$bitsCheckOffset   = strpos($body, '< 2048');
	$opensslVerifyOffset = strpos($body, 'openssl_verify(');

	expect($bitsCheckOffset)->not->toBeFalse();
	expect($opensslVerifyOffset)->not->toBeFalse();
	expect($bitsCheckOffset)->toBeLessThan($opensslVerifyOffset);
});

test('GHSA-8w9r-xmpr-5q3v: weak key rejection returns false', function () use ($body) {
	// The check and the early return must be in close proximity; find the
	// bits-check block and confirm `return false` follows it.
	$bitsCheckOffset = strpos($body, '< 2048');
	$returnOffset    = strpos($body, 'return false', $bitsCheckOffset);

	expect($bitsCheckOffset)->not->toBeFalse();
	// return false must appear within 200 bytes of the bits check
	expect($returnOffset - $bitsCheckOffset)->toBeLessThan(200);
});

test('GHSA-8w9r-xmpr-5q3v: key strength check logs a SECURITY message', function () use ($body) {
	// The rejection block must log both the severity marker and the key size
	// so operators can diagnose which package triggered the guard.
	$bitsCheckOffset = strpos($body, '< 2048');
	$logWindow       = substr($body, $bitsCheckOffset, 300);

	expect($logWindow)->toContain('SECURITY');
	expect($logWindow)->toContain('bits');
});
