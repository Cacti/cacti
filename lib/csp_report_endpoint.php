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

/* This endpoint is unauthenticated and reachable pre-bootstrap, so it
 * deliberately avoids loading lib/functions.php or include/global.php.
 * Loading functions.php alone surfaced undefined-$config warnings in
 * targeted PHP checks. csp_report_log() below writes to PHP's default
 * error log, falling through to cacti_log() only if a parent caller
 * already set up $config and that function is available. */

/**
 * Self-contained logger for the CSP report endpoint. Prefers cacti_log()
 * when a parent caller has already bootstrapped $config; otherwise writes
 * to PHP's default error log. Either way the report lands somewhere an
 * operator can read it without depending on the Cacti DB.
 */
function csp_report_log($message) {
	$message = preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $message);

	if (function_exists('cacti_log') && isset($GLOBALS['config']['base_path'])) {
		@cacti_log($message, false, 'CSP-REPORT');

		return;
	}

	error_log('CACTI CSP-REPORT: ' . $message);
}

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

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

/* Read one byte past the cap and stop. The length argument to
 * file_get_contents() is not honoured for php://input on every SAPI, so a
 * large body could still be buffered whole before anything rejected it; a
 * bounded read loop caps it regardless. Keeping the extra byte is what lets
 * the size check below fire, since slicing back to the cap left the length
 * unable to exceed the number it was compared against and an oversized body
 * arrived as invalid JSON once truncation had broken it. */
$rawBody = '';
$input   = @fopen('php://input', 'rb');

if ($input !== false) {
	while (!feof($input) && strlen($rawBody) <= 16384) {
		$chunk = fread($input, 8192);

		if ($chunk === false || $chunk === '') {
			break;
		}

		$rawBody .= $chunk;
	}

	fclose($input);
}

$rawBody = substr($rawBody, 0, 16385);

$result = csp_report_validate_payload(
	array('CONTENT_TYPE' => $contentType),
	$rawBody,
	16384
);

/* Per-IP / per-minute rate cap. The endpoint is unauthenticated by design
 * (the browser fires reports without credentials) so an attacker can flood
 * cacti_log / error_log unless we drop excess events. We always return the
 * normal HTTP status so probing cannot infer the cap. */
function csp_report_should_log() : bool {
	$cap = 30;

	/* Keep the counters in a directory this process owns rather than beside
	   everything else in the temp dir. The old path was sys_get_temp_dir()
	   plus a hash of the address and minute, which any local user could work
	   out: pre-creating a victim's file at the cap silenced that address, and
	   leaving a symlink there gave fopen() somewhere else to write. */
	$dir = sys_get_temp_dir() . '/cacti_csp';

	if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
		return false;
	}

	/* Refuse a directory we do not own, or one swapped for a link, and drop the
	   report rather than logging it uncapped. The two failures are not equal:
	   logging without a cap lets anyone on the network fill the disk through an
	   endpoint that needs no credentials, and a local user can arrange this
	   condition deliberately to remove the cap. Dropping reports loses
	   telemetry until the directory is repaired, which is the smaller harm. */
	if (is_link($dir)) {
		return false;
	}

	if (function_exists('posix_getuid')) {
		$owner = @fileowner($dir);

		if ($owner === false || $owner !== posix_getuid()) {
			return false;
		}
	}

	csp_report_prune_buckets($dir);

	$ip     = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$bucket = $dir . '/' . hash('sha256', $ip . '|' . gmdate('YmdHi'));

	$fh = @fopen($bucket, 'c+');

	if ($fh === false) {
		return false;
	}

	$logged = false;

	if (flock($fh, LOCK_EX)) {
		$count = (int) fread($fh, 16);

		if ($count < $cap) {
			rewind($fh);
			ftruncate($fh, 0);
			fwrite($fh, (string) ($count + 1));
			$logged = true;
		}

		flock($fh, LOCK_UN);
	}

	fclose($fh);

	return $logged;
}

/**
 * Drop counter files from earlier minutes. Nothing removed them before, so a
 * host taking reports accumulated one file per address per minute until it ran
 * out of inodes. Pruning here keeps the directory to roughly the number of
 * addresses reporting right now.
 */
function csp_report_prune_buckets($dir) : void {
	/* One request in twenty does the sweep. Every request paying for it would
	   cost a directory listing per report for no benefit. */
	if (function_exists('random_int')) {
		if (random_int(1, 20) !== 1) {
			return;
		}
	} elseif (mt_rand(1, 20) !== 1) {
		return;
	}

	$entries = @scandir($dir);

	if ($entries === false) {
		return;
	}

	$cutoff = time() - 120;

	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (!is_file($path) || is_link($path)) {
			continue;
		}

		$mtime = @filemtime($path);

		if ($mtime !== false && $mtime < $cutoff) {
			@unlink($path);
		}
	}
}

if ($result['ok']) {
	if (csp_report_should_log()) {
		csp_report_log($result['summary']);
	}
	http_response_code(204);
} else {
	http_response_code(400);
	header('Content-Type: text/plain; charset=UTF-8');
	/* Emit the rejection reason as plain text. No HTML output here because
	 * this endpoint is called by the browser's report mechanism, not rendered
	 * in a page, and we don't want to introduce XSS surface. */
	print $result['reason'];
}
