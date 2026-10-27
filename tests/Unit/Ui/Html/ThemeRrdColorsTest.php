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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * rrdtheme.php values go straight onto the rrdtool command line as
 * --color TAG#RRGGBB[AA], so a malformed value breaks every graph drawn in
 * that theme rather than one element.
 */

/**
 * Loads a theme's rrdtheme.php in its own scope and returns what it defines.
 *
 * @param string $file Absolute path to rrdtheme.php
 *
 * @return array<string, mixed>
 */
function theme_rrd_settings(string $file) : array {
	include $file;

	return get_defined_vars();
}

/**
 * Lists every shipped theme directory that defines graph colors.
 *
 * @return array<string, array{0: string}>
 */
function theme_rrd_directories() : array {
	$themes = [];

	foreach (glob(dirname(__DIR__, 4) . '/include/themes/*/rrdtheme.php') as $file) {
		$themes[basename(dirname($file))] = [dirname($file)];
	}

	return $themes;
}

test('graph colors are rrdtool hex colors in every mode', function (string $dir) {
	$settings = theme_rrd_settings($dir . '/rrdtheme.php');

	expect($settings)->toHaveKey('rrdcolors');

	foreach (['rrdcolors', 'rrdcolors_light', 'rrdcolors_dark'] as $set) {
		if (!isset($settings[$set])) {
			continue;
		}

		// Light and dark replace the base set wholesale, so a missing key
		// silently falls back to rrdtool's default for that element.
		expect(array_keys($settings[$set]))->toEqual(array_keys($settings['rrdcolors']));

		foreach ($settings[$set] as $color) {
			expect($color)->toMatch('/^[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/');
		}
	}
})->with(theme_rrd_directories());

test('a palette theme defines light and dark graph colors', function (string $dir) {
	$settings = theme_rrd_settings($dir . '/rrdtheme.php');

	expect($settings)->toHaveKeys(['rrdcolors_light', 'rrdcolors_dark']);
	expect($settings['rrdcolors_light'])->not->toEqual($settings['rrdcolors_dark']);
})->with(array_filter(theme_rrd_directories(), fn (array $theme) => is_file($theme[0] . '/parent')));

test('a palette theme directory index only redirects', function (string $dir) {
	ob_start();
	include $dir . '/index.php';

	expect(ob_get_clean())->toBe('');
})->with(array_filter(theme_rrd_directories(), fn (array $theme) => is_file($theme[0] . '/parent')));
