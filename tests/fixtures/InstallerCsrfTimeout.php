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

namespace CactiInstallerCsrfFixture;
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 2);
$GLOBALS['config'] = ['base_path' => $root, 'include_path' => $root . '/include', 'is_web' => false];
require $root . '/include/csrf.php';
$GLOBALS['csrf']['secret'] = str_repeat('a', 64);
$GLOBALS['csrf']['key'] = false;
$GLOBALS['csrf']['user'] = false;
$GLOBALS['csrf']['cookie'] = false;
$GLOBALS['csrf']['session'] = true;
$GLOBALS['csrf']['allow-ip'] = false;
$GLOBALS['csrf']['expires'] = 7200;
$GLOBALS['csrf']['hash'] = 'sha256';
$GLOBALS['csrf']['log_file'] = '';
session_name('InstallerFixture');
session_start();
$old_id = session_id();
$_COOKIE[session_name()] = $old_id;
define('IN_CACTI_INSTALL', 1);
$GLOBALS['auth_json'] = true;
function __($text) { return $text; }
function csrf_log(...$args) {}
function csrf_get_tokens() {
	$token = \csrf_get_tokens();
	if (!\csrf_check_tokens($token)) { throw new \RuntimeException('Replacement token is invalid.'); }
	return $token;
}
\cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_error_callback', __NAMESPACE__);
register_shutdown_function(function () use ($old_id) {
	if (http_response_code() !== 403 || session_id() === $old_id) { throw new \RuntimeException('Installer CSRF timeout did not reject and rotate its session.'); }
	session_destroy();
});
ob_start();
csrf_error_callback();
