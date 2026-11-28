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
 * A color template item exists to give an aggregate graph item a color, so
 * it is saved only with a color. An item whose color has no row is still
 * listed with well formed markup. The probe runs the unchanged page code.
 */

namespace ColorTemplateItemColorTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function run(array $scenario) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario + array(
		'db' => array(
			'colors'          => array(array('id' => 5, 'name' => 'Blue', 'hex' => '0000FF')),
			'color_templates' => array(array('color_template_id' => 1, 'name' => 'Blues')),
		),
	))));
}

function save($color_id) {
	return run(array(
		'slice'   => array(array('color_templates_items.php', 'aggregate_color_item_form_save')),
		'call'    => 'aggregate_color_item_form_save',
		'request' => array('save_component_item' => '1', 'color_template_id' => '1', 'color_template_item_id' => '0', 'sequence' => '', 'color_id' => $color_id),
	));
}

test('an item is saved with a color', function () {
	$result = save('5');

	expect($result['errors'])->toBe(array())
		->and($result['tables']['color_template_items'])->toBe(array(array('color_template_item_id' => '1', 'color_template_id' => '1', 'color_id' => '5', 'sequence' => '1')))
		->and($result['location'])->toBe('Location: color_templates.php?header=false&action=template_edit&color_template_id=1');
});

test('an item without a color is refused', function ($color_id) {
	$result = save($color_id);

	expect($result['errors'])->toBe(array('color_id'))
		->and($result['tables']['color_template_items'])->toBe(array())
		->and($result['location'])->toBe('Location: color_templates_items.php?header=false&action=item_edit&color_template_item_id=0&color_template_id=1');
})->with(array(
	'none'     => array('0'),
	'empty'    => array(''),
	'not an id' => array('blue'),
	'negative' => array('-5'),
));

test('an item whose color has no row is listed without a style attribute', function () {
	$output = run(array(
		'slice' => array(array('color_templates.php', 'draw_color_template_items_list')),
		'call'  => 'draw_color_template_items_list',
		'args'  => array(array(array('color_template_item_id' => 1, 'color_template_id' => 1, 'hex' => null)), 'color_templates_items.php', '', true),
	))['output'];

	expect($output)->toContain("<td></td>\n<td></td>")
		->and($output)->not->toContain("style='");
});
