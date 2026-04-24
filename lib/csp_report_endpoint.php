<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Functions.php provides cacti_log() without dragging in the full session/
 * auth stack that include/global.php would start. This endpoint is
 * intentionally unauthenticated, so we want the lightest possible bootstrap. */
require_once(__DIR__ . '/functions.php');

/**
 * Strip log-injection characters from a CSP report field before interpolation.
 * A crafted report body with embedded CR/LF would forge extra log lines.
 */
function csp_report_sanitize_field($v) {
	if (!is_string($v)) {
		return '(unknown)';
	}
	/* Strip CR/LF and other C0 controls; truncate to 256 chars for log sanity. */
	$v = preg_replace('/[\x00-\x1f\x7f]/', ' ', $v);
	if (strlen($v) > 256) {
		$v = substr($v, 0, 253) . '...';
	}
	return $v;
}

/**
 * Validate and normalise a raw CSP report POST.
 *
 * Kept as a pure function so it can be exercised in unit tests without
 * an actual HTTP request context. Returns a result array rather than
 * throwing so the caller controls the HTTP response code.
 *
 * @param array  $headers  Associative array of request headers (CONTENT_TYPE key).
 * @param string $body     Raw POST body.
 * @param int    $maxBytes Upper bound enforced before JSON parse; 16 KB is
 *                         large enough for any real report but small enough
 *                         to prevent the process from buffering an attacker-
 *                         supplied multi-megabyte payload into memory.
 * @return array           ['ok' => bool, 'reason' => string, 'summary' => string]
 */
function csp_report_validate_payload(array $headers, $body, $maxBytes) {
	$ct = isset($headers['CONTENT_TYPE']) ? strtolower(trim($headers['CONTENT_TYPE'])) : '';

	if (strpos($ct, 'application/csp-report') !== 0 && strpos($ct, 'application/json') !== 0) {
		return array('ok' => false, 'reason' => 'Unsupported Content-Type', 'summary' => '');
	}

	$len = strlen($body);

	if ($len === 0) {
		return array('ok' => false, 'reason' => 'Empty body', 'summary' => '');
	}

	if ($len > $maxBytes) {
		return array('ok' => false, 'reason' => 'Body exceeds size limit', 'summary' => '');
	}

	$decoded = json_decode($body, true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		return array('ok' => false, 'reason' => 'Invalid JSON', 'summary' => '');
	}

	if (!is_array($decoded)) {
		return array('ok' => false, 'reason' => 'JSON root must be an object', 'summary' => '');
	}

	/* Browsers send either the legacy format:
	 *   {"csp-report": {"violated-directive": ..., ...}}
	 * or the Reporting API (report-to) format:
	 *   {"type": "csp-violation", "body": {"effectiveDirective": ..., ...}}
	 * Normalise both into a flat map of the violation fields. */
	if (isset($decoded['csp-report']) && is_array($decoded['csp-report'])) {
		$report = $decoded['csp-report'];
		/* Sanitize before interpolation: a crafted report can embed CR/LF to forge log lines. */
		$directive  = csp_report_sanitize_field(isset($report['violated-directive'])  ? $report['violated-directive']  : '(unknown)');
		$blockedUri = csp_report_sanitize_field(isset($report['blocked-uri'])         ? $report['blocked-uri']          : '(unknown)');
		$docUri     = csp_report_sanitize_field(isset($report['document-uri'])        ? $report['document-uri']         : '(unknown)');
	} elseif (isset($decoded['body']) && is_array($decoded['body'])) {
		$report = $decoded['body'];
		/* report-to uses camelCase keys; same log-injection risk applies. */
		$directive  = csp_report_sanitize_field(isset($report['effectiveDirective'])  ? $report['effectiveDirective']   : '(unknown)');
		$blockedUri = csp_report_sanitize_field(isset($report['blockedURL'])          ? $report['blockedURL']            : '(unknown)');
		$docUri     = csp_report_sanitize_field(isset($report['documentURL'])         ? $report['documentURL']           : '(unknown)');
	} else {
		return array('ok' => false, 'reason' => 'Missing csp-report or body field', 'summary' => '');
	}

	$summary = 'CSP violation: ' . $directive . ' blocked ' . $blockedUri . ' on ' . $docUri;

	return array('ok' => true, 'reason' => '', 'summary' => $summary);

}

/* --- Entry point ---------------------------------------------------------- */

if (defined('CACTI_CSP_REPORT_TEST_MODE')) {
	return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

/* $_SERVER['CONTENT_TYPE'] is set by most SAPI implementations; the
 * HTTP_CONTENT_TYPE fallback covers CGI setups that omit the prefix. */
$contentType = '';
if (isset($_SERVER['CONTENT_TYPE'])) {
	$contentType = $_SERVER['CONTENT_TYPE'];
} elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
	$contentType = $_SERVER['HTTP_CONTENT_TYPE'];
}

/* Cap the read at 16 KB; file_get_contents does not honour a length argument
 * for php://input on all SAPIs, so we slice after the fact. */
$rawBody = (string) file_get_contents('php://input', false, null, 0, 16385);
$rawBody = substr($rawBody, 0, 16384);

$result = csp_report_validate_payload(
	array('CONTENT_TYPE' => $contentType),
	$rawBody,
	16384
);

if ($result['ok']) {
	cacti_log($result['summary'], false, 'CSP-REPORT');
	http_response_code(204);
} else {
	http_response_code(400);
	header('Content-Type: text/plain; charset=UTF-8');
	/* Emit the rejection reason as plain text. No HTML output here because
	 * this endpoint is called by the browser's report mechanism, not rendered
	 * in a page, and we don't want to introduce XSS surface. */
	print $result['reason'];
}
