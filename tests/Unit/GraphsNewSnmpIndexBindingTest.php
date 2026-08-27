<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for the second-order SQL injection via host_snmp_cache.snmp_index
 * in the graphs_new.php index filter (sibling of the field_name pivot, GHSA-j3px).
 * snmp_index is stored raw on the script data-query path and must be quoted with
 * db_qstr() rather than concatenated into the IN() list.
 */

$source = file_get_contents(dirname(__DIR__, 2) . '/graphs_new.php');

test('graphs_new quotes snmp_index in the IN() list', function () use ($source) {
	expect($source)->toContain("\$sql_where .= ' AND snmp_index IN(' . db_qstr(\$index['snmp_index']);")
		->and($source)->toContain("\$sql_where .= ', ' . db_qstr(\$index['snmp_index']);");
});

test('graphs_new never concatenates snmp_index into a raw quoted literal', function () use ($source) {
	expect($source)->not->toContain("snmp_index IN('\" . \$index['snmp_index']")
		->and($source)->not->toContain("\$sql_where .= \", '\" . \$index['snmp_index']");
});
