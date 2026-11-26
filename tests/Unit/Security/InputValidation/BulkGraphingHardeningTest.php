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

namespace CactiBulkGraphingHardeningTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 4);

$GLOBALS['bulk_vdef_items']     = array();
$GLOBALS['bulk_vdef_logs']      = array();
$GLOBALS['bulk_vdef_functions'] = array(1 => 'MAXIMUM', 2 => 'MINIMUM', 3 => 'AVERAGE');
$GLOBALS['bulk_tpl_inputs']     = array();
$GLOBALS['bulk_ds_queries']     = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['bulk_vdef_logs'][] = $facility . ': ' . $message;
}

function db_qstr($value) {
	return "'" . str_replace("'", "\\'", (string) $value) . "'";
}

function db_execute($sql) {
	$GLOBALS['bulk_ds_queries'][] = $sql;
	return true;
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM vdef_items') !== false) {
		$vid = (int) $params[0];
		return isset($GLOBALS['bulk_vdef_items'][$vid]) ? $GLOBALS['bulk_vdef_items'][$vid] : array();
	}

	if (strpos($sql, 'FROM data_template_rrd') !== false) {
		return isset($GLOBALS['bulk_tpl_inputs']['ids']) ? $GLOBALS['bulk_tpl_inputs']['ids'] : array();
	}

	return array();
}

function db_fetch_row_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM vdef_items WHERE id = ?') !== false) {
		$item_id = (int) $params[0];
		foreach ($GLOBALS['bulk_vdef_items'] as $items) {
			foreach ($items as $item) {
				if ((int) $item['id'] === $item_id) {
					return $item;
				}
			}
		}
		return array();
	}

	if (strpos($sql, 'FROM data_input') !== false) {
		$did = (int) $params[0];
		return isset($GLOBALS['bulk_tpl_inputs']['rows'][$did]) ? $GLOBALS['bulk_tpl_inputs']['rows'][$did] : array();
	}

	return array();
}

function graph_template_input_column_is_allowed($column_name) {
	return \graph_template_input_column_is_allowed($column_name);
}

function graph_template_input_value_is_allowed($column_name, $value) {
	return \graph_template_input_value_is_allowed($column_name, $value);
}

require_once $root . '/lib/graph_template_input.php';

if (!function_exists(__NAMESPACE__ . '\\get_vdef_item_name')) {
	\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef_item_name', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef_recursive', __NAMESPACE__);
}

if (!function_exists(__NAMESPACE__ . '\\template_suggested_field_is_allowed')) {
	\cacti_fixture_source_function($root . '/lib/template.php', 'template_suggested_field_is_allowed', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/template.php', 'graph_template_whitelist_check', __NAMESPACE__);
}

if (!function_exists(__NAMESPACE__ . '\\api_data_source_suggested_value_target')) {
	\cacti_fixture_source_function($root . '/lib/api_data_source.php', 'api_data_source_suggested_value_target', __NAMESPACE__);
}

if (!function_exists(__NAMESPACE__ . '\\dsstats_format_sql_numeric')) {
	\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_format_sql_numeric', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_write_buffer', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['bulk_vdef_items'] = array();
	$GLOBALS['bulk_vdef_logs']  = array();
	$GLOBALS['vdef_functions']  = $GLOBALS['bulk_vdef_functions'];
	$GLOBALS['bulk_tpl_inputs'] = array();
	$GLOBALS['bulk_ds_queries'] = array();
});

test('template_suggested_field_is_allowed accepts editable fields and blocks structural columns', function () {
	expect(template_suggested_field_is_allowed('graph_templates_graph', 'title', 'CPU Usage'))->toBeTrue()
		->and(template_suggested_field_is_allowed('graph_templates_graph', 'height', '120'))->toBeTrue()
		->and(template_suggested_field_is_allowed('graph_templates_graph', 'local_graph_id', '99'))->toBeFalse()
		->and(template_suggested_field_is_allowed('graph_templates_graph', 'graph_template_id', '5'))->toBeFalse()
		->and(template_suggested_field_is_allowed('graph_templates_item', 'color_id', '12'))->toBeTrue()
		->and(template_suggested_field_is_allowed('graph_templates_item', 'task_item_id', '5'))->toBeFalse()
		->and(template_suggested_field_is_allowed('graph_templates_item', 'local_graph_id', '5'))->toBeFalse()
		->and(template_suggested_field_is_allowed('data_template_data', 'name', 'Traffic In'))->toBeTrue()
		->and(template_suggested_field_is_allowed('data_template_data', 'active', 'on'))->toBeTrue()
		->and(template_suggested_field_is_allowed('data_template_data', 'data_source_path', '/etc/passwd'))->toBeFalse()
		->and(template_suggested_field_is_allowed('data_template_data', 'data_input_id', '2'))->toBeFalse()
		->and(template_suggested_field_is_allowed('data_template_rrd', 'rrd_maximum', 'U'))->toBeTrue()
		->and(template_suggested_field_is_allowed('data_template_rrd', 'rrd_maximum', '100000'))->toBeTrue()
		->and(template_suggested_field_is_allowed('data_template_rrd', 'rrd_maximum', 'not-a-number'))->toBeFalse()
		->and(template_suggested_field_is_allowed('data_template_rrd', 'data_source_name', 'injected'))->toBeFalse()
		->and(template_suggested_field_is_allowed('unknown_table', 'name', 'x'))->toBeFalse();
});

