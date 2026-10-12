<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('script exclusions remain correct through publication and management changes', function (string $scenario) {
	if (!getenv('BOOST_DB_HOST')) $this->markTestSkipped('Set BOOST_DB_HOST for the dedicated MariaDB contract database.');
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UnusedSourceCacheProbe.php', [$scenario]);
	expect($r['passed'])->toBeTrue();
})->with(['eligibility', 'publish-failure', 'graph-lifecycle', 'remote', 'remote-race']);
