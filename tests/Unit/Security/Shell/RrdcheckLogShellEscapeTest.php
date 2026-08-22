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
 * Regression test for issue#7473.
 *
 * The rrdcheck poller builds a log redirect from path_rrdcheck_log inside the
 * args passed to exec_background(), which does not escape string args.  No
 * settings form exposes path_rrdcheck_log today, so this is defense in depth,
 * but the code ships the unescaped pattern and a future settings field would
 * reactivate it.  release/1.2.31 already quotes the value.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

$source = file_get_contents(__DIR__ . '/../../../../lib/rrdcheck.php');

test('rrdcheck quotes path_rrdcheck_log before building the log redirect', function () use ($source) {
	expect($source)->toContain("\$safe_log = cacti_escapeshellarg(read_config_option('path_rrdcheck_log'));")
		->and($source)->toContain("poller_rrdcheck.php >> ' . \$safe_log . ' 2>&1'");
});

test('rrdcheck never concatenates the raw log path into the redirect', function () use ($source) {
	expect($source)->not->toContain("poller_rrdcheck.php >> ' . read_config_option('path_rrdcheck_log')");
});

test('escaping the malicious rrdcheck log path keeps the injection in one argument', function () {
	$evil = '/tmp/rrdcheck.log 2>&1; touch /tmp/pwned ; echo';

	$args = 'poller_rrdcheck.php >> ' . cacti_escapeshellarg($evil) . ' 2>&1';

	expect($args)->toContain("'/tmp/rrdcheck.log 2>&1; touch /tmp/pwned ; echo'")
		->and(substr_count($args, "'"))->toBe(2);
});
