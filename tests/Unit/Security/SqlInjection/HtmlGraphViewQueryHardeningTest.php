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

require_once dirname(__DIR__, 4) . '/lib/html_utility.php';
require_once dirname(__DIR__, 4) . '/lib/html_graph.php';

test('html_graph_sql_limit builds valid offset and row count pairs', function () {
	expect(html_graph_sql_limit(1, 20))->toBe('0,20')
		->and(html_graph_sql_limit('3', 15))->toBe('30,15')
		->and(html_graph_sql_limit(2, 10))->toBe('10,10');
});

test('html_graph_sql_limit clamps invalid, zero, and negative pages to page 1', function () {
	expect(html_graph_sql_limit(0, 20))->toBe('0,20')
		->and(html_graph_sql_limit(-4, 20))->toBe('0,20')
		->and(html_graph_sql_limit('', 20))->toBe('0,20')
		->and(html_graph_sql_limit('invalid', 20))->toBe('0,20')
		->and(html_graph_sql_limit('1 UNION SELECT 1', 20))->toBe('0,20')
		->and(html_graph_sql_limit(2, 0))->toBe('1,1');
});

test('html_graph_build_dsstats_order accepts allowlisted data source ordering parameters', function () {
	$order = html_graph_build_dsstats_order('traffic_in', 'DESC', 'max', 'p95n', 1000, 2000);

	expect($order)->toBe([
		'data_source' => 'traffic_in',
		'order'       => 'desc',
		'start_time'  => 1000,
		'end_time'    => 2000,
		'cf'          => 'max',
		'measure'     => 'p95n',
	]);
});

test('html_graph_build_dsstats_order preserves numeric and avg consolidation function values', function () {
	$avg = html_graph_build_dsstats_order('cpu-user', 'asc', 'avg', 'average', 10, 20);
	$one = html_graph_build_dsstats_order('cpu_sys', 'other', '1', 'sum', 10, 20);

	expect($avg['cf'])->toBe('avg')
		->and($avg['order'])->toBe('asc')
		->and($avg['measure'])->toBe('average')
		->and($one['cf'])->toBe('1')
		->and($one['order'])->toBe('asc')
		->and($one['measure'])->toBe('sum');
});

test('html_graph_build_dsstats_order falls back to title order for invalid or placeholder sources', function () {
	expect(html_graph_build_dsstats_order('', 'desc', 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order('-1', 'desc', 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order('traffic_in', '', 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order(['bad'], 'desc', 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order('traffic_in', ['bad'], 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order("bad'source", 'desc', 'avg', 'average', 10, 20))->toBe('gtg.title_cache')
		->and(html_graph_build_dsstats_order('too_long_data_source_name_exceeds_19', 'desc', 'avg', 'average', 10, 20))->toBe('gtg.title_cache');
});

test('html_graph_build_dsstats_order normalizes unrecognized cf and measure values', function () {
	$order = html_graph_build_dsstats_order('traffic_out', 'invalid', 'unexpected', 'unknown_col', 50, 100);

	expect($order)->toBe([
		'data_source' => 'traffic_out',
		'order'       => 'asc',
		'start_time'  => 50,
		'end_time'    => 100,
		'cf'          => '',
		'measure'     => 'average',
	]);
});

test('html_graph_dsstats_order_from_request reads and validates request parameters', function () {
	$saved_request = $_REQUEST;
	$saved_session = $_SESSION ?? [];

	try {
		$_SESSION['sess_current_timespan_begin_now'] = 1700000000;
		$_SESSION['sess_current_timespan_end_now']   = 1700003600;

		srv('graph_source', 'traffic_in');
		srv('graph_order', 'desc');
		srv('cf', 'max');
		srv('measure', 'p95n');

		expect(html_graph_dsstats_order_from_request())->toBe([
			'data_source' => 'traffic_in',
			'order'       => 'desc',
			'start_time'  => 1700000000,
			'end_time'    => 1700003600,
			'cf'          => 'max',
			'measure'     => 'p95n',
		]);

		srv('graph_source', '-1');
		expect(html_graph_dsstats_order_from_request())->toBe('gtg.title_cache');
	} finally {
		$_REQUEST = $saved_request;
		$_SESSION = $saved_session;
	}
});

test('preview and list views validate site_id, graphs, and page before calling get_allowed_graphs', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/html_graph.php');
	expect($source)->not->toBeFalse();

	foreach (['html_graph_preview_view', 'html_graph_list_view'] as $function) {
		expect(preg_match('/function ' . $function . '\(.*?\n}\n/s', $source, $matches))->toBe(1);
		$body = $matches[0];

		expect($body)->toContain("' h.site_id=' . gfrv('site_id')")
			->and($body)->not->toContain("' h.site_id=' . grv('site_id')")
			->and($body)->toContain("\$graph_rows = gfrv('graphs');")
			->and($body)->not->toContain("\$graph_rows = grv('graphs');")
			->and($body)->toContain("html_graph_sql_limit(gfrv('page', FILTER_VALIDATE_INT, ['default' => 1]), \$graph_rows)");
	}
});
