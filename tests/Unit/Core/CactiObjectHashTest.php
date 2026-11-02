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
 * cacti_object_hash() replaces spl_object_hash(), which is deprecated starting
 * in PHP 8.6. It must preserve the properties the database and boost layers
 * relied on: a stable, 32 character hex string that is unique per live object
 * and that is derived from spl_object_id(). Non-objects must yield false so
 * callers never index a cache with a bogus key.
 */

require_once dirname(__DIR__, 3) . '/lib/functions.php';

test('a hash is a 32 character lowercase hex string derived from spl_object_id', function () {
	$object = new stdClass();

	$hash = cacti_object_hash($object);

	expect($hash)->toBeString()
		->and(strlen($hash))->toBe(32)
		->and($hash)->toMatch('/^[0-9a-f]{32}$/')
		->and($hash)->toBe(sprintf('%016x0000000000000000', spl_object_id($object)));
});

test('the same object always hashes to the same value', function () {
	$object = new stdClass();

	expect(cacti_object_hash($object))->toBe(cacti_object_hash($object));
});

test('two distinct live objects hash to different values', function () {
	$first  = new stdClass();
	$second = new stdClass();

	expect(cacti_object_hash($first))->not->toBe(cacti_object_hash($second));
});

test('non-object arguments return false', function () {
	expect(cacti_object_hash('a string'))->toBeFalse()
		->and(cacti_object_hash(42))->toBeFalse()
		->and(cacti_object_hash(3.14))->toBeFalse()
		->and(cacti_object_hash(array('an', 'array')))->toBeFalse()
		->and(cacti_object_hash(null))->toBeFalse()
		->and(cacti_object_hash(true))->toBeFalse();
});
