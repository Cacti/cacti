<?php

// Use a disposable loopback installation with remember-me and MFA enabled and a
// dedicated local account whose authenticator has not been enrolled.
test('a remembered browser can view graphs and must confirm before changing MFA', function () {
	$url = rtrim(getenv('CACTI_MFA_TEST_URL'), '/');
	expect(parse_url($url, PHP_URL_HOST))->toBeIn(['localhost', '127.0.0.1', '::1']);
	$client = curl_init();
	curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
	$request = function ($path, $post = null) use ($url, $client) {
		curl_setopt($client, CURLOPT_URL, $url . '/' . $path);

		if ($post === null) {
			curl_setopt($client, CURLOPT_HTTPGET, true);
		} else {
			curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($post));
		}
		$html = curl_exec($client);

		if ($html === false) {
			throw new RuntimeException(curl_error($client));
		}

		return [curl_getinfo($client, CURLINFO_RESPONSE_CODE), $html];
	};
	$token = function ($html) {
		$dom = new DOMDocument();
		$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

		return (new DOMXPath($dom))->evaluate('string(//input[@name="__csrf_magic"]/@value)');
	};

	[, $form] = $request('index.php');
	$password = getenv('CACTI_MFA_TEST_PASSWORD');
	[$status] = $request('index.php', [
		'action'      => 'login', 'login_username' => getenv('CACTI_MFA_TEST_USER'), 'login_password' => $password,
		'remember_me' => 'on', '__csrf_magic' => $token($form),
	]);
	expect($status)->toBe(302);
	[, $form] = $request('auth_profile.php?tab=2fa');
	expect($form)->toMatch('/id=[\'"]tfa_enabled[\'"]/');
	$cookies = curl_getinfo($client, CURLINFO_COOKIELIST);
	curl_setopt($client, CURLOPT_COOKIELIST, 'ALL');

	foreach ($cookies as $cookie) {
		if (explode("\t", $cookie)[5] === 'cacti_remembers') {
			curl_setopt($client, CURLOPT_COOKIELIST, $cookie);
		}
	}

	[, $graphs] = $request('graph_view.php');
	expect($graphs)->not->toContain('name="login_password"');
	[, $form] = $request('auth_profile.php?tab=2fa');
	expect($form)->toContain('name="confirmation_password"');
	$csrf = ['__csrf_magic' => $token($form)];

	foreach (['enable_2fa', 'disable_2fa', 'verify_2fa'] as $action) {
		expect($request('auth_profile.php?action=' . $action, $csrf)[0])->toBe(403);
	}

	$challenge = $csrf + ['action' => 'confirm_mfa_identity'];
	expect($request('auth_profile.php', $challenge + ['cancel_confirmation' => 1])[0])->toBe(302)
		->and($request('auth_profile.php', $challenge + ['confirmation_password' => 'incorrect'])[0])->toBe(403)
		->and($request('auth_profile.php?action=disable_2fa', $csrf)[0])->toBe(403)
		->and($request('auth_profile.php', $challenge + ['confirmation_password' => $password])[0])->toBe(302);
	expect($request('auth_profile.php?tab=2fa')[1])->toMatch('/id=[\'"]tfa_enabled[\'"]/');
})->skip(!getenv('CACTI_MFA_TEST_URL'), 'Requires a disposable Cacti HTTP installation.');
