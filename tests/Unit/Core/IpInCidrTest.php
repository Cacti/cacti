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

/* Vectors from the upstream IpUtils test suite (getIpv4Data, getIpv6Data and
 * the invalid-input cases), so the native matcher keeps the semantics that
 * callers relied on.  Each row is [expected, address, cidr or list]. */
dataset('ipv4 vectors', [
	[true, '192.168.1.1', '192.168.1.1'],
	[true, '192.168.1.1', '192.168.1.1/1'],
	[true, '192.168.1.1', '192.168.1.0/24'],
	[false, '192.168.1.1', '1.2.3.4/1'],
	[false, '192.168.1.1', '192.168.1.1/33'],
	[true, '192.168.1.1', ['1.2.3.4/1', '192.168.1.0/24']],
	[true, '192.168.1.1', ['192.168.1.0/24', '1.2.3.4/1']],
	[false, '192.168.1.1', ['1.2.3.4/1', '4.3.2.1/1']],
	[true, '1.2.3.4', '0.0.0.0/0'],
	[true, '1.2.3.4', '192.168.1.0/0'],
	[false, '1.2.3.4', '256.256.256/0'],
	[false, 'an_invalid_ip', '192.168.1.0/24'],
	[false, '', '1.2.3.4/1'],
]);

dataset('ipv6 vectors', [
	[true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'],
	[false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65'],
	[false, '2a01:198:603:0:396e:4789:8e99:890f', '::1'],
	[true, '0:0:0:0:0:0:0:1', '::1'],
	[false, '0:0:603:0:396e:4789:8e99:0001', '::1'],
	[true, '0:0:603:0:396e:4789:8e99:0001', '::/0'],
	[true, '0:0:603:0:396e:4789:8e99:0001', '2a01:198:603:0::/0'],
	[true, '2a01:198:603:0:396e:4789:8e99:890f', ['::1', '2a01:198:603:0::/65']],
	[true, '2a01:198:603:0:396e:4789:8e99:890f', ['2a01:198:603:0::/65', '::1']],
	[false, '2a01:198:603:0:396e:4789:8e99:890f', ['::1', '1a01:198:603:0::/65']],
	[false, '}__test|O:21:&quot;JDatabaseDriverMysqli&quot;:3:{s:2', '::1'],
	[false, '2a01:198:603:0:396e:4789:8e99:890f', 'unknown'],
	[false, '', '::1'],
	[false, '127.0.0.1', '::1'],
	[false, '0.0.0.0/8', '::1'],
	[false, '::1', '127.0.0.1'],
	[false, '::1', '0.0.0.0/8'],
	[true, '::ffff:10.126.42.2', '::ffff:10.0.0.0/0'],
]);

dataset('invalid inputs', [
	[false, '0.0.0', '0.0.0/0'],
	[false, '0.0.0', '0.0.0.0/0'],
	[false, '0.0.0.0', '0.0.0/0'],
	[false, '1.2.3.4/24', '1.2.3.0/24'],
	[false, '1.2.3.4', ' 1.2.3.0/24'],
	[false, '::1/128', '::1'],
	[false, 'fe80::1%eth0', 'fe80::/10'],
	[false, '010.0.0.1', '10.0.0.0/8'],
	[false, '10.0.0.1', '010.0.0.0/8'],
	[false, '10.0.0.1', [null, 10, ['10.0.0.0/8']]],
]);

test('IPv4 membership', function (bool $expected, string $ip, string|array $cidrs) : void {
	expect(cacti_ip_in_cidr($ip, $cidrs))->toBe($expected);
})->with('ipv4 vectors');

test('IPv6 membership', function (bool $expected, string $ip, string|array $cidrs) : void {
	expect(cacti_ip_in_cidr($ip, $cidrs))->toBe($expected);
})->with('ipv6 vectors');

test('invalid addresses and entries never match', function (bool $expected, string $ip, string|array $cidrs) : void {
	expect(cacti_ip_in_cidr($ip, $cidrs))->toBe($expected);
})->with('invalid inputs');

test('prefix bounds are inclusive of zero and the family width', function () : void {
	expect(cacti_ip_in_cidr('192.0.2.1', '192.0.2.1/32'))->toBeTrue();
	expect(cacti_ip_in_cidr('192.0.2.2', '192.0.2.1/32'))->toBeFalse();
	expect(cacti_ip_in_cidr('192.0.2.1', '192.0.2.0/33'))->toBeFalse();
	expect(cacti_ip_in_cidr('2001:db8::1', '2001:db8::1/128'))->toBeTrue();
	expect(cacti_ip_in_cidr('2001:db8::2', '2001:db8::1/128'))->toBeFalse();
	expect(cacti_ip_in_cidr('2001:db8::1', '2001:db8::/129'))->toBeFalse();
	expect(cacti_ip_in_cidr('255.255.255.255', '0.0.0.0/0'))->toBeTrue();
	expect(cacti_ip_in_cidr('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', '::/0'))->toBeTrue();
});

test('every IPv4 prefix length splits on the right bit', function () : void {
	for ($bits = 1; $bits <= 32; $bits++) {
		// The last address inside 10.0.0.0/bits and the first one past it.
		$last = ip2long('10.0.0.0') | ((1 << (32 - $bits)) - 1);

		expect(cacti_ip_in_cidr(long2ip($last), '10.0.0.0/' . $bits))->toBeTrue();

		if ($bits > 7) {
			expect(cacti_ip_in_cidr(long2ip($last + 1), '10.0.0.0/' . $bits))->toBeFalse();
		}
	}
});

test('every IPv6 prefix length splits on the right bit', function () : void {
	$network = str_repeat("\x00", 16);

	for ($bits = 1; $bits <= 128; $bits++) {
		/* Flip exactly bit number $bits (1-based); it lies outside a /($bits - 1)
		 * block's mask only when the block is longer than $bits - 1. */
		$probe        = $network;
		$byte         = intdiv($bits - 1, 8);
		$probe[$byte] = chr(0x80 >> (($bits - 1) % 8));
		$probe        = inet_ntop($probe);

		expect(cacti_ip_in_cidr($probe, '::/' . ($bits - 1)))->toBeTrue();
		expect(cacti_ip_in_cidr($probe, '::/' . $bits))->toBeFalse();
	}
});

test('IPv4-mapped addresses stay in the IPv6 family', function () : void {
	expect(cacti_ip_in_cidr('::ffff:10.1.2.3', '10.0.0.0/8'))->toBeFalse();
	expect(cacti_ip_in_cidr('10.1.2.3', '::ffff:10.0.0.0/104'))->toBeFalse();
	expect(cacti_ip_in_cidr('10.1.2.3', '::ffff:0:0/96'))->toBeFalse();
	expect(cacti_ip_in_cidr('::ffff:10.1.2.3', '::ffff:0:0/96'))->toBeTrue();
	expect(cacti_ip_in_cidr('::ffff:10.1.2.3', '::ffff:10.0.0.0/104'))->toBeTrue();
	expect(cacti_ip_in_cidr('::ffff:11.1.2.3', '::ffff:10.0.0.0/104'))->toBeFalse();
	expect(cacti_ip_in_cidr('10.1.2.3', '::/0'))->toBeFalse();
	expect(cacti_ip_in_cidr('::1', '0.0.0.0/0'))->toBeFalse();
});

test('a malformed prefix never matches, never throws and does not hide a later entry', function () : void {
	foreach (['2x', ' 8', '8 ', '8.5', '', '-1', '+8', '0x8', '1e1', '8/8', '999999999999999999999'] as $prefix) {
		expect(cacti_ip_in_cidr('10.1.1.1', '10.0.0.0/' . $prefix))->toBeFalse();
		expect(cacti_ip_in_cidr('2001:db8::1', '2001:db8::/' . $prefix))->toBeFalse();
	}

	expect(cacti_ip_in_cidr('10.1.1.1', ['10.0.0.0/2x', '10.0.0.0/8']))->toBeTrue();
	expect(cacti_ip_in_cidr('10.1.1.1', '10.0.0.0/08'))->toBeTrue();
	expect(cacti_ip_in_cidr('11.1.1.1', '10.0.0.0/08'))->toBeFalse();
});

test('an empty list never matches', function () : void {
	expect(cacti_ip_in_cidr('10.0.0.1', []))->toBeFalse();
	expect(cacti_ip_in_cidr('10.0.0.1', ''))->toBeFalse();
});
