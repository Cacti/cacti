<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('integrated plugin references are removed only after a successful install', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');
	$start  = strpos($source, 'public function processStepComplete()');
	$body   = substr($source, $start, strpos($source, "\n\t}\n", $start) - $start);

	$complete = strpos($body, 'STEP_COMPLETE) {');
	$error    = strpos($body, 'STEP_ERROR) {');
	$call     = strpos($body, 'api_plugin_uninstall_integrated()');

	expect(substr_count($body, 'api_plugin_uninstall_integrated()'))->toBe(1)
		->and($call)->toBeGreaterThan($complete)
		->and($call)->toBeLessThan($error);
});
