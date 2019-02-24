#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = array(
		'create',
		'load',
		'report',
		'repair',
		'alters',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		switch($arg) {
		case 'create':
			create_tables();
			break;

		case 'load':
			load_audit_database();

			break;
		case 'report':
			report_audit_results();

			break;
		case 'repair':
			repair_database();

			break;
		case 'alters':
			repair_database(false);

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

	exit(0);
} else {
	display_help();
	exit(1);
}

function repair_database($run = true) {
	$alters = report_audit_results(false);

	if (cacti_sizeof($alters)) {
		foreach($alters as $alter) {
			print 'Executing : ' . trim($alter, ';');
			if ($run) {
				print (db_execute($alter) ? ' - Success':' - Failed') . PHP_EOL;
			} else {
				print ' - Dry Run' . PHP_EOL;
			}
		}
	}
}

function report_audit_results($output = true) {
	global $database_default;
	$db_name = 'Tables_in_' . $database_default;

	create_tables();

	$tables = db_fetch_assoc('SHOW TABLES');

	$alters  = array();
	$ialters = array();

	$cols = array(
		'table_type'    => 'Type',
		'table_null'    => 'Null',
		'table_key'     => 'Key',
		'table_default' => 'Default',
		'table_extra'   => 'Extra'
	);

	$idxs = array(
		'idx_non_unique'   => 'Non_unique',
		'idx_key_name'     => 'Key_name',
		'idx_seq_in_index' => 'Seq_in_index',
		'idx_column_name'  => 'Column_name',
		'idx_packed'       => 'Packed',
		'idx_comment'      => 'Comment'
	);

	if (cacti_sizeof($tables)) {
		foreach($tables as $table) {
			$alter_cmds = array();
			$table_name = $table[$db_name];
			$columns = db_fetch_assoc('SHOW COLUMNS IN ' . $table_name);

			$status  = db_fetch_row('SHOW TABLE STATUS LIKE "' . $table_name . '"');
			if ($status['Collation'] == 'utf8mb4_unicode_ci' || $status['Collation'] == 'utf8_general_ci') {
				$text = 'mediumtext';
			} else {
				$text = 'text';
			}

			if ($output) {
				print '---------------------------------------------------------------------------------------------' . PHP_EOL;
				printf('Checking Table: %-40s', '\'' . $table_name . '\'');
			}

			$table_exists = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM table_columns
				WHERE table_name = ?',
				array($table_name));

			if (!$table_exists) {
				$plugin_table = db_fetch_row_prepared('SELECT *
					FROM plugin_db_changes
					WHERE `table` = ?
					AND method = ?',
					array($table_name, 'create'));

				if (!cacti_sizeof($plugin_table)) {
					if ($output) {
						print ' - Does not Exist.  Possible Plugin' . PHP_EOL;
						continue;
					}
				}

				if ($output) {
					print ' - Plugin Detected' . PHP_EOL;
					continue;
				}
			}

			/* Column scanning comes in two parts.  In the first part, we
			 * scan the columns in the current database to the saved schema
			 * In the second pass, we look for the columns from the saved
			 * schema to look for missing ones.
			 */

			$i = 1;
			$errors = 0;
			$warnings = 0;

			if (cacti_sizeof($columns)) {
				foreach($columns as $c) {
					$alter_cmd    = '';
					$sequence_off = false;

					$dbc = db_fetch_row_prepared('SELECT *
						FROM table_columns
						WHERE table_name = ?
						AND table_field = ?',
						array($table_name, $c['Field']));

					if (!cacti_sizeof($dbc)) {
						$plugin_column = db_fetch_row_prepared('SELECT *
							FROM plugin_db_changes
							WHERE `table` = ?
							AND `column` = ?
							AND method = ?',
							array($table_name, $c['Field'], 'addcolumn'));

						if (!cacti_sizeof($plugin_column)) {
							if ($output) {
								print PHP_EOL . 'WARNING Col: \'' . $c['Field'] . '\', does not exist in default Cacti.  Plugin possible';
							}

							$warnings++;
						}
					} else {
						foreach($cols as $dbcol => $col) {
							if ($col == 'Type' && $dbc[$dbcol] == 'text') {
								if ($text == 'mediumtext') {
									$dbc[$dbcol] = $text;
								}
							}

							if ($c[$col] != $dbc[$dbcol] && $text != 'mediumtext') {
								if ($output) {
									if ($col != 'Key') {
										print PHP_EOL . 'ERROR Col: \'' . $c['Field'] . '\', Attribute \'' . $col . '\' invalid. Should be: \'' . $dbc[$dbcol] . '\', Is: \'' . $c[$col] . '\'';
									}
								}

								// This is an index error
								if ($col != 'Key') {
									$errors++;
								}

								$alter_cmd = make_column_alter($table_name, $dbc);
							}
						}
					}

					$i++;

					if ($alter_cmd != '') {
						$alter_cmds[] = $alter_cmd . ';';
					}
				}
			}

			if (isset($alter_cmds) && cacti_sizeof($alter_cmds)) {
				$alters = array_merge($alters, $alter_cmds);
			}

			/* In this pass, we will gather the default schema and look for
			 * missing information.
			 */
			$db_columns = db_fetch_assoc_prepared('SELECT *
				FROM table_columns
				WHERE table_name = ?',
				array($table_name));

			if (cacti_sizeof($db_columns)) {
				foreach($db_columns as $c) {
					if (!db_column_exists($table_name, $c['table_field'])) {
						if ($output) {
							print PHP_EOL . 'WARNING Col: \'' . $c['table_field'] . '\' is missing from \'' . $table_name . '\'';
						}

						$warnings++;
					}
				}
			}

			/* Index scanning comes in two parts.  In the first part, we
			 * scan the indexes in the current database to the saved schema
			 * In the second pass, we look for the indexes from the saved
			 * schema to look for missing ones.
			 */

			$indexes = db_fetch_assoc('SHOW INDEXES IN ' . $table_name);

			if (cacti_sizeof($indexes)) {
				foreach($indexes as $i) {
					$dbc = db_fetch_row_prepared('SELECT *
						FROM table_indexes
						WHERE idx_table_name = ?
						AND idx_key_name = ?
						AND idx_seq_in_index = ?
						AND idx_column_name = ?',
						array($i['Table'], $i['Key_name'], $i['Seq_in_index'], $i['Column_name']));

					if (!cacti_sizeof($dbc)) {
						if ($output) {
							print PHP_EOL . 'WARNING Index: \'' . $i['Key_name'] . '\', does not exist in default Cacti.  Plugin possible';
						}

						$warnings++;
					} else {
						foreach($idxs as $dbidx => $idx) {
							if ($i[$idx] != $dbc[$dbidx]) {
								if ($output) {
									print PHP_EOL . 'ERROR Index: \'' . $i['Key_name'] . '\', Attribute \'' . $idx . '\' invalid. Should be: \'' . $dbc[$dbidx] . '\', Is: \'' . $i[$idx] . '\'';
								}

								$alters = array_merge($alters, make_index_alter($table_name, $i['Key_name']));
								$errors++;
							}
						}
					}
				}
			}

			$db_indexes = db_fetch_assoc_prepared('SELECT *
				FROM table_indexes
				WHERE idx_table_name = ?',
				array($table_name));

			if (cacti_sizeof($db_indexes)) {
				foreach($db_indexes as $i) {
					if (!db_index_exists($table_name, $i['idx_key_name'])) {
						if ($output) {
							print PHP_EOL . 'ERROR Index: \'' . $i['idx_key_name'] . '\', is missing from \'' . $table_name . '\'';;
						}

						$alters = array_merge($alters, make_index_alter($table_name, $i['idx_key_name']));
						$errors++;
					}
				}
			}

			if ($output) {
				if ($errors || $warnings) {
					print PHP_EOL . PHP_EOL . 'ERRORS: ' . $errors . ', WARNINGS: ' . $warnings . PHP_EOL;
				} else {
					print ' - Clean' . PHP_EOL;
				}
			}
		}
	}

	if (cacti_sizeof($ialters)) {
		$alters = array_merge($alters, $ialters);
	}

	return $alters;
}

