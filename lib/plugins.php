<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function do_hook($name) {
	$data = func_get_args();
	$data = api_plugin_hook($name, $data);

	return $data;
}

function do_hook_function($name, $parm = null) {
	return api_plugin_hook_function($name, $parm);
}

function api_user_realm_auth($filename = '') {
	return api_plugin_user_realm_auth($filename);
}

/**
 * This function executes a hook.
 * @param string $name Name of hook to fire
 * @return mixed $data
 */
function api_plugin_hook($name) {
	global $config, $plugin_hooks, $plugins_integrated;

	static $hook_cache = array();

	$args = func_get_args();
	$ret  = '';

	if (defined('IN_CACTI_INSTALL') || !db_table_exists('plugin_hooks')) {
		return $args;
	}

	if (!isset($hook_cache[$name])) {
		/* order the plugins by order */
		$result = db_fetch_assoc_prepared('SELECT ph.name, ph.file, ph.function
			FROM plugin_hooks AS ph
			LEFT JOIN plugin_config AS pc
			ON pc.directory = ph.name
			WHERE ph.status = 1
			AND hook = ?
			ORDER BY pc.id ASC',
			array($name),
			true
		);

		$hook_cache[$name] = $result;
	} else {
		$result = $hook_cache[$name];
	}

	if (!empty($result)) {
		foreach ($result as $hdata) {
			$plugin_name = $hdata['name'];
			$plugin_file = $hdata['file'];

			// Security check
			if (strpos($plugin_file, '..') !== false) {
				cacti_log("ERROR: Attempted inclusion of not plugin file $plugin_file from $plugin_name with the hook name $name", false, 'SECURITY');
				continue;
			}

			if (!in_array($plugin_name, $plugins_integrated, true)) {
				$plugin_func = $hdata['function'];
				$plugin_file = $hdata['file'];
				$full_path   = CACTI_PATH_PLUGINS . '/' . $plugin_name . '/' . $plugin_file;
				$debounce    = 'mpf_' . $plugin_name . '_' . $plugin_func;

				if (file_exists($full_path)) {
					include_once($full_path);
				}

				if (function_exists($plugin_func)) {
					api_plugin_run_plugin_hook($name, $plugin_name, $plugin_func, $args);
				} else if (debounce_run_notification($debounce)) {
					cacti_log(sprintf('WARNING: Function "%s" does not exist in %s/%s for hook "%s"' . PHP_EOL, $plugin_func, $plugin_name, $plugin_file, $name), false, 'PLUGIN', POLLER_VERBOSITY_MEDIUM);
				}
			}
		}
	}

	/* Variable-length argument lists have a slight problem when */
	/* passing values by reference. Pity. This is a workaround.  */
	return $args;
}

function api_plugin_hook_function($name, $parm = null) {
	global $config, $plugin_hooks, $plugins_integrated;

	static $hook_cache = array();

	$ret = $parm;

	if (defined('IN_CACTI_INSTALL') || !db_table_exists('plugin_hooks')) {
		return $ret;
	}

	if (!isset($hook_cache[$name])) {
		/* order the plugins by order */
		$result = db_fetch_assoc_prepared('SELECT ph.name, ph.file, ph.function
			FROM plugin_hooks AS ph
			LEFT JOIN plugin_config AS pc
			ON pc.directory = ph.name
			WHERE ph.status = 1
			AND hook = ?
			ORDER BY pc.id ASC',
			array($name),
			true
		);

		$hook_cache[$name] = $result;
	} else {
		$result = $hook_cache[$name];
	}

	if (empty($ret)) {
		$null_ret = true;
	} else {
		$null_ret = false;
	}

	if (cacti_sizeof($result)) {
		foreach ($result as $hdata) {
			if (!in_array($hdata['name'], $plugins_integrated, true)) {
				$message = '';

				if (api_plugin_can_install($hdata['name'], $message)) {
					$p[] = $hdata['name'];

					if (file_exists(CACTI_PATH_PLUGINS . '/' . $hdata['name'] . '/' . $hdata['file'])) {
						include_once(CACTI_PATH_PLUGINS . '/' . $hdata['name'] . '/' . $hdata['file']);
					}

					$function = $hdata['function'];

					if (function_exists($function)) {
						if (is_array($ret)) {
							$is_array = true;
						} else {
							$is_array = false;
						}

						$ret = api_plugin_run_plugin_hook_function($name, $hdata['name'], $function, $ret);

						if (($is_array && !is_array($ret)) || ($ret == null && $null_ret === false)) {
							if (cacti_sizeof($result) > 1) {
								cacti_log(sprintf("WARNING: Plugin hook '%s' from Plugin '%s' must return the calling array or variable, and it is not doing so.  Please report this to the Plugin author.", $function, $hdata['name']), false, 'PLUGIN');
							}
						}
					}
				}
			}
		}
	}

	/* Variable-length argument lists have a slight problem when */
	/* passing values by reference. Pity. This is a workaround.  */
	return $ret;
}

function api_plugin_run_plugin_hook($hook, $plugin, $function, $args) {
	global $config, $menu;

	if ($config['poller_id'] > 1) {
		// Let's control the menu
		$orig_menu = $menu;

		$required_capabilities = array(
			// Poller related
			'poller_top'               => array('remote_collect'),              // Poller Top, api_plugin_hook
			'poller_bottom'            => array('remote_poller'),               // Poller execution, api_plugin_hook
			'update_host_status'       => array('remote_collect'),              // Processing poller output, api_plugin_hook
			'poller_output'            => array('remote_collect'),              // Poller output activities
			'poller_finishing'         => array('remote_collect'),              // Poller post processing, api_plugin_hook
			'poller_exiting'           => array('remote_collect'),              // Poller exception handling, api_plugin_hook

			// GUI Related
			'page_head'                => array('online_view', 'offline_view'), // Navigation, api_plugin_hook
			'top_header_tabs'          => array('online_view', 'offline_view'), // Top Tabs, api_plugin_hook
			'top_graph_header_tabs'    => array('online_view', 'offline_view'), // Top Tabs, api_plugin_hook
			'graph_buttons'            => array('online_view', 'offline_view'), // Buttons by graphs, api_plugin_hook
			'graphs_new_top_links'     => array('online_mgmt', 'offline_mgmt'), // Buttons by graphs, api_plugin_hook
			'page_head'                => array('online_view', 'offline_view')  // Content, api_plugin_hook
		);

		$plugin_capabilities = api_plugin_remote_capabilities($plugin);

		if ($plugin_capabilities === false) {
			$function($args);
		} elseif (api_plugin_hook_is_remote_collect($hook, $plugin, $required_capabilities)) {
			if (api_plugin_status_run($hook, $required_capabilities, $plugin_capabilities, $plugin)) {
				$function($args);
			}
		} elseif (isset($required_capabilities[$hook])) {
			if (api_plugin_status_run($hook, $required_capabilities, $plugin_capabilities, $plugin)) {
				$function($args);
			}
		} elseif ($config['connection'] == 'online' ||
			((api_plugin_has_capability($plugin, 'offline_mgmt') || api_plugin_has_capability($plugin, 'offline_view'))
			&& $config['connection'] != 'online')) {
			$function($args);
		} else {
			// Don't run as they are not required
		}

		// See if we need to restore the menu to original
		$remote_hooks = array(
			'config_arrays',
			'config_insert',
		);

		if (in_array($hook, $remote_hooks, true) && ($config['connection'] == 'offline' || $config['connection'] == 'recovery')) {
			if (!api_plugin_has_capability($plugin, 'offline_mgmt')) {
				if ($orig_menu !== $menu) {
					$menu = $orig_menu;
				}
			}
		}
	} else {
		$function($args);
	}

	return $args;
}

function api_plugin_run_plugin_hook_function($hook, $plugin, $function, $ret) {
	global $config;

	if ($config['poller_id'] > 1) {
		$required_capabilities = array(
			// Poller related
			'poller_output'            => array('remote_collect'),              // Processing poller output, api_plugin_hook_function
			'cacti_stats_update'       => array('remote_collect'),              // Updating Cacti stats

			// GUI Related
			'top_header'               => array('online_view', 'offline_view'), // Top Tabs, api_plugin_hook_function
			'top_graph_header'         => array('online_view', 'offline_view'), // Top Tabs, api_plugin_hook_function
			'rrd_graph_graph_options'  => array('online_view', 'offline_view'), // Buttons by graphs, api_plugin_hook_function
			'data_sources_table'       => array('online_mgmt', 'offline_mgmt'), // Buttons by graphs, api_plugin_hook_function

			'device_action_array'      => array('online_mgmt', 'offline_mgmt'), // Actions Dropdown, api_plugin_hook_function
			'data_source_action_array' => array('online_mgmt', 'offline_mgmt'), // Actions Dropdown, api_plugin_hook_function
			'graphs_action_array'      => array('online_mgmt', 'offline_mgmt'), // Actions Dropdown, api_plugin_hook_function
		);

		$plugin_capabilities = api_plugin_remote_capabilities($plugin);

		if ($plugin_capabilities === false) {
			// we will run if capabilities are not set
			$ret = $function($ret);
		} elseif (api_plugin_hook_is_remote_collect($hook, $plugin, $required_capabilities)) {
			// run if hook is remote_collect and we support it
			if (api_plugin_status_run($hook, $required_capabilities, $plugin_capabilities, $plugin)) {
				$ret = $function($ret);
			}
		} elseif (isset($required_capabilities[$hook])) {
			// run if hook is remote_collect and we support it
			if (api_plugin_status_run($hook, $required_capabilities, $plugin_capabilities, $plugin)) {
				$ret = $function($ret);
			}
		} else {
			$ret = $function($ret);
		}
	} else {
		$ret = $function($ret);
	}

	return $ret;
}

