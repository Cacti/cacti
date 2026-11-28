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
 * Every page that prints a stored color hex treats it as text, so a value
 * written by something other than the color form cannot change the markup or
 * the exported XML. Each test runs the unchanged page function with one
 * color whose hex holds markup characters.
 */

namespace ColorHexOutputTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

const HEX     = "a'\"><i>";
const ESCAPED = 'a&apos;&quot;&gt;&lt;i&gt;';

function run(array $scenario) {
	$scenario['db'] = ($scenario['db'] ?? array()) + array('colors' => array(array('id' => 1, 'name' => 'Odd', 'hex' => HEX)));

	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario)))['output'];
}

function expectEscaped($output) {
	expect($output)->toContain(ESCAPED)
		->and($output)->not->toContain('<i>');
}

test('the delete confirmation of the color list', function () {
	expectEscaped(run(array(
		'slice'   => array(array('color.php', 'form_actions')),
		'call'    => 'form_actions',
		'request' => array('drp_action' => '1'),
		'post'    => array('chk_1' => 'on'),
	)));
});

test('the color list', function () {
	expectEscaped(run(array(
		'slice'   => array(array('color.php', 'color'), array('color.php', 'process_request_vars')),
		'call'    => 'color',
		'request' => array('has_graphs' => 'false', 'named' => 'false'),
	)));
});

test('the item list of a color template', function () {
	expectEscaped(run(array(
		'slice' => array(array('color_templates.php', 'draw_color_template_items_list')),
		'call'  => 'draw_color_template_items_list',
		'args'  => array(array(array('color_template_item_id' => 1, 'color_template_id' => 1, 'hex' => HEX)), 'color_templates_items.php', '', true),
	)));
});

test('the removal confirmation of a color template item', function () {
	expectEscaped(run(array(
		'slice'   => array(array('color_templates_items.php', 'aggregate_color_item_remove_confirm')),
		'call'    => 'aggregate_color_item_remove_confirm',
		'request' => array('id' => '1', 'color_id' => '1'),
		'db'      => array(
			'color_templates'      => array(array('color_template_id' => 1, 'name' => 'Blues')),
			'color_template_items' => array(array('color_template_item_id' => 1, 'color_template_id' => 1, 'color_id' => 1)),
		),
	)));
});

test('the color dropdown', function () {
	expectEscaped(run(array(
		'slice' => array(array('lib/html_form.php', 'form_color_dropdown')),
		'call'  => 'form_color_dropdown',
		'args'  => array('color_id', '1', '', '0'),
	)));
});

test('the graph item list', function () {
	expectEscaped(run(array(
		'slice' => array(array('lib/html.php', 'draw_graph_items_list')),
		'call'  => 'draw_graph_items_list',
		'args'  => array(array(array('id' => 1, 'sequence' => 1, 'graph_type_id' => 7, 'data_source_name' => 'traffic_in', 'text_format' => 'In', 'hard_return' => '', 'gprint_name' => '', 'cdef_name' => '', 'vdef_name' => '', 'alpha' => 'FF', 'consolidation_function_id' => 1, 'hex' => HEX)), 'graphs_items.php', 'local_graph_id=1', true),
	)));
});

test('the aggregate graph item list', function () {
	expectEscaped(run(array(
		'slice' => array(array('lib/api_aggregate.php', 'draw_aggregate_graph_items_list')),
		'call'  => 'draw_aggregate_graph_items_list',
		'args'  => array(0, 5),
		'db'    => array('graph_templates_item' => array(array('id' => 1, 'graph_template_id' => 5, 'graph_type_id' => 7, 'color_id' => 1, 'text_format' => 'In'))),
	)));
});

test('the graph template export', function () {
	$scenario = array(
		'slice' => array(array('lib/export.php', 'graph_template_to_xml'), array('lib/export.php', 'xml_character_encode')),
		'call'  => 'graph_template_to_xml',
		'args'  => array(5),
		'db'    => array(
			'graph_templates'       => array(array('id' => 5, 'name' => 'Traffic')),
			'graph_templates_graph' => array(array('id' => 9, 'graph_template_id' => 5, 'local_graph_id' => 0)),
			'graph_templates_item'  => array(array('id' => 1, 'hash' => 'abc', 'graph_template_id' => 5, 'graph_type_id' => 7, 'color_id' => 1)),
		),
	);

	$scenario['db'] += array('colors' => array(array('id' => 1, 'name' => 'Odd', 'hex' => HEX)));

	$result = \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario)));

	expect($result['result'])->toContain('<color_id>' . ESCAPED . '</color_id>')
		->and($result['result'])->not->toContain('<i>');
});
