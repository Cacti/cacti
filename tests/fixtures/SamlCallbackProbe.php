<?php

// Runs in its own process so protocol helpers and session state stay isolated.
$root = dirname(__DIR__, 2);
$mode = $argv[1];
require $root . '/include/vendor/autoload.php';
define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_URL', '/');
define('CACTI_WEB', true);
define('CACTI_CSRF_SECRET', '');
define('POLLER_VERBOSITY_DEBUG', 5);
define('AUTH_METHOD_PROVIDERS', 4);
define('PROVIDER_TYPE_LDAP', 1);
define('PROVIDER_TYPE_AD', 2);
define('PROVIDER_TYPE_SAML2', 3);
define('PROVIDER_TYPE_OPENID', 4);

if ($mode !== 'other-page') {
	define('CACTI_SAML_POST_CALLBACK', true);
}
function read_config_option($name) {
	return $name === 'base_url' ? 'https://cacti.fixture' : ($GLOBALS['mode'] === 'inactive' ? 0 : 4);
}
function gfrv($name) {
	return 1001;
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}
function cacti_log(...$args) {
}
function cacti_decrypt_secret($value) {
	return $value;
}
function __($text) {
	return $text;
}
function db_fetch_row_prepared(...$args) {
	return $GLOBALS['mode'] === 'disabled' ? [] : $GLOBALS['providerRow'];
}

session_start();
$_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'POST', 'HTTPS' => 'on', 'SERVER_PORT' => 443, 'HTTP_HOST' => 'cacti.fixture', 'SERVER_NAME' => 'cacti.fixture', 'SCRIPT_NAME' => '/login_sso.php', 'REQUEST_URI' => '/login_sso.php?action=acs&realm=1001']);
$key     = openssl_pkey_new(['private_key_bits' => 2048]);
$csr     = openssl_csr_new(['commonName' => 'fixture'], $key, ['digest_alg' => 'sha256']);
$cert    = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
openssl_pkey_export($key, $privateKey);
openssl_x509_export($cert, $certificate);
$providerRow = ['id' => 1, 'type' => $mode === 'openid' ? 4 : 3, 'name' => 'Fixture', 'enabled' => 'on', 'parameters' => json_encode(['idp_entity_id' => 'https://idp.fixture', 'idp_sso_url' => 'https://idp.fixture/sso', 'idp_slo_url' => 'https://idp.fixture/slo', 'idp_x509cert' => $certificate, 'want_assertions_signed' => true])];
$now         = gmdate('Y-m-d\TH:i:s\Z');
$before      = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
$after       = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
$acs         = 'https://cacti.fixture/login_sso.php?action=acs&amp;realm=1001';
$audience    = 'https://cacti.fixture/login_sso.php?action=metadata&amp;realm=1001';
$assertion   = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_fixture_assertion" Version="2.0" IssueInstant="' . $now . '"><saml:Issuer>https://idp.fixture</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">alice@example.test</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData InResponseTo="_fixture_request" Recipient="' . $acs . '" NotOnOrAfter="' . $after . '"/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="' . $before . '" NotOnOrAfter="' . $after . '"><saml:AudienceRestriction><saml:Audience>' . $audience . '</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="' . $now . '" SessionIndex="_fixture_session"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement></saml:Assertion>';

if ($mode !== 'unsigned') {
	$assertion = OneLogin\Saml2\Utils::addSign($assertion, $privateKey, $certificate);
	// addSign() targets protocol messages; assertions put Issuer before Signature.
	$document = new DOMDocument();
	$document->loadXML($assertion);
	$issuer = $document->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0);
	$document->documentElement->insertBefore($issuer, $document->documentElement->firstChild);
	$assertion = $document->saveXML();
}
$assertion = preg_replace('/^<\?xml[^>]*>\s*/', '', $assertion);

if ($mode === 'tampered') {
	$assertion = str_replace('alice@example.test', 'mallory@example.test', $assertion);
}
$response = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_fixture_response" Version="2.0" IssueInstant="' . $now . '" Destination="' . $acs . '" InResponseTo="_fixture_request"><saml:Issuer>https://idp.fixture</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>' . $assertion . '</samlp:Response>';
$_POST    = ['SAMLResponse' => base64_encode($response)];

if ($mode !== 'missing') {
	$_SESSION['sess_saml_request_1'] = ['request_id' => $mode === 'mismatch' ? '_other_request' : '_fixture_request', 'remember' => false];
}
require $root . '/include/csrf.php';
$exempt   = csrf_is_saml_callback();
$csrf     = csrf_check(false);
$login    = false;
$username = '';
$replay   = false;

if ($exempt && !in_array($mode, ['openid', 'disabled', 'inactive', 'other-page'], true)) {
	$provider = new Cacti\Auth\SamlLoginProvider($providerRow);
	$result   = $provider->complete();
	$login    = $result->success;
	$username = $result->username;
	$replay   = $provider->complete()->success;
}
print json_encode(['exempt' => $exempt, 'csrf' => $csrf, 'login' => $login, 'username' => $username, 'replay' => $replay]);
