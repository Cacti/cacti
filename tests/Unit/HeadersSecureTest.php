<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Tests for CactiSecureHeaders, the CSP script-src helper behind the
 * "Inline JavaScript Protection" setting. The buildScriptSrc()/
 * buildReportUriDirective() methods are pure, so mode branching is exercised
 * directly without a config or request context.
 */

require_once dirname(__DIR__, 2) . '/lib/headers_secure.php';

// --- default (HTMX) mode: byte-identical to prior releases ---

test('default mode keeps unsafe-inline and no eval', function () {
	$src = CactiSecureHeaders::buildScriptSrc('', 'IGNORED', '*.cdn.example');

	expect($src)->toBe("script-src 'self' 'unsafe-inline' *.cdn.example");
	expect($src)->not->toContain('nonce-');
	expect($src)->not->toContain('unsafe-eval');
});

test('stored CSP modes are normalized to supported values', function () {
	expect(CactiSecureHeaders::normalizeCspMode('nonce'))->toBe('nonce');
	expect(CactiSecureHeaders::normalizeCspMode('nonce-enforce'))->toBe('nonce-enforce');
	expect(CactiSecureHeaders::normalizeCspMode('unsafe-eval'))->toBe('unsafe-eval');
	expect(CactiSecureHeaders::normalizeCspMode('unknown'))->toBe('');
	expect(CactiSecureHeaders::normalizeCspMode(false))->toBe('');
});

test('nonce migration keeps the enforced policy compatible', function () {
	$src = CactiSecureHeaders::buildEnforcedScriptSrc('nonce', '*.cdn.example');

	expect($src)->toBe("script-src 'self' 'unsafe-inline' *.cdn.example");
	expect($src)->not->toContain('nonce-');
	expect($src)->not->toContain('strict-dynamic');
});

test('nonce enforcement builds a strict enforced policy', function () {
	$nonce = 'abcdefghijklmnopqrstuvwx';
	$src   = CactiSecureHeaders::buildEnforcedScriptSrc('nonce-enforce', '', $nonce);

	expect($src)->toContain("'nonce-$nonce'");
	expect($src)->toContain("'strict-dynamic'");
});

// --- None mode: legacy unsafe-eval value, most permissive ---

test('none mode adds unsafe-eval alongside unsafe-inline', function () {
	$src = CactiSecureHeaders::buildScriptSrc('unsafe-eval', 'IGNORED', '');

	expect($src)->toContain("'unsafe-eval'");
	expect($src)->toContain("'unsafe-inline'");
	expect($src)->not->toContain('nonce-');
});

// --- Nonce mode ---

test('nonce mode emits the nonce with strict-dynamic', function () {
	$src = CactiSecureHeaders::buildScriptSrc('nonce', 'abcdefghijklmnopqrstuvwx', '');

	expect($src)->toContain("'nonce-abcdefghijklmnopqrstuvwx'");
	expect($src)->toContain("'strict-dynamic'");
	// strict-dynamic needs unsafe-eval for jQuery globalEval; unsafe-inline is
	// the pre-CSP3 fallback that supporting browsers ignore once a nonce is set.
	expect($src)->toContain("'unsafe-eval'");
	expect($src)->toContain("'unsafe-inline'");
});

test('nonce builder rejects malformed authorization tokens', function (string $nonce) {
	CactiSecureHeaders::buildScriptSrc('nonce', $nonce, '');
})->with([
	'empty'       => '',
	'too short'   => 'abc123',
	'quoted'      => 'abcdefghijklmnopqrstu"wx',
	'directive'   => 'abcdefghijklmnop;script',
])->throws(InvalidArgumentException::class);

test('report-only script source is emitted only for nonce migration', function () {
	$nonce = 'abcdefghijklmnopqrstuvwx';

	expect(CactiSecureHeaders::buildReportOnlyScriptSrc('nonce', $nonce, ''))
		->toContain("'nonce-$nonce'")
		->toContain("'strict-dynamic'");
	expect(CactiSecureHeaders::buildReportOnlyScriptSrc('', '', ''))->toBe('');
	expect(CactiSecureHeaders::buildReportOnlyScriptSrc('unsafe-eval', '', ''))->toBe('');
});

test('nonce attributes are emitted only with a valid nonce', function () {
	$nonce = 'abcdefghijklmnopqrstuvwx';

	expect(CactiSecureHeaders::buildNonceAttribute('nonce', $nonce))
		->toBe('nonce="' . $nonce . '"');
	expect(CactiSecureHeaders::buildNonceAttribute('nonce-enforce', $nonce))
		->toBe('nonce="' . $nonce . '"');
	expect(CactiSecureHeaders::buildNonceAttribute('', ''))->toBe('');
	expect(CactiSecureHeaders::buildNonceAttribute('unsafe-eval', ''))->toBe('');
});

