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

namespace Sonata\GoogleAuthenticator {
	class GoogleAuthenticator {
		function checkCode($secret, $code) {
			$GLOBALS['token_checks']++;

			return $code === 'valid';
		}
	}
}
namespace {
	$root = dirname(__DIR__, 2);

	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require $root . '/include/global_constants.php';
	require $root . '/tests/Helpers/SourceSlice.php';
	require $root . '/tests/Helpers/FixtureCoverage.php';
	$mode                      = $argv[1] ?? 'valid';
	$_SERVER['REQUEST_METHOD'] = $mode === 'get' ? 'GET' : 'POST';
	$realm                     = str_starts_with($mode,'external') ? 2 : 0;
	$pdo                       = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
	$pdo->exec('CREATE TABLE user_auth(id INTEGER PRIMARY KEY, username TEXT, realm INTEGER, tfa_enabled TEXT, tfa_secret TEXT, login_opts INTEGER, enabled TEXT, lastfail INTEGER, failed_attempts INTEGER, locked TEXT, password TEXT)');
	$pdo->exec('CREATE TABLE user_log(username TEXT, user_id INTEGER, result INTEGER, ip TEXT, time TEXT)');

	if ($mode !== 'missing') {
		$statement = $pdo->prepare('INSERT INTO user_auth VALUES (1,?,?,?,?,?,?,?,?,?,?)');
		$statement->execute(['fixture', $realm, 'on', 'fixture', 1, 'on', str_contains($mode,'expired') ? time() - 120 : time(), 1, str_contains($mode,'locked') || str_contains($mode,'expired') ? 'on' : '', '']);
	}
	$_SESSION                   = [SESS_USER_ID=>1];
	$_COOKIE                    = [];
	$_SERVER['HTTP_USER_AGENT'] = 'Fixture';
	$token_checks               = 0;
	function csrf_check(bool $fatal = true) : bool {
		return $GLOBALS['mode'] !== 'tokenless';
	}
	cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', '');

	function db_fetch_row_prepared($sql, $params = []) {
		$s = $GLOBALS['pdo']->prepare($sql);
		$s->execute($params);

		return $s->fetch(PDO::FETCH_ASSOC);
	}
	function db_fetch_cell_prepared($sql, $params = []) {
		$s = $GLOBALS['pdo']->prepare($sql);
		$s->execute($params);

		return $s->fetchColumn();
	}
	function db_execute_prepared($sql, $params = []) {
		$s = $GLOBALS['pdo']->prepare(str_replace(['INSERT IGNORE', 'NOW()'], ['INSERT OR IGNORE', "datetime('now')"], $sql));

		return $s->execute($params);
	}
	function read_config_option($name) {
		return ['secpass_2fatime'=>60, 'secpass_lockfailed'=>2, 'secpass_unlocktime'=>1][$name] ?? '';
	}
	function cacti_sizeof($value) {
		return is_array($value) ? count($value) : false;
	}
	function cacti_log($message, $output = false, $environ = '', $level = 0) {
	}
	function get_client_addr() {
		return '127.0.0.1';
	}
	function __($text) {
		return $text;
	}
	function set_default_action() {
	}
	function gnrv($name, $default = '') {
		return $name === 'action' ? 'login_2fa' : (str_contains($GLOBALS['mode'],'invalid') ? 'invalid' : 'valid');
	}
	function cacti_cookie_set(...$args) {
	}
	function auth_login_redirect($options) {
	}
	function api_plugin_hook_function($name, $default = null) {
		return $default;
	}
	function get_selected_theme() {
		return 'modern';
	}
	function html_auth_header(...$args) {
		exit;
	}

	foreach (['auth_checkclear_lockout', 'auth_process_lockout_check', 'auth_process_lockout', 'auth_log_failure', 'auth_realm_token'] as $function) {
		cacti_fixture_source_function($root . '/lib/auth.php',$function,'');
	}
	register_shutdown_function(static function () {
		$user = $GLOBALS['pdo']->query('SELECT realm,failed_attempts,locked FROM user_auth')->fetch(PDO::FETCH_ASSOC);
		print json_encode(['status'=>http_response_code() ?: 200, 'user'=>$user, 'token_checks'=>$GLOBALS['token_checks'], 'authenticated'=>!empty($_SESSION[SESS_USER_2FA])],JSON_THROW_ON_ERROR) . "\n";
	});
}
