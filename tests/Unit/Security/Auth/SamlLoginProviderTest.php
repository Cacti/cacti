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

/*
 * Runs SamlLoginProvider in this process against a locally generated IdP
 * key, so the provider's settings and assertion handling are measured where
 * SamlCallbackValidationTest only observes them from a child process.
 */

use Cacti\Auth\SamlLoginProvider;

/** Create a disposable IdP key pair and self-signed certificate. */
function saml_provider_test_identity(): array {
	$key  = openssl_pkey_new(['private_key_bits' => 2048]);
	$csr  = openssl_csr_new(['commonName' => 'fixture'], $key, ['digest_alg' => 'sha256']);
	$cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
	openssl_pkey_export($key, $privateKey);
	openssl_x509_export($cert, $certificate);

	return [$privateKey, $certificate];
}

/** Build a provider row for realm 1001 trusting the given IdP certificate. */
function saml_provider_test_provider(string $certificate, array $parameters = []): SamlLoginProvider {
	return new SamlLoginProvider([
		'id'         => 1,
		'type'       => PROVIDER_TYPE_SAML2,
		'name'       => 'Fixture SAML',
		'enabled'    => 'on',
		'parameters' => json_encode($parameters + [
			'idp_entity_id'          => 'https://idp.fixture',
			'idp_sso_url'            => 'https://idp.fixture/sso',
			'idp_slo_url'            => 'https://idp.fixture/slo',
			'idp_x509cert'           => $certificate,
			'want_assertions_signed' => true,
		]),
	]);
}

/** Return a base64 SAML Response carrying an assertion signed by the IdP key. */
function saml_provider_test_response(string $privateKey, string $certificate, string $nameId): string {
	$now       = gmdate('Y-m-d\TH:i:s\Z');
	$before    = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
	$after     = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
	$acs       = 'https://cacti.fixture/login_sso.php?action=acs&amp;realm=1001';
	$audience  = 'https://cacti.fixture/login_sso.php?action=metadata&amp;realm=1001';
	$assertion = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_fixture_assertion" Version="2.0" IssueInstant="' . $now . '"><saml:Issuer>https://idp.fixture</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">' . $nameId . '</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData InResponseTo="_fixture_request" Recipient="' . $acs . '" NotOnOrAfter="' . $after . '"/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="' . $before . '" NotOnOrAfter="' . $after . '"><saml:AudienceRestriction><saml:Audience>' . $audience . '</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="' . $now . '" SessionIndex="_fixture_session"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement></saml:Assertion>';
	$assertion = OneLogin\Saml2\Utils::addSign($assertion, $privateKey, $certificate);

	// addSign() targets protocol messages; assertions put Issuer before Signature.
	$document = new DOMDocument();
	$document->loadXML($assertion);
	$issuer = $document->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0);
	$document->documentElement->insertBefore($issuer, $document->documentElement->firstChild);
	$assertion = preg_replace('/^<\?xml[^>]*>\s*/', '', $document->saveXML());

	return base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_fixture_response" Version="2.0" IssueInstant="' . $now . '" Destination="' . $acs . '" InResponseTo="_fixture_request"><saml:Issuer>https://idp.fixture</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>' . $assertion . '</samlp:Response>');
}

beforeEach(function () {
	global $config;

	$this->samlState                    = [$_SERVER, $_POST, $_SESSION ?? [], $config[OPTIONS_CLI]['base_url'] ?? null];
	$config[OPTIONS_CLI]['base_url']    = 'https://cacti.fixture';
	$_SERVER                            = array_merge($_SERVER, ['REQUEST_METHOD' => 'POST', 'HTTPS' => 'on', 'SERVER_PORT' => 443,
		'HTTP_HOST'                                                                  => 'cacti.fixture', 'SERVER_NAME' => 'cacti.fixture', 'SCRIPT_NAME' => '/login_sso.php',
		'REQUEST_URI'                                                                => '/login_sso.php?action=acs&realm=1001']);
	$_SESSION                           = [];
});

afterEach(function () {
	global $config;

	[$_SERVER, $_POST, $_SESSION, $baseUrl] = $this->samlState;

	if ($baseUrl === null) {
		unset($config[OPTIONS_CLI]['base_url']);
	} else {
		$config[OPTIONS_CLI]['base_url'] = $baseUrl;
	}
});

test('an assertion without the configured username claim falls back to its NameID', function () {
	[$privateKey, $certificate] = saml_provider_test_identity();
	$provider                   = saml_provider_test_provider($certificate, ['claim_username' => 'uid']);

	$_POST                           = ['SAMLResponse' => saml_provider_test_response($privateKey, $certificate, 'alice@example.test')];
	$_SESSION['sess_saml_request_1'] = ['request_id' => '_fixture_request', 'remember' => true];

	$result = $provider->complete();

	expect($result->success)->toBeTrue()
		->and($result->username)->toBe('alice@example.test')
		->and($result->rememberMe)->toBeTrue()
		->and($_SESSION)->not->toHaveKey('sess_saml_request_1');
});

test('published metadata derives the SP entity and NameID format from the base URL by default', function () {
	[, $certificate] = saml_provider_test_identity();
	$metadata        = new SimpleXMLElement(saml_provider_test_provider($certificate)->getMetadata());
	$metadata->registerXPathNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

	expect((string) $metadata['entityID'])->toBe('https://cacti.fixture/login_sso.php?action=metadata&realm=1001')
		->and((string) $metadata->xpath('//md:NameIDFormat')[0])->toBe('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress')
		->and((string) $metadata->xpath('//md:AssertionConsumerService/@Location')[0])->toBe('https://cacti.fixture/login_sso.php?action=acs&realm=1001');
});

test('published metadata keeps an administrator supplied SP entity and NameID format', function () {
	[, $certificate] = saml_provider_test_identity();
	$provider        = saml_provider_test_provider($certificate, ['sp_entity_id' => 'https://sp.fixture/entity', 'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified']);
	$metadata        = new SimpleXMLElement($provider->getMetadata());
	$metadata->registerXPathNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');

	expect((string) $metadata['entityID'])->toBe('https://sp.fixture/entity')
		->and((string) $metadata->xpath('//md:NameIDFormat')[0])->toBe('urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified');
});
