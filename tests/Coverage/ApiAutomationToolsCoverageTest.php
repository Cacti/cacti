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

if (!defined('TREE_ITEM_TYPE_HEADER')) {
	define('TREE_ITEM_TYPE_HEADER', 1);
}

if (!defined('TREE_ITEM_TYPE_GRAPH')) {
	define('TREE_ITEM_TYPE_GRAPH', 2);
}

if (!defined('TREE_ITEM_TYPE_HOST')) {
	define('TREE_ITEM_TYPE_HOST', 3);
}

$GLOBALS['automation_assoc_results'] = [];
$GLOBALS['automation_cell_results']  = [];
$GLOBALS['automation_queries']       = [];
$GLOBALS['tree_sort_types']          = [1 => 'Manual'];
$GLOBALS['tree_item_types']          = [1 => 'Header', 2 => 'Graph', 3 => 'Host'];
$GLOBALS['host_group_types']         = [1 => 'Graph Template'];

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $value) : int {
		return is_array($value) ? count($value) : 0;
	}
}

function db_fetch_assoc(string $sql) : array|false {
	$GLOBALS['automation_queries'][] = [$sql, []];

	return array_shift($GLOBALS['automation_assoc_results']);
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array|false {
	$GLOBALS['automation_queries'][] = [$sql, $params];

	return array_shift($GLOBALS['automation_assoc_results']);
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	$GLOBALS['automation_queries'][] = [$sql, $params];

	return array_shift($GLOBALS['automation_cell_results']);
}

require_once CACTI_PATH_LIBRARY . '/api_automation_tools.php';

beforeEach(function () : void {
	$GLOBALS['automation_assoc_results'] = [];
	$GLOBALS['automation_cell_results']  = [];
	$GLOBALS['automation_queries']       = [];
});

test('automation lookup helpers map database rows and bind optional filters', function () : void {
	$GLOBALS['automation_assoc_results'] = [
		[['id' => 2, 'name' => 'Linux']],
		[['id' => 3, 'description' => 'router-a']],
		[['id' => 4, 'name' => 'DC']],
		[['id' => 5, 'hostname' => 'host-a', 'description' => 'Host A', 'host_template_id' => 2]],
		[['data_template_id' => 6, 'name' => 'community', 'default' => 'public', 'description' => 'Community']],
		[['id' => 7, 'hostname' => '192.0.2.7']],
		[['field_name' => 'ifName']],
		[['field_value' => 'eth0']],
		[['id' => 8, 'name' => 'Interfaces']],
		[['id' => 9, 'name' => 'Interface Statistics']],
		[['id' => 10, 'name' => 'Traffic']],
		[['id' => 11, 'name' => 'Traffic']],
		[],
		[],
		[],
		[],
		[]
	];

	expect(getHostTemplates())->toBe([0 => 'None', 2 => 'Linux'])
		->and(getHostsByDescription([2]))->toBe(['router-a' => 3])
		->and(getSites())->toBe([4 => ['id' => 4, 'name' => 'DC']])
		->and(getHosts([2]))->toBe([5 => ['id' => 5, 'hostname' => 'host-a', 'description' => 'Host A', 'host_template_id' => 2]])
		->and(getInputFields(10))->toHaveKey('6:community')
		->and(getAddresses())->toBe(['192.0.2.7' => 7])
		->and(getSNMPFields(5, 8))->toBe(['ifName' => 1])
		->and(getSNMPValues(5, 'ifName', 8))->toBe(['eth0' => 1])
		->and(getSNMPQueries())->toBe([8 => 'Interfaces'])
		->and(getSNMPQueryTypes(8))->toBe([9 => 'Interface Statistics'])
		->and(getGraphTemplates())->toBe([10 => 'Traffic'])
		->and(getGraphTemplatesByHostTemplate([2]))->toBe([11 => 'Traffic'])
		->and(getHostsByDescription())->toBe([])
		->and(getHosts())->toBe([])
		->and(getGraphTemplatesByHostTemplate())->toBe([])
		->and(getSNMPFields(5))->toBe([])
		->and(getSNMPValues(5, 'ifName'))->toBe([])
		->and(getHostsByDescription(['invalid']))->toBeFalse()
		->and(getHosts(['invalid']))->toBeFalse()
		->and(getGraphTemplatesByHostTemplate(['invalid']))->toBeFalse();

	expect($GLOBALS['automation_queries'][1][1])->toBe([2])
		->and($GLOBALS['automation_queries'][6][1])->toBe([5, 8])
		->and($GLOBALS['automation_queries'][7][1])->toBe([5, 'ifName', 8]);

	expect(automation_prepare_id_list(2))->toBe(['placeholders' => '?', 'params' => [2]])
		->and(automation_prepare_id_list('0002'))->toBe(['placeholders' => '?', 'params' => [2]])
		->and(automation_prepare_id_list([2, '02', 3]))->toBe(['placeholders' => '?, ?', 'params' => [2, 3]])
		->and(automation_prepare_id_list((string) PHP_INT_MAX . '0'))->toBeFalse();
});

test('automation display helpers render every supported record type', function () : void {
	$GLOBALS['automation_assoc_results'] = [
		[['snmp_community' => 'public']],
		[['id' => 1, 'sort_type' => 1, 'name' => 'Main']],
		[
			['id' => 1, 'parent' => 0, 'local_graph_id' => 0, 'title' => 'Network', 'host_id' => 0, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => null, 'host_name' => null],
			['id' => 2, 'parent' => 0, 'local_graph_id' => 22, 'title' => '', 'host_id' => 0, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => 'Root Graph', 'host_name' => null],
			['id' => 3, 'parent' => 0, 'local_graph_id' => 0, 'title' => '', 'host_id' => 33, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => null, 'host_name' => 'router-a'],
			['id' => 4, 'parent' => 1, 'local_graph_id' => 0, 'title' => 'Subnet', 'host_id' => 0, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => null, 'host_name' => null],
			['id' => 5, 'parent' => 1, 'local_graph_id' => 44, 'title' => '', 'host_id' => 0, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => 'Nested Graph', 'host_name' => null],
			['id' => 6, 'parent' => 1, 'local_graph_id' => 0, 'title' => '', 'host_id' => 66, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => null, 'host_name' => 'router-b'],
			['id' => 1, 'parent' => 1, 'local_graph_id' => 0, 'title' => 'Cycle', 'host_id' => 0, 'host_grouping_type' => 1, 'sort_children_type' => 1, 'graph_title' => null, 'host_name' => null]
		],
		[['id' => 1, 'steps' => 1, 'rows' => 600, 'name' => 'Daily']],
		[['id' => 1, 'name' => 'Traffic', 'template_name' => 'Interface']],
		[['id' => 1, 'username' => 'admin', 'full_name' => 'Administrator']],
		[['id' => 1, 'name' => 'Operators', 'description' => 'Ops']]
	];
	ob_start();
	displayQueryTypes([1 => 'Interface']);
	displayHostTemplates([2 => 'Linux']);
	displayCommunities();
	displaySNMPFields(['ifName' => 1], 5);
	displaySNMPValues(['eth0' => 1], 5, 'ifName');
	displaySNMPQueries([8 => 'Interfaces']);
	displayInputFields([['data_template_id' => 6, 'name' => 'community', 'default' => 'public', 'description' => 'Community']]);
	displayGraphTemplates([10 => 'Traffic']);
	displayHosts([['id' => 5, 'hostname' => 'host-a', 'host_template_id' => 2, 'description' => 'Host A']]);
	displaySites([['id' => 4, 'name' => 'DC']]);
	displayTrees();
	displayTreeNodes(1);
	displayRRAs();
	displayHostGraphs(5);
	displayUsers();
	displayGroups();
	$output = ob_get_clean();

	expect($output)->toContain('Known SNMP Query Types')
		->toContain('public')
		->toContain('Nested Graph')
		->toContain('Root Graph')
		->toContain('router-a')
		->toContain('Daily')
		->toContain('Administrator')
		->toContain('Operators');
});

test('quiet and empty display inputs produce no output', function () : void {
	$GLOBALS['automation_assoc_results'] = [false, [], [], [], [], [], []];

	ob_start();
	displayQueryTypes(false, true);
	displayHostTemplates(false, true);
	displayCommunities(true);
	displaySNMPFields(false, 1, true);
	displaySNMPValues(false, 1, 'field', true);
	displaySNMPQueries(false, true);
	displayInputFields(false, true);
	displayGraphTemplates(false, true);
	displayHosts(false, true);
	displaySites(false, true);
	displayTrees(true);
	displayTreeNodes(1, 'graph', 1, true);
	displayRRAs(true);
	displayHostGraphs(1, true);
	displayUsers(true);
	displayGroups(true);
	$output = ob_get_clean();

	expect($output)->toBe('');
});
