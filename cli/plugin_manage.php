#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');

$parms     = $_SERVER['argv'];
$install   = false;
$enable    = false;
$disable   = false;
$uninstall = false;
$allperms  = false;
$plugins   = array();

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = array(
		'plugin::',
		'install',
		'enable',
		'disable',
		'uninstall',
		'allperms',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach ($options as $arg => $value) {
		switch($arg) {
			case 'plugin':
				if (is_array($value)) {
					$plugins = $value;
				} else {
					$plugins[] = $value;
				}

				break;
			case 'install':
				$install = true;

				break;
			case 'uninstall':
				$uninstall = true;

				break;
			case 'disable':
				$disable = true;

				break;
			case 'enable':
				$enable = true;

				break;
			case 'allperms':
				$allperms = true;

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();
				exit(0);
			case 'help':
			case 'H':
			case 'h':
				display_help();
				exit(0);
			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}

	// Sanity checks
	if ($enable && $disable) {
		print 'FATAL: Options --enable and --disable are mutually exclusive' . PHP_EOL;

		exit(1);
	}

	if ($install && $uninstall) {
		print 'FATAL: Options --install and --uninstall are mutually exclusive' . PHP_EOL;

		exit(1);
	}

	if ($install && $disable) {
		print 'FATAL: Options --install and --disable are mutually exclusive' . PHP_EOL;

		exit(1);
	}

	if ($uninstall && $enable) {
		print 'FATAL: Options --uninstall and --enable are mutually exclusive' . PHP_EOL;

		exit(1);
	}
} else {
	display_help();

	exit(1);
}

print 'NOTE: ' . cacti_sizeof($plugins) . ' Plugins to be acted on.' . PHP_EOL;

if (cacti_sizeof($plugins)) {
	foreach ($plugins as $plugin) {
		print "NOTE: Plugin '$plugin' processing started" . PHP_EOL;

		if ($install) {
			$installed = false;

			if (is_dir(CACTI_PATH_PLUGINS . '/' . $plugin)) {
				print "NOTE: Plugin directory for Plugin $plugin exists" . PHP_EOL;

				if (!api_plugin_installed($plugin)) {
					$message = '';

					if (api_plugin_can_install($plugin, $message)) {
						api_plugin_install($plugin);

						if (api_plugin_installed($plugin)) {
							print "NOTE: Plugin $plugin installed successfully." . PHP_EOL;

							$installed = true;

							if ($enable) {
								api_plugin_enable($plugin);

								print "NOTE: Plugin $plugin enabled." . PHP_EOL;
							}
						}
					} else {
						print "WARNING: Plugin '$plugin' can not install.  Message is: $message" . PHP_EOL;
					}
				} else {
					$installed = true;

					print "WARNING: Plugin '$plugin' already installed." . PHP_EOL;
				}
			} else {
				print "WARNING: Plugin '$plugin' missing plugin directory.  Plugin not installed" . PHP_EOL;
			}

			if ($installed && $allperms) {
				plugin_manage_install_allrealms($plugin);
			}
		} elseif ($uninstall || $disable) {
			if ($disable) {
				print "NOTE: Disabling Plugin $plugin." . PHP_EOL;
				api_plugin_disable($plugin);
			}

			if ($uninstall) {
				print "NOTE: Uninstalling Plugin $plugin." . PHP_EOL;
				api_plugin_uninstall($plugin);
			}
		}
	}
}

function plugin_manage_install_allrealms($plugin) {
	print "NOTE: Enabling Plugin '$plugin' permissions for administrative accounts" . PHP_EOL;

	$realms = db_fetch_assoc_prepared('SELECT *
		FROM plugin_realms
		WHERE plugin = ?',
		array($plugin));

	foreach($realms as $realm) {
		api_plugin_register_realm($plugin, $realm['file'], $realm['display'], 1);
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Install Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 *display_help - displays the usage of the function
 */
function display_help() {
	print 'usage: plugin_manage.php [--plugin=S] [--install --enable --allperms] [--uninstall ] [--disable]' . PHP_EOL . PHP_EOL;

	print 'A utility to install/uninstall a Cacti plugin or plugins' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '  --plugin=S   - The plugin to install.  Use the option multiple time for more plugins' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '  --install    - Install the plugin or plugins' . PHP_EOL;
	print '  --enable     - Enable the plugin or plugins' . PHP_EOL;
	print '  --allperms   - Enable all permission for plugin or plugins' . PHP_EOL;
	print '  --uninstall  - Uninstall the plugin or plugins' . PHP_EOL;
	print '  --disable    - Disable the plugin or plugins' . PHP_EOL;
}
