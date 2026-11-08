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
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
$root = dirname(__DIR__,2);
require $root . '/include/global_constants.php';
require $root . '/lib/Auth/LoginProviderInterface.php';
$mode          = $argv[1];
$verifications = 0;
function read_config_option($name) {
	return $name === 'base_url' ? 'https://cacti.fixture' : '';
}
function auth_checkclear_lockout(string $username,int $realm):void {
}
function auth_process_lockout_check(string $username,int $realm):bool {
	return false;
}
function db_column_exists($table,$column) {
	return false;
}
function auth_process_lockout(string $username,int $realm):void {
}
function db_fetch_row_prepared($sql,$params = []) {
	if ($GLOBALS['mode'] === 'disabled') {
		return ['id'=>1, 'username'=>'fixture', 'password'=>'', 'enabled'=>''];
	}

	if ($GLOBALS['mode'] === 'wrong') {
		return ['id'=>1, 'username'=>'fixture', 'password'=>password_hash('another password', PASSWORD_BCRYPT, ['cost'=>4]), 'enabled'=>'on'];
	}

	return [];
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}
function compat_password_verify($password,$hash) {
	$GLOBALS['verifications']++;

	return password_verify($password,$hash);
}
function cacti_log($message,$output = false,$environ = '',$level = 0) {
}
function cacti_decrypt_secret($value) {
	return false;
}
function get_client_addr() {
	return '127.0.0.1';
}
function __($text) {
	return $text;
}

if (str_starts_with($mode,'saml')) {
	require $root . '/lib/Auth/RedirectLoginProviderInterface.php';
	require $root . '/lib/Auth/AbstractLoginProvider.php';
	require $root . '/lib/Auth/SamlLoginProvider.php';
	$parameters = [];

	if ($mode === 'saml-message') {
		$parameters = ['want_assertions_signed'=>''];
	} elseif ($mode === 'saml-assertion') {
		$parameters = ['want_assertions_signed'=>'on'];
	}
	$provider = new Cacti\Auth\SamlLoginProvider(['id'=>1, 'parameters'=>json_encode($parameters)]);
	$settings = (new ReflectionMethod($provider,'buildSettings'))->invoke($provider);
	print json_encode($settings['security'],JSON_THROW_ON_ERROR) . "\n";
} else {
	require $root . '/lib/Auth/CredentialLoginProviderInterface.php';
	require $root . '/lib/Auth/LocalAuthLoginProvider.php';
	$provider = new Cacti\Auth\LocalAuthLoginProvider();
	$result   = (new ReflectionMethod($provider,'verifyCredential'))->invoke($provider,'fixture','ordinary fixture password');
	print json_encode(['result'=>$result, 'message'=>$error_msg, 'verifications'=>$verifications],JSON_THROW_ON_ERROR) . "\n";
}
