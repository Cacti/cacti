<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * The Graph Perms tab of a user group reads only enabled groups, so a
 * disabled group has no policy. The select columns and the filter built from
 * an empty policy list must still form a valid query, and since no policy
 * grants access, the filter must match no graph. The query below has the
 * shape of the one in the Graph Perms tab of user_group_admin.php and runs on
 * SQLite.
 */

require_once dirname(__DIR__, 4) . '/lib/auth.php';

function userGroupGraphPolicyDb() : PDO {
	$db = new PDO('sqlite::memory:');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$db->exec('CREATE TABLE graph_templates_graph (local_graph_id INTEGER, graph_template_id INTEGER, title_cache TEXT, width INTEGER, height INTEGER)');
	$db->exec('CREATE TABLE graph_local (id INTEGER, host_id INTEGER, graph_template_id INTEGER, snmp_index TEXT, snmp_query_id INTEGER)');
	$db->exec('CREATE TABLE graph_templates (id INTEGER, name TEXT)');
	$db->exec('CREATE TABLE host (id INTEGER, description TEXT, disabled TEXT, deleted TEXT)');
	$db->exec('CREATE TABLE user_auth_group_perms (group_id INTEGER, item_id INTEGER, type INTEGER)');

	$db->exec("INSERT INTO host VALUES (1, 'router', '', '')");
	$db->exec("INSERT INTO graph_templates VALUES (5, 'Traffic')");
	$db->exec("INSERT INTO graph_local VALUES (10, 1, 5, '', 0), (11, 1, 5, '', 0)");
	$db->exec("INSERT INTO graph_templates_graph VALUES (10, 5, 'router - eth0', 500, 120), (11, 5, 'router - eth1', 500, 120)");
	$db->exec('INSERT INTO user_auth_group_perms VALUES (3, 10, 1)');

	return $db;
}

function userGroupGraphPolicyQuery(PDO $db, array $policies, int $method, bool $associated_only) : array {
	$sql_where = 'WHERE (gtg.local_graph_id > 0)';

	if ($associated_only) {
		$sql_where = get_policy_where($method, $policies, $sql_where);
	}

	$details = get_policy_join_select($policies);

	return $db->query("SELECT gtg.local_graph_id, h.description,
		h.disabled, h.deleted, gt.name AS template_name,
		gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id, {$details['sql_select']}
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id = gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id = gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id = gl.host_id
		{$details['sql_join']}
		$sql_where
		ORDER BY gtg.title_cache
		LIMIT 0,30")->fetchAll(PDO::FETCH_ASSOC);
}

test('a group without a policy lists every graph with a valid select list', function () {
	$graphs = userGroupGraphPolicyQuery(userGroupGraphPolicyDb(), [], 1, false);

	expect(array_column($graphs, 'local_graph_id'))->toBe([10, 11]);
});

test('a group without a policy grants no graph under any graph policy', function (int $method) {
	expect(userGroupGraphPolicyQuery(userGroupGraphPolicyDb(), [], $method, true))->toBe([]);
})->with([1, 2, 3, 4]);

test('the filter for no policy extends an empty or an existing where clause', function () {
	expect(get_policy_where(1, [], ''))->toBe('WHERE 1 = 0');
	expect(get_policy_where(1, [], 'WHERE (gtg.local_graph_id > 0)'))->toBe('WHERE (gtg.local_graph_id > 0) AND 1 = 0');
});

test('an enabled group still reports its own permissions', function () {
	$policies = [
		['id' => 3, 'type' => 'group', 'name' => 'Operators', 'policy_graphs' => 2, 'policy_hosts' => 2, 'policy_graph_templates' => 2]
	];

	$graphs = userGroupGraphPolicyQuery(userGroupGraphPolicyDb(), $policies, 1, true);

	expect(array_column($graphs, 'local_graph_id'))->toBe([10]);
	expect($graphs[0]['graph1'])->toBe(3);
});
