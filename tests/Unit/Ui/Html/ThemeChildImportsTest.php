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
 * A child theme names its parent in three places: the `parent` file that
 * get_theme_paths() reads, the @import in main.css and the include in
 * rrdtheme.php. Only the first is read by PHP, so these checks keep the other
 * two in step with it, and keep each @import cache buster equal to the md5 of
 * the file it loads, because browsers cache the imported sheet by that URL.
 */

/**
 * Lists the theme directories that declare a parent theme.
 *
 * @return array<string, array{string}> Dataset of theme name => [theme name]
 */
function theme_child_imports_children() : array {
	$children = [];

	foreach (glob(dirname(__DIR__, 4) . '/include/themes/*/parent') as $file) {
		$theme            = basename(dirname($file));
		$children[$theme] = [$theme];
	}

	return $children;
}

test('child theme imports carry the md5 of the file they load', function (string $theme) {
	$dir = dirname(__DIR__, 4) . '/include/themes/' . $theme;

	preg_match_all('/@import\s+(?:url\(\s*)?[\'"]([^\'"?]+)\?([0-9a-f]{32})[\'"]/', file_get_contents($dir . '/main.css'), $imports, PREG_SET_ORDER);

	expect($imports)->not->toBeEmpty();

	foreach ($imports as [, $path, $hash]) {
		expect(is_file($dir . '/' . $path))->toBeTrue($theme . ' imports missing ' . $path);
		expect($hash)->toBe(md5_file($dir . '/' . $path), $theme . '/main.css has a stale hash for ' . $path);
	}
})->with(theme_child_imports_children());

test('child theme stylesheet and graph colors load the declared parent', function (string $theme) {
	$dir    = dirname(__DIR__, 4) . '/include/themes/' . $theme;
	$parent = trim(file_get_contents($dir . '/parent'));

	expect(file_get_contents($dir . '/main.css'))->toContain('@import \'../' . $parent . '/main.css?');
	expect(file_get_contents($dir . '/rrdtheme.php'))->toContain('include __DIR__ . \'/../' . $parent . '/rrdtheme.php\';');
})->with(theme_child_imports_children());
