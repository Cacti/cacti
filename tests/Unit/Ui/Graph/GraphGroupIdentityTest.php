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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('graph groups retain calculated legends and stop at their own boundary', function () {
	$groups = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/graph_group_probe.php');
	expect($groups['instance'])->toBe([101, 102, 103, 104]);
	expect($groups['template'])->toBe([301, 302]);
	expect($groups['non_parent'])->toBe([]);
});
