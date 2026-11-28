<?php

use Cacti\Auth\FreshAuthentication;

beforeEach(function () {
	unset($_SESSION['sess_fresh_auth'], $_SESSION['sess_mfa_challenge']);
	$_SESSION[SESS_USER_ID] = 21;
});

test('remembered, expired and fresh sessions retain distinct permissions', function () {
	expect(FreshAuthentication::isFresh(21))->toBeFalse();
	FreshAuthentication::record(21, time() - FreshAuthentication::WINDOW);
	expect(FreshAuthentication::isFresh(21))->toBeFalse();
	FreshAuthentication::record(21, time());
	expect(FreshAuthentication::isFresh(21))->toBeTrue()->and(FreshAuthentication::isFresh(22))->toBeFalse();
});

test('provider challenge requires recent evidence for the original account and realm', function (int $user, int $realm, int $offset, bool $allowed) {
	$challenge = FreshAuthentication::begin(21, 1001);
	$time      = $_SESSION['sess_mfa_challenge']['start'] + $offset;
	expect(FreshAuthentication::complete($challenge, $user, $realm, $time))->toBe($allowed)
		->and(FreshAuthentication::isFresh(21))->toBe($allowed)
		->and(FreshAuthentication::complete($challenge, $user, $realm, $time))->toBeFalse();
})->with([[21, 1001, 0, true], [22, 1001, 0, false], [21, 1002, 0, false], [21, 1001, -1, false], [21, 1001, 300, false]]);

test('cancelled and superseded provider challenges cannot confer freshness', function () {
	$first = FreshAuthentication::begin(21, 1001);
	FreshAuthentication::begin(21, 1001);
	expect(FreshAuthentication::complete($first, 21, 1001, time()))->toBeFalse();
	$next = FreshAuthentication::begin(21, 1001);
	unset($_SESSION['sess_mfa_challenge']);
	expect(FreshAuthentication::complete($next, 21, 1001, time()))->toBeFalse()->and(FreshAuthentication::isFresh(21))->toBeFalse();
});

test('provider requests ask for an interactive challenge only when confirming MFA', function (string $provider, string $mode) {
	$probe = dirname(__DIR__, 3) . '/fixtures/MfaProviderRequest.php';
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' ' . escapeshellarg($provider) . ' ' . escapeshellarg($mode), $output, $status);
	expect($status)->toBe(0)->and($output)->toBe(['ok']);
})->with(['oidc', 'saml'])->with(['login', 'challenge']);

test('SAML freshness requires a matching validated assertion and authentication instant', function () {
	$xml = '<p:Response xmlns:p="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:a="urn:oasis:names:tc:SAML:2.0:assertion"><a:Assertion ID="approved"><a:AuthnStatement AuthnInstant="invalid"/></a:Assertion></p:Response>';
	expect(\Cacti\Auth\SamlLoginProvider::authenticationTime(null, null))->toBe(0)
		->and(\Cacti\Auth\SamlLoginProvider::authenticationTime($xml, 'different'))->toBe(0)
		->and(\Cacti\Auth\SamlLoginProvider::authenticationTime($xml, 'approved'))->toBe(0)
		->and(\Cacti\Auth\SamlLoginProvider::authenticationTime(str_replace('invalid', '2026-01-02T00:00:00Z', $xml), 'approved'))->toBe(strtotime('2026-01-02T00:00:00Z'));
});

test('the SSO callback binds confirmation to the active account before any login transition', function (string $mode) {
	$probe = dirname(__DIR__, 3) . '/fixtures/MfaSsoCallback.php';
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' ' . escapeshellarg($mode), $output, $status);
	expect($status)->toBe(0)->and($output)->toBe(['ok']);
})->with(['success', 'wrong-account', 'stale', 'cancelled', 'failed']);
