<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs the theme name and setting value checks in the places that use them,
 * with the production functions loaded into this namespace and the database,
 * request and session boundaries replaced by the stand-ins below.
 *
 * Usage: ThemeNameHardening.php [web-guard]
 * The web-guard mode runs the Midwinter hash script's command line guard as a
 * web request would, which ends the process.
 */

namespace Cacti\Test\ThemeNameHardening;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';

if (!\defined('CACTI_PATH_INCLUDE')) {
	\define('CACTI_PATH_INCLUDE', $root . '/include');
}

\define('CACTI_PATH_LIBRARY', $root . '/lib');

function get_guest_account() : int {
	return 0;
}
function is_view_allowed(string $view) : bool {
	return $view === 'graph_settings';
}

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

$GLOBALS['fixture'] = ['config' => ['selected_theme' => 'modern'], 'user_theme' => '', 'request' => [], 'queries' => [], 'settings' => [], 'killed' => [], 'status' => null];

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['fixture']['config'][$name] ?? '';
}
function db_table_exists(string $table) : bool {
	return true;
}
function db_fetch_cell_prepared(string $sql, array $params = [], string $column = '', bool $log = true) : mixed {
	return $GLOBALS['fixture']['user_theme'];
}
function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['fixture']['queries'][] = $params;

	return true;
}
function isrv(string $name) : bool {
	return isset($GLOBALS['fixture']['request'][$name]);
}
function gnrv(string $name) : mixed {
	return $GLOBALS['fixture']['request'][$name] ?? null;
}
function srv(string $name, mixed $value) : void {
	$GLOBALS['fixture']['request'][$name] = $value;
}
function set_user_setting(string $name, mixed $value, mixed $user) : void {
	$GLOBALS['fixture']['settings'][$name] = $value;
}
function kill_session_var(string $name) : void {
	$GLOBALS['fixture']['killed'][] = $name;
	unset($_SESSION[$name]);
}
function get_theme_paths(string $format, string $path, ?string $theme = null, ?string $file = null, bool $pathFirst = false, mixed ...$args) : string {
	return sprintf($format, $path . $file, ...$args);
}
function http_response_code(int $code) : void {
	$GLOBALS['fixture']['status'] = $code;
}