function api_plugin_hook_is_remote_collect($hook, $plugin, $required_capabilities) {
	if (isset($required_capabilities[$hook])) {
		foreach ($required_capabilities[$hook] as $capability) {
			if (strpos($capability, 'remote_collect') !== false) {
				return true;
			}
		}
	}

	return false;
}

function api_plugin_get_dependencies($plugin) {
	global $config;

	$file = CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO';

	$returndeps = array();

	if (file_exists($file)) {
		$info = parse_ini_file($file, true);

		if (isset($info['info']['requires']) && trim($info['info']['requires']) != '') {
			$parts = array_map('trim', explode(' ', $info['info']['requires']));

			foreach ($parts as $p) {
				$vparts = explode(':', $p);

				if (isset($vparts[1])) {
					$returndeps[$vparts[0]] = $vparts[1];
				} else {
					$returndeps[$p] = true;
				}
			}

			return $returndeps;
		}
	}

	return false;
}

function api_plugin_minimum_version($plugin, $version) {
	if (strlen($version)) {
		$plugin_version = db_fetch_cell_prepared('SELECT version
			FROM plugin_config
			WHERE directory = ?',
			array($plugin));

		$result = cacti_version_compare($version, $plugin_version, '<=');
	} else {
		$plugin_version = '<not read>';
		$result         = true;
	}

	return $result;
}

function api_plugin_installed($plugin) {
	$plugin_data = db_fetch_row_prepared('SELECT directory, status
		FROM plugin_config
		WHERE directory = ?',
		array($plugin));

	if (cacti_sizeof($plugin_data)) {
		if ($plugin_data['status'] >= 1) {
			return true;
		}
	}

	return false;
}

function api_plugin_remote_capabilities($plugin) {
	global $config, $info_data;

	if ($plugin == 'internal') {
		return 'online_view:1 online_mgmt:1 offline_view:1 offline_mgmt:1 remote_collect:1';
	}

	$file = CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO';

	if (!isset($info_data[$plugin])) {
		if (file_exists($file)) {
			$info = parse_ini_file($file, true);

			if (cacti_sizeof($info)) {
				$info_data[$plugin] = $info['info'];
			}
		}
	}

	if (isset($info_data[$plugin]) && isset($info_data[$plugin]['capabilities'])) {
		return $info_data[$plugin]['capabilities'];
	} else {
		return 'online_view:0 online_mgmt:0 offline_view:0 offline_mgmt:0 remote_collect:0';
	}

	return false;
}

function api_plugin_has_capability($plugin, $capability) {
	$capabilities = api_plugin_remote_capabilities($plugin);

	if (strpos($capabilities, "$capability:1") !== false) {
		return true;
	} else {
		return false;
	}
}

function api_plugin_status_run($hook, $required_capabilities, $plugin_capabilities, $plugin = '') {
	global $config;

	$status = $config['connection'];

	if ($plugin == '') {
		cacti_log('WARNING: The function \'api_plugin_status_run\' API has changed.  Please add the $plugin attribute to the last position', false, 'PLUGIN');
		$plugin = 'Unknown';
	}

	// Don't run if not a supported hook
	if (!isset($required_capabilities[$hook])) {
		cacti_log(sprintf('WARNING: Not running hook %s for plugin %s as its not a supported Remote Hook', $hook, $plugin), false, 'PLUGIN');

		return false;
	}

	foreach ($required_capabilities[$hook] as $capability) {
		if ($capability == 'remote_collect') {
			if (strpos($plugin_capabilities, "$capability:1") !== false) {
				return true;
			}
		} elseif ($capability == 'remote_poller') {
			if (strpos($plugin_capabilities, "$capability:1") !== false) {
				return true;
			}
		} elseif ($status == 'online' && strpos($capability, 'online') === false) {
			continue;
		} elseif (($status == 'offline' || $status == 'recovery') && strpos($capability, 'offline') === false) {
			continue;
		}

		if (strpos($plugin_capabilities, "$capability:1") !== false) {
			return true;
		}

		switch ($capability) {
			case 'offline_view': // if the plugin has mgmt, it's assumed to have view
				if (strpos($plugin_capabilities, 'offline_mgmt:1') !== false) {
					return true;
				}

				break;
			case 'online_view': // if the plugin has mgmt, it's assumed to have view
				if (strpos($plugin_capabilities, 'offline_mgmt:1') !== false) {
					return true;
				}

				break;

			default:
				break;
		}
	}

	return false;
}

function api_plugin_db_table_create($plugin, $table, $data) {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/database.php');

	$result = db_fetch_assoc('SHOW TABLES');
	$tables = array();

	foreach ($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}

	if (!in_array($table, $tables, true)) {
		$c   = 0;
		$sql = 'CREATE TABLE `' . $table . "` (\n";

		foreach ($data['columns'] as $column) {
			if (isset($column['name'])) {
				if ($c > 0) {
					$sql .= ",\n";
				}

				$sql .= '`' . $column['name'] . '`';

				if (isset($column['type'])) {
					$sql .= ' ' . $column['type'];
				}

				if (isset($column['unsigned'])) {
					$sql .= ' unsigned';
				}

				if (isset($column['NULL']) && $column['NULL'] == false) {
					$sql .= ' NOT NULL';
				}

				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default'])) {
					$sql .= ' default NULL';
				}

				if (isset($column['default'])) {
					if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
						$sql .= ' default CURRENT_TIMESTAMP';
					} else {
						$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
					}
				}

				if (isset($column['auto_increment'])) {
					$sql .= ' auto_increment';
				}

				$c++;
			}
		}

		if (isset($data['primary'])) {
			$sql .= ",\n PRIMARY KEY (`" . $data['primary'] . '`)';
		}

		if (isset($data['keys']) && cacti_sizeof($data['keys'])) {
			foreach ($data['keys'] as $key) {
				if (isset($key['name'])) {
					$sql .= ",\n INDEX `" . $key['name'] . '` (' . db_format_index_create($key['columns']) . ')';
				}
			}
		}

		if (isset($data['unique_keys'])) {
			foreach ($data['unique_keys'] as $key) {
				if (isset($key['name'])) {
					$sql .= ",\n UNIQUE INDEX `" . $key['name'] . '` (' . db_format_index_create($key['columns']) . ')';
				}
			}
		}

		$sql .= ') ENGINE = ' . $data['type'];

		if (isset($data['charset'])) {
			$sql .= ' DEFAULT CHARSET = ' . $data['charset'];
		}

		if (isset($data['row_format']) && strtolower(db_get_global_variable('innodb_file_format')) == 'barracuda') {
			$sql .= ' ROW_FORMAT = ' . $data['row_format'];
		}

		if (isset($data['comment'])) {
			$sql .= " COMMENT = '" . $data['comment'] . "'";
		}

		if (db_execute($sql)) {
			db_execute_prepared("REPLACE INTO plugin_db_changes
				(plugin, `table`, `column`, `method`)
				VALUES (?, ?, '', 'create')",
				array($plugin, $table));

			if (isset($data['collate'])) {
				db_execute("ALTER TABLE `$table` COLLATE = " . $data['collate']);
			}
		}
	}
}

function api_plugin_drop_table($table) {
	db_execute("DROP TABLE IF EXISTS $table");

	api_plugin_drop_remote_table($table);
}

function api_plugin_db_changes_remove($plugin) {
	$tables = db_fetch_assoc_prepared("SELECT `table`
		FROM plugin_db_changes
		WHERE plugin = ?
		AND method ='create'",
		array($plugin), false);

	if (cacti_count($tables)) {
		foreach ($tables as $table) {
			db_execute('DROP TABLE IF EXISTS `' . $table['table'] . '`;');
		}

		api_plugin_drop_remote_table($table['table']);

		db_execute_prepared("DELETE FROM plugin_db_changes
			WHERE plugin = ?
			AND method ='create'",
			array($plugin), false);
	}

	$columns = db_fetch_assoc_prepared("SELECT `table`, `column`
		FROM plugin_db_changes
		WHERE plugin = ?
		AND method ='addcolumn'",
		array($plugin), false);

	if (cacti_count($columns)) {
		foreach ($columns as $column) {
			db_execute('ALTER TABLE `' . $column['table'] . '` DROP `' . $column['column'] . '`');
		}

		db_execute_prepared("DELETE FROM plugin_db_changes
			WHERE plugin = ?
			AND method = 'addcolumn'",
			array($plugin), false);
	}
}

