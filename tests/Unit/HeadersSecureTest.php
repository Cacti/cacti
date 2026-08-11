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

// --- None mode: legacy unsafe-eval value, most permissive ---

test('none mode adds unsafe-eval alongside unsafe-inline', function () {
	$src = CactiSecureHeaders::buildScriptSrc('unsafe-eval', 'IGNORED', '');

	expect($src)->toContain("'unsafe-eval'");
	expect($src)->toContain("'unsafe-inline'");
	expect($src)->not->toContain('nonce-');
});

// --- Nonce mode ---

test('nonce mode emits the nonce with strict-dynamic', function () {
	$src = CactiSecureHeaders::buildScriptSrc('nonce', 'abc123', '');

	expect($src)->toContain("'nonce-abc123'");
	expect($src)->toContain("'strict-dynamic'");
	// strict-dynamic needs unsafe-eval for jQuery globalEval; unsafe-inline is
	// the pre-CSP3 fallback that supporting browsers ignore once a nonce is set.
	expect($src)->toContain("'unsafe-eval'");
	expect($src)->toContain("'unsafe-inline'");
});

// --- nonce generation ---

test('nonce is base64url with no padding and stable within a request', function () {
	$a = CactiSecureHeaders::getNonce();
	$b = CactiSecureHeaders::getNonce();

	expect($a)->toBe($b);
	expect($a)->toMatch('/^[A-Za-z0-9_-]{20,}$/');
	expect($a)->not->toContain('=');
});

// --- report-uri suffix ---

test('report-uri suffix is attached only in nonce mode with a uri', function () {
	expect(CactiSecureHeaders::buildReportUriDirective('nonce', '/cacti/csp_report.php'))
		->toBe(' report-uri /cacti/csp_report.php;');

	expect(CactiSecureHeaders::buildReportUriDirective('', '/cacti/csp_report.php'))->toBe('');
	expect(CactiSecureHeaders::buildReportUriDirective('unsafe-eval', '/cacti/csp_report.php'))->toBe('');
	expect(CactiSecureHeaders::buildReportUriDirective('nonce', ''))->toBe('');
});

// --- nonce attribute reflects the active mode ---

test('nonce attribute is empty outside nonce mode', function () {
	// read_config_option is either absent or the test stub returning false,
	// so getCspMode() resolves to the default and no attribute is emitted.
	expect(CactiSecureHeaders::getNonceAttribute())->toBe('');
	expect(CactiSecureHeaders::isNonceMode())->toBeFalse();
});
