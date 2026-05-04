<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$databaseSource        = file_get_contents(__DIR__ . '/../../lib/database.php');
$graphViewSource       = file_get_contents(__DIR__ . '/../../graph_view.php');
$reportsSource         = file_get_contents(__DIR__ . '/../../lib/reports.php');
$aggregateGraphsSource = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');

test('GHSA-69gg/xrh3/gp82/pf37: db_qstr_rlike caps operand length at 255 bytes', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_qstr_rlike(');
	expect($start)->not->toBeFalse();

	$end  = strpos($databaseSource, "\n}\n", $start);
	$body = substr($databaseSource, $start, $end - $start);

	// Long regex operands caused DoS against MySQL's RE2. The cap keeps
	// the pattern bounded before it reaches the engine.
	expect($body)->toContain('strlen($s) > 255');
	expect($body)->toContain('substr($s, 0, 255)');
});

test('GHSA-69gg/xrh3/gp82/pf37: db_qstr_rlike strips NUL, pipe and brace metacharacters', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_qstr_rlike(');
	$end   = strpos($databaseSource, "\n}\n", $start);
	$body  = substr($databaseSource, $start, $end - $start);

	// Alternation and quantifier-bound constructs were the DoS vector;
	// stripping them here is belt-and-braces on top of the length cap.
	expect($body)->toContain('str_replace(array("\0", \'|\', \'{\', \'}\'), \'\', $s)');
});

test('GHSA-69gg/xrh3/gp82/pf37: db_qstr_rlike returns a quoted RLIKE fragment', function () use ($databaseSource) {
	$start = strpos($databaseSource, 'function db_qstr_rlike(');
	$end   = strpos($databaseSource, "\n}\n", $start);
	$body  = substr($databaseSource, $start, $end - $start);

	// The return shape is what callers rely on when concatenating into
	// WHERE clauses; a drift here would re-open SQL injection.
	expect($body)->toContain("return 'RLIKE ' . db_qstr(");
});

test('GHSA-69gg / GHSA-gp82: graph_view.php routes rfilter through db_qstr_rlike', function () use ($graphViewSource) {
	expect($graphViewSource)->toContain("db_qstr_rlike(get_request_var('rfilter'))");
});

test('GHSA-xrh3 / GHSA-pf37: lib/reports.php wraps graph_name_regexp in db_qstr_rlike', function () use ($reportsSource) {
	expect($reportsSource)->toContain("db_qstr_rlike(\$item['graph_name_regexp'])");
});

test('GHSA-69gg/xrh3/gp82/pf37: aggregate_graphs.php routes rfilter through db_qstr_rlike', function () use ($aggregateGraphsSource) {
	expect($aggregateGraphsSource)->toContain("db_qstr_rlike(get_request_var('rfilter'))");
});
