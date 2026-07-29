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

/*
 * Tests for the WHERE-clause construction in
 * timeout_kill_registered_processes() (lib/poller.php).
 *
 * The "match any taskid/pid" defaults are $taskid = 0 and $pid = -1, but
 * the old guards were `$taskid != ''` and `$pid != ''`. On PHP 8, comparing
 * an int to a non-numeric string casts the int to string first, so
 * `0 != ''` and `-1 != ''` both evaluate true -- the filter was added even
 * for the un-filtered defaults, so every call constrained the query to
 * taskid = 0 AND pid = -1, which no real row ever matches. The fix guards
 * on the actual sentinel values: `$taskid != 0` and `$pid != -1`.
 *
 * The guard chain is pulled directly out of the real source file and
 * wrapped in a throwaway function, following the extract-and-eval pattern
 * used elsewhere in this suite (see Issue7070PercentileContractTest.php).
 */

$source = file_get_contents(__DIR__ . '/../../lib/poller.php');

preg_match(
	"/if \(\\\$tasktype != ''\).*?\n\t\}\n\n\tif \(\\\$taskname.*?\n\t\}\n\n\tif \(\\\$taskid.*?\n\t\}\n\n\tif \(\\\$pid.*?\n\t\}/s",
	$source,
	$matches
);

test('the taskid/pid sentinel guards are present in the source', function () use ($matches) {
	expect($matches)->not->toBeEmpty();
	expect($matches[0])->toContain('$taskid != 0');
	expect($matches[0])->toContain('$pid != -1');
});

test('UNIX_TIMESTAMP(started), not FROM_UNIXTIME(started), is used in the timeout comparison', function () use ($source) {
	expect($source)->toContain('UNIX_TIMESTAMP() > UNIX_TIMESTAMP(started) + timeout');
	expect($source)->not->toContain('FROM_UNIXTIME(started) + timeout');
});

/**
 * build_where - runs the real WHERE-clause guard chain extracted from
 * timeout_kill_registered_processes() against the given filter values
 *
 * @param  string $tasktype Task type filter, empty string to skip
 * @param  string $taskname Task name filter, empty string to skip
 * @param  mixed  $taskid   Task id filter, 0 is the match-any sentinel
 * @param  mixed  $pid      Process id filter, -1 is the match-any sentinel
 *
 * @return array  [$sql_where, $params] as constructed by the guard chain
 */
$build_where = function (string $tasktype, string $taskname, mixed $taskid, mixed $pid) use ($matches) {
	// eval() here only wraps a guard chain regex-extracted from this
	// repo's own lib/poller.php (not external/user input) into a
	// throwaway named function. Test-only.
	$name = 'timeout_kill_where_' . str_replace('.', '_', uniqid('', true));
	eval("function $name(\$tasktype, \$taskname, \$taskid, \$pid) {
		\$sql_where = '';
		\$params    = array();
		{$matches[0]}
		return array(\$sql_where, \$params);
	}");

	return $name($tasktype, $taskname, $taskid, $pid);
};

test('default taskid/pid (match-any sentinels) add no WHERE filter', function () use ($build_where) {
	list($sql_where, $params) = $build_where('', '', 0, -1);

	expect($sql_where)->toBe('');
	expect($params)->toBe(array());
});

test('an explicit taskid of 0 is impossible to request, matching the match-any sentinel', function () use ($build_where) {
	// Documents the actual (intentional) contract: 0 is reserved as
	// "don't filter on taskid", same as -1 for pid.
	list($sql_where,) = $build_where('', '', 0, -1);
	expect($sql_where)->not->toContain('taskid');
});

test('a real taskid and pid add their WHERE filters', function () use ($build_where) {
	list($sql_where, $params) = $build_where('poller', 'spikekill', 42, 12345);

	expect($sql_where)->toContain('AND tasktype = ?');
	expect($sql_where)->toContain('AND taskname = ?');
	expect($sql_where)->toContain('AND taskid = ?');
	expect($sql_where)->toContain('AND pid = ?');
	expect($params)->toBe(array('poller', 'spikekill', 42, 12345));
});
