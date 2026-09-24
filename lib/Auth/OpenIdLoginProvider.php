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

declare(strict_types = 1);

namespace Cacti\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * OpenID Connect Login Provider (Authorization Code + PKCE).
 *
 * Every outbound call (discovery document, JWKS, token exchange, userinfo)
 * goes through cacti_http(), Cacti's SSRF-hardened fetch helper, rather than
 * a bundled client library that would make its own raw HTTP calls.
 * firebase/php-jwt is used only for what it is good at: verifying the ID
 * token's signature and decoding its claims.
 */
class OpenIdLoginProvider extends AbstractLoginProvider implements RedirectLoginProviderInterface {
	public static function collectParameters(): array {
		return [
			// no-validation: OAuth2 client identifier issued by the IdP, free text
			'client_id'       => form_input_validate(gnrv('client_id'), 'client_id', '', true, 3),
			// no-validation: OAuth2 client secret, an arbitrary secret with no format to enforce
			'client_secret'   => form_input_validate(gnrv('client_secret'), 'client_secret', '', true, 3),
			// no-validation: space-delimited OAuth2 scope list, free text
			'scopes'          => form_input_validate(gnrv('scopes'), 'scopes', '', true, 3),
			// no-validation: scheme is enforced separately in discover() (HTTPS required)
			'discovery_url'   => form_input_validate(gnrv('discovery_url'), 'discovery_url', '', true, 3),
			'claim_username'  => gnrv('oidc_claim_username'),
			'claim_full_name' => gnrv('oidc_claim_full_name'),
			'claim_email'     => gnrv('oidc_claim_email'),
			'group_claim'     => gnrv('oidc_group_claim'),
			'group_name'      => gnrv('oidc_group_name'),
		];
	}

	public function getButtonLabel(): string {
		return $this->param('button_label') !== '' ? (string) $this->param('button_label') : $this->getName();
	}

	public function initiate(): never {
		$discovery = $this->discover();

		$verifier  = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		$state     = bin2hex(random_bytes(16));
		$nonce     = bin2hex(random_bytes(16));

		$_SESSION['sess_oidc_' . $this->getId()] = [
			'state'    => $state,
			'nonce'    => $nonce,
			'verifier' => $verifier,
			// Carried across the IdP redirect since the login form's checkbox
			// state cannot otherwise survive the round trip.
			'remember' => isrv('remember_me'),
		];

		$query = http_build_query([
			'response_type'         => 'code',
			'client_id'             => $this->param('client_id'),
			'redirect_uri'          => $this->redirectUri(),
			'scope'                 => $this->param('scopes', 'openid profile email'),
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
		]);

		header('Location: ' . $discovery['authorization_endpoint'] . '?' . $query);

		exit;
	}

