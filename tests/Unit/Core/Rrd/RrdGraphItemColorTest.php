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
 * A color is stored as six hexadecimal digits and is written into the
 * RRDtool graph argument without quotes. The color part of the argument is
 * built only from a hex of that form, so a value stored by another writer is
 * left out instead of changing the argument.
 */

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

test('six hexadecimal digits are a color hex', function ($hex) {
	expect(cacti_color_hex_valid($hex))->toBeTrue();
})->with(array('000000', 'FFFFFF', 'fafd9e', 'A0b1C2'));

test('anything else is not a color hex', function ($hex) {
	expect(cacti_color_hex_valid($hex))->toBeFalse();
})->with(array(
	'empty'             => array(''),
	'null'              => array(null),
	'three digits'      => array('FFF'),
	'five digits'       => array('FFFFF'),
	'seven digits'      => array('FFFFFFF'),
	'eight digits'      => array('FFFFFFFF'),
	'trailing line'     => array("FFFFFF\n"),
	'short and line'    => array("FFF\n"),
	'leading hash'      => array('#FFFFFF'),
	'not hexadecimal'   => array('GGGGGG'),
	'space'             => array('FFF FF'),
	'integer'           => array(123456),
	'array'             => array(array('FFFFFF')),
));

test('an item color is the hex followed by the quoted alpha', function () {
	$GLOBALS['config']['cacti_server_os'] = 'unix';

	expect(rrd_graph_item_color_code('FF0000', '7F'))->toBe("#FF0000'7F'");
});

test('an item without a valid hex has no color part', function ($hex) {
	expect(rrd_graph_item_color_code($hex, 'FF'))->toBe('');
})->with(array(
	'none'          => array(''),
	'null'          => array(null),
	'short'         => array('FFF'),
	'trailing line' => array("FFF\n"),
	'long'          => array('FFFFFFFF'),
));

test('the graph command builds the item color through the check', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');

	expect($source)->toContain("\$graph_item_color_code = rrd_graph_item_color_code(\$graph_item['hex'], \$graph_item['alpha']);")
		->and($source)->not->toContain("\$graph_item_color_code = '#' . \$graph_item['hex'];");
});

test('template import stores a color only from six hexadecimal digits', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/import.php');

	expect($source)->toContain("(\$field_name == 'color_id') && (cacti_color_hex_valid(\$item_array[\$field_name]))");
});
