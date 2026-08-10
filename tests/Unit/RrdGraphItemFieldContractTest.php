<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for GHSA-vq9v-6ggh-phhr.
 *
 * Graph item options are appended into RRDtool's colon delimited argument
 * strings, so they cannot be escaped after the fact.  Several stored fields
 * reached the command line raw, and the command line reaches shell_exec().
 * Of those, value (varchar 255), dashes (varchar 20), textalign (varchar 10)
 * and alpha/alpha2 (char 2) are free text columns.  Every one of those sinks
 * now goes through a validator first.
 *
 * @group regression
 */

$rrdPath = dirname(__DIR__, 2) . '/lib/rrd.php';

test('dashes reaches the command line only through the list validator', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/':dashes=' \. \\\$graph_item\['dashes'\]/");
	expect($source)->toMatch('/rrd_graph_item_number_list\(\$graph_item\[.dashes.\]\)/');
});

test('textalign reaches the command line only through the keyword validator', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/':' \. \\\$graph_item\['textalign'\]/");
	expect($source)->toMatch('/rrd_graph_item_textalign\(\$graph_item\[.textalign.\]\)/');
});

test('alpha reaches the colour code only through the hex validator', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/\.= \\\$graph_item\['alpha2?'\]/");
	expect($source)->toMatch('/rrd_graph_item_alpha\(\$graph_item\[.alpha.\]\)/');
	expect($source)->toMatch('/rrd_graph_item_alpha\(\$graph_item\[.alpha2.\]\)/');
});

test('no SHIFT statement is built from a raw graph item value', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/'SHIFT:' \. \\\$data_source_name \. ':' \. \\\$graph_item\['value'\]/");
	expect(substr_count($source, '$shift_value = strip_alpha($graph_item[\'value\'])'))->toBe(4);
});

test('the TICK fraction is numeric before it is appended', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/\(':' \. \\\$graph_item\['value'\]\)\)/");
	expect($source)->toMatch('/\$tick_fraction = strip_alpha\(\$graph_item\[.value.\]\)/');
});

test('gradheight and line_width are coerced before they are appended', function () use ($rrdPath) {
	$source = file_get_contents($rrdPath);

	expect($source)->not->toMatch("/':gradheight=' \. \\\$graph_item\['gradheight'\]/");
	expect($source)->not->toMatch("/'LINE' \. \\\$graph_item\['line_width'\]/");
	expect(substr_count($source, "\$gradheight = strip_alpha(\$graph_item['gradheight'])"))->toBe(2);
});

test('the validators live in a module the coverage gate can reach', function () {
	$module = dirname(__DIR__, 2) . '/lib/rrd_graph_item.php';

	expect(file_exists($module))->toBeTrue();
	expect(file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php'))
		->toMatch("/require_once\(__DIR__ \. '\/rrd_graph_item\.php'\)/");
});
