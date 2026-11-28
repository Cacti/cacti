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
 * The alpha of a graph item is one of the opacities the item editor offers
 * (include/global_arrays.php), so a graph item or graph template item is
 * saved only with one of them. The probe runs the unchanged form_save() of
 * graphs_items.php and graph_templates_items.php.
 */

namespace GraphItemAlphaTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function save($page, $alpha) {
	$request = array(
		'save_component_item'    => '1',
		'graph_template_item_id' => '0',
		'graph_template_id'      => '3',
		'local_graph_id'         => $page === 'graphs_items.php' ? '8' : '0',
		'graph_type_id'          => '7',
		'color_id'               => '5',
		'alpha'                  => $alpha,
		'task_item_id'           => '',
		'consolidation_function_id' => '1',
		'text_format'            => 'Inbound',
		'cdef_id'                => '0',
		'vdef_id'                => '0',
		'gprint_id'              => '2',
		'sequence'               => '',
	);

	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode(array(
		'slice'   => array(array($page, 'form_save')),
		'call'    => 'form_save',
		'request' => $request,
	))));
}

test('an offered alpha is saved', function ($page, $alpha) {
	$result = save($page, $alpha);

	expect($result['errors'])->toBe(array())
		->and(array_column($result['tables']['graph_templates_item'], 'alpha'))->toBe(array($alpha));
})->with(array('graphs_items.php', 'graph_templates_items.php'))->with(array('00', '7F', 'FF'));

test('a lower case alpha is saved in upper case', function ($page) {
	$result = save($page, 'ff');

	expect($result['errors'])->toBe(array())
		->and(array_column($result['tables']['graph_templates_item'], 'alpha'))->toBe(array('FF'));
})->with(array('graphs_items.php', 'graph_templates_items.php'));

test('the alpha may be left empty as before', function ($page) {
	expect(save($page, '')['errors'])->toBe(array());
})->with(array('graphs_items.php', 'graph_templates_items.php'));

test('any other alpha is refused', function ($page, $alpha) {
	$result = save($page, $alpha);

	expect($result['errors'])->toBe(array('alpha'))
		->and($result['tables']['graph_templates_item'])->toBe(array());
})->with(array('graphs_items.php', 'graph_templates_items.php'))->with(array(
	'not offered'   => array('80'),
	'option text'   => array('-a'),
	'not hex'       => array('ZZ'),
	'three digits'  => array('FFF'),
	'trailing line' => array("FF\n"),
));
