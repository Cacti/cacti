#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$table = '';
$create = true;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--table':
				$table = trim(sql_clean($value));
				break;
			case '--update':
				$create = false;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:

		}
	}
}

if ($table == '') {
	echo "ERROR: You must provide a table name\n";
	display_help();
	exit;
} else {
	echo sqltable_to_php($table, $create);
}

function sqltable_to_php ($table, $create) {
	global $config, $database_default;

	include_once($config['library_path'] . '/database.php');

	$result = db_fetch_assoc('SHOW tables FROM `' . $database_default . '`');

	$tables = array();
	$text   = '';

	if (sizeof($result)) {
		foreach($result as $index => $arr) {
			foreach ($arr as $t) {
				$tables[] = $t;
			}
		}
	} else {
		echo "ERROR: Obtaining list of tables from $database_default\n";
		exit;
	}

	if (in_array($table, $tables)) {
		$result = db_fetch_assoc("SHOW FULL columns FROM $table");

		$cols   = array();
		$pri    = array();
		$keys   = array();
		$text   = "\n\$data = array();\n";

		if (sizeof($result)) {
			foreach ($result as $r) {
				$text .= "\$data['columns'][] = array(";
				$text .= "'name' => '" . $r['Field'] . "'";

				if (strpos(strtolower($r['Type']), ' unsigned') !== FALSE) {
					$r['Type'] = str_ireplace(' unsigned', '', $r['Type']);
					$text .= ", 'unsigned' => true";
				}

				$text .= ", 'type' => \"" . $r['Type'] . "\"";
				$text .= ", 'NULL' => " . (strtolower($r['Null']) == 'no' ? 'false' : 'true');

				if (trim($r['Default']) != '') {
					$text .= ", 'default' => '" . $r['Default'] . "'";
				}

				if (trim($r['Extra']) != '') {
					if (strtolower($r['Extra']) == 'on update current_timestamp') {
						$text .= ", 'on_update' => 'CURRENT_TIMESTAMP'";
					}

					if (strtolower($r['Extra']) == 'auto_increment') {
						$text .= ", 'auto_increment' => true";
					}
				}
				if (trim($r['Comment']) != '') {
					$text .= ", 'comment' => '" . $r['Comment'] . "'";
				}

				$text .= ");\n";
			}
		} else {
			echo "ERROR: Obtaining list of columns from $table\n";
			exit;
		}

		$result = db_fetch_assoc("SHOW INDEX FROM $table");
		if (sizeof($result)) {
			foreach ($result as $r) {
				if ($r['Key_name'] == 'PRIMARY') {
					$pri[] = $r['Column_name'];
				} else {
					$keys[$r['Key_name']][$r['Seq_in_index']] = $r['Column_name'];
				}
			}

			if (!empty($pri)) {
				$text .= "\$data['primary'] = array('" . implode("','", $pri) . "');\n";
			}

			if (!empty($keys)) {
				foreach ($keys as $n => $k) {
					$text .= "\$data['keys'][] = array('name' => '$n', 'columns' => array('" . implode("','", $k) . "'));\n";
				}
			}
		} else {
			//echo "ERROR: Obtaining list of indexes from $table\n";
			//exit;
		}

		$result = db_fetch_row("SELECT ENGINE, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$table'");
		if (sizeof($result)) {
			$text .= "\$data['type'] = '" . $result['ENGINE'] . "';\n";
			$text .= "\$data['comment'] = '" . $result['TABLE_COMMENT'] . "';\n";
			if ($create) {
				$text .= "db_table_create ('$table', \$data);\n";
			} else {
				$text .= "db_update_table ('$table', \$data, false);\n";
			}
		} else {
			echo "ERROR: Unable to get tables details from Information Schema\n";
			exit;
		}
	}

	return $text;
}

function sql_clean($text) {
	$text = str_replace(array("\\", '/', "'", '"', '|'), '', $text);
	return $text;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
    display_version();

    echo "\nusage: sqltable_to_php.php --table=table_name [--update]\n\n";
    echo "A simple developers utility to create a save schema for a newly created or\n";
	echo "modified database table.  These save schema's can be placed into a plugins\n";
	echo "setup.php file in order to create the tables inside of a plugin as a part of\n";
	echo "it's install function.\n\n";
	echo "Required:\n";
	echo "--table=table_name - The table that you want exportred\n\n";
	echo "Optional:\n";
	echo "--update           - The utility provides create syntax.  If the update flag is\n";
	echo "                     specified, the utility will provide update syntax\n\n";
}

