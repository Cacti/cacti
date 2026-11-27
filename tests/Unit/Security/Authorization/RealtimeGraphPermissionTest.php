<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * graph_realtime.php checks is_graph_allowed() before it starts the realtime
 * poller for a graph, matching graph.php. The graph image itself was already
 * refused by rrdtool_function_graph(); this stops the on-demand poll as well.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/graph_realtime.php');

test('realtime checks graph permission before starting the realtime poller', function () use ($src) {
	$guard = strpos($src, 'if (!is_graph_allowed($local_graph_id)) {');
	$exec  = strpos($src, 'shell_exec(');
	expect($guard)->not->toBeFalse()
		->and($exec)->not->toBeFalse()
		->and($guard)->toBeLessThan($exec);
});
