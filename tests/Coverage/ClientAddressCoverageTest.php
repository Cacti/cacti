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

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $value) : int {
		return is_array($value) ? count($value) : 0;
	}
}

require_once CACTI_PATH_LIBRARY . '/client_address.php';

test('trusted match rejects an empty address or an empty list', function () : void {
	expect(cacti_trusted_proxy_match('', ['10.0.0.1']))->toBeFalse();
	expect(cacti_trusted_proxy_match('10.0.0.1', []))->toBeFalse();
});

test('trusted match skips empty entries', function () : void {
	expect(cacti_trusted_proxy_match('10.0.0.1', ['', '10.0.0.1']))->toBeTrue();
	expect(cacti_trusted_proxy_match('10.0.0.1', ['']))->toBeFalse();
});

test('trusted match normalises equivalent IP spellings', function () : void {
	expect(cacti_trusted_proxy_match('::1', ['0:0:0:0:0:0:0:1']))->toBeTrue();
	expect(cacti_trusted_proxy_match('127.0.0.1', ['127.0.0.1']))->toBeTrue();
});

test('trusted match does not let an IPv4-mapped entry match bare IPv4', function () : void {
	expect(cacti_trusted_proxy_match('127.0.0.1', ['::ffff:127.0.0.1']))->toBeFalse();
});

test('trusted match supports CIDR', function () : void {
	expect(cacti_trusted_proxy_match('10.1.2.3', ['10.0.0.0/8']))->toBeTrue();
	expect(cacti_trusted_proxy_match('10.1.2.3', ['192.168.0.0/16']))->toBeFalse();
	expect(cacti_trusted_proxy_match('2001:db8::1', ['2001:db8::/32']))->toBeTrue();
});

test('trusted match falls back to exact string equality for a non IP entry', function () : void {
	expect(cacti_trusted_proxy_match('proxy.local', ['proxy.local']))->toBeTrue();
	expect(cacti_trusted_proxy_match('proxy.local', ['other.local']))->toBeFalse();
});

test('header keys map wire names onto CGI names', function () : void {
	expect(cacti_server_header_key('REMOTE_ADDR'))->toBe('REMOTE_ADDR');
	expect(cacti_server_header_key('HTTP_X_FORWARDED_FOR'))->toBe('HTTP_X_FORWARDED_FOR');
	expect(cacti_server_header_key('X-Forwarded-For'))->toBe('HTTP_X_FORWARDED_FOR');
	expect(cacti_server_header_key(' cf-connecting-ip '))->toBe('HTTP_CF_CONNECTING_IP');
});

test('resolution needs a usable REMOTE_ADDR', function () : void {
	expect(cacti_resolve_client_addr([], [], ['HTTP_X_FORWARDED_FOR']))->toBeFalse();
	expect(cacti_resolve_client_addr(['REMOTE_ADDR' => ''], [], ['HTTP_X_FORWARDED_FOR']))->toBeFalse();
	expect(cacti_resolve_client_addr(['REMOTE_ADDR' => 'garbage'], [], ['HTTP_X_FORWARDED_FOR']))->toBeFalse();
});

test('an untrusted peer never has its forwarded header honoured', function () : void {
	$server = ['REMOTE_ADDR' => '198.51.100.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.9'];

	expect(cacti_resolve_client_addr($server, [], ['HTTP_X_FORWARDED_FOR']))->toBe('198.51.100.1');
	expect(cacti_resolve_client_addr($server, ['10.0.0.5'], ['HTTP_X_FORWARDED_FOR']))->toBe('198.51.100.1');
});

test('a trusted peer yields the hop it observed', function () : void {
	$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1, 198.51.100.7'];

	expect(cacti_resolve_client_addr($server, ['10.0.0.5'], ['HTTP_X_FORWARDED_FOR']))->toBe('198.51.100.7');
});

test('trusted hops inside the chain are walked past', function () : void {
	$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.0.0.6, 10.0.0.7'];

	expect(cacti_resolve_client_addr($server, ['10.0.0.0/8'], ['HTTP_X_FORWARDED_FOR']))->toBe('198.51.100.7');
});

test('a chain of nothing but trusted hops falls back to REMOTE_ADDR', function () : void {
	$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '10.0.0.6, 10.0.0.7'];

	expect(cacti_resolve_client_addr($server, ['10.0.0.0/8'], ['HTTP_X_FORWARDED_FOR']))->toBe('10.0.0.5');
});

test('a malformed entry abandons that header', function () : void {
	$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, not-an-ip'];

	expect(cacti_resolve_client_addr($server, ['10.0.0.5'], ['HTTP_X_FORWARDED_FOR']))->toBe('10.0.0.5');
});

test('empty chain segments are skipped', function () : void {
	$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, '];

	expect(cacti_resolve_client_addr($server, ['10.0.0.5'], ['HTTP_X_FORWARDED_FOR']))->toBe('198.51.100.7');
});

test('REMOTE_ADDR and absent headers in the permitted list are skipped', function () : void {
	$server  = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];
	$headers = ['REMOTE_ADDR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'];

	expect(cacti_resolve_client_addr($server, ['10.0.0.5'], $headers))->toBe('198.51.100.7');
});
