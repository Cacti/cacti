<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/ip.php';

test('cacti_normalize_ip normalizes IPv4', function () {
	expect(cacti_normalize_ip('192.168.1.1'))->toBe('192.168.1.1');
});

test('cacti_normalize_ip compresses IPv6', function () {
	expect(cacti_normalize_ip('2001:0db8:0000:0000:0000:0000:0000:0001'))->toBe('2001:db8::1');
});

test('cacti_normalize_ip downgrades IPv4-mapped IPv6', function () {
	expect(cacti_normalize_ip('::ffff:192.168.1.1'))->toBe('192.168.1.1');
});

test('cacti_normalize_ip strips zone index', function () {
	expect(cacti_normalize_ip('fe80::1%eth0'))->toBe('fe80::1');
});

test('cacti_normalize_ip rejects invalid input', function () {
	expect(cacti_normalize_ip('not-an-ip'))->toBeFalse();
	expect(cacti_normalize_ip(''))->toBeFalse();
});

test('cacti_normalize_ip handles loopback', function () {
	expect(cacti_normalize_ip('127.0.0.1'))->toBe('127.0.0.1');
	expect(cacti_normalize_ip('::1'))->toBe('::1');
});

test('cacti_ip_in_cidr matches IPv4 CIDR', function () {
	expect(cacti_ip_in_cidr('192.168.1.50', '192.168.1.0/24'))->toBeTrue();
	expect(cacti_ip_in_cidr('192.168.2.1', '192.168.1.0/24'))->toBeFalse();
});

test('cacti_ip_in_cidr matches single IP', function () {
	expect(cacti_ip_in_cidr('10.0.0.1', '10.0.0.1'))->toBeTrue();
	expect(cacti_ip_in_cidr('10.0.0.2', '10.0.0.1'))->toBeFalse();
});

test('cacti_ip_in_cidr matches IPv6 CIDR', function () {
	expect(cacti_ip_in_cidr('2001:db8::1', '2001:db8::/32'))->toBeTrue();
	expect(cacti_ip_in_cidr('2001:db9::1', '2001:db8::/32'))->toBeFalse();
});

test('cacti_ip_in_cidr rejects family mismatch', function () {
	expect(cacti_ip_in_cidr('192.168.1.1', '2001:db8::/32'))->toBeFalse();
});

test('cacti_ip_in_cidr rejects invalid mask', function () {
	expect(cacti_ip_in_cidr('192.168.1.1', '192.168.1.0/33'))->toBeFalse();
	expect(cacti_ip_in_cidr('192.168.1.1', '192.168.1.0/-1'))->toBeFalse();
});

test('cacti_ip_in_cidr handles /0 matches all', function () {
	expect(cacti_ip_in_cidr('10.20.30.40', '0.0.0.0/0'))->toBeTrue();
});

test('cacti_ip_in_cidr handles /32 exact match', function () {
	expect(cacti_ip_in_cidr('10.0.0.1', '10.0.0.1/32'))->toBeTrue();
	expect(cacti_ip_in_cidr('10.0.0.2', '10.0.0.1/32'))->toBeFalse();
});

test('cacti_get_secure_client_ip returns REMOTE_ADDR with no proxies', function () {
	$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
	unset($_SERVER['HTTP_X_FORWARDED_FOR']);
	expect(cacti_get_secure_client_ip())->toBe('203.0.113.50');
});

test('cacti_get_secure_client_ip ignores XFF when REMOTE_ADDR not trusted', function () {
	$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
	expect(cacti_get_secure_client_ip(array('192.168.1.0/24')))->toBe('203.0.113.50');
});

test('cacti_get_secure_client_ip extracts client from XFF when proxy trusted', function () {
	$_SERVER['REMOTE_ADDR'] = '192.168.1.10';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99, 192.168.1.10';
	expect(cacti_get_secure_client_ip(array('192.168.1.0/24')))->toBe('203.0.113.99');
});

test('cacti_get_secure_client_ip returns 0.0.0.0 on missing REMOTE_ADDR', function () {
	unset($_SERVER['REMOTE_ADDR']);
	expect(cacti_get_secure_client_ip())->toBe('0.0.0.0');
});
