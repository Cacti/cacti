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

require_once dirname(__DIR__, 2) . '/lib/type_secure.php';

/**
 * Mirror of the logic in aggregate_graphs.php for VULN-002 fix.
 */
function simulate_aggregate_sql_handoff($input_ids) {
	$ids = array_map(fn($id) => CactiSecureType::toInt($id), explode(',', $input_ids));
	return ' agi.local_graph_id IN(' . implode(',', $ids) . ')';
}

test('Data Handoff: aggregate local_graph_ids are strictly cast to integers', function () {
	$malicious_input = '1,2,3) OR 1=1 --';
	$sql_fragment = simulate_aggregate_sql_handoff($malicious_input);
	
	// Malicious input "3) OR 1=1 --" should be cast to 3 by toInt() if it starts with 3,
	// or 0 if non-numeric. PHP's is_numeric used in toInt() will return false for '3) OR 1=1 --'.
	
	expect($sql_fragment)->not->toContain('OR 1=1');
	expect($sql_fragment)->not->toContain('--');
	expect($sql_fragment)->toBe(' agi.local_graph_id IN(1,2,0)');
});
