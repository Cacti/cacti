<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration test for GHSA-8w9r-xmpr-5q3v: RSA key strength gate.
 *
 * Generates real RSA keypairs at 512 bits and 2048 bits, then exercises the
 * key-strength guard extracted from import_read_package_data() directly.
 *
 * Run:
 *   docker exec cacti12_web php /var/www/html/cacti/tests/integration/HardeningImportKeyStrengthIntegrationTest.php
 *
 * Exit codes: 0 = all pass, 1 = one or more failures.
 */

$failures = 0;

function pass(string $name): void {
	echo "PASS: $name\n";
}

function fail(string $name, string $reason): void {
	global $failures;
	$failures++;
	echo "FAIL: $name -- $reason\n";
}

/*
 * Inline the key-strength guard from import_read_package_data().
 * Returns false when the key is absent, unparseable, or below 2048 bits.
 * Returns the key details array when the size gate passes.
 */
function check_key_strength(string $public_key_pem): bool|array {
	$pkey = openssl_pkey_get_public($public_key_pem);
	if ($pkey === false) {
		return false;
	}
	$details = openssl_pkey_get_details($pkey);
	if ($details === false || $details['bits'] < 2048) {
		return false;
	}
	return $details;
}

// -------------------------------------------------------------------------
// Generate a 512-bit RSA keypair
// -------------------------------------------------------------------------
$weak_res = openssl_pkey_new([
	'private_key_bits' => 512,
	'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

if ($weak_res === false) {
	fail('512-bit key generation', 'openssl_pkey_new failed: ' . openssl_error_string());
	exit(1);
}

$weak_pub_details = openssl_pkey_get_details($weak_res);
$weak_pub_pem     = $weak_pub_details['key'];

// -------------------------------------------------------------------------
// Generate a 2048-bit RSA keypair
// -------------------------------------------------------------------------
$strong_res = openssl_pkey_new([
	'private_key_bits' => 2048,
	'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

if ($strong_res === false) {
	fail('2048-bit key generation', 'openssl_pkey_new failed: ' . openssl_error_string());
	exit(1);
}

$strong_pub_details = openssl_pkey_get_details($strong_res);
$strong_pub_pem     = $strong_pub_details['key'];

// -------------------------------------------------------------------------
// Test 1: 512-bit key must be rejected by the size gate
// -------------------------------------------------------------------------
$result = check_key_strength($weak_pub_pem);
if ($result === false) {
	pass('512-bit RSA key is rejected by the key strength gate');
} else {
	fail('512-bit RSA key is rejected by the key strength gate', "expected false, got bits={$result['bits']}");
}

// -------------------------------------------------------------------------
// Test 2: 2048-bit key must pass the size gate (returns details, not false)
// -------------------------------------------------------------------------
$result = check_key_strength($strong_pub_pem);
if ($result !== false && isset($result['bits']) && $result['bits'] >= 2048) {
	pass('2048-bit RSA key passes the key strength gate');
} else {
	$got = ($result === false) ? 'false' : (string)($result['bits'] ?? 'unknown');
	fail('2048-bit RSA key passes the key strength gate', "expected details with bits>=2048, got $got");
}

// -------------------------------------------------------------------------
// Test 3: unparseable / empty key must be rejected
// -------------------------------------------------------------------------
$result = check_key_strength('not-a-pem-key');
if ($result === false) {
	pass('Garbage PEM is rejected before key-size check');
} else {
	fail('Garbage PEM is rejected before key-size check', 'expected false for unparseable input');
}

// -------------------------------------------------------------------------
// Test 4: confirm the guard position in lib/import.php source
//         (sanity check that the file on disk matches what we tested above)
// -------------------------------------------------------------------------
$src_path = dirname(__DIR__, 2) . '/lib/import.php';
if (!file_exists($src_path)) {
	fail('import.php source guard position', "file not found at $src_path");
} else {
	$src   = file_get_contents($src_path);
	$start = strpos($src, 'function import_read_package_data(');
	$end   = strpos($src, "\nfunction ", $start + 1);
	$body  = substr($src, $start, $end - $start);

	$bits_pos   = strpos($body, '< 2048');
	$verify_pos = strpos($body, 'openssl_verify(');

	if ($bits_pos !== false && $verify_pos !== false && $bits_pos < $verify_pos) {
		pass('Key strength guard precedes openssl_verify in import_read_package_data source');
	} else {
		fail(
			'Key strength guard precedes openssl_verify in import_read_package_data source',
			sprintf('bits_pos=%s verify_pos=%s', var_export($bits_pos, true), var_export($verify_pos, true))
		);
	}
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n";
if ($failures === 0) {
	echo "All tests passed.\n";
	exit(0);
} else {
	echo "$failures test(s) failed.\n";
	exit(1);
}
