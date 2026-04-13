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
 * Source-level and contract tests for authentication security advisories.
 *
 * GHSA-6gr7: Open redirect via REFERER substring check (lib/auth.php)
 * GHSA-273r: Session fixation — missing session_regenerate_id (auth_login.php)
 * GHSA-6rvg: Auth bypass via DNS short-hostname collision (remote_agent.php)
 * GHSA-8p2f: Reports IDOR — missing ownership check (lib/html_reports.php)
 * GHSA-hr82: TOCTOU lockout race — non-atomic failed_attempts increment (lib/auth.php)
 */

$authPath        = __DIR__ . '/../../lib/auth.php';
$authLoginPath   = __DIR__ . '/../../auth_login.php';
$remoteAgentPath = __DIR__ . '/../../remote_agent.php';
$htmlReportsPath = __DIR__ . '/../../lib/html_reports.php';

// ---------------------------------------------------------------------------
// GHSA-6gr7: Open redirect via REFERER substring check
// ---------------------------------------------------------------------------

test('GHSA-6gr7: str_contains CACTI_PATH_URL substring check is bypassable with evil host', function () {
	// auth_login_redirect checks the REFERER by looking for CACTI_PATH_URL
	// (e.g. '/cacti/') as a substring anywhere in the URL. An attacker hosts
	// a domain whose path component contains the expected string.
	$cactiPathUrl = '/cacti/';
	$maliciousUrl = 'https://evil.com/cacti/index.php';

	expect(str_contains($maliciousUrl, $cactiPathUrl))->toBeTrue();
});

test('GHSA-6gr7: auth.php uses validate_redirect_url for REFERER validation', function () use ($authPath) {
	$contents = file_get_contents($authPath);

	// The fix replaces raw $_SERVER['HTTP_REFERER'] with validate_redirect_url()
	expect($contents)->toContain("validate_redirect_url(\$_SERVER['HTTP_REFERER'])");
	// The old vulnerable pattern must not exist
	expect($contents)->not->toContain("\$referer = \$_SERVER['HTTP_REFERER'];");
});

// ---------------------------------------------------------------------------
// GHSA-273r: Session fixation
// ---------------------------------------------------------------------------

test('GHSA-273r: auth_login.php calls session_regenerate_id after login', function () use ($authLoginPath) {
	$contents = file_get_contents($authLoginPath);

	// session_regenerate_id(true) must be called to prevent session fixation.
	expect($contents)->toContain('session_regenerate_id(true)');
});

// ---------------------------------------------------------------------------
// GHSA-6rvg: Auth bypass via DNS short-hostname collision
// ---------------------------------------------------------------------------

test('GHSA-6rvg: remote_agent.php defines remote_agent_strip_domain()', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	expect($contents)->toContain('function remote_agent_strip_domain');
});

test('GHSA-6rvg: stripping domain from two distinct FQDNs yields the same short name', function () {
	// remote_agent_strip_domain() keeps only the first label. A legitimate
	// poller 'poller1.corp.example.com' and an attacker-controlled host
	// 'poller1.evil.com' both reduce to 'poller1', so the whitelist check
	// passes for the attacker's request.
	$stripDomain = function (string $host): string {
		if (str_contains($host, '.')) {
			return explode('.', $host)[0];
		}

		return $host;
	};

	$legitimate = $stripDomain('poller1.corp.example.com');
	$attacker   = $stripDomain('poller1.evil.com');

	expect($legitimate)->toBe('poller1');
	expect($attacker)->toBe('poller1');
	expect($legitimate)->toBe($attacker);
});

test('GHSA-6rvg: contract — remote_client_authorized uses full FQDN comparison', function () use ($remoteAgentPath) {
	$contents = file_get_contents($remoteAgentPath);

	// The authorization path must not call remote_agent_strip_domain()
	// before comparing hostnames.
	$authFunc  = substr($contents, strpos($contents, 'function remote_client_authorized'));
	$nextFunc  = strpos($authFunc, "\nfunction ", 1);
	$authBody  = $nextFunc !== false ? substr($authFunc, 0, $nextFunc) : $authFunc;

	expect($authBody)->not->toContain('remote_agent_strip_domain');
});

// ---------------------------------------------------------------------------
// GHSA-8p2f: Reports IDOR — ownership check on DELETE
// ---------------------------------------------------------------------------

test('GHSA-8p2f: html_reports.php DELETE query includes user_id ownership filter', function () use ($htmlReportsPath) {
	$contents = file_get_contents($htmlReportsPath);

	// The fix adds AND user_id = ? to the DELETE query and a pre-query
	// ownership check using SESS_USER_ID.
	expect($contents)->toContain('DELETE FROM reports WHERE id = ? AND user_id = ?');
	expect($contents)->toContain('SESS_USER_ID');
});

// ---------------------------------------------------------------------------
// GHSA-hr82: TOCTOU lockout race — non-atomic failed_attempts increment
// ---------------------------------------------------------------------------

test('GHSA-hr82: auth.php uses atomic SQL increment for failed_attempts', function () use ($authPath) {
	$contents = file_get_contents($authPath);

	// The fix uses SET failed_attempts = failed_attempts + 1 in the UPDATE
	// so the increment is atomic and cannot be raced.
	expect($contents)->toContain('failed_attempts = failed_attempts + 1');

	// The old PHP-side increment must not exist.
	expect($contents)->not->toContain('intval($user[\'failed_attempts\']) + 1');
});
