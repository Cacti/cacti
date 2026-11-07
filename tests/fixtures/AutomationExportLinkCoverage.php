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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__) . '/Helpers/IsolatedProbe.php';
require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
$files = ['automation_graph_rules.php', 'automation_networks.php', 'automation_snmp.php', 'automation_templates.php', 'automation_tree_rules.php'];

foreach ($files as $file) {
	$r = cacti_test_isolated_probe(__DIR__ . '/AutomationExportLinkProbe.php', [$file], true);

	if (preg_match('/DownloadStart\(("[^\n]*")\);/', $r['html'], $match) !== 1) {
		throw new RuntimeException('Download call absent');
	}
	$url = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
	parse_str(parse_url($url, PHP_URL_QUERY), $query);

	if (parse_url($url, PHP_URL_PATH) !== $file || $query !== ['action' => 'export', 'selected_items' => serialize([1, 2])]) {
		throw new RuntimeException('Export selection changed');
	}
}
print "PASS: automation export link roundtrip\n";
