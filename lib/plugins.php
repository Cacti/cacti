<?php

function do_hook ($name) {
	$data = func_get_args();
	$data = api_plugin_hook ($name, $data);
	return $data;
}

function do_hook_function($name,$parm=NULL) {
	return api_plugin_hook_function ($name, $parm);
}

function api_user_realm_auth ($filename = '') {
	return api_plugin_user_realm_auth ($filename);
}

/**
 * This function executes a hook.
 * @param string $name Name of hook to fire
 * @return mixed $data
 */
function api_plugin_hook ($name) {
	global $config, $plugin_hooks, $plugins_system;
	$data = func_get_args();
	$ret = '';
	$p = array();

	$ps_where = '';
	if (sizeof($plugins_system)) {
		foreach($plugins_system as $plugin) {
			$ps_where .= (strlen($ps_where) ? "', '":"('") . $plugin;
		}
		$ps_where .= "')";
	}

	/* order the plugin functions by system first, then followed by order */
	$result = db_fetch_assoc("SELECT 1 AS id, ph.name, ph.file, ph.function
		FROM plugin_hooks AS ph
		LEFT JOIN plugin_config AS pc
		ON pc.directory=ph.name
		WHERE ph.status = 1 AND hook = '$name'
		AND ph.name IN $ps_where
		UNION
		SELECT pc.id, ph.name, ph.file, ph.function
		FROM plugin_hooks AS ph
		LEFT JOIN plugin_config AS pc
		ON pc.directory=ph.name
		WHERE ph.status = 1 AND hook = '$name'
		AND ph.name NOT IN $ps_where
		ORDER BY id ASC", true);

	if (count($result)) {
		foreach ($result as $hdata) {
			$p[] = $hdata['name'];
			if (file_exists($config['base_path'] . '/plugins/' . $hdata['name'] . '/' . $hdata['file'])) {
				include_once($config['base_path'] . '/plugins/' . $hdata['name'] . '/' . $hdata['file']);
			}
			$function = $hdata['function'];
			if (function_exists($function)) {
				$function($data);
			}
		}
	}

	if (isset($plugin_hooks[$name]) && is_array($plugin_hooks[$name])) {
		foreach ($plugin_hooks[$name] as $pname => $function) {
			if (function_exists($function)  && !function_exists('plugin_' . $pname . '_install') && !in_array($pname, $p)) {
				$function($data);
			}
		}
	}

	/* Variable-length argument lists have a slight problem when */
	/* passing values by reference. Pity. This is a workaround.  */
	return $data;
}

function api_plugin_hook_function ($name, $parm=NULL) {
	global $config, $plugin_hooks, $plugins_system;
	$ret = $parm;
	$p = array();
	$ps_where = '';

	if (sizeof($plugins_system)) {
		foreach($plugins_system as $plugin) {
			$ps_where .= (strlen($ps_where) ? "', '":"('") . $plugin;
		}
		$ps_where .= "')";
	}

	/* order the plugin functions by system first, then followed by order */
	$result = db_fetch_assoc("SELECT 1 AS id, ph.name, ph.file, ph.function
		FROM plugin_hooks AS ph
		LEFT JOIN plugin_config AS pc
		ON pc.directory=ph.name
		WHERE ph.status = 1 AND hook = '$name'
		AND ph.name IN $ps_where
		UNION
		SELECT pc.id, ph.name, ph.file, ph.function
		FROM plugin_hooks AS ph
		LEFT JOIN plugin_config AS pc
		ON pc.directory=ph.name
		WHERE ph.status = 1 AND hook = '$name'
		AND ph.name NOT IN $ps_where
		ORDER BY id ASC", true);

	if (count($result)) {
		foreach ($result as $hdata) {
			$p[] = $hdata['name'];
			if (file_exists($config['base_path'] . '/plugins/' . $hdata['name'] . '/' . $hdata['file'])) {
				include_once($config['base_path'] . '/plugins/' . $hdata['name'] . '/' . $hdata['file']);
			}
			$function = $hdata['function'];
			if (function_exists($function)) {
				$ret = $function($ret);
			}
		}
	}

	if (isset($plugin_hooks[$name]) && is_array($plugin_hooks[$name])) {
		foreach ($plugin_hooks[$name] as $pname => $function) {
			if (function_exists($function)  && !function_exists('plugin_' . $pname . '_install') && !in_array($pname, $p)) {
				$ret = $function($ret);
			}
		}
	}

	/* Variable-length argument lists have a slight problem when */
	/* passing values by reference. Pity. This is a workaround.  */
	return $ret;
}

function api_plugin_db_table_create ($plugin, $table, $data) {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$result = db_fetch_assoc("show tables from `" . $database_default . "`") or die (mysql_error());
	$tables = array();
	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}
	if (!in_array($table, $tables)) {
		$c = 0;
		$sql = 'CREATE TABLE `' . $table . "` (\n";
		foreach ($data['columns'] as $column) {
			if (isset($column['name'])) {
				if ($c > 0)
					$sql .= ",\n";
				$sql .= '`' . $column['name'] . '`';
				if (isset($column['type']))
					$sql .= ' ' . $column['type'];
				if (isset($column['unsigned']))
					$sql .= ' unsigned';
				if (isset($column['NULL']) && $column['NULL'] == false)
					$sql .= ' NOT NULL';
				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default']))
					$sql .= ' default NULL';
				if (isset($column['default']))
					$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
				if (isset($column['auto_increment']))
					$sql .= ' auto_increment';
				$c++;
			}
		}

		if (isset($data['primary'])) {
			$sql .= ",\n PRIMARY KEY (`" . $data['primary'] . '`)';
		}

		if (isset($data['keys']) && sizeof($data['keys'])) {
		foreach ($data['keys'] as $key) {
			if (isset($key['name'])) {
				$sql .= ",\n KEY `" . $key['name'] . '` (`' . $key['columns'] . '`)';
			}
		}
		}
		$sql .= ') ENGINE = ' . $data['type'];

		if (isset($data['comment'])) {
			$sql .= " COMMENT = '" . $data['comment'] . "'";
		}
		if (db_execute($sql)) {
			db_execute("INSERT INTO plugin_db_changes (plugin, `table`, method) VALUES ('$plugin', '$table', 'create')");
		}
	}
}

function api_plugin_db_changes_remove ($plugin) {
	// Example: api_plugin_db_changes_remove ('thold');

	$tables = db_fetch_assoc("SELECT `table` FROM plugin_db_changes WHERE plugin = '$plugin' AND method ='create'", false);
	if (count($tables)) {
		foreach ($tables as $table) {
			db_execute("DROP TABLE IF EXISTS `" . $table['table'] . "`;");
		}
		db_execute("DELETE FROM plugin_db_changes where plugin = '$plugin' AND method ='create'", false);
	}
	$columns = db_fetch_assoc("SELECT `table`, `column` FROM plugin_db_changes WHERE plugin = '$plugin' AND method ='addcolumn'", false);
	if (count($columns)) {
		foreach ($columns as $column) {
			db_execute('ALTER TABLE `' . $column['table'] . '` DROP `' . $column['column'] . '`');
		}
		db_execute("DELETE FROM plugin_db_changes where plugin = '$plugin' AND method = 'addcolumn'", false);
	}
}

function api_plugin_db_add_column ($plugin, $table, $column) {
	// Example: api_plugin_db_add_column ('thold', 'plugin_config', array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));

	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	$result = db_fetch_assoc('show columns from `' . $table . '`') or die (mysql_error());
	$columns = array();
	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$columns[] = $t;
		}
	}
	if (isset($column['name']) && !in_array($column['name'], $columns)) {
		$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';
		if (isset($column['type']))
			$sql .= ' ' . $column['type'];
		if (isset($column['unsigned']))
			$sql .= ' unsigned';
		if (isset($column['NULL']) && $column['NULL'] == false)
			$sql .= ' NOT NULL';
		if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default']))
			$sql .= ' default NULL';
		if (isset($column['default']))
			$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
		if (isset($column['auto_increment']))
			$sql .= ' auto_increment';
		if (isset($column['after']))
			$sql .= ' AFTER ' . $column['after'];

		if (db_execute($sql)) {
			db_execute("INSERT INTO plugin_db_changes (plugin, `table`, `column`, `method`) VALUES ('$plugin', '$table', '" . $column['name'] . "', 'addcolumn')");
		}
	}
}

