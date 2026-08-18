<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * plugins.php built its filter into LIKE clauses with db_qstr(), which escapes
 * quotes but not the LIKE metacharacters % and _, so a filter of '%' matched
 * every row and bypassed the state filter (GHSA-jjfq-mj5r-2r95). The filter is
 * now a bound parameter, LIKE-escaped via db_like_escape().
 */

$src = file_get_contents(CACTI_PATH_BASE . '/plugins.php');

test('the filter no longer reaches LIKE through db_qstr', function () use ($src) {
	expect($src)->not->toContain("db_qstr('%' . grv('filter') . '%')");
});

test('the filter is bound and LIKE-escaped once, then reused for every placeholder', function () use ($src) {
	expect($src)->toContain("array_fill(0, substr_count(\$sql_where, '?'), '%' . db_like_escape(grv('filter')) . '%')");
});

test('every query that embeds the filter where-clause is prepared', function () use ($src) {
	// the count and list queries must pass $where_params
	expect(substr_count($src, '$sql_where", $where_params);'))->toBeGreaterThanOrEqual(3);
	expect($src)->toContain('db_fetch_assoc_prepared($sql, $where_params);');
	// and none of them may run the where-clause through the non-prepared fetch
	expect($src)->not->toContain('db_fetch_cell("SELECT COUNT(*)');
});
