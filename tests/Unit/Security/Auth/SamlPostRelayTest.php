<?php

use Cacti\Auth\SamlPostRelay;

require_once dirname(__DIR__, 4) . '/lib/Auth/SamlPostRelay.php';

test('only SAML POST callbacks with a scalar assertion and valid realm are eligible', function () {
	foreach (['acs', 'callback'] as $action) {
		expect(SamlPostRelay::isCallbackPost('POST', ['action' => $action, 'realm' => '1001'], ['SAMLResponse' => 'fixture']))->toBeTrue();
	}

	foreach ([
		['GET', ['action' => 'acs', 'realm' => 1001], ['SAMLResponse' => 'fixture']],
		['POST', ['action' => 'login', 'realm' => 1001], ['SAMLResponse' => 'fixture']],
		['POST', ['action' => 'acs', 'realm' => 1], ['SAMLResponse' => 'fixture']],
		['POST', ['action' => ['acs'], 'realm' => 1001], ['SAMLResponse' => 'fixture']],
		['POST', ['action' => 'acs', 'realm' => [1001]], ['SAMLResponse' => 'fixture']],
		['POST', ['action' => 'acs', 'realm' => 1001], ['SAMLResponse' => ['fixture']]],
		['POST', ['action' => 'acs', 'realm' => 1001], ['SAMLResponse' => '']],
	] as [$method, $query, $post]) {
		expect(SamlPostRelay::isCallbackPost($method, $query, $post))->toBeFalse();
	}
});

test('relay forwards only protocol fields and escapes HTML without changing their values', function () {
	$assertion = 'fixture"<&';
	$html      = SamlPostRelay::render(['action' => 'acs', 'realm' => '1001'], ['SAMLResponse' => $assertion, 'RelayState' => '<fixture>', '__csrf_magic' => 'do-not-forward', 'cacti_saml_relay' => '0'], 'fixture-nonce');
	$document  = new DOMDocument();
	$document->loadHTML($html);
	$fields = [];

	foreach ($document->getElementsByTagName('input') as $input) {
		if ($input->getAttribute('type') === 'hidden') {
			$fields[$input->getAttribute('name')] = $input->getAttribute('value');
		}
	}
	expect($fields)->toBe(['SAMLResponse' => $assertion, 'cacti_saml_relay' => '1', 'RelayState' => '<fixture>']);
	expect($document->getElementsByTagName('form')->item(0)->getAttribute('action'))->toBe('login_sso.php?action=acs&realm=1001');
	expect($document->getElementsByTagName('script')->item(0)->getAttribute('nonce'))->toBe('fixture-nonce');
	expect($html)->not->toContain('do-not-forward');
});

test('the relay runs before the authentication bootstrap opens a session', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/login_sso.php');
	expect(strpos($source, 'SamlPostRelay::relay('))->toBeLessThan(strpos($source, "require_once('./include/auth.php')"));
});
