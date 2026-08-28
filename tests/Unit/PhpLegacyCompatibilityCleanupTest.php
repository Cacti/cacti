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

$base = dirname(__DIR__, 2);

test('removed PHP runtime compatibility branches do not return', function () use ($base) {
	$global    = file_get_contents($base . '/include/global.php');
	$functions = file_get_contents($base . '/lib/functions.php');

	expect($global)
		->not->toContain('get_magic_quotes_gpc')
		->not->toContain("ini_get('register_globals')")
		->and($functions)
		->not->toContain('session_unregister')
		->not->toContain("ini_get('safe_mode')");
});

test('regex validation does not rely on removed track_errors state', function () use ($base) {
	$source = file_get_contents($base . '/lib/html_utility.php');

	expect($source)
		->not->toContain("ini_get('track_errors')")
		->not->toContain("ini_set('track_errors'")
		->not->toContain('preg_match("\'" . $regex . "\'", NULL)')
		->toContain('error_get_last()');
});

test('function availability uses the current disabled-functions contract', function () use ($base) {
	$source = file_get_contents($base . '/lib/functions.php');

	expect($source)
		->toContain("explode(',', (string) ini_get('disable_functions'))")
		->toContain("), true);");
});
