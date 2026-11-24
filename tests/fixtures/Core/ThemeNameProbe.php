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
 * Runs the unchanged profile setting update from auth_profile.php, the graph
 * cache name from lib/boost.php and the theme directory index guards in their
 * own process, and prints what they did.
 */

namespace CactiThemeNameFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);

$GLOBALS['config'] = array(
	'is_web'               => false,
	'base_path'            => $root,
	'config_options_array' => array('storage_location' => 0, 'selected_theme' => 'modern'),
);

require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['queries'][] = array(preg_replace('/\s+/', ' ', $sql), $params);

	return true;
}

function is_view_allowed($realm) {
	return in_array($realm, $GLOBALS['allowed_realms'], true);
}

function kill_session_var($name) {
	unset($_SESSION[$name]);
}

function db_table_exists($table) {
	return true;
}

function db_fetch_cell_prepared($sql, $params = array(), $column = '', $log = true) {
	$GLOBALS['queries'][] = array(preg_replace('/\s+/', ' ', $sql), $params);

	return $GLOBALS['stored_theme'];
}

function isset_request_var($name) {
	return isset($_REQUEST[$name]);
}

function get_nfilter_request_var($name, $default = '') {
	return $_REQUEST[$name] ?? $default;
}

function set_request_var($name, $value) {
	$_REQUEST[$name] = $value;
}

function set_user_setting($name, $value, $user = -1) {
	$GLOBALS['stored'][$name] = array($value, $user);
}

function read_config_option($name, $force = false) {
	return $name === 'boost_png_cache_secret' ? str_repeat('ab', 32) : \read_config_option($name, $force);
}

\cacti_fixture_source_function($root . '/auth_profile.php', 'api_auth_user_setting_allowed', __NAMESPACE__);
\cacti_fixture_source_function($root . '/auth_profile.php', 'api_auth_update_user_setting', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/functions.php', 'save_user_settings', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/functions.php', 'get_selected_theme', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/boost.php', 'boost_graph_cache_filename', __NAMESPACE__);

/* Store one setting as a user holding the given realms and report the queries sent. */
function update_setting($name, $value, $realms) {
	$GLOBALS['settings_user'] = array(
		'general' => array(
			'selected_theme' => array('method' => 'drop_array', 'array' => array('modern' => 'Modern', 'dark' => 'Dark')),
			'enable_hscroll' => array('method' => 'checkbox'),
		),
	);
	$GLOBALS['allowed_realms'] = $realms;
	$GLOBALS['queries']        = array();
	$_SESSION                  = array('sess_user_id' => 7, 'selected_theme' => 'dark');

	api_auth_update_user_setting($name, $value);

	return array('queries' => $GLOBALS['queries'], 'theme_kept' => isset($_SESSION['selected_theme']));
}

function profile() {
	return array(
		'listed theme'          => update_setting('selected_theme', 'dark', array('graph_settings')),
		'unlisted theme'        => update_setting('selected_theme', '../../other', array('graph_settings')),
		'no settings realm'     => update_setting('selected_theme', 'dark', array()),
		'scroll without realm'  => update_setting('enable_hscroll', 'on', array()),
		'unknown setting'       => update_setting('not_a_setting', 'x', array('graph_settings')),
	);
}

/* Save the profile form with a listed and an unlisted theme. */
function save() {
	$GLOBALS['settings_user'] = array(
		'general' => array(
			'selected_theme' => array('method' => 'drop_array', 'array' => array('modern' => 'Modern', 'dark' => 'Dark')),
			'user_language'  => array('method' => 'drop_array', 'array' => array('en-US' => 'English')),
			'full_name'      => array('method' => 'textbox'),
		),
	);
	$_SESSION = array('sess_user_id' => 7);
	$result   = array();

	foreach (array('listed' => 'dark', 'unlisted' => '../../other') as $case => $theme) {
		$GLOBALS['stored'] = array();
		$_REQUEST          = array('selected_theme' => $theme, 'user_language' => 'en-US', 'full_name' => 'Ann');

		save_user_settings();

		$result[$case] = $GLOBALS['stored'];
	}

	return $result;
}

/* The theme a logged in user gets for a stored theme name. */
function selected() {
	$GLOBALS['themes'] = array('classic' => 'Classic', 'dark' => 'Dark', 'modern' => 'Modern');
	$result            = array();

	foreach (array('installed' => 'classic', 'not installed' => '../../other') as $case => $stored) {
		$GLOBALS['queries']      = array();
		$GLOBALS['stored_theme'] = $stored;
		$_SESSION                = array('sess_user_id' => 7);

		$result[$case] = array('theme' => get_selected_theme(), 'queries' => $GLOBALS['queries']);
	}

	return $result;
}

/* Cache file names for the same graph drawn in different themes and color modes. */
function cache() {
	$name = function ($theme, $mode) {
		if ($mode === null) {
			unset($_COOKIE['CactiColorMode']);
		} else {
			$_COOKIE['CactiColorMode'] = $mode;
		}

		return basename(boost_graph_cache_filename('/cache', 5, 1, 86400, array('graph_theme' => $theme)));
	};

	return array(
		'modern'           => $name('modern', null),
		'modern dark'      => $name('modern', 'dark'),
		'modern light'     => $name('modern', 'light'),
		'modern dimmed'    => $name('modern', 'dark-dimmed'),
		'modern unknown'   => $name('modern', '../x'),
		'classic'          => $name('classic', null),
		'invalid theme'    => $name('../../other', null),
	);
}

/* Request each theme directory index guard added by the theme hardening. */
function guards() {
	global $root;

	$result = array();

	foreach (glob($root . '/include/themes/{*,*/*,*/*/*}/index.php', GLOB_BRACE) as $file) {
		ob_start();
		$returned = include $file;
		$output   = ob_get_clean();
		$headers  = function_exists('xdebug_get_headers') ? xdebug_get_headers() : null;

		$result[substr($file, strlen($root) + 1)] = array(
			'returned' => $returned,
			'output'   => $output,
			'location' => $headers === null ? null : in_array('Location:../index.php', $headers, true),
		);
	}

	return $result;
}

$scenario = $argv[1] ?? '';

if (!in_array($scenario, array('profile', 'save', 'selected', 'cache', 'guards'), true)) {
	fwrite(STDERR, "unknown scenario\n");
	exit(1);
}

print json_encode(call_user_func(__NAMESPACE__ . '\\' . $scenario), JSON_THROW_ON_ERROR);
