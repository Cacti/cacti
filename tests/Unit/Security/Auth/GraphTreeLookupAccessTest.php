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

/*
 * The realtime graph request checks graph permission before it starts the
 * realtime poller, and the graph tree node request checks the tree it resolved
 * before it returns the tree name or any node. Each probe call runs the
 * production code for one request in its own process.
 */

function lookup_probe(string $mode, string $case): array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/SortLookupChecks.php', [$mode, $case]);
}

test('a realtime request for a viewable graph starts the realtime poller', function () {
	expect(lookup_probe('realtime', 'allowed'))->toBe(['allowed' => true, 'poller_started' => true]);
});

test('a realtime request for a graph the user may not view receives a denial before the poller starts', function () {
	expect(lookup_probe('realtime', 'denied'))->toBe(['allowed' => false, 'poller_started' => false]);
});

test('a tree node request returns nodes only for a tree the user may view', function (string $case, ?array $nodes, array $checked) {
	expect(lookup_probe('tree', $case))->toBe(['nodes' => $nodes, 'checked' => $checked]);
})->with([
	'root of a viewable tree'                 => ['root-allowed', [5, -1], [5]],
	'root of another tree'                    => ['root-denied', null, [6]],
	'branch of a viewable tree'               => ['branch-allowed', [5, 70], [5]],
	'branch of another tree'                  => ['branch-denied', null, [6]],
	'branch resolved without a tree id'       => ['lookup-denied', null, [6]],
	'default tree the user may not view'      => ['default-denied', null, [6]],
	'tree list when no tree is selected'      => ['tree-list', [0, -1], []],
]);
