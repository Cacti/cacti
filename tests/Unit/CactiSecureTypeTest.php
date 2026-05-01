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

require_once dirname(__DIR__, 2) . '/lib/type_secure.php';

test('CactiSecureType::toInt converts numeric strings to integers', function () {
	expect(CactiSecureType::toInt('123'))->toBe(123);
	expect(CactiSecureType::toInt('0'))->toBe(0);
	expect(CactiSecureType::toInt('-5'))->toBe(-5);
});

test('CactiSecureType::toInt returns 0 for null or non-numeric strings', function () {
	expect(CactiSecureType::toInt(null))->toBe(0);
	expect(CactiSecureType::toInt('abc'))->toBe(0);
	expect(CactiSecureType::toInt(''))->toBe(0);
	expect(CactiSecureType::toInt('12.3'))->toBe(12); // is_numeric allows floats
});

test('CactiSecureType::toString returns string for various inputs', function () {
	expect(CactiSecureType::toString(123))->toBe('123');
	expect(CactiSecureType::toString(null))->toBe('');
	expect(CactiSecureType::toString(true))->toBe('1');
	expect(CactiSecureType::toString(false))->toBe('');
});

test('CactiSecureType::toArray ensures value is an array', function () {
	expect(CactiSecureType::toArray([1, 2, 3]))->toBe([1, 2, 3]);
	expect(CactiSecureType::toArray(null))->toBe([]);
	expect(CactiSecureType::toArray(false))->toBe([]);
	expect(CactiSecureType::toArray('not an array'))->toBe([]);
});