function api_plugin_db_add_column($plugin, $table, $column) {
	global $config, $database_default;

	// Example: api_plugin_db_add_column ('thold', 'plugin_config',
	//	array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));

	include_once(CACTI_PATH_LIBRARY . '/database.php');

	$result  = db_fetch_assoc('SHOW COLUMNS FROM `' . $table . '`');
	$columns = array();

	foreach ($result as $index => $arr) {
		foreach ($arr as $t) {
			$columns[] = $t;
		}
	}

	if (isset($column['name']) && !in_array($column['name'], $columns, true)) {
		$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';

		if (isset($column['type'])) {
			$sql .= ' ' . $column['type'];
		}

		if (isset($column['unsigned'])) {
			$sql .= ' unsigned';
		}

		if (isset($column['NULL']) && $column['NULL'] == false) {
			$sql .= ' NOT NULL';
		}

		if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default'])) {
			$sql .= ' default NULL';
		}

		if (isset($column['default'])) {
			if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
				$sql .= ' default CURRENT_TIMESTAMP';
			} else {
				$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
			}
		}

		if (isset($column['auto_increment'])) {
			$sql .= ' auto_increment';
		}

		if (isset($column['after'])) {
			$sql .= ' AFTER ' . $column['after'];
		}

		if (db_execute($sql)) {
			db_execute_prepared("INSERT INTO plugin_db_changes
				(plugin, `table`, `column`, `method`)
				VALUES (?, ?, ?, 'addcolumn')",
				array($plugin, $table, $column['name']));
		}
	}
}

function api_plugin_can_install($plugin, &$message) {
	$dependencies = api_plugin_get_dependencies($plugin);
	$message      = '';
	$proceed      = true;

	if (is_array($dependencies) && cacti_sizeof($dependencies)) {
		foreach ($dependencies as $dependency => $version) {
			if (!plugin_valid_version_range($dependency, $version)) {
				$message .= __('\'%s\' versions \'%s\' above or in range are required to install \'%s\'. ', ucwords($dependency), $version, ucwords($plugin));

				$proceed = false;
			} elseif (!api_plugin_installed($dependency)) {
				$message .= __('\'%s\' must first be installed before \'%s\' is installed. ', ucwords($dependency), ucwords($plugin));

				$proceed = false;
			}
		}
	}

	return $proceed;
}

function api_plugin_install($plugin) {
	global $config;

	if (!defined('IN_CACTI_INSTALL')) {
		define('IN_CACTI_INSTALL', 1);
		define('IN_PLUGIN_INSTALL', 1);
	}

	$dependencies = api_plugin_get_dependencies($plugin);

	$proceed = api_plugin_can_install($plugin, $message);

	if (!$proceed) {
		$message .= '<br><br>' . __('Plugin cannot be installed.');

		raise_message('dependency_check', $message, MESSAGE_LEVEL_ERROR);

		header('Location: plugins.php');

		exit;
	}

	include_once(CACTI_PATH_PLUGINS . "/$plugin/setup.php");

	$exists = db_fetch_assoc_prepared('SELECT id
		FROM plugin_config
		WHERE directory = ?',
		array($plugin), false);

	if (cacti_sizeof($exists)) {
		db_execute_prepared('DELETE FROM plugin_config
			WHERE directory = ?',
			array($plugin));
	}

	$name     = $author = $webpage = $version = '';
	$function = 'plugin_' . $plugin . '_version';

	if (function_exists($function)) {
		$info = $function();
		$name = $info['longname'];

		if (isset($info['homepage'])) {
			$webpage = $info['homepage'];
		} elseif (isset($info['webpage'])) {
			$webpage = $info['webpage'];
		} else {
			$webpage = 'Not Stated';
		}

		$author  = $info['author'];
		$version = $info['version'];
	} elseif (strpos($plugin, 'plugin_') !== false) {
		raise_message('directory_error', __('The Plugin directory \'%s\' needs to be renamed to remove \'plugin_\' from the name before it can be installed.', $plugin), MESSAGE_LEVEL_ERROR);
		return false;
	} else {
		raise_message('version_error', __('The Plugin in the directory \'%s\' does not include an version function \'%s()\'.  This function must exist for the plugin to be installed.', $plugin, $function), MESSAGE_LEVEL_ERROR);
		return false;
	}

	db_execute_prepared('INSERT INTO plugin_config
		(directory, name, author, webpage, version, last_updated)
		VALUES (?, ?, ?, ?, ?, NOW())',
		array($plugin, $name, $author, $webpage, $version));

	$function = 'plugin_' . $plugin . '_install';

	if (function_exists($function)) {
		$function();
		$ready = api_plugin_check_config($plugin);

		if ($ready) {
			// Set the plugin as "disabled" so it can go live
			db_execute_prepared('UPDATE plugin_config
				SET status = 4
				WHERE directory = ?',
				array($plugin));

			cacti_log(sprintf('NOTE: Cacti Plugin %s has been installed by %s', $plugin, get_username()), false, 'PLUGIN');
		} else {
			// Set the plugin as "needs configuration"
			db_execute_prepared('UPDATE plugin_config
				SET status = 2
				WHERE directory = ?',
				array($plugin));

			cacti_log(sprintf('WARNING: Cacti Plugin %s was not installed by %s due to Configuration Issues', $plugin, get_username()), false, 'PLUGIN');
		}
	} else {
		raise_message('install_error', __('The Plugin in the directory \'%s\' does not include an install function \'%s()\'.  This function must exist for the plugin to be installed.', $plugin, $function), MESSAGE_LEVEL_ERROR);
		return false;
	}

	api_plugin_replicate_config();
}

/**
 * api_plugin_upgrade_register - Check the current version vs. the info version
 * and if it finds that they are different, it will update the version
 * and return true or false depending on if the version was changed.
 *
 * @param   string  The name of the plugin
 *
 * @return  bool    True if the version changed else false
 */
