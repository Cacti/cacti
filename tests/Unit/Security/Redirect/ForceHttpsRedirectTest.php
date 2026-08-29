<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

test('forced HTTPS redirects use the server configured name', function () {
	expect(cacti_build_https_redirect_url(
		'monitor.example',
		'/cacti/graph.php?id=1',
		'/cacti/'
	))->toBe('https://monitor.example/cacti/graph.php?id=1');
});

test('forced HTTPS redirects support IPv6 server names', function () {
	expect(cacti_build_https_redirect_url(
		'2001:db8::1',
		'/cacti/',
		'/cacti/'
	))->toBe('https://[2001:db8::1]/cacti/');
});

test('forced HTTPS redirects reject invalid server names', function () {
	expect(cacti_build_https_redirect_url(
		"monitor.example\r\nLocation: https://evil.example",
		'/cacti/',
		'/cacti/'
	))->toBe('');
});

test('redirect validation ignores a spoofed Host header', function () {
	$server = $_SERVER;

	try {
		$_SERVER['SERVER_NAME'] = 'monitor.example';
		$_SERVER['HTTP_HOST']   = 'evil.example';

		expect(validate_redirect_url('https://evil.example/admin', '/cacti/'))->toBe('/cacti/');
		expect(validate_redirect_url('https://monitor.example/graph.php?id=1', '/cacti/'))->toBe('/graph.php?id=1');
	} finally {
		$_SERVER = $server;
	}
});

test('forced HTTPS bootstrap does not read the Host header', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');

	expect($source)->not->toContain("\$_SERVER['HTTP_HOST']");
});
