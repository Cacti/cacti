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
 * Regression test for issue#7469.
 *
 * test_data_source()'s two script-server branches interpolated
 * path_php_binary, an admin-set Settings > Paths value, into a command handed
 * to shell_exec().  release/1.2.31 wrapped it in cacti_escapeshellcmd(); the
 * fix never reached develop, leaving authenticated command injection in both
 * the PHP_SCRIPT_SERVER and QUERY_SCRIPT_SERVER paths.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

$source = file_get_contents(__DIR__ . '/../../../../lib/functions.php');

test('the script-server branch escapes path_php_binary', function () use ($source) {
	expect($source)->toContain("\$php   = cacti_escapeshellcmd(read_config_option('path_php_binary'));")
		->and($source)->not->toContain("\$php   = read_config_option('path_php_binary');");
});

test('the query-script-server branch escapes path_php_binary', function () use ($source) {
	expect($source)->toContain("\$script_path = cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q '")
		->and($source)->not->toContain("\$script_path = read_config_option('path_php_binary') . ' -q '");
});

test('escaping neutralizes a shell metacharacter in path_php_binary', function () {
	$evil = 'php ; touch /tmp/pwned ; echo';

	$cmd = cacti_escapeshellcmd($evil) . ' -q /tmp/script.php';

	// escapeshellcmd backslash-escapes the ; so it can no longer start a
	// new command; the injected touch never runs as its own statement.
	expect($cmd)->not->toMatch('/(^|[^\\\\]);\s*touch/')
		->and($cmd)->toContain('\;');
});
