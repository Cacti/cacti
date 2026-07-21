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

$root = dirname(__DIR__, 2);

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return '';
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return is_array($array) || $array instanceof Countable ? count($array) : 0;
	}
}

require_once $root . '/lib/auth.php';

/* is_trusted_proxy() and the forwarded-header gate in get_basic_auth_username()
 * ensure client-supplied HTTP_ identity headers are only honored when the request
 * arrives from a proxy that config.php lists in $trusted_proxies. The web-server-set
 * REMOTE_USER path is not proxy-gated. */

function reset_identity_env(array $server, array $trusted): void {
	foreach (['PHP_AUTH_USER', 'REMOTE_USER', 'REDIRECT_REMOTE_USER',
		'HTTP_X_FORWARDED_USER', 'HTTP_PHP_AUTH_USER', 'HTTP_REMOTE_USER',
		'HTTP_REDIRECT_REMOTE_USER', 'REMOTE_ADDR'] as $key) {
		unset($_SERVER[$key]);
	}

	foreach ($server as $key => $value) {
		$_SERVER[$key] = $value;
	}

	$GLOBALS['config']['trusted_proxies'] = $trusted;
}

test('is_trusted_proxy is false when the trusted list is empty', function () {
	reset_identity_env(['REMOTE_ADDR' => '10.0.0.1'], []);
	expect(is_trusted_proxy())->toBeFalse();
});

test('is_trusted_proxy is false when REMOTE_ADDR is not in the trusted list', function () {
	reset_identity_env(['REMOTE_ADDR' => '203.0.113.9'], ['10.0.0.1']);
	expect(is_trusted_proxy())->toBeFalse();
});

test('is_trusted_proxy is true on an exact REMOTE_ADDR match', function () {
	reset_identity_env(['REMOTE_ADDR' => '10.0.0.1'], ['10.0.0.2', '10.0.0.1']);
	expect(is_trusted_proxy())->toBeTrue();
});

test('is_trusted_proxy is false when REMOTE_ADDR is unset', function () {
	reset_identity_env([], ['10.0.0.1']);
	expect(is_trusted_proxy())->toBeFalse();
});

test('forwarded X-Forwarded-User is ignored from an untrusted client', function () {
	reset_identity_env([
		'REMOTE_ADDR'          => '203.0.113.9',
		'HTTP_X_FORWARDED_USER' => 'admin',
	], []);
	expect(get_basic_auth_username())->toBeFalse();
});

test('forwarded HTTP_REMOTE_USER is ignored from an untrusted client', function () {
	reset_identity_env([
		'REMOTE_ADDR'     => '203.0.113.9',
		'HTTP_REMOTE_USER' => 'admin',
	], ['10.0.0.1']);
	expect(get_basic_auth_username())->toBeFalse();
});

test('forwarded X-Forwarded-User is honored from a trusted proxy', function () {
	reset_identity_env([
		'REMOTE_ADDR'          => '10.0.0.1',
		'HTTP_X_FORWARDED_USER' => 'admin',
	], ['10.0.0.1']);
	expect(get_basic_auth_username())->toBe('admin');
});

test('forwarded HTTP_REMOTE_USER is honored from a trusted proxy', function () {
	reset_identity_env([
		'REMOTE_ADDR'     => '10.0.0.1',
		'HTTP_REMOTE_USER' => 'proxyuser',
	], ['10.0.0.1']);
	expect(get_basic_auth_username())->toBe('proxyuser');
});

test('web-server-set REMOTE_USER is honored regardless of proxy trust', function () {
	reset_identity_env([
		'REMOTE_ADDR' => '203.0.113.9',
		'REMOTE_USER' => 'svcaccount',
	], []);
	expect(get_basic_auth_username())->toBe('svcaccount');
});

test('an untrusted client cannot override the web-server REMOTE_USER via a forwarded header', function () {
	reset_identity_env([
		'REMOTE_ADDR'          => '203.0.113.9',
		'REMOTE_USER'          => 'svcaccount',
		'HTTP_X_FORWARDED_USER' => 'attacker',
	], []);
	expect(get_basic_auth_username())->toBe('svcaccount');
});
