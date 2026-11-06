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

$root        = dirname(__DIR__,2);
$source      = file_get_contents($root . '/auth_2fa.php');
$bootstrap   = __DIR__ . '/AuthSecondFactorBootstrap.php';
$replacement = 'require ' . var_export($bootstrap,true) . ';';
$source      = str_replace("require(__DIR__ . '/include/global.php');",$replacement,$source,$count);

if ($count !== 1) {
	throw new RuntimeException('Expected auth bootstrap missing');
}
$temporary = tempnam(sys_get_temp_dir(),'cacti-2fa-fixture-');
file_put_contents($temporary,$source);
register_shutdown_function(static function () use ($temporary) {unlink($temporary); });
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/auth_2fa.php');
require $temporary;
