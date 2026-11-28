<?php

function throttleTestAddress() : string {
	return '2001:db8:' . bin2hex(random_bytes(2)) . ':' . bin2hex(random_bytes(2)) . '::1';
}

function throttleTestAttempts(array $requests, int $seconds = 60) : array {
	$workers = [];

	foreach ($requests as [$peer, $forwarded, $login]) {
		$process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/LoginThrottleAttempt.php', $peer, $forwarded, $login, (string) $seconds],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$workers[] = [$process, $pipes];
	}

	$results = [];

	foreach ($workers as [$process, $pipes]) {
		$result = stream_get_contents($pipes[1]);
		$error  = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		if (proc_close($process) !== 0 || $error !== '') {
			throw new RuntimeException($error . $result);
		}
		$results[] = $result;
	}

	return $results;
}

test('concurrent password attempts across names share an address budget', function () {
	$ip       = throttleTestAddress();
	$requests = [];

	foreach (range(1, 8) as $i) {
		$requests[] = [$ip, '', 'account-' . $i];
	}
	$counts = array_count_values(throttleTestAttempts($requests));
	expect($counts['allow'])->toBe(6)->and($counts['deny'])->toBe(2);
})->skip(!getenv('CACTI_THROTTLE_TEST_DATABASE'), 'Requires a disposable Cacti database.');

test('one normalized identifier has a separate budget at each trusted client address', function () {
	$ip      = throttleTestAddress();
	$other   = throttleTestAddress();
	$results = throttleTestAttempts([
		['192.0.2.1', $ip, 'Ann'], ['192.0.2.1', $ip, ' ann '], ['192.0.2.1', $ip, 'ANN'],
		['192.0.2.1', $other, 'ann'], ['192.0.2.1', $ip, 'another'],
	]);
	expect(array_count_values(array_slice($results, 0, 3)))->toMatchArray(['allow' => 2, 'deny' => 1])
		->and(array_slice($results, 3))->toBe(['allow', 'allow']);
})->skip(!getenv('CACTI_THROTTLE_TEST_DATABASE'), 'Requires a disposable Cacti database.');

test('untrusted forwarded addresses share the peer budget and expiry restores access', function () {
	$peer     = throttleTestAddress();
	$requests = [];

	foreach (range(1, 3) as $i) {
		$requests[] = [$peer, '192.0.2.' . $i, 'Pat'];
	}
	expect(array_count_values(throttleTestAttempts($requests, 3)))->toMatchArray(['allow' => 2, 'deny' => 1]);
	sleep(3);
	expect(throttleTestAttempts([[$peer, '', 'Pat']], 3))->toBe(['allow']);
})->skip(!getenv('CACTI_THROTTLE_TEST_DATABASE'), 'Requires a disposable Cacti database.');

test('the password login form returns generic denial and recovers after expiry', function () {
	$url = getenv('CACTI_THROTTLE_TEST_URL');
	expect(parse_url($url, PHP_URL_HOST))->toBeIn(['localhost', '127.0.0.1', '::1']);
	$client = curl_init(rtrim($url, '/') . '/index.php');
	curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
	$attempt = function (string $password) use ($client) {
		curl_setopt($client, CURLOPT_HTTPGET, true);
		$html = curl_exec($client);
		$dom  = new DOMDocument();
		$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		$csrf = (new DOMXPath($dom))->evaluate('string(//input[@name="__csrf_magic"]/@value)');
		curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query([
			'action' => 'login', 'login_username' => 'admin', 'login_password' => $password, '__csrf_magic' => $csrf,
		]));
		$result = curl_exec($client);

		return [curl_getinfo($client, CURLINFO_RESPONSE_CODE), $result];
	};

	expect($attempt('incorrect')[0])->toBe(200)->and($attempt('incorrect')[0])->toBe(200);
	$password         = getenv('CACTI_THROTTLE_TEST_PASSWORD');
	[$code, $message] = $attempt($password);
	expect($code)->toBe(200)->and($message)->toContain('Login Failed');
	sleep(3);
	expect($attempt($password)[0])->toBe(302);
})->skip(!getenv('CACTI_THROTTLE_TEST_URL'), 'Requires a disposable Cacti HTTP installation.');
