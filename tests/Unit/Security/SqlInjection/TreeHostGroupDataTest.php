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
 * Tree host_group_data (hgd) selects a graph template, data query, or data
 * query index on the tree right pane. Numeric identifiers parsed from hgd
 * must be coerced to integers before querying snmp_query/graph_templates or
 * calling get_host_graph_list(), and get_host_graph_list() must bind host_id
 * and snmp_query id through prepared parameters.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function tree_hgd_probe(array $payload) {
	return cacti_test_isolated_probe(
		dirname(__DIR__, 3) . '/fixtures/Core/TreeHostGroupDataProbe.php',
		array(json_encode($payload, JSON_THROW_ON_ERROR))
	);
}

test('grow_right_pane_tree coerces dq identifier to an integer and binds it in get_host_graph_list', function () {
	$verdict = tree_hgd_probe(array(
		'mode' => 'pane',
		'hgd'  => 'dq:5 OR 1=1',
	));

	expect($verdict['raw_assoc_queries'])->toBeEmpty();
	expect($verdict['assoc_queries'])->toHaveCount(1);
	expect($verdict['assoc_queries'][0]['sql'])->toContain('WHERE gl.host_id = ? AND sq.id = ?');
	expect($verdict['assoc_queries'][0]['params'])->toBe(array(12, 5));

	$snmp_queries = array_values(array_filter(
		$verdict['cell_queries'],
		static fn (array $query) => str_contains($query['sql'], 'FROM snmp_query')
	));
	expect($snmp_queries)->toHaveCount(1);
	expect($snmp_queries[0]['params'])->toBe(array(5));
});

test('grow_right_pane_tree coerces dqi identifier to an integer for formatting and query lookup', function () {
	$verdict = tree_hgd_probe(array(
		'mode' => 'pane',
		'hgd'  => 'dqi:3 UNION SELECT 1:eth0',
	));

	expect($verdict['raw_assoc_queries'])->toBeEmpty();
	expect($verdict['assoc_queries'])->toHaveCount(1);
	expect($verdict['assoc_queries'][0]['params'])->toBe(array(12, 3));
	expect($verdict['dqi_calls'])->toBe(array(
		array(
			'host_id'          => 12,
			'data_query_id'    => 3,
			'data_query_index' => 'eth0',
		),
	));
});

test('grow_right_pane_tree coerces gt identifier to an integer', function () {
	$verdict = tree_hgd_probe(array(
		'mode'     => 'pane',
		'hgd'      => 'gt:7 OR 1=1',
		'grouping' => 1,
	));

	$gt_queries = array_values(array_filter(
		$verdict['cell_queries'],
		static fn (array $query) => str_contains($query['sql'], 'FROM graph_templates')
	));
	expect($gt_queries)->toHaveCount(1);
	expect($gt_queries[0]['params'])->toBe(array(7));
	expect($verdict['allowed_wheres'])->toHaveCount(1);
	expect($verdict['allowed_wheres'][0])->toContain('gl.graph_template_id IN (7, 0)');
});

test('grow_right_pane_tree handles truncated hgd segments without warnings', function (string $hgd, bool $expect_non_query, bool $expect_template_base) {
	$verdict = tree_hgd_probe(array(
		'mode' => 'pane',
		'hgd'  => $hgd,
	));

	expect($verdict['raw_assoc_queries'])->toBeEmpty();
	expect($verdict['has_non_query'])->toBe($expect_non_query);
	expect($verdict['has_template_base'])->toBe($expect_template_base);
})->with(array(
	'missing dq id'     => array('dq', true, false),
	'zero dq id'        => array('dq:0', true, false),
	'missing dqi parts' => array('dqi', true, true),
	'missing dqi index' => array('dqi:5', false, true),
	'missing gt id'     => array('gt', false, false),
));

test('get_host_graph_list preserves default and empty data_query_id behavior while binding numeric ids', function () {
	$default = tree_hgd_probe(array(
		'mode'          => 'list',
		'host_id'       => '12 OR 1=1',
		'data_query_id' => '-1',
	));

	expect($default['raw_assoc_queries'])->toBeEmpty();
	expect($default['assoc_queries'][0]['sql'])->toContain('WHERE gl.host_id = ?');
	expect($default['assoc_queries'][0]['sql'])->not->toContain('sq.id = ?');
	expect($default['assoc_queries'][0]['params'])->toBe(array(12));
	expect($default['allowed_wheres'])->toContain('gl.snmp_query_id=0 AND gl.host_id=12 ');

	$empty = tree_hgd_probe(array(
		'mode'          => 'list',
		'host_id'       => 0,
		'data_query_id' => '',
	));

	expect($empty['raw_assoc_queries'])->toBeEmpty();
	expect($empty['assoc_queries'][0]['params'])->toBe(array());
	expect($empty['allowed_wheres'])->toContain('gl.snmp_query_id=0 ');

	$crafted = tree_hgd_probe(array(
		'mode'          => 'list',
		'host_id'       => '0',
		'data_query_id' => '9 OR 1=1',
	));

	expect($crafted['raw_assoc_queries'])->toBeEmpty();
	expect($crafted['assoc_queries'][0]['sql'])->toContain('WHERE sq.id = ?');
	expect($crafted['assoc_queries'][0]['params'])->toBe(array(9));
});
