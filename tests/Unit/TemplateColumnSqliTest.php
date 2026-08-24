<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once CACTI_PATH_LIBRARY . '/graph_template_input.php';

test('graph template inputs allow only editable graph item fields', function () {
	$allowed = [
		'graph_type_id',
		'task_item_id',
		'color_id',
		'alpha',
		'color2_id',
		'alpha2',
		'gradheight',
		'consolidation_function_id',
		'cdef_id',
		'vdef_id',
		'shift',
		'value',
		'gprint_id',
		'textalign',
		'text_format',
		'legend',
		'hard_return',
		'line_width',
		'dashes',
		'dash_offset',
		'sequence',
	];

	foreach ($allowed as $column_name) {
		expect(graph_template_input_column_is_allowed($column_name))->toBeTrue();
	}
});

test('graph template inputs reject structural columns and SQL syntax', function () {
	$rejected = [
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
	];

	foreach ($rejected as $column_name) {
		expect(graph_template_input_column_is_allowed($column_name))->toBeFalse();
	}
});

test('every graph template input handoff uses the application allowlist', function () {
	$root  = dirname(__DIR__, 2);
	$files = [
		'graph_templates.php',
		'graphs.php',
		'lib/api_graph.php',
		'lib/html_form_template.php',
		'lib/import.php',
		'lib/template.php',
	];

	foreach ($files as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)
			->toBeString()
			->toContain('graph_template_input_column_is_allowed(');
	}
});

test('the push out sinks use only the validated local identifier', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/template.php');

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

test('XML import validates graph input fields before its first template write', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/import.php');

	expect(preg_match('/function xml_to_graph_template\(.*?\n}\n/s', $source, $matches))->toBe(1);
	$body = $matches[0];
	$gate = strpos($body, 'graph_template_input_column_is_allowed(');
	$save = strpos($body, "sql_save(\$save, 'graph_templates')");

	expect($gate)->not->toBeFalse();
	expect($save)->not->toBeFalse();
	expect($gate)->toBeLessThan($save);
});

test('graph saves preflight every input before updating graph items', function () {
	$source = file_get_contents(CACTI_PATH_BASE . '/graphs.php');

	$guard = strpos($source, 'graph_template_input_column_is_allowed(');
	$abort = strpos($source, '$input_list = [];', $guard);
	$write = strpos($source, 'UPDATE graph_templates_item', $guard);

	expect($guard)->not->toBeFalse();
	expect($abort)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($guard)->toBeLessThan($abort);
	expect($abort)->toBeLessThan($write);
});

test('graph input values are validated by field shape', function () {
	expect(graph_template_input_value_is_allowed('task_item_id', '42'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('alpha', '7F'))->toBeTrue();
	expect(graph_template_input_value_is_allowed('text_format', "safe\ttext"))->toBeTrue();
	expect(graph_template_input_value_is_allowed('task_item_id', '1 OR 1=1'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('alpha', 'FFFF'))->toBeFalse();
	expect(graph_template_input_value_is_allowed('text_format', "bad\0text"))->toBeFalse();
});

test('input mutations validate ownership and use transactions', function () {
	$source = file_get_contents(CACTI_PATH_BASE . '/graph_templates.php');

	expect($source)
		->toContain('graph_template_input_relationships_are_valid(')
		->toContain('db_begin_transaction()')
		->toContain('db_rollback_transaction()')
		->toContain('db_commit_transaction()')
		->toContain('AND graph_template_id = ?');
});

test('integrity audit is read only', function () {
	$source = file_get_contents(CACTI_PATH_BASE . '/cli/audit_graph_template_inputs.php');

	expect($source)
		->toContain('cross-template definitions')
		->toContain('orphaned definitions')
		->not->toMatch('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/');
});
