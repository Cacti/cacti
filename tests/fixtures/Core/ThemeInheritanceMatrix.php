<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ThemeInheritance;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

// Resolve themes against a private tree so the fixture never writes into the
// checkout; get_theme_parent() and get_include_relpath() read these constants.
$root = sys_get_temp_dir() . '/cacti-theme-inheritance-' . getmypid();
define('CACTI_PATH_BASE', $root);
define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_URL', '/cacti/');

function get_md5_hash($file) {
	return 'h';
}
function get_selected_theme() {
	throw new RuntimeException('Every case names its theme');
}
function debounce_run_notification($key) {
	return false;
}

$source = dirname(__DIR__, 3) . '/lib/functions.php';

foreach (['get_include_relpath', 'get_theme_candidate_paths', 'get_theme_parent', 'get_theme_paths'] as $function) {
	\cacti_fixture_source_function($source, $function, __NAMESPACE__);
}

$themes = [
	'base'   => ['main.css' => '', 'main.js' => '', 'shared.css' => ''],
	'child'  => ['main.css' => '', 'main.js' => '', 'parent' => "  base\n"],
	'grand'  => ['main.css' => '', 'parent' => 'child'],
	'trav'   => ['main.css' => '', 'parent' => '../base'],
	'empty'  => ['main.css' => '', 'parent' => ''],
	'self'   => ['main.css' => '', 'parent' => 'self'],
	'ghost'  => ['main.css' => '', 'parent' => 'no-such-theme'],
	'upper'  => ['main.css' => '', 'parent' => 'Base'],
	'nocss'  => ['main.css' => '', 'parent' => 'bare'],
	'bare'   => ['main.js' => ''],
];

foreach ($themes as $theme => $files) {
	mkdir(CACTI_PATH_INCLUDE . '/themes/' . $theme, 0700, true);

	foreach ($files as $file => $contents) {
		file_put_contents(CACTI_PATH_INCLUDE . '/themes/' . $theme . '/' . $file, $contents);
	}
}

register_shutdown_function(static function () use ($root, $themes) : void {
	foreach ($themes as $theme => $files) {
		foreach (array_keys($files) as $file) {
			unlink(CACTI_PATH_INCLUDE . '/themes/' . $theme . '/' . $file);
		}
		rmdir(CACTI_PATH_INCLUDE . '/themes/' . $theme);
	}
	rmdir(CACTI_PATH_INCLUDE . '/themes');
	rmdir(CACTI_PATH_INCLUDE);
	rmdir($root);
});

/**
 * @return array<int, string> Resolved include paths without the URL prefix or hash
 */
function resolve(string $path, string $theme, string $file) : array {
	$found = [];

	foreach (array_filter(explode(PHP_EOL, get_theme_paths('%s', $path, $theme, $file))) as $url) {
		$found[] = preg_replace('/\?.*$/', '', substr($url, strlen(CACTI_PATH_URL)));
	}

	return $found;
}

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$count = 0;

// Candidate order: path + theme, include/themes + theme + path, include/themes + theme.
check(get_theme_candidate_paths('include/css/', 'child/') === ['include/css/child/', 'include/themes/child/include/css/', 'include/themes/child/'], 'Candidate path order changed');
$count++;

// Only a valid single-level parent with a main.css is accepted; results are cached.
foreach (['child' => 'base', 'grand' => 'child', 'base' => '', 'trav' => '', 'empty' => '', 'self' => '', 'ghost' => '', 'upper' => '', 'nocss' => '', 'Bad/Name' => ''] as $theme => $parent) {
	check(get_theme_parent($theme) === $parent, 'Unexpected parent for ' . $theme);
	check(get_theme_parent($theme) === $parent, 'Cached parent changed for ' . $theme);
	$count++;
}

// A child copy wins and the parent copy is not also emitted.
check(resolve('include/css/', 'child', 'main.js') === ['include/themes/child/main.js'], 'Child copy did not win');
$count++;

// A file the child does not ship comes from the parent.
check(resolve('include/css/', 'child', 'shared.css') === ['include/themes/base/shared.css'], 'Parent fallback missing');
$count++;

// The parent of a parent is never followed.
check(resolve('include/css/', 'grand', 'shared.css') === [], 'Grandparent was followed');
$count++;

// Invalid parent names never resolve a file.
foreach (['trav', 'empty', 'self', 'ghost', 'upper', 'nocss'] as $theme) {
	check(resolve('include/css/', $theme, 'shared.css') === [], 'Invalid parent resolved for ' . $theme);
	$count++;
}

// Resolving within include/themes/ itself skips the nested candidates.
check(resolve('include/themes/', 'child', 'shared.css') === ['include/themes/base/shared.css'], 'Theme-root parent fallback missing');
$count++;

// Palette themes: the directory index prints nothing and graph colors are rrdtool hex values.
$repoThemes = dirname(__DIR__, 3) . '/include/themes/';

foreach (glob($repoThemes . '*/parent') as $parentFile) {
	$dir = dirname($parentFile);

	ob_start();
	include $dir . '/index.php';
	check(ob_get_clean() === '', 'Theme index printed output: ' . $dir);

	$settings = (static function (string $file) : array {
		include $file;

		return get_defined_vars();
	})($dir . '/rrdtheme.php');

	check($settings['rrdcolors'] === $settings['rrdcolors_light'], 'Default graph colors are not the light set: ' . $dir);
	check($settings['rrdcolors_light'] !== $settings['rrdcolors_dark'], 'Light and dark graph colors match: ' . $dir);

	foreach (['rrdcolors_light', 'rrdcolors_dark'] as $set) {
		foreach ($settings[$set] as $color) {
			check(preg_match('/^[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color) === 1, 'Bad graph color in ' . $dir);
		}
	}

	check(isset($settings['rrdfonts']), 'Parent graph fonts were not inherited: ' . $dir);
	$count++;
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
