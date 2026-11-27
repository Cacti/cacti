<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The tree node loaders check the tree before they return its nodes: the graph
 * view against the trees the user may view, and tree management against the
 * trees the user may manage, like the other node actions.
 */

function tree_node_case_body(string $file, string $marker): string {
	$source = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
	$start  = strpos($source, "case 'get_node':");

	return substr($source, $start, strpos($source, $marker, $start) - $start);
}

test('the graph view node loader checks tree visibility before loading nodes', function () {
	expect(tree_node_case_body('graph_view.php', 'api_tree_get_main($tree_id, $parent);'))
		->toContain('if ($tree_id > 0 && !is_tree_allowed($tree_id)) {');
});

test('the tree management node loader checks the tree before loading nodes', function () {
	expect(tree_node_case_body('tree.php', 'api_tree_get_node('))
		->toContain("if (!cacti_authorize_resource(\$_SESSION['sess_user_id'], (int) get_request_var('tree_id'), 'graph_tree')) {");
});
