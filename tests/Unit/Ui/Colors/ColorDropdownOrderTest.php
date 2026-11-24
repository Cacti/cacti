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
 * The color dropdown lists colors in hex order, by red, then green, then
 * blue. SUBSTRING() counts from 1, so a sort key that starts at 0 is not the
 * red digits. The probe renders the unchanged form_color_dropdown().
 */

namespace ColorDropdownOrderTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('the colors are listed in hex order', function () {
	$hexes = array('F00000', '000001', '0F0000', '000000', '00F000', '0000F0');
	$rows  = array();

	foreach ($hexes as $i => $hex) {
		$rows[] = array('id' => $i + 1, 'hex' => $hex);
	}

	$output = \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode(array(
		'slice' => array(array('lib/html_form.php', 'form_color_dropdown')),
		'call'  => 'form_color_dropdown',
		'args'  => array('color_id', '1', 'None', '0'),
		'db'    => array('colors' => $rows),
	))))['output'];

	preg_match_all("/data-color='([0-9A-F]{6})'/", $output, $listed);

	expect($listed[1])->toBe(array('000000', '000001', '0000F0', '00F000', '0F0000', 'F00000'));
});
