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

require_once __DIR__ . '/../../lib/functions.php';

// cacti_strtolower tests

test('cacti_strtolower converts ASCII uppercase', function () {
	expect(cacti_strtolower('HELLO'))->toBe('hello');
});

test('cacti_strtolower preserves lowercase', function () {
	expect(cacti_strtolower('hello'))->toBe('hello');
});

test('cacti_strtolower handles mixed case', function () {
	expect(cacti_strtolower('HeLLo WoRLd'))->toBe('hello world');
});

test('cacti_strtolower handles empty string', function () {
	expect(cacti_strtolower(''))->toBe('');
});

test('cacti_strtolower handles protocol names', function () {
	expect(cacti_strtolower('TCP'))->toBe('tcp');
	expect(cacti_strtolower('UDP6'))->toBe('udp6');
	expect(cacti_strtolower('HTTPS'))->toBe('https');
});

test('cacti_strtolower handles config values', function () {
	expect(cacti_strtolower('ON'))->toBe('on');
	expect(cacti_strtolower('Off'))->toBe('off');
});

test('cacti_strtolower handles UTF-8 characters', function () {
	expect(cacti_strtolower('ÜBER'))->toBe('über');
});

test('cacti_strtolower handles numeric strings', function () {
	expect(cacti_strtolower('123ABC'))->toBe('123abc');
});

test('cacti_strtolower handles NaN detection pattern', function () {
	expect(cacti_strtolower('NaN'))->toBe('nan');
	expect(cacti_strtolower('-NaN'))->toBe('-nan');
});

test('cacti_strtolower handles file extensions', function () {
	expect(cacti_strtolower('RRD'))->toBe('rrd');
	expect(cacti_strtolower('PNG'))->toBe('png');
});

// cacti_strtoupper tests

test('cacti_strtoupper converts ASCII lowercase', function () {
	expect(cacti_strtoupper('hello'))->toBe('HELLO');
});

test('cacti_strtoupper preserves uppercase', function () {
	expect(cacti_strtoupper('HELLO'))->toBe('HELLO');
});

test('cacti_strtoupper handles empty string', function () {
	expect(cacti_strtoupper(''))->toBe('');
});

test('cacti_strtoupper handles hex strings', function () {
	expect(cacti_strtoupper('0a1b2c'))->toBe('0A1B2C');
	expect(cacti_strtoupper('ff'))->toBe('FF');
});

test('cacti_strtoupper handles PHP_OS pattern', function () {
	expect(cacti_strtoupper('win'))->toBe('WIN');
	expect(cacti_strtoupper('Lin'))->toBe('LIN');
});

test('cacti_strtoupper handles plugin names', function () {
	expect(cacti_strtoupper('thold'))->toBe('THOLD');
});

test('cacti_strtoupper handles UTF-8 characters', function () {
	expect(cacti_strtoupper('über'))->toBe('ÜBER');
});

test('cacti_strtoupper handles path constants pattern', function () {
	expect(cacti_strtoupper('base'))->toBe('BASE');
	expect(cacti_strtoupper('library'))->toBe('LIBRARY');
});
