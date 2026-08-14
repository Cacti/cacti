<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * End-to-end harness for the Inline JavaScript Protection setting. Each test
 * boots the PHP built-in web server pointing at tests/integration/fixtures/
 * with a given CSP_TEST_MODE and reads the response back, so header() side
 * effects are observable and the wire format is proven for every mode:
 * HTMX (default), Nonce Migration (report only), Nonce Enforcement, and
 * None (unsafe-eval).
 *
 * Each server runs on its own free port and is torn down in a finally block
 * so tests never share process state.
 */

function _csp_find_free_port(): int {
	$sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

	if ($sock === false) {
		throw new RuntimeException("could not allocate free port: $errstr");
	}
	$name = stream_socket_get_name($sock, false);
	fclose($sock);
	$parts = explode(':', $name);

	return (int) end($parts);
}

function _csp_start_server($mode, $alternates = '', $reportUri = '', $enforce = false) {
	$port    = _csp_find_free_port();
	$docroot = realpath(__DIR__ . '/fixtures');
	$php_bin = defined('PHP_BINARY') ? PHP_BINARY : 'php';

	// Array form runs php -S directly, without a shell.
	$cmd = [$php_bin, '-S', '127.0.0.1:' . (int) $port, '-t', $docroot, 'csp_fixture.php'];

	// The built-in server logs every request to stderr. Send its stdout/stderr
	// to the null device so an undrained pipe can never fill and deadlock the
	// test (or leave the server blocked on a write).
	$null = defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['file', $null, 'a'],
		2 => ['file', $null, 'a'],
	];

	$env                        = $_ENV;
	$env['CSP_TEST_MODE']       = (string) $mode;
	$env['CSP_TEST_ALTERNATES'] = (string) $alternates;
	$env['CSP_TEST_REPORT_URI'] = (string) $reportUri;
	$env['CSP_TEST_ENFORCE']    = $enforce ? '1' : '0';

	$proc = proc_open($cmd, $descriptors, $pipes, $docroot, $env);

	if (!is_resource($proc)) {
		throw new RuntimeException('proc_open failed for php -S');
	}

	$deadline = microtime(true) + 3.0;
	$ready    = false;

	// The first probes fail with "connection refused" until the server binds.
	// That is expected, so swallow those warnings (the test runner would
	// otherwise flag the test as risky) rather than relying on the '@' operator,
	// which PHPUnit's error handler ignores.
	set_error_handler(static function () {
		return true;
	});

	try {
		while (microtime(true) < $deadline) {
			$probe = stream_socket_client('tcp://127.0.0.1:' . (int) $port, $errno, $errstr, 0.2);

			if ($probe !== false) {
				fclose($probe);
				$ready = true;

				break;
			}
			usleep(50000);
		}
	} finally {
		restore_error_handler();
	}

	if (!$ready) {
		proc_terminate($proc, 9);
		proc_close($proc);

		throw new RuntimeException('php -S failed to start on port ' . $port);
	}

	return ['proc' => $proc, 'port' => $port, 'pipes' => $pipes];
}

function _csp_stop_server($server): void {
	if (!is_array($server) || empty($server['proc']) || !is_resource($server['proc'])) {
		return;
	}
	proc_terminate($server['proc'], 15);

	foreach ($server['pipes'] as $p) {
		if (is_resource($p)) {
			fclose($p);
		}
	}
	proc_close($server['proc']);
}

/*
 * GET the fixture in a single request. The built-in server spawns a fresh
 * process per request, so a HEAD followed by a GET would produce two nonces;
 * one request keeps the header nonce and the body nonce in sync.
 */
function _csp_fetch($port): array {
	$ch = curl_init('http://127.0.0.1:' . (int) $port . '/');
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

	$body    = substr($raw, $header_size);
	$headers = [];

	foreach (preg_split('/\r?\n/', trim(substr($raw, 0, $header_size))) as $line) {
		if ($line === '' || strpos($line, 'HTTP/') === 0) {
			continue;
		}
		$pos = strpos($line, ':');

		if ($pos === false) {
			continue;
		}
		$name             = strtolower(trim(substr($line, 0, $pos)));
		$headers[$name][] = trim(substr($line, $pos + 1));
	}

	return ['status' => $status, 'headers' => $headers, 'body' => (string) $body];
}

// ---- default (HTMX) ----

