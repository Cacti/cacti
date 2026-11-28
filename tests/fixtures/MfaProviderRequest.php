<?php
// Exercise provider initiation in a fresh process. Capture the outbound
// redirect before the provider exits without contacting an identity provider.
namespace Cacti\Auth;

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/include/vendor/autoload.php';

function isrv(string $key) : bool {
	return false;
}

function header(string $value) : void {
	$query = [];
	parse_str(parse_url(substr($value, strlen('Location: ')), PHP_URL_QUERY), $query);
	$challenge = $GLOBALS['argv'][2] === 'challenge';

	if (isset($query['SAMLRequest'])) {
		$document = new \DOMDocument();
		$document->loadXML(gzinflate(base64_decode($query['SAMLRequest'], true)), LIBXML_NONET);
		$forced = $document->documentElement->getAttribute('ForceAuthn') === 'true';
		$saved  = $_SESSION['sess_saml_request_1'];

		if ($forced !== $challenge || $saved['request_id'] !== $document->documentElement->getAttribute('ID')) {
			throw new \RuntimeException('Unexpected SAML authentication request.');
		}
	} else {
		$saved = $_SESSION['sess_oidc_1'];

		if (($query['state'] ?? '') !== $saved['state'] || ($query['nonce'] ?? '') !== $saved['nonce']
			|| ($challenge && (($query['prompt'] ?? '') !== 'login' || ($query['max_age'] ?? '') !== '0'))
			|| (!$challenge && (isset($query['prompt']) || isset($query['max_age'])))) {
			throw new \RuntimeException('Unexpected OpenID Connect authorization request.');
		}
	}

	if ($saved['challenge'] !== ($challenge ? 'fixture' : '')) {
		throw new \RuntimeException('Incorrect challenge binding.');
	}
	print 'ok';
}

$row = ['id' => 1, 'type' => 4, 'name' => 'Test', 'enabled' => 'on', 'parameters' => '{}'];

if ($argv[1] === 'oidc') {
	$provider = new class($row) extends OpenIdLoginProvider {
		protected function discover() : array {
			return ['authorization_endpoint' => 'https://idp.example.test/authorize'];
		}

		protected function redirectUri() : string {
			return 'https://cacti.example.test/callback';
		}
	};
} else {
	$provider = new class($row) extends SamlLoginProvider {
		protected function buildSettings() : array {
			return [
				'strict' => true,
				'sp'     => [
					'entityId'                 => 'https://cacti.example.test',
					'assertionConsumerService' => ['url' => 'https://cacti.example.test/acs'],
				],
				'idp' => [
					'entityId'                 => 'https://idp.example.test',
					'certFingerprint'          => str_repeat('01', 32),
					'certFingerprintAlgorithm' => 'sha256',
					'singleSignOnService'      => ['url' => 'https://idp.example.test/login'],
				],
			];
		}
	};
}

if ($argv[2] === 'challenge') {
	$provider->challenge('fixture');
}
$provider->initiate();
