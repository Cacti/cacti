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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

beforeEach(function () {
	unset(
		$_SERVER['REMOTE_ADDR'],
		$_SERVER['HTTP_X_FORWARDED_FOR'],
		$_SERVER['HTTP_CLIENT_IP'],
		$_SERVER['HTTP_X_FORWARDED'],
		$_SERVER['HTTP_FORWARDED_FOR'],
		$_SERVER['HTTP_FORWARDED'],
	);
});

test('returns REMOTE_ADDR when proxy_headers is false', function () {
	global $config;

	$config['proxy_headers'] = false;

	$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

	expect(get_client_addr())->toBe('192.168.1.100');
});

test('returns false when no server variables are set', function () {
	global $config;

	$config['proxy_headers'] = false;

	expect(get_client_addr())->toBeFalse();
});

test('returns first valid IP from X-Forwarded-For when proxy_headers enabled', function () {
	global $config, $allowed_proxy_headers;

	require __DIR__ . '/../../include/global_arrays.php';

	$config['proxy_headers'] = true;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1,10.0.0.2';
	$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

	expect(get_client_addr())->toBe('10.0.0.1');
});

test('skips invalid IPs in forwarded header', function () {
	global $config, $allowed_proxy_headers;

	require __DIR__ . '/../../include/global_arrays.php';

	$config['proxy_headers'] = true;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip,203.0.113.50';
	$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

	expect(get_client_addr())->toBe('203.0.113.50');
});

test('falls back to REMOTE_ADDR when forwarded header has only invalid IPs', function () {
	global $config, $allowed_proxy_headers;

	require __DIR__ . '/../../include/global_arrays.php';

	$config['proxy_headers'] = true;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = 'garbage';
	$_SERVER['REMOTE_ADDR'] = '172.16.0.5';

	expect(get_client_addr())->toBe('172.16.0.5');
});

test('ignores proxy headers when proxy_headers is false', function () {
	global $config;

	$config['proxy_headers'] = false;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
	$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

	expect(get_client_addr())->toBe('192.168.1.100');
});

test('supports IPv6 addresses', function () {
	global $config;

	$config['proxy_headers'] = false;

	$_SERVER['REMOTE_ADDR'] = '::1';

	expect(get_client_addr())->toBe('::1');
});

test('handles array proxy_headers config with specific headers allowed', function () {
	global $config, $allowed_proxy_headers;

	$config['proxy_headers'] = ['HTTP_CLIENT_IP'];

	$_SERVER['HTTP_CLIENT_IP'] = '10.10.10.10';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '172.16.0.1';
	$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

	expect(get_client_addr())->toBe('10.10.10.10');
});
