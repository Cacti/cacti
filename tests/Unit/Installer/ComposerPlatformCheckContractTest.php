<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

$root = dirname(__DIR__, 3);

test('Composer leaves extension diagnostics to the Cacti installer', function () use ($root) {
	$composer = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
	$platformCheck = file_get_contents($root . '/include/vendor/composer/platform_check.php');

	expect($composer['config']['platform-check'])->toBe('php-only')
		->and($platformCheck)->toContain('PHP_VERSION_ID')
		->and($platformCheck)->not->toContain('extension_loaded(')
		->and($platformCheck)->not->toContain('$missingExtensions');
});
