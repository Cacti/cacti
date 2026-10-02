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
 * aggregate_graphs.php form_save() generated a new item sequence with a WHERE
 * clause built from the request value local_graph_id. get_sequence() runs a
 * string group_query verbatim with no bound parameters, so a raw
 * 'local_graph_id=' . grv('local_graph_id') let an authenticated user reach
 * SQL through that filter (GHSA-6mjm-5vwr-3fr2). The value now travels as a
 * validated, bound array filter instead, and build_where_from_array() turns
 * it into a placeholder rather than inlining it.
 */

require_once CACTI_PATH_LIBRARY . '/functions.php';

$src = file_get_contents(CACTI_PATH_BASE . '/aggregate_graphs.php');

test('the aggregate new-sequence branch binds local_graph_id instead of concatenating it', function () use ($src) {
	// the sequence query must receive a validated, bound array filter ...
	expect($src)->toContain("get_sequence(\$sequence, 'sequence', 'graph_templates_item', ['local_graph_id' => gfrv('local_graph_id')])")
		// ... and must never rebuild that filter by concatenating a request value
		->and($src)->not->toContain("get_sequence(\$sequence, 'sequence', 'graph_templates_item', 'local_graph_id=' . grv('local_graph_id'))")
		->and($src)->not->toContain("get_sequence(\$sequence, 'sequence', 'graph_templates_item', 'local_graph_id=' . gfrv('local_graph_id'))");
});

test('build_where_from_array binds an injection payload as a parameter rather than inlining it', function () {
	$params  = [];
	$payload = "1) UNION SELECT username, password FROM user_auth -- ";

	$where = build_where_from_array(['local_graph_id' => $payload], $params);

	expect($where)->toBe('`local_graph_id` = ?')
		->and($params)->toBe([$payload])
		->and($where)->not->toContain('UNION');
});

test('build_where_from_array binds a valid integer as a parameter', function () {
	$params = [];

	$where = build_where_from_array(['local_graph_id' => 7], $params);

	expect($where)->toBe('`local_graph_id` = ?')
		->and($params)->toBe([7]);
});
