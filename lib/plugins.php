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
			// Security check
			if (strpos($hdata['file'], '..') !== false) {
				cacti_log("ERROR: Attempted inclusion of not plugin file $plugin_file from $plugin_name with the hook name $name", false, 'SECURITY');
				continue;
			}

			$plugin_name = $hdata['name'];

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
			$parts = explode(' ', trim($info['info']['requires']));

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
			if (!api_plugin_minimum_version($dependency, $version)) {
				$message .= __('%s Version %s or above is required for %s. ', ucwords($dependency), $version, ucwords($plugin));

				$proceed = false;
			} elseif (!api_plugin_installed($dependency)) {
				$message .= __('%s is required for %s, and it is not installed. ', ucwords($dependency), ucwords($plugin));

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
		(directory, name, author, webpage, version)
		VALUES (?, ?, ?, ?, ?)',
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
		} else {
			// Set the plugin as "needs configuration"
			db_execute_prepared('UPDATE plugin_config
				SET status = 2
				WHERE directory = ?',
				array($plugin));
		}
	} else {
		raise_message('install_error', __('The Plugin in the directory \'%s\' does not include an install function \'%s()\'.  This function must exist for the plugin to be installed.', $plugin, $function), MESSAGE_LEVEL_ERROR);
		return false;
	}

	api_plugin_replicate_config();
}

function api_plugin_uninstall_integrated() {
	global $config, $plugin_hooks, $plugins_integrated;

	foreach ($plugins_integrated as $plugin) {
		api_plugin_uninstall($plugin, false);
	}
}

function api_plugin_uninstall($plugin, $tables = true) {
	global $config;

	if (file_exists(CACTI_PATH_PLUGINS . "/$plugin/setup.php")) {
		include_once(CACTI_PATH_PLUGINS . "/$plugin/setup.php");

		// Run the Plugin's Uninstall Function first
		$function = "plugin_{$plugin}_uninstall";

		if (function_exists($function)) {
			$function();
		}
	}

	api_plugin_remove_hooks($plugin);
	api_plugin_remove_realms($plugin);

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

	api_plugin_replicate_config();
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
		SET status = 4
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
		if (!isset($info['compat']) || cacti_version_compare(CACTI_VERSION, $info['compat'], '<')) {
			return array('compat' => false, 'requires' => __('Requires: Cacti >= %s', $info['compat']));
		}
	} else {
		return array('compat' => false, 'requires' => __('Legacy Plugin'));
	}

	return array('compat' => true, 'requires' => __('Requires: Cacti >= %s', $info['compat']));
}

function plugin_load_info_defaults($file, $info, $defaults = array()) {
	$result = $info;
	$dir    = @basename(@dirname($file));

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

	if (strstr($dir, ' ') !== false) {
		$result['status'] = -3;
	} elseif (strtolower($dir) != strtolower($result['name'])) {
		$result['status'] = -2;
	} elseif (!isset($result['compat']) || cacti_version_compare(CACTI_VERSION, $result['compat'], '<')) {
		$result['status'] = -1;
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
