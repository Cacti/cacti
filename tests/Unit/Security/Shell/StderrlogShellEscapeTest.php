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

/*
 * Regression test for issue#7466.
 *
 * The main poller builds an stderr redirect from path_stderrlog, an
 * admin-settable Settings > Paths field, and passes it to exec_background()
 * as $extra_parms.  exec_background() passes redirect args to the shell
 * unescaped, so the path must be quoted at the call site.  release/1.2.31
 * wrapped it in cacti_escapeshellarg(); the fix never reached develop.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

$pollerSource = file_get_contents(__DIR__ . '/../../../../poller.php');

test('poller quotes path_stderrlog before building the stderr redirect', function () use ($pollerSource) {
	expect($pollerSource)->toContain("'>> ' . cacti_escapeshellarg(read_config_option('path_stderrlog')) . ' 2>&1'");
});

test('poller never concatenates the raw stderr path into the redirect', function () use ($pollerSource) {
	expect($pollerSource)->not->toContain("'>> ' . read_config_option('path_stderrlog') . ' 2>&1'");
});

test('escaping the malicious stderr path keeps the injection inside one argument', function () {
	$evil = '/tmp/poller.err 2>&1; touch /tmp/pwned ; echo';

	$redirect = '>> ' . cacti_escapeshellarg($evil) . ' 2>&1';

	expect($redirect)->toBe(">> '/tmp/poller.err 2>&1; touch /tmp/pwned ; echo' 2>&1")
		->and(substr_count($redirect, "'"))->toBe(2);
});
