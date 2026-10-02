<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-73qf-hq23-c4m7: tree.php's lock/unlock/get_node actions dispatched to
 * api_tree_*() with no ownership check, letting any authenticated user lock or
 * unlock any tree and read another user's private tree structure. Every tree
 * node action must gate on is_tree_allowed() before dispatch, like the node
 * write actions already do.
 */

$root   = dirname(__DIR__, 4);
$source = file_get_contents($root . '/tree.php');

$actions = ['lock', 'unlock', 'copy_node', 'create_node', 'delete_node', 'move_node', 'rename_node', 'get_node'];

test('every tree node action is gated by is_tree_allowed before dispatch', function () use ($source, $actions) {
	foreach ($actions as $action) {
		$pos = strpos($source, "case '" . $action . "':");

		expect($pos)->not->toBeFalse();

		$next  = strpos($source, 'break;', $pos);
		$block = substr($source, $pos, $next - $pos);

		expect($block)->toContain('is_tree_allowed');
	}
});
