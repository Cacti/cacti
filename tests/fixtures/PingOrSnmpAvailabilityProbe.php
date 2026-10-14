<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
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

if (!function_exists('socket_create')) {
    print json_encode(['sockets' => false]);
    exit;
}
$p = bug5679_ping($argv[2] === '1', $argv[3] === '1');
$mode = constant($argv[1]);
print json_encode(['sockets' => true, 'up' => $p->ping($mode), 'snmp_calls' => $p->snmp_calls], JSON_THROW_ON_ERROR);
