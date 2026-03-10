#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$table  = '';
$plugin = '';
$create = true;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--table':
				$table = trim(sql_clean($value));

				break;
			case '--plugin':
				$plugin = trim(sql_clean($value));

				break;
			case '--update':
				$create = false;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

			default:
		}
	}
}

if ($table == '') {
	print 'ERROR: You must provide a table name' . PHP_EOL;
	display_help();

	exit(1);
} else {
	print sqltable_to_php($table, $create, $plugin);
}

function sqltable_to_php(string $table, bool $create, string $plugin = '') : string {
	global $database_default;

	include_once(CACTI_PATH_LIBRARY . '/database.php');

	$result = db_fetch_assoc('SHOW tables FROM `' . $database_default . '`');

	$tables = [];
	$text   = '';

	if (cacti_sizeof($result)) {
		foreach ($result as $index => $arr) {
			foreach ($arr as $t) {
				$tables[] = $t;
			}
		}
	} else {
		print "ERROR: Obtaining list of tables from $database_default" . PHP_EOL;

		exit;
	}

	if (in_array($table, $tables, true)) {
		$result = db_fetch_assoc("SHOW FULL columns FROM $table");

		$cols   = [];
		$pri    = [];
		$keys   = [];
		$text   = PHP_EOL . '$data = array();' . PHP_EOL;

		if (cacti_sizeof($result)) {
			foreach ($result as $r) {
				$text .= "\$data['columns'][] = array(";
				$text .= "'name' => '" . $r['Field'] . "'";

				if (str_contains(cacti_strtolower($r['Type']), ' unsigned')) {
					$r['Type'] = str_ireplace(' unsigned', '', $r['Type']);
					$text .= ", 'unsigned' => true";
				}

				$text .= ", 'type' => " . db_qstr($r['Type']);
				$text .= ", 'NULL' => " . (cacti_strtolower($r['Null']) == 'no' ? 'false' : 'true');

				if ($r['Default'] != '' && trim($r['Default']) != '') {
					if ($r['Default'] == "''") {
						$r['Default'] = '';
					} elseif (str_contains($r['Default'], 'current_timestamp()')) {
						$r['Default'] = str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $r['Default']);
					}

					$text .= ", 'default' => '" . $r['Default'] . "'";
				} elseif (stripos($r['Type'], 'char') !== false) {
					$text .= ", 'default' => ''";
				}

				if (trim($r['Extra']) != '') {
					if (cacti_strtolower($r['Extra']) == 'on update current_timestamp') {
						$text .= ", 'on_update' => 'CURRENT_TIMESTAMP'";
					}

					if (cacti_strtolower($r['Extra']) == 'auto_increment') {
						$text .= ", 'auto_increment' => true";
					}
				}

				if (trim($r['Comment']) != '') {
					$text .= ", 'comment' => '" . $r['Comment'] . "'";
				}

				$text .= ');' . PHP_EOL;
			}
		} else {
			print "ERROR: Obtaining list of columns from $table" . PHP_EOL;

			exit;
		}

		$result = db_fetch_assoc("SHOW INDEX FROM $table");

		if (cacti_sizeof($result)) {
			$unique_keys = [];

			foreach ($result as $r) {
				if ($r['Key_name'] == 'PRIMARY') {
					$pri[] = $r['Column_name'];
				} else {
					$keys[$r['Key_name']][$r['Seq_in_index']] = $r['Column_name'];

					if ($r['Non_unique'] == 0) {
						$unique_keys[$r['Key_name']] = $r['Key_name'];
					}
				}
			}

			if (!empty($pri)) {
				if ($plugin != '' || $create) {
					$text .= "\$data['primary'] = '" . implode('`,`', $pri) . "';" . PHP_EOL;
				} else {
					$text .= "\$data['primary'] = array('" . implode("','", $pri) . "');" . PHP_EOL;
				}
			}

			if (!empty($keys)) {
				foreach ($keys as $n => $k) {
					if ($plugin != '') {
						$text .= "\$data['keys'][] = array('name' => '$n', " . (isset($unique_keys[$n]) ? "'unique' => true, " : '') . "'columns' => '" . implode('`,`', $k) . "');" . PHP_EOL;
					} else {
						$text .= "\$data['keys'][] = array('name' => '$n', " . (isset($unique_keys[$n]) ? "'unique' => true, " : '') . "'columns' => array('" . implode("','", $k) . "'));" . PHP_EOL;
					}
				}
			}
		} else {
			// print "ERROR: Obtaining list of indexes from $table" . PHP_EOL;
			// exit;
		}

		$result = db_fetch_row_prepared('SELECT ENGINE, TABLE_COMMENT, ROW_FORMAT, CHARACTER_SET_NAME
			FROM information_schema.TABLES tbl JOIN information_schema.COLLATIONS coll ON tbl.TABLE_COLLATION=coll.COLLATION_NAME
			WHERE TABLE_SCHEMA = SCHEMA()
			AND TABLE_NAME = ?',
			[$table]);

		if (cacti_sizeof($result)) {
			$text .= "\$data['type'] = '" . $result['ENGINE'] . "';" . PHP_EOL;
			$text .= "\$data['charset'] = '" . $result['CHARACTER_SET_NAME'] . "';" . PHP_EOL;

			if (!empty($result['TABLE_COMMENT'])) {
				$text .= "\$data['comment'] = '" . $result['TABLE_COMMENT'] . "';" . PHP_EOL;
			}
			$text .= "\$data['row_format'] = '" . $result['ROW_FORMAT'] . "';" . PHP_EOL;

			if ($create) {
				if ($plugin != '') {
					$text .= "api_plugin_db_table_create ('$plugin', '$table', \$data);" . PHP_EOL;
				} else {
					$text .= "db_table_create ('$table', \$data);" . PHP_EOL;
				}
			} else {
				$text .= "db_update_table ('$table', \$data, false);" . PHP_EOL;
			}
		} else {
			print 'ERROR: Unable to get tables details from Information Schema' . PHP_EOL;

			exit;
		}
	}

	return $text;
}

function sql_clean(string $text) : string {
	$text = str_replace(['\\', '/', "'", '"', '|'], '', $text);

	return $text;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti SQL to PHP Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: sqltable_to_php.php --table=table_name [--plugin=name] [--update]' . PHP_EOL . PHP_EOL;

	print 'A simple developers utility to create a save schema for a newly created or' . PHP_EOL;
	print 'modified database table in a format that is consumable by Cacti.' . PHP_EOL . PHP_EOL;

	print 'These save schemas can be placed into a plugin\'s setup.php file in order' . PHP_EOL;
	print 'to create the tables inside of a plugin as a part of its install function.' . PHP_EOL;
	print 'The plugin parameter is optional, but if you want the table(s) automatically' . PHP_EOL;
	print 'removed from Cacti when uninstalling the plugin, specify its name.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '--table=table_name - The table that you want exported' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '--plugin=name      - The name of the plugin that will manage tables' . PHP_EOL;
	print '--update           - The utility provides create syntax.  If the update flag is' . PHP_EOL;
	print '                     specified, the utility will provide update syntax' . PHP_EOL . PHP_EOL;
}
