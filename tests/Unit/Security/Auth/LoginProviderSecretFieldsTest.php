<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Covers the two new Login Provider form primitives added alongside SAML2's
 * pasted certificate/private key fields:
 *
 *  - cacti_encrypt_secret_with_key()/cacti_decrypt_secret_with_key(), the
 *    key-parameterized core of cacti_encrypt_secret()/cacti_decrypt_secret()
 *    (exercised here directly so no `settings` table/database is needed).
 *  - form_cert_box(), which shows a pasted certificate's expiration date.
 *  - form_privkey_box(), which never redisplays a stored private key.
 */

function login_provider_test_cert(int $daysValid = 365): string {
	$resource = openssl_pkey_new([
		'private_key_bits' => 2048,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	]);

	// digest_alg must be explicit: this environment's [req] section has no
	// default_md, which otherwise fails signing under OpenSSL 3.x.
	$csr  = openssl_csr_new(['commonName' => 'cacti-test'], $resource, ['digest_alg' => 'sha256']);
	$cert = openssl_csr_sign($csr, null, $resource, $daysValid, ['digest_alg' => 'sha256']);

	openssl_x509_export($cert, $pem);

	return $pem;
}

test('cacti_encrypt_secret_with_key()/cacti_decrypt_secret_with_key() round-trip the plaintext', function () {
	$key       = random_bytes(32);
	$plaintext = "-----BEGIN PRIVATE KEY-----\nsome-test-key-material\n-----END PRIVATE KEY-----";

	$ciphertext = cacti_encrypt_secret_with_key($plaintext, $key);

	expect($ciphertext)->not->toBe($plaintext);
	expect($ciphertext)->not->toBe('');
	expect(cacti_decrypt_secret_with_key($ciphertext, $key))->toBe($plaintext);
});

test('each encryption uses a fresh IV, so repeated calls produce different ciphertext', function () {
	$key       = random_bytes(32);
	$plaintext = 'the-same-secret-every-time';

	$first  = cacti_encrypt_secret_with_key($plaintext, $key);
	$second = cacti_encrypt_secret_with_key($plaintext, $key);

	expect($first)->not->toBe($second);
	expect(cacti_decrypt_secret_with_key($first, $key))->toBe($plaintext);
	expect(cacti_decrypt_secret_with_key($second, $key))->toBe($plaintext);
});

test('an empty plaintext/ciphertext round-trips as an empty string, never touching openssl', function () {
	$key = random_bytes(32);

	expect(cacti_encrypt_secret_with_key('', $key))->toBe('');
	expect(cacti_decrypt_secret_with_key('', $key))->toBe('');
});

test('decrypting with the wrong key or malformed ciphertext fails safely instead of throwing', function () {
	$key       = random_bytes(32);
	$wrongKey  = random_bytes(32);
	$plaintext = 'top secret';

	$ciphertext = cacti_encrypt_secret_with_key($plaintext, $key);

	expect(cacti_decrypt_secret_with_key($ciphertext, $wrongKey))->not->toBe($plaintext);
	expect(cacti_decrypt_secret_with_key('not-even-base64-iv-data', $key))->toBeFalse();
});

test('form_cert_box() shows the expiration date of a pasted valid certificate', function () {
	$pem = login_provider_test_cert(30);

	ob_start();
	form_cert_box('idp_x509cert', $pem, '', 6, 60);
	$html = ob_get_clean();

	$expected = date('Y-m-d', strtotime('+30 days'));

	expect($html)->toContain('<textarea');
	expect($html)->toContain('Good Till: ' . $expected);
	expect($html)->toContain('fa-circle-check');
	expect($html)->toContain('deviceUp');
});

