<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/ping.php';

/* ping() references these Cacti helpers; stub them when a lighter bootstrap is
 * in use so the availability logic can be exercised in isolation. */
if (!function_exists('__')) {
	function __($s) { return $s; }
}
if (!function_exists('cacti_log')) {
	function cacti_log($s) {}
}

/*
 * Bug #5679: in AVAIL_SNMP_OR_PING mode the SNMP branch was hardcoded to a
 * successful result and ping_snmp() was never called, so a host that failed
 * ping but had no working SNMP was still reported up. These stubs drive
 * ping_icmp()/ping_snmp() to fixed outcomes so the availability decision can
 * be asserted without real network I/O.
 */
class Bug5679FakePing extends Net_Ping {
	public $icmp_up    = false;
	public $snmp_up    = false;
	public $snmp_calls = 0;

	function ping_icmp() {
		$this->ping_status = $this->icmp_up ? '1.00' : 'down';
		return $this->icmp_up;
	}

	function ping_snmp() {
		$this->snmp_calls++;
		$this->snmp_status = $this->snmp_up ? '1.00' : 'down';
		return $this->snmp_up;
	}
}

function bug5679_ping($icmp_up, $snmp_up, $community = 'public', $version = 2) {
	$p = new Bug5679FakePing();
	$p->host = array(
		'hostname'       => '127.0.0.1',
		'snmp_community' => $community,
		'snmp_version'   => $version,
	);
	$p->icmp_up = $icmp_up;
	$p->snmp_up = $snmp_up;

	return $p;
}

/* Net_Ping::ping() forces $avail_method to AVAIL_SNMP whenever socket_create()
 * doesn't exist, regardless of what the caller asked for. CI's PHP build
 * doesn't enable the sockets extension, which would collapse every mode
 * below to the SNMP-only path and make these assertions meaningless. */
beforeEach(function () {
	if (!function_exists('socket_create')) {
		test()->markTestSkipped('sockets extension not available; ping() forces AVAIL_SNMP and the ICMP branch is unreachable.');
	}
});

test('OR mode reports down when ping fails and SNMP does not respond', function () {
	$p = bug5679_ping(false, false);
	expect($p->ping(AVAIL_SNMP_OR_PING))->toBeFalse();
	expect($p->snmp_calls)->toBe(1);
});

test('OR mode reports up when ping fails but SNMP responds', function () {
	$p = bug5679_ping(false, true);
	expect($p->ping(AVAIL_SNMP_OR_PING))->toBeTrue();
	expect($p->snmp_calls)->toBe(1);
});

test('OR mode reports up on a successful ping without an extra SNMP query', function () {
	$p = bug5679_ping(true, false);
	expect($p->ping(AVAIL_SNMP_OR_PING))->toBeTrue();
	expect($p->snmp_calls)->toBe(0);
});

test('AND mode short-circuits to down on ping failure', function () {
	$p = bug5679_ping(false, true);
	expect($p->ping(AVAIL_SNMP_AND_PING))->toBeFalse();
	expect($p->snmp_calls)->toBe(0);
});

test('AND mode reports up only when ping and SNMP both succeed', function () {
	$p = bug5679_ping(true, true);
	expect($p->ping(AVAIL_SNMP_AND_PING))->toBeTrue();
});

test('ping-only mode ignores SNMP state', function () {
	$p = bug5679_ping(false, true);
	expect($p->ping(AVAIL_PING))->toBeFalse();
	expect($p->snmp_calls)->toBe(0);
});
