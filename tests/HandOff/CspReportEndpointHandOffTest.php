<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Hand-off tests describe the behavioural contract between the CSP nonce
 * migration on 1.2.x and whatever maintains it next. They run under Pest
 * alongside Unit/ but live under HandOff/ so they can be enumerated as the
 * acceptance set when the branch is handed back to upstream maintainers.
 *
 * Pest.php currently only discovers Unit/ and integration/, so this file
 * registers itself explicitly. Doing it here keeps the hand-off suite
 * self-contained and avoids touching shared bootstrap. */
uses(PHPUnit\Framework\TestCase::class)->in(__DIR__);

/* Prevent the entry-point block in csp_report_endpoint.php from running
 * when require_once pulls the file into the test process. */
if (!defined('CACTI_CSP_REPORT_TEST_MODE')) {
	define('CACTI_CSP_REPORT_TEST_MODE', 1);
}

/* The endpoint and the header builder both call cacti_log() / read_config_option()
 * when present. Stub them so neither test path needs a live DB or session. */
if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $facility = 'CACTI') {
		$GLOBALS['__handoff_log_lines'][] = (string)$message;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($key) {
		if (isset($GLOBALS['__test_config_options'][$key])) {
			return $GLOBALS['__test_config_options'][$key];
		}
		return '';
	}
}

if (!function_exists('html_escape')) {
	function html_escape($str) {
		return $str;
	}
}

require_once __DIR__ . '/../../lib/csp_report_endpoint.php';
require_once __DIR__ . '/../../lib/headers_secure.php';

/* ---- helpers ---------------------------------------------------------- */

function _ho_headers($ct) {
	return array('CONTENT_TYPE' => $ct);
}

/* defaultReportUri() is private. Reaching it via reflection keeps the
 * production surface unchanged while letting the hand-off contract pin
 * the $url_path -> report-uri mapping that downstream installs depend on.
 * csp_report_log() routes through cacti_log() when $config is bootstrapped;
 * the test forces that path by setting $GLOBALS['config']['base_path']. */
function _ho_default_report_uri() {
	$ref = new ReflectionMethod('CactiSecureHeaders', 'defaultReportUri');
	$ref->setAccessible(true);
	return $ref->invoke(null);
}

/* ---- POST -> validator -> log line ------------------------------------ */

beforeEach(function () {
	$GLOBALS['__handoff_log_lines'] = array();
	/* Force csp_report_log() through the cacti_log() branch so the test
	 * captures the log line instead of writing to PHP's error_log. */
	$GLOBALS['config']['base_path'] = __DIR__;
});

test('valid POST produces single log line naming directive and source', function () {
	$body = json_encode(array(
		'csp-report' => array(
			'violated-directive' => 'script-src',
			'blocked-uri'        => 'https://evil.example/x.js',
			'document-uri'       => 'https://app.example/dashboard',
		),
	));

	$result = csp_report_validate_payload(
		_ho_headers('application/csp-report'),
		$body,
		16384
	);

	expect($result['ok'])->toBeTrue();

	csp_report_log($result['summary']);

	expect($GLOBALS['__handoff_log_lines'])->toHaveCount(1);
	$line = $GLOBALS['__handoff_log_lines'][0];
	expect($line)->toContain('script-src');
	expect($line)->toContain('https://evil.example/x.js');
	/* No embedded newline survives sanitisation -> single log line. */
	expect(substr_count($line, "\n"))->toBe(0);
	expect(substr_count($line, "\r"))->toBe(0);
});

test('CRLF in report fields is stripped before logging', function () {
	$body = json_encode(array(
		'csp-report' => array(
			'violated-directive' => "script-src\r\nINJECTED admin login success",
			'blocked-uri'        => "inline\nFAKE second line",
			'document-uri'       => "https://app.example/p\r\nfoo",
		),
	));

	$result = csp_report_validate_payload(
		_ho_headers('application/csp-report'),
		$body,
		16384
	);

	expect($result['ok'])->toBeTrue();
	csp_report_log($result['summary']);

	expect($GLOBALS['__handoff_log_lines'])->toHaveCount(1);
	$line = $GLOBALS['__handoff_log_lines'][0];
	expect(strpos($line, "\r"))->toBeFalse();
	expect(strpos($line, "\n"))->toBeFalse();
});

/* ---- 16 KB body cap --------------------------------------------------- */

