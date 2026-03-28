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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// =====================================================================
// sanitize_unserialize_selected_items tests
// =====================================================================

test('unserialize accepts valid serialized array of integers', function () {
	$items = serialize([1, 2, 3]);

	$result = sanitize_unserialize_selected_items($items);

	expect($result)->toBe([1, 2, 3]);
});

test('unserialize accepts serialized array with string integers', function () {
	$items = serialize(['10', '20', '30']);

	$result = sanitize_unserialize_selected_items($items);

	expect($result)->toBe(['10', '20', '30']);
});

test('unserialize allows empty string values in array', function () {
	$items = serialize([1, '', 3]);

	$result = sanitize_unserialize_selected_items($items);

	expect($result)->toBe([1, '', 3]);
});

test('unserialize rejects PHP object injection attempt', function () {
	$payload = 'a:1:{i:0;O:8:"stdClass":0:{}}';

	expect(sanitize_unserialize_selected_items($payload))->toBeFalse();
});

test('unserialize rejects object with plus sign in length', function () {
	$payload = 'a:1:{i:0;O:+8:"stdClass":0:{}}';

	expect(sanitize_unserialize_selected_items($payload))->toBeFalse();
});

test('unserialize rejects empty input', function () {
	expect(sanitize_unserialize_selected_items(''))->toBeFalse();
});

test('unserialize rejects null input', function () {
	expect(sanitize_unserialize_selected_items(null))->toBeFalse();
});

test('unserialize rejects non-string input', function () {
	expect(sanitize_unserialize_selected_items(42))->toBeFalse();
});

test('unserialize rejects array containing non-numeric strings', function () {
	$items = serialize(['1', 'DROP TABLE', '3']);

	expect(sanitize_unserialize_selected_items($items))->toBeFalse();
});

test('unserialize rejects nested arrays', function () {
	$items = serialize([1, [2, 3], 4]);

	expect(sanitize_unserialize_selected_items($items))->toBeFalse();
});

// =====================================================================
// sanitize_cdef tests
// =====================================================================

test('sanitize_cdef passes normal RPN expression through', function () {
	expect(sanitize_cdef('a,b,+,8,*'))->toBe('a,b,+,8,*');
});

test('sanitize_cdef strips shell injection characters', function () {
	$result = sanitize_cdef('a;`rm -rf /`$PATH');

	expect($result)->not->toContain(';')
		->and($result)->not->toContain('`')
		->and($result)->not->toContain('$')
		->and($result)->toBe('arm -rf /PATH');
});

test('sanitize_cdef strips all dangerous characters', function () {
	$result = sanitize_cdef('^$<>`\'"|[]{}!;');

	expect($result)->toBe('');
});

test('sanitize_cdef handles empty string', function () {
	expect(sanitize_cdef(''))->toBe('');
});

// =====================================================================
// is_ipaddress tests
// =====================================================================

test('is_ipaddress accepts valid IPv4', function () {
	expect(is_ipaddress('192.168.1.1'))->toBeTrue();
});

test('is_ipaddress accepts valid IPv6', function () {
	expect(is_ipaddress('::1'))->toBeTrue();
});

test('is_ipaddress accepts full IPv6', function () {
	expect(is_ipaddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334'))->toBeTrue();
});

test('is_ipaddress rejects hostname', function () {
	expect(is_ipaddress('example.com'))->toBeFalse();
});

test('is_ipaddress rejects empty string', function () {
	expect(is_ipaddress(''))->toBeFalse();
});

test('is_ipaddress rejects malformed IPv4', function () {
	expect(is_ipaddress('999.999.999.999'))->toBeFalse();
});

// =====================================================================
// is_mac_address tests
// =====================================================================

test('is_mac_address accepts colon-separated MAC', function () {
	expect(is_mac_address('00:1A:2B:3C:4D:5E'))->toBeTruthy();
});

test('is_mac_address accepts hyphen-separated MAC', function () {
	expect(is_mac_address('00-1A-2B-3C-4D-5E'))->toBeTruthy();
});

test('is_mac_address rejects invalid MAC', function () {
	expect(is_mac_address('not-a-mac'))->toBeFalsy();
});

test('is_mac_address rejects empty string', function () {
	expect(is_mac_address(''))->toBeFalsy();
});

// =====================================================================
// is_hex_string tests
// =====================================================================

test('is_hex_string accepts Hex- prefixed string and mutates result', function () {
	$input = 'Hex-41 42 43';

	$return = is_hex_string($input);

	expect($return)->toBeTrue()
		->and($input)->toBe('41 42 43');
});

test('is_hex_string accepts Hex-STRING: prefixed string', function () {
	$input = 'Hex-STRING:41 42 43';

	$return = is_hex_string($input);

	expect($return)->toBeTrue()
		->and($input)->toBe('41 42 43');
});

test('is_hex_string rejects string without hex prefix', function () {
	$input = '41 42 43';

	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects single hex pair', function () {
	$input = 'Hex-41';

	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects non-hex characters after prefix', function () {
	$input = 'Hex-GG HH';

	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects empty string', function () {
	$input = '';

	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects uneven-length hex parts', function () {
	$input = 'Hex-4 42 43';

	expect(is_hex_string($input))->toBeFalse();
});
