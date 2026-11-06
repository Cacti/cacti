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

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';

test('disabled installer automation survives saving and reloading (#7934)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/InstallerAutomationMode.php');
	expect($result['disabled_values'])->toBe(5)->and($result['network_reads'])->toBe(0);
});

test('installer CSRF timeout returns a valid replacement token and rejects the failed request', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/InstallerCsrfTimeout.php');
	expect($result['error'])->toBe('csrf_timeout')->and($result['csrfMagicToken'])->toStartWith('sid:');
});
