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

namespace InstallerFailureCoverage;

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$source = dirname(__DIR__, 3);
define('CACTI_PATH_LIBRARY', $source . '/lib');
require_once $source . '/include/global_constants.php';

foreach (['install/functions.php' => ['install_write_poller_config', 'install_rewrite_poller_config', 'install_write_whole_stream', 'install_validate_vendor_dependencies', 'prime_default_settings', 'remote_update_config_file'], 'lib/xml.php' => ['xml2array', 'get_children'], 'lib/import.php' => ['import_xml_data', 'parse_xml_hash']] as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($source . '/' . $file, $function, __NAMESPACE__);
	}
}

// Faults are injected only at filesystem/database boundaries; production control flow is unchanged.
function file_get_contents($path) {
	return $GLOBALS['fault'] === 'read' ? false : \file_get_contents($path);
}
function stat($path) {
	return $GLOBALS['fault'] === 'stat' ? false : \stat($path);
}
function tempnam($dir, $prefix) {
	if ($GLOBALS['fault'] === 'temp') {
		return false;
	}

	return \tempnam($GLOBALS['fault'] === 'fallback' ? sys_get_temp_dir() : $dir, $prefix);
}
function is_writable($path) {
	return !($GLOBALS['fault'] === 'directory' && is_dir($path)) && \is_writable($path);
}
function flock($stream, $operation, &$would_block = null) {
	return $GLOBALS['fault'] !== 'lock' && \flock($stream, $operation, $would_block);
}
function rewind($stream) {
	return $GLOBALS['fault'] !== 'rewind' && \rewind($stream);
}
function fopen($path, $mode) {
	return $GLOBALS['fault'] === 'open' ? false : \fopen($path, $mode);
}
function fwrite($stream, $data) {
	if ($GLOBALS['fault'] === 'write') {
		return false;
	}

	if ($GLOBALS['fault'] === 'zero') {
		return 0;
	}

	return \fwrite($stream, $data, $GLOBALS['fault'] === 'short' ? min(3, strlen($data)) : strlen($data));
}
function fflush($stream) {
	return $GLOBALS['fault'] !== 'flush' && \fflush($stream);
}
function fsync($stream) {
	return $GLOBALS['fault'] !== 'sync' && \fsync($stream);
}
function fileowner($path) {
	return $GLOBALS['fault'] === 'owner' ? -1 : \fileowner($path);
}
function filegroup($path) {
	return $GLOBALS['fault'] === 'group' ? -1 : \filegroup($path);
}
function chown($path, $owner) {
	return false;
}
function chgrp($path, $group) {
	return false;
}
function chmod($path, $mode) {
	return $GLOBALS['fault'] !== 'mode' && \chmod($path, $mode);
}
function rename($from, $to) {
	return $GLOBALS['fault'] !== 'rename' && \rename($from, $to);
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}
function cacti_count($value) {
	return is_array($value) ? count($value) : 0;
}
function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	if (++$GLOBALS['writes'] === $GLOBALS['fail_write']) {
		return false;
	}

	for ($i = 0; $i < count($params); $i += 2) {
		if (!array_key_exists($params[$i], $GLOBALS['saved'])) {
			$GLOBALS['saved'][$params[$i]] = $params[$i + 1];
		}
	}

	return true;
}
function db_connect_real(string $host, string $user, string $password, string $database, string $type = 'mysql', int $port = 3306, int $retries = 20,
	bool $ssl = false, string $key = '', string $cert = '', string $ca = '', string $capath = '', bool $verify = true, bool $persist = false) : mixed {
	return new \stdClass();
}
function db_fetch_cell_prepared($sql, $params = [], $column = '', $log = true, $connection = false) {
	return 7;
}
function db_close(mixed &$connection = false) : bool {
	$GLOBALS['closed'] = true;
	$connection        = false;

	return true;
}
function check($condition, $message) {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
	$GLOBALS['assertions']++;
}

$GLOBALS['assertions'] = 0;
$GLOBALS['fault']      = '';
$root                  = sys_get_temp_dir() . '/cacti-installer-failures-' . bin2hex(random_bytes(8));
mkdir($root . '/include/vendor/composer', 0700, true);
mkdir($root . '/include/vendor/example/runtime', 0700, true);
define('CACTI_PATH_INCLUDE', $root . '/include');
$file     = $root . '/include/config.php';
$original = "<?php\n\$poller_id = 1;\n\$other = 'preserved';\n";

