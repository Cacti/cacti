<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for issue #7026.
 *
 * Fix: drop the key-length conditional in import_read_package_data() and
 *      import_package() that selected OPENSSL_ALGO_SHA1 for short keys.
 *      Package signatures are now verified with SHA-256 unconditionally.
 *
 * Source-scan invariants plus a behavioural check that a SHA-1 signature no
 * longer verifies through the SHA-256 path.
 */

$importSource = file_get_contents(__DIR__ . '/../../lib/import.php');

test('#7026: SHA-1 is no longer referenced in the signature verification code', function () use ($importSource) {
	expect($importSource)->not->toContain('OPENSSL_ALGO_SHA1');
});

test('#7026: the key-length fallback conditional is gone', function () use ($importSource) {
	expect($importSource)->not->toContain('strlen($public_key) < 200');
});

test('#7026: both verify sites use OPENSSL_ALGO_SHA256', function () use ($importSource) {
	// Two call sites remain: the XML signature and the per-file signature.
	$count = substr_count($importSource, 'OPENSSL_ALGO_SHA256');
	expect($count)->toBeGreaterThanOrEqual(2);
});

test('#7026: a SHA-256 signature verifies and a SHA-1 signature is rejected', function () {
	$res = openssl_pkey_new([
		'private_key_bits' => 2048,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	]);

	expect($res)->not->toBeFalse();

	$details = openssl_pkey_get_details($res);
	$public  = $details['key'];
	$data    = 'cacti package payload';

	$sha256_sig = '';
	openssl_sign($data, $sha256_sig, $res, OPENSSL_ALGO_SHA256);

	$sha1_sig = '';
	openssl_sign($data, $sha1_sig, $res, OPENSSL_ALGO_SHA1);

	// The verification path now uses SHA-256 only.
	expect(openssl_verify($data, $sha256_sig, $public, OPENSSL_ALGO_SHA256))->toBe(1);
	expect(openssl_verify($data, $sha1_sig, $public, OPENSSL_ALGO_SHA256))->toBe(0);
});
