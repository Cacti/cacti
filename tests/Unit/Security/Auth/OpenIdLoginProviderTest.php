<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * OpenIdLoginProvider is the credential-bearing boundary for OIDC logins:
 * verifyIdToken() is the only thing standing between an attacker-controlled
 * ID token and a provisioned Cacti account, and complete() is the only thing
 * standing between a forged callback and a completed login. These tests
 * exercise both against a locally-generated RSA keypair/JWKS - no real IdP
 * or network access is used or required.
 *
 * cacti_http() is stubbed in the Cacti\Auth namespace: PHP resolves an
 * unqualified call from within that namespace to a same-namespace function
 * before falling back to the real global one, so verifyIdToken()/discover()/
 * complete() transparently hit the canned responses set up per test instead
 * of making a real network call.
 */

namespace Cacti\Auth {
	function cacti_http(string $method, string $url, array $options = []): array {
		$responses = $GLOBALS['__oidc_test_http_responses'] ?? [];

		if (isset($responses[$url])) {
			return $responses[$url];
		}

		return ['success' => false, 'status' => 0, 'body' => '', 'error' => 'no stub response for ' . $url];
	}
}

namespace {
	use Cacti\Auth\OpenIdLoginProvider;
	use Firebase\JWT\JWT;

	function oidc_test_keypair(): array {
		$resource = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);

		openssl_pkey_export($resource, $privateKeyPem);
		$details = openssl_pkey_get_details($resource);

