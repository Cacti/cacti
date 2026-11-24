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
 * With gradient support on, an AREA item is drawn as a series of shaded
 * areas starting from its color. An AREA without a color is invisible in
 * RRDtool and often used as the base of a stack; shading it started from no
 * color and drew opaque black, so it keeps its plain AREA.
 */

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

function rrdAreaGradientOption($value) {
	$GLOBALS['config']['config_options_array']['enable_rrdtool_gradient_support'] = $value;
}

afterEach(function () {
	unset($GLOBALS['config']['config_options_array']['enable_rrdtool_gradient_support']);
});

test('an item with a color is shaded when gradient support is on', function () {
	rrdAreaGradientOption('on');

	expect(rrd_area_uses_gradient("#FF0000'FF'"))->toBeTrue();
});

test('an item without a color is not shaded', function () {
	rrdAreaGradientOption('on');

	expect(rrd_area_uses_gradient(''))->toBeFalse();
});

test('no item is shaded when gradient support is off', function () {
	rrdAreaGradientOption('');

	expect(rrd_area_uses_gradient("#FF0000'FF'"))->toBeFalse();
});

test('the AREA item asks whether to shade with its color', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');

	expect($source)->toContain("if (rrd_area_uses_gradient(\$graph_item_color_code)) {\n\t\t\t\t\t\t// End color is")
		->and(substr_count($source, "'enable_rrdtool_gradient_support'"))->toBe(1);
});
