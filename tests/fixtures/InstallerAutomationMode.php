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

require dirname(__DIR__, 2) . '/lib/installer.php';
$settings = [];
$network_reads = 0;
function read_config_option($name, $force = false) { return $GLOBALS['settings'][$name] ?? null; }
function set_install_config_option($name, $value) { $GLOBALS['settings'][$name] = $value; }
function db_table_exists($table) { return true; }
function db_fetch_row($sql) { $GLOBALS['network_reads']++; return ['id' => 1, 'enabled' => 'on']; }
function log_install_debug(...$args) {}
function log_install_medium(...$args) {}
function log_install_high(...$args) {}
function clean_up_lines($value) { return $value; }
function __($text, ...$args) { return $args ? sprintf($text, ...$args) : $text; }

$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
$setter = new ReflectionMethod(Installer::class, 'setAutomationMode');
$property = new ReflectionProperty(Installer::class, 'automationMode');
foreach ([false, 0, '0', 'false', ''] as $disabled) {
	$property->setValue($installer, true);
	$setter->invoke($installer, $disabled);
	if ($property->getValue($installer) !== false || $settings['install_automation_mode'] !== 0 || $installer->getAutomationMode() !== 0) {
		throw new RuntimeException('Disabled automation was ignored or lost on reload.');
	}
}
if ($network_reads !== 0) { throw new RuntimeException('An explicit disabled setting reloaded enabled network defaults.'); }
$setter->invoke($installer, null);
if ($property->getValue($installer) !== false) { throw new RuntimeException('Null changed the stored automation mode.'); }
$setter->invoke($installer, true);
if ($property->getValue($installer) !== true || $installer->getAutomationMode() !== 1) { throw new RuntimeException('Enabling automation changed.'); }
print json_encode(['disabled_values' => 5, 'network_reads' => $network_reads]);
