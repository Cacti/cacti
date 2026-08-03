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
 | The poller error handlers each carried an E_STRICT entry.  Nothing can  |
 | raise that level as of PHP 8.0 and reading the constant is deprecated   |
 | as of PHP 8.4, so every handled error emitted a second deprecation.     |
 +-------------------------------------------------------------------------+
*/

$basePath = dirname(__DIR__, 2);

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
