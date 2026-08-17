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
 * Regression tests for get_dsstats_order_string() (lib/html_utility.php),
 * the ORDER BY builder used by get_allowed_graphs() (lib/auth.php) when
 * sorting graphs by a dsstats measure.
 *
 * Before this fix, get_allowed_graphs() concatenated $sql_order['measure']
 * and $sql_order['order'] straight into an ORDER BY clause. Both values
 * originate from unfiltered request variables (grv('measure'),
 * grv('graph_order')) reachable pre-auth through graph_view.php when the
 * guest account is enabled and dsstats_enable is 'on'.
 */

require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

test('valid measure and order pass through unchanged', function () {
	expect(get_dsstats_order_string(['measure' => 'p95n', 'order' => 'desc']))
		->toBe('ORDER BY rs.p95n DESC');
});

test('every legal measure value is accepted', function () {
	foreach (['average', 'peak', 'p25n', 'p50n', 'p75n', 'p90n', 'p95n', 'sum'] as $measure) {
		expect(get_dsstats_order_string(['measure' => $measure, 'order' => 'asc']))
			->toBe('ORDER BY rs.' . $measure . ' ASC');
	}
});

test('order is case-insensitive', function () {
	expect(get_dsstats_order_string(['measure' => 'sum', 'order' => 'DESC']))->toBe('ORDER BY rs.sum DESC');
	expect(get_dsstats_order_string(['measure' => 'sum', 'order' => 'Desc']))->toBe('ORDER BY rs.sum DESC');
	expect(get_dsstats_order_string(['measure' => 'sum', 'order' => 'desc']))->toBe('ORDER BY rs.sum DESC');
	expect(get_dsstats_order_string(['measure' => 'sum', 'order' => 'ASC']))->toBe('ORDER BY rs.sum ASC');
});

test('unrecognised measure falls back to average instead of reaching SQL', function () {
	$clause = get_dsstats_order_string(['measure' => 'average; DROP TABLE user_auth-- ', 'order' => 'asc']);

	expect($clause)->toBe('ORDER BY rs.average ASC')
		->and($clause)->not->toContain(';')
		->and($clause)->not->toContain('--');
});

test('UNION SELECT payload in measure is rejected', function () {
	$clause = get_dsstats_order_string([
		'measure' => '1) UNION SELECT password FROM user_auth-- ',
		'order'   => 'asc',
	]);

	expect($clause)->toBe('ORDER BY rs.average ASC')
		->and($clause)->not->toContain('UNION')
		->and($clause)->not->toContain('user_auth');
});

test('subquery payload in measure is rejected', function () {
	$clause = get_dsstats_order_string([
		'measure' => '(SELECT password FROM user_auth LIMIT 1)',
		'order'   => 'asc',
	]);

	expect($clause)->toBe('ORDER BY rs.average ASC')
		->and($clause)->not->toContain('SELECT');
});

test('SQL payload in order falls back to ASC', function () {
	expect(get_dsstats_order_string(['measure' => 'sum', 'order' => 'asc; DROP TABLE user_auth-- ']))
		->toBe('ORDER BY rs.sum ASC');
});

test('missing keys default to a safe clause', function () {
	expect(get_dsstats_order_string([]))->toBe('ORDER BY rs.average ASC');
});