	public function complete(): LoginResult {
		$saved = $_SESSION['sess_oidc_' . $this->getId()] ?? null;

		unset($_SESSION['sess_oidc_' . $this->getId()]);

		if (!is_array($saved)) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		if (!hash_equals($saved['state'], (string) ($_GET['state'] ?? ''))) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$code = (string) ($_GET['code'] ?? '');

		if ($code === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$discovery = $this->discover();

		$token = cacti_http('POST', $discovery['token_endpoint'], [
			'headers' => ['Content-Type: application/x-www-form-urlencoded'],
			'body'    => http_build_query([
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $this->redirectUri(),
				'client_id'     => $this->param('client_id'),
				'client_secret' => $this->param('client_secret'),
				'code_verifier' => $saved['verifier'],
			]),
		]);

		if (!$token['success']) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$tokens = json_decode($token['body'], true);

		if (!is_array($tokens) || empty($tokens['id_token'])) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$claims = $this->verifyIdToken((string) $tokens['id_token'], $discovery, $saved['nonce']);

		if ($claims === null) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		if (!empty($tokens['access_token']) && !empty($discovery['userinfo_endpoint'])) {
			$userinfo = cacti_http('GET', $discovery['userinfo_endpoint'], [
				'headers' => ['Authorization: Bearer ' . $tokens['access_token']],
			]);

			if ($userinfo['success']) {
				$decoded = json_decode($userinfo['body'], true);

				// The UserInfo response is a second, separate credential-bearing
				// fetch; only trust it when its subject matches the already-verified
				// ID token, or a substituted/inconsistent response could overwrite
				// identity claims such as sub/preferred_username.
				if (is_array($decoded) && isset($decoded['sub'], $claims['sub']) && hash_equals((string) $claims['sub'], (string) $decoded['sub'])) {
					$claims = array_merge($claims, $decoded);
				}
			}
		}

		$usernameClaim = (string) $this->param('claim_username', 'preferred_username');
		$username       = (string) ($claims[$usernameClaim] ?? $claims['sub'] ?? '');

		if ($username === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$groupClaim   = (string) $this->param('group_claim');
		$memberships  = $groupClaim !== '' ? (array) ($claims[$groupClaim] ?? []) : [];

		if (!$this->groupMembershipAllows($memberships, (string) $this->param('group_name'))) {
			return LoginResult::failure(__('Access Denied!  You are not a member of the required group.'));
		}

		return LoginResult::authenticated($username, [
			'full_name' => (string) ($claims[(string) $this->param('claim_full_name', 'name')] ?? ''),
			'email'     => (string) ($claims[(string) $this->param('claim_email', 'email')] ?? ''),
		], null, (bool) ($saved['remember'] ?? false));
	}

	/**
	 * Verify the ID token's signature (via the IdP's published JWKS) and
	 * standard claims (issuer, audience, nonce, expiry), returning its
	 * payload as an array on success or null on any validation failure.
	 */
	protected function verifyIdToken(string $idToken, array $discovery, string $expectedNonce): ?array {
		$jwks = cacti_http('GET', $discovery['jwks_uri']);

		if (!$jwks['success']) {
			return null;
		}

		$keySet = json_decode($jwks['body'], true);

		if (!is_array($keySet)) {
			return null;
		}

		try {
			$decoded = (array) JWT::decode($idToken, JWK::parseKeySet($keySet));
		} catch (\Throwable) {
			return null;
		}

		if (($decoded['iss'] ?? null) !== $discovery['issuer']) {
			return null;
		}

		$audience = (array) ($decoded['aud'] ?? []);
		$clientId = (string) $this->param('client_id');
		$audienceMatches = in_array($clientId, $audience, true) || ($decoded['aud'] ?? null) === $clientId;

		if (!$audienceMatches) {
			return null;
		}

		// A multi-audience token MUST carry an azp identifying this client, and
		// an azp that is present but wrong is rejected even for a single audience.
		$azp = $decoded['azp'] ?? null;

		if ($azp !== null && (string) $azp !== $clientId) {
			return null;
		}

		if (count($audience) > 1 && $azp === null) {
			return null;
		}

		if (!hash_equals($expectedNonce, (string) ($decoded['nonce'] ?? ''))) {
			return null;
		}

		return $decoded;
	}

	/**
	 * @return array{authorization_endpoint: string, token_endpoint: string,
	 *               jwks_uri: string, issuer: string, userinfo_endpoint?: string}
	 */
	protected function discover(): array {
		$discoveryUrl = (string) $this->param('discovery_url');

		if (!str_starts_with($discoveryUrl, 'https://')) {
			throw new \RuntimeException('OpenID discovery URL must use HTTPS');
		}

		$response = cacti_http('GET', $discoveryUrl);

		if (!$response['success']) {
			throw new \RuntimeException('Unable to fetch OpenID discovery document');
		}

		$discovery = json_decode($response['body'], true);

		if (!is_array($discovery) || empty($discovery['authorization_endpoint']) || empty($discovery['token_endpoint']) || empty($discovery['jwks_uri'])) {
			throw new \RuntimeException('Invalid OpenID discovery document');
		}

		// A discovery document served from a plain-HTTP issuer could point every
		// downstream endpoint at HTTP too, exposing the client secret,
		// authorization code, and tokens in transit and allowing key substitution.
		foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint'] as $endpoint) {
			if (!empty($discovery[$endpoint]) && !str_starts_with((string) $discovery[$endpoint], 'https://')) {
				throw new \RuntimeException('OpenID endpoint must use HTTPS: ' . $endpoint);
			}
		}

		return $discovery;
	}

	protected function redirectUri(): string {
		return rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=callback&realm=' . (1000 + $this->getId());
	}
}
