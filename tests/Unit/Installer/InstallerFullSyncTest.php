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

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

test('the full sync guard is read once and recorded after every collector was tried', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/install/functions.php');
	$start  = strpos($source, 'function install_full_sync()');
	$body   = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	/*
	 * install_replicate used to be set inside the loop and read by the same
	 * condition for the next collector, so only the first remote data collector
	 * was synchronized and the others fell silently into the skipped branch.
	 */
	$loopStart = strpos($body, 'foreach ($pollers as $poller)');
	$flagSet   = strpos($body, "set_config_option('install_replicate', 1)");

	expect(substr_count($body, "set_config_option('install_replicate', 1)"))->toBe(1)
		->and($flagSet)->toBeGreaterThan($loopStart)
		->and(substr($body, $loopStart, strpos($body, "\n\t}\n", $loopStart) - $loopStart))->not->toContain("set_config_option('install_replicate'")
		->and($body)->toContain('if ($replicate) {');
});
