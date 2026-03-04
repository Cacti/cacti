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
require_once dirname(__DIR__, 2) . '/lib/functions.php';

// --- sanitize_unserialize_selected_items ---

test('sanitize_unserialize_selected_items accepts valid numeric array', function () {
	$items = serialize([1, 2, 3]);
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBe([1, 2, 3]);
});

test('sanitize_unserialize_selected_items accepts array with string numbers', function () {
	$items = serialize(['10', '20', '30']);
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBe(['10', '20', '30']);
});

test('sanitize_unserialize_selected_items accepts array with empty strings', function () {
	$items = serialize(['1', '', '3']);
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBe(['1', '', '3']);
});

test('sanitize_unserialize_selected_items rejects non-numeric values', function () {
	$items = serialize(['1', 'abc', '3']);
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects nested arrays', function () {
	$items = serialize([1, [2, 3], 4]);
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items blocks object injection', function () {
	// Craft a serialized string containing an object reference
	$malicious = 'a:1:{i:0;O:8:"stdClass":0:{}}';
	$result = sanitize_unserialize_selected_items($malicious);
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items blocks object injection with plus sign', function () {
	$malicious = 'a:1:{i:0;O:+8:"stdClass":0:{}}';
	$result = sanitize_unserialize_selected_items($malicious);
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects empty string', function () {
	$result = sanitize_unserialize_selected_items('');
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects non-string input', function () {
	$result = sanitize_unserialize_selected_items(123);
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items rejects malformed serialized data', function () {
	$result = sanitize_unserialize_selected_items('not_serialized_at_all');
	expect($result)->toBeFalse();
});

test('sanitize_unserialize_selected_items handles escaped slashes from POST', function () {
	// WordPress/PHP magic_quotes style escaping
	$items = addslashes(serialize([5, 10, 15]));
	$result = sanitize_unserialize_selected_items($items);
	expect($result)->toBe([5, 10, 15]);
});

// --- sanitize_cdef ---

test('sanitize_cdef strips shell metacharacters', function () {
	expect(sanitize_cdef('a,b,+|;`rm'))->toBe('a,b,+rm');
});

test('sanitize_cdef strips angle brackets', function () {
	expect(sanitize_cdef('CURRENT_DATA_SOURCE<>0'))->toBe('CURRENT_DATA_SOURCE0');
});

test('sanitize_cdef preserves valid RPN operators', function () {
	$cdef = 'CURRENT_DATA_SOURCE,100,*,+,-,/,%';
	expect(sanitize_cdef($cdef))->toBe($cdef);
});

test('sanitize_cdef preserves numeric values and commas', function () {
	$cdef = '100,200,+';
	expect(sanitize_cdef($cdef))->toBe('100,200,+');
});

test('sanitize_cdef handles empty string', function () {
	expect(sanitize_cdef(''))->toBe('');
});

test('sanitize_cdef strips curly braces', function () {
	expect(sanitize_cdef('val{inject}'))->toBe('valinject');
});

// --- is_ipaddress ---

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

test('is_ipaddress rejects invalid octets', function () {
	expect(is_ipaddress('999.999.999.999'))->toBeFalse();
});

// --- is_mac_address ---

test('is_mac_address accepts colon-separated MAC', function () {
	$result = is_mac_address('00:1A:2B:3C:4D:5E');
	expect($result)->toBeTruthy();
});

test('is_mac_address accepts hyphen-separated MAC', function () {
	$result = is_mac_address('00-1A-2B-3C-4D-5E');
	expect($result)->toBeTruthy();
});

test('is_mac_address accepts lowercase MAC', function () {
	$result = is_mac_address('aa:bb:cc:dd:ee:ff');
	expect($result)->toBeTruthy();
});

test('is_mac_address rejects invalid MAC', function () {
	$result = is_mac_address('not-a-mac');
	expect($result)->toBeFalsy();
});

test('is_mac_address rejects empty string', function () {
	$result = is_mac_address('');
	expect($result)->toBeFalsy();
});

test('is_mac_address rejects too-short MAC', function () {
	$result = is_mac_address('00:1A:2B');
	expect($result)->toBeFalsy();
});

// --- is_hex_string ---

test('is_hex_string detects Hex- prefix with valid hex pairs', function () {
	$input = 'Hex-41 42 43';
	$result = is_hex_string($input);
	expect($result)->toBeTrue()
		->and($input)->toBe('41 42 43');
});

test('is_hex_string rejects Hex-STRING: prefix (caught by hex- branch)', function () {
	// The hex- branch matches before hex-string:, leaving 'STRING:4F 4B'
	// which fails hex pair validation
	$input = 'Hex-STRING:4F 4B';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects string without hex prefix', function () {
	$input = 'plain text';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects empty string', function () {
	$input = '';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects single hex pair (length 1)', function () {
	$input = 'Hex-4F';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects non-hex characters after prefix', function () {
	$input = 'Hex-ZZ GG';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string rejects odd-length hex parts', function () {
	$input = 'Hex-4 42 43';
	expect(is_hex_string($input))->toBeFalse();
});

test('is_hex_string is case insensitive on prefix', function () {
	$input = 'HEX-41 42';
	$result = is_hex_string($input);
	expect($result)->toBeTrue();
});
