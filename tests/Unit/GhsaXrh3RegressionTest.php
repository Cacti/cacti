<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-xrh3-6pfg-ff35 / CVE-2026-39953.
 *
 * Stored SQL Injection via graph_name_regexp in lib/reports.php. The develop
 * branch concatenated graph_name_regexp raw into 4 REGEXP clauses. The fix
 * migrates each to db_qstr_rlike() which applies PDO quoting.
 */

test('lib/reports.php contains no raw REGEXP concat with graph_name_regexp', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/reports.php');
	expect($src)->not->toBeFalse();
	expect($src)->not->toContain("REGEXP '\" . \$item['graph_name_regexp'] . \"'");
});

test('lib/reports.php uses db_qstr_rlike for all graph_name_regexp sinks', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/reports.php');
	$matches = substr_count($src, "db_qstr_rlike(\$item['graph_name_regexp'])");
	expect($matches)->toBeGreaterThanOrEqual(4);
});