function api_plugin_install ($plugin) {
	global $config;
	include_once($config['base_path'] . "/plugins/$plugin/setup.php");

	$exists = db_fetch_assoc("SELECT id FROM plugin_config WHERE directory = '$plugin'", false);
	if (sizeof($exists)) {
		db_execute("DELETE FROM plugin_config WHERE directory = '$plugin'");
	}

	$name = $author = $webpage = $version = '';
	$function = 'plugin_' . $plugin . '_version';
	if (function_exists($function)){
		$info = $function();
		$name = $info['longname'];
		if (isset($info['homepage'])) {
			$webpage = $info['homepage'];
		}elseif (isset($info['webpage'])) {
			$webpage = $info['webpage'];
		}else{
			$webpage = "Not Stated";
		}
		$author = $info['author'];
		$version = $info['version'];
	}

	db_execute("INSERT INTO plugin_config (directory, name, author, webpage, version) VALUES ('$plugin', '$name', '$author', '$webpage', '$version')");

	$function = 'plugin_' . $plugin . '_install';
	if (function_exists($function)){
		$function();
		$ready = api_plugin_check_config ($plugin);
		if ($ready) {
			// Set the plugin as "disabled" so it can go live
			db_execute("UPDATE plugin_config SET status = 4 WHERE directory = '$plugin'");
		} else {
			// Set the plugin as "needs configuration"
			db_execute("UPDATE plugin_config SET status = 2 WHERE directory = '$plugin'");
		}
	}
}

