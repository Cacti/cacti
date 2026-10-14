<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('availability decisions preserve ping and SNMP short circuits', function ($mode, $icmp, $snmp, $up, $calls) {
    $r = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/PingOrSnmpAvailabilityProbe.php', [$mode, $icmp, $snmp]);
    if (!$r['sockets']) $this->markTestSkipped('sockets extension is required for the ICMP branch.');
    expect($r['up'])->toBe($up)->and($r['snmp_calls'])->toBe($calls);
})->with([
    'both down' => ['AVAIL_SNMP_OR_PING', '0', '0', false, 1],
    'SNMP up' => ['AVAIL_SNMP_OR_PING', '0', '1', true, 1],
    'ping up' => ['AVAIL_SNMP_OR_PING', '1', '0', true, 0],
    'AND ping down' => ['AVAIL_SNMP_AND_PING', '0', '1', false, 0],
    'AND both up' => ['AVAIL_SNMP_AND_PING', '1', '1', true, 1],
    'ping only' => ['AVAIL_PING', '0', '1', false, 0]
]);
