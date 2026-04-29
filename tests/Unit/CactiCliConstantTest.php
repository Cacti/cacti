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

test('global.php defines CACTI_CLI constant', function () {
	$source = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($source)->toContain("define('CACTI_CLI'");
});

test('global.php defines CACTI_WEB constant', function () {
	$source = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($source)->toContain("define('CACTI_WEB'");
});

test('session.php uses CACTI_CLI not php_sapi_name', function () {
	$source = file_get_contents(__DIR__ . '/../../include/session.php');
	expect($source)->toBeString()
		->not->toContain("php_sapi_name() == 'cli'")
		->not->toContain("php_sapi_name() === 'cli'");
	expect($source)->toContain('CACTI_CLI');
});

/*
 * The production code path of CactiCommand::initialize() must require
 * cli_check.php; the test-only escape hatch (PHP_TESTING constant /
 * CACTI_PHP_TESTING env var) must not become the only branch.
 */
test('CactiCommand::initialize requires cli_check.php on production path', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/CactiCommand.php');
	expect($source)->toContain("require_once dirname(__DIR__) . '/include/cli_check.php'");
});

test('CactiCommand::initialize bypass is gated on PHP_TESTING and CACTI_PHP_TESTING only', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/CactiCommand.php');
	expect($source)->toMatch("/defined\\('PHP_TESTING'\\)\\s*\\|\\|\\s*getenv\\('CACTI_PHP_TESTING'\\)/");
});
