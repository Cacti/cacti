<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Prevent the entry-point block from executing when the file is included
 * during testing. The guard in csp_report_endpoint.php checks for this
 * constant and returns before touching $_SERVER or php://input. */
define('CACTI_CSP_REPORT_TEST_MODE', 1);

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $facility = 'CACTI') {
		/* intentional no-op in test context */
	}
}

require_once __DIR__ . '/../../lib/csp_report_endpoint.php';

function _csp_headers($ct) {
	return array('CONTENT_TYPE' => $ct);
}

test('validator rejects oversize body', function () {
	$body   = str_repeat('a', 20000);
	$result = csp_report_validate_payload(_csp_headers('application/csp-report'), $body, 16384);

	expect($result['ok'])->toBeFalse();
	expect(strtolower($result['reason']))->toContain('size');
});

test('validator rejects wrong content type', function () {
	$result = csp_report_validate_payload(_csp_headers('text/plain'), '{"csp-report":{}}', 16384);

	expect($result['ok'])->toBeFalse();
	expect(strtolower($result['reason']))->toContain('content-type');
});

test('validator rejects malformed JSON', function () {
	$result = csp_report_validate_payload(_csp_headers('application/csp-report'), 'not json{', 16384);

	expect($result['ok'])->toBeFalse();
	expect(strtolower($result['reason']))->toContain('json');
});

test('validator accepts legacy csp-report format', function () {
	$body = json_encode(array(
		'csp-report' => array(
			'violated-directive' => 'script-src',
			'blocked-uri'        => 'inline',
			'document-uri'       => 'https://x/y',
		),
	));

	$result = csp_report_validate_payload(_csp_headers('application/csp-report'), $body, 16384);

	expect($result['ok'])->toBeTrue();
	expect($result['summary'])->toContain('script-src');
	expect($result['summary'])->toContain('inline');
	expect($result['summary'])->toContain('https://x/y');
});

test('validator accepts Reporting API body format', function () {
	$body = json_encode(array(
		'type' => 'csp-violation',
		'body' => array(
			'effectiveDirective' => 'script-src',
			'blockedURL'         => 'https://evil.example/x.js',
			'documentURL'        => 'https://app.example/page',
		),
	));

	$result = csp_report_validate_payload(_csp_headers('application/json'), $body, 16384);

	expect($result['ok'])->toBeTrue();
	expect($result['summary'])->toContain('script-src');
});

test('sanitizer strips CR/LF so a report cannot forge log lines', function () {
	$dirty = "script-src\r\nFORGED LOG LINE";
	$clean = csp_report_sanitize_field($dirty);

	expect($clean)->not->toContain("\r");
	expect($clean)->not->toContain("\n");
});

test('sanitizer truncates overlong fields', function () {
	$clean = csp_report_sanitize_field(str_repeat('a', 500));

	expect(strlen($clean))->toBeLessThanOrEqual(256);
});