test('nonce attributes reject malformed authorization tokens', function () {
	CactiSecureHeaders::buildNonceAttribute('nonce', 'not-a-valid-nonce');
})->throws(InvalidArgumentException::class);

// --- nonce generation ---

test('nonce is base64url with no padding and stable within a request', function () {
	$a = CactiSecureHeaders::getNonce();
	$b = CactiSecureHeaders::getNonce();

	expect($a)->toBe($b);
	expect($a)->toMatch('/^[A-Za-z0-9_-]{20,}$/');
	expect($a)->not->toContain('=');
});

test('nonce generation has no predictable fallback', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/headers_secure.php');

	expect($source)->not->toBeFalse()
		->and($source)->not->toContain('uniqid(')
		->and($source)->not->toContain('mt_rand(')
		->and($source)->not->toContain('openssl_random_pseudo_bytes(')
		->and($source)->toContain('throw new \\RuntimeException');
});

// --- report-uri suffix ---

test('report-uri suffix is attached only in nonce mode with a uri', function () {
	expect(CactiSecureHeaders::buildReportUriDirective('nonce', '/cacti/csp_report.php'))
		->toBe(' report-uri /cacti/csp_report.php;');
	expect(CactiSecureHeaders::buildReportUriDirective('nonce', 'https://reports.example/csp'))
		->toBe(' report-uri https://reports.example/csp;');
	expect(CactiSecureHeaders::buildReportUriDirective('nonce-enforce', '/cacti/csp_report.php'))
		->toBe(' report-uri /cacti/csp_report.php;');

	expect(CactiSecureHeaders::buildReportUriDirective('', '/cacti/csp_report.php'))->toBe('');
	expect(CactiSecureHeaders::buildReportUriDirective('unsafe-eval', '/cacti/csp_report.php'))->toBe('');
	expect(CactiSecureHeaders::buildReportUriDirective('nonce', ''))->toBe('');
});

test('report-uri builder rejects malformed or unsafe endpoints', function (string $uri) {
	CactiSecureHeaders::buildReportUriDirective('nonce', $uri);
})->with([
	'protocol relative' => '//reports.example/csp',
	'credentials'       => 'https://user:pass@example.com/csp',
	'query'             => 'https://example.com/csp?token=secret',
	'fragment'          => 'https://example.com/csp#fragment',
	'non-http scheme'   => 'data:text/plain,csp',
	'invalid hostname'  => 'https://not_a_host.example/csp',
	'directive break'   => '/csp; script-src *',
])->throws(InvalidArgumentException::class);

test('report endpoint resolution accepts safe configuration or uses the bundled endpoint', function () {
	expect(CactiSecureHeaders::resolveReportUri('/custom/csp', '/cacti'))
		->toBe('/custom/csp');
	expect(CactiSecureHeaders::resolveReportUri('https://reports.example/csp', '/cacti'))
		->toBe('https://reports.example/csp');
	expect(CactiSecureHeaders::resolveReportUri('javascript:alert(1)', '/cacti'))
		->toBe('/cacti/csp_report.php');
	expect(CactiSecureHeaders::resolveReportUri(false, ''))
		->toBe('/csp_report.php');
});

// --- nonce attribute reflects the active mode ---

test('nonce attribute is empty outside nonce mode', function () {
	// read_config_option is either absent or the test stub returning false,
	// so getCspMode() resolves to the default and no attribute is emitted.
	expect(CactiSecureHeaders::getNonceAttribute())->toBe('');
	expect(CactiSecureHeaders::isNonceMode())->toBeFalse();
});

test('runtime policy wrappers use compatible defaults without configuration', function () {
	expect(CactiSecureHeaders::scriptSrc(''))
		->toBe("script-src 'self' 'unsafe-inline' ");
	expect(CactiSecureHeaders::reportOnlyScriptSrc(''))->toBe('');
	expect(CactiSecureHeaders::reportUriDirective())->toBe('');
});

test('shared script emitters attach the request nonce', function () {
	$root = dirname(__DIR__, 2);

	foreach ([
		'include/auth.php',
		'include/global_session.php',
		'lib/auth.php',
		'lib/functions.php',
		'lib/htmx.php',
	] as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->not->toBeFalse();
		expect($source)->toContain('CactiSecureHeaders::getNonceAttribute()');
	}
});

test('shared navigation logos use links instead of inline event handlers', function () {
	$root = dirname(__DIR__, 2);

	foreach (['include/top_header.php', 'include/top_general_header.php'] as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->not->toBeFalse();
		expect($source)->toContain("class='cactiLogo' href='");
		expect($source)->not->toMatch('/\bon[a-z]+\s*=/i');
	}
});
