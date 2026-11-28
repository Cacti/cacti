<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once CACTI_PATH_LIBRARY . '/ip.php';

test('empty address or empty range list never matches', function () : void {
	expect(cacti_ip_in_ranges('', ['10.0.0.1']))->toBeFalse();
	expect(cacti_ip_in_ranges('10.0.0.1', []))->toBeFalse();
});

test('entries are trimmed and empty entries skipped', function () : void {
	expect(cacti_ip_in_ranges('192.0.2.1', ['', ' ', ' 192.0.2.1 ']))->toBeTrue();
	expect(cacti_ip_in_ranges('192.0.2.1', ['', ' ']))->toBeFalse();
});

test('plain IPv4 address matches exactly', function () : void {
	expect(cacti_ip_in_ranges('192.168.1.10', ['192.168.1.10']))->toBeTrue();
	expect(cacti_ip_in_ranges('192.168.1.10', ['192.168.1.11']))->toBeFalse();
});

test('plain IPv6 address matches exactly, ignoring spelling', function () : void {
	expect(cacti_ip_in_ranges('::1', ['0:0:0:0:0:0:0:1']))->toBeTrue();
	expect(cacti_ip_in_ranges('2001:db8::1', ['2001:db8::2']))->toBeFalse();
});

test('IPv4 CIDR membership and non-membership', function () : void {
	expect(cacti_ip_in_ranges('10.1.2.3', ['10.0.0.0/8']))->toBeTrue();
	expect(cacti_ip_in_ranges('10.1.2.3', ['10.1.2.0/24']))->toBeTrue();
	expect(cacti_ip_in_ranges('10.2.2.3', ['10.1.2.0/24']))->toBeFalse();
	expect(cacti_ip_in_ranges('192.168.1.1', ['10.0.0.0/8', '172.16.0.0/12']))->toBeFalse();
});

test('IPv6 CIDR membership and non-membership', function () : void {
	expect(cacti_ip_in_ranges('2001:db8::1', ['2001:db8::/32']))->toBeTrue();
	expect(cacti_ip_in_ranges('2001:db9::1', ['2001:db8::/32']))->toBeFalse();
});

test('address matches a later entry when an earlier one misses', function () : void {
	expect(cacti_ip_in_ranges('10.1.2.3', ['192.168.0.0/16', '10.0.0.0/8']))->toBeTrue();
});

test('an IPv4-mapped IPv6 entry never silently matches bare IPv4', function () : void {
	expect(cacti_ip_in_ranges('127.0.0.1', ['::ffff:127.0.0.1']))->toBeFalse();
});

test('families do not cross match', function () : void {
	expect(cacti_ip_in_ranges('10.0.0.1', ['2001:db8::/32']))->toBeFalse();
	expect(cacti_ip_in_ranges('2001:db8::1', ['10.0.0.0/8']))->toBeFalse();
});

test('a non IP entry falls back to exact string equality', function () : void {
	expect(cacti_ip_in_ranges('host.local', ['host.local']))->toBeTrue();
	expect(cacti_ip_in_ranges('host.local', ['other.local']))->toBeFalse();
	expect(cacti_ip_in_ranges('10.0.0.1', ['not-an-ip', '10.0.0.0/8']))->toBeTrue();
});

/* A prefix such as '2x', ' 8' or '8.5' is not a prefix: it must never match,
 * never throw and never be read loosely as 8. */
test('a malformed CIDR prefix never matches and never throws', function () : void {
	foreach (['10.0.0.0/2x', '10.0.0.0/ 8', '10.0.0.0/8.5', '10.0.0.0/', '10.0.0.0/-1', '10.0.0.0/33', '10.0.0.0/0x8'] as $range) {
		expect(cacti_ip_in_ranges('10.1.1.1', [$range]))->toBeFalse();
	}

	expect(cacti_ip_in_ranges('2001:db8::1', ['2001:db8::/3x']))->toBeFalse();
	expect(cacti_ip_in_ranges('2001:db8::1', ['2001:db8::/129']))->toBeFalse();
});

test('a malformed entry does not hide a later valid one', function () : void {
	expect(cacti_ip_in_ranges('10.1.1.1', ['10.0.0.0/2x', '10.0.0.0/8']))->toBeTrue();
});

test('a zero padded prefix is read as its integer value', function () : void {
	expect(cacti_ip_in_ranges('10.1.1.1', ['10.0.0.0/08']))->toBeTrue();
	expect(cacti_ip_in_ranges('11.1.1.1', ['10.0.0.0/08']))->toBeFalse();
});

test('the CIDR parser accepts only an address with an in-range integer prefix', function () : void {
	expect(cacti_ip_cidr_parse('10.0.0.0/8'))->toBe([inet_pton('10.0.0.0'), 8]);
	expect(cacti_ip_cidr_parse('10.0.0.0/008'))->toBe([inet_pton('10.0.0.0'), 8]);
	expect(cacti_ip_cidr_parse('10.0.0.5'))->toBe([inet_pton('10.0.0.5'), 32]);
	expect(cacti_ip_cidr_parse('2001:db8::/128'))->toBe([inet_pton('2001:db8::'), 128]);
	expect(cacti_ip_cidr_parse('2001:db8::1'))->toBe([inet_pton('2001:db8::1'), 128]);

	foreach (['10.0.0.0/2x', '10.0.0.0/ 8', '10.0.0.0/8 ', '10.0.0.0/8.5', '10.0.0.0/33', '10.0.0.0/', '10.0.0.0/+8',
		'10.0.0.0/8/8', '10.0.0.0/00000000000000000000033', '2001:db8::/129', 'host.local', 'host.local/8',
		' 10.0.0.0/8', '010.0.0.0/8', 'fe80::1%eth0/64', ''] as $invalid) {
		expect(cacti_ip_cidr_parse($invalid))->toBeNull();
	}
});

test('a long zero padded prefix is read as its integer value', function () : void {
	expect(cacti_ip_cidr_parse('10.0.0.0/00000000000000000000008'))->toBe([inet_pton('10.0.0.0'), 8]);
});

test('the ranges filter keeps valid entries and drops malformed ones', function () : void {
	expect(cacti_ip_ranges_filter([' 10.0.0.1 ', '', '10.0.0.0/2x', '192.168.0.0/16', 'host.local', '::1'], 'a test list'))
		->toBe(['10.0.0.1', '192.168.0.0/16', '::1']);
});
