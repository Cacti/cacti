<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration harness for CactiSecureHeaders::emitHeaders().
 *
 * Unlike the Unit tests (which exercise buildCspPolicy() in process), these
 * boot the PHP built-in web server pointing at tests/integration/fixtures/
 * and curl it back. That way header() side-effects are observable and we
 * can prove end-to-end that the wire format matches the spec for every
 * value of the content_security_policy_script flag.
 *
 * Each test spins up its own server on a free port and tears it down in
 * a finally-style cleanup so tests never share process state.
 */

/* ---- harness helpers ---- */

function _csp_find_free_port() {
	$sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	if ($sock === false) {
		throw new RuntimeException("could not allocate free port: {$errstr}");
	}
	$name = stream_socket_get_name($sock, false);
	fclose($sock);
	$parts = explode(':', $name);
	return (int) end($parts);
}

/**
 * Launch php -S on a free port with the given CSP_TEST_MODE, wait for it
 * to accept connections, and return an array describing the running server.
 *
 * Caller is responsible for calling _csp_stop_server() on the returned
 * descriptor.
 */
function _csp_start_server($mode, $alternates = '') {
	$port    = _csp_find_free_port();
	$docroot = realpath(__DIR__ . '/fixtures');
	$router  = 'csp_fixture.php';

	/* php -S <addr> -t <docroot> <router>. Router is resolved relative to
	 * docroot. Using the router form means every path served by the server
	 * runs csp_fixture.php, which is what we want. */
	$php_bin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
	$cmd = escapeshellarg($php_bin)
		. ' -S 127.0.0.1:' . (int)$port
		. ' -t ' . escapeshellarg($docroot)
		. ' ' . escapeshellarg($router);

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	$env = $_ENV;
	$env['CSP_TEST_MODE']       = (string)$mode;
	$env['CSP_TEST_ALTERNATES'] = (string)$alternates;

	$proc = proc_open($cmd, $descriptors, $pipes, $docroot, $env);
	if (!is_resource($proc)) {
		throw new RuntimeException('proc_open failed for php -S');
	}

	/* Wait up to ~3s for the port to come alive. */
	$deadline = microtime(true) + 3.0;
	$ready = false;
	while (microtime(true) < $deadline) {
		$probe = @stream_socket_client('tcp://127.0.0.1:' . (int)$port, $errno, $errstr, 0.2);
		if ($probe !== false) {
			fclose($probe);
			$ready = true;
			break;
		}
		usleep(50000);
	}

	if (!$ready) {
		proc_terminate($proc, 9);
		proc_close($proc);
		throw new RuntimeException('php -S failed to start on port ' . $port);
	}

	return array(
		'proc'  => $proc,
		'port'  => $port,
		'pipes' => $pipes,
	);
}

function _csp_stop_server($server) {
	if (!is_array($server) || empty($server['proc'])) {
		return;
	}
	if (is_resource($server['proc'])) {
		proc_terminate($server['proc'], 15);
		/* Drain pipes so proc_close() can return promptly. */
		foreach ($server['pipes'] as $p) {
			if (is_resource($p)) {
				fclose($p);
			}
		}
		proc_close($server['proc']);
	}
}

/**
 * GET the fixture URL and return both headers and body from a single
 * request. The built-in PHP server spawns a fresh process per request, so
 * a separate HEAD + GET would produce two different nonces. Returns
 * array('status' => int, 'headers' => array<string lowerName, string value[]>,
 * 'body' => string). Multi-valued headers are kept as arrays so duplicate-
 * header asserts work.
 */
function _csp_fetch($port) {
	$url = 'http://127.0.0.1:' . (int)$port . '/';
	$ch  = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	$raw = curl_exec($ch);
	if ($raw === false) {
		$err = curl_error($ch);
		curl_close($ch);
		throw new RuntimeException('curl failed: ' . $err);
	}
	$status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$header_size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	curl_close($ch);

	$raw_headers = substr($raw, 0, $header_size);
	$body        = substr($raw, $header_size);

	$headers = array();
	$lines = preg_split('/\r?\n/', trim($raw_headers));
	foreach ($lines as $line) {
		if ($line === '' || strpos($line, 'HTTP/') === 0) {
			continue;
		}
		$pos = strpos($line, ':');
		if ($pos === false) {
			continue;
		}
		$name  = strtolower(trim(substr($line, 0, $pos)));
		$value = trim(substr($line, $pos + 1));
		if (!isset($headers[$name])) {
			$headers[$name] = array();
		}
		$headers[$name][] = $value;
	}

	return array('status' => $status, 'headers' => $headers, 'body' => (string)$body);
}

/* ---- tests ---- */

test('empty mode emits Content-Security-Policy with unsafe-inline', function () {
	$server = _csp_start_server('');
	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['status'])->toBe(200);
		expect($resp['headers'])->toHaveKey('content-security-policy');
		$csp = $resp['headers']['content-security-policy'][0];
		expect($csp)->toContain("'unsafe-inline'");
		expect($resp['headers'])->not->toHaveKey('content-security-policy-report-only');
	} finally {
		_csp_stop_server($server);
	}
});

