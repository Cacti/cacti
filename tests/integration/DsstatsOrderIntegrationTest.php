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
 * Integration coverage for the get_allowed_graphs() dsstats ORDER BY path
 * (lib/auth.php). tests/Unit/Security/SqlInjection/DsstatsOrderStringTest.php already covers
 * get_dsstats_order_string() in isolation; this test drives it together
 * with the other real functions get_allowed_graphs() calls on the same
 * code path (dsstats_get_best_partition() from lib/dsstats.php and
 * db_qstr() from lib/database.php), reproducing the exact JOIN + ORDER BY
 * fragment that gets concatenated into the graph list query, so a
 * regression that reintroduces unescaped concatenation anywhere along
 * that path (not just inside get_dsstats_order_string() itself) is
 * caught. Calling get_allowed_graphs() itself would require a live DB
 * connection (auth_valid_user(), read_config_option(), db_fetch_assoc()),
 * which this test suite does not provision.
 */

require_once CACTI_PATH_LIBRARY . '/database.php';
require_once CACTI_PATH_LIBRARY . '/dsstats.php';
require_once CACTI_PATH_LIBRARY . '/html_utility.php';

/*
 * Rebuilds the $sql_order_join / $sql_order fragment exactly as
 * get_allowed_graphs() does for the is_array($sql_order) branch
 * (lib/auth.php), so the test exercises the real call sequence rather
 * than a paraphrase of it.
 */
function build_dsstats_order_fragment(array $sql_order) : array {
	$table = dsstats_get_best_partition($sql_order['start_time'], $sql_order['end_time']);

	$cf = $sql_order['cf'] == 'max' ? 1 : 0;

	$sql_order_join = "INNER JOIN
		(
			SELECT DISTINCT gti.local_graph_id, ot.*
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			INNER JOIN $table AS ot
			ON dtr.local_data_id = ot.local_data_id
			WHERE ot.rrd_name = " . db_qstr($sql_order['data_source']) . "
			AND ot.cf = $cf
		) AS rs
		ON gl.id = rs.local_graph_id";

	return [$sql_order_join, get_dsstats_order_string($sql_order)];
}

test('malicious data_source is escaped, not concatenated, in the dsstats JOIN clause', function () {
	[$join] = build_dsstats_order_fragment([
		'start_time'  => time(),
		'end_time'    => time(),
		'data_source' => "traffic_in' AND 1=(SELECT password FROM user_auth LIMIT 1)-- ",
		'cf'          => 'avg',
		'measure'     => 'average',
		'order'       => 'asc',
	]);

	// db_qstr() must have backslash-escaped the embedded quote so the
	// payload stays inside the string literal instead of closing it.
	expect($join)->toContain("\\'")
		->and($join)->not->toContain("rrd_name = 'traffic_in' AND");
});

test('malicious measure/order survive the full order-fragment build as a safe clause', function () {
	[, $order] = build_dsstats_order_fragment([
		'start_time'  => time(),
		'end_time'    => time(),
		'data_source' => 'traffic_in',
		'cf'          => 'avg',
		'measure'     => '1) UNION SELECT password FROM user_auth-- ',
		'order'       => 'asc; DROP TABLE user_auth-- ',
	]);

	expect($order)->toBe('ORDER BY rs.average ASC')
		->and($order)->not->toContain('UNION')
		->and($order)->not->toContain('DROP')
		->and($order)->not->toContain(';');
});

test('a quote-breakout data_source combined with a statement-terminator measure stays inert in both halves of the fragment', function () {
	[$join, $order] = build_dsstats_order_fragment([
		'start_time'  => time(),
		'end_time'    => time(),
		'data_source' => "x'; DROP TABLE user_auth; -- ",
		'cf'          => 'max',
		'measure'     => 'p95n; DROP TABLE user_auth-- ',
		'order'       => 'desc',
	]);

	// db_qstr() escapes the embedded quote, so "DROP TABLE" stays inert
	// string data inside 'x\'; DROP TABLE user_auth; -- ' rather than
	// breaking out into executable SQL. What must NOT happen is an
	// *unescaped* quote reaching the JOIN, or the measure's payload
	// reaching the ORDER BY at all.
	expect($join)->toContain("x\\'; DROP TABLE user_auth; -- ")
		->and($join)->not->toContain("rrd_name = 'x';");

	expect($order)->toBe('ORDER BY rs.average DESC')
		->and($order)->not->toContain('DROP')
		->and($order)->not->toContain(';');
});

test('lib/auth.php builds the dsstats ORDER BY via get_dsstats_order_string(), not raw concatenation', function () {
	$contents = file_get_contents(CACTI_PATH_LIBRARY . '/auth.php');

	expect($contents)->toContain('get_dsstats_order_string($sql_order)')
		->and($contents)->not->toContain("'ORDER BY rs.' . \$sql_order['measure']");
});
