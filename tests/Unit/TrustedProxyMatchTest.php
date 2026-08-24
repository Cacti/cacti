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
 * A proxy list is normally written with ranges. The previous match compared
 * entries as whole addresses, so 10.0.0.0/8 was neither an address inet_pton
 * would accept nor a string equal to the client. The range matched nothing and
 * said nothing about it, so the setting looked applied when it was not.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

test('an address inside a configured range is trusted', function () {
	expect(cacti_trusted_proxy_match('10.4.5.6', ['10.0.0.0/8']))->toBeTrue()
		->and(cacti_trusted_proxy_match('172.16.9.9', ['172.16.0.0/12']))->toBeTrue();
});

test('an address outside the range is not trusted', function () {
	expect(cacti_trusted_proxy_match('192.168.1.1', ['10.0.0.0/8']))->toBeFalse()
		->and(cacti_trusted_proxy_match('11.0.0.1', ['10.0.0.0/8']))->toBeFalse();
});

test('a single address entry still matches exactly', function () {
	expect(cacti_trusted_proxy_match('10.1.2.3', ['10.1.2.3']))->toBeTrue()
		->and(cacti_trusted_proxy_match('10.1.2.4', ['10.1.2.3']))->toBeFalse();
});

test('alternate spellings of one address still match', function () {
	expect(cacti_trusted_proxy_match('::1', ['0:0:0:0:0:0:0:1']))->toBeTrue();
});

test('an ipv6 range is honoured', function () {
	expect(cacti_trusted_proxy_match('2001:db8::1', ['2001:db8::/32']))->toBeTrue()
		->and(cacti_trusted_proxy_match('2001:dead::1', ['2001:db8::/32']))->toBeFalse();
});

test('an empty list or empty entry trusts nobody', function () {
	expect(cacti_trusted_proxy_match('10.0.0.1', []))->toBeFalse()
		->and(cacti_trusted_proxy_match('10.0.0.1', ['']))->toBeFalse()
		->and(cacti_trusted_proxy_match('', ['10.0.0.1']))->toBeFalse();
});

test('an ipv4 mapped entry does not match its bare form', function () {
	expect(cacti_trusted_proxy_match('127.0.0.1', ['::ffff:127.0.0.1']))->toBeFalse();
});
