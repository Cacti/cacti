<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../lib/headers_secure.php';

/* Stub read_config_option() so tests can control flag values via
 * $GLOBALS['__test_config_options'] without a database connection. */
if (!function_exists('read_config_option')) {
	function read_config_option($key) {
		if (isset($GLOBALS['__test_config_options'][$key])) {
			return $GLOBALS['__test_config_options'][$key];
		}
		return '';
	}
}

/* Passthrough stub so CactiSecureHeaders::emitHeaders() does not fail
 * when html_escape() is called for alternate-source sanitisation. */
if (!function_exists('html_escape')) {
	function html_escape($str) {
		return $str;
	}
}

/* ---- tests ---- */

test('getNonce returns same value within request', function () {
	$first  = CactiSecureHeaders::getNonce();
	$second = CactiSecureHeaders::getNonce();
	expect($first)->toBe($second);
});

test('getNonce output is base64url only', function () {
	$nonce = CactiSecureHeaders::getNonce();
	expect($nonce)->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('getNonce length is 24 chars', function () {
	/* 18 bytes base64url-encoded without padding: ceil(18*4/3) = 24 */
	$nonce = CactiSecureHeaders::getNonce();
	expect(strlen($nonce))->toBe(24);
});

test('getNonceAttribute wraps in nonce="..."', function () {
	$attr = CactiSecureHeaders::getNonceAttribute();
	expect($attr)->toContain('nonce="');
	expect(substr($attr, -1))->toBe('"');
	$nonce = CactiSecureHeaders::getNonce();
	expect($attr)->toContain($nonce);
});

test('getCspMode empty flag returns empty string', function () {
	$GLOBALS['__test_config_options']['content_security_policy_script'] = '';
	expect(CactiSecureHeaders::getCspMode())->toBe('');
});

test('getCspMode unknown value returns empty string', function () {
	$GLOBALS['__test_config_options']['content_security_policy_script'] = 'garbage';
	expect(CactiSecureHeaders::getCspMode())->toBe('');
});

test('getCspMode returns nonce when flag set', function () {
	$GLOBALS['__test_config_options']['content_security_policy_script'] = 'nonce';
	expect(CactiSecureHeaders::getCspMode())->toBe('nonce');
});

test('isNonceMode true for nonce and nonce-report', function () {
	$GLOBALS['__test_config_options']['content_security_policy_script'] = 'nonce';
	expect(CactiSecureHeaders::isNonceMode())->toBeTrue();

	$GLOBALS['__test_config_options']['content_security_policy_script'] = 'nonce-report';
	expect(CactiSecureHeaders::isNonceMode())->toBeTrue();
});

test('buildCspPolicy empty mode has unsafe-inline in script-src', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('', '', '');
	expect($policy)->toContain('script-src');
	expect($policy)->toContain("'unsafe-inline'");
});

test('buildCspPolicy nonce mode has nonce token and no unsafe-inline in script-src', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');
	expect($policy)->toContain("'nonce-XYZ'");
	/* Extract the script-src directive span and verify unsafe-inline absent. */
	$start = strpos($policy, 'script-src');
	$end   = strpos($policy, ';', $start);
	$scriptSrc = substr($policy, $start, $end - $start);
	expect($scriptSrc)->not->toContain("'unsafe-inline'");
});

test('buildCspPolicy nonce mode includes strict-dynamic and unsafe-eval for jQuery compat', function () {
	/* Without strict-dynamic, jQuery .html() / .append() injected scripts fail
	 * because they do not carry the nonce. Without unsafe-eval, jQuery's
	 * globalEval and new Function() paths fail. Both are required for
	 * Cacti core + plugins (thold, monitor, etc) to render under nonce mode. */
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');
	$start  = strpos($policy, 'script-src');
	$end    = strpos($policy, ';', $start);
	$scriptSrc = substr($policy, $start, $end - $start);
	expect($scriptSrc)->toContain("'strict-dynamic'");
	expect($scriptSrc)->toContain("'unsafe-eval'");
});

test('buildCspPolicy nonce mode style-src keeps unsafe-inline for jQuery .css() / inline style attrs', function () {
	/* jQuery .css() and the dozens of legacy inline style="" attributes
	 * across Cacti pages need unsafe-inline. Style XSS is a much narrower
	 * attack surface than script XSS, so the trade-off is intentional. */
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');
	$start  = strpos($policy, 'style-src');
	$end    = strpos($policy, ';', $start);
	$styleSrc = substr($policy, $start, $end - $start);
	expect($styleSrc)->toContain("'unsafe-inline'");
	/* Style-src no longer carries the nonce in nonce mode; the nonce is
	 * scoped to script-src only. */
	expect($styleSrc)->not->toContain("'nonce-XYZ'");
});

test('buildCspPolicy unsafe-eval mode keeps unsafe-inline and adds unsafe-eval', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('unsafe-eval', '', '');
	$start = strpos($policy, 'script-src');
	$end   = strpos($policy, ';', $start);
	$scriptSrc = substr($policy, $start, $end - $start);
	expect($scriptSrc)->toContain("'unsafe-inline'");
	expect($scriptSrc)->toContain("'unsafe-eval'");
});

test('buildCspPolicy nonce-report with report_uri includes report-uri directive', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('nonce-report', 'ABC', '', '/cacti/csp_report.php');
	expect($policy)->toContain('report-uri /cacti/csp_report.php;');
});

test('buildCspPolicy nonce enforce mode also appends report-uri when provided', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'ABC', '', '/cacti/csp_report.php');
	expect($policy)->toContain('report-uri /cacti/csp_report.php;');
});

test('buildCspPolicy does not append report-uri for unsafe-eval mode', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('unsafe-eval', '', '', '/cacti/csp_report.php');
	expect($policy)->not->toContain('report-uri');
});

test('buildCspPolicy does not append report-uri when empty string passed', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('nonce-report', 'ABC', '', '');
	expect($policy)->not->toContain('report-uri');
});