foreach (['cacti_is_theme_name', 'cacti_sizeof', 'is_valid_theme', 'get_selected_theme', 'cacti_validate_theme', 'cacti_js_encode', 'save_user_settings', 'get_md5_include_css'] as $function) {
	\cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

if (($argv[1] ?? '') === 'web-guard') {
	// Unqualified PHP_SAPI in the script resolves to this namespace first.
	\define(__NAMESPACE__ . '\PHP_SAPI', 'fpm-fcgi');
	register_shutdown_function(static function () : void {
		if ($GLOBALS['fixture']['status'] !== 404) {
			fwrite(STDERR, "The hash script ran outside the command line\n");
			exit(1);
		}
	});
	\cacti_fixture_source_block($root . '/include/themes/midwinter/update_hash.php', __NAMESPACE__, "/^(if \\(PHP_SAPI !== 'cli'\\) \\{\n.*?^\\})/ms");

	throw new \RuntimeException('The hash script guard did not end the request');
}

// Theme names: only plain names of installed themes pass.
check(cacti_is_theme_name('modern') && !cacti_is_theme_name('modern/../modern') && !cacti_is_theme_name('absent') && !cacti_is_theme_name(null), 'Theme name check');

$GLOBALS['themes'] = ['../modern' => 'Detour', 'modern' => 'Modern'];
$_SESSION          = [\SESS_USER_ID => 7, 'selected_theme' => 'modern'];
check(get_selected_theme() === 'modern', 'Session theme');

// A stored name that fails the check is replaced by the first installed theme.
$_SESSION                         = [\SESS_USER_ID => 7, 'selected_theme' => '../modern'];
$GLOBALS['fixture']['user_theme'] = "modern'";
check(get_selected_theme() === 'modern' && end($GLOBALS['fixture']['queries']) === ['modern', 7], 'Stored theme replacement');

// Without a usable configured theme is_valid_theme falls back to the theme list.
$GLOBALS['fixture']['config']['selected_theme'] = '<b>';
$theme                                          = 'modern/';
check(is_valid_theme($theme, 7) && $theme === 'modern', 'Theme list fallback');
$GLOBALS['fixture']['config']['selected_theme'] = 'modern';
$theme                                          = '-modern';
check(is_valid_theme($theme, 7) && $theme === 'modern', 'Configured theme fallback');

// Without a session nothing is cached and a bad configured name is not returned.
$_SESSION                                       = [];
$GLOBALS['fixture']['config']['selected_theme'] = 'modern/../../x';
check(get_selected_theme() === 'modern', 'Configured theme without a session');
$GLOBALS['fixture']['config']['selected_theme'] = 'modern';

// Setting values: a fixed-list setting stores only listed values.
$GLOBALS['settings_user'] = ['general' => [
	'selected_theme' => ['method' => 'drop_array', 'array' => ['modern' => 'Modern', 'dark' => 'Dark']],
	'user_language'  => ['method' => 'drop_array', 'array' => ['en-US' => 'English']],
	'full_name'      => ['method' => 'textbox'],
]];
$GLOBALS['fixture']['request'] = ['selected_theme' => '../../x', 'user_language' => 'en-US', 'full_name' => 'Operator'];
save_user_settings(7);
check($GLOBALS['fixture']['settings'] === ['user_language' => 'en-US', 'full_name' => 'Operator'], 'Profile form save');
check(!cacti_validate_user_setting_value('selected_theme', ['dark'], ['method' => 'drop_array', 'array' => ['dark' => 'Dark']]), 'Array value');
check(cacti_validate_user_setting_value('full_name', 'any', ['method' => 'textbox']) === 'any', 'Unlisted setting');

// The single-setting update on the profile page uses the same check.
\cacti_fixture_source_function($root . '/auth_profile.php', 'api_auth_update_user_setting', __NAMESPACE__);
$_SESSION                      = [\SESS_USER_ID => 7, 'selected_theme' => 'modern'];
$GLOBALS['fixture']['queries'] = [];
api_auth_update_user_setting('selected_theme', '../../x');
check($GLOBALS['fixture']['queries'] === [] && isset($_SESSION['selected_theme']), 'Unlisted profile value stored');
api_auth_update_user_setting('selected_theme', 'dark');
check($GLOBALS['fixture']['queries'] === [['selected_theme', 'dark', 7]] && !isset($_SESSION['selected_theme']), 'Listed profile value not stored');

// The newtheme flag only drops the cached name; its value is never stored.
$_SESSION                      = [\SESS_USER_ID => 7, 'selected_theme' => 'dark'];
$GLOBALS['fixture']['request'] = ['newtheme' => '../../x'];
\cacti_fixture_source_block($root . '/include/global.php', __NAMESPACE__, "/^\\t(if \\(isrv\\('newtheme'\\)\\) \\{\n.*?\n\\t\\})/ms");
check(!isset($_SESSION['selected_theme']), 'newtheme flag');

// Pages print the name as an encoded script value.
$_SESSION = [\SESS_USER_ID => 7, 'selected_theme' => 'dark'];
ob_start();
\cacti_fixture_source_block($root . '/include/global_session.php', __NAMESPACE__, "/(\\?>';\n\\tvar theme=<\\?php print cacti_js_encode\\(get_selected_theme\\(\\)\\); \\?>;)/");
check(str_contains(ob_get_clean(), 'var theme="dark";'), 'Encoded page theme');

// The deferred stylesheet link restores its media from onload.
check(str_contains(get_md5_include_css('include/css/', true, null, 'main.css'), 'onload="this.media=\'all\'"'), 'Deferred stylesheet link');

// Cached graph images are keyed by the validated theme and the color mode.
\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_graph_cache_filename', __NAMESPACE__);
$GLOBALS['fixture']['config']['boost_png_cache_secret'] = str_repeat('a', 64);
$keys                                                   = [];

foreach ([['dark', null], ['dark', 'light'], ['modern', 'light'], ['absent', 'light']] as [$graph_theme, $mode]) {
	$_COOKIE = $mode === null ? [] : ['CactiColorMode' => $mode];
	$keys[]  = boost_graph_cache_filename('/cache', 1, 0, 0, '', ['graph_theme' => $graph_theme]);
}
check(count(array_unique($keys)) === 3 && $keys[3] === $keys[2], 'Graph cache key');

// The Midwinter hash script lists stylesheets without Cacti's string helpers.
// It uses SPL classes, which a namespace does not fall back for, so load it globally.
\cacti_fixture_source_function($root . '/include/themes/midwinter/update_hash.php', 'file_search', '');
$directory = sys_get_temp_dir() . '/cacti-theme-hash-' . bin2hex(random_bytes(4));
mkdir($directory);

foreach (['a.CSS', 'b.txt'] as $name) {
	touch($directory . '/' . $name);
}
$found = array_map('basename', array_map('strval', \file_search($directory, ['css'])));
array_map('unlink', glob($directory . '/*'));
rmdir($directory);
check($found === ['a.CSS'], 'Stylesheet search');

// Theme subdirectories send direct requests back to the theme index.
$guards = 0;

foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/include/themes', \FilesystemIterator::SKIP_DOTS)) as $file) {
	if ($file->getFilename() === 'index.php' && preg_match('/\*\/\s*header\(\'Location:\.\.\/index\.php\'\);\s*$/', file_get_contents($file->getPathname()))) {
		include $file->getPathname();
		$guards++;
	}
}
check($guards >= 13, 'Theme index guards');