function api_plugin_uninstall ($plugin) {
	global $config;
	include_once($config['base_path'] . "/plugins/$plugin/setup.php");
	// Run the Plugin's Uninstall Function first
	$function = 'plugin_' . $plugin . '_uninstall';
	if (function_exists($function)) {
		$function();
	}
	api_plugin_remove_hooks ($plugin);
	api_plugin_remove_realms ($plugin);
	db_execute("DELETE FROM plugin_config WHERE directory = '$plugin'");
	api_plugin_db_changes_remove ($plugin);
}

function api_plugin_check_config ($plugin) {
	global $config;
	include_once($config['base_path'] . "/plugins/$plugin/setup.php");
	$function = 'plugin_' . $plugin . '_check_config';
	if (function_exists($function)) {
		return $function();
	}
	return TRUE;
}

function api_plugin_enable ($plugin) {
	$ready = api_plugin_check_config ($plugin);
	if ($ready) {
		api_plugin_enable_hooks ($plugin);
		db_execute("UPDATE plugin_config SET status = 1 WHERE directory = '$plugin'");
	}
}

function api_plugin_is_enabled ($plugin) {
	$status = db_fetch_cell("SELECT status FROM plugin_config WHERE directory = '$plugin'", false);
	if ($status == '1')
		return true;
	return false;
}

function api_plugin_disable ($plugin) {
	api_plugin_disable_hooks ($plugin);
	db_execute("UPDATE plugin_config SET status = 4 WHERE directory = '$plugin'");
}

function api_plugin_moveup($plugin) {
	global $plugins_system;

	$sql_where = "";
	if (sizeof($plugins_system)) {
		foreach($plugins_system as $s) {
			$sql_where .= (strlen($sql_where) ? " AND ":"(") . " directory!='$s'";
		}

		$sql_where .= ")";
	}

	$id = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='$plugin'" . (strlen($sql_where) ? " AND " . $sql_where:""));
	$temp_id = db_fetch_cell("SELECT MAX(id) FROM plugin_config")+1;
	$prior_id = db_fetch_cell("SELECT MAX(id) FROM plugin_config WHERE id<$id" . (strlen($sql_where) ? " AND " . $sql_where:""));

	/* update the above plugin to the prior temp id */
	db_execute("UPDATE plugin_config SET id=$temp_id WHERE id=$prior_id");
	db_execute("UPDATE plugin_config SET id=$prior_id WHERE id=$id");
	db_execute("UPDATE plugin_config SET id=$id WHERE id=$temp_id");
}

function api_plugin_movedown($plugin) {
	global $plugins_system;

	$sql_where = "";
	if (sizeof($plugins_system)) {
		foreach($plugins_system as $s) {
			$sql_where .= (strlen($sql_where) ? " AND ":"(") . " directory!='$s'";
		}

		$sql_where .= ")";
	}

	$id = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='$plugin'" . (strlen($sql_where) ? " AND " . $sql_where:""));
	$temp_id = db_fetch_cell("SELECT MAX(id) FROM plugin_config")+1;
	$next_id = db_fetch_cell("SELECT MIN(id) FROM plugin_config WHERE id>$id" . (strlen($sql_where) ? " AND " . $sql_where:""));

	/* update the above plugin to the prior temp id */
	db_execute("UPDATE plugin_config SET id=$temp_id WHERE id=$next_id");
	db_execute("UPDATE plugin_config SET id=$next_id WHERE id=$id");
	db_execute("UPDATE plugin_config SET id=$id WHERE id=$temp_id");
}