try {
	check(!install_write_poller_config($file, 7), 'Missing config was accepted');

	foreach (['', 'short', 'read', 'stat', 'directory', 'rewind', 'temp', 'fallback', 'open', 'write', 'zero', 'flush', 'sync', 'owner', 'group', 'mode', 'rename'] as $fault) {
		\file_put_contents($file, $original);
		\chmod($file, 0640);
		$GLOBALS['fault'] = $fault;
		$inode            = fileinode($file);
		$success          = install_write_poller_config($file, 7);
		clearstatcache();
		$in_place = in_array($fault, ['directory', 'temp', 'fallback', 'owner', 'group'], true);
		$expected = $in_place || in_array($fault, ['', 'short'], true);

		if ($in_place) {
			check(fileinode($file) === $inode, 'Fallback replaced the original file: ' . $fault);
		}
		check($success === $expected, 'Incorrect write result: ' . $fault);
		check(\file_get_contents($file) === ($expected ? str_replace('$poller_id = 1;', '$poller_id = 7;', $original) : $original), 'Config changed incorrectly: ' . $fault);
		check((fileperms($file) & 0777) === 0640, 'Permissions changed: ' . $fault);
		check(glob(dirname($file) . '/.cacti-config-*') === [], 'Temporary file leaked: ' . $fault);
	}

	// A failed in-place update must leave the previous configuration readable.
	foreach (['open', 'lock', 'flush'] as $fault) {
		\file_put_contents($file, $original);
		$GLOBALS['fault'] = $fault;
		check(!install_rewrite_poller_config($file, 'updated', $original), 'In-place update succeeded: ' . $fault);
		check(\file_get_contents($file) === $original, 'In-place failure lost the original: ' . $fault);
	}
	$GLOBALS['fault'] = '';
	\file_put_contents($file, "<?php\n\$poller_id = 1;\n\$poller_id = 2;\n");
	check(!install_write_poller_config($file, 7), 'Ambiguous config was accepted');
	\file_put_contents($file, $original);
	symlink($file, $root . '/link.php');
	check(install_write_poller_config($root . '/link.php', 7) && is_link($root . '/link.php'), 'Config symlink was replaced');
	$GLOBALS['fault']  = 'write';
	$GLOBALS['closed'] = false;

	foreach (['hostname' => 'fixture.invalid', 'username' => 'fixture', 'password' => '', 'default' => 'fixture', 'type' => 'mysql', 'port' => 3306, 'retries' => 1, 'ssl' => false, 'ssl_key' => '', 'ssl_cert' => '', 'ssl_ca' => '', 'ssl_capath' => '', 'ssl_verify_server_cert' => true] as $key => $value) {
		$GLOBALS['rdatabase_' . $key] = $value;
	}
	check(remote_update_config_file() === 'Failed to update configuration file' && $GLOBALS['closed'], 'Remote write failure did not close the connection and report failure');
	$GLOBALS['fault'] = '';

	$GLOBALS['settings'] = null;
	check(!prime_default_settings(), 'Invalid settings were accepted');
	$GLOBALS['settings'] = ['general' => ['nested' => ['items' => ['nested_default' => ['default' => 'value']]]]];

	for ($i = 0; $i < 251; $i++) {
		$GLOBALS['settings']['general']['setting_' . $i] = ['default' => (string) $i];
	}
	$GLOBALS['saved']      = ['setting_0' => 'custom'];
	$GLOBALS['writes']     = 0;
	$GLOBALS['fail_write'] = 2;
	$_SESSION              = [];
	check(!prime_default_settings() && !isset($_SESSION['settings_primed']), 'Failed defaults write was marked complete');
	$GLOBALS['fail_write'] = 0;
	check(prime_default_settings() && $_SESSION['settings_primed'] && count($GLOBALS['saved']) === 252 && $GLOBALS['saved']['setting_0'] === 'custom', 'Retry lost existing defaults');
	check(prime_default_settings(), 'Completed defaults were not reusable');

	$package  = ['name' => 'example/runtime', 'version' => '1.0.0'];
	$metadata = $root . '/include/vendor/composer/installed.json';
	\file_put_contents($root . '/include/vendor/autoload.php', '<?php');
	\file_put_contents($root . '/composer.lock', json_encode(['packages' => [$package]]));

	foreach ([null, '{broken', json_encode(['packages' => []]), json_encode(['packages' => [$package]]), json_encode(['packages' => [$package + ['install-path' => '../example/runtime']]])] as $data) {
		if ($data !== null) {
			\file_put_contents($metadata, $data);
		}
		check(install_validate_vendor_dependencies($root) === ($data !== null && str_contains($data, 'install-path')), 'Incorrect dependency readiness');
	}
	$GLOBALS['fault'] = 'read';
	check(!install_validate_vendor_dependencies($root), 'Unreadable dependency metadata was accepted');
	$GLOBALS['fault'] = '';
	\file_put_contents($root . '/composer.lock', json_encode(['packages' => [['name' => 'broken']]]));
	check(!install_validate_vendor_dependencies($root), 'Incomplete lock metadata was accepted');

	$GLOBALS['config']['library_path'] = $source . '/lib';
	$GLOBALS['import_messages']        = [];

	foreach (['not xml', '<cacti/>', '<cacti><invalid><name>bad hash</name></invalid></cacti>'] as $xml) {
		check(import_xml_data($xml, false, 1) === false, 'Malformed template was accepted');
	}
	check(xml2array('<cacti><template><name>Example</name></template></cacti>') === ['template' => ['name' => 'Example']], 'Valid XML changed');
	print json_encode(['passed' => $GLOBALS['assertions']], JSON_THROW_ON_ERROR) . "\n";
} finally {
	foreach (['link.php', 'include/config.php', 'include/vendor/autoload.php', 'include/vendor/composer/installed.json', 'composer.lock'] as $path) {
		if (file_exists($root . '/' . $path)) {
			unlink($root . '/' . $path);
		}
	}

	foreach (['include/vendor/example/runtime', 'include/vendor/example', 'include/vendor/composer', 'include/vendor', 'include', ''] as $path) {
		rmdir($root . '/' . $path);
	}
}
