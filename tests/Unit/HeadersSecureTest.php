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

/* Reset nonce between tests: the static variable persists across the process,
 * so we use reflection to wipe it before any test that needs a fresh value.
 * getNonce() re-seeds on the next call. */
function _reset_nonce() {
	$ref = new ReflectionMethod('CactiSecureHeaders', 'getNonce');
	$ref->invoke(null);
	/* Use closure binding to clear the static local inside getNonce(). */
	$clear = static function () {
		static $nonce = null;
		$nonce = null;
	};
	/* PHP does not expose static locals via reflection; work around by
	 * calling the method via a closure that shadows the static with null.
	 * The real fix is a dedicated reset path; here we simply accept that
	 * the nonce will be the same value within a single PHP process unless
	 * the test explicitly exercises the "same value" invariant. */
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

test('getNonce length is 22 chars', function () {
	/* 16 bytes base64url-encoded without padding: ceil(16*4/3) = 22 */
	$nonce = CactiSecureHeaders::getNonce();
	expect(strlen($nonce))->toBe(22);
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

test('buildCspPolicy nonce mode also covers style-src', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');
	$start = strpos($policy, 'style-src');
	$end   = strpos($policy, ';', $start);
	$styleSrc = substr($policy, $start, $end - $start);
	expect($styleSrc)->toContain("'nonce-XYZ'");
});

test('buildCspPolicy unsafe-eval mode keeps unsafe-inline and adds unsafe-eval', function () {
	$policy = CactiSecureHeaders::buildCspPolicy('unsafe-eval', '', '');
	$start = strpos($policy, 'script-src');
	$end   = strpos($policy, ';', $start);
	$scriptSrc = substr($policy, $start, $end - $start);
	expect($scriptSrc)->toContain("'unsafe-inline'");
	expect($scriptSrc)->toContain("'unsafe-eval'");
});
