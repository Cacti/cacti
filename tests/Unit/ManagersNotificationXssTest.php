<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$managersSource = file_get_contents(__DIR__ . '/../../managers.php');

test('snmp notification fallback output is html escaped', function () use ($managersSource) {
	expect($managersSource)->toContain("print '<td>' . html_escape(\$item['notification']) . '</td>';");
});
