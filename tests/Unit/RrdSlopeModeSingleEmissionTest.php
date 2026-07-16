<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for duplicate --slope-mode emission in
 * rrd_function_process_graph_options() (lib/rrd.php).
 *
 * The per-option switch (case 'slope_mode') and a later standalone
 * "provide smooth lines" block both appended --slope-mode for the same
 * graphs, so slope-enabled graphs got the option twice on every rrdtool
 * graph command. PR #7183 removed the standalone block on 1.2.x; this
 * forward-ports it to develop.
 *
 * @group regression
 */

test('lib/rrd.php emits --slope-mode from exactly one site', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

	expect(substr_count($source, "'--slope-mode'"))->toBe(1);
});
