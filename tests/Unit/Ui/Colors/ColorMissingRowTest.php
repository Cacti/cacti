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
 * A link or a selection can name a color or color template that another user
 * has deleted since the page was shown, and the CSV import can be submitted
 * with no file. Each case returns to a list with a message instead of reading
 * keys of a missing row. The probe runs the unchanged page function.
 */

namespace ColorMissingRowTest;

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function run(array $scenario) {
	return \cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ColorPagesProbe.php', array(json_encode($scenario + array(
		'db' => array(
			'colors'               => array(array('id' => 1, 'name' => 'Blue', 'hex' => '0000FF')),
			'color_templates'      => array(array('color_template_id' => 1, 'name' => 'Blues')),
			'color_template_items' => array(array('color_template_item_id' => 1, 'color_template_id' => 1, 'color_id' => 1, 'sequence' => 1)),
		),
	))));
}

test('the CSV import with no file chosen returns to the import page with a message', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'form_save')),
		'call'    => 'form_save',
		'request' => array('save_component_import' => '1'),
		'files'   => array('import_file' => array('name' => '', 'tmp_name' => '', 'error' => 4)),
	));

	expect($result['location'])->toBe('Location: color.php?action=import')
		->and(array_column($result['raised'], 'id'))->toBe(array(35));
});

test('the CSV import with a file imports it and shows the results', function () {
	$file = tempnam(sys_get_temp_dir(), 'cacti-colors-');
	file_put_contents($file, "name,hex\nSky,87CEEB\n");

	try {
		$result = run(array(
			'slice'   => array(array('color.php', 'form_save'), array('color.php', 'color_import_processor')),
			'call'    => 'form_save',
			'request' => array('save_component_import' => '1'),
			'files'   => array('import_file' => array('name' => 'colors.csv', 'tmp_name' => $file, 'error' => 0)),
		));
	} finally {
		unlink($file);
	}

	expect($result['location'])->toBe('Location: color.php?action=import')
		->and($result['raised'])->toBe(array())
		->and($result['session']['import_debug_info'][1])->toBe('INSERT SUCCEEDED: (87CEEB,Sky)')
		->and(array_column($result['tables']['colors'], 'name', 'hex'))->toBe(array('0000FF' => 'Blue', '87CEEB' => 'Sky'));
});

test('a delete confirmation for a color that no longer exists returns to the list', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'form_actions')),
		'call'    => 'form_actions',
		'request' => array('drp_action' => '1'),
		'post'    => array('chk_9' => 'on'),
	));

	expect($result['location'])->toBe('Location: color.php?header=false')
		->and(array_column($result['raised'], 'id'))->toBe(array(40))
		->and($result['output'])->not->toContain('<li>');
});

test('editing a color that no longer exists returns to the list', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'color_edit')),
		'call'    => 'color_edit',
		'request' => array('id' => '9'),
	));

	expect($result['location'])->toBe('Location: color.php')
		->and(array_column($result['raised'], 'id'))->toBe(array('color_not_found'))
		->and($result['output'])->toBe('');
});

test('editing an existing color still shows the form', function () {
	$result = run(array(
		'slice'   => array(array('color.php', 'color_edit')),
		'call'    => 'color_edit',
		'request' => array('id' => '1'),
	));

	expect($result['location'])->toBeNull()
		->and($result['output'])->toContain('Colors [edit: 0000FF]');
});

test('an item of a color template that no longer exists returns to the list', function ($request) {
	$result = run(array(
		'slice'   => array(array('color_templates_items.php', 'aggregate_color_item_edit')),
		'call'    => 'aggregate_color_item_edit',
		'request' => $request,
	));

	expect($result['location'])->toBe('Location: color_templates.php')
		->and(array_column($result['raised'], 'id'))->toBe(array('color_item_not_found'))
		->and($result['output'])->toBe('');
})->with(array(
	'missing template' => array(array('color_template_id' => '9', 'color_template_item_id' => '0')),
	'missing item'     => array(array('color_template_id' => '1', 'color_template_item_id' => '9')),
));

test('an item of an existing color template is still edited', function ($request) {
	$result = run(array(
		'slice'   => array(array('color_templates_items.php', 'aggregate_color_item_edit')),
		'call'    => 'aggregate_color_item_edit',
		'request' => $request,
	));

	expect($result['location'])->toBeNull()
		->and($result['output'])->toContain('Blues');
})->with(array(
	'existing item' => array(array('color_template_id' => '1', 'color_template_item_id' => '1')),
	'new item'      => array(array('color_template_id' => '1', 'color_template_item_id' => '0')),
));

test('duplicating a color template that no longer exists creates nothing', function () {
	$result = run(array(
		'slice' => array(array('lib/aggregate.php', 'duplicate_color_template')),
		'call'  => 'duplicate_color_template',
		'args'  => array(9, '<template_title> (1)'),
	));

	expect($result['tables']['color_templates'])->toBe(array(array('color_template_id' => '1', 'name' => 'Blues')))
		->and($result['tables']['color_template_items'])->toHaveCount(1);
});

test('duplicating an existing color template copies it and its items', function () {
	$result = run(array(
		'slice' => array(array('lib/aggregate.php', 'duplicate_color_template')),
		'call'  => 'duplicate_color_template',
		'args'  => array(1, '<template_title> (1)'),
	));

	expect($result['tables']['color_templates'][1])->toBe(array('color_template_id' => '2', 'name' => 'Blues (1)'))
		->and($result['tables']['color_template_items'][1])->toBe(array('color_template_item_id' => '2', 'color_template_id' => '2', 'color_id' => '1', 'sequence' => '1'));
});
