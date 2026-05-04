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

$authSource = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');
$authLoginSource = file_get_contents(dirname(__DIR__, 2) . '/auth_login.php');

test('auth_process_lockout uses atomic SQL increment for failed_attempts', function () use ($authSource) {
	// The fix replaces SELECT-then-UPDATE with a single atomic UPDATE
	expect(str_contains($authSource, 'failed_attempts = failed_attempts + 1'))
		->toBeTrue();
});

test('auth_process_lockout atomic increment is inside auth_process_lockout function', function () use ($authSource) {
	// Extract the function body and verify the pattern is there
	$start = strpos($authSource, 'function auth_process_lockout(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 2000);
	expect(str_contains($body, 'failed_attempts = failed_attempts + 1'))
		->toBeTrue();
});

test('set_auth_cookie does not use mt_rand', function () use ($authSource) {
	// Extract set_auth_cookie function body
	$start = strpos($authSource, 'function set_auth_cookie(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1000);
	expect(str_contains($body, 'mt_rand'))
		->toBeFalse();
});

test('set_auth_cookie does not use md5 with REQUEST_TIME', function () use ($authSource) {
	$start = strpos($authSource, 'function set_auth_cookie(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1000);
	expect(str_contains($body, "md5(\$_SERVER['REQUEST_TIME']"))
		->toBeFalse();
});

test('set_auth_cookie uses random_bytes for CSPRNG', function () use ($authSource) {
	$start = strpos($authSource, 'function set_auth_cookie(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1000);
	expect(str_contains($body, 'random_bytes('))
		->toBeTrue();
});

test('set_auth_cookie fails closed on CSPRNG failure', function () use ($authSource) {
	$start = strpos($authSource, 'function set_auth_cookie(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1000);
	// Must catch Exception from random_bytes and return false
	expect(str_contains($body, 'catch (Exception'))
		->toBeTrue();
	expect(str_contains($body, 'return false'))
		->toBeTrue();
});

test('auth_display_custom_error_message escapes message with htmlspecialchars', function () use ($authSource) {
	$start = strpos($authSource, 'function auth_display_custom_error_message(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1500);
	expect(str_contains($body, 'htmlspecialchars($message'))
		->toBeTrue();
});

test('auth_display_custom_error_message escapes custom_message with htmlspecialchars', function () use ($authSource) {
	$start = strpos($authSource, 'function auth_display_custom_error_message(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 1500);
	expect(str_contains($body, 'htmlspecialchars($custom_message'))
		->toBeTrue();
});

test('auth_login_redirect blocks protocol-relative open redirect', function () use ($authSource) {
	$start = strpos($authSource, 'function auth_login_redirect(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 3000);
	// The fix checks that $referer[1] === '/' to block //evil.com
	expect(str_contains($body, "\$referer[1] === '/'"))
		->toBeTrue();
});

test('auth_login_redirect validates referer starts with slash', function () use ($authSource) {
	$start = strpos($authSource, 'function auth_login_redirect(');
	expect($start)->not->toBeFalse();

	$body = substr($authSource, $start, 3000);
	// Must check $referer[0] to ensure path is relative
	expect(str_contains($body, "\$referer[0] !== '/'"))
		->toBeTrue();
});

test('auth_login performs auth transition hardening on successful login', function () use ($authLoginSource) {
	expect(str_contains($authLoginSource, "cacti_auth_transition((int)\$user['id'], 'login')"))
		->toBeTrue();
});
