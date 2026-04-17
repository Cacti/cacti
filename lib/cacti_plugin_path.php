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

/**
 * Safe plugin path resolution.
 *
 * Eliminates the LFI/include-path vulnerability class by enforcing
 * that every plugin file inclusion resolves to a path inside the
 * Cacti plugins directory after symlink resolution.
 * @param mixed $plugin_name
 * @param mixed $subpath
 */

/**
 * Resolve a safe path to a plugin file.
 *
 * @param string $plugin_name Plugin directory name (e.g., 'thold')
 * @param string $subpath     File within the plugin (default 'setup.php')
 *
 * @return string|false Resolved absolute path, or false if invalid
 */
function cacti_plugin_path($plugin_name, $subpath = 'setup.php') {
	global $config;

	// Strip any traversal from the plugin name
	$safe_name = basename($plugin_name);

	if (empty($safe_name) || $safe_name !== $plugin_name) {
		cacti_log("WARNING: cacti_plugin_path: invalid plugin name rejected: '$plugin_name'", false, 'SYSTEM');

		return false;
	}

	// Build the candidate path
	$plugins_dir = $config['base_path'] . '/plugins';
	$candidate   = $plugins_dir . '/' . $safe_name . '/' . $subpath;

	// Verify the file exists
	if (!file_exists($candidate)) {
		return false;
	}

	// Resolve symlinks and verify containment
	$real_path    = realpath($candidate);
	$real_plugins = realpath($plugins_dir);

	if ($real_path === false || $real_plugins === false) {
		return false;
	}

	$sep = DIRECTORY_SEPARATOR;

	if (DIRECTORY_SEPARATOR === '\\') {
		$real_path    = strtolower(str_replace('\\', '/', $real_path));
		$real_plugins = strtolower(str_replace('\\', '/', $real_plugins));
		$sep          = '/';
	}

	if (strpos($real_path, $real_plugins . $sep) !== 0) {
		cacti_log("WARNING: cacti_plugin_path: path escapes plugins dir: '$candidate' -> '$real_path'", false, 'SYSTEM');

		return false;
	}

	return $real_path;
}