function api_plugin_register_hook ($plugin, $hook, $function, $file) {
	$exists = db_fetch_assoc("SELECT id FROM plugin_hooks WHERE name = '$plugin' AND hook = '$hook'", false);
	if (!count($exists)) {
		$settings = array('config_settings', 'config_arrays', 'config_form');
		if (!in_array($hook, $settings)) {
			db_execute("INSERT INTO plugin_hooks (name, hook, function, file) VALUES ('$plugin', '$hook', '$function', '$file')");
		} else {
			db_execute("INSERT INTO plugin_hooks (name, hook, function, file, status) VALUES ('$plugin', '$hook', '$function', '$file', 1)");
		}
	}
}

function api_plugin_remove_hooks ($plugin) {
	db_execute("DELETE FROM plugin_hooks WHERE name = '$plugin'");
}

function api_plugin_enable_hooks ($plugin) {
	db_execute("UPDATE plugin_hooks SET status = 1 WHERE name = '$plugin'");
}

function api_plugin_disable_hooks ($plugin) {
	db_execute("UPDATE plugin_hooks SET status = 0 WHERE name = '$plugin' AND hook != 'config_settings' AND hook != 'config_arrays' AND hook != 'config_form'");
}

function api_plugin_register_realm ($plugin, $file, $display, $admin = false) {
	$exists = db_fetch_assoc("SELECT id FROM plugin_realms WHERE plugin = '$plugin' AND file = '$file'", false);
	if (!count($exists)) {
		db_execute("INSERT INTO plugin_realms (plugin, file, display) VALUES ('$plugin', '$file', '$display')");
		if ($admin) {
			$realm_id = db_fetch_assoc("SELECT id FROM plugin_realms WHERE plugin = '$plugin' AND file = '$file'", false);
			$realm_id = $realm_id[0]['id'] + 100;
			$user_id = db_fetch_assoc("SELECT id FROM user_auth WHERE username = 'admin'", false);
			if (count($user_id)) {
				$user_id = $user_id[0]['id'];
				$exists = db_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE user_id = $user_id and realm_id = $realm_id", false);
				if (!count($exists)) {
					db_execute("INSERT INTO user_auth_realm (user_id, realm_id) VALUES ($user_id, $realm_id)");
				}
			}
		}
	}
}

function api_plugin_remove_realms ($plugin) {
	$realms = db_fetch_assoc("SELECT id FROM plugin_realms WHERE plugin = '$plugin'", false);
	foreach ($realms as $realm) {
		$id = $realm['id'] + 100;
		db_execute("DELETE FROM user_auth_realm WHERE realm_id = '$id'");
	}
	db_execute("DELETE FROM plugin_realms WHERE plugin = '$plugin'");
}

function api_plugin_load_realms () {
	global $user_auth_realms, $user_auth_realm_filenames;
	$plugin_realms = db_fetch_assoc("SELECT * FROM plugin_realms ORDER BY plugin, display", false);
	if (count($plugin_realms)) {
		foreach ($plugin_realms as $plugin_realm) {
			$plugin_files = explode(',', $plugin_realm['file']);
			foreach($plugin_files as $plugin_file) {
				$user_auth_realm_filenames[$plugin_file] = $plugin_realm['id'] + 100;
			}
			$user_auth_realms[$plugin_realm['id'] + 100] = $plugin_realm['display'];
		}
	}
}

function api_plugin_user_realm_auth ($filename = '') {
	global $user_realms, $user_auth_realms, $user_auth_realm_filenames;
	/* list all realms that this user has access to */
	if (!isset($user_realms)) {
		if (isset($_SESSION["sess_user_id"]) && (read_config_option('global_auth') == 'on' || read_config_option('auth_method') != 0)) {
			$user_realms = db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . $_SESSION["sess_user_id"], false);
			$user_realms = array_rekey($user_realms, "realm_id", "realm_id");
		}else{
			$user_realms = $user_auth_realms;
		}
	}
	if ($filename != '' && isset($user_auth_realm_filenames[basename($filename)])) {
		if (isset($user_realms[$user_auth_realm_filenames[basename($filename)]]))
			return TRUE;
	}
	return FALSE;
}

function plugin_config_arrays () {
	global $menu;
	$menu['Configuration']['plugins.php'] = 'Plugin Management';
	api_plugin_load_realms ();
}

function plugin_draw_navigation_text ($nav) {
	$nav["plugins.php:"] = array("title" => "Plugin Management", "mapping" => "index.php:", "url" => "plugins.php", "level" => "1");
	return $nav;
}
