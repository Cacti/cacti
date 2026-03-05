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
