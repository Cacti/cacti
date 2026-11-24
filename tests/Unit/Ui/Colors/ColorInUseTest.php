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
 * A named color is read only, and a color used by a graph item or a color
 * template cannot be deleted. Both rules come from the stored rows, so the
 * color save, the CSV import and the delete action keep them whatever the
 * form posts. Each test runs the unchanged color.php function.
 */

namespace ColorInUseTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function run(array $scenario) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario + array(
		'db' => array(
			'colors' => array(
				array('id' => 1, 'name' => 'Black', 'hex' => '000000', 'read_only' => 'on'),
				array('id' => 2, 'name' => 'Graph', 'hex' => '111111'),
				array('id' => 3, 'name' => 'Template', 'hex' => '222222'),
				array('id' => 4, 'name' => 'Spare', 'hex' => '333333'),
			),
			'graph_templates_item' => array(array('id' => 1, 'local_graph_id' => 7, 'color_id' => 2)),
			'color_template_items' => array(array('color_template_item_id' => 1, 'color_template_id' => 1, 'color_id' => 3)),
		),
	))));
}

function colors(array $result) {
	return array_column($result['tables']['colors'], null, 'id');
}

test('a named color keeps its name and hex when the form leaves out read only', function () {
	$colors = colors(run(array(
		'slice'   => array(array('color.php', 'form_save')),
		'call'    => 'form_save',
		'request' => array('save_component_color' => '1', 'id' => '1', 'name' => 'Renamed', 'hex' => 'ABCDEF'),
	)));

	expect($colors['1'])->toBe(array('id' => '1', 'name' => 'Black', 'hex' => '000000', 'read_only' => 'on'));
});

test('the form cannot make a color read only', function () {
	$colors = colors(run(array(
		'slice'   => array(array('color.php', 'form_save')),
		'call'    => 'form_save',
		'request' => array('save_component_color' => '1', 'id' => '4', 'name' => 'Spare', 'hex' => '333333', 'read_only' => 'on', 'hidden_name' => 'Spare'),
	)));

	expect($colors['4']['read_only'])->toBe('');
});

test('an unnamed color is still saved', function () {
	$colors = colors(run(array(
		'slice'   => array(array('color.php', 'form_save')),
		'call'    => 'form_save',
		'request' => array('save_component_color' => '1', 'id' => '4', 'name' => 'Spare Blue', 'hex' => '0000FF'),
	)));

	expect($colors['4'])->toBe(array('id' => '4', 'name' => 'Spare Blue', 'hex' => '0000FF', 'read_only' => ''));
});

test('delete keeps named colors and colors in use', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'form_actions')),
		'call'    => 'form_actions',
		'request' => array('drp_action' => '1', 'selected_items' => serialize(array('1', '2', '3', '4'))),
	));

	expect(array_keys(colors($result)))->toBe(array(1, 2, 3))
		->and(array_column($result['raised'], 'level', 'id'))->toBe(array('color_in_use' => MESSAGE_LEVEL_WARN));
});

test('deleting unused colors raises no warning', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'form_actions')),
		'call'    => 'form_actions',
		'request' => array('drp_action' => '1', 'selected_items' => serialize(array('4'))),
	));

	expect(array_keys(colors($result)))->toBe(array(1, 2, 3))
		->and($result['raised'])->toBe(array());
});

test('the CSV import with updates allowed does not rename a named color', function () {
	$csv    = array("name,hex\n", "Not Black,000000\n", "Plot,111111\n", "New,444444\n");
	$result = run(array(
		'slice'   => array(array('color.php', 'color_import_processor')),
		'call'    => 'color_import_processor',
		'args'    => array($csv),
		'request' => array('allow_update' => 'on'),
	));
	$colors = colors($result);

	expect($colors['1']['name'])->toBe('Black')
		->and($colors['2']['name'])->toBe('Plot')
		->and(array_column($colors, 'name', 'hex')['444444'])->toBe('New')
		->and($result['result'][1])->toBe('<strong>UPDATE SKIPPED, NAMED COLOR:</strong> (000000,Not Black)');
});

test('the color list offers only unused colors that are not named for deletion', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'color'), array('color.php', 'process_request_vars')),
		'call'    => 'color',
		'request' => array('has_graphs' => 'false', 'named' => 'false', 'sort_column' => 'hex', 'sort_direction' => 'ASC'),
	));

	expect($result['checkboxes'])->toBe(array(1 => true, 2 => true, 3 => true, 4 => false));
});