test('lib/template.php checks whitelist before test_data_sources and gates custom_data by t_value', function () use ($root) {
	$source = file_get_contents($root . '/lib/template.php');

	expect($source)->toContain("if (!graph_template_whitelist_check(\$graph_template_id))")
		->and($source)->toContain("AND did_tpl.t_value = 'on'")
		->and($source)->toContain("if (!is_array(\$decoded))")
		->and($source)->toContain("elseif (trim((string) \$data_input['input_string']) !== '')");
});

test('get_vdef resolves valid items and terminates safely on direct and indirect cycles', function () {
	$GLOBALS['bulk_vdef_items'] = array(
		1 => array(
			array('id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'),
			array('id' => 11, 'type' => '1', 'value' => '1'),
		),
		2 => array(
			array('id' => 20, 'type' => '5', 'value' => '2'),
		),
		3 => array(
			array('id' => 30, 'type' => '5', 'value' => '4'),
		),
		4 => array(
			array('id' => 40, 'type' => '5', 'value' => '3'),
		),
	);

	expect(get_vdef(1))->toBe('CURRENT_DATA_SOURCE,MAXIMUM')
		->and(get_vdef(2))->toBe('')
		->and(get_vdef(3))->toBe('')
		->and($GLOBALS['bulk_vdef_logs'])->not->toBeEmpty();
});

test('vdef.php validates type and value on save and scopes item deletion to vdef_id', function () use ($root) {
	$source = file_get_contents($root . '/vdef.php');

	expect($source)->toContain("if (!array_key_exists(\$save['type'], \$vdef_item_types))")
		->and($source)->toContain("get_filter_request_var('id');")
		->and($source)->toContain("WHERE id = ?\n\t\tAND vdef_id = ?");
});

test('api_data_source_suggested_value_target allows only permitted columns and valid values', function () {
	expect(api_data_source_suggested_value_target('name', 'Interface Traffic'))->toBe('data_template_data')
		->and(api_data_source_suggested_value_target('active', 'on'))->toBe('data_template_data')
		->and(api_data_source_suggested_value_target('rrd_step', '300'))->toBe('data_template_data')
		->and(api_data_source_suggested_value_target('rrd_maximum', 'U'))->toBe('data_template_rrd')
		->and(api_data_source_suggested_value_target('rrd_minimum', '0'))->toBe('data_template_rrd')
		->and(api_data_source_suggested_value_target('rrd_heartbeat', '600'))->toBe('data_template_rrd')
		->and(api_data_source_suggested_value_target('data_source_path', '/tmp/x.rrd'))->toBeFalse()
		->and(api_data_source_suggested_value_target('data_input_id', '1'))->toBeFalse()
		->and(api_data_source_suggested_value_target('data_source_name', '<b>x</b>'))->toBeFalse()
		->and(api_data_source_suggested_value_target('rrd_maximum', 'abc'))->toBeFalse()
		->and(api_data_source_suggested_value_target('active', 'yes'))->toBeFalse();
});

test('automation_graph_rules.php enforces strict column regex without inverted db_qstr guard', function () use ($root) {
	$source = file_get_contents($root . '/automation_graph_rules.php');

	expect($source)->not->toContain("if (\"'\$field_name'\" != db_qstr(\$field_name))")
		->and($source)->toContain("preg_match('/^(?:(?:h|ht|gt|gtg)\\.)?[A-Za-z0-9_]+$/', \$save['field'])")
		->and($source)->toContain("preg_match('/^(?:(?:h|ht|gt|gl|gtg)\\.)?[A-Za-z0-9_]+$/', \$save['field'])");
});

test('dsstats_format_sql_numeric and dsstats_write_buffer sanitize bulk SQL inserts', function () {
	expect(dsstats_format_sql_numeric('42.5'))->toBe('42.5')
		->and(dsstats_format_sql_numeric('NaN'))->toBe('NULL')
		->and(dsstats_format_sql_numeric('U'))->toBe('NULL')
		->and(dsstats_format_sql_numeric("1'); DROP TABLE host;--"))->toBe('NULL');

	$stats = array(
		'15' => array(
			"traffic'in" => array('AVG' => '12.5', 'MAX' => "bad'sql"),
		),
	);

	dsstats_write_buffer($stats, 'invalid_table');
	expect($GLOBALS['bulk_ds_queries'])->toBeEmpty();

	dsstats_write_buffer($stats, 'daily');
	expect($GLOBALS['bulk_ds_queries'])->toHaveCount(1)
		->and($GLOBALS['bulk_ds_queries'][0])->toContain("(15,'traffic\\'in',12.5,NULL)")
		->and($GLOBALS['bulk_ds_queries'][0])->not->toContain("bad'sql");
});

test('external links enforce enabled state, preserve disabled state on edit, and sandbox console iframes', function () use ($root) {
	$linkSource  = file_get_contents($root . '/link.php');
	$linksSource = file_get_contents($root . '/links.php');
	$indexSource = file_get_contents($root . '/index.php');

	expect($linkSource)->toContain('AND enabled = "on"')
		->and($linksSource)->toContain("(isset(\$data['enabled']) ? \$data['enabled']:'')")
		->and($indexSource)->toContain("filter_var(\$page['contentfile'], FILTER_VALIDATE_URL)")
		->and($indexSource)->toContain('sandbox="allow-scripts allow-popups allow-forms"');
});