function api_plugin_upgrade_register($plugin) {
	global $config;

	$info = plugin_load_info_file(CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO');

	if ($info) {
		$details = db_fetch_row_prepared('SELECT *
			FROM plugin_config
			WHERE directory = ?',
			array($plugin));

		if (cacti_sizeof($details)) {
			$id      = $details['id'];
			$version = $details['version'];

			if (isset($info['webpage'])) {
				$info['homepage'] = $info['webpage'];
			}

			if ($version != $info['version']) {
				db_execute_prepared('UPDATE plugin_config
					SET name = ?, author = ?, webpage = ?, version = ?, last_updated = NOW()
					WHERE id = ?',
					array(
						$info['longname'],
						$info['author'],
						$info['homepage'],
						$info['version'],
						$id
					)
				);

				return true;
			}
		}
	}

	return false;
}

function api_plugin_uninstall_integrated() {
	global $config, $plugin_hooks, $plugins_integrated;

	foreach ($plugins_integrated as $plugin) {
		api_plugin_uninstall($plugin, false);
	}
}

function api_plugin_hooks_found($plugin) {
	return db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ?', array($plugin)) ? true:false;
}

function api_plugin_realms_found($plugin) {
	return db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_realms WHERE plugin = ?', array($plugin)) ? true:false;
}

function api_plugin_uninstall($plugin, $tables = true) {
	global $config;

	$plugin_found = false;
	if (file_exists(CACTI_PATH_PLUGINS . "/$plugin/setup.php")) {
		include_once(CACTI_PATH_PLUGINS . "/$plugin/setup.php");

		// Run the Plugin's Uninstall Function first
		$function = "plugin_{$plugin}_uninstall";

		if (function_exists($function)) {
			$function();

			$plugin_found = true;
		}
	}

	if (api_plugin_hooks_found($plugin)) {
		api_plugin_remove_hooks($plugin);
	}

	if (api_plugin_realms_found($plugin)) {
		api_plugin_remove_realms($plugin);
	}

	db_execute_prepared('DELETE FROM plugin_config
		WHERE directory = ?',
		array($plugin));

	if ($tables) {
		api_plugin_db_changes_remove($plugin);
	} else {
		db_execute_prepared('DELETE FROM plugin_db_changes
			WHERE plugin = ?',
			array($plugin));
	}

	if ($plugin_found) {
		api_plugin_replicate_config();

		cacti_log(sprintf('NOTE: Cacti Plugin %s has been uninstalled by %s', $plugin, get_username()), false, 'PLUGIN');
	}
}

function api_plugin_check_config($plugin) {
	global $config;

	clearstatcache();

	if (file_exists(CACTI_PATH_PLUGINS . "/$plugin/setup.php")) {
		include_once(CACTI_PATH_PLUGINS . "/$plugin/setup.php");

		$function = "plugin_{$plugin}_check_config";

		if (function_exists($function)) {
			return $function();
		}

		return true;
	}

	return false;
}

function api_plugin_enable($plugin) {
	$ready = api_plugin_check_config($plugin);

	if ($ready) {
		api_plugin_enable_hooks($plugin);

		db_execute_prepared('UPDATE plugin_config
			SET status = 1
			WHERE directory = ?',
			array($plugin));

		cacti_log(sprintf('WARNING: Cacti Plugin %s has been enabled by %s', $plugin, get_username()), false, 'PLUGIN');
	}
}

function api_plugin_is_enabled($plugin) {
	static $pstatus = array();

	if (isset($pstatus[$plugin])) {
		return $pstatus[$plugin];
	}

	$status = db_fetch_cell_prepared('SELECT status
		FROM plugin_config
		WHERE directory = ?',
		array($plugin), false);

	if ($status == '1') {
		$pstatus[$plugin] = true;

		return true;
	}

	$pstatus[$plugin] = false;

	return false;
}

function api_plugin_disable($plugin) {
	api_plugin_disable_hooks($plugin);

	db_execute_prepared('UPDATE plugin_config
		SET status = 4
		WHERE directory = ?',
		array($plugin));

	api_plugin_replicate_config();

	cacti_log(sprintf('WARNING: Cacti Plugin %s has been disabled by %s', $plugin, get_username()), false, 'PLUGIN');
}

function api_plugin_remove_data($plugin) {
	$setup_file = CACTI_PATH_BASE . "/plugins/$plugin/setup.php";

	if (file_exists($setup_file)) {
		require_once($setup_file);

		$rmdata_function = "plugin_{$plugin}_remove_data";

		if (function_exists($rmdata_function)) {
			$rmdata_function();

			raise_message('rmdata_complete', __('Data for Plugin %s including Tables and Settings has been removed.', $plugin), MESSAGE_LEVEL_INFO);
		} else {
			raise_message('rmdata_not_complete', __('Data for Plugin %s including Tables and Settings has not been removed due to missing removal function.', $plugin), MESSAGE_LEVEL_ERROR);
		}
	}
}

function api_plugin_replicate_config() {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	$gone_time = read_config_option('poller_interval') * 2;

	$pollers = array_rekey(
		db_fetch_assoc('SELECT
			id,
			UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) AS last_polled
			FROM poller
			WHERE id > 1
			AND disabled=""'),
		'id', 'last_polled'
	);

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller_id => $last_polled) {
			if ($last_polled < $gone_time) {
				replicate_out($poller_id, 'plugins');
			}
		}
	}
}

function api_plugin_drop_remote_table($table) {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	$gone_time = read_config_option('poller_interval') * 2;

	$pollers = array_rekey(
		db_fetch_assoc('SELECT
			id,
			UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) AS last_polled
			FROM poller
			WHERE id > 1
			AND disabled=""'),
		'id', 'last_polled'
	);

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller_id => $last_polled) {
			$rcnn_id = poller_connect_to_remote($poller_id);

			if ($rcnn_id !== false) {
				db_execute("DROP TABLE IF EXISTS $table", false, $rcnn_id);
			}
		}
	}
}

function api_plugin_disable_all($plugin) {
	api_plugin_disable_hooks_all($plugin);

	db_execute_prepared('UPDATE plugin_config
		SET status = 7
		WHERE directory = ?',
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_moveup($plugin) {
	$id = db_fetch_cell_prepared('SELECT id
		FROM plugin_config
		WHERE directory = ?',
		array($plugin));

	if (!empty($id)) {
		$temp_id = db_fetch_cell('SELECT MAX(id) FROM plugin_config') + 1;

		$prior_id = db_fetch_cell_prepared('SELECT MAX(id)
			FROM plugin_config
			WHERE id < ?',
			array($id));

		/* update the above plugin to the prior temp id */
		db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($temp_id, $prior_id));
		db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($prior_id, $id));
		db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($id, $temp_id));
	}

	api_plugin_replicate_config();
}

function api_plugin_movedown($plugin) {
	$id      = db_fetch_cell_prepared('SELECT id FROM plugin_config WHERE directory = ?', array($plugin));
	$temp_id = db_fetch_cell('SELECT MAX(id) FROM plugin_config') + 1;
	$next_id = db_fetch_cell_prepared('SELECT MIN(id) FROM plugin_config WHERE id > ?', array($id));

	/* update the above plugin to the prior temp id */
	db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($temp_id, $next_id));
	db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($next_id, $id));
	db_execute_prepared('UPDATE plugin_config SET id = ? WHERE id = ?', array($id, $temp_id));

	api_plugin_replicate_config();
}

function api_plugin_register_hook($plugin, $hook, $function, $file, $enable = false) {
	$status = 0;

	if (!api_plugin_valid_entrypoint($plugin, __FUNCTION__)) {
		return false;
	}

	$exists = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_hooks
		WHERE name = ?
		AND hook = ?',
		array($plugin, $hook), false);

	if (!$exists) {
		// enable the hooks if they are system level hooks to enable configuration
		$settings = array('config_settings', 'config_arrays', 'config_form');
		$status   = (!in_array($hook, $settings, true) ? 0 : 1);

		if ($enable) {
			$status = 1;
		}

		db_execute_prepared('INSERT INTO plugin_hooks
			(name, hook, `function`, file, status)
			VALUES (?, ?, ?, ?, ?)',
			array($plugin, $hook, $function, $file, $status));
	} else {
		if ($enable == true) {
			$status = 1;
		}

		// enable the hook automatically if other hooks are already enabled
		// for this plugin.
		if (!$status) {
			$exists = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_hooks
				WHERE name = ?
				AND status = 1',
				array($plugin));

			if ($exists > 0) {
				$status = 1;
			}
		}

		db_execute_prepared('UPDATE plugin_hooks
			SET `function` = ?, `status` = ?,
			`file` = ?
			WHERE `name` = ?
			AND `hook` = ?',
			array($function, $status, $file, $plugin, $hook));
	}

	api_plugin_replicate_config();
}

