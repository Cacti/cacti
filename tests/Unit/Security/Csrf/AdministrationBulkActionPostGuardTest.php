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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('bulk execution requires a validated POST before entering its handler', function ($file) {
	$probe = dirname(__DIR__, 3) . '/fixtures/BulkActionPostGuardProbe.php';

	foreach (['get'=>[405, false, false], 'tokenless'=>[403, true, false], 'valid'=>[200, true, true]] as $mode=>$expected) {
		$r = cacti_test_isolated_probe($probe, [$file, $mode]);
		expect([$r['status'], $r['checked'], $r['reached']])->toBe($expected);
	}
})->with(['host.php', 'layouts.php', 'lib/html_reports.php', 'links.php', 'login_providers.php', 'managers.php', 'package_keys.php', 'package_repos.php', 'tree.php', 'user_admin.php', 'user_group_admin.php']);
