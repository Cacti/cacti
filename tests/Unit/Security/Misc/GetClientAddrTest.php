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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

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

test('ignores a forwarded chain when no trusted proxy is configured', function () {
	global $config, $allowed_proxy_headers;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$config['proxy_headers']   = true;
	$config['trusted_proxies'] = [];

	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1,10.0.0.2';
	$_SERVER['REMOTE_ADDR']          = '192.168.1.1';

	// The leftmost entry is client supplied. With no trusted proxy declared
	// the header carries no authority, so REMOTE_ADDR wins.
	expect(get_client_addr())->toBe('192.168.1.1');
});

test('takes the hop the trusted proxy observed, not the client supplied one', function () {
	global $config, $allowed_proxy_headers;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$config['proxy_headers']   = true;
	$config['trusted_proxies'] = ['192.168.1.1'];

	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1,203.0.113.9';
	$_SERVER['REMOTE_ADDR']          = '192.168.1.1';

	expect(get_client_addr())->toBe('203.0.113.9');
});

test('a spoofed leftmost entry cannot impersonate a poller', function () {
	global $config, $allowed_proxy_headers;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$config['proxy_headers']   = true;
	$config['trusted_proxies'] = ['10.0.0.5/32'];

	// Attacker sends a poller address as the leftmost entry.
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1,198.51.100.7';
	$_SERVER['REMOTE_ADDR']          = '10.0.0.5';

	expect(get_client_addr())->toBe('198.51.100.7');
});

test('a malformed chain entry falls back to REMOTE_ADDR', function () {
	global $config, $allowed_proxy_headers;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$config['proxy_headers']   = true;
	$config['trusted_proxies'] = ['192.168.1.1'];

	// Walking right to left, a garbage entry means the chain cannot be
	// trusted any further left, so the header is abandoned.
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50,not-an-ip';
	$_SERVER['REMOTE_ADDR']          = '192.168.1.1';

	expect(get_client_addr())->toBe('192.168.1.1');
});

test('falls back to REMOTE_ADDR when forwarded header has only invalid IPs', function () {
	global $config, $allowed_proxy_headers;

	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	$config['proxy_headers'] = true;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = 'garbage';
	$_SERVER['REMOTE_ADDR']          = '172.16.0.5';

	expect(get_client_addr())->toBe('172.16.0.5');
});

test('ignores proxy headers when proxy_headers is false', function () {
	global $config;

	$config['proxy_headers'] = false;

	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
	$_SERVER['REMOTE_ADDR']          = '192.168.1.100';

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

	$config['proxy_headers']   = ['HTTP_CLIENT_IP'];
	$config['trusted_proxies'] = ['192.168.1.1'];

	$_SERVER['HTTP_CLIENT_IP']       = '10.10.10.10';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '172.16.0.1';
	$_SERVER['REMOTE_ADDR']          = '192.168.1.1';

	expect(get_client_addr())->toBe('10.10.10.10');
});
