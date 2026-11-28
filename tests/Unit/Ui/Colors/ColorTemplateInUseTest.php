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
 * A color template used by an aggregate template or an aggregate graph is in
 * use. The list counts the templates and graphs that use it, not their
 * items, offers only unused color templates for deletion, and the delete
 * action keeps the ones in use. The probe runs the unchanged page code.
 */

namespace ColorTemplateInUseTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function run(array $scenario) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario + array(
		'db' => array(
			'color_templates' => array(
				array('color_template_id' => 1, 'name' => 'Template Use'),
				array('color_template_id' => 2, 'name' => 'Graph Use'),
				array('color_template_id' => 3, 'name' => 'Unused'),
			),
			'color_template_items' => array(
				array('color_template_item_id' => 1, 'color_template_id' => 1, 'color_id' => 1),
				array('color_template_item_id' => 2, 'color_template_id' => 2, 'color_id' => 1),
				array('color_template_item_id' => 3, 'color_template_id' => 3, 'color_id' => 1),
			),
			/* one aggregate template uses Template Use on two of its items */
			'aggregate_graph_templates_item' => array(
				array('aggregate_template_id' => 10, 'graph_templates_item_id' => 1, 'color_template' => 1),
				array('aggregate_template_id' => 10, 'graph_templates_item_id' => 2, 'color_template' => 1),
			),
			/* one aggregate graph uses Graph Use on three of its items */
			'aggregate_graphs_graph_item' => array(
				array('aggregate_graph_id' => 100, 'graph_templates_item_id' => 1, 'color_template' => 2),
				array('aggregate_graph_id' => 100, 'graph_templates_item_id' => 2, 'color_template' => 2),
				array('aggregate_graph_id' => 100, 'graph_templates_item_id' => 3, 'color_template' => 2),
			),
		),
	))));
}

test('delete keeps color templates used by an aggregate template or graph', function () {
	$result = run(array(
		'slice'   => array(array('color_templates.php', 'aggregate_color_form_actions')),
		'call'    => 'aggregate_color_form_actions',
		'request' => array('drp_action' => '1', 'selected_items' => serialize(array('1', '2', '3'))),
	));

	expect(array_column($result['tables']['color_templates'], 'color_template_id'))->toBe(array('1', '2'))
		->and(array_column($result['tables']['color_template_items'], 'color_template_id'))->toBe(array('1', '2'))
		->and(array_column($result['raised'], 'level', 'id'))->toBe(array('color_template_in_use' => MESSAGE_LEVEL_WARN));
});

test('deleting an unused color template removes it and its items', function () {
	$result = run(array(
		'slice'   => array(array('color_templates.php', 'aggregate_color_form_actions')),
		'call'    => 'aggregate_color_form_actions',
		'request' => array('drp_action' => '1', 'selected_items' => serialize(array('3'))),
	));

	expect(array_column($result['tables']['color_templates'], 'color_template_id'))->toBe(array('1', '2'))
		->and(array_column($result['tables']['color_template_items'], 'color_template_id'))->toBe(array('1', '2'))
		->and($result['raised'])->toBe(array());
});

test('the list counts templates and graphs and offers only unused color templates', function () {
	$result = run(array(
		'slice'   => array(array('color_templates.php', 'aggregate_color_template')),
		'call'    => 'aggregate_color_template',
		'request' => array('has_graphs' => 'false', 'sort_column' => 'name', 'sort_direction' => 'ASC', 'rows' => '30', 'page' => '1', 'filter' => ''),
	));

	preg_match_all("/<tr id=\"line(\d+)\">.*?<\/tr>/s", $result['output'], $rows);
	$counts = array();

	foreach ($rows[0] as $i => $row) {
		preg_match_all("/<td class='nowrap right'>([^<]*)<\/td>/", $row, $cells);
		$counts[$rows[1][$i]] = $cells[1];
	}

	expect($counts)->toBe(array(
		'2' => array('No', '1', ''),
		'1' => array('No', '', '1'),
		'3' => array('Yes', '', ''),
	))->and($result['checkboxes'])->toBe(array(2 => true, 1 => true, 3 => false));
});
