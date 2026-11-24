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
 * The colors table has a unique key on hex, and the color save is an insert
 * that updates on a duplicate key. Saving a hex that another color has would
 * rename that color (a new color) or save nothing (an edited color) while
 * reporting success, so the save is refused and the form shows the error.
 * The probe runs the unchanged color.php save against an SQLite upsert.
 */

namespace ColorDuplicateHexTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function save(array $request) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode(array(
		'slice'   => array(array('color.php', 'form_save')),
		'call'    => 'form_save',
		'request' => $request + array('save_component_color' => '1'),
		'db'      => array('colors' => array(
			array('id' => 1, 'name' => 'Black', 'hex' => '000000', 'read_only' => 'on'),
			array('id' => 2, 'name' => 'Ink', 'hex' => '101010'),
		)),
	))));
}

$unchanged = array(
	array('id' => '1', 'name' => 'Black', 'hex' => '000000', 'read_only' => 'on'),
	array('id' => '2', 'name' => 'Ink', 'hex' => '101010', 'read_only' => ''),
);

test('a new color with an existing hex is refused', function () use ($unchanged) {
	$result = save(array('id' => '0', 'name' => 'Night', 'hex' => '000000'));

	expect($result['tables']['colors'])->toBe($unchanged)
		->and($result['errors'])->toBe(array('hex'))
		->and(array_column($result['raised'], 'message', 'id'))->toBe(array('color_hex_exists' => 'Another Color already has the Hex value 000000.'))
		->and($result['location'])->toBe('Location: color.php?header=false&action=edit&id=0');
});

test('an edited color cannot take the hex of another color', function () use ($unchanged) {
	$result = save(array('id' => '2', 'name' => 'Ink', 'hex' => '000000'));

	expect($result['tables']['colors'])->toBe($unchanged)
		->and($result['errors'])->toBe(array('hex'))
		->and($result['location'])->toBe('Location: color.php?header=false&action=edit&id=2');
});

test('an edited color keeps its own hex', function () {
	$result = save(array('id' => '2', 'name' => 'Ink Blue', 'hex' => '101010'));

	expect($result['tables']['colors'][1])->toBe(array('id' => '2', 'name' => 'Ink Blue', 'hex' => '101010', 'read_only' => ''))
		->and($result['errors'])->toBe(array())
		->and($result['raised'][0]['id'])->toBe(1);
});
