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
 * Batch-12 security consolidation regressions (develop), guarding the fixes
 * in this batch:
 *
 *   GHSA-4qx8-jj2h-p7q2  constant-time 2FA cookie + reset-token compares
 *   GHSA-54fg-q9h9-88mm  package-repo manifest fetch via cacti_http (SSRF)
 *   GHSA-5v3j-wcrr-jxjg  data-input <field> quote shedding on substitution
 *   GHSA-vhh2-gghg-whjg  spikekill --rrdfile escaping (confirmation)
 */

$root = dirname(__DIR__, 3);

$twoFaSource     = file_get_contents($root . '/auth_2fa.php');
$resetSource     = file_get_contents($root . '/auth_resetpassword.php');
$repoSource      = file_get_contents($root . '/package_repos.php');
$functionsSource = file_get_contents($root . '/lib/functions.php');
$spikekillSource = file_get_contents($root . '/poller_spikekill.php');
$importSource    = file_get_contents($root . '/package_import.php');

// =====================================================================
// GHSA-4qx8: constant-time comparisons
// =====================================================================

test('GHSA-4qx8: the 2FA bypass cookie is compared with hash_equals', function () use ($twoFaSource) {
	expect($twoFaSource)->toContain('hash_equals(hash_hmac(')
		->and($twoFaSource)->not->toContain("\$tfaCookeHash === hash_hmac(");
});

test('GHSA-4qx8: the reset token is compared with hash_equals at both sites', function () use ($resetSource) {
	expect($resetSource)->not->toContain("\$hash['hash'] != \$user_hash")
		->and(substr_count($resetSource, 'hash_equals((string) $hash[\'hash\'], (string) $user_hash)'))->toBe(2);
});

// =====================================================================
// GHSA-54fg: package repo fetch through the hardened client
// =====================================================================

test('GHSA-54fg: both repo fetch paths use cacti_http and drop raw file_get_contents', function () use ($repoSource) {
	expect($repoSource)->toContain("cacti_http('GET', \$file)")
		->and($repoSource)->toContain("cacti_http('GET', \$file, \$http_options)")
		->and($repoSource)->not->toContain('file_get_contents($file')
		->and($repoSource)->not->toContain('verify_peer');
});

test('GHSA-54fg: the import-time repo fetch routes remote reads through cacti_http', function () use ($importSource) {
	expect($importSource)->toContain("cacti_http('GET', \$file, \$http_options)")
		->and($importSource)->toContain("cacti_http('GET', \$file)")
		->and($importSource)->not->toContain('verify_peer')
		->and($importSource)->not->toContain('file_get_contents($file, false');
});

// =====================================================================
// GHSA-5v3j: quote-aware <field> substitution
// =====================================================================

test('GHSA-5v3j: substitute_script_path is quote-aware and closes template quoting around values', function () use ($functionsSource) {
	expect($functionsSource)->toContain("preg_match('/\\G<([A-Za-z0-9_]+)>/'")
		->and($functionsSource)->toContain('$out .= $quote . $value . $quote;')
		->and($functionsSource)->not->toContain('>(?(1)')
		->and($functionsSource)->not->toContain('array_key_exists($matches[2], $escaped_values)');
});

test('GHSA-5v3j: a double-quoted placeholder sheds the quotes so the value cannot break out', function () {
	expect(function_exists('substitute_script_path'))->toBeTrue();

	// cacti_escapeshellarg() wraps the value in single quotes.
	$esc = "'" . '$(id)' . "'";

	expect(substitute_script_path('"<arg1>"', ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path("'<arg1>'", ['arg1' => $esc]))->toBe($esc)
		->and(substitute_script_path('<arg1>', ['arg1' => $esc]))->toBe($esc);
});

test('GHSA-5v3j: a token embedded in a larger quoted word keeps the value safely quoted', function () {
	$esc = "'" . '$(id)' . "'";

	// the template's quoting is closed around the already-escaped value, so an
	// outer double quote cannot re-enable $(...) / backticks inside it
	expect(substitute_script_path('"prefix<arg1>"', ['arg1' => $esc]))->toBe('"prefix"' . $esc . '""')
		->and(substitute_script_path('"<arg1>suffix"', ['arg1' => $esc]))->toBe('""' . $esc . '"suffix"')
		->and(substitute_script_path('"a<arg1>b"', ['arg1' => $esc]))->toBe('"a"' . $esc . '"b"');
});

test('GHSA-5v3j: an unknown token keeps its literal form and quotes', function () {
	expect(substitute_script_path('"<nope>"', ['arg1' => 'x']))->toBe('"<nope>"');
});

test('GHSA-5v3j: single-pass fq9x behaviour is retained', function () {
	expect(substitute_script_path('<f>', ['f' => '<g>', 'g' => 'PWN']))->toBe('<g>');
});

// =====================================================================
// GHSA-vhh2: spikekill RRDfile escaping (already fixed; confirmation)
// =====================================================================

test('GHSA-vhh2: the spikekill poller escapes the RRDfile path', function () use ($spikekillSource) {
	expect($spikekillSource)->toContain("' --rrdfile=' . cacti_escapeshellarg(\$f)")
		->and($spikekillSource)->not->toContain("' --rrdfile=' . \$f .");
});
