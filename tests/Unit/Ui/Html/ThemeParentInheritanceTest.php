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
 * A theme directory holding a `parent` file inherits every file it does not
 * ship from that parent. The fixtures are real theme directories because
 * get_theme_paths() and get_theme_parent() only look under include/themes.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

$theme_fixture_tag = 'zzparenttest' . getmypid();
// Pest includes test files from inside a method, so file-scope variables are
// not globals unless written through $GLOBALS.
$GLOBALS['theme_fixture_dirs'] = [];

/**
 * Creates a fixture theme directory holding the given files.
 *
 * @param string                $name  Theme directory name
 * @param array<string, string> $files File name => contents
 *
 * @return void
 */
function theme_parent_fixture(string $name, array $files) : void {
	$dir = CACTI_PATH_INCLUDE . '/themes/' . $name;

	if (!is_dir($dir) && !mkdir($dir)) {
		throw new RuntimeException('Unable to create theme fixture ' . $dir);
	}

	$GLOBALS['theme_fixture_dirs'][] = $dir;

	foreach ($files as $file => $contents) {
		file_put_contents($dir . '/' . $file, $contents);
	}
}

/**
 * Returns the include paths get_theme_paths() resolves for a theme file.
 *
 * @param string $theme Theme directory name
 * @param string $file  File to resolve
 *
 * @return array<int, string> Resolved paths with the URL prefix and hash removed
 */
function theme_parent_resolve(string $theme, string $file) : array {
	$output = get_theme_paths('%s|', 'include/css/', $theme, $file);
	$found  = [];

	foreach (array_filter(explode('|', (string) $output)) as $url) {
		$found[] = preg_replace('/\?.*$/', '', substr($url, strlen(CACTI_PATH_URL)));
	}

	return $found;
}

beforeAll(function () use ($theme_fixture_tag) {
	theme_parent_fixture($theme_fixture_tag . 'base', [
		'main.css'   => '/* parent */',
		'main.js'    => '/* parent */',
		'shared.css' => '/* parent only */',
	]);

	theme_parent_fixture($theme_fixture_tag . 'child', [
		'main.css' => '/* child */',
		'main.js'  => '/* child */',
		'parent'   => '  ' . $theme_fixture_tag . "base\n",
	]);
});

afterAll(function () {
	foreach (array_reverse($GLOBALS['theme_fixture_dirs']) as $dir) {
		foreach (glob($dir . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $file) {
			unlink($file);
		}

		rmdir($dir);
	}
});

test('a child theme file wins and the parent copy is not also loaded', function () use ($theme_fixture_tag) {
	$found = theme_parent_resolve($theme_fixture_tag . 'child', 'main.js');

	expect($found)->toContain('include/themes/' . $theme_fixture_tag . 'child/main.js');
	expect($found)->not->toContain('include/themes/' . $theme_fixture_tag . 'base/main.js');
});

test('a file the child does not ship falls back to the parent', function () use ($theme_fixture_tag) {
	$found = theme_parent_resolve($theme_fixture_tag . 'child', 'shared.css');

	expect($found)->toBe(['include/themes/' . $theme_fixture_tag . 'base/shared.css']);
	expect(get_theme_parent($theme_fixture_tag . 'child'))->toBe($theme_fixture_tag . 'base');
});

test('a theme without a parent file is unchanged', function () use ($theme_fixture_tag) {
	expect(get_theme_parent($theme_fixture_tag . 'base'))->toBe('');
	expect(theme_parent_resolve($theme_fixture_tag . 'base', 'missing.css'))->toBe([]);
});

test('an invalid parent name is ignored', function (string $suffix, string $parent) use ($theme_fixture_tag) {
	$child = $theme_fixture_tag . $suffix;

	theme_parent_fixture($child, [
		'main.css' => '/* child */',
		'parent'   => str_replace('{self}', $child, $parent),
	]);

	expect(get_theme_parent($child))->toBe('');
	expect(theme_parent_resolve($child, 'shared.css'))->toBe([]);
})->with([
	'traversal'   => ['trav', '../' . 'zzparenttest' . getmypid() . 'base'],
	'empty'       => ['empty', ''],
	'self'        => ['self', '{self}'],
	'nonexistent' => ['ghost', 'no-such-theme-' . getmypid()],
	'uppercase'   => ['upper', 'Modern'],
]);

test('the parent of a parent is not followed', function () use ($theme_fixture_tag) {
	theme_parent_fixture($theme_fixture_tag . 'grand', [
		'main.css' => '/* grandchild */',
		'parent'   => $theme_fixture_tag . 'child',
	]);

	expect(get_theme_parent($theme_fixture_tag . 'grand'))->toBe($theme_fixture_tag . 'child');
	expect(theme_parent_resolve($theme_fixture_tag . 'grand', 'shared.css'))->toBe([]);
});
