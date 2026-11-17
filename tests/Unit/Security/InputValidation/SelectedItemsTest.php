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

test('bulk selections preserve canonical lists from confirmation forms', function ($items) {
	expect(sanitize_unserialize_selected_items(addslashes(serialize($items))))->toBe($items);
})->with(array(
	'integer identifiers' => array(array(1, 2, 3)),
	'string identifiers' => array(array('10', '20', '30')),
	'empty selection' => array(array()),
	'legacy empty values' => array(array(1, '', null, false)),
));

test('bulk selections reject trailing data and non-list encodings', function ($input) {
	// PHP 8.3+ warns about trailing bytes before the canonical check rejects them.
	set_error_handler(static function ($severity, $message) {
		return $severity === E_WARNING && str_starts_with($message, 'unserialize(): Extra data');
	});

	try {
		expect(sanitize_unserialize_selected_items(addslashes($input)))->toBeFalse();
	} finally {
		restore_error_handler();
	}
})->with(array(
	'trailing bytes' => array('a:1:{i:0;i:5;}extra'),
	'noncanonical integer' => array('a:1:{i:0;i:+5;}'),
	'nonzero first index' => array(serialize(array(1 => 5, 2 => 6))),
	'named key' => array(serialize(array('id' => 5))),
	'out-of-order keys' => array(serialize(array(1 => 6, 0 => 5))),
	'nested array' => array(serialize(array(array(5)))),
	'non-numeric item' => array(serialize(array('invalid'))),
	'empty input' => array(''),
));
