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
