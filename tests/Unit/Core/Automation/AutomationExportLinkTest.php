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

require_once dirname(__DIR__,3) . '/Helpers/IsolatedProbe.php';
test('automation download links preserve selected IDs through JavaScript and URL serialization',function ($file) {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AutomationExportLinkProbe.php',[$file]);
	expect(preg_match('/DownloadStart\(("[^\n]*")\);/',$r['html'],$match))->toBe(1);
	$url = json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
	expect(parse_url($url,PHP_URL_PATH))->toBe($file);
	parse_str(parse_url($url,PHP_URL_QUERY),$query);
	expect($query)->toBe(['action'=>'export', 'selected_items'=>serialize([1, 2])]);
})->with(['automation_graph_rules.php', 'automation_networks.php', 'automation_snmp.php', 'automation_templates.php', 'automation_tree_rules.php']);