function make_column_alter($table, $dbc) {
	$alter_cmd = 'ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $dbc['table_field'] . '` ' . $dbc['table_type'] . ($dbc['table_null'] == 'NO' ? ' NOT NULL':'');

	if ($dbc['table_null'] == 'YES' && $dbc['table_default'] == 'NULL') {
		$alter_cmd .= ' DEFAULT NULL';
	} elseif ($dbc['table_default'] !== 'NULL') {
		if ($dbc['table_default'] == 'CURRENT_TIMESTAMP') {
			$alter_cmd .= ' DEFAULT CURRENT_TIMESTAMP';
		} elseif ($dbc['table_extra'] != 'auto_increment') {
			$alter_cmd .= ' DEFAULT \'' . $dbc['table_default'] . '\'';
		}
	}

	if ($dbc['table_extra'] != '') {
		$alter_cmd .= ' ' . $dbc['table_extra'];
	}

	return $alter_cmd;
}

function make_index_alter($table, $key) {
	$alter_cmds = array();
	$alter_cmd  = '';

	if (db_index_exists($table, $key)) {
		$alter_cmds[] = 'ALTER TABLE `' . $table . '` DROP KEY `' . $key . '`';
	}

	$parts = db_fetch_assoc_prepared('SELECT *
		FROM table_indexes
		WHERE idx_table_name = ?
		AND idx_key_name = ?
		ORDER BY idx_seq_in_index',
		array($table, $key));

	if (cacti_sizeof($parts)) {
		$i = 0;
		foreach($parts as $p) {
			if ($i == 0 && $p['idx_key_name'] == 'PRIMARY') {
				$alter_cmd = 'ALTER TABLE `' . $table . '` ADD PRIMARY KEY ' . $p['idx_index_type'] . ' (';
			} elseif ($i == 0) {
				if ($p['idx_non_unique'] == 1) {
					$alter_cmd = 'ALTER TABLE `' . $table . '` ADD INDEX `' . $key . '` (';
				} else {
					$alter_cmd = 'ALTER TABLE `' . $table . '` ADD UNIQUE INDEX `' . $key . '` (';
				}
			}

			$alter_cmd .= ($i > 0 ? ',':'') . '`' . $p['idx_column_name'] . '`';

			$i++;
		}

		$alter_cmd .= ') USING ' . $p['idx_index_type'];

		$alter_cmds[] = $alter_cmd;
	}

	return $alter_cmds;
}

function create_tables($load = true) {
	global $config, $database_default, $database_username, $database_password, $database_port, $database_hostname;

	db_execute("CREATE TABLE IF NOT EXISTS table_columns (
		table_name varchar(50) NOT NULL,
		table_sequence int(10) unsigned NOT NULL,
		table_field varchar(50) NOT NULL,
		table_type varchar(50) default NULL,
		table_null varchar(10) default NULL,
		table_key varchar(4) default NULL,
		table_default varchar(50) default NULL,
		table_extra varchar(128) default NULL,
		PRIMARY KEY (table_name, table_sequence, table_field))
		ENGINE=InnoDB
		COMMENT='Holds Default Cacti Table Definitions'");

	$exists_columns = db_table_exists('table_columns');

	if (!$exists_columns) {
		print "Failed to create 'table_coluns'";
		exit;
	}

	db_execute("CREATE TABLE IF NOT EXISTS table_indexes (
		idx_table_name varchar(50) NOT NULL,
		idx_non_unique int(10) unsigned default NULL,
		idx_key_name varchar(128) NOT NULL,
		idx_seq_in_index int(10) unsigned NOT NULL,
		idx_column_name varchar(50) NOT NULL,
		idx_collation varchar(10) default NULL,
		idx_cardinality int(10) unsigned default NULL,
		idx_sub_part varchar(50) default NULL,
		idx_packed varchar(128) default NULL,
		idx_null varchar(10) default NULL,
		idx_index_type varchar(20) default NULL,
		idx_comment varchar(128) default NULL,
		PRIMARY KEY (idx_table_name, idx_key_name, idx_seq_in_index, idx_column_name))
		ENGINE=InnoDB
		COMMENT='Holds Default Cacti Index Definitions'");

	$exists_indexes = db_table_exists('table_indexes');

	if (!$exists_indexes) {
		print "Failed to create 'table_indexes'";
		exit;
	}

	if ($load) {
		db_execute('TRUNCATE table_columns');
		db_execute('TRUNCATE table_indexes');

		$output = array();
		$error  = 0;

		if (file_exists($config['base_path'] . '/docs/audit_schema.sql')) {
			exec('mysql' .
				' -u' . $database_username .
				' -p' . $database_password .
				' -h' . $database_hostname .
				' -P' . $database_port .
				' ' . $database_default .
				' < ' . $config['base_path'] . '/docs/audit_schema.sql', $output, $error);

			if ($error == 0) {
				print 'SUCCESS: Loaded the Audit Schema' . PHP_EOL;
			} else {
				print 'FATAL: Failed Load the Audit Schema' . PHP_EOL;
				print 'ERROR: ' . implode(', ', $output) . PHP_EOL;
			}
		} else {
			print 'FATAL: Failed to find Audit Schema' . PHP_EOL;
		}
	}
}

function load_audit_database() {
	global $config, $database_default, $database_username, $database_password;

	$db_name = 'Tables_in_' . $database_default;

	create_tables(false);

	db_execute('TRUNCATE table_columns');
	db_execute('TRUNCATE table_indexes');

	$tables = db_fetch_assoc('SHOW TABLES');

	if (cacti_sizeof($tables)) {
		foreach($tables as $table) {
			$table_name = $table[$db_name];

			$columns = db_fetch_assoc('SHOW COLUMNS IN ' . $table_name);
			$indexes = db_fetch_assoc('SHOW INDEXES IN ' . $table_name);

			print 'Importing Table: ' . $table_name;

			$i = 1;
			if (cacti_sizeof($columns)) {
				foreach($columns as $c) {
					db_execute_prepared('INSERT INTO table_columns
						(table_name, table_sequence, table_field, table_type, table_null, table_key, table_default, table_extra)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
						array(
							$table_name,
							$i,
							$c['Field'],
							$c['Type'],
							$c['Null'],
							$c['Key'],
							$c['Default'],
							$c['Extra']
						)
					);

					$i++;
				}
			}

			print ' - Done' . PHP_EOL;

			if (cacti_sizeof($indexes)) {
				foreach($indexes as $i) {
					db_execute_prepared('INSERT INTO table_indexes
						(idx_table_name, idx_non_unique, idx_key_name, idx_seq_in_index, idx_column_name,
						idx_collation, idx_cardinality, idx_sub_part, idx_packed, idx_null, idx_index_type, idx_comment)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						array(
							$i['Table'],
							$i['Non_unique'],
							$i['Key_name'],
							$i['Seq_in_index'],
							$i['Column_name'],
							$i['Collation'],
							$i['Cardinality'],
							$i['Sub_part'],
							$i['Packed'],
							$i['Null'],
							$i['Index_type'],
							$i['Comment']
						)
					);
				}
			}
		}
	}

	if (is_dir($config['base_path'] . '/docs')) {
		print PHP_EOL . 'Exporting Table Audit Table Creation Logic to ' . $config['base_path'] . '/docs/audit_schema.sql' . PHP_EOL;

		exec('mysqldump -u' . $database_username . ' -p' . $database_password . ' cacti table_columns table_indexes > ' . $config['base_path'] . '/docs/audit_schema.sql');

		print 'Finished Creating Audit Schema' . PHP_EOL . PHP_EOL;
	} else {
		print PHP_EOL . 'FATAL: Docs directory does not exist!' . PHP_EOL . PHP_EOL;
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Audit Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . "usage: audit_database.php --report | --repair" . PHP_EOL . PHP_EOL;
	print "Cacti utility for auditing and correcting your Cacti database.  This utility can" . PHP_EOL;
	print "will scan your Cacti database and report any problems in the schema that it finds." . PHP_EOL . PHP_EOL;
	print "Options:" . PHP_EOL;
	print "    --report - Report on any issues found in the audit of the database" . PHP_EOL;
	print "    --repair - Repair any issues found during the audit of the database" . PHP_EOL . PHP_EOL;
}

