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

namespace CactiBulkCliHardeningProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';

$GLOBALS['bulk_cli_logs'] = array();
$GLOBALS['bulk_cli_bg']   = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_path_is_within($path, $base) {
	return \cacti_path_is_within($path, $base);
}

function data_source_path_within_rra($path) {
	return \data_source_path_within_rra($path);
}

function cacti_escapeshellarg($string) {
	return escapeshellarg((string) $string);
}

function cacti_escapeshellarg_cmd($string) {
	return '"' . (string) $string . '"';
}

function cacti_escapeshellcmd($string) {
	return escapeshellcmd((string) $string);
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['bulk_cli_logs'][] = $facility . ': ' . $message;
}

function read_config_option($name, $force = false) {
	if ($name === 'path_php_binary') {
		return PHP_BINARY;
	}
	if ($name === 'path_rrdtool') {
		return '/usr/bin/rrdtool';
	}
	return '';
}

function exec_background($binary, $args) {
	$GLOBALS['bulk_cli_bg'][] = array($binary, $args);
}

function pushout_debug($msg) {
}

\cacti_fixture_source_function($root . '/cli/structure_rra_paths.php', 'sp_recursive_chown', __NAMESPACE__);
\cacti_fixture_source_function($root . '/cli/structure_rra_paths.php', 'sp_recursive_chgrp', __NAMESPACE__);
\cacti_fixture_source_function($root . '/cli/md5sum.php', 'dirToArray', __NAMESPACE__);
\cacti_fixture_source_function($root . '/cli/rebuild_poller_cache.php', 'pushout_launch_child', __NAMESPACE__);
\cacti_fixture_source_function($root . '/cli/float_rrdfiles.php', 'float_rrdfile', __NAMESPACE__);

$tmpBase = sys_get_temp_dir() . '/cacti-cli-probe-' . bin2hex(random_bytes(6));
mkdir($tmpBase . '/rra/sub', 0700, true);
mkdir($tmpBase . '/cacti', 0700, true);
mkdir($tmpBase . '/cacti_outside', 0700, true);

$target  = $tmpBase . '/rra/real.txt';
$subfile = $tmpBase . '/rra/sub/child.txt';
$sublink = $tmpBase . '/rra/sub/nested_link.txt';
$symlink = $tmpBase . '/rra/link.txt';
file_put_contents($target, 'data');
file_put_contents($subfile, 'child');
symlink($target, $sublink);
symlink($target, $symlink);

file_put_contents($tmpBase . '/cacti/ok.txt', 'hello');
file_put_contents($tmpBase . '/cacti_outside/secret.txt', 'secret');
symlink($tmpBase . '/cacti_outside/secret.txt', $tmpBase . '/cacti/escape.txt');

$GLOBALS['config'] = array(
	'base_path'       => $tmpBase,
	'rra_path'        => $tmpBase . '/rra',
	'cacti_server_os' => 'unix',
);

try {
	// 1. sp_recursive_chown / sp_recursive_chgrp on symlink and directory with subdir + symlink
	$gid = filegroup($tmpBase . '/rra');
	$chown_sym = sp_recursive_chown($symlink, getmyuid());
	$chgrp_sym = sp_recursive_chgrp($symlink, $gid);
	$chown_dir = sp_recursive_chown($tmpBase . '/rra', getmyuid());
	$chgrp_dir = sp_recursive_chgrp($tmpBase . '/rra', $gid);
	@sp_recursive_chown($tmpBase . '/rra', 0);
	@sp_recursive_chgrp($tmpBase . '/rra', 0);

	// 2. dirToArray traversal & symlink guard
	$GLOBALS['debug'] = false;
	$GLOBALS['quiet'] = true;
	$md5_inside  = dirToArray('', realpath($tmpBase . '/cacti'), '/^\/(\.|\.\.)$/');
	$md5_outside = dirToArray('../cacti_outside', realpath($tmpBase . '/cacti'), '/^\/(\.|\.\.)$/');

	// 3. pushout_launch_child argv array
	$GLOBALS['debug']            = true;
	$GLOBALS['host_template_id'] = 5;
	$GLOBALS['data_template_id'] = 7;
	pushout_launch_child(2, 4);

	// 4. float_rrdfile RRA containment + unix/win32 command building (missing file returns false before exec)
	$float_outside = float_rrdfile('/etc/passwd', 1, 300, 100, 200);
	$float_unix    = float_rrdfile($tmpBase . '/rra/missing.rrd', 1, 300, 100, 200);
	$GLOBALS['config']['cacti_server_os'] = 'win32';
	$float_win32   = float_rrdfile($tmpBase . '/rra/missing.rrd', 1, 300, 100, 200);
	$GLOBALS['config']['cacti_server_os'] = 'unix';

	print json_encode(array(
		'chown_sym'     => $chown_sym,
		'chgrp_sym'     => $chgrp_sym,
		'chown_dir'     => $chown_dir,
		'chgrp_dir'     => $chgrp_dir,
		'md5_inside'    => array_keys($md5_inside),
		'md5_outside'   => $md5_outside,
		'pushout_bg'    => $GLOBALS['bulk_cli_bg'],
		'float_outside' => $float_outside,
		'float_unix'    => $float_unix,
		'float_win32'   => $float_win32,
	), JSON_THROW_ON_ERROR);
} finally {
	@unlink($sublink);
	@unlink($symlink);
	@unlink($target);
	@unlink($subfile);
	@rmdir($tmpBase . '/rra/sub');
	@rmdir($tmpBase . '/rra');
	@unlink($tmpBase . '/cacti/escape.txt');
	@unlink($tmpBase . '/cacti/ok.txt');
	@unlink($tmpBase . '/cacti_outside/secret.txt');
	@rmdir($tmpBase . '/cacti');
	@rmdir($tmpBase . '/cacti_outside');
	@rmdir($tmpBase);
}
