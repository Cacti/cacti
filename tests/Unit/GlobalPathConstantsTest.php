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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/**
 * Test for PR #6702: Verify CACTI_PATH_MIBS constant is defined
 */

test('CACTI_PATH_MIBS constant is defined in global_path.php', function () {
	// Simulate config array that would be set before including global_path.php
	global $config;

	expect(defined('CACTI_PATH_MIBS'))
		->toBeTrue('CACTI_PATH_MIBS constant should be defined');
});

test('CACTI_PATH_MIBS uses mibs_path from config', function () {
	global $config;

	$expectedPath = CACTI_PATH_BASE . '/mibs';

	expect(CACTI_PATH_MIBS)
		->toBe($expectedPath, 'CACTI_PATH_MIBS should match config mibs_path');
});

test('CACTI_PATH_MIBS constant matches other CACTI_PATH_ patterns', function () {
	global $config;

	// Verify it follows the same pattern as other path constants
	expect(defined('CACTI_PATH_MIBS'))
		->toBeTrue()
		->and(defined('CACTI_PATH_BASE'))
		->toBeTrue('Other CACTI_PATH_ constants should also be defined')
		->and(CACTI_PATH_MIBS)
		->toBeString()
		->and(strlen(CACTI_PATH_MIBS))
		->toBeGreaterThan(0, 'CACTI_PATH_MIBS should not be empty');
});
