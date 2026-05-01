<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-level lint guard for GHSA-69gg-mjfm-jjpc. This is not a behavioral
 * test; it greps the migrated files to prove the vulnerable concatenation
 * patterns do not reappear and that db_qstr_rlike() is used instead.
 */

$root = dirname(__DIR__, 2);

$dataDebug    = file_get_contents($root . '/data_debug.php');
$dataSources  = file_get_contents($root . '/data_sources.php');
$graphView    = file_get_contents($root . '/graph_view.php');
$graphs       = file_get_contents($root . '/graphs.php');
$removeGraphs = file_get_contents($root . '/cli/remove_graphs.php');
$applyRules   = file_get_contents($root . '/cli/apply_automation_rules.php');

$sources = array(
	'data_debug.php'                  => $dataDebug,
	'data_sources.php'                => $dataSources,
	'graph_view.php'                  => $graphView,
	'graphs.php'                      => $graphs,
	'cli/remove_graphs.php'           => $removeGraphs,
	'cli/apply_automation_rules.php'  => $applyRules,
);

test('migrated files no longer interpolate rfilter into RLIKE literals', function () use ($sources) {
	foreach ($sources as $name => $src) {
		expect($src)->not->toContain("RLIKE '\" . get_request_var('rfilter')");
	}
});

test('migrated files do not build RLIKE from double-quoted concat', function () use ($sources) {
	foreach ($sources as $name => $src) {
		expect($src)->not->toMatch('/RLIKE\s+"\s*\.\s*\$/');
	}
});

test('data_debug.php uses db_qstr_rlike for all rfilter RLIKE clauses', function () use ($dataDebug) {
	expect($dataDebug)->toContain("dtd.name_cache \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($dataDebug)->toContain("dtd.local_data_id \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($dataDebug)->toContain("dt.name \" . db_qstr_rlike(get_request_var('rfilter'))");
});

test('data_sources.php uses db_qstr_rlike and casts dl.id to int', function () use ($dataSources) {
	expect($dataSources)->toContain("dtd.name_cache \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($dataSources)->toContain("dtd.local_data_id \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($dataSources)->toContain("dt.name \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($dataSources)->toContain("dl.id = \" . (int) get_request_var('rfilter')");
});

test('graph_view.php uses db_qstr_rlike for rfilter RLIKE clauses', function () use ($graphView) {
	$count = substr_count($graphView, "gtg.title_cache \" . db_qstr_rlike(get_request_var('rfilter'))");
	expect($count)->toBeGreaterThanOrEqual(2);
});

test('graphs.php uses db_qstr_rlike and casts gl.id to int', function () use ($graphs) {
	expect($graphs)->toContain("gtg.title_cache \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($graphs)->toContain("gt.name \" . db_qstr_rlike(get_request_var('rfilter'))")
		->and($graphs)->toContain("gl.id = \" . (int) get_request_var('rfilter')");
});

test('cli/remove_graphs.php uses db_qstr_rlike for regex list', function () use ($removeGraphs) {
	expect($removeGraphs)->toContain("title_cache ' . db_qstr_rlike(\$r)");
});

test('cli/apply_automation_rules.php migrated hostname/description to db_qstr_rlike', function () use ($applyRules) {
	expect($applyRules)->toContain("h.hostname ' . db_qstr_rlike(\$hostname)")
		->and($applyRules)->toContain("h.description ' . db_qstr_rlike(\$description)")
		->and($applyRules)->not->toContain("h.hostname RLIKE ' . db_qstr(\$hostname)")
		->and($applyRules)->not->toContain("h.description RLIKE ' . db_qstr(\$description)");
});
