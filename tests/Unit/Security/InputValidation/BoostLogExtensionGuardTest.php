<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-m6wx-f538-m6q3: path_boost_log went through settings.php's generic
 * is_valid_pathname() branch instead of the hard .log extension whitelist
 * applied to path_cactilog/path_stderrlog, re-enabling the log-poisoning-to-RCE
 * primitive (point the Boost Debug Log at a .php file under the webroot).
 */

$root   = dirname(__DIR__, 4);
$source = file_get_contents($root . '/settings.php');

test('path_boost_log is covered by the .log extension whitelist', function () use ($source) {
	expect($source)->toContain("\$field_name == 'path_cactilog' || \$field_name == 'path_stderrlog' || \$field_name == 'path_boost_log'");
});
