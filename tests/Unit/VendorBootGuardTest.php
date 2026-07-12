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

/*
 * Tests for the vendor boot guard and installer refresh (#6456).
 *
 * A Git checkout has no include/vendor, and global.php previously died
 * with a bare autoload fatal.  The guard prints the composer command and
 * exits before any database work.  The installer additionally repairs a
 * vendor tree that composer reports as purely missing packages.
 */

test('global.php guards the autoload require with instructions', function () {
	$src = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($src)->not->toBeFalse('Failed to read include/global.php');

	$guardPos = strpos($src, "is_file(CACTI_PATH_INCLUDE . '/vendor/autoload.php')");
	expect($guardPos)->not->toBeFalse('vendor guard must exist');

	$requirePos = strpos($src, "require_once(CACTI_PATH_INCLUDE . '/vendor/autoload.php')");
	expect($requirePos)->not->toBeFalse('autoload require must exist')
		->and($guardPos)->toBeLessThan($requirePos, 'guard must run before the require');

	$dbPos = strpos($src, 'db_connect_real(');
	expect($dbPos)->not->toBeFalse('db connect must exist')
		->and($guardPos)->toBeLessThan($dbPos, 'guard must run before any database work');

	$block = substr($src, $guardPos, 800);
	expect(strpos($block, 'composer install'))->not->toBeFalse('guard must name the composer command');
});

test('composer dry-run summary parsing gates the refresh correctly', function () {
	$parse = function (string $check): string {
		if (str_contains($check, 'Nothing to install, update or remove')) {
			return 'current';
		}

		if (!preg_match('/Package operations: (\d+) installs?, (\d+) updates?, (\d+) removals?/', $check, $ops)) {
			return 'indeterminate';
		}

		if ($ops[1] == 0 || $ops[2] > 0 || $ops[3] > 0) {
			return 'skewed';
		}

		return 'repair';
	};

	// pristine packaged release
	expect($parse('Nothing to install, update or remove'))->toBe('current');
	// git checkout without vendor: observed in the docker run for this change
	expect($parse('Package operations: 115 installs, 0 updates, 0 removals'))->toBe('repair');
	// committed tree behind the lock file: not the installer's business
	expect($parse('Package operations: 1 install, 21 updates, 0 removals'))->toBe('skewed');
	expect($parse('Package operations: 0 installs, 6 updates, 79 removals'))->toBe('skewed');
	// composer itself failed (missing extensions, network, bad json)
	expect($parse('Your requirements could not be resolved to an installable set of packages.'))->toBe('indeterminate');
	expect($parse(''))->toBe('indeterminate');
	// singular forms
	expect($parse('Package operations: 1 install, 0 updates, 0 removals'))->toBe('repair');
});
