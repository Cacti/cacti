<?php

/*
 * Copyright (c) 1999-2005 The SquirrelMail Project Team (http://squirrelmail.org)
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 */

global $plugin_hooks, $plugins_system, $plugins;
$plugin_hooks = array();
$plugins_system = array('snmpagent', 'settings', 'boost', 'dsstats');

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
	$plugins    = array_merge($plugins, $oldplugins);
}

/* On startup, register all plugins configured for use. */
if (isset($plugins) && is_array($plugins)) {
	foreach ($plugins as $name) {
		use_plugin($name);
	}
}
