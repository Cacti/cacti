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

require dirname(__DIR__, 2) . '/include/global_constants.php';
require dirname(__DIR__, 2) . '/lib/html.php';
require dirname(__DIR__, 2) . '/lib/installer.php';

$config = ['base_path' => dirname(__DIR__, 2)];
define('CACTI_VERSION', '1.2.32');

$cacheFile = tempnam(sys_get_temp_dir(), 'cir');
file_put_contents($cacheFile, '<[version]> 1.2.32 <[status]> ' . DB_STATUS_ERROR . " <[sql]> UPDATE settings SET value = '<b>sql</b>' <[error]> failed" . PHP_EOL);

function __($text, ...$args) { return $args ? vsprintf($text, $args) : $text; }
function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }
function read_config_option($name, $force = false) { return $name === 'install_cache_db' ? $GLOBALS['cacheFile'] : ''; }
function db_execute(...$args) { return true; }
function api_plugin_uninstall_integrated() {}
function log_install_medium(...$args) {}
function tail_file($file, $lines, $type, $filter, &$page_nr, &$total_rows) { return ['2026-01-01 00:00:00 - CMDPHP INSTALL: <b>log</b>']; }

$cacti_version_codes = ['1.2.32' => '0'];
$database_statuses   = [DB_STATUS_ERROR => 'Error'];

$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();

foreach (['stepCurrent' => Installer::STEP_ERROR, 'iconClass' => [], 'buttonPrevious' => new InstallerButton(), 'buttonNext' => new InstallerButton()] as $name => $value) {
	(new ReflectionProperty(Installer::class, $name))->setValue($installer, $value);
}

try {
	$log    = Installer::getInstallLog();
	$result = $installer->processStepComplete();
} finally {
	unlink($cacheFile);
}

foreach (['log' => $log, 'result' => $result] as $name => $html) {
	if (strpos($html, '<b>') !== false) {
		throw new RuntimeException('Installer ' . $name . ' output rendered stored markup.');
	}
}

if (strpos($log, '&lt;b&gt;log&lt;/b&gt;') === false || strpos($result, '&lt;b&gt;sql&lt;/b&gt;') === false) {
	throw new RuntimeException('Installer output lost the escaped text.');
}

print json_encode(['log_escaped' => true, 'sql_escaped' => true]);