test('HTMX default keeps unsafe-inline and adds no nonce or eval', function () {
	$server = _csp_start_server('');

	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['status'])->toBe(200);
		$csp = $resp['headers']['content-security-policy'][0];
		expect($csp)->toContain("script-src 'self' 'unsafe-inline'");
		expect($csp)->not->toContain('nonce-');
		expect($csp)->not->toContain("'unsafe-eval'");
		expect($csp)->not->toContain('report-uri');
		expect($resp['headers'])->not->toHaveKey('content-security-policy-report-only');
		// bootstrap script carries no nonce attribute outside nonce mode
		expect($resp['body'])->toContain("<script type='text/javascript' >");
	} finally {
		_csp_stop_server($server);
	}
});

// ---- Nonce ----

test('Nonce migration emits a report-only nonce that matches the served script tag', function () {
	$server = _csp_start_server('nonce');

	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];
		$ro   = $resp['headers']['content-security-policy-report-only'][0];

		expect($csp)->toContain("script-src 'self' 'unsafe-inline'");
		expect($csp)->not->toContain('nonce-');
		expect($csp)->not->toContain("'strict-dynamic'");
		expect($ro)->toContain("'strict-dynamic'");
		expect($ro)->toContain("'unsafe-eval'");
		expect($ro)->toContain('report-uri');

		// The report-only header nonce and body nonce must be identical. The
		// enforced compatibility policy leaves legacy scripts and handlers usable.
		expect($ro)->toMatch('/\'nonce-([A-Za-z0-9_-]+)\'/');
		preg_match('/\'nonce-([A-Za-z0-9_-]+)\'/', $ro, $hm);
		preg_match('/<script[^>]*nonce="([A-Za-z0-9_-]+)"/', $resp['body'], $bm);
		expect($bm[1] ?? 'no-body-nonce')->toBe($hm[1]);
		expect($resp['body'])->toContain('window.legacyInlineRan = true;');
		expect($resp['body'])->toContain("onclick='window.legacyHandlerRan = true;'");
	} finally {
		_csp_stop_server($server);
	}
});

test('Nonce migration is report-only until legacy script sites are converted', function () {
	$server = _csp_start_server('nonce');

	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['headers'])->toHaveKey('content-security-policy');
		expect($resp['headers'])->toHaveKey('content-security-policy-report-only');
	} finally {
		_csp_stop_server($server);
	}
});

test('Nonce enforcement selection remains report-only without the config gate', function () {
	$server = _csp_start_server('nonce-enforce');

	try {
		$resp = _csp_fetch($server['port']);
		expect($resp['headers']['content-security-policy'][0])
			->toContain("script-src 'self' 'unsafe-inline'")
			->not->toContain('nonce-');
		expect($resp['headers'])->toHaveKey('content-security-policy-report-only');
	} finally {
		_csp_stop_server($server);
	}
});

test('Nonce enforcement requires the config gate and uses the response nonce', function () {
	$server = _csp_start_server('nonce-enforce', '', '', true);

	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];

		expect($csp)->toContain("'strict-dynamic'");
		expect($csp)->toContain('report-uri');
		expect($resp['headers'])->not->toHaveKey('content-security-policy-report-only');

		preg_match('/\'nonce-([A-Za-z0-9_-]+)\'/', $csp, $hm);
		preg_match('/<script[^>]*nonce="([A-Za-z0-9_-]+)"/', $resp['body'], $bm);
		expect($bm[1] ?? 'no-body-nonce')->toBe($hm[1] ?? 'no-header-nonce');
	} finally {
		_csp_stop_server($server);
	}
});

// ---- None (legacy unsafe-eval) ----

test('None mode adds unsafe-eval alongside unsafe-inline and no nonce', function () {
	$server = _csp_start_server('unsafe-eval');

	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];
		expect($csp)->toContain("script-src 'self' 'unsafe-eval' 'unsafe-inline'");
		expect($csp)->not->toContain('nonce-');
		expect($csp)->not->toContain('report-uri');
		expect($resp['headers'])->not->toHaveKey('content-security-policy-report-only');
	} finally {
		_csp_stop_server($server);
	}
});

// ---- alternates flow into every directive ----

test('alternate sources appear in frame-ancestors and script-src', function () {
	$server = _csp_start_server('', '*.cdn.example');

	try {
		$resp = _csp_fetch($server['port']);
		$csp  = $resp['headers']['content-security-policy'][0];
		expect($csp)->toContain("frame-ancestors 'self' *.cdn.example");
		expect($csp)->toContain('*.cdn.example');
	} finally {
		_csp_stop_server($server);
	}
});
