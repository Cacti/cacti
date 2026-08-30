<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Forward-port of the 1.2.x hardening fixes. cacti_is_https() is exercised
 * directly (it has no DB dependency); the remaining three are source-scan
 * guards because they sit inside DB/shell code paths.
 */

$functions = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');
$auth      = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');
$dsstats   = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

require_once dirname(__DIR__, 4) . '/lib/functions.php';

beforeEach(function () {
	unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);
	$GLOBALS['config']['proxy_headers'] = [];
});

test('cacti_is_https is true on direct TLS and false on plain http', function () {
	$_SERVER['HTTPS'] = 'on';
	expect(cacti_is_https())->toBeTrue();

	$_SERVER['HTTPS'] = 'off';
	expect(cacti_is_https())->toBeFalse();

	unset($_SERVER['HTTPS']);
	expect(cacti_is_https())->toBeFalse();
});

test('a forwarded proto is honoured only when proxy trust is enabled', function () {
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

	// proxy trust off: a client-supplied header must NOT force https
	$GLOBALS['config']['proxy_headers'] = [];
	expect(cacti_is_https())->toBeFalse();

	// proxy trust on: the forwarded proto is honoured
	$GLOBALS['config']['proxy_headers'] = ['REMOTE_ADDR'];
	expect(cacti_is_https())->toBeTrue();
});

test('X-Forwarded-SSL on is honoured under proxy trust', function () {
	$GLOBALS['config']['proxy_headers'] = true;
	$_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';
	expect(cacti_is_https())->toBeTrue();
});

test('get_guest_account requires the guest user to be enabled', function () use ($functions) {
	$start = strpos($functions, 'function get_guest_account(');
	expect(substr($functions, $start, 400))->toContain("enabled = 'on'");
});

test('permission where-clauses cast ids to int', function () use ($auth) {
	expect($auth)->not->toContain('" gl.id = $graph_id"');
	expect($auth)->not->toContain('" h.id = $device_id"');
	expect($auth)->toContain('(int) $graph_id');
	expect($auth)->toContain('(int) $device_id');
});

test('path_dsstats_log is escaped in the shell redirect', function () use ($dsstats) {
	expect(substr_count($dsstats, "cacti_escapeshellarg(read_config_option('path_dsstats_log'))"))->toBe(2);
});