		return [$privateKeyPem, $details['rsa']];
	}

	function oidc_base64url(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	function oidc_test_jwks(array $rsaDetails, string $kid): array {
		return [
			'keys' => [
				[
					'kty' => 'RSA',
					'kid' => $kid,
					'use' => 'sig',
					'alg' => 'RS256',
					'n'   => oidc_base64url($rsaDetails['n']),
					'e'   => oidc_base64url($rsaDetails['e']),
				],
			],
		];
	}

	function oidc_test_provider(array $parameters = []): OpenIdLoginProvider {
		$row = [
			'id'                  => 1,
			'name'                => 'Test OIDC',
			'type'                => PROVIDER_TYPE_OPENID,
			'enabled'             => 'on',
			'allow_auth_cookies'  => 'on',
			'user_id'             => 0,
			'button_label'        => '',
			'debug'               => '',
			'parameters'          => json_encode($parameters + [
				'client_id'     => 'test-client',
				'client_secret' => 'test-secret',
				'discovery_url' => 'https://idp.example.test/.well-known/openid-configuration',
			]),
		];

		return new OpenIdLoginProvider($row);
	}

	function oidc_verify_id_token(OpenIdLoginProvider $provider, string $idToken, array $discovery, string $nonce): ?array {
		$method = new ReflectionMethod($provider, 'verifyIdToken');
		$method->setAccessible(true);

		return $method->invoke($provider, $idToken, $discovery, $nonce);
	}

	function oidc_test_discovery(string $jwksUri, string $issuer, ?string $userinfoEndpoint = null): array {
		$discovery = [
			'authorization_endpoint' => 'https://idp.example.test/authorize',
			'token_endpoint'         => 'https://idp.example.test/token',
			'jwks_uri'               => $jwksUri,
			'issuer'                 => $issuer,
		];

		if ($userinfoEndpoint !== null) {
			$discovery['userinfo_endpoint'] = $userinfoEndpoint;
		}

		return $discovery;
	}

	beforeEach(function () {
		$GLOBALS['__oidc_test_http_responses'] = [];
		$_SESSION                              = [];
		$_REQUEST                              = [];
	});

	// -----------------------------------------------------------------------
	// verifyIdToken(): signature, issuer, audience, azp, nonce
	// -----------------------------------------------------------------------

	test('a validly signed token with matching issuer, audience and nonce is accepted', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		$claims = oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce');

		expect($claims)->not->toBeNull()
			->and($claims['sub'])->toBe('user-1');
	});

	test('a token signed by a different key than the published JWKS is rejected', function () {
		[$privateKey]           = oidc_test_keypair();
		[, $otherRsaDetails]    = oidc_test_keypair();
		$provider               = oidc_test_provider();
		$discovery              = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		// JWKS published here has a different public key than the one that signed the token above.
		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($otherRsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	test('a token with the wrong issuer is rejected', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://attacker.example.test',
			'aud'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	test('a token with a different audience is rejected', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'someone-elses-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	test('a multi-audience token with no azp is rejected', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => ['test-client', 'another-client'],
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	test('a multi-audience token with a matching azp is accepted', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => ['test-client', 'another-client'],
			'azp'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->not->toBeNull();
	});

	test('a token with an azp that does not match this client is rejected even for a single audience', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'test-client',
			'azp'   => 'someone-elses-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	test('a token with the wrong nonce is rejected', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discovery                 = oidc_test_discovery('https://idp.example.test/jwks', 'https://idp.example.test');

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'replayed-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'][$discovery['jwks_uri']] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
			'error'   => '',
		];

		expect(oidc_verify_id_token($provider, $idToken, $discovery, 'expected-nonce'))->toBeNull();
	});

	// -----------------------------------------------------------------------
	// discover(): HTTPS enforcement
	// -----------------------------------------------------------------------

	test('a non-HTTPS discovery URL is rejected before any network call', function () {
		$provider = oidc_test_provider(['discovery_url' => 'http://idp.example.test/.well-known/openid-configuration']);

		$method = new ReflectionMethod($provider, 'discover');
		$method->setAccessible(true);

		expect(fn () => $method->invoke($provider))->toThrow(\RuntimeException::class);
	});

	test('a discovery document advertising a non-HTTPS endpoint is rejected', function () {
		$provider     = oidc_test_provider();
		$discoveryUrl = 'https://idp.example.test/.well-known/openid-configuration';

		$GLOBALS['__oidc_test_http_responses'][$discoveryUrl] = [
			'success' => true,
			'status'  => 200,
			'body'    => json_encode([
				'authorization_endpoint' => 'https://idp.example.test/authorize',
				'token_endpoint'         => 'http://idp.example.test/token',
				'jwks_uri'               => 'https://idp.example.test/jwks',
				'issuer'                 => 'https://idp.example.test',
			]),
			'error'   => '',
		];

		$method = new ReflectionMethod($provider, 'discover');
		$method->setAccessible(true);

		expect(fn () => $method->invoke($provider))->toThrow(\RuntimeException::class);
	});

	// -----------------------------------------------------------------------
	// complete(): callback state and UserInfo subject matching
	// -----------------------------------------------------------------------

	test('a callback with a state that does not match the saved session is rejected', function () {
		$provider = oidc_test_provider();

		$_SESSION['sess_oidc_' . $provider->getId()] = [
			'state'    => 'expected-state',
			'nonce'    => 'expected-nonce',
			'verifier' => 'verifier-value',
			'remember' => false,
		];

		$_REQUEST['state'] = 'forged-state';
		$_REQUEST['code']  = 'auth-code';

		$result = $provider->complete();

		expect($result->success)->toBeFalse();
	});

	test('a callback with no saved session state is rejected', function () {
		$provider = oidc_test_provider();

		$_REQUEST['state'] = 'anything';
		$_REQUEST['code']  = 'auth-code';

		$result = $provider->complete();

		expect($result->success)->toBeFalse();
	});

	test('a full callback authenticates and ignores UserInfo with a mismatched subject', function () {
		[$privateKey, $rsaDetails] = oidc_test_keypair();
		$provider                  = oidc_test_provider();
		$discoveryUrl              = 'https://idp.example.test/.well-known/openid-configuration';

		$_SESSION['sess_oidc_' . $provider->getId()] = [
			'state'    => 'expected-state',
			'nonce'    => 'expected-nonce',
			'verifier' => 'verifier-value',
			'remember' => true,
		];

		$_REQUEST['state'] = 'expected-state';
		$_REQUEST['code']  = 'auth-code';

		$idToken = JWT::encode([
			'iss'   => 'https://idp.example.test',
			'aud'   => 'test-client',
			'sub'   => 'user-1',
			'nonce' => 'expected-nonce',
			'exp'   => time() + 300,
		], $privateKey, 'RS256', 'kid-1');

		$GLOBALS['__oidc_test_http_responses'] = [
			$discoveryUrl => [
				'success' => true,
				'status'  => 200,
				'body'    => json_encode([
					'authorization_endpoint' => 'https://idp.example.test/authorize',
					'token_endpoint'         => 'https://idp.example.test/token',
					'jwks_uri'               => 'https://idp.example.test/jwks',
					'issuer'                 => 'https://idp.example.test',
					'userinfo_endpoint'      => 'https://idp.example.test/userinfo',
				]),
				'error'   => '',
			],
			'https://idp.example.test/token' => [
				'success' => true,
				'status'  => 200,
				'body'    => json_encode([
					'id_token'     => $idToken,
					'access_token' => 'opaque-access-token',
				]),
				'error'   => '',
			],
			'https://idp.example.test/jwks' => [
				'success' => true,
				'status'  => 200,
				'body'    => json_encode(oidc_test_jwks($rsaDetails, 'kid-1')),
				'error'   => '',
			],
			// UserInfo claims a DIFFERENT subject than the verified ID token -
			// must be ignored rather than merged/trusted.
			'https://idp.example.test/userinfo' => [
				'success' => true,
				'status'  => 200,
				'body'    => json_encode([
					'sub'  => 'attacker-controlled-subject',
					'name' => 'Attacker Name',
				]),
				'error'   => '',
			],
		];

		$result = $provider->complete();

		expect($result->success)->toBeTrue()
			->and($result->username)->toBe('user-1')
			->and($result->claims['full_name'])->toBe('')
			->and($result->rememberMe)->toBeTrue();
	});
}
