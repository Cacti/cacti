<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

test('cookie domains match the configured server name', function () {
	expect(cacti_cookie_domain_matches_host('monitor.example', 'monitor.example'))->toBeTrue()
		->and(cacti_cookie_domain_matches_host('.example.com', 'monitor.example.com'))->toBeTrue()
		->and(cacti_cookie_domain_matches_host('EXAMPLE.COM', 'Monitor.Example.Com'))->toBeTrue()
		->and(cacti_cookie_domain_matches_host('127.0.0.1', '127.0.0.1'))->toBeTrue();
});

test('cookie domains reject mismatched or malformed hosts', function () {
	expect(cacti_cookie_domain_matches_host('cacti13_test', '127.0.0.1'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('example.com', '127.0.0.1'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('localhost', '127.0.0.1'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('example.com', 'notexample.com'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host("example.com\r\n", 'example.com'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('example.com.', 'example.com'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('2001:db8::1', '2001:db8::1'))->toBeFalse();
});

test('a single label domain is only accepted as the host itself', function () {
	/* Domain=localhost on host localhost is stored by the browser, while
	 * Domain=com on host foo.com is discarded as a public suffix. Both were
	 * confirmed against a real client before this rule was written. */
	expect(cacti_cookie_domain_matches_host('localhost', 'localhost'))->toBeTrue()
		->and(cacti_cookie_domain_matches_host('com', 'foo.com'))->toBeFalse()
		->and(cacti_cookie_domain_matches_host('example', 'host.example'))->toBeFalse();
});

test('invalid configured domains use host-only session cookies', function () {
	$global = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');

	expect($global)->toContain("ini_set('session.cookie_domain', '');")
		->and($global)->toContain('cacti_cookie_domain_matches_host($cacti_cookie_domain, $server_name)')
		->and($global)->toContain('using a host-only cookie.');
});

test('session regeneration only runs for an active session', function () {
	$functions = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
	$start     = strpos($functions, 'function cacti_session_regenerate()');
	$body      = substr($functions, $start, 400);

	expect($start)->not->toBeFalse()
		->and($body)->toContain('if (session_status() === PHP_SESSION_ACTIVE) {')
		->and(strpos($body, 'session_regenerate_id(true);'))->toBeGreaterThan(strpos($body, 'PHP_SESSION_ACTIVE'));
});