function api_plugin_remove_hooks($plugin) {
	db_execute_prepared('DELETE FROM plugin_hooks
		WHERE name = ?',
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_enable_hooks($plugin) {
	db_execute_prepared('UPDATE plugin_hooks
		SET status = 1
		WHERE name = ?',
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_disable_hooks($plugin) {
	db_execute_prepared("UPDATE plugin_hooks
		SET status = 4
		WHERE name = ?
		AND hook != 'config_settings'
		AND hook != 'config_arrays'
		AND hook != 'config_form'",
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_disable_hooks_all($plugin) {
	db_execute_prepared('UPDATE plugin_hooks
		SET status = 0
		WHERE name = ?',
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_valid_entrypoint($plugin, $function) {
	// Check for invalid entrypoint install/upgrade
	$backtrace = debug_backtrace();
	if (cacti_sizeof($backtrace)) {
		if (!preg_match('/(install|upgrade|setup)/i', $backtrace[2]['function'])) {
			cacti_log(sprintf('WARNING: Plugin \'%s\' is attempting to call \'%s\' improperly in function \'%s\'', $plugin, $function, $backtrace[2]['function']), false, 'PLUGIN');
			return false;
		}
	}

	return true;
}

function api_plugin_register_realm($plugin, $file, $display, $admin = true) {
	if (!api_plugin_valid_entrypoint($plugin, __FUNCTION__)) {
		return false;
	}

	$files = explode(',', $file);
	$i     = 0;

	$sql_where = '(';

	foreach ($files as $tfile) {
		$sql_where .= ($sql_where != '(' ? ' OR ':'') .
			' (file = "' . $tfile . '" OR file LIKE "' . $tfile . ',%" OR file LIKE "%,' . $tfile . ',%" OR file LIKE "%,' . $tfile . '")';
	}
	$sql_where .= ')';

	$realm_ids = db_fetch_assoc_prepared("SELECT id
		FROM plugin_realms
		WHERE plugin = ?
		AND $sql_where",
		array($plugin));

	if (cacti_sizeof($realm_ids) == 1) {
		$realm_id = $realm_ids[0]['id'];
	} elseif (cacti_sizeof($realm_ids) > 1) {
		$realm_id = $realm_ids[0]['id'];
		cacti_log('WARNING: Registering Realm for Plugin ' . $plugin . ' and Filenames ' . $file . ' is ambiguous.  Using first matching Realm.  Contact the plugin owner to resolve this issue.', false, 'PLUGIN');

		unset($realm_ids[0]);

		foreach ($realm_ids as $id) {
			$realm_info = db_fetch_row_prepared('SELECT *
				FROM plugin_realms
				WHERE id = ?',
				array($id['id']));

			if ($file == $realm_info['file']) {
				db_execute_prepared('UPDATE IGNORE user_auth_realm
					SET realm_id = ?
					WHERE realm_id = ?',
					array($realm_id + 100, $realm_info['id'] + 100));

				db_execute_prepared('UPDATE IGNORE user_auth_group_realm
					SET realm_id = ?
					WHERE realm_id = ?',
					array($realm_id + 100, $realm_info['id'] + 100));

				db_execute_prepared('DELETE FROM plugin_realms
					WHERE id = ?',
					array($realm_info['id']));
			} elseif (strpos($realm_info['file'], $file)) {
				if (substr($realm_info['file'], 0, strlen($file)) == $file) {
					$file = substr($file, strlen($file) - 1);
				} else {
					$file = str_replace(',' . $file, '', $realm_info['file']);
					$file = str_replace(',,', ',', $file);
				}

				db_execute_prepared('UPDATE plugin_realms
					SET file = ?
					WHERE id = ?',
					array($file, $realm_info['id']));
			}
		}
	} else {
		$realm_id = false;
	}

	if ($realm_id === false) {
		db_execute_prepared('REPLACE INTO plugin_realms
			(plugin, file, display)
			VALUES (?, ?, ?)',
			array($plugin, $file, $display));

		if ($admin) {
			$realm_id = db_fetch_cell_prepared('SELECT id
				FROM plugin_realms
				WHERE plugin = ?
				AND file = ?',
				array($plugin, $file), false);

			$realm_id = $realm_id + 100;

			$user_ids[] = read_config_option('admin_user');

			if (isset($_SESSION[SESS_USER_ID])) {
				$user_ids[] = $_SESSION[SESS_USER_ID];
			}

			if (cacti_sizeof($user_ids)) {
				foreach ($user_ids as $user_id) {
					db_execute_prepared('REPLACE INTO user_auth_realm
						(user_id, realm_id)
						VALUES (?, ?)',
						array($user_id, $realm_id));
				}
			}
		}
	} else {
		db_execute_prepared('UPDATE plugin_realms
			SET display = ?,
			file = ?
			WHERE id = ?',
			array($display, $file, $realm_id));
	}

	api_plugin_replicate_config();
}

function api_plugin_remove_realms($plugin) {
	$realms = db_fetch_assoc_prepared('SELECT id
		FROM plugin_realms
		WHERE plugin = ?',
		array($plugin), false);

	foreach ($realms as $realm) {
		$id = $realm['id'] + 100;
		db_execute_prepared('DELETE FROM user_auth_realm
			WHERE realm_id = ?',
			array($id));

		db_execute_prepared('DELETE FROM user_auth_group_realm
			WHERE realm_id = ?',
			array($id));
	}

	db_execute_prepared('DELETE FROM plugin_realms
		WHERE plugin = ?',
		array($plugin));

	api_plugin_replicate_config();
}

function api_plugin_load_realms() {
	global $user_auth_realms, $user_auth_realm_filenames;

	$plugin_realms = db_fetch_assoc('SELECT *
		FROM plugin_realms
		ORDER BY plugin, display');

	if (cacti_sizeof($plugin_realms)) {
		foreach ($plugin_realms as $plugin_realm) {
			$plugin_files = explode(',', $plugin_realm['file']);

			foreach ($plugin_files as $plugin_file) {
				$user_auth_realm_filenames[$plugin_file] = $plugin_realm['id'] + 100;
			}

			$user_auth_realms[$plugin_realm['id'] + 100] = $plugin_realm['display'];
		}
	}
}

function api_plugin_user_realm_auth($filename = '') {
	global $user_auth_realm_filenames;
	/* list all realms that this user has access to */

	if ($filename != '' && isset($user_auth_realm_filenames[basename($filename)])) {
		if (is_realm_allowed($user_auth_realm_filenames[basename($filename)])) {
			return true;
		}
	}

	return false;
}

function api_plugin_reorder($new_order) {
	if (cacti_sizeof($new_order)) {
		$plugins = db_fetch_assoc('SELECT * FROM plugin_config ORDER BY id');
		$columns = array_keys($plugins[0]);

		$plugins_reorder = array_rekey($plugins, 'id', $columns);

		foreach($new_order as $plugin) {
			$id = str_replace('line', '', $plugin);
			input_validate_input_number($id, 'id');

			$order[] = $id;
		}

		$sequence = 1;

		$sql = 'REPLACE INTO plugin_config
			(id, directory, name, status, author, webpage, version, last_updated)
			VALUES ';

		$params = array();

		foreach($order as $id) {
			if (isset($plugins_reorder[$id])) {
				$plugins_reorder[$id]['id'] = $sequence;

				$sql .= ($sequence > 1 ? ',':'') . '(?, ?, ?, ?, ?, ?, ?, ?)';

				$params[] = $plugins_reorder[$id]['id'];
				$params[] = $plugins_reorder[$id]['directory'];
				$params[] = $plugins_reorder[$id]['name'];
				$params[] = $plugins_reorder[$id]['status'];
				$params[] = $plugins_reorder[$id]['author'];
				$params[] = $plugins_reorder[$id]['webpage'];
				$params[] = $plugins_reorder[$id]['version'];
				$params[] = $plugins_reorder[$id]['last_updated'];

				$sequence++;
			}
		}

		/* resequence it one transaction */
		db_execute_prepared($sql, $params);

		/* remove anything invalid */
		db_execute_prepared('DELETE FROM plugin_config WHERE id >= ?', array($sequence));
	}
}

function api_plugin_get_available_file_contents($plugin, $tag, $filetype) {
	include_once(CACTI_PATH_INCLUDE . '/vendor/parsedown/Parsedown.php');

	if (db_column_exists('plugin_available', $filetype)) {
		$contents = db_fetch_cell_prepared("SELECT $filetype AS data
			FROM plugin_available
			WHERE plugin = ?
			AND tag_name = ?",
			array($plugin, $tag));

		if ($contents != '') {
			$contents = base64_decode($contents);

			$Parsedown = new Parsedown();

			print $Parsedown->text($contents);
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function api_plugin_archive_remove($plugin, $id) {
	db_execute_prepared('DELETE FROM plugin_archive
		WHERE plugin = ? AND id = ?',
		array($plugin, $id));

	raise_message('plugin_archive_removed', __('The Archive for Plugin \'%s\' has been removed.', $plugin), MESSAGE_LEVEL_INFO);
}

function api_plugin_archive_restore($plugin, $id, $type = 'archive') {
	if ($type == 'archive') {
		$archive = db_fetch_cell_prepared('SELECT archive
			FROM plugin_archive
			WHERE plugin = ?
			AND id = ?',
			array($plugin, $id));

		$new_updated = db_fetch_cell_prepared('SELECT last_updated
			FROM plugin_archive
			WHERE plugin = ?
			AND id = ?',
			array($plugin, $id));
	} else {
		$archive = db_fetch_cell_prepared('SELECT archive
			FROM plugin_available
			WHERE plugin = ?
			AND tag_name = ?',
			array($plugin, $id));

		$new_updated = db_fetch_cell_prepared('SELECT published_at
			FROM plugin_available
			WHERE plugin = ?
			AND tag_name = ?',
			array($plugin, $id));
	}

	if ($archive != '') {
		$tmpfile  = sys_get_temp_dir() . '/' . $plugin . '_' . rand() . '.tar.gz';;
		$pharfile = "phar://{$tmpfile}";

		$file_data = base64_decode($archive);

		if ($file_data != '') {
			/* set the restore path to the plugin directory */
			$restore_path = CACTI_PATH_BASE . "/plugins/$plugin";

			/* write the archive to the temporary directory */
			file_put_contents($tmpfile, $file_data);

			/* open the archive */
			$archive = new PharData($tmpfile);

			/* create directory if required */
			if (!is_dir($restore_path)) {
				if (!mkdir($restore_path, 0755, true)) {
					if ($type == 'archive') {
						raise_message('restore_failed', __('Restore failed!  The Plugin \'%s\' archive Restore failed.  Unable to create directory \'%s\'.', $plugin, $restore_path), MESSAGE_LEVEL_ERROR);
					} else {
						raise_message('restore_failed', __('Restore failed!  The available Plugin \'%s\' Load failed.  Unable to create directory \'%s\'.', $plugin, $restore_path), MESSAGE_LEVEL_ERROR);
					}

					$archive->__destruct();
					unlink($tmpfile);

					return false;
				}
			}

			/* get the list of files and directories from inside the archive file */
			$archive_files = array();

			foreach (new RecursiveIteratorIterator($archive) as $file) {
				/**
				 * archives from github have an extra directory
				 * remove it.
				 *
				 * an example from the resulting array:
				 * [CHANGELOG.md] => /Cacti-plugin_cycle-e941f17/CHANGELOG.md
				 */
				if ($type != 'archive') {
					$pfile    = str_replace($pharfile, '', $file->getPathname());
					$tfile    = ltrim($pfile, '/');
					$paths    = explode('/', $tfile);
					$bad_path = array_shift($paths);
					$tfile    = implode('/', $paths);

					/* skip hidden files like .github* */
					if (substr($tfile, 0, 1) == '.' && $tfile != '.htaccess') {
						continue;
					}

					$archive_files[$tfile] = $pfile;
				} else {
					$tfile = str_replace("phar://{$tmpfile}", '', $file->getPathname());

					$archive_files[$tfile] = $tfile;
				}
			}

			/* get the list of files in the current plugin directory */
			$dir_iterator = new RecursiveDirectoryIterator($restore_path);
			$iterator     = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

			$current_files = array();
			foreach ($iterator as $file) {
				$file = str_replace($restore_path, '', $file);

				if (substr($file, -1) == '.') {
					continue;
				}

				$current_files[$file] = $file;
			}

			/* relative plugin data locations that should not be remove */
			$info_file = CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO';
			$noremove  = array();
			if (file_exists($info_file)) {
				$info = plugin_load_info_file($info_file);

				if (isset($info['noremove'])) {
					$noremove = explode(' ', $info['noremove']);
				}
			}

			/* remove files that are not in the archive */
			foreach ($current_files as $file) {
				if (!is_dir("$restore_path/$file") && !isset($archive_files[$file])) {
					if (basename($file) !== 'config.php' && basename($file) != 'config_local.php') {
						if (!in_array(dirname($file), $noremove)) {
							// Let's not do that until we figure out weathermap.
							// We need a discussion by the cactigroup as well
							// unlink("$restore_path/$file");
						}
					}
				}
			}

			/* load the archive into memory */
			Phar::loadPhar($tmpfile, 'my.tgz');

			/**
			 * put yourself into the base directory in order to
			 * be able to use basenames to create things like
			 * directories.
			 */
			chdir($restore_path);

			foreach($archive_files as $basefile => $pharpath) {
				$output = file_get_contents("phar://my.tgz{$pharpath}");

				if (strlen($output)) {
					$rfile = ltrim($basefile, '/');

					if (basename($rfile) != $rfile) {
						if (!is_dir(dirname($rfile)) && !mkdir(dirname($rfile), 0755, true)) {
							if ($type == 'archive') {
								raise_message('restore_failed', __('Restore failed!  The archived Plugin \'%s\' Restore failed. Unable to create directory %s', $plugin, dirname($basefile)), MESSAGE_LEVEL_INFO);
							} else {
								raise_message('restore_failed', __('Load failed!  The available Plugin \'%s\' Load failed. Unable to create directory %s', $plugin, dirname($basefile)), MESSAGE_LEVEL_INFO);
							}

							$archive->__destruct();
							unlink($tmpfile);

							return false;
						}
					}

					file_put_contents($restore_path . '/' . $basefile, $output);
				}
			}

			$archive->__destruct();

			/* remove the archive file */
			unlink($tmpfile);

			if ($type == 'archive') {
				raise_message('archive_restored', __('Restore succeeded!  The archived Plugin \'%s\' Restore succeeded.', $plugin), MESSAGE_LEVEL_INFO);

				db_execute_prepared('UPDATE plugin_config
					SET last_updated = ?,
					WHERE directory = ?',
					array($new_updated, $plugin));
			} else {
				raise_message('archive_restored', __('Load succeeded!  The available Plugin \'%s\' Load succeeded.', $plugin), MESSAGE_LEVEL_INFO);

				if ($id == 'develop') {
					db_execute_prepared('UPDATE plugin_config
						SET last_updated = NOW()
						WHERE directory = ?',
						array($plugin));
				} else {
					db_execute_prepared('UPDATE plugin_config
						SET last_updated = ?,
						WHERE directory = ?',
						array($new_updated, $plugin));
				}
			}

			return true;
		} else {
			if ($type == 'archive') {
				raise_message('archive_failed', __('Restore failed!  The archived Plugin \'%s\' Restore failed.  Check the cacti.log for warnings.', $plugin), MESSAGE_LEVEL_ERROR);
			} else {
				raise_message('archive_failed', __('Load failed!  The available Plugin \'%s\' Load failed.  Check the cacti.log for warnings.', $plugin), MESSAGE_LEVEL_ERROR);
			}

			return false;
		}
	} else {
		if ($type == 'archive') {
			raise_message('plugin_archive_not_found', __('Restore failed!  Unable to locate the archive record for Plugin \'%s\' in the database.', $plugin), MESSAGE_LEVEL_ERROR);
		} else {
			raise_message('plugin_archive_not_found', __('Load failed!  Unable to locate the available record for Plugin \'%s\' in the database.', $plugin), MESSAGE_LEVEL_ERROR);
		}

		return false;
	}
}

function api_plugin_archive($plugin) {
	$plugin_data = db_fetch_row_prepared('SELECT *
		FROM plugin_config
		WHERE directory = ?',
		array($plugin));

	if (cacti_sizeof($plugin_data)) {
		$tmpfile  = sys_get_temp_dir() . '/' . $plugin . '_' . rand() . '.tar';
		$tmpafile = "$tmpfile.gz";
		$path     = CACTI_PATH_BASE . "/plugins/$plugin";
		$md5sum   = md5sum_path($path);

		/* create the tar file */
		$archive = new PharData($tmpfile);
		$archive->buildFromDirectory($path);

		/* create the tar.gz file */
		$archive->compress(Phar::GZ);
		$archive->__destruct();

		/* delete the tar file */
		unlink($tmpfile);

		$info_file = CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO';
		$compat    = '';
		$requires  = '';

		if (file_exists($info_file)) {
			$info = plugin_load_info_file($info_file);

			if (isset($info['compat'])) {
				$compat = $info['compat'];
			}

			if (isset($info['requires'])) {
				$requires = $info['requires'];
			}
		}

		if (file_exists($tmpafile)) {
			db_execute_prepared('INSERT INTO plugin_archive
				(plugin, description, author, webpage, version, requires, compat, user_id, dir_md5sum, last_updated, archive)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array(
					$plugin,
					$plugin_data['name'],
					$plugin_data['author'],
					$plugin_data['webpage'],
					$plugin_data['version'],
					$requires,
					$compat,
					SESS_USER_ID,
					$md5sum,
					date('Y-m-d H:i:s'),
					base64_encode(file_get_contents($tmpafile))
				)
			);

			unlink($tmpafile);

			raise_message('plugin_archived', __('The Plugin \'%s\' has been archived successfully.', $plugin), MESSAGE_LEVEL_INFO);
		} else {
			raise_message('plugin_archive_failed', __('The Plugin \'%s\' archiving process has failed.  Check the Cacti log for errors.', $plugin), MESSAGE_LEVEL_ERROR);
		}
	} else {
		raise_message('plugin_archive_failed', __('The Plugin \'%s\' archiving process has failed due to the plugin directory being missing.', $plugin), MESSAGE_LEVEL_ERROR);
	}
}

function plugin_config_arrays() {
	global $config, $menu;

	if ($config['poller_id'] == 1 || $config['connection'] == 'online') {
		$menu[__('Configuration')]['plugins.php'] = __('Plugins');
	}

	api_plugin_load_realms();
}

function plugin_draw_navigation_text($nav) {
	$nav['plugins.php:'] = array('title' => __('Plugins'), 'mapping' => 'index.php:', 'url' => 'plugins.php', 'level' => '1');

	return $nav;
}

function plugin_is_compatible($plugin) {
	global $config;

	$info = plugin_load_info_file(CACTI_PATH_PLUGINS . '/' . $plugin . '/INFO');

	if ($info !== false) {
		if (!isset($info['compat']) || plugin_valid_version_range($info['compat'], CACTI_VERSION)) {
			return array('compat' => false, 'requires' => __('Requires: Cacti >= %s', $info['compat']));
		}
	} else {
		return array('compat' => false, 'requires' => __('Legacy Plugin'));
	}

	return array('compat' => true, 'requires' => __('Requires: Cacti >= %s', $info['compat']));
}

function plugin_valid_version_range($range_string, $compare_version = CACTI_VERSION)  {
	if (strpos($range_string, ' ') !== false) {
		$compares = explode(' ', $range_string);

		foreach($compares as $line) {
			if (strpos($line, '<=') !== false) {
				$theversion = str_replace('<=', '', $line);
				$versions[] = array('direction' => '=', 'version' => $theversion);
			} elseif (strpos($line, '>=') !== false) {
				$theversion = str_replace('>=', '', $line);
				$versions[] = array('direction' => '=', 'version' => $theversion);
			} elseif (strpos($line, '<') !== false) {
				$theversion = str_replace('<', '', $line);
				$versions[] = array('direction' => '=', 'version' => $theversion);
			} elseif (strpos($line, '>') !== false) {
				$theversion = str_replace('>', '', $line);
				$versions[] = array('direction' => '=', 'version' => $theversion);
			} elseif (strpos($line, '=') !== false) {
				$theversion = str_replace('=', '', $line);
				$versions[] = array('direction' => '=', 'version' => $theversion);
			} else {
				cacti_log('Invalid version comparison');
				return false;
			}
		}

		foreach($versions as $v) {
			if (!cacti_version_compare($compare_version, $v['version'], $v['direction'])) {
				return false;
			}
		}
	} else {
		$versions[] = array('direction' => '>=', 'version' => $range_string);

		if (cacti_version_compare($compare_version, $range_string, '>=')) {
			return true;
		} else {
			return false;
		}
	}

	return true;
}

function plugin_valid_dependencies($required) {
	if ($required == '') {
		return true;
	} elseif (strpos($required, ' ') !== false) {
		$requires = array_map('trim', explode(' ', $required));
	} else {
		$requires[] = $required;
	}

	foreach($requires as $r) {
		$parts    = explode(':', $r);
		$dplugin  = $parts[0];
		$compares = $parts[1];

		$version  = db_fetch_cell_prepared('SELECT version
			FROM plugin_config
			WHERE directory = ?',
			array($dplugin));

		if (empty($version)) {
			return false;
		}

		if (!plugin_valid_version_range($compares, $version)) {
			return false;
		}
	}

	return true;
}

function plugin_load_info_defaults($file, $info, $defaults = array()) {
	$result = $info;

	if ($file != '') {
		$dir = basename(dirname($file));
	} else {
		$dir = 'unknown';
	}

	if (!is_array($defaults)) {
		$defaults = array();
	}

	if (!is_array($result)) {
		$result = array();
	}

	$info_fields = array(
		'name'         => ucfirst($dir),
		'requires'     => '',
		'longname'     => ucfirst($dir),
		'status'       => file_exists($file) ? 0 : -4,
		'version'      => __('Unknown'),
		'author'       => __('Unknown'),
		'homepage'     => isset($info['webpage']) ? $info['webpage'] : __('Not Stated'),
		'capabilities' => '',
		'directory'    => $dir,
	);

	$info_fields = $info_fields + $defaults;

	foreach ($info_fields as $name => $value) {
		if (!array_key_exists($name, $result)) {
			$result[$name] = $value;
		}
	}

	if ($info_fields['status'] == 0) {
		if (strstr($dir, ' ') !== false) {
			$result['status'] = -3;
		} elseif (strtolower($dir) != strtolower($result['name'])) {
			$result['status'] = -2;
		} elseif (!isset($result['compat']) || cacti_version_compare(CACTI_VERSION, $result['compat'], '<')) {
			$result['status'] = -1;
		}
	}

	return $result;
}

function plugin_load_info_file($file) {
	$info = false;

	if (file_exists($file)) {
		if (is_readable($file)) {
			$info = parse_ini_file($file, true);

			if (cacti_sizeof($info) && array_key_exists('info', $info)) {
				$info = plugin_load_info_defaults($file, $info['info']);
			} else {
				cacti_log('WARNING: Loading plugin INFO file failed.  Parsing INI file failed.', false, 'WEBUI');
			}
		} else {
			cacti_log('WARNING: Loading plugin INFO file failed.  INFO file not readable.', false, 'WEBUI');
		}
	} else {
		cacti_log('WARNING: Loading plugin INFO file failed.  INFO file does not exist.', false, 'WEBUI');
	}

	return $info;
}

function plugin_fetch_latest_plugins() {
	$start = microtime(true);

	$repo = trim(read_config_option('github_repository'), "/\n\r ");
	$user = trim(read_config_option('github_user'));

	if ($repo == '' || $user == '') {
	       	if (CACTI_WEB) {
			raise_message('plugins_failed', __('Unable to retrieve Cacti Plugins due to the Base API Repository URL or User not being set in Configuration > Settings > General > GitHub/GitLab API Settings.'), MESSAGE_LEVEL_ERROR);
		} else {
			print 'Unable to retrieve Cacti Plugins due to the Base API Repository URL or User not being set in Configuration > Settings > General > GitHub/GitLab API Settings.' . PHP_EOL;
		}

		return false;
	}

	$avail_plugins = array();

	$plugins = plugin_make_github_request("$repo/users/$user/repos", 'json');

	if ($plugins === false) {
       	if (CACTI_WEB) {
			raise_message('plugins_failed', __('No plugins found at GitHub/GitLab location.'), MESSAGE_LEVEL_ERROR);
			header('Location: plugins.php');
			exit;
		} else {
			print 'WARNING: No plugins found at GitHub/GitLab location.' . PHP_EOL;
			return false;
		}
	}

	if (cacti_sizeof($plugins)) {
		foreach($plugins as $pi) {
			if (isset($pi['full_name'])) {
				if (strpos($pi['full_name'], 'plugin_') !== false) {
					$plugin = explode('plugin_', $pi['full_name'])[1];

					$avail_plugins[$plugin]['name'] = $plugin;
				}
			}
		}
	}

	$updated = 0;

	if (cacti_sizeof($avail_plugins)) {
		foreach($avail_plugins as $plugin_name => $pi_details) {
			$details = plugin_make_github_request("$repo/repos/$user/plugin_{$plugin_name}/releases", 'json');

			if ($details === false) {
				if (CACTI_WEB) {
					raise_message('releases_warning', __('The Cacti plugin %s has not releases', $plugin_name), MESSAGE_LEVEL_WARN);
					header('Location: plugins.php');
					exit;
				} else {
					printf('The Cacti plugin %s has not releases' . PHP_EOL, $plugin_name);
					return false;
				}
			}

			if (cacti_sizeof($details)) {
				$json_data = $details;

				/* insert latest release */
				if (isset($json_data[0]['tag_name'])) {
					$avail_plugins[$plugin_name][$json_data[0]['tag_name']]['body']         = $json_data[0]['body'];
					$avail_plugins[$plugin_name][$json_data[0]['tag_name']]['published_at'] = date('Y-m-d H:i:s', strtotime($json_data[0]['published_at']));

					$published_at = date('Y-m-d H:i:s', strtotime($json_data[0]['published_at']));
					$tag_name     = $json_data[0]['tag_name'];

					$unchanged = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM plugin_available
						WHERE plugin = ?
						AND published_at = ?
						AND tag_name = ?',
						array($plugin_name, $published_at, $tag_name)
					);

					if ($unchanged) {
						$skip = true;
						cacti_log(sprintf('SKIPPED: Plugin:\'%s\', Tag/Release:\'%s\' Skipped as it has not changed', $plugin_name, $json_data[0]['tag_name']), false, 'PLUGIN');
					} else {
						$skip = false;
					}

					if (!$skip) {
						$updated++;

						$pstart = microtime(true);

						$files = array(
							'changelog' => "$repo/repos/$user/plugin_{$plugin_name}/contents/CHANGELOG.md?ref={$json_data[0]['tag_name']}",
							'readme'    => "$repo/repos/$user/plugin_{$plugin_name}/contents/README.md?ref={$json_data[0]['tag_name']}",
							'info'      => "$repo/repos/$user/plugin_{$plugin_name}/contents/INFO?ref={$json_data[0]['tag_name']}",
							'archive'   => "$repo/repos/$user/plugin_{$plugin_name}/tarball?ref={$json_data[0]['tag_name']}"
						);

						$ofiles = array();

						foreach($files as $file => $url) {
							if ($file != 'archive') {
								$file_details = plugin_make_github_request($url, 'json');

								if ($file_details === false) {
									if (CACTI_WEB) {
										raise_message('plugins_failed', __('Unable to get archive from GitHub/GitLab location.'), MESSAGE_LEVEL_ERROR);
										header('Location: plugins.php');
										exit;
									} else {
										print 'WARNING: Unable to get archive from GitHub/GitLab location.' . PHP_EOL;
										return false;
									}
								}

								if (isset($file_details['content'])) {
									$ofiles[$file] = base64_decode($file_details['content']);
								} else {
									$ofiles[$file] = '';
								}
							} else {
								$file_details = plugin_make_github_request($url, 'file');

								if ($file_details === false) {
									if (CACTI_WEB) {
										raise_message('plugins_failed', __('Unable to get %s from GitHub/GitLab location.', $file), MESSAGE_LEVEL_ERROR);
										header('Location: plugins.php');
										exit;
									} else {
										printf('WARNING: Unable to get %s from GitHub/GitLab location.' . PHP_EOL, $file);
										return false;
									}
								}

								$ofiles[$file] = $file_details;
							}
						}

						$compat      = '';
						$requires    = '';
						$description = '';
						$webpage     = '';
						$author      = '';
						if ($ofiles['info'] != '') {
							$lines = explode("\n", $ofiles['info']);

							foreach($lines as $l) {
								if (strpos($l, 'compat ') !== false) {
									$compat = trim(explode('=', $l)[1]);
								} elseif (strpos($l, 'requires ') !== false) {
									$requires = trim(explode('=', $l)[1]);
								} elseif (strpos($l, 'longname ') !== false) {
									$description = trim(explode('=', $l)[1]);
								} elseif (strpos($l, 'homepage ') !== false) {
									$webpage = trim(explode('=', $l)[1]);
								} elseif (strpos($l, 'author ') !== false) {
									$author = trim(explode('=', $l)[1]);
								}
							}
						}

						db_execute_prepared('REPLACE INTO plugin_available
							(plugin, description, author, webpage, tag_name, compat, requires, published_at, body, info, readme, changelog, archive)
							VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
							array(
								$plugin_name,
								$description,
								$author,
								$webpage,
								$json_data[0]['tag_name'],
								$compat,
								$requires,
								date('Y-m-d H:i:s', strtotime($json_data[0]['published_at'])),
								base64_encode($json_data[0]['body']),
								base64_encode($ofiles['info']),
								base64_encode($ofiles['readme']),
								base64_encode($ofiles['changelog']),
								base64_encode($ofiles['archive'])
							)
						);

						$pend = microtime(true);

						cacti_log(sprintf('UPDATED: Plugin:\'%s\', Tag/Release:\'%s\' Updated in %0.2f seconds.', $plugin_name, $json_data[0]['tag_name'], $pend - $pstart), false, 'PLUGIN');
					}
				}
			}

			$develop = plugin_make_github_request("$repo/repos/$user/plugin_{$plugin_name}?rel=develop", 'json');

			if ($develop === false) {
				if (CACTI_WEB) {
					raise_message('plugins_failed', __('Unable to get develop repo data for plugin %s from GitHub/GitLab location.', $plugin_name), MESSAGE_LEVEL_ERROR);
					header('Location: plugins.php');
					exit;
				} else {
					printf('Unable to get develop repo data for plugin %s from GitHub/GitLab location.' . PHP_EOL, $plugin_name);
					return false;
				}
			}

			if (cacti_sizeof($develop)) {
				$published_at = date('Y-m-d H:i:s', strtotime($develop['pushed_at']));
				$tag_name     = 'develop';

				$unchanged = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM plugin_available
					WHERE plugin = ?
					AND published_at = ?
					AND tag_name = ?',
					array($plugin_name, $published_at, $tag_name)
				);

				if ($unchanged) {
					$skip = true;
					cacti_log(sprintf('SKIPPED: Plugin:\'%s\', Tag/Release:\'%s\' Skipped as it has not changed', $plugin_name, 'develop'), false, 'PLUGIN');
				} else {
					$skip = false;
				}

				if (!$skip) {
					$updated++;

					$pstart = microtime(true);

					$avail_plugins[$plugin_name]['develop']['body']         = '';
					$avail_plugins[$plugin_name]['develop']['published_at'] = $published_at;

					/* insert develop */
					$files = array(
						'changelog' => "$repo/repos/$user/plugin_{$plugin_name}/contents/CHANGELOG.md?ref=develop",
						'readme'    => "$repo/repos/$user/plugin_{$plugin_name}/contents/README.md?ref=develop",
						'info'      => "$repo/repos/$user/plugin_{$plugin_name}/contents/INFO?ref=develop",
						'archive'   => "$repo/repos/$user/plugin_{$plugin_name}/tarball?ref=develop"
					);

					$ofiles = array();

					foreach($files as $file => $url) {
						if ($file != 'archive') {
							$file_details = plugin_make_github_request($url, 'json');

							if ($file_details === false) {
								if (CACTI_WEB) {
									raise_message('plugins_failed', __('Unable to get archive from GitHub/GitLab location.'), MESSAGE_LEVEL_ERROR);
									header('Location: plugins.php');
									exit;
								} else {
									print 'WARNING: Unable to get archive from GitHub/GitLab location.' . PHP_EOL;
									return false;
								}
							}

							if (isset($file_details['content'])) {
								$ofiles[$file] = base64_decode($file_details['content']);
							} else {
								$ofiles[$file] = '';
							}
						} else {
							$file_details = plugin_make_github_request($url, 'file');

							if ($file_details === false) {
								if (CACTI_WEB) {
									raise_message('plugins_failed', __('Unable to get %s from GitHub/GitLab location.', $file), MESSAGE_LEVEL_ERROR);
									header('Location: plugins.php');
									exit;
								} else {
									printf('WARNING: Unable to get %s from GitHub/GitLab location.' . PHP_EOL, $file);
									return false;
								}
							}

							$ofiles[$file] = $file_details;
						}
					}

					$compat      = '';
					$requires    = '';
					$description = '';
					$author      = '';
					$webpage     = '';
					if ($ofiles['info'] != '') {
						$lines = explode("\n", $ofiles['info']);

						foreach($lines as $l) {
							if (strpos($l, 'compat ') !== false) {
								$compat = trim(explode('=', $l)[1]);
							} elseif (strpos($l, 'requires ') !== false) {
								$requires = trim(explode('=', $l)[1]);
							} elseif (strpos($l, 'longname ') !== false) {
								$description = trim(explode('=', $l)[1]);
							} elseif (strpos($l, 'homepage ') !== false) {
								$webpage = trim(explode('=', $l)[1]);
							} elseif (strpos($l, 'author ') !== false) {
								$author = trim(explode('=', $l)[1]);
							}
						}
					}

					db_execute_prepared('REPLACE INTO plugin_available
						(plugin, description, author, webpage, tag_name, compat, requires, published_at, body, info, readme, changelog, archive)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						array(
							$plugin_name,
							$description,
							$author,
							$webpage,
							$tag_name,
							$compat,
							$requires,
							$published_at,
							'',
							base64_encode($ofiles['info']),
							base64_encode($ofiles['readme']),
							base64_encode($ofiles['changelog']),
							base64_encode($ofiles['archive'])
						)
					);

					$pend = microtime(true);

					cacti_log(sprintf('UPDATED: Plugin:\'%s\', Tag/Release:\'%s\' Updated in %0.2f seconds.', $plugin_name, 'develop', $pend - $pstart), false, 'PLUGIN');
				}
			}
		}
	}

	$end = microtime(true);

	$total_plugins   = cacti_sizeof($avail_plugins);
	$updated_plugins = $updated;

	if (cacti_sizeof($avail_plugins)) {
		raise_message('plugins_fetched', __('There were \'%s\' Plugins found at The Cacti Groups GitHub site and \'%s\' Plugins Tags/Releases were retrieved and updated in %0.2f seconds.', $total_plugins, $updated_plugins, $end - $start), MESSAGE_LEVEL_INFO);
	} else {
		raise_message('plugins_fetched', __('Unable to reach The Cacti Groups GitHub site.  No plugin data retrieved in %0.2f seconds.', $end-$start), MESSAGE_LEVEL_WARN);
	}

	cacti_log(sprintf('PLUGIN STATS: Time:%0.2f Plugins:%d Updated:%d', $end-$start, cacti_sizeof($avail_plugins), $updated_plugins), false, 'SYSTEM');

	return $avail_plugins;
}

function plugin_make_github_request($url, $type = 'json') {
	$pat  = read_config_option('github_access_token');

	$use_pat = false;
	if ($pat != '') {
		$use_pat = true;
	}

	$ch = curl_init();

	if ($ch) {
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'CactiServer ' . CACTI_VERSION);

		$headers  = array();
		$header[] = 'X-GitHub-Api-Version: 2022-11-28';

		if ($type == 'json') {
			$headers[] = 'Content-Type: application/json';
		} elseif ($type == 'file') {
			$file = sys_get_temp_dir() . '/curlfile.output.' . rand() . '.tgz';

			$fh = fopen($file, 'w');

			curl_setopt($ch, CURLOPT_FILE, $fh);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		}

		if ($use_pat) {
			$headers[] = "Authorization: Bearer $pat";
		}

		if (cacti_sizeof($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$data  = curl_exec($ch);
		$info  = curl_getinfo($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);

		curl_close($ch);

		if ($info['http_code'] == 403 || $info['http_code'] == 429) {
			$json_data = json_decode($data, true);
			raise_message('rate_limited', $json_data['message'], MESSAGE_LEVEL_ERROR);

			return false;
		}

		if ($errno == 0) {
			if ($type == 'json') {
				return json_decode($data, true);
			} elseif ($type == 'raw') {
				return $data;
			} elseif ($type == 'file') {
				fclose($fh);

				$data = file_get_contents($file);

				unlink($file);

				return $data;
			}
		} else {
			raise_message('curl_error', "Curl Experienced an error with url:$url, error:$error", MESSAGE_LEVEL_ERROR);

			return false;
		}
	}
}