test('body over 16 KB is rejected before JSON parse', function () {
	/* 16385 bytes of payload inside a valid-looking envelope: parser would
	 * accept it if reached. The hand-off contract requires the size guard
	 * to fire first so a megabyte-sized attacker payload never lands in
	 * json_decode(). The endpoint's SAPI adapter maps this to a 4xx
	 * response (current code emits 400; the hand-off intent is 413 for
	 * the size case so operators can distinguish "too big" from "malformed").
	 * The contract pinned here is "rejected before parse" since the pure
	 * validator is the portable surface. */
	$payload = str_repeat('a', 16385);
	$body    = '{"csp-report":{"violated-directive":"' . $payload . '"}}';

	$result = csp_report_validate_payload(
		_ho_headers('application/csp-report'),
		$body,
		16384
	);

	expect($result['ok'])->toBeFalse();
	expect(strtolower($result['reason']))->toContain('size');
	expect($result['summary'])->toBe('');
});

/* ---- Content-Type allowlist ------------------------------------------ */

test('content-type application/csp-report is accepted', function () {
	$body = '{"csp-report":{"violated-directive":"script-src"}}';
	$r = csp_report_validate_payload(_ho_headers('application/csp-report'), $body, 16384);
	expect($r['ok'])->toBeTrue();
});

test('content-type application/json is accepted', function () {
	$body = json_encode(array(
		'type' => 'csp-violation',
		'body' => array('effectiveDirective' => 'script-src'),
	));
	$r = csp_report_validate_payload(_ho_headers('application/json'), $body, 16384);
	expect($r['ok'])->toBeTrue();
});

test('content-type text/plain is accepted (some browsers send this)', function () {
	/* Hand-off contract: a small number of mobile browsers POST CSP reports
	 * with text/plain. The validator must treat it like the JSON family and
	 * try to decode the body. This test will be red against any future
	 * implementation that drops text/plain. */
	$body = '{"csp-report":{"violated-directive":"script-src"}}';
	$r = csp_report_validate_payload(_ho_headers('text/plain'), $body, 16384);
	expect($r['ok'])->toBeTrue();
});

test('unknown content-type is rejected', function () {
	$body = '{"csp-report":{"violated-directive":"script-src"}}';
	$r = csp_report_validate_payload(_ho_headers('application/x-www-form-urlencoded'), $body, 16384);
	expect($r['ok'])->toBeFalse();
	expect(strtolower($r['reason']))->toContain('content-type');
});

/* ---- $url_path -> defaultReportUri() --------------------------------- */

test('defaultReportUri honours url_path "/" as web root', function () {
	$GLOBALS['url_path'] = '/';
	expect(_ho_default_report_uri())->toBe('/csp_report.php');
});

test('defaultReportUri honours url_path "/cacti/" with trailing slash', function () {
	$GLOBALS['url_path'] = '/cacti/';
	expect(_ho_default_report_uri())->toBe('/cacti/csp_report.php');
});

test('defaultReportUri honours url_path "/sub/path" without trailing slash', function () {
	$GLOBALS['url_path'] = '/sub/path';
	expect(_ho_default_report_uri())->toBe('/sub/path/csp_report.php');
});

test('defaultReportUri falls back to /cacti/csp_report.php when url_path unset', function () {
	unset($GLOBALS['url_path']);
	expect(_ho_default_report_uri())->toBe('/cacti/csp_report.php');
});

/* ---- emitHeaders() -> CSP header bytes -------------------------------- */

test('nonce mode CSP header has strict-dynamic, unsafe-eval, and the nonce in script-src', function () {
	/* Build the policy directly with a fixed nonce so the assertion is
	 * byte-exact rather than dependent on per-process CSPRNG output. The
	 * branch under test is buildCspPolicy(); emitHeaders() is a thin
	 * adapter that header()s the same string. */
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');

	$start = strpos($policy, 'script-src');
	$end   = strpos($policy, ';', $start);
	$scriptSrc = substr($policy, $start, $end - $start);

	expect($scriptSrc)->toContain("'strict-dynamic'");
	expect($scriptSrc)->toContain("'unsafe-eval'");
	expect($scriptSrc)->toContain("'nonce-XYZ'");
});

test('nonce mode style-src uses unsafe-inline rather than the nonce', function () {
	/* jQuery .css() and legacy inline style="" require unsafe-inline; the
	 * nonce is intentionally scoped to script-src only. Hand-off contract
	 * pins this so a future "tighten everything" pass does not silently
	 * add 'nonce-...' to style-src and break the UI. */
	$policy = CactiSecureHeaders::buildCspPolicy('nonce', 'XYZ', '');

	$start = strpos($policy, 'style-src');
	$end   = strpos($policy, ';', $start);
	$styleSrc = substr($policy, $start, $end - $start);

	expect($styleSrc)->toContain("'unsafe-inline'");
	expect($styleSrc)->not->toContain("'nonce-XYZ'");
});
