<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-j3px-vw6r-g25x: snmp_index is stored host_snmp_cache data interpolated
 * raw into an IN() list in graphs_new.php. Both the first-item and the
 * subsequent-item branches must quote/escape it via db_qstr().
 */

$root = dirname(__DIR__, 4);

test('New Graphs escapes snmp_index in both branches of the IN() pivot', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->toContain("db_qstr(\$index['snmp_index'])")
		// first-item branch must not concatenate the raw value
		->and($source)->not->toContain("\" AND snmp_index IN('\" . \$index['snmp_index']")
		// subsequent-item branch must not concatenate the raw value either
		->and($source)->not->toContain("\", '\" . \$index['snmp_index'] . \"'");
});
