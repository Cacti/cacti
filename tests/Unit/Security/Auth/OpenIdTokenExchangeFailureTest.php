<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * A failed authorization code exchange must deny the login, and only a
 * provider with debugging enabled may write the IdP's failure reason to the
 * Cacti log. The discovery document is supplied by overriding discover(), so
 * no IdP is contacted. The token endpoint points at a loopback address that
 * cacti_http()'s SSRF guard refuses before opening a connection.
 */

use Cacti\Auth\OpenIdLoginProvider;

class OpenIdTokenExchangeFailureProvider extends OpenIdLoginProvider {
	protected function discover(): array {
		return [
			'authorization_endpoint' => 'https://idp.example.test/authorize',
			'token_endpoint'         => 'https://127.0.0.1/token',
			'jwks_uri'               => 'https://idp.example.test/jwks',
			'issuer'                 => 'https://idp.example.test',
		];
	}
}

function oidc_exchange_provider(string $debug) : OpenIdTokenExchangeFailureProvider {
	return new OpenIdTokenExchangeFailureProvider([
		'id'                 => 4,
		'name'               => 'Exchange OIDC',
		'type'               => PROVIDER_TYPE_OPENID,
		'enabled'            => 'on',
		'allow_auth_cookies' => 'on',
		'user_id'            => 0,
		'button_label'       => '',
		'debug'              => $debug,
		'parameters'         => json_encode([
			'client_id'     => 'test-client',
			'client_secret' => 'test-secret',
			'discovery_url' => 'https://idp.example.test/.well-known/openid-configuration',
		]),
	]);
}

function oidc_exchange_callback(OpenIdTokenExchangeFailureProvider $provider) : void {
	$_SESSION['sess_oidc_' . $provider->getId()] = [
		'state'    => 'expected-state',
		'nonce'    => 'expected-nonce',
		'verifier' => 'verifier-value',
		'remember' => false,
	];

	$_REQUEST['state'] = 'expected-state';
	$_REQUEST['code']  = 'auth-code';
}

function oidc_exchange_log() : string {
	clearstatcache();

	return is_file($GLOBALS['oidc_exchange_log']) ? (string) file_get_contents($GLOBALS['oidc_exchange_log']) : '';
}

beforeEach(function () {
	global $config;

	$this->oidc_exchange_prior = [
		'session'     => $_SESSION ?? [],
		'request'     => $_REQUEST,
		'options_cli' => $config[OPTIONS_CLI] ?? null,
	];

	$GLOBALS['oidc_exchange_log'] = tempnam(sys_get_temp_dir(), 'cacti-oidc-log-');

	$options = [
		'log_destination'        => 1,
		'log_verbosity'          => POLLER_VERBOSITY_LOW,
		'path_cactilog'          => $GLOBALS['oidc_exchange_log'],
		'selective_debug'        => '',
		'selective_plugin_debug' => '',
	];

	$_SESSION              = [OPTIONS_WEB => $options];
	$_REQUEST              = [];
	$config[OPTIONS_CLI]   = array_merge($config[OPTIONS_CLI] ?? [], $options);
});

afterEach(function () {
	global $config;

	$_SESSION = $this->oidc_exchange_prior['session'];
	$_REQUEST = $this->oidc_exchange_prior['request'];

	if ($this->oidc_exchange_prior['options_cli'] === null) {
		unset($config[OPTIONS_CLI]);
	} else {
		$config[OPTIONS_CLI] = $this->oidc_exchange_prior['options_cli'];
	}

	@unlink($GLOBALS['oidc_exchange_log']);
});

test('a refused token exchange denies the login and logs why when debugging', function () {
	$provider = oidc_exchange_provider('on');
	oidc_exchange_callback($provider);

	$result = $provider->complete();

	expect($result->success)->toBeFalse()
		->and($_SESSION)->not->toHaveKey('sess_oidc_' . $provider->getId())
		->and(oidc_exchange_log())->toContain("AUTH OIDC: token exchange failed for provider 'Exchange OIDC': ");
});

test('a refused token exchange stays out of the log when debugging is off', function () {
	$provider = oidc_exchange_provider('');
	oidc_exchange_callback($provider);

	$result = $provider->complete();

	expect($result->success)->toBeFalse()
		->and(oidc_exchange_log())->not->toContain('token exchange failed');
});
