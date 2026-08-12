<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * call_remote_data_collector() issued an outbound file_get_contents() to any
 * hostname stored in the poller table, including loopback and link-local
 * addresses, so an admin could point a remote collector at 127.0.0.1 or the
 * cloud metadata endpoint (169.254.169.254) and turn a status poll into SSRF.
 * The resolved target is now checked against reserved ranges before the fetch,
 * while private LAN ranges (used by legitimate distributed collectors) stay
 * allowed.
 */

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');

function _crdc_body(string $src): string {
	$start = strpos($src, 'function call_remote_data_collector(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\nfunction ", $start + 1);

	if ($end === false) {
		$end = strlen($src);
	}

	return substr($src, $start, $end - $start);
}

test('the target IP is checked against reserved ranges before the fetch', function () use ($src) {
	$body  = _crdc_body($src);
	$guard = strpos($body, 'FILTER_FLAG_NO_RES_RANGE');
	$fetch = strpos($body, 'file_get_contents(');

	expect($guard)->not->toBeFalse();
	expect($fetch)->not->toBeFalse();
	expect($guard)->toBeLessThan($fetch);
	expect($body)->toContain('Refusing Remote Data Collector fetch');
});

test('the outbound request is pinned to the validated IP, not the hostname', function () use ($src) {
	$body = _crdc_body($src);

	// the fetch connects to the resolved+validated IP so the OS cannot resolve
	// the hostname a second time (DNS rebinding); the hostname rides in the Host
	// header and TLS peer_name instead.
	expect($body)->toContain('$connect_host = filter_var($target_ip');
	expect($body)->toContain("get_url_type() . '://' . \$connect_host . \$port . \$url");
	expect($body)->toContain("\$fgc_contextoption['http']['header'] .= 'Host: ' . \$host_header");
	expect($body)->toContain('get_default_contextoption(false, $normalized_host)');

	// the raw hostname must no longer be the connect target in the URL
	expect($body)->not->toContain("'://' . \$url_host");
});

test('reserved and loopback/link-local targets are blocked', function () {
	$blocked = array('127.0.0.1', '169.254.169.254', '0.0.0.0', '::1');

	foreach ($blocked as $ip) {
		expect(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE))->toBeFalse();
	}
});

test('private LAN ranges used by distributed collectors are still allowed', function () {
	$allowed = array('10.0.0.5', '172.16.4.4', '192.168.1.10', '8.8.8.8');

	foreach ($allowed as $ip) {
		expect(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE))->not->toBeFalse();
	}
});
