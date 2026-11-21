<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The New Graphs data query table escapes the data query field name in its
 * column header.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/graphs_new.php');

test('the data-query column header escapes the field name', function () use ($src) {
	expect($src)->toContain("<th class='tableSubHeaderColumn'>\" . html_escape(\$field_array['name']) . '</th>'")
		->and($src)->not->toContain("<th class='tableSubHeaderColumn'>\" . \$field_array['name'] . '</th>'");
});
