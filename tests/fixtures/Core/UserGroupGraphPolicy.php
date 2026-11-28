<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\UserGroupGraphPolicy;

use PDO;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Builds the Graph Perms query of a user group (user_group_admin.php) from the
 * policy helpers in lib/auth.php, loaded unchanged with their original line
 * numbers, and runs it on SQLite.  A disabled group has no policy: the query
 * must stay valid and must grant no graph.
 */

$root = dirname(__DIR__, 3);

$GLOBALS['passed'] = 0;

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

\cacti_fixture_source_function($root . '/lib/auth.php', 'get_policy_join_select', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/auth.php', 'get_policy_where', __NAMESPACE__);

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE graph_templates_graph (local_graph_id INTEGER, graph_template_id INTEGER, title_cache TEXT)');
$db->exec('CREATE TABLE graph_local (id INTEGER, host_id INTEGER, graph_template_id INTEGER)');
$db->exec('CREATE TABLE host (id INTEGER, description TEXT)');
$db->exec('CREATE TABLE user_auth_group_perms (group_id INTEGER, item_id INTEGER, type INTEGER)');
$db->exec("INSERT INTO host VALUES (1, 'router')");
$db->exec('INSERT INTO graph_local VALUES (10, 1, 5), (11, 1, 5)');
$db->exec("INSERT INTO graph_templates_graph VALUES (10, 5, 'router - eth0'), (11, 5, 'router - eth1')");
$db->exec('INSERT INTO user_auth_group_perms VALUES (3, 10, 1)');

// The graph list of the Graph Perms tab, optionally limited to the graphs the policies grant.
function graphs(PDO $db, array $policies, int $method, bool $granted_only) : array {
	$sql_where = 'WHERE (gtg.local_graph_id > 0)';

	if ($granted_only) {
		$sql_where = get_policy_where($method, $policies, $sql_where);
	}

	$details = get_policy_join_select($policies);

	return $db->query("SELECT gtg.local_graph_id, h.description, {$details['sql_select']}
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id = gtg.local_graph_id
		LEFT JOIN host AS h
		ON h.id = gl.host_id
		{$details['sql_join']}
		$sql_where
		ORDER BY gtg.title_cache")->fetchAll(PDO::FETCH_ASSOC);
}

$all = graphs($db, [], 1, false);
check(array_column($all, 'local_graph_id') === [10, 11] && array_key_exists('graph1', $all[0]) && $all[0]['graph1'] === null, 'a group without a policy lists every graph with a valid select list');

foreach ([1, 2, 3, 4] as $method) {
	check(graphs($db, [], $method, true) === [], 'a group without a policy is granted no graph under policy method ' . $method);
}

check(get_policy_where(1, [], '') === 'WHERE 1 = 0', 'the filter for no policy starts a where clause');

$granted = graphs($db, [['id' => 3, 'type' => 'group', 'name' => 'Operators', 'policy_graphs' => 2, 'policy_hosts' => 2, 'policy_graph_templates' => 2]], 1, true);
check(array_column($granted, 'local_graph_id') === [10] && (int) $granted[0]['graph1'] === 3, 'an enabled group still reports its own permissions');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