test('unsafe-eval mode adds unsafe-eval to script-src', function () {
	$server = _csp_start_server('unsafe-eval');
	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];
		$start = strpos($csp, 'script-src');
		$end   = strpos($csp, ';', $start);
		$scriptSrc = substr($csp, $start, $end - $start);
		expect($scriptSrc)->toContain("'unsafe-eval'");
		expect($scriptSrc)->toContain("'unsafe-inline'");
	} finally {
		_csp_stop_server($server);
	}
});

test('nonce mode replaces unsafe-inline with nonce in script-src and style-src', function () {
	$server = _csp_start_server('nonce');
	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['headers'])->toHaveKey('content-security-policy');
		$csp = $resp['headers']['content-security-policy'][0];

		$sStart = strpos($csp, 'script-src');
		$sEnd   = strpos($csp, ';', $sStart);
		$scriptSrc = substr($csp, $sStart, $sEnd - $sStart);
		expect(preg_match("/'nonce-[A-Za-z0-9_-]+'/", $scriptSrc))->toBe(1);
		expect($scriptSrc)->not->toContain("'unsafe-inline'");

		$yStart = strpos($csp, 'style-src');
		$yEnd   = strpos($csp, ';', $yStart);
		$styleSrc = substr($csp, $yStart, $yEnd - $yStart);
		expect($styleSrc)->toContain("'unsafe-inline'");
		expect($styleSrc)->not->toContain("'nonce-'");
	} finally {
		_csp_stop_server($server);
	}
});

test('nonce-report mode emits Content-Security-Policy-Report-Only', function () {
	$server = _csp_start_server('nonce-report');
	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['headers'])->toHaveKey('content-security-policy-report-only');
		expect($resp['headers'])->not->toHaveKey('content-security-policy');
		$csp = $resp['headers']['content-security-policy-report-only'][0];
		expect($csp)->toMatch("/'nonce-[A-Za-z0-9_-]+'/");
	} finally {
		_csp_stop_server($server);
	}
});

test('nonce matches between header and rendered inline script tag', function () {
	/* Header and body must be fetched in a single request: php -S spawns
	 * a fresh process per request, which reseeds getNonce(). Separate
	 * HEAD + GET would give two different nonces and mask a real bug. */
	$server = _csp_start_server('nonce');
	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];
		if (!preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $csp, $m)) {
			throw new RuntimeException('no nonce token in CSP header');
		}
		$header_nonce = $m[1];

		/* Fixture renders: <script nonce="<token>">'test';</script> */
		if (!preg_match('/<script\s+nonce="([A-Za-z0-9_-]+)"/', $resp['body'], $bm)) {
			throw new RuntimeException('no nonce attribute in rendered script tag');
		}
		$dom_nonce = $bm[1];

		expect($dom_nonce)->toBe($header_nonce);
	} finally {
		_csp_stop_server($server);
	}
});

test('X-Content-Type-Options nosniff emitted in all modes', function () {
	foreach (array('', 'unsafe-eval', 'nonce', 'nonce-report') as $mode) {
		$server = _csp_start_server($mode);
		try {
			$resp = _csp_fetch($server['port']);
			expect($resp['headers'])->toHaveKey('x-content-type-options');
			expect($resp['headers']['x-content-type-options'][0])->toBe('nosniff');
		} finally {
			_csp_stop_server($server);
		}
	}
});

test('X-Frame-Options SAMEORIGIN emitted in all modes', function () {
	foreach (array('', 'unsafe-eval', 'nonce', 'nonce-report') as $mode) {
		$server = _csp_start_server($mode);
		try {
			$resp = _csp_fetch($server['port']);
			expect($resp['headers'])->toHaveKey('x-frame-options');
			expect($resp['headers']['x-frame-options'][0])->toBe('SAMEORIGIN');
		} finally {
			_csp_stop_server($server);
		}
	}
});

test('calling emitHeaders twice does not emit duplicate headers', function () {
	/* Fixture invokes emitHeaders() twice on purpose. The second call must
	 * be a no-op (headers_sent() is false on the first call, true only
	 * after output begins; PHP's header list is keyed by name for non-
	 * replace=false callers, and this helper uses replace=true, which is
	 * the default). Assert each security header appears exactly once. */
	$server = _csp_start_server('nonce');
	try {
		$resp = _csp_fetch($server['port']);
		$single = array(
			'content-security-policy',
			'x-frame-options',
			'x-content-type-options',
			'referrer-policy',
			'permissions-policy',
			'cross-origin-opener-policy',
			'cross-origin-resource-policy',
			'cache-control',
		);
		foreach ($single as $name) {
			if (isset($resp['headers'][$name])) {
				expect(count($resp['headers'][$name]))
					->toBe(1, "duplicate {$name} header");
			}
		}
	} finally {
		_csp_stop_server($server);
	}
});
