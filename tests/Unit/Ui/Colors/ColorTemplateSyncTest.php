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
 * "Sync Aggregates" of a color template rebuilds every aggregate template
 * that uses it, and every aggregate graph that uses it and is not kept up to
 * date by its template. The probe runs the unchanged sync_color_templates()
 * and records each push_out_aggregates() call.
 */

namespace ColorTemplateSyncTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function sync($color_template) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode(array(
		'slice' => array(array('color_templates.php', 'sync_color_templates')),
		'call'  => 'sync_color_templates',
		'args'  => array($color_template),
		'db'    => array(
			'color_templates' => array(
				array('color_template_id' => 1, 'name' => 'Blues'),
				array('color_template_id' => 2, 'name' => 'Reds'),
			),
			/* aggregate template 10 uses Blues */
			'aggregate_graph_templates_item' => array(array('aggregate_template_id' => 10, 'graph_templates_item_id' => 1, 'color_template' => 1)),
			'aggregate_graphs' => array(
				/* from template 10, kept up to date by the template */
				array('id' => 100, 'aggregate_template_id' => 10, 'template_propogation' => 'on', 'local_graph_id' => 500),
				/* from template 10, not kept up to date, using Reds */
				array('id' => 101, 'aggregate_template_id' => 10, 'template_propogation' => '', 'local_graph_id' => 501),
				/* no template, using Blues */
				array('id' => 102, 'aggregate_template_id' => 0, 'template_propogation' => '', 'local_graph_id' => 502),
				/* no template, using Reds */
				array('id' => 103, 'aggregate_template_id' => 0, 'template_propogation' => '', 'local_graph_id' => 503),
			),
			'aggregate_graphs_graph_item' => array(
				array('aggregate_graph_id' => 100, 'graph_templates_item_id' => 1, 'color_template' => 1),
				array('aggregate_graph_id' => 101, 'graph_templates_item_id' => 1, 'color_template' => 2),
				array('aggregate_graph_id' => 102, 'graph_templates_item_id' => 1, 'color_template' => 1),
				array('aggregate_graph_id' => 103, 'graph_templates_item_id' => 1, 'color_template' => 2),
			),
		),
	))));
}

test('the templates and graphs using the color template are rebuilt', function () {
	$result = sync(1);

	expect($result['pushed'])->toBe(array(array('10', 0), array('0', '502')))
		->and($result['raised'][0]['message'])->toBe("Color Template 'Blues' had 1 Aggregate Templates pushed out and 1 Non-Templated Aggregates pushed out");
});

test('a graph not kept up to date by its template is rebuilt only for its own color template', function () {
	$result = sync(2);

	expect($result['pushed'])->toBe(array(array('10', '501'), array('0', '503')))
		->and($result['raised'][0]['message'])->toBe("Color Template 'Reds' had 0 Aggregate Templates pushed out and 2 Non-Templated Aggregates pushed out");
});
