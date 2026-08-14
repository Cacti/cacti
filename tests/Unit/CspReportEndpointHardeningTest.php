<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * issue#7702. The endpoint read one byte past its cap and then sliced the
 * result back to the cap, so the size check compared a length against a number
 * it could never exceed and an oversized body arrived as invalid JSON. The
 * rate limiter kept one counter per address per minute in the temp dir under a
 * guessable name, removed none of them, and followed a symlink left in their
 * place.
 */

if (!defined('CACTI_CSP_REPORT_TEST_MODE')) {
	define('CACTI_CSP_REPORT_TEST_MODE', true);
}

require_once dirname(__DIR__, 2) . '/lib/csp_report_endpoint.php';

test('a body over the cap is refused as oversize, not as bad JSON', function () {
	$headers = ['CONTENT_TYPE' => 'application/csp-report'];
	$body    = str_repeat('A', 16385);

	$result = csp_report_validate_payload($headers, $body, 16384);

	expect($result['ok'])->toBeFalse()
		->and($result['reason'])->toBe('Body exceeds size limit');
});

test('a body at the cap is still measured, not truncated past the check', function () {
	$headers = ['CONTENT_TYPE' => 'application/csp-report'];
	$json    = json_encode(['csp-report' => ['violated-directive' => 'script-src']]);
	$padded  = str_pad($json, 16384, ' ');

	$result = csp_report_validate_payload($headers, $padded, 16384);

	expect(strlen($padded))->toBe(16384)
		->and($result['ok'])->toBeTrue();
});

test('the endpoint keeps the byte that proves the body was too long', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/csp_report_endpoint.php');

	// slicing back to the cap is what made the check unreachable
	expect($src)->not->toContain('$rawBody = substr($rawBody, 0, 16384);')
		->and($src)->toContain("file_get_contents('php://input', false, null, 0, 16385)");
});

test('the request method is guarded like the other server values', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/csp_report_endpoint.php');

	expect($src)->toContain("!isset(\$_SERVER['REQUEST_METHOD'])");
});

test('counters live in a directory of their own, not loose in the temp dir', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/csp_report_endpoint.php');

	expect($src)->not->toContain("sys_get_temp_dir() . '/cacti_csp_' . hash(")
		->and($src)->toContain("\$dir = sys_get_temp_dir() . '/cacti_csp';")
		->and($src)->toContain('is_link($dir)');
});

test('pruning removes stale counters and leaves current ones', function () {
	$dir = sys_get_temp_dir() . '/cacti_csp_prune_' . bin2hex(random_bytes(6));
	mkdir($dir, 0700, true);

	$stale   = $dir . '/' . hash('sha256', 'stale');
	$current = $dir . '/' . hash('sha256', 'current');

	file_put_contents($stale, '5');
	file_put_contents($current, '5');
	touch($stale, time() - 600);

	// the sweep is sampled, so call it until it runs rather than depending on luck
	for ($i = 0; $i < 400 && file_exists($stale); $i++) {
		csp_report_prune_buckets($dir);
	}

	expect(file_exists($stale))->toBeFalse()
		->and(file_exists($current))->toBeTrue();

	@unlink($current);
	@rmdir($dir);
});
