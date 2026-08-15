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
 * Tests for gprint_format field inclusion in aggregate graph creation.
 *
 * Two code paths in graphs.php build $ag_data for aggregate graphs:
 *   - Non-template (action 9): reads gprint_format from POST via
 *     isset_request_var(), yielding 'on' or ''.
 *   - Template (action 10): copies gprint_format from the template row.
 *
 * Before the fix, both paths omitted gprint_format, so aggregate graphs
 * lost the "use prefix for GPRINT lines" setting.
 *
 * These tests verify the field-mapping logic using inline stubs that
 * mirror the production code without requiring a database or HTTP layer.
 */

/**
 * Mirrors the non-template path: isset_request_var() returns true/false
 * for a checkbox field, mapped to 'on' or ''.
 */
function build_ag_data_no_template(array $request_vars): array {
	$ag_data = [];

	$ag_data['aggregate_template_id'] = 0;
	$ag_data['template_propogation']  = '';
	$ag_data['gprint_prefix']         = $request_vars['gprint_prefix'] ?? '';
	$ag_data['gprint_format']         = isset($request_vars['gprint_format']) ? 'on' : '';
	$ag_data['graph_type']            = $request_vars['aggregate_graph_type'] ?? 0;
	$ag_data['total']                 = $request_vars['aggregate_total'] ?? 0;
	$ag_data['total_type']            = $request_vars['aggregate_total_type'] ?? 0;
	$ag_data['total_prefix']          = $request_vars['aggregate_total_prefix'] ?? '';
	$ag_data['order_type']            = $request_vars['aggregate_order_type'] ?? 0;

	return $ag_data;
}

/**
 * Mirrors the template path: gprint_format is copied directly from the
 * template row returned by db_fetch_row_prepared().
 */
function build_ag_data_from_template(array $template_data): array {
	$ag_data = [];

	$ag_data['aggregate_template_id'] = $template_data['id'] ?? 0;
	$ag_data['template_propogation']  = 'on';
	$ag_data['gprint_prefix']         = $template_data['gprint_prefix'];
	$ag_data['gprint_format']         = $template_data['gprint_format'];
	$ag_data['graph_type']            = $template_data['graph_type'];
	$ag_data['total']                 = $template_data['total'];
	$ag_data['total_type']            = $template_data['total_type'];
	$ag_data['total_prefix']          = $template_data['total_prefix'];
	$ag_data['order_type']            = $template_data['order_type'];

	return $ag_data;
}

// --- Non-template path: gprint_format checkbox present ---

test('non-template path sets gprint_format to on when checkbox is present', function () {
	$request = [
		'gprint_prefix'         => 'host',
		'gprint_format'         => 'on',
		'aggregate_graph_type'  => 1,
		'aggregate_total'       => 1,
		'aggregate_total_type'  => 1,
		'aggregate_total_prefix' => 'Total',
		'aggregate_order_type'  => 1,
	];

	$ag_data = build_ag_data_no_template($request);

	expect($ag_data)->toHaveKey('gprint_format')
		->and($ag_data['gprint_format'])->toBe('on');
});

// --- Non-template path: gprint_format checkbox absent ---

test('non-template path sets gprint_format to empty when checkbox is absent', function () {
	$request = [
		'gprint_prefix'         => 'host',
		'aggregate_graph_type'  => 1,
		'aggregate_total'       => 0,
		'aggregate_total_type'  => 0,
		'aggregate_total_prefix' => '',
		'aggregate_order_type'  => 1,
	];

	$ag_data = build_ag_data_no_template($request);

	expect($ag_data)->toHaveKey('gprint_format')
		->and($ag_data['gprint_format'])->toBe('');
});

// --- Template path: gprint_format copied from template row ---

test('template path copies gprint_format on from template data', function () {
	$template_data = [
		'id'            => 5,
		'gprint_prefix' => 'host',
		'gprint_format' => 'on',
		'graph_type'    => 1,
		'total'         => 1,
		'total_type'    => 1,
		'total_prefix'  => 'Total',
		'order_type'    => 1,
	];

	$ag_data = build_ag_data_from_template($template_data);

	expect($ag_data)->toHaveKey('gprint_format')
		->and($ag_data['gprint_format'])->toBe('on');
});

test('template path copies gprint_format empty from template data', function () {
	$template_data = [
		'id'            => 7,
		'gprint_prefix' => 'host',
		'gprint_format' => '',
		'graph_type'    => 1,
		'total'         => 0,
		'total_type'    => 0,
		'total_prefix'  => '',
		'order_type'    => 1,
	];

	$ag_data = build_ag_data_from_template($template_data);

	expect($ag_data)->toHaveKey('gprint_format')
		->and($ag_data['gprint_format'])->toBe('');
});

// --- Both paths produce the same field set ---

test('both paths produce identical ag_data keys', function () {
	$request = [
		'gprint_prefix'         => 'host',
		'gprint_format'         => 'on',
		'aggregate_graph_type'  => 1,
		'aggregate_total'       => 1,
		'aggregate_total_type'  => 1,
		'aggregate_total_prefix' => 'Total',
		'aggregate_order_type'  => 1,
	];

	$template_data = [
		'id'            => 5,
		'gprint_prefix' => 'host',
		'gprint_format' => 'on',
		'graph_type'    => 1,
		'total'         => 1,
		'total_type'    => 1,
		'total_prefix'  => 'Total',
		'order_type'    => 1,
	];

	$no_tpl = build_ag_data_no_template($request);
	$from_tpl = build_ag_data_from_template($template_data);

	$no_tpl_keys = array_keys($no_tpl);
	$from_tpl_keys = array_keys($from_tpl);
	sort($no_tpl_keys);
	sort($from_tpl_keys);

	expect($no_tpl_keys)->toBe($from_tpl_keys);
});

// --- Non-template checkbox: isset vs empty distinction ---

test('non-template path treats empty string gprint_format as present', function () {
	/* HTML checkboxes submit 'on', but test that isset() catches any value */
	$request = [
		'gprint_prefix'         => '',
		'gprint_format'         => '',
		'aggregate_graph_type'  => 0,
		'aggregate_total'       => 0,
		'aggregate_total_type'  => 0,
		'aggregate_total_prefix' => '',
		'aggregate_order_type'  => 0,
	];

	$ag_data = build_ag_data_no_template($request);

	/* key is set in the array, so isset() returns true -> 'on' */
	expect($ag_data['gprint_format'])->toBe('on');
});
