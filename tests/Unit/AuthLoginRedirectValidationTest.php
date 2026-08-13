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
 * The post-login referer redirect must be validated. The HTTP_REFERER branch of
 * auth_login_redirect() only checked str_contains(CACTI_PATH_URL), which an
 * absolute off-host URL satisfies, so it could emit an attacker-controlled
 * Location header (open redirect). It now routes the referer through
 * validate_redirect_url() before the header() call.
 */

$authSrc = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');
$htmlSrc = file_get_contents(dirname(__DIR__, 2) . '/lib/html_utility.php');

test('the referer redirect is validated before it is emitted', function () use ($authSrc) {
	$start = strpos($authSrc, 'function auth_login_redirect(');
	$body  = substr($authSrc, $start, strpos($authSrc, "\nfunction ", $start + 1) - $start);

	$validate = strpos($body, '$referer  = validate_redirect_url($referer);');
	$emit     = strpos($body, "header('Location: ' . \$referer)");

	expect($validate)->not->toBeFalse();
	expect($emit)->not->toBeFalse();
	// validation must happen before the Location header is sent
	expect($validate)->toBeLessThan($emit);
});

test('validate_redirect_url enforces same-host and rejects off-site targets', function () use ($htmlSrc) {
	$start = strpos($htmlSrc, 'function validate_redirect_url(');
	$body  = substr($htmlSrc, $start, strpos($htmlSrc, "\n}", $start) - $start);

	// off-host rejection: compares the referer host to the server host and returns
	// the default when they differ
	expect($body)->toContain('$ref_host = parse_url($url, PHP_URL_HOST)');
	expect($body)->toContain('$ref_host === $srv_host');
	// protocol-relative and newline/header-injection rejection
	expect($body)->toContain("strpos(\$url, '//') === 0");
	expect($body)->toContain("preg_match('/[\\r\\n]/', \$url)");
});
