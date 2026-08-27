<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for the tree node-action IDOR (GHSA-73qf / GHSA-fchr): the
 * AJAX node actions (copy/create/delete/move/rename/get_node) operate on a
 * request tree_id and must be gated by cacti_authorize_resource so a non-owner
 * cannot read or modify another user's tree.
 */

$source = file_get_contents(dirname(__DIR__, 3) . '/tree.php');

test('tree.php gates every node action on tree ownership before dispatch', function () use ($source) {
	foreach (array('copy_node', 'create_node', 'delete_node', 'move_node', 'rename_node', 'get_node') as $action) {
		expect($source)->toContain("'$action'");
	}
	/* the guard must run before the action switch, keyed on tree_id */
	expect($source)->toContain("cacti_authorize_resource(\$_SESSION['sess_user_id'], (int) get_request_var('tree_id'), 'graph_tree')");

	$guard_pos  = strpos($source, "\$tree_node_actions = array(");
	$switch_pos = strpos($source, "switch (get_request_var('action')) {");
	expect($guard_pos)->toBeInt()->and($switch_pos)->toBeInt()->and($guard_pos)->toBeLessThan($switch_pos);
});

test('the node-action guard denies with 403 and exits', function () use ($source) {
	expect($source)->toMatch('/in_array\(get_nfilter_request_var\(\'action\'\), \$tree_node_actions.*?header\(\'HTTP\/1\.1 403 Forbidden\'\);\s*print[^\n]*\s*exit;/s');
});
