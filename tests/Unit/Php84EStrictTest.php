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
 |                                                                         |
 | E_STRICT can no longer be raised as of PHP 8.0 and reading the constant |
 | is deprecated as of PHP 8.4, so the error handlers must not name it.    |
 +-------------------------------------------------------------------------+
*/

$basePath = dirname(__DIR__, 2);

test('include/global_arrays.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/include/global_arrays.php'))->not->toContain('E_STRICT');
});

test('lib/functions.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/functions.php'))->not->toContain('E_STRICT');
});

test('lib/aggregate.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/aggregate.php'))->not->toContain('E_STRICT');
});

test('lib/rrdcheck.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/rrdcheck.php'))->not->toContain('E_STRICT');
});

test('lib/boost.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/boost.php'))->not->toContain('E_STRICT');
});

test('lib/dsstats.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/dsstats.php'))->not->toContain('E_STRICT');
});

test('lib/dsdebug.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/dsdebug.php'))->not->toContain('E_STRICT');
});

test('userland cannot raise E_STRICT, so the removed key is unreachable', function () {
	/* 2048 is the E_STRICT level, spelled numerically because naming the
	   constant is itself deprecated on PHP 8.4 */
	$strict = 2048;
	$raised = false;

	try {
		trigger_error('probe', $strict);
	} catch (\ValueError $e) {
		$raised = true;
	}

	expect($raised)->toBeTrue();
});

test('the error maps name their levels directly instead of patching them in', function () use ($basePath) {
	$files = ['lib/aggregate.php', 'lib/rrdcheck.php', 'lib/boost.php', 'lib/dsstats.php', 'lib/dsdebug.php'];

	expect(constant('E_RECOVERABLE_ERROR'))->toBe(4096)
		->and(constant('E_DEPRECATED'))->toBe(8192);

	foreach ($files as $file) {
		$source = file_get_contents($basePath . '/' . $file);

		expect($source)->not->toContain('$errortype[E_RECOVERABLE_ERROR]')
			->and($source)->not->toContain('$errortype[E_DEPRECATED]');
	}
});

test('aggregate no longer polyfills a constant that always exists', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/aggregate.php'))->not->toContain("define('E_RECOVERABLE_ERROR'");
});
