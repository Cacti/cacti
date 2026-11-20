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

/*
 * scripts/ss_ping.php, the PHP port of ping.pl. ping itself is not run here
 * because ICMP is not available in every test environment; the parsing is
 * exercised with captured output instead.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_ping.php';

const SS_PING_IPUTILS = "PING 10.0.0.1 (10.0.0.1) 56(84) bytes of data.\n"
	. "64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=0.045 ms\n\n"
	. "--- 10.0.0.1 ping statistics ---\n"
	. "1 packets transmitted, 1 received, 0% packet loss, time 0ms\n"
	. "rtt min/avg/max/mdev = 0.045/0.045/0.045/0.000 ms\n";

const SS_PING_BUSYBOX = "PING 10.0.0.1 (10.0.0.1): 56 data bytes\n"
	. "64 bytes from 10.0.0.1: seq=0 ttl=64 time=1.208 ms\n";

const SS_PING_MACOS = "PING 10.0.0.1 (10.0.0.1): 56 data bytes\n"
	. "64 bytes from 10.0.0.1: icmp_seq=0 ttl=64 time=12.913 ms\n";

const SS_PING_UNKNOWN = "ping: nohost.invalid: Name or service not known\n";

test('the reply line is found for iputils, busybox and macOS output', function () {
	expect(ss_ping_line(SS_PING_IPUTILS, true))->toBe('64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=0.045 ms')
		->and(ss_ping_line(SS_PING_BUSYBOX, true))->toBe('64 bytes from 10.0.0.1: seq=0 ttl=64 time=1.208 ms')
		->and(ss_ping_line(SS_PING_MACOS, false))->toBe('64 bytes from 10.0.0.1: icmp_seq=0 ttl=64 time=12.913 ms');
});

test('resolver errors are picked up only when asked for', function () {
	expect(ss_ping_line(SS_PING_UNKNOWN, true))->toBe('ping: nohost.invalid: Name or service not known')
		->and(ss_ping_line(SS_PING_UNKNOWN, false))->toBe('')
		->and(ss_ping_line('', true))->toBe('');
});

test('the round trip time is returned in milliseconds', function () {
	expect(ss_ping_time('64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=0.045 ms'))->toBe('0.045')
		->and(ss_ping_time('64 bytes from 10.0.0.1: seq=0 ttl=64 time=1.208 ms'))->toBe('1.208')
		->and(ss_ping_time('64 octets from 10.0.0.1: icmp_seq=1 ttl=64 temps=3.10 ms'))->toBe('3.10');
});

test('a time reported in usec is converted to ms', function () {
	expect(ss_ping_time('64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=250 usec'))->toBe('0.25')
		->and(ss_ping_time('64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=1 usec'))->toBe('0.001');
});

test('a line without a time returns U', function () {
	expect(ss_ping_time('ping: nohost.invalid: Name or service not known'))->toBe('U')
		->and(ss_ping_time('64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time= ms'))->toBe('U')
		->and(ss_ping_time(''))->toBe('U');
});

test('the transport prefix Cacti stores is removed', function () {
	expect(ss_ping_host('tcp:router1'))->toBe('router1')
		->and(ss_ping_host('UDP6:2001:db8::1'))->toBe('2001:db8::1')
		->and(ss_ping_host('host-1.example.com'))->toBe('host-1.example.com');
});

test('a host that ping would read as an option is rejected', function (string $host) {
	expect(ss_ping_host($host))->toBeNull()
		->and(ss_ping_collect($host))->toBe(['U', 1])
		->and(ss_ping($host))->toBe('U');
})->with(['', '-f', '-c100', 'tcp:-f', 'host;id', 'a b', '$(id)']);

test('a program that cannot be run yields no output', function () {
	expect(ss_ping_run('cacti-no-such-ping', ['-c', '1', '127.0.0.1']))->toBe('');
});
