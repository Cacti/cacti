<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $plugin_hooks, $plugins_integrated, $plugins;
$plugin_hooks       = array();
$plugins_integrated = array('snmpagent', 'clog', 'settings', 'boost', 'dsstats', 'watermark', 'ssl', 'ugroup', 'domains', 'jqueryskin', 'secpass', 'logrotate', 'realtime', 'rrdclean', 'nectar', 'aggregate', 'autom8', 'discovery', 'spikekill', 'superlinks');

function use_plugin ($name) {
	global $config;
	if (file_exists($config['base_path'] . "/plugins/$name/setup.php")) {
		include_once($config['base_path'] . "/plugins/$name/setup.php");
		$function = "plugin_init_$name";
		if (function_exists($function)) {
			$function();
		}
	}
}

/**
 * This function executes a hook.
 * @param string $name Name of hook to fire
 * @return mixed $data
 */
if (!is_array($plugins)) {
	$plugins = array();
}

$oldplugins = read_config_option('oldplugins');
if (strlen(trim($oldplugins))) {
	$oldplugins = explode(',', $oldplugins);
	foreach($oldplugins as $plugin) {
		$plugins[$plugin]['directory'] = $plugin;
	}
}

/* On startup, register all plugins configured for use. */
if (isset($plugins) && is_array($plugins) && !defined('IN_CACTI_INSTALL')) {
	foreach ($plugins as $plugin) {
		use_plugin($plugin['directory']);
	}
}
