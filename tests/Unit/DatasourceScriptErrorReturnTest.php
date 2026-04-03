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

/**
 * Source-scan tests verifying that data source scripts return 'U' on error.
 *
 * Cacti project rule: data source scripts must return 'U' for unknown/error
 * samples, never '0'. Returning '0' causes RRDtool to graph a false zero data
 * point rather than an unknown sample, silently corrupting gauge metrics.
 */

test('ss_webseer.php does not initialise $value to 0', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain("\$value = '0'")
		->and($src)->toContain("\$value = 'U'");
});

test('ss_webseer.php returns U not 0 on empty/missing value', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain("? '0' : \$value")
		->and($src)->toContain("? 'U' : \$value")
		// empty('0') === true in PHP; guard must use strict string comparison
		->and($src)->not->toContain('empty($value)')
		->and($src)->toContain("\$value === ''");
});

test('ss_webseer.php does not return false on fallthrough', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain('return false');
});

test('ss_gexport.php does not initialise $value to 0', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain("\$value = '0'")
		->and($src)->toContain("\$value = 'U'");
});

test('ss_gexport.php returns U not 0 on empty value', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain("? '0' : \$value")
		->and($src)->toContain("? 'U' : \$value");
});

test('ss_gexport.php does not return null on fallthrough', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain('return null');
});

test('query_host_cpu.php prints U when get index is absent', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/query_host_cpu.php');
	// The get handler must have an else branch that prints 'U'; a comment
	// containing the literal would satisfy toContain but not the structural check.
	expect($src)->toContain("print 'U'")
		->and($src)->toContain('} else {')
		->and($src)->not->toContain("/* print 'U'");
});
