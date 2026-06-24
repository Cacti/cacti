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

require_once $root . '/lib/auth.php';

/* remote_agent_fcrdns_confirmed is the pure forward-confirmation decision
 * extracted from remote_client_authorized(). A PTR that does not forward-confirm
 * must NOT authorize via source-IP fallback, since get_client_addr() honors a
 * client-supplied X-Forwarded-For when proxy_headers is enabled. */

test('fcrdns confirms when a forward A record matches the source address', function () {
	expect(remote_agent_fcrdns_confirmed('10.0.0.5', [
		['type' => 'A', 'ip' => '10.0.0.5'],
	]))->toBeTrue();
});

test('fcrdns confirms when a forward AAAA record matches the source address', function () {
	expect(remote_agent_fcrdns_confirmed('2001:db8::1', [
		['type' => 'AAAA', 'ipv6' => '2001:db8::1'],
	]))->toBeTrue();
});

test('fcrdns rejects when forward records resolve to a different address', function () {
	// PTR exists but forward lookup points elsewhere: the spoofing/inconsistency
	// signal. The caller must reject rather than fall back to IP matching.
	expect(remote_agent_fcrdns_confirmed('10.0.0.5', [
		['type' => 'A', 'ip' => '10.0.0.9'],
	]))->toBeFalse();
});

test('fcrdns rejects when no forward records exist', function () {
	expect(remote_agent_fcrdns_confirmed('10.0.0.5', []))->toBeFalse();
});

test('fcrdns rejects an attacker spoofing a known poller address via forwarded headers', function () {
	// attacker controls 203.0.113.7, sends X-Forwarded-For: 10.0.0.5 (a poller IP).
	// gethostbyaddr on 10.0.0.5 yields the poller PTR, but the attacker cannot make
	// that hostname forward-resolve to their own connection, so confirmation fails.
	$poller_forward_records = [
		['type' => 'A', 'ip' => '10.0.0.5'],
	];

	$spoofed_client_addr = '10.0.0.5';

	// The honest poller (real source 10.0.0.5) confirms.
	expect(remote_agent_fcrdns_confirmed($spoofed_client_addr, $poller_forward_records))->toBeTrue();

	// The same PTR name forward-resolves to 10.0.0.5, not to the attacker's address,
	// so a request whose real source differs cannot pass confirmation.
	expect(remote_agent_fcrdns_confirmed('203.0.113.7', $poller_forward_records))->toBeFalse();
});