test('form_cert_box() never redisplays the stored certificate, like form_privkey_box()', function () {
	$pem = login_provider_test_cert(30);

	ob_start();
	form_cert_box('idp_x509cert', $pem, '', 6, 60);
	$html = ob_get_clean();

	expect($html)->not->toContain('BEGIN CERTIFICATE');

	$matched = preg_match("/<textarea[^>]*>(.*?)<\/textarea>/s", $html, $matches);
	expect($matched)->toBe(1);
	expect($matches[1])->toBe('');
});

test('form_cert_box() flags a pasted value that is not a valid certificate', function () {
	ob_start();
	form_cert_box('idp_x509cert', 'not a certificate', '', 6, 60);
	$html = ob_get_clean();

	expect($html)->toContain('fa-circle-xmark');
	expect($html)->toContain('deviceDown');
	expect($html)->toContain('Not a valid Certificate');
});

test('form_cert_box() flags an expired certificate with deviceDown and its expiry date', function () {
	// 0 days validity: notAfter equals the moment of generation, already
	// expired by the time openssl_x509_parse() is checked against time() -
	// sleep(1) guards against the two falling in the same second.
	$pem = login_provider_test_cert(0);
	sleep(1);

	ob_start();
	form_cert_box('idp_x509cert', $pem, '', 6, 60);
	$html = ob_get_clean();

	$expected = date('Y-m-d');

	expect($html)->toContain('fa-circle-xmark');
	expect($html)->toContain('deviceDown');
	expect($html)->toContain('Expired: ' . $expected);
});

test('form_cert_box() shows no status indicator when left blank', function () {
	ob_start();
	form_cert_box('idp_x509cert', '', '', 6, 60);
	$html = ob_get_clean();

	expect($html)->not->toContain('fa-circle-check');
	expect($html)->not->toContain('fa-circle-xmark');
});

test('form_privkey_box() never renders the stored secret and shows [stored]', function () {
	$stillEncryptedBlob = cacti_encrypt_secret_with_key('super-secret-private-key', random_bytes(32));

	ob_start();
	form_privkey_box('sp_private_key', $stillEncryptedBlob, 4, 60);
	$html = ob_get_clean();

	expect($html)->toContain('[stored]');
	expect($html)->not->toContain('super-secret-private-key');
	expect($html)->not->toContain($stillEncryptedBlob);
	expect($html)->toContain("id='sp_private_key'");

	// The rendered textarea itself must always be empty, regardless of the stored value.
	$matched = preg_match("/<textarea[^>]*>(.*?)<\/textarea>/s", $html, $matches);
	expect($matched)->toBe(1);
	expect($matches[1])->toBe('');
});

test('form_privkey_box() shows [not set] when nothing is stored', function () {
	ob_start();
	form_privkey_box('sp_private_key', '', 4, 60);
	$html = ob_get_clean();

	expect($html)->toContain('[not set]');
});

test('keepExistingIfBlank() saves the freshly submitted value without touching the database', function () {
	$method = new ReflectionMethod(\Cacti\Auth\SamlLoginProvider::class, 'keepExistingIfBlank');
	$method->setAccessible(true);

	expect($method->invoke(null, 'fresh-cert-pem', 'sp_x509cert'))->toBe('fresh-cert-pem');
});

test('keepExistingIfBlank()/encryptOrKeepExisting() resolve to "" for a blank field with no existing provider row, without touching the database', function () {
	$GLOBALS['_CACTI_REQUEST']['id'] = 0;

	$keepExisting = new ReflectionMethod(\Cacti\Auth\SamlLoginProvider::class, 'keepExistingIfBlank');
	$keepExisting->setAccessible(true);
	expect($keepExisting->invoke(null, '', 'sp_x509cert'))->toBe('');

	$encryptOrKeep = new ReflectionMethod(\Cacti\Auth\SamlLoginProvider::class, 'encryptOrKeepExisting');
	$encryptOrKeep->setAccessible(true);
	expect($encryptOrKeep->invoke(null, '', 'sp_private_key'))->toBe('');

	unset($GLOBALS['_CACTI_REQUEST']['id']);
});
