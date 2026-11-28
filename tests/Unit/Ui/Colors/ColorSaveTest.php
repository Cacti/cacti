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
 * Runs the save function of color.php against a colors table. A color hex
 * is stored as exactly six hexadecimal digits.
 */

namespace ColorSaveTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function save(array $request, array $colors = array()) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode(array(
		'slice'   => array(array('color.php', 'form_save'), array('color.php', 'color_import_processor')),
		'call'    => 'form_save',
		'request' => $request + array('save_component_color' => '1'),
		'db'      => array('colors' => $colors),
	))));
}

test('a six digit hex is saved', function ($hex) {
	$result = save(array('id' => '0', 'name' => 'Sky', 'hex' => $hex));

	expect($result['errors'])->toBe(array())
		->and($result['tables']['colors'])->toBe(array(array('id' => '1', 'name' => 'Sky', 'hex' => $hex, 'read_only' => '')))
		->and($result['location'])->toBe('Location: color.php?header=false');
})->with(array('87CEEB', 'a0b1c2'));

test('a hex that is not six hexadecimal digits is refused', function ($hex) {
	$result = save(array('id' => '0', 'name' => 'Sky', 'hex' => $hex));

	expect($result['errors'])->toBe(array('hex'))
		->and($result['tables']['colors'])->toBe(array())
		->and($result['location'])->toBe('Location: color.php?header=false&action=edit&id=0');
})->with(array(
	'one digit'      => array('F'),
	'three digits'   => array('FFF'),
	'five digits'    => array('FFFFF'),
	'seven digits'   => array('FFFFFFF'),
	'eight digits'   => array('FFFFFFFF'),
	'trailing line'  => array("FFF\n"),
	'six and a line' => array("FFFFFF\n"),
	'not hex'        => array('GGGGGG'),
	'empty'          => array(''),
));
