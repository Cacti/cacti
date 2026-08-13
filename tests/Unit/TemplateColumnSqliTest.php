<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/graph_template_input.php';

test('graph template inputs allow only editable 1.2.x graph item fields', function () {
	$allowed = array(
		'graph_type_id',
		'task_item_id',
		'color_id',
		'alpha',
		'consolidation_function_id',
		'cdef_id',
		'vdef_id',
		'shift',
		'value',
		'gprint_id',
		'textalign',
		'text_format',
		'hard_return',
		'line_width',
		'dashes',
		'dash_offset',
		'sequence',
	);

	foreach ($allowed as $column_name) {
		expect(graph_template_input_column_is_allowed($column_name))->toBeTrue();
	}
});

test('graph template inputs reject structural columns and SQL syntax', function () {
	$rejected = array(
		'id',
		'hash',
		'local_graph_id',
		'local_graph_template_item_id',
		'graph_template_id',
		'%',
		'_',
		"task_item_id' OR 1=1 --",
		'',
		null,
	);

	foreach ($rejected as $column_name) {
		expect(graph_template_input_column_is_allowed($column_name))->toBeFalse();
	}
});

test('every 1.2.x graph template input handoff uses the application allowlist', function () {
	$root = dirname(__DIR__, 2);
	$files = array(
		'graph_templates_inputs.php',
		'graphs.php',
		'lib/api_graph.php',
		'lib/html_form_template.php',
		'lib/import.php',
		'lib/template.php',
	);

	foreach ($files as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)
			->toBeString()
			->toContain('graph_template_input_column_is_allowed(');
	}
});

test('the 1.2.x push out sinks use only the validated local identifier', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/template.php');

	expect(preg_match('/function push_out_graph_input\(.*?\n}\n/s', $source, $matches))->toBe(1);
	$body = $matches[0];

	$guard  = strpos($body, 'graph_template_input_column_is_allowed(');
	$select = strpos($body, "SELECT local_graph_id,'");
	$update = strpos($body, 'UPDATE graph_templates_item');

	expect($guard)->not->toBeFalse();
	expect($select)->not->toBeFalse();
	expect($update)->not->toBeFalse();
	expect($guard)->toBeLessThan($select);
	expect($guard)->toBeLessThan($update);
	expect($body)->toContain("'SELECT local_graph_id,' . \$column_name . '");
	expect($body)->toContain("SET ' . \$column_name . ' = ?");
	expect($body)->not->toContain("SET ' . \$graph_input['column_name']");
});

test('1.2.x XML import validates graph input fields before its first template write', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/import.php');

	expect(preg_match('/function xml_to_graph_template\(.*?\n}\n/s', $source, $matches))->toBe(1);
	$body = $matches[0];
	$gate = strpos($body, 'graph_template_input_column_is_allowed(');
	$save = strpos($body, "sql_save(\$save, 'graph_templates')");

	expect($gate)->not->toBeFalse();
	expect($save)->not->toBeFalse();
	expect($gate)->toBeLessThan($save);
});

test('1.2.x graph saves preflight every input before updating graph items', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/graphs.php');

	$guard = strpos($source, 'graph_template_input_column_is_allowed(');
	$abort = strpos($source, '$input_list = array();', $guard);
	$write = strpos($source, 'UPDATE graph_templates_item', $guard);

	expect($guard)->not->toBeFalse();
	expect($abort)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($guard)->toBeLessThan($abort);
	expect($abort)->toBeLessThan($write);
});

test('1.2.x graph input values are validated by field shape', function () {
	expect(graph_template_input_value_is_allowed('task_item_id', '42'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('color_id', '12'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('color_id', '16777215'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('alpha', '7F'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('line_width', '1,25'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('dash_offset', '-8388608'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('sequence', '16777215'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('text_format', "safe\ttext"))->toBeTrue();
	expect(graph_template_input_value_is_allowed('task_item_id', '1 OR 1=1'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('color_id', 'ABCDEF'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('color_id', '16777216'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('alpha', 'FFFF'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('dash_offset', '1.5'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('dash_offset', '8388608'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('sequence', '16777216'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('text_format', "bad\0text"))->toBeFalse();
});

test('1.2.x graph input propagation preflights every value before updating', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/template.php');

	expect(preg_match('/function push_out_graph_input\(.*?\n}\n/s', $source, $matches))->toBe(1);
	$body = $matches[0];
	$validation = strpos($body, 'graph_template_input_value_is_allowed(');
	$abort      = strpos($body, 'return;', $validation);
	$write      = strpos($body, 'UPDATE graph_templates_item', $validation);

	expect($validation)->not->toBeFalse();
	expect($abort)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($validation)->toBeLessThan($abort);
	expect($abort)->toBeLessThan($write);
});

test('1.2.x graph input deletion requires a CSRF protected POST', function () {
	$handler = file_get_contents(dirname(__DIR__, 2) . '/graph_templates_inputs.php');
	$ui      = file_get_contents(dirname(__DIR__, 2) . '/graph_templates.php');

	expect($handler)->toContain("\$_SERVER['REQUEST_METHOD'] !== 'POST'");
	expect($ui)
		->toContain("loadPageUsingPost('graph_templates_inputs.php'")
		->toContain('__csrf_magic: csrfMagicToken')
		->not->toContain('graph_templates_inputs.php?action=input_remove');
});

test('1.2.x input mutations validate ownership and use transactions', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/graph_templates_inputs.php');

	expect($source)
		->toContain('graph_template_input_relationships_are_valid(')
		->toContain('db_begin_transaction()')
		->toContain('db_rollback_transaction()')
		->toContain('db_commit_transaction()')
		->toContain('AND graph_template_id = ?');
});

test('1.2.x integrity audit is read only', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/cli/audit_graph_template_inputs.php');

	expect($source)
		->toContain('cross-template definitions')
		->toContain('orphaned definitions')
		->not->toMatch('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/');
});
