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
 * Regression test for issue#7476.
 *
 * boost_poller_bottom() builds a shell redirect from path_boost_log, an
 * admin-settable free-text Settings > Paths field, and hands it to
 * exec_background() as $redirect_args.  exec_background() passes redirect args
 * to the shell unescaped by design, so the path must be quoted at the call
 * site.  release/1.2.31 wrapped it in cacti_escapeshellarg(); that fix never
 * reached develop, leaving '>> ' . $boost_log . ' 2>&1' open to command
 * injection.  This asserts the path is escaped before it reaches the redirect.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

$boostSource = file_get_contents(__DIR__ . '/../../../../lib/boost.php');

test('boost quotes path_boost_log before building the redirect', function () use ($boostSource) {
	expect($boostSource)->toContain('$safe_log = cacti_escapeshellarg($boost_log);')
		->and($boostSource)->toContain("'>> ' . \$safe_log . ' 2>&1'")
		->and($boostSource)->toContain("'>> ' . \$safe_log");
});

test('boost never concatenates the raw log path into the redirect', function () use ($boostSource) {
	expect($boostSource)->not->toContain("'>> ' . \$boost_log");
});

test('escaping the malicious log path keeps the injection inside one argument', function () {
	// The exact payload the Docker proof-of-concept used against develop.
	$evil = '/tmp/boost.log 2>&1; touch /tmp/pwned ; echo';

	$redirect = '>> ' . cacti_escapeshellarg($evil) . ' 2>&1';

	// The whole payload must sit inside a single quoted token; the ';' that
	// would start a new command on develop is now literal data.
	expect($redirect)->toBe(">> '/tmp/boost.log 2>&1; touch /tmp/pwned ; echo' 2>&1")
		->and(substr_count($redirect, "'"))->toBe(2);
});
