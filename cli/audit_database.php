#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;
include(dirname(__FILE__) . '/../include/global.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $db_insert1, $db_insert2;

if (sizeof($parms)) {
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
			prime_db_inserts();
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
			exit;
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

	if (sizeof($alters)) {
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

	if (sizeof($tables)) {
		foreach($tables as $table) {
			$alter_cmds = array();
			$table_name = $table[$db_name];
			$columns = db_fetch_assoc('SHOW COLUMNS IN ' . $table_name);

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

				if (!sizeof($plugin_table)) {
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

			if (sizeof($columns)) {
				foreach($columns as $c) {
					$alter_cmd    = '';
					$sequence_off = false;

					$dbc = db_fetch_row_prepared('SELECT *
						FROM table_columns
						WHERE table_name = ?
						AND table_field = ?',
						array($table_name, $c['Field']));

					if (!sizeof($dbc)) {
						$plugin_column = db_fetch_row_prepared('SELECT *
							FROM plugin_db_changes
							WHERE `table` = ?
							AND `column` = ?
							AND method = ?',
							array($table_name, $c['Field'], 'addcolumn'));

						if (!sizeof($plugin_column)) {
							if ($output) {
								print PHP_EOL . 'WARNING Col: \'' . $c['Field'] . '\', does not exist in default Cacti.  Plugin possible';
							}

							$warnings++;
						}
					} else {
						foreach($cols as $dbcol => $col) {
							if ($c[$col] != $dbc[$dbcol]) {
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

			if (isset($alter_cmds) && sizeof($alter_cmds)) {
				$alters = array_merge($alters, $alter_cmds);
			}

			/* In this pass, we will gather the default schema and look for
			 * missing information.
			 */
			$db_columns = db_fetch_assoc_prepared('SELECT *
				FROM table_columns
				WHERE table_name = ?',
				array($table_name));

			if (sizeof($db_columns)) {
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

			if (sizeof($indexes)) {
				foreach($indexes as $i) {
					$dbc = db_fetch_row_prepared('SELECT *
						FROM table_indexes
						WHERE idx_table_name = ?
						AND idx_key_name = ?
						AND idx_seq_in_index = ?
						AND idx_column_name = ?',
						array($i['Table'], $i['Key_name'], $i['Seq_in_index'], $i['Column_name']));

					if (!sizeof($dbc)) {
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

			if (sizeof($db_indexes)) {
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

	if (sizeof($ialters)) {
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

	if (sizeof($parts)) {
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
	global $db_insert1, $db_insert2;

	$exists = db_table_exists('table_columns');

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
		echo "Failed to create 'table_coluns'";
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
		echo "Failed to create 'table_indexes'";
		exit;
	}


	if (!$exists && $load) {
		prime_db_inserts();

		db_execute('TRUNCATE table_columns');
		db_execute('TRUNCATE table_indexes');

		db_execute($db_insert1);
		db_execute($db_insert2);
	}
}

function load_audit_database() {
	global $database_default;
	$db_name = 'Tables_in_' . $database_default;

	create_tables(false);

	db_execute('TRUNCATE table_columns');
	db_execute('TRUNCATE table_indexes');

	$tables = db_fetch_assoc('SHOW TABLES');

	if (sizeof($tables)) {
		foreach($tables as $table) {
			$table_name = $table[$db_name];

			$columns = db_fetch_assoc('SHOW COLUMNS IN ' . $table_name);
			$indexes = db_fetch_assoc('SHOW INDEXES IN ' . $table_name);

			print 'Importing Table: ' . $table_name;

			$i = 1;
			if (sizeof($columns)) {
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

			if (sizeof($indexes)) {
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
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
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


function prime_db_inserts() {
	global $db_insert1, $db_insert2;

	$db_insert1 = "INSERT INTO `table_columns` VALUES ('aggregate_graphs',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('aggregate_graphs',2,'aggregate_template_id','int(10) unsigned','NO','MUL',NULL,''),('aggregate_graphs',3,'template_propogation','char(2)','NO','','',''),('aggregate_graphs',4,'local_graph_id','int(10) unsigned','NO','MUL',NULL,''),('aggregate_graphs',5,'title_format','varchar(128)','NO','MUL',NULL,''),('aggregate_graphs',6,'graph_template_id','int(10) unsigned','NO','',NULL,''),('aggregate_graphs',7,'gprint_prefix','varchar(64)','NO','',NULL,''),('aggregate_graphs',8,'graph_type','int(10) unsigned','NO','',NULL,''),('aggregate_graphs',9,'total','int(10) unsigned','NO','',NULL,''),('aggregate_graphs',10,'total_type','int(10) unsigned','NO','',NULL,''),('aggregate_graphs',11,'total_prefix','varchar(64)','NO','',NULL,''),('aggregate_graphs',12,'order_type','int(10) unsigned','NO','',NULL,''),('aggregate_graphs',13,'created','timestamp','NO','','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('aggregate_graphs',14,'user_id','int(10) unsigned','NO','MUL',NULL,''),('aggregate_graphs_graph_item',1,'aggregate_graph_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graphs_graph_item',2,'graph_templates_item_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graphs_graph_item',3,'sequence','mediumint(8) unsigned','NO','','0',''),('aggregate_graphs_graph_item',4,'color_template','int(11) unsigned','NO','',NULL,''),('aggregate_graphs_graph_item',5,'t_graph_type_id','char(2)','YES','','',''),('aggregate_graphs_graph_item',6,'graph_type_id','tinyint(3)','NO','','0',''),('aggregate_graphs_graph_item',7,'t_cdef_id','char(2)','YES','','',''),('aggregate_graphs_graph_item',8,'cdef_id','mediumint(8) unsigned','YES','',NULL,''),('aggregate_graphs_graph_item',9,'item_skip','char(2)','NO','',NULL,''),('aggregate_graphs_graph_item',10,'item_total','char(2)','NO','',NULL,''),('aggregate_graphs_items',1,'aggregate_graph_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graphs_items',2,'local_graph_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graphs_items',3,'sequence','mediumint(8) unsigned','NO','','0',''),('aggregate_graph_templates',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('aggregate_graph_templates',2,'name','varchar(64)','NO','',NULL,''),('aggregate_graph_templates',3,'graph_template_id','int(10) unsigned','NO','MUL',NULL,''),('aggregate_graph_templates',4,'gprint_prefix','varchar(64)','NO','',NULL,''),('aggregate_graph_templates',5,'graph_type','int(10) unsigned','NO','',NULL,''),('aggregate_graph_templates',6,'total','int(10) unsigned','NO','',NULL,''),('aggregate_graph_templates',7,'total_type','int(10) unsigned','NO','',NULL,''),('aggregate_graph_templates',8,'total_prefix','varchar(64)','NO','',NULL,''),('aggregate_graph_templates',9,'order_type','int(10) unsigned','NO','',NULL,''),('aggregate_graph_templates',10,'created','timestamp','NO','','CURRENT_TIMESTAMP',''),('aggregate_graph_templates',11,'user_id','int(10) unsigned','NO','MUL',NULL,''),('aggregate_graph_templates_graph',1,'aggregate_template_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graph_templates_graph',2,'t_image_format_id','char(2)','YES','','',''),('aggregate_graph_templates_graph',3,'image_format_id','tinyint(1)','NO','','0',''),('aggregate_graph_templates_graph',4,'t_height','char(2)','YES','','',''),('aggregate_graph_templates_graph',5,'height','mediumint(8)','NO','','0',''),('aggregate_graph_templates_graph',6,'t_width','char(2)','YES','','',''),('aggregate_graph_templates_graph',7,'width','mediumint(8)','NO','','0',''),('aggregate_graph_templates_graph',8,'t_upper_limit','char(2)','YES','','',''),('aggregate_graph_templates_graph',9,'upper_limit','varchar(20)','NO','','0',''),('aggregate_graph_templates_graph',10,'t_lower_limit','char(2)','YES','','',''),('aggregate_graph_templates_graph',11,'lower_limit','varchar(20)','NO','','0',''),('aggregate_graph_templates_graph',12,'t_vertical_label','char(2)','YES','','',''),('aggregate_graph_templates_graph',13,'vertical_label','varchar(200)','YES','','',''),('aggregate_graph_templates_graph',14,'t_slope_mode','char(2)','YES','','',''),('aggregate_graph_templates_graph',15,'slope_mode','char(2)','YES','','on',''),('aggregate_graph_templates_graph',16,'t_auto_scale','char(2)','YES','','',''),('aggregate_graph_templates_graph',17,'auto_scale','char(2)','YES','','',''),('aggregate_graph_templates_graph',18,'t_auto_scale_opts','char(2)','YES','','',''),('aggregate_graph_templates_graph',19,'auto_scale_opts','tinyint(1)','NO','','0',''),('aggregate_graph_templates_graph',20,'t_auto_scale_log','char(2)','YES','','',''),('aggregate_graph_templates_graph',21,'auto_scale_log','char(2)','YES','','',''),('aggregate_graph_templates_graph',22,'t_scale_log_units','char(2)','YES','','',''),('aggregate_graph_templates_graph',23,'scale_log_units','char(2)','YES','','',''),('aggregate_graph_templates_graph',24,'t_auto_scale_rigid','char(2)','YES','','',''),('aggregate_graph_templates_graph',25,'auto_scale_rigid','char(2)','YES','','',''),('aggregate_graph_templates_graph',26,'t_auto_padding','char(2)','YES','','',''),('aggregate_graph_templates_graph',27,'auto_padding','char(2)','YES','','',''),('aggregate_graph_templates_graph',28,'t_base_value','char(2)','YES','','',''),('aggregate_graph_templates_graph',29,'base_value','mediumint(8)','NO','','0',''),('aggregate_graph_templates_graph',30,'t_grouping','char(2)','YES','','',''),('aggregate_graph_templates_graph',31,'grouping','char(2)','NO','','',''),('aggregate_graph_templates_graph',32,'t_unit_value','char(2)','YES','','',''),('aggregate_graph_templates_graph',33,'unit_value','varchar(20)','YES','','',''),('aggregate_graph_templates_graph',34,'t_unit_exponent_value','char(2)','YES','','',''),('aggregate_graph_templates_graph',35,'unit_exponent_value','varchar(5)','NO','','',''),('aggregate_graph_templates_graph',36,'t_alt_y_grid','char(2)','YES','','',''),('aggregate_graph_templates_graph',37,'alt_y_grid','char(2)','YES','',NULL,''),('aggregate_graph_templates_graph',38,'t_right_axis','char(2)','YES','','',''),('aggregate_graph_templates_graph',39,'right_axis','varchar(20)','YES','',NULL,''),('aggregate_graph_templates_graph',40,'t_right_axis_label','char(2)','YES','','',''),('aggregate_graph_templates_graph',41,'right_axis_label','varchar(200)','YES','',NULL,''),('aggregate_graph_templates_graph',42,'t_right_axis_format','char(2)','YES','','',''),('aggregate_graph_templates_graph',43,'right_axis_format','mediumint(8)','YES','',NULL,''),('aggregate_graph_templates_graph',44,'t_right_axis_formatter','char(2)','YES','','',''),('aggregate_graph_templates_graph',45,'right_axis_formatter','varchar(10)','YES','',NULL,''),('aggregate_graph_templates_graph',46,'t_left_axis_formatter','char(2)','YES','','',''),('aggregate_graph_templates_graph',47,'left_axis_formatter','varchar(10)','YES','',NULL,''),('aggregate_graph_templates_graph',48,'t_no_gridfit','char(2)','YES','','',''),('aggregate_graph_templates_graph',49,'no_gridfit','char(2)','YES','',NULL,''),('aggregate_graph_templates_graph',50,'t_unit_length','char(2)','YES','','',''),('aggregate_graph_templates_graph',51,'unit_length','varchar(10)','YES','',NULL,''),('aggregate_graph_templates_graph',52,'t_tab_width','char(2)','YES','','',''),('aggregate_graph_templates_graph',53,'tab_width','varchar(20)','YES','','30',''),('aggregate_graph_templates_graph',54,'t_dynamic_labels','char(2)','YES','','',''),('aggregate_graph_templates_graph',55,'dynamic_labels','char(2)','YES','',NULL,''),('aggregate_graph_templates_graph',56,'t_force_rules_legend','char(2)','YES','','',''),('aggregate_graph_templates_graph',57,'force_rules_legend','char(2)','YES','',NULL,''),('aggregate_graph_templates_graph',58,'t_legend_position','char(2)','YES','','',''),('aggregate_graph_templates_graph',59,'legend_position','varchar(10)','YES','',NULL,''),('aggregate_graph_templates_graph',60,'t_legend_direction','char(2)','YES','','',''),('aggregate_graph_templates_graph',61,'legend_direction','varchar(10)','YES','',NULL,''),('aggregate_graph_templates_item',1,'aggregate_template_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graph_templates_item',2,'graph_templates_item_id','int(10) unsigned','NO','PRI',NULL,''),('aggregate_graph_templates_item',3,'sequence','mediumint(8) unsigned','NO','','0',''),('aggregate_graph_templates_item',4,'color_template','int(11)','NO','',NULL,''),('aggregate_graph_templates_item',5,'t_graph_type_id','char(2)','YES','','',''),('aggregate_graph_templates_item',6,'graph_type_id','tinyint(3)','NO','','0',''),('aggregate_graph_templates_item',7,'t_cdef_id','char(2)','YES','','',''),('aggregate_graph_templates_item',8,'cdef_id','mediumint(8) unsigned','YES','',NULL,''),('aggregate_graph_templates_item',9,'item_skip','char(2)','NO','',NULL,''),('aggregate_graph_templates_item',10,'item_total','char(2)','NO','',NULL,''),('automation_devices',1,'id','bigint(20) unsigned','NO','PRI',NULL,'auto_increment'),('automation_devices',2,'network_id','int(10) unsigned','NO','','0',''),('automation_devices',3,'hostname','varchar(100)','NO','MUL','',''),('automation_devices',4,'ip','varchar(17)','NO','UNI','',''),('automation_devices',5,'snmp_community','varchar(100)','NO','','',''),('automation_devices',6,'snmp_version','tinyint(1) unsigned','NO','','1',''),('automation_devices',7,'snmp_port','mediumint(5) unsigned','NO','','161',''),('automation_devices',8,'snmp_username','varchar(50)','YES','',NULL,''),('automation_devices',9,'snmp_password','varchar(50)','YES','',NULL,''),('automation_devices',10,'snmp_auth_protocol','char(6)','YES','','',''),('automation_devices',11,'snmp_priv_passphrase','varchar(200)','YES','','',''),('automation_devices',12,'snmp_priv_protocol','char(6)','YES','','',''),('automation_devices',13,'snmp_context','varchar(64)','YES','','',''),('automation_devices',14,'snmp_engine_id','varchar(64)','YES','','',''),('automation_devices',15,'sysName','varchar(100)','NO','','',''),('automation_devices',16,'sysLocation','varchar(255)','NO','','',''),('automation_devices',17,'sysContact','varchar(255)','NO','','',''),('automation_devices',18,'sysDescr','varchar(255)','NO','','',''),('automation_devices',19,'sysUptime','int(32)','NO','','0',''),('automation_devices',20,'os','varchar(64)','NO','','',''),('automation_devices',21,'snmp','tinyint(4)','NO','','0',''),('automation_devices',22,'known','tinyint(4)','NO','','0',''),('automation_devices',23,'up','tinyint(4)','NO','','0',''),('automation_devices',24,'time','int(11)','NO','','0',''),('automation_graph_rules',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('automation_graph_rules',2,'name','varchar(255)','NO','MUL','',''),('automation_graph_rules',3,'snmp_query_id','smallint(3) unsigned','NO','','0',''),('automation_graph_rules',4,'graph_type_id','smallint(3) unsigned','NO','','0',''),('automation_graph_rules',5,'enabled','char(2)','YES','','',''),('automation_graph_rule_items',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('automation_graph_rule_items',2,'rule_id','mediumint(8) unsigned','NO','','0',''),('automation_graph_rule_items',3,'sequence','smallint(3) unsigned','NO','','0',''),('automation_graph_rule_items',4,'operation','smallint(3) unsigned','NO','','0',''),('automation_graph_rule_items',5,'field','varchar(255)','NO','','',''),('automation_graph_rule_items',6,'operator','smallint(3) unsigned','NO','','0',''),('automation_graph_rule_items',7,'pattern','varchar(255)','NO','','',''),('automation_ips',1,'ip_address','varchar(20)','NO','PRI','',''),('automation_ips',2,'hostname','varchar(100)','YES','',NULL,''),('automation_ips',3,'network_id','int(10) unsigned','YES','',NULL,''),('automation_ips',4,'pid','int(10) unsigned','YES','MUL',NULL,''),('automation_ips',5,'status','int(10) unsigned','YES','',NULL,''),('automation_ips',6,'thread','int(10) unsigned','YES','',NULL,''),('automation_match_rule_items',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('automation_match_rule_items',2,'rule_id','mediumint(8) unsigned','NO','','0',''),('automation_match_rule_items',3,'rule_type','smallint(3) unsigned','NO','','0',''),('automation_match_rule_items',4,'sequence','smallint(3) unsigned','NO','','0',''),('automation_match_rule_items',5,'operation','smallint(3) unsigned','NO','','0',''),('automation_match_rule_items',6,'field','varchar(255)','NO','','',''),('automation_match_rule_items',7,'operator','smallint(3) unsigned','NO','','0',''),('automation_match_rule_items',8,'pattern','varchar(255)','NO','','',''),('automation_networks',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('automation_networks',2,'poller_id','int(10) unsigned','YES','MUL','1',''),('automation_networks',3,'site_id','int(10) unsigned','YES','','1',''),('automation_networks',4,'name','varchar(128)','NO','','',''),('automation_networks',5,'subnet_range','varchar(1024)','NO','','',''),('automation_networks',6,'dns_servers','varchar(128)','NO','','',''),('automation_networks',7,'enabled','char(2)','YES','','',''),('automation_networks',8,'snmp_id','int(10) unsigned','YES','',NULL,''),('automation_networks',9,'enable_netbios','char(2)','YES','','',''),('automation_networks',10,'add_to_cacti','char(2)','YES','','',''),('automation_networks',11,'total_ips','int(10) unsigned','YES','','0',''),('automation_networks',12,'up_hosts','int(10) unsigned','NO','','0',''),('automation_networks',13,'snmp_hosts','int(10) unsigned','NO','','0',''),('automation_networks',14,'ping_method','int(10) unsigned','NO','','0',''),('automation_networks',15,'ping_port','int(10) unsigned','NO','','0',''),('automation_networks',16,'ping_timeout','int(10) unsigned','NO','','0',''),('automation_networks',17,'ping_retries','int(10) unsigned','YES','','0',''),('automation_networks',18,'sched_type','int(10) unsigned','NO','','0',''),('automation_networks',19,'threads','int(10) unsigned','YES','','1',''),('automation_networks',20,'run_limit','int(10) unsigned','YES','','0',''),('automation_networks',21,'start_at','varchar(20)','YES','',NULL,''),('automation_networks',22,'next_start','timestamp','NO','','0000-00-00 00:00:00',''),('automation_networks',23,'recur_every','int(10) unsigned','YES','','1',''),('automation_networks',24,'day_of_week','varchar(45)','YES','',NULL,''),('automation_networks',25,'month','varchar(45)','YES','',NULL,''),('automation_networks',26,'day_of_month','varchar(45)','YES','',NULL,''),('automation_networks',27,'monthly_week','varchar(45)','YES','',NULL,''),('automation_networks',28,'monthly_day','varchar(45)','YES','',NULL,''),('automation_networks',29,'last_runtime','double','NO','','0',''),('automation_networks',30,'last_started','timestamp','NO','','0000-00-00 00:00:00',''),('automation_networks',31,'last_status','varchar(128)','NO','','',''),('automation_networks',32,'rerun_data_queries','char(2)','YES','',NULL,''),('automation_processes',1,'pid','int(8) unsigned','NO','PRI',NULL,''),('automation_processes',2,'poller_id','int(10) unsigned','YES','','1',''),('automation_processes',3,'network_id','int(10) unsigned','NO','PRI','0',''),('automation_processes',4,'task','varchar(20)','YES','','',''),('automation_processes',5,'status','varchar(20)','YES','',NULL,''),('automation_processes',6,'command','varchar(20)','YES','',NULL,''),('automation_processes',7,'up_hosts','int(10) unsigned','YES','','0',''),('automation_processes',8,'snmp_hosts','int(10) unsigned','YES','','0',''),('automation_processes',9,'heartbeat','timestamp','NO','','0000-00-00 00:00:00',''),('automation_snmp',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('automation_snmp',2,'name','varchar(100)','NO','','',''),('automation_snmp_items',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('automation_snmp_items',2,'snmp_id','int(10) unsigned','NO','PRI','0',''),('automation_snmp_items',3,'sequence','int(10) unsigned','NO','','0',''),('automation_snmp_items',4,'snmp_version','tinyint(1) unsigned','NO','','1',''),('automation_snmp_items',5,'snmp_community','varchar(100)','NO','',NULL,''),('automation_snmp_items',6,'snmp_port','mediumint(5) unsigned','NO','','161',''),('automation_snmp_items',7,'snmp_timeout','int(10) unsigned','NO','','500',''),('automation_snmp_items',8,'snmp_retries','tinyint(11) unsigned','NO','','3',''),('automation_snmp_items',9,'max_oids','int(12) unsigned','YES','','10',''),('automation_snmp_items',10,'snmp_username','varchar(50)','YES','',NULL,''),('automation_snmp_items',11,'snmp_password','varchar(50)','YES','',NULL,''),('automation_snmp_items',12,'snmp_auth_protocol','char(6)','YES','','',''),('automation_snmp_items',13,'snmp_priv_passphrase','varchar(200)','YES','','',''),('automation_snmp_items',14,'snmp_priv_protocol','char(6)','YES','','',''),('automation_snmp_items',15,'snmp_context','varchar(64)','YES','','',''),('automation_snmp_items',16,'snmp_engine_id','varchar(64)','YES','','',''),('automation_templates',1,'id','int(8)','NO','PRI',NULL,'auto_increment'),('automation_templates',2,'host_template','int(8)','NO','','0',''),('automation_templates',3,'availability_method','int(10) unsigned','YES','','2',''),('automation_templates',4,'sysDescr','varchar(255)','YES','','',''),('automation_templates',5,'sysName','varchar(255)','YES','','',''),('automation_templates',6,'sysOid','varchar(60)','YES','','',''),('automation_templates',7,'sequence','int(10) unsigned','YES','','0',''),('automation_tree_rules',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('automation_tree_rules',2,'name','varchar(255)','NO','MUL','',''),('automation_tree_rules',3,'tree_id','smallint(3) unsigned','NO','','0',''),('automation_tree_rules',4,'tree_item_id','mediumint(8) unsigned','NO','','0',''),('automation_tree_rules',5,'leaf_type','smallint(3) unsigned','NO','','0',''),('automation_tree_rules',6,'host_grouping_type','smallint(3) unsigned','NO','','0',''),('automation_tree_rules',7,'enabled','char(2)','YES','','',''),('automation_tree_rule_items',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('automation_tree_rule_items',2,'rule_id','mediumint(8) unsigned','NO','','0',''),('automation_tree_rule_items',3,'sequence','smallint(3) unsigned','NO','','0',''),('automation_tree_rule_items',4,'field','varchar(255)','NO','','',''),('automation_tree_rule_items',5,'sort_type','smallint(3) unsigned','NO','','0',''),('automation_tree_rule_items',6,'propagate_changes','char(2)','YES','','',''),('automation_tree_rule_items',7,'search_pattern','varchar(255)','NO','','',''),('automation_tree_rule_items',8,'replace_pattern','varchar(255)','NO','','',''),('cdef',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('cdef',2,'hash','varchar(32)','NO','MUL','',''),('cdef',3,'system','mediumint(8) unsigned','NO','','0',''),('cdef',4,'name','varchar(255)','NO','MUL','',''),('cdef_items',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('cdef_items',2,'hash','varchar(32)','NO','','',''),('cdef_items',3,'cdef_id','mediumint(8) unsigned','NO','MUL','0',''),('cdef_items',4,'sequence','mediumint(8) unsigned','NO','','0',''),('cdef_items',5,'type','tinyint(2)','NO','','0',''),('cdef_items',6,'value','varchar(150)','NO','','',''),('colors',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('colors',2,'name','varchar(40)','YES','','',''),('colors',3,'hex','varchar(6)','NO','UNI','',''),('colors',4,'read_only','char(2)','YES','','',''),('color_templates',1,'color_template_id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('color_templates',2,'name','varchar(255)','NO','','',''),('color_template_items',1,'color_template_item_id','int(12) unsigned','NO','PRI',NULL,'auto_increment'),('color_template_items',2,'color_template_id','mediumint(8) unsigned','NO','','0',''),('color_template_items',3,'color_id','mediumint(8) unsigned','NO','','0',''),('color_template_items',4,'sequence','mediumint(8) unsigned','NO','','0',''),('data_input',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_input',2,'hash','varchar(32)','NO','','',''),('data_input',3,'name','varchar(200)','NO','MUL','',''),('data_input',4,'input_string','varchar(512)','YES','',NULL,''),('data_input',5,'type_id','tinyint(2)','NO','','0',''),('data_input_data',1,'data_input_field_id','mediumint(8) unsigned','NO','PRI','0',''),('data_input_data',2,'data_template_data_id','mediumint(8) unsigned','NO','PRI','0',''),('data_input_data',3,'t_value','char(2)','YES','MUL',NULL,''),('data_input_data',4,'value','text','YES','',NULL,''),('data_input_fields',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_input_fields',2,'hash','varchar(32)','NO','','',''),('data_input_fields',3,'data_input_id','mediumint(8) unsigned','NO','MUL','0',''),('data_input_fields',4,'name','varchar(200)','NO','','',''),('data_input_fields',5,'data_name','varchar(50)','NO','','',''),('data_input_fields',6,'input_output','char(3)','NO','MUL','',''),('data_input_fields',7,'update_rra','char(2)','YES','','0',''),('data_input_fields',8,'sequence','smallint(5)','NO','','0',''),('data_input_fields',9,'type_code','varchar(40)','YES','MUL',NULL,''),('data_input_fields',10,'regexp_match','varchar(200)','YES','',NULL,''),('data_input_fields',11,'allow_nulls','char(2)','YES','',NULL,''),('data_local',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_local',2,'data_template_id','mediumint(8) unsigned','NO','MUL','0',''),('data_local',3,'host_id','mediumint(8) unsigned','NO','MUL','0',''),('data_local',4,'snmp_query_id','mediumint(8)','NO','MUL','0',''),('data_local',5,'snmp_index','varchar(255)','NO','MUL','',''),('data_source_profiles',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_source_profiles',2,'hash','varchar(32)','NO','','',''),('data_source_profiles',3,'name','varchar(255)','NO','MUL','',''),('data_source_profiles',4,'step','int(10) unsigned','NO','','300',''),('data_source_profiles',5,'heartbeat','int(10) unsigned','NO','','600',''),('data_source_profiles',6,'x_files_factor','double','YES','','0.5',''),('data_source_profiles',7,'default','char(2)','YES','','',''),('data_source_profiles_cf',1,'data_source_profile_id','mediumint(8) unsigned','NO','PRI','0',''),('data_source_profiles_cf',2,'consolidation_function_id','smallint(5) unsigned','NO','PRI','0',''),('data_source_profiles_rra',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_source_profiles_rra',2,'data_source_profile_id','mediumint(8) unsigned','NO','MUL','0',''),('data_source_profiles_rra',3,'name','varchar(255)','NO','','',''),('data_source_profiles_rra',4,'steps','int(10) unsigned','YES','','1',''),('data_source_profiles_rra',5,'rows','int(10) unsigned','NO','','700',''),('data_source_profiles_rra',6,'timespan','int(10) unsigned','NO','','0',''),('data_source_purge_action',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('data_source_purge_action',2,'name','varchar(128)','NO','UNI','',''),('data_source_purge_action',3,'local_data_id','mediumint(8) unsigned','NO','','0',''),('data_source_purge_action',4,'action','tinyint(2)','NO','','0',''),('data_source_purge_temp',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('data_source_purge_temp',2,'name_cache','varchar(255)','NO','','',''),('data_source_purge_temp',3,'local_data_id','mediumint(8) unsigned','NO','MUL','0',''),('data_source_purge_temp',4,'name','varchar(128)','NO','UNI','',''),('data_source_purge_temp',5,'size','int(10) unsigned','NO','','0',''),('data_source_purge_temp',6,'last_mod','timestamp','NO','','0000-00-00 00:00:00',''),('data_source_purge_temp',7,'in_cacti','tinyint(4)','NO','MUL','0',''),('data_source_purge_temp',8,'data_template_id','mediumint(8) unsigned','NO','MUL','0',''),('data_source_stats_daily',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_daily',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_daily',3,'average','double','YES','',NULL,''),('data_source_stats_daily',4,'peak','double','YES','',NULL,''),('data_source_stats_hourly',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_hourly',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_hourly',3,'average','double','YES','',NULL,''),('data_source_stats_hourly',4,'peak','double','YES','',NULL,''),('data_source_stats_hourly_cache',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_hourly_cache',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_hourly_cache',3,'time','timestamp','NO','PRI','0000-00-00 00:00:00',''),('data_source_stats_hourly_cache',4,'value','double','YES','',NULL,''),('data_source_stats_hourly_last',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_hourly_last',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_hourly_last',3,'value','double','YES','',NULL,''),('data_source_stats_hourly_last',4,'calculated','double','YES','',NULL,''),('data_source_stats_monthly',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_monthly',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_monthly',3,'average','double','YES','',NULL,''),('data_source_stats_monthly',4,'peak','double','YES','',NULL,''),('data_source_stats_weekly',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_weekly',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_weekly',3,'average','double','YES','',NULL,''),('data_source_stats_weekly',4,'peak','double','YES','',NULL,''),('data_source_stats_yearly',1,'local_data_id','mediumint(8) unsigned','NO','PRI',NULL,''),('data_source_stats_yearly',2,'rrd_name','varchar(19)','NO','PRI',NULL,''),('data_source_stats_yearly',3,'average','double','YES','',NULL,''),('data_source_stats_yearly',4,'peak','double','YES','',NULL,''),('data_template',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_template',2,'hash','varchar(32)','NO','','',''),('data_template',3,'name','varchar(150)','NO','MUL','',''),('data_template_data',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_template_data',2,'local_data_template_data_id','mediumint(8) unsigned','NO','','0',''),('data_template_data',3,'local_data_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_data',4,'data_template_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_data',5,'data_input_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_data',6,'t_name','char(2)','YES','',NULL,''),('data_template_data',7,'name','varchar(250)','NO','','',''),('data_template_data',8,'name_cache','varchar(255)','NO','','',''),('data_template_data',9,'data_source_path','varchar(255)','YES','','',''),('data_template_data',10,'t_active','char(2)','YES','','',''),('data_template_data',11,'active','char(2)','YES','',NULL,''),('data_template_data',12,'t_rrd_step','char(2)','YES','','',''),('data_template_data',13,'rrd_step','mediumint(8) unsigned','NO','','0',''),('data_template_data',14,'t_data_source_profile_id','char(2)','YES','','',''),('data_template_data',15,'data_source_profile_id','mediumint(8) unsigned','NO','','1',''),('data_template_rrd',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('data_template_rrd',2,'hash','varchar(32)','NO','','',''),('data_template_rrd',3,'local_data_template_rrd_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_rrd',4,'local_data_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_rrd',5,'data_template_id','mediumint(8) unsigned','NO','MUL','0',''),('data_template_rrd',6,'t_rrd_maximum','char(2)','YES','',NULL,''),('data_template_rrd',7,'rrd_maximum','varchar(20)','NO','','0',''),('data_template_rrd',8,'t_rrd_minimum','char(2)','YES','',NULL,''),('data_template_rrd',9,'rrd_minimum','varchar(20)','NO','','0',''),('data_template_rrd',10,'t_rrd_heartbeat','char(2)','YES','',NULL,''),('data_template_rrd',11,'rrd_heartbeat','mediumint(6)','NO','','0',''),('data_template_rrd',12,'t_data_source_type_id','char(2)','YES','',NULL,''),('data_template_rrd',13,'data_source_type_id','smallint(5)','NO','','0',''),('data_template_rrd',14,'t_data_source_name','char(2)','YES','',NULL,''),('data_template_rrd',15,'data_source_name','varchar(19)','NO','','',''),('data_template_rrd',16,'t_data_input_field_id','char(2)','YES','',NULL,''),('data_template_rrd',17,'data_input_field_id','mediumint(8) unsigned','NO','','0',''),('external_links',1,'id','int(11)','NO','PRI',NULL,'auto_increment'),('external_links',2,'sortorder','int(11)','NO','','0',''),('external_links',3,'enabled','char(2)','YES','','on',''),('external_links',4,'contentfile','varchar(255)','NO','','',''),('external_links',5,'title','varchar(20)','NO','','',''),('external_links',6,'style','varchar(10)','NO','','',''),('external_links',7,'extendedstyle','varchar(50)','NO','','',''),('graph_local',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('graph_local',2,'graph_template_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_local',3,'host_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_local',4,'snmp_query_id','mediumint(8)','NO','MUL','0',''),('graph_local',5,'snmp_query_graph_id','mediumint(8)','NO','MUL','0',''),('graph_local',6,'snmp_index','varchar(255)','NO','MUL','',''),('graph_templates',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('graph_templates',2,'hash','char(32)','NO','','',''),('graph_templates',3,'name','char(255)','NO','MUL','',''),('graph_templates',4,'multiple','char(2)','NO','MUL','',''),('graph_templates_gprint',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('graph_templates_gprint',2,'hash','varchar(32)','NO','','',''),('graph_templates_gprint',3,'name','varchar(100)','NO','MUL','',''),('graph_templates_gprint',4,'gprint_text','varchar(255)','YES','',NULL,''),('graph_templates_graph',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('graph_templates_graph',2,'local_graph_template_graph_id','mediumint(8) unsigned','NO','','0',''),('graph_templates_graph',3,'local_graph_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_templates_graph',4,'graph_template_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_templates_graph',5,'t_image_format_id','char(2)','YES','','',''),('graph_templates_graph',6,'image_format_id','tinyint(1)','NO','','0',''),('graph_templates_graph',7,'t_title','char(2)','YES','','',''),('graph_templates_graph',8,'title','varchar(255)','NO','','',''),('graph_templates_graph',9,'title_cache','varchar(255)','NO','MUL','',''),('graph_templates_graph',10,'t_height','char(2)','YES','','',''),('graph_templates_graph',11,'height','mediumint(8)','NO','','0',''),('graph_templates_graph',12,'t_width','char(2)','YES','','',''),('graph_templates_graph',13,'width','mediumint(8)','NO','','0',''),('graph_templates_graph',14,'t_upper_limit','char(2)','YES','','',''),('graph_templates_graph',15,'upper_limit','varchar(20)','NO','','0',''),('graph_templates_graph',16,'t_lower_limit','char(2)','YES','','',''),('graph_templates_graph',17,'lower_limit','varchar(20)','NO','','0',''),('graph_templates_graph',18,'t_vertical_label','char(2)','YES','','',''),('graph_templates_graph',19,'vertical_label','varchar(200)','YES','',NULL,''),('graph_templates_graph',20,'t_slope_mode','char(2)','YES','','',''),('graph_templates_graph',21,'slope_mode','char(2)','YES','','on',''),('graph_templates_graph',22,'t_auto_scale','char(2)','YES','','',''),('graph_templates_graph',23,'auto_scale','char(2)','YES','',NULL,''),('graph_templates_graph',24,'t_auto_scale_opts','char(2)','YES','','',''),('graph_templates_graph',25,'auto_scale_opts','tinyint(1)','NO','','0',''),('graph_templates_graph',26,'t_auto_scale_log','char(2)','YES','','',''),('graph_templates_graph',27,'auto_scale_log','char(2)','YES','',NULL,''),('graph_templates_graph',28,'t_scale_log_units','char(2)','YES','','',''),('graph_templates_graph',29,'scale_log_units','char(2)','YES','',NULL,''),('graph_templates_graph',30,'t_auto_scale_rigid','char(2)','YES','','',''),('graph_templates_graph',31,'auto_scale_rigid','char(2)','YES','',NULL,''),('graph_templates_graph',32,'t_auto_padding','char(2)','YES','','',''),('graph_templates_graph',33,'auto_padding','char(2)','YES','',NULL,''),('graph_templates_graph',34,'t_base_value','char(2)','YES','','',''),('graph_templates_graph',35,'base_value','mediumint(8)','NO','','0',''),('graph_templates_graph',36,'t_grouping','char(2)','YES','','',''),('graph_templates_graph',37,'grouping','char(2)','NO','','',''),('graph_templates_graph',38,'t_unit_value','char(2)','YES','','',''),('graph_templates_graph',39,'unit_value','varchar(20)','YES','',NULL,''),('graph_templates_graph',40,'t_unit_exponent_value','char(2)','YES','','',''),('graph_templates_graph',41,'unit_exponent_value','varchar(5)','NO','','',''),('graph_templates_graph',42,'t_alt_y_grid','char(2)','YES','','',''),('graph_templates_graph',43,'alt_y_grid','char(2)','YES','',NULL,''),('graph_templates_graph',44,'t_right_axis','char(2)','YES','','',''),('graph_templates_graph',45,'right_axis','varchar(20)','YES','',NULL,''),('graph_templates_graph',46,'t_right_axis_label','char(2)','YES','','',''),('graph_templates_graph',47,'right_axis_label','varchar(200)','YES','',NULL,''),('graph_templates_graph',48,'t_right_axis_format','char(2)','YES','','',''),('graph_templates_graph',49,'right_axis_format','mediumint(8)','YES','',NULL,''),('graph_templates_graph',50,'t_right_axis_formatter','char(2)','YES','','',''),('graph_templates_graph',51,'right_axis_formatter','varchar(10)','YES','',NULL,''),('graph_templates_graph',52,'t_left_axis_formatter','char(2)','YES','','',''),('graph_templates_graph',53,'left_axis_formatter','varchar(10)','YES','',NULL,''),('graph_templates_graph',54,'t_no_gridfit','char(2)','YES','','',''),('graph_templates_graph',55,'no_gridfit','char(2)','YES','',NULL,''),('graph_templates_graph',56,'t_unit_length','char(2)','YES','','',''),('graph_templates_graph',57,'unit_length','varchar(10)','YES','',NULL,''),('graph_templates_graph',58,'t_tab_width','char(2)','YES','','',''),('graph_templates_graph',59,'tab_width','varchar(20)','YES','','30',''),('graph_templates_graph',60,'t_dynamic_labels','char(2)','YES','','',''),('graph_templates_graph',61,'dynamic_labels','char(2)','YES','',NULL,''),('graph_templates_graph',62,'t_force_rules_legend','char(2)','YES','','',''),('graph_templates_graph',63,'force_rules_legend','char(2)','YES','',NULL,''),('graph_templates_graph',64,'t_legend_position','char(2)','YES','','',''),('graph_templates_graph',65,'legend_position','varchar(10)','YES','',NULL,''),('graph_templates_graph',66,'t_legend_direction','char(2)','YES','','',''),('graph_templates_graph',67,'legend_direction','varchar(10)','YES','',NULL,''),('graph_templates_item',1,'id','int(12) unsigned','NO','PRI',NULL,'auto_increment'),('graph_templates_item',2,'hash','varchar(32)','NO','','',''),('graph_templates_item',3,'local_graph_template_item_id','int(12) unsigned','NO','','0',''),('graph_templates_item',4,'local_graph_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_templates_item',5,'graph_template_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_templates_item',6,'task_item_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_templates_item',7,'color_id','mediumint(8) unsigned','NO','','0',''),('graph_templates_item',8,'alpha','char(2)','YES','','FF',''),('graph_templates_item',9,'graph_type_id','tinyint(3)','NO','','0',''),('graph_templates_item',10,'line_width','decimal(4,2)','YES','','0.00',''),('graph_templates_item',11,'dashes','varchar(20)','YES','',NULL,''),('graph_templates_item',12,'dash_offset','mediumint(4)','YES','',NULL,''),('graph_templates_item',13,'cdef_id','mediumint(8) unsigned','NO','','0',''),('graph_templates_item',14,'vdef_id','mediumint(8) unsigned','NO','','0',''),('graph_templates_item',15,'shift','char(2)','YES','',NULL,''),('graph_templates_item',16,'consolidation_function_id','tinyint(2)','NO','','0',''),('graph_templates_item',17,'textalign','varchar(10)','YES','',NULL,''),('graph_templates_item',18,'text_format','varchar(255)','YES','',NULL,''),('graph_templates_item',19,'value','varchar(255)','YES','',NULL,''),('graph_templates_item',20,'hard_return','char(2)','YES','',NULL,''),('graph_templates_item',21,'gprint_id','mediumint(8) unsigned','NO','','0',''),('graph_templates_item',22,'sequence','mediumint(8) unsigned','NO','','0',''),('graph_template_input',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('graph_template_input',2,'hash','varchar(32)','NO','','',''),('graph_template_input',3,'graph_template_id','mediumint(8) unsigned','NO','','0',''),('graph_template_input',4,'name','varchar(255)','NO','','',''),('graph_template_input',5,'description','text','YES','',NULL,''),('graph_template_input',6,'column_name','varchar(50)','NO','','',''),('graph_template_input_defs',1,'graph_template_input_id','mediumint(8) unsigned','NO','PRI','0',''),('graph_template_input_defs',2,'graph_template_item_id','int(12) unsigned','NO','PRI','0',''),('graph_tree',1,'id','smallint(5) unsigned','NO','PRI',NULL,'auto_increment'),('graph_tree',2,'enabled','char(2)','YES','','on',''),('graph_tree',3,'locked','tinyint(4)','YES','','0',''),('graph_tree',4,'locked_date','timestamp','NO','','0000-00-00 00:00:00',''),('graph_tree',5,'sort_type','tinyint(3) unsigned','NO','','1',''),('graph_tree',6,'name','varchar(255)','NO','MUL','',''),('graph_tree',7,'sequence','int(10) unsigned','YES','MUL','1',''),('graph_tree',8,'user_id','int(10) unsigned','YES','','1',''),('graph_tree',9,'last_modified','timestamp','NO','','0000-00-00 00:00:00',''),('graph_tree',10,'modified_by','int(10) unsigned','YES','','1',''),('graph_tree_items',1,'id','bigint(20) unsigned','NO','PRI',NULL,'auto_increment'),('graph_tree_items',2,'parent','bigint(20) unsigned','YES','MUL',NULL,''),('graph_tree_items',3,'position','int(10) unsigned','YES','',NULL,''),('graph_tree_items',4,'graph_tree_id','smallint(5) unsigned','NO','MUL','0',''),('graph_tree_items',5,'local_graph_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_tree_items',6,'title','varchar(255)','YES','',NULL,''),('graph_tree_items',7,'host_id','mediumint(8) unsigned','NO','MUL','0',''),('graph_tree_items',8,'site_id','int(10) unsigned','YES','MUL','0',''),('graph_tree_items',9,'host_grouping_type','tinyint(3) unsigned','NO','','1',''),('graph_tree_items',10,'sort_children_type','tinyint(3) unsigned','NO','','1',''),('graph_tree_items',11,'graph_regex','varchar(60)','YES','','',''),('graph_tree_items',12,'host_regex','varchar(60)','YES','','',''),('host',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('host',2,'poller_id','int(10) unsigned','NO','MUL','1',''),('host',3,'site_id','int(10) unsigned','NO','MUL','1',''),('host',4,'host_template_id','mediumint(8) unsigned','NO','','0',''),('host',5,'description','varchar(150)','NO','','',''),('host',6,'hostname','varchar(100)','YES','MUL',NULL,''),('host',7,'notes','text','YES','',NULL,''),('host',8,'external_id','varchar(40)','YES','MUL',NULL,''),('host',9,'snmp_community','varchar(100)','YES','',NULL,''),('host',10,'snmp_version','tinyint(1) unsigned','NO','','1',''),('host',11,'snmp_username','varchar(50)','YES','',NULL,''),('host',12,'snmp_password','varchar(50)','YES','',NULL,''),('host',13,'snmp_auth_protocol','char(6)','YES','','',''),('host',14,'snmp_priv_passphrase','varchar(200)','YES','','',''),('host',15,'snmp_priv_protocol','char(6)','YES','','',''),('host',16,'snmp_context','varchar(64)','YES','','',''),('host',17,'snmp_engine_id','varchar(64)','YES','','',''),('host',18,'snmp_port','mediumint(5) unsigned','NO','','161',''),('host',19,'snmp_timeout','mediumint(8) unsigned','NO','','500',''),('host',20,'snmp_sysDescr','varchar(300)','NO','','',''),('host',21,'snmp_sysObjectID','varchar(128)','NO','','',''),('host',22,'snmp_sysUpTimeInstance','int(10) unsigned','NO','','0',''),('host',23,'snmp_sysContact','varchar(300)','NO','','',''),('host',24,'snmp_sysName','varchar(300)','NO','','',''),('host',25,'snmp_sysLocation','varchar(300)','NO','','',''),('host',26,'availability_method','smallint(5) unsigned','NO','','1',''),('host',27,'ping_method','smallint(5) unsigned','YES','','0',''),('host',28,'ping_port','int(12) unsigned','YES','','0',''),('host',29,'ping_timeout','int(12) unsigned','YES','','500',''),('host',30,'ping_retries','int(12) unsigned','YES','','2',''),('host',31,'max_oids','int(12) unsigned','YES','','10',''),('host',32,'device_threads','tinyint(2) unsigned','NO','','1',''),('host',33,'disabled','char(2)','YES','MUL',NULL,''),('host',34,'status','tinyint(2)','NO','MUL','0',''),('host',35,'status_event_count','mediumint(8) unsigned','NO','','0',''),('host',36,'status_fail_date','timestamp','NO','','0000-00-00 00:00:00',''),('host',37,'status_rec_date','timestamp','NO','','0000-00-00 00:00:00',''),('host',38,'status_last_error','varchar(255)','YES','','',''),('host',39,'min_time','decimal(10,5)','YES','','9.99999',''),('host',40,'max_time','decimal(10,5)','YES','','0.00000',''),('host',41,'cur_time','decimal(10,5)','YES','','0.00000',''),('host',42,'avg_time','decimal(10,5)','YES','','0.00000',''),('host',43,'polling_time','double','YES','','0',''),('host',44,'total_polls','int(12) unsigned','YES','','0',''),('host',45,'failed_polls','int(12) unsigned','YES','','0',''),('host',46,'availability','decimal(8,5)','NO','','100.00000',''),('host',47,'last_updated','timestamp','NO','','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('host_graph',1,'host_id','mediumint(8) unsigned','NO','PRI','0',''),('host_graph',2,'graph_template_id','mediumint(8) unsigned','NO','PRI','0',''),('host_snmp_cache',1,'host_id','mediumint(8) unsigned','NO','PRI','0',''),('host_snmp_cache',2,'snmp_query_id','mediumint(8) unsigned','NO','PRI','0',''),('host_snmp_cache',3,'field_name','varchar(50)','NO','PRI','',''),('host_snmp_cache',4,'field_value','varchar(512)','YES','MUL',NULL,''),('host_snmp_cache',5,'snmp_index','varchar(191)','NO','PRI','',''),('host_snmp_cache',6,'oid','text','NO','',NULL,''),('host_snmp_cache',7,'present','tinyint(4)','NO','MUL','1',''),('host_snmp_cache',8,'last_updated','timestamp','NO','MUL','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('host_snmp_query',1,'host_id','mediumint(8) unsigned','NO','PRI','0',''),('host_snmp_query',2,'snmp_query_id','mediumint(8) unsigned','NO','PRI','0',''),('host_snmp_query',3,'sort_field','varchar(50)','NO','','',''),('host_snmp_query',4,'title_format','varchar(50)','NO','','',''),('host_snmp_query',5,'reindex_method','tinyint(3) unsigned','NO','','0',''),('host_template',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('host_template',2,'hash','varchar(32)','NO','','',''),('host_template',3,'name','varchar(100)','NO','MUL','',''),('host_template_graph',1,'host_template_id','mediumint(8) unsigned','NO','PRI','0',''),('host_template_graph',2,'graph_template_id','mediumint(8) unsigned','NO','PRI','0',''),('host_template_snmp_query',1,'host_template_id','mediumint(8) unsigned','NO','PRI','0',''),('host_template_snmp_query',2,'snmp_query_id','mediumint(8) unsigned','NO','PRI','0',''),('plugin_config',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('plugin_config',2,'directory','varchar(32)','NO','MUL','',''),('plugin_config',3,'name','varchar(64)','NO','','',''),('plugin_config',4,'status','tinyint(2)','NO','MUL','0',''),('plugin_config',5,'author','varchar(64)','NO','','',''),('plugin_config',6,'webpage','varchar(255)','NO','','',''),('plugin_config',7,'version','varchar(8)','NO','','',''),('plugin_db_changes',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('plugin_db_changes',2,'plugin','varchar(16)','NO','MUL','',''),('plugin_db_changes',3,'table','varchar(64)','NO','','',''),('plugin_db_changes',4,'column','varchar(64)','NO','',NULL,''),('plugin_db_changes',5,'method','varchar(16)','NO','MUL','',''),('plugin_hooks',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('plugin_hooks',2,'name','varchar(32)','NO','','',''),('plugin_hooks',3,'hook','varchar(64)','NO','MUL','',''),('plugin_hooks',4,'file','varchar(255)','NO','','',''),('plugin_hooks',5,'function','varchar(128)','NO','','',''),('plugin_hooks',6,'status','int(8)','NO','MUL','0',''),('plugin_realms',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('plugin_realms',2,'plugin','varchar(32)','NO','MUL','',''),('plugin_realms',3,'file','text','NO','',NULL,''),('plugin_realms',4,'display','varchar(64)','NO','','',''),('poller',1,'id','smallint(5) unsigned','NO','PRI',NULL,'auto_increment'),('poller',2,'disabled','char(2)','YES','MUL','',''),('poller',3,'name','varchar(30)','YES','MUL',NULL,''),('poller',4,'notes','varchar(1024)','YES','','',''),('poller',5,'status','int(10) unsigned','NO','','0',''),('poller',6,'hostname','varchar(100)','NO','','',''),('poller',7,'dbdefault','varchar(20)','NO','','cacti',''),('poller',8,'dbhost','varchar(64)','NO','','cacti',''),('poller',9,'dbuser','varchar(20)','NO','','',''),('poller',10,'dbpass','varchar(64)','NO','','',''),('poller',11,'dbport','int(10) unsigned','YES','','3306',''),('poller',12,'dbssl','char(3)','YES','','',''),('poller',13,'total_time','double','YES','','0',''),('poller',14,'snmp','mediumint(8) unsigned','YES','','0',''),('poller',15,'script','mediumint(8) unsigned','YES','','0',''),('poller',16,'server','mediumint(8) unsigned','YES','','0',''),('poller',17,'last_update','timestamp','NO','','0000-00-00 00:00:00',''),('poller',18,'last_status','timestamp','NO','','0000-00-00 00:00:00',''),('poller_command',1,'poller_id','smallint(5) unsigned','NO','PRI','1',''),('poller_command',2,'time','timestamp','NO','','0000-00-00 00:00:00',''),('poller_command',3,'action','tinyint(3) unsigned','NO','PRI','0',''),('poller_command',4,'command','varchar(191)','NO','PRI','',''),('poller_command',5,'last_updated','timestamp','NO','','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('poller_data_template_field_mappings',1,'data_template_id','int(10) unsigned','NO','PRI','0',''),('poller_data_template_field_mappings',2,'data_name','varchar(40)','NO','PRI','',''),('poller_data_template_field_mappings',3,'data_source_names','varchar(125)','NO','PRI','',''),('poller_data_template_field_mappings',4,'last_updated','timestamp','NO','','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('poller_item',1,'local_data_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_item',2,'poller_id','int(10) unsigned','NO','MUL','1',''),('poller_item',3,'host_id','mediumint(8) unsigned','NO','MUL','0',''),('poller_item',4,'action','tinyint(2) unsigned','NO','MUL','1',''),('poller_item',5,'present','tinyint(4)','NO','MUL','1',''),('poller_item',6,'last_updated','timestamp','NO','','CURRENT_TIMESTAMP','on update CURRENT_TIMESTAMP'),('poller_item',7,'hostname','varchar(100)','NO','','',''),('poller_item',8,'snmp_community','varchar(100)','NO','','',''),('poller_item',9,'snmp_version','tinyint(1) unsigned','NO','','0',''),('poller_item',10,'snmp_username','varchar(50)','NO','','',''),('poller_item',11,'snmp_password','varchar(50)','NO','','',''),('poller_item',12,'snmp_auth_protocol','char(6)','NO','','',''),('poller_item',13,'snmp_priv_passphrase','varchar(200)','NO','','',''),('poller_item',14,'snmp_priv_protocol','char(6)','NO','','',''),('poller_item',15,'snmp_context','varchar(64)','YES','','',''),('poller_item',16,'snmp_engine_id','varchar(64)','YES','','',''),('poller_item',17,'snmp_port','mediumint(5) unsigned','NO','','161',''),('poller_item',18,'snmp_timeout','mediumint(8) unsigned','NO','','0',''),('poller_item',19,'rrd_name','varchar(19)','NO','PRI','',''),('poller_item',20,'rrd_path','varchar(255)','NO','','',''),('poller_item',21,'rrd_num','tinyint(2) unsigned','NO','','0',''),('poller_item',22,'rrd_step','mediumint(8)','NO','','300',''),('poller_item',23,'rrd_next_step','mediumint(8)','NO','','0',''),('poller_item',24,'arg1','text','YES','',NULL,''),('poller_item',25,'arg2','varchar(255)','YES','',NULL,''),('poller_item',26,'arg3','varchar(255)','YES','',NULL,''),('poller_output',1,'local_data_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_output',2,'rrd_name','varchar(19)','NO','PRI','',''),('poller_output',3,'time','timestamp','NO','PRI','0000-00-00 00:00:00',''),('poller_output',4,'output','varchar(512)','NO','','',''),('poller_output_boost',1,'local_data_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_output_boost',2,'rrd_name','varchar(19)','NO','PRI','',''),('poller_output_boost',3,'time','timestamp','NO','PRI','0000-00-00 00:00:00',''),('poller_output_boost',4,'output','varchar(512)','NO','',NULL,''),('poller_output_boost_processes',1,'sock_int_value','bigint(20) unsigned','NO','PRI',NULL,'auto_increment'),('poller_output_boost_processes',2,'status','varchar(255)','YES','',NULL,''),('poller_output_realtime',1,'local_data_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_output_realtime',2,'rrd_name','varchar(19)','NO','PRI','',''),('poller_output_realtime',3,'time','timestamp','NO','PRI','0000-00-00 00:00:00',''),('poller_output_realtime',4,'output','text','NO','',NULL,''),('poller_output_realtime',5,'poller_id','varchar(256)','NO','MUL','1',''),('poller_reindex',1,'host_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_reindex',2,'data_query_id','mediumint(8) unsigned','NO','PRI','0',''),('poller_reindex',3,'action','tinyint(3) unsigned','NO','','0',''),('poller_reindex',4,'present','tinyint(4)','NO','MUL','1',''),('poller_reindex',5,'op','char(1)','NO','','',''),('poller_reindex',6,'assert_value','varchar(100)','NO','','',''),('poller_reindex',7,'arg1','varchar(255)','NO','','',''),('poller_resource_cache',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('poller_resource_cache',2,'resource_type','varchar(20)','YES','',NULL,''),('poller_resource_cache',3,'md5sum','varchar(32)','YES','',NULL,''),('poller_resource_cache',4,'path','varchar(191)','YES','UNI',NULL,''),('poller_resource_cache',5,'update_time','timestamp','NO','','0000-00-00 00:00:00',''),('poller_resource_cache',6,'contents','longblob','YES','',NULL,''),('poller_time',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('poller_time',2,'pid','int(11) unsigned','NO','','0',''),('poller_time',3,'poller_id','int(10) unsigned','NO','MUL','1',''),('poller_time',4,'start_time','timestamp','NO','','0000-00-00 00:00:00',''),('poller_time',5,'end_time','timestamp','NO','','0000-00-00 00:00:00',''),('reports',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('reports',2,'user_id','mediumint(8) unsigned','NO','','0',''),('reports',3,'name','varchar(100)','NO','','',''),('reports',4,'cformat','char(2)','NO','','',''),('reports',5,'format_file','varchar(255)','NO','','',''),('reports',6,'font_size','smallint(2) unsigned','NO','','0',''),('reports',7,'alignment','smallint(2) unsigned','NO','','0',''),('reports',8,'graph_linked','char(2)','NO','','',''),('reports',9,'intrvl','smallint(2) unsigned','NO','','0',''),('reports',10,'count','smallint(2) unsigned','NO','','0',''),('reports',11,'offset','int(12) unsigned','NO','','0',''),('reports',12,'mailtime','bigint(20) unsigned','NO','MUL','0',''),('reports',13,'subject','varchar(64)','NO','','',''),('reports',14,'from_name','varchar(40)','NO','',NULL,''),('reports',15,'from_email','text','NO','',NULL,''),('reports',16,'email','text','NO','',NULL,''),('reports',17,'bcc','text','NO','',NULL,''),('reports',18,'attachment_type','smallint(2) unsigned','NO','','1',''),('reports',19,'graph_height','smallint(2) unsigned','NO','','0',''),('reports',20,'graph_width','smallint(2) unsigned','NO','','0',''),('reports',21,'graph_columns','smallint(2) unsigned','NO','','0',''),('reports',22,'thumbnails','char(2)','NO','','',''),('reports',23,'lastsent','bigint(20) unsigned','NO','','0',''),('reports',24,'enabled','char(2)','YES','','',''),('reports_items',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('reports_items',2,'report_id','int(10) unsigned','NO','MUL','0',''),('reports_items',3,'item_type','tinyint(1) unsigned','NO','','1',''),('reports_items',4,'tree_id','int(10) unsigned','NO','','0',''),('reports_items',5,'branch_id','int(10) unsigned','NO','','0',''),('reports_items',6,'tree_cascade','char(2)','NO','','',''),('reports_items',7,'graph_name_regexp','varchar(128)','NO','','',''),('reports_items',8,'host_template_id','int(10) unsigned','NO','','0',''),('reports_items',9,'host_id','int(10) unsigned','NO','','0',''),('reports_items',10,'graph_template_id','int(10) unsigned','NO','','0',''),('reports_items',11,'local_graph_id','int(10) unsigned','NO','','0',''),('reports_items',12,'timespan','int(10) unsigned','NO','','0',''),('reports_items',13,'align','tinyint(1) unsigned','NO','','1',''),('reports_items',14,'item_text','text','NO','',NULL,''),('reports_items',15,'font_size','smallint(2) unsigned','NO','','10',''),('reports_items',16,'sequence','smallint(5) unsigned','NO','','0',''),('sessions',1,'id','varchar(32)','NO','PRI',NULL,''),('sessions',2,'remote_addr','varchar(25)','NO','','',''),('sessions',3,'access','int(10) unsigned','YES','',NULL,''),('sessions',4,'data','mediumblob','YES','',NULL,''),('settings',1,'name','varchar(50)','NO','PRI','',''),('settings',2,'value','varchar(2048)','NO','','',''),('settings_tree',1,'user_id','mediumint(8) unsigned','NO','PRI','0',''),('settings_tree',2,'graph_tree_item_id','mediumint(8) unsigned','NO','PRI','0',''),('settings_tree',3,'status','tinyint(1)','NO','','0',''),('settings_user',1,'user_id','smallint(8) unsigned','NO','PRI','0',''),('settings_user',2,'name','varchar(50)','NO','PRI','',''),('settings_user',3,'value','varchar(2048)','NO','','',''),('settings_user_group',1,'group_id','smallint(8) unsigned','NO','PRI','0',''),('settings_user_group',2,'name','varchar(50)','NO','PRI','',''),('settings_user_group',3,'value','varchar(2048)','NO','','',''),('sites',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('sites',2,'name','varchar(100)','NO','MUL','',''),('sites',3,'address1','varchar(100)','YES','','',''),('sites',4,'address2','varchar(100)','YES','','',''),('sites',5,'city','varchar(50)','YES','MUL','',''),('sites',6,'state','varchar(20)','YES','MUL',NULL,''),('sites',7,'postal_code','varchar(20)','YES','MUL','',''),('sites',8,'country','varchar(30)','YES','MUL','',''),('sites',9,'timezone','varchar(40)','YES','','',''),('sites',10,'latitude','decimal(13,10)','NO','','0.0000000000',''),('sites',11,'longitude','decimal(13,10)','NO','','0.0000000000',''),('sites',12,'alternate_id','varchar(30)','YES','MUL','',''),('sites',13,'notes','varchar(1024)','YES','',NULL,''),('snmpagent_cache',1,'oid','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache',2,'name','varchar(50)','NO','MUL',NULL,''),('snmpagent_cache',3,'mib','varchar(50)','NO','MUL',NULL,''),('snmpagent_cache',4,'type','varchar(50)','NO','','',''),('snmpagent_cache',5,'otype','varchar(50)','NO','','',''),('snmpagent_cache',6,'kind','varchar(50)','NO','','',''),('snmpagent_cache',7,'max-access','varchar(50)','NO','','not-accessible',''),('snmpagent_cache',8,'value','varchar(255)','NO','','',''),('snmpagent_cache',9,'description','varchar(5000)','NO','','',''),('snmpagent_cache_notifications',1,'name','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache_notifications',2,'mib','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache_notifications',3,'attribute','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache_notifications',4,'sequence_id','smallint(6)','NO','PRI',NULL,''),('snmpagent_cache_textual_conventions',1,'name','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache_textual_conventions',2,'mib','varchar(50)','NO','PRI',NULL,''),('snmpagent_cache_textual_conventions',3,'type','varchar(50)','NO','PRI','',''),('snmpagent_cache_textual_conventions',4,'description','varchar(5000)','NO','','',''),('snmpagent_managers',1,'id','int(8)','NO','PRI',NULL,'auto_increment'),('snmpagent_managers',2,'hostname','varchar(100)','NO','MUL',NULL,''),('snmpagent_managers',3,'description','varchar(255)','NO','',NULL,''),('snmpagent_managers',4,'disabled','char(2)','YES','',NULL,''),('snmpagent_managers',5,'max_log_size','tinyint(1)','NO','',NULL,''),('snmpagent_managers',6,'snmp_version','tinyint(1) unsigned','NO','','1',''),('snmpagent_managers',7,'snmp_community','varchar(100)','NO','',NULL,''),('snmpagent_managers',8,'snmp_username','varchar(50)','NO','',NULL,''),('snmpagent_managers',9,'snmp_password','varchar(50)','NO','',NULL,''),('snmpagent_managers',10,'snmp_auth_protocol','char(6)','NO','',NULL,''),('snmpagent_managers',11,'snmp_priv_passphrase','varchar(200)','NO','',NULL,''),('snmpagent_managers',12,'snmp_priv_protocol','char(6)','NO','',NULL,''),('snmpagent_managers',13,'snmp_engine_id','varchar(64)','YES','',NULL,''),('snmpagent_managers',14,'snmp_port','mediumint(5) unsigned','NO','','161',''),('snmpagent_managers',15,'snmp_message_type','tinyint(1)','NO','',NULL,''),('snmpagent_managers',16,'notes','text','YES','',NULL,''),('snmpagent_managers_notifications',1,'manager_id','int(8)','NO','PRI',NULL,''),('snmpagent_managers_notifications',2,'notification','varchar(50)','NO','PRI',NULL,''),('snmpagent_managers_notifications',3,'mib','varchar(50)','NO','PRI',NULL,''),('snmpagent_mibs',1,'id','int(8)','NO','PRI',NULL,'auto_increment'),('snmpagent_mibs',2,'name','varchar(50)','NO','','',''),('snmpagent_mibs',3,'file','varchar(255)','NO','','',''),('snmpagent_notifications_log',1,'id','int(12)','NO','PRI',NULL,'auto_increment'),('snmpagent_notifications_log',2,'time','int(24)','NO','MUL',NULL,''),('snmpagent_notifications_log',3,'severity','tinyint(1)','NO','MUL',NULL,''),('snmpagent_notifications_log',4,'manager_id','int(8)','NO','MUL',NULL,''),('snmpagent_notifications_log',5,'notification','varchar(190)','NO','',NULL,''),('snmpagent_notifications_log',6,'mib','varchar(50)','NO','',NULL,''),('snmpagent_notifications_log',7,'varbinds','varchar(5000)','NO','',NULL,''),('snmp_query',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('snmp_query',2,'hash','varchar(32)','NO','','',''),('snmp_query',3,'xml_path','varchar(255)','NO','','',''),('snmp_query',4,'name','varchar(100)','NO','MUL','',''),('snmp_query',5,'description','varchar(255)','YES','',NULL,''),('snmp_query',6,'graph_template_id','mediumint(8) unsigned','NO','','0',''),('snmp_query',7,'data_input_id','mediumint(8) unsigned','NO','','0',''),('snmp_query_graph',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('snmp_query_graph',2,'hash','varchar(32)','NO','','',''),('snmp_query_graph',3,'snmp_query_id','mediumint(8) unsigned','NO','MUL','0',''),('snmp_query_graph',4,'name','varchar(100)','NO','','',''),('snmp_query_graph',5,'graph_template_id','mediumint(8) unsigned','NO','MUL','0',''),('snmp_query_graph_rrd',1,'snmp_query_graph_id','mediumint(8) unsigned','NO','PRI','0',''),('snmp_query_graph_rrd',2,'data_template_id','mediumint(8) unsigned','NO','PRI','0',''),('snmp_query_graph_rrd',3,'data_template_rrd_id','mediumint(8) unsigned','NO','PRI','0',''),('snmp_query_graph_rrd',4,'snmp_field_name','varchar(50)','NO','','0',''),('snmp_query_graph_rrd_sv',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('snmp_query_graph_rrd_sv',2,'hash','varchar(32)','NO','','',''),('snmp_query_graph_rrd_sv',3,'snmp_query_graph_id','mediumint(8) unsigned','NO','MUL','0',''),('snmp_query_graph_rrd_sv',4,'data_template_id','mediumint(8) unsigned','NO','MUL','0',''),('snmp_query_graph_rrd_sv',5,'sequence','mediumint(8) unsigned','NO','','0',''),('snmp_query_graph_rrd_sv',6,'field_name','varchar(100)','NO','','',''),('snmp_query_graph_rrd_sv',7,'text','varchar(255)','NO','','',''),('snmp_query_graph_sv',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('snmp_query_graph_sv',2,'hash','varchar(32)','NO','','',''),('snmp_query_graph_sv',3,'snmp_query_graph_id','mediumint(8) unsigned','NO','MUL','0',''),('snmp_query_graph_sv',4,'sequence','mediumint(8) unsigned','NO','','0',''),('snmp_query_graph_sv',5,'field_name','varchar(100)','NO','','',''),('snmp_query_graph_sv',6,'text','varchar(255)','NO','','',''),('table_columns',1,'table_name','varchar(50)','NO','PRI','',''),('table_columns',2,'table_sequence','int(10) unsigned','NO','PRI','0',''),('table_columns',3,'table_field','varchar(50)','NO','PRI','',''),('table_columns',4,'table_type','varchar(50)','YES','',NULL,''),('table_columns',5,'table_null','varchar(10)','YES','',NULL,''),('table_columns',6,'table_key','varchar(4)','YES','',NULL,''),('table_columns',7,'table_default','varchar(50)','YES','',NULL,''),('table_columns',8,'table_extra','varchar(128)','YES','',NULL,''),('table_indexes',1,'idx_table_name','varchar(50)','NO','PRI','',''),('table_indexes',2,'idx_non_unique','int(10) unsigned','YES','',NULL,''),('table_indexes',3,'idx_key_name','varchar(128)','NO','PRI','',''),('table_indexes',4,'idx_seq_in_index','int(10) unsigned','NO','PRI','0',''),('table_indexes',5,'idx_column_name','varchar(50)','NO','PRI','',''),('table_indexes',6,'idx_collation','varchar(10)','YES','',NULL,''),('table_indexes',7,'idx_cardinality','int(10) unsigned','YES','',NULL,''),('table_indexes',8,'idx_sub_part','varchar(50)','YES','',NULL,''),('table_indexes',9,'idx_packed','varchar(128)','YES','',NULL,''),('table_indexes',10,'idx_null','varchar(10)','YES','',NULL,''),('table_indexes',11,'idx_index_type','varchar(20)','YES','',NULL,''),('table_indexes',12,'idx_comment','varchar(128)','YES','',NULL,''),('user_auth',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('user_auth',2,'username','varchar(50)','NO','MUL','0',''),('user_auth',3,'password','varchar(2048)','NO','','',''),('user_auth',4,'realm','mediumint(8)','NO','MUL','0',''),('user_auth',5,'full_name','varchar(100)','YES','','0',''),('user_auth',6,'email_address','varchar(128)','YES','',NULL,''),('user_auth',7,'must_change_password','char(2)','YES','',NULL,''),('user_auth',8,'password_change','char(2)','YES','','on',''),('user_auth',9,'show_tree','char(2)','YES','','on',''),('user_auth',10,'show_list','char(2)','YES','','on',''),('user_auth',11,'show_preview','char(2)','NO','','on',''),('user_auth',12,'graph_settings','char(2)','YES','',NULL,''),('user_auth',13,'login_opts','tinyint(1)','NO','','1',''),('user_auth',14,'policy_graphs','tinyint(1) unsigned','NO','','1',''),('user_auth',15,'policy_trees','tinyint(1) unsigned','NO','','1',''),('user_auth',16,'policy_hosts','tinyint(1) unsigned','NO','','1',''),('user_auth',17,'policy_graph_templates','tinyint(1) unsigned','NO','','1',''),('user_auth',18,'enabled','char(2)','NO','MUL','on',''),('user_auth',19,'lastchange','int(12)','NO','','-1',''),('user_auth',20,'lastlogin','int(12)','NO','','-1',''),('user_auth',21,'password_history','varchar(4096)','NO','','-1',''),('user_auth',22,'locked','varchar(3)','NO','','',''),('user_auth',23,'failed_attempts','int(5)','NO','','0',''),('user_auth',24,'lastfail','int(12)','NO','','0',''),('user_auth',25,'reset_perms','int(12) unsigned','NO','','0',''),('user_auth_cache',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('user_auth_cache',2,'user_id','int(10) unsigned','NO','MUL','0',''),('user_auth_cache',3,'hostname','varchar(100)','NO','MUL','',''),('user_auth_cache',4,'last_update','timestamp','NO','MUL','CURRENT_TIMESTAMP',''),('user_auth_cache',5,'token','varchar(191)','NO','UNI','',''),('user_auth_group',1,'id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('user_auth_group',2,'name','varchar(20)','NO','',NULL,''),('user_auth_group',3,'description','varchar(255)','NO','','',''),('user_auth_group',4,'graph_settings','varchar(2)','YES','',NULL,''),('user_auth_group',5,'login_opts','tinyint(1)','NO','','1',''),('user_auth_group',6,'show_tree','varchar(2)','YES','','on',''),('user_auth_group',7,'show_list','varchar(2)','YES','','on',''),('user_auth_group',8,'show_preview','varchar(2)','NO','','on',''),('user_auth_group',9,'policy_graphs','tinyint(1) unsigned','NO','','1',''),('user_auth_group',10,'policy_trees','tinyint(1) unsigned','NO','','1',''),('user_auth_group',11,'policy_hosts','tinyint(1) unsigned','NO','','1',''),('user_auth_group',12,'policy_graph_templates','tinyint(1) unsigned','NO','','1',''),('user_auth_group',13,'enabled','char(2)','NO','','on',''),('user_auth_group_members',1,'group_id','int(10) unsigned','NO','PRI',NULL,''),('user_auth_group_members',2,'user_id','int(10) unsigned','NO','PRI',NULL,''),('user_auth_group_perms',1,'group_id','mediumint(8) unsigned','NO','PRI','0',''),('user_auth_group_perms',2,'item_id','mediumint(8) unsigned','NO','PRI','0',''),('user_auth_group_perms',3,'type','tinyint(2) unsigned','NO','PRI','0',''),('user_auth_group_realm',1,'group_id','int(10) unsigned','NO','PRI',NULL,''),('user_auth_group_realm',2,'realm_id','int(10) unsigned','NO','PRI',NULL,''),('user_auth_perms',1,'user_id','mediumint(8) unsigned','NO','PRI','0',''),('user_auth_perms',2,'item_id','mediumint(8) unsigned','NO','PRI','0',''),('user_auth_perms',3,'type','tinyint(2) unsigned','NO','PRI','0',''),('user_auth_realm',1,'realm_id','mediumint(8) unsigned','NO','PRI','0',''),('user_auth_realm',2,'user_id','mediumint(8) unsigned','NO','PRI','0',''),('user_domains',1,'domain_id','int(10) unsigned','NO','PRI',NULL,'auto_increment'),('user_domains',2,'domain_name','varchar(20)','NO','',NULL,''),('user_domains',3,'type','int(10) unsigned','NO','','0',''),('user_domains',4,'enabled','char(2)','NO','','on',''),('user_domains',5,'defdomain','tinyint(3)','NO','','0',''),('user_domains',6,'user_id','int(10) unsigned','NO','','0',''),('user_domains_ldap',1,'domain_id','int(10) unsigned','NO','PRI',NULL,''),('user_domains_ldap',2,'server','varchar(128)','NO','',NULL,''),('user_domains_ldap',3,'port','int(10) unsigned','NO','',NULL,''),('user_domains_ldap',4,'port_ssl','int(10) unsigned','NO','',NULL,''),('user_domains_ldap',5,'proto_version','tinyint(3) unsigned','NO','',NULL,''),('user_domains_ldap',6,'encryption','tinyint(3) unsigned','NO','',NULL,''),('user_domains_ldap',7,'referrals','tinyint(3) unsigned','NO','',NULL,''),('user_domains_ldap',8,'mode','tinyint(3) unsigned','NO','',NULL,''),('user_domains_ldap',9,'dn','varchar(128)','NO','',NULL,''),('user_domains_ldap',10,'group_require','char(2)','NO','',NULL,''),('user_domains_ldap',11,'group_dn','varchar(128)','NO','',NULL,''),('user_domains_ldap',12,'group_attrib','varchar(128)','NO','',NULL,''),('user_domains_ldap',13,'group_member_type','tinyint(3) unsigned','NO','',NULL,''),('user_domains_ldap',14,'search_base','varchar(128)','NO','',NULL,''),('user_domains_ldap',15,'search_filter','varchar(128)','NO','',NULL,''),('user_domains_ldap',16,'specific_dn','varchar(128)','NO','',NULL,''),('user_domains_ldap',17,'specific_password','varchar(128)','NO','',NULL,''),('user_log',1,'username','varchar(50)','NO','PRI','0',''),('user_log',2,'user_id','mediumint(8)','NO','PRI','0',''),('user_log',3,'time','timestamp','NO','PRI','0000-00-00 00:00:00',''),('user_log',4,'result','tinyint(1)','NO','','0',''),('user_log',5,'ip','varchar(40)','NO','','',''),('vdef',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('vdef',2,'hash','varchar(32)','NO','MUL','',''),('vdef',3,'name','varchar(255)','NO','MUL','',''),('vdef_items',1,'id','mediumint(8) unsigned','NO','PRI',NULL,'auto_increment'),('vdef_items',2,'hash','varchar(32)','NO','','',''),('vdef_items',3,'vdef_id','mediumint(8) unsigned','NO','MUL','0',''),('vdef_items',4,'sequence','mediumint(8) unsigned','NO','','0',''),('vdef_items',5,'type','tinyint(2)','NO','','0',''),('vdef_items',6,'value','varchar(150)','NO','','',''),('version',1,'cacti','char(20)','NO','PRI','','');";

	$db_insert2 = "INSERT INTO `table_indexes` VALUES ('aggregate_graphs',1,'aggregate_template_id',1,'aggregate_template_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs',1,'local_graph_id',1,'local_graph_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs',1,'title_format',1,'title_format','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs_graph_item',0,'PRIMARY',1,'aggregate_graph_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs_graph_item',0,'PRIMARY',2,'graph_templates_item_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs_items',0,'PRIMARY',1,'aggregate_graph_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graphs_items',0,'PRIMARY',2,'local_graph_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates',1,'graph_template_id',1,'graph_template_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates_graph',0,'PRIMARY',1,'aggregate_template_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates_item',0,'PRIMARY',1,'aggregate_template_id','A',0,NULL,NULL,'','BTREE',''),('aggregate_graph_templates_item',0,'PRIMARY',2,'graph_templates_item_id','A',0,NULL,NULL,'','BTREE',''),('automation_devices',1,'hostname',1,'hostname','A',0,NULL,NULL,'','BTREE',''),('automation_devices',0,'ip',1,'ip','A',0,NULL,NULL,'','BTREE',''),('automation_devices',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('automation_graph_rules',1,'name',1,'name','A',3,'171',NULL,'','BTREE',''),('automation_graph_rules',0,'PRIMARY',1,'id','A',3,NULL,NULL,'','BTREE',''),('automation_graph_rule_items',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('automation_ips',1,'pid',1,'pid',NULL,0,NULL,NULL,'YES','HASH',''),('automation_ips',0,'PRIMARY',1,'ip_address',NULL,0,NULL,NULL,'','HASH',''),('automation_match_rule_items',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('automation_networks',1,'poller_id',1,'poller_id','A',1,NULL,NULL,'YES','BTREE',''),('automation_networks',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('automation_processes',0,'PRIMARY',1,'pid',NULL,NULL,NULL,NULL,'','HASH',''),('automation_processes',0,'PRIMARY',2,'network_id',NULL,0,NULL,NULL,'','HASH',''),('automation_snmp',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('automation_snmp_items',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('automation_snmp_items',0,'PRIMARY',2,'snmp_id','A',2,NULL,NULL,'','BTREE',''),('automation_templates',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('automation_tree_rules',1,'name',1,'name','A',2,'171',NULL,'','BTREE',''),('automation_tree_rules',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('automation_tree_rule_items',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('cdef',1,'hash',1,'hash','A',2,NULL,NULL,'','BTREE',''),('cdef',1,'name',1,'name','A',2,'171',NULL,'','BTREE',''),('cdef',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('cdef_items',1,'cdef_id_sequence',1,'cdef_id','A',22,NULL,NULL,'','BTREE',''),('cdef_items',1,'cdef_id_sequence',2,'sequence','A',22,NULL,NULL,'','BTREE',''),('cdef_items',0,'PRIMARY',1,'id','A',22,NULL,NULL,'','BTREE',''),('colors',0,'hex',1,'hex','A',377,NULL,NULL,'','BTREE',''),('colors',0,'PRIMARY',1,'id','A',377,NULL,NULL,'','BTREE',''),('color_templates',0,'PRIMARY',1,'color_template_id','A',2,NULL,NULL,'','BTREE',''),('color_template_items',0,'PRIMARY',1,'color_template_item_id','A',44,NULL,NULL,'','BTREE',''),('data_input',1,'name_type_id',1,'name','A',14,'171',NULL,'','BTREE',''),('data_input',1,'name_type_id',2,'type_id','A',14,NULL,NULL,'','BTREE',''),('data_input',0,'PRIMARY',1,'id','A',14,NULL,NULL,'','BTREE',''),('data_input_data',0,'PRIMARY',1,'data_input_field_id','A',246,NULL,NULL,'','BTREE',''),('data_input_data',0,'PRIMARY',2,'data_template_data_id','A',246,NULL,NULL,'','BTREE',''),('data_input_data',1,'t_value',1,'t_value','A',246,NULL,NULL,'YES','BTREE',''),('data_input_fields',1,'data_input_id',1,'data_input_id','A',26,NULL,NULL,'','BTREE',''),('data_input_fields',1,'input_output',1,'input_output','A',4,NULL,NULL,'','BTREE',''),('data_input_fields',0,'PRIMARY',1,'id','A',52,NULL,NULL,'','BTREE',''),('data_input_fields',1,'type_code_data_input_id',1,'type_code','A',26,NULL,NULL,'YES','BTREE',''),('data_input_fields',1,'type_code_data_input_id',2,'data_input_id','A',52,NULL,NULL,'','BTREE',''),('data_local',1,'data_template_id',1,'data_template_id','A',5,NULL,NULL,'','BTREE',''),('data_local',1,'host_id_snmp_query_id',1,'host_id','A',5,NULL,NULL,'','BTREE',''),('data_local',1,'host_id_snmp_query_id',2,'snmp_query_id','A',5,NULL,NULL,'','BTREE',''),('data_local',0,'PRIMARY',1,'id','A',5,NULL,NULL,'','BTREE',''),('data_local',1,'snmp_index',1,'snmp_index','A',5,'191',NULL,'','BTREE',''),('data_local',1,'snmp_query_id',1,'snmp_query_id','A',5,NULL,NULL,'','BTREE',''),('data_source_profiles',1,'name',1,'name','A',2,'171',NULL,'','BTREE',''),('data_source_profiles',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('data_source_profiles_cf',1,'data_source_profile_id',1,'data_source_profile_id','A',2,NULL,NULL,'','BTREE',''),('data_source_profiles_cf',0,'PRIMARY',1,'data_source_profile_id','A',2,NULL,NULL,'','BTREE',''),('data_source_profiles_cf',0,'PRIMARY',2,'consolidation_function_id','A',2,NULL,NULL,'','BTREE',''),('data_source_profiles_rra',1,'data_source_profile_id',1,'data_source_profile_id','A',2,NULL,NULL,'','BTREE',''),('data_source_profiles_rra',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('data_source_purge_action',0,'name',1,'name','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_action',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_temp',1,'data_template_id',1,'data_template_id','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_temp',1,'in_cacti',1,'in_cacti','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_temp',1,'local_data_id',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_temp',0,'name',1,'name','A',0,NULL,NULL,'','BTREE',''),('data_source_purge_temp',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_daily',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_daily',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_hourly',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_hourly',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_hourly_cache',0,'PRIMARY',1,'local_data_id',NULL,NULL,NULL,NULL,'','HASH',''),('data_source_stats_hourly_cache',0,'PRIMARY',2,'time',NULL,NULL,NULL,NULL,'','HASH',''),('data_source_stats_hourly_cache',0,'PRIMARY',3,'rrd_name',NULL,0,NULL,NULL,'','HASH',''),('data_source_stats_hourly_cache',1,'time',1,'time','A',NULL,NULL,NULL,'','BTREE',''),('data_source_stats_hourly_last',0,'PRIMARY',1,'local_data_id',NULL,NULL,NULL,NULL,'','HASH',''),('data_source_stats_hourly_last',0,'PRIMARY',2,'rrd_name',NULL,0,NULL,NULL,'','HASH',''),('data_source_stats_monthly',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_monthly',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_weekly',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_weekly',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_yearly',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('data_source_stats_yearly',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('data_template',1,'name',1,'name','A',33,NULL,NULL,'','BTREE',''),('data_template',0,'PRIMARY',1,'id','A',33,NULL,NULL,'','BTREE',''),('data_template_data',1,'data_input_id',1,'data_input_id','A',19,NULL,NULL,'','BTREE',''),('data_template_data',1,'data_template_id',1,'data_template_id','A',38,NULL,NULL,'','BTREE',''),('data_template_data',1,'local_data_id',1,'local_data_id','A',2,NULL,NULL,'','BTREE',''),('data_template_data',0,'PRIMARY',1,'id','A',38,NULL,NULL,'','BTREE',''),('data_template_rrd',1,'data_template_id',1,'data_template_id','A',59,NULL,NULL,'','BTREE',''),('data_template_rrd',0,'duplicate_dsname_contraint',1,'local_data_id','A',2,NULL,NULL,'','BTREE',''),('data_template_rrd',0,'duplicate_dsname_contraint',2,'data_source_name','A',59,NULL,NULL,'','BTREE',''),('data_template_rrd',0,'duplicate_dsname_contraint',3,'data_template_id','A',59,NULL,NULL,'','BTREE',''),('data_template_rrd',1,'local_data_id',1,'local_data_id','A',2,NULL,NULL,'','BTREE',''),('data_template_rrd',1,'local_data_template_rrd_id',1,'local_data_template_rrd_id','A',2,NULL,NULL,'','BTREE',''),('data_template_rrd',0,'PRIMARY',1,'id','A',59,NULL,NULL,'','BTREE',''),('external_links',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('graph_local',1,'graph_template_id',1,'graph_template_id','A',4,NULL,NULL,'','BTREE',''),('graph_local',1,'host_id',1,'host_id','A',4,NULL,NULL,'','BTREE',''),('graph_local',0,'PRIMARY',1,'id','A',4,NULL,NULL,'','BTREE',''),('graph_local',1,'snmp_index',1,'snmp_index','A',4,'191',NULL,'','BTREE',''),('graph_local',1,'snmp_query_graph_id',1,'snmp_query_graph_id','A',4,NULL,NULL,'','BTREE',''),('graph_local',1,'snmp_query_id',1,'snmp_query_id','A',4,NULL,NULL,'','BTREE',''),('graph_templates',1,'multiple_name',1,'multiple','A',8,NULL,NULL,'','BTREE',''),('graph_templates',1,'multiple_name',2,'name','A',8,'171',NULL,'','BTREE',''),('graph_templates',1,'name',1,'name','A',8,'171',NULL,'','BTREE',''),('graph_templates',0,'PRIMARY',1,'id','A',8,NULL,NULL,'','BTREE',''),('graph_templates_gprint',1,'name',1,'name','A',2,NULL,NULL,'','BTREE',''),('graph_templates_gprint',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('graph_templates_graph',1,'graph_template_id',1,'graph_template_id','A',37,NULL,NULL,'','BTREE',''),('graph_templates_graph',1,'local_graph_id',1,'local_graph_id','A',37,NULL,NULL,'','BTREE',''),('graph_templates_graph',0,'PRIMARY',1,'id','A',37,NULL,NULL,'','BTREE',''),('graph_templates_graph',1,'title_cache',1,'title_cache','A',37,'191',NULL,'','BTREE',''),('graph_templates_item',1,'graph_template_id',1,'graph_template_id','A',71,NULL,NULL,'','BTREE',''),('graph_templates_item',1,'lgi_gti',1,'local_graph_id','A',8,NULL,NULL,'','BTREE',''),('graph_templates_item',1,'lgi_gti',2,'graph_template_id','A',85,NULL,NULL,'','BTREE',''),('graph_templates_item',1,'local_graph_id_sequence',1,'local_graph_id','A',8,NULL,NULL,'','BTREE',''),('graph_templates_item',1,'local_graph_id_sequence',2,'sequence','A',85,NULL,NULL,'','BTREE',''),('graph_templates_item',0,'PRIMARY',1,'id','A',427,NULL,NULL,'','BTREE',''),('graph_templates_item',1,'task_item_id',1,'task_item_id','A',142,NULL,NULL,'','BTREE',''),('graph_template_input',0,'PRIMARY',1,'id','A',74,NULL,NULL,'','BTREE',''),('graph_template_input_defs',1,'graph_template_input_id',1,'graph_template_input_id','A',163,NULL,NULL,'','BTREE',''),('graph_template_input_defs',0,'PRIMARY',1,'graph_template_input_id','A',163,NULL,NULL,'','BTREE',''),('graph_template_input_defs',0,'PRIMARY',2,'graph_template_item_id','A',326,NULL,NULL,'','BTREE',''),('graph_tree',1,'name',1,'name','A',1,'171',NULL,'','BTREE',''),('graph_tree',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('graph_tree',1,'sequence',1,'sequence','A',1,NULL,NULL,'YES','BTREE',''),('graph_tree_items',1,'graph_tree_id',1,'graph_tree_id','A',0,NULL,NULL,'','BTREE',''),('graph_tree_items',1,'host_id',1,'host_id','A',0,NULL,NULL,'','BTREE',''),('graph_tree_items',1,'local_graph_id',1,'local_graph_id','A',0,NULL,NULL,'','BTREE',''),('graph_tree_items',1,'parent_position',1,'parent','A',0,NULL,NULL,'YES','BTREE',''),('graph_tree_items',1,'parent_position',2,'position','A',0,NULL,NULL,'YES','BTREE',''),('graph_tree_items',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('graph_tree_items',1,'site_id',1,'site_id','A',0,NULL,NULL,'YES','BTREE',''),('host',1,'disabled',1,'disabled','A',1,NULL,NULL,'YES','BTREE',''),('host',1,'external_id',1,'external_id','A',1,NULL,NULL,'YES','BTREE',''),('host',1,'hostname',1,'hostname','A',1,NULL,NULL,'YES','BTREE',''),('host',1,'poller_id_disabled',1,'poller_id','A',1,NULL,NULL,'','BTREE',''),('host',1,'poller_id_disabled',2,'disabled','A',1,NULL,NULL,'YES','BTREE',''),('host',1,'poller_id_last_updated',1,'poller_id','A',1,NULL,NULL,'','BTREE',''),('host',1,'poller_id_last_updated',2,'last_updated','A',1,NULL,NULL,'','BTREE',''),('host',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('host',1,'site_id',1,'site_id','A',1,NULL,NULL,'','BTREE',''),('host',1,'status',1,'status','A',1,NULL,NULL,'','BTREE',''),('host_graph',0,'PRIMARY',1,'host_id','A',2,NULL,NULL,'','BTREE',''),('host_graph',0,'PRIMARY',2,'graph_template_id','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'field_name',1,'field_name','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'field_value',1,'field_value','A',2,'191',NULL,'YES','BTREE',''),('host_snmp_cache',1,'host_id',1,'host_id','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'host_id',2,'field_name','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'last_updated',1,'last_updated','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'present',1,'present','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',0,'PRIMARY',1,'host_id','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',0,'PRIMARY',2,'snmp_query_id','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',0,'PRIMARY',3,'field_name','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',0,'PRIMARY',4,'snmp_index','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'snmp_index',1,'snmp_index','A',2,NULL,NULL,'','BTREE',''),('host_snmp_cache',1,'snmp_query_id',1,'snmp_query_id','A',2,NULL,NULL,'','BTREE',''),('host_snmp_query',1,'host_id',1,'host_id','A',1,NULL,NULL,'','BTREE',''),('host_snmp_query',0,'PRIMARY',1,'host_id','A',1,NULL,NULL,'','BTREE',''),('host_snmp_query',0,'PRIMARY',2,'snmp_query_id','A',1,NULL,NULL,'','BTREE',''),('host_template',1,'name',1,'name','A',2,NULL,NULL,'','BTREE',''),('host_template',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('host_template_graph',1,'host_template_id',1,'host_template_id','A',2,NULL,NULL,'','BTREE',''),('host_template_graph',0,'PRIMARY',1,'host_template_id','A',2,NULL,NULL,'','BTREE',''),('host_template_graph',0,'PRIMARY',2,'graph_template_id','A',2,NULL,NULL,'','BTREE',''),('host_template_snmp_query',1,'host_template_id',1,'host_template_id','A',2,NULL,NULL,'','BTREE',''),('host_template_snmp_query',0,'PRIMARY',1,'host_template_id','A',2,NULL,NULL,'','BTREE',''),('host_template_snmp_query',0,'PRIMARY',2,'snmp_query_id','A',2,NULL,NULL,'','BTREE',''),('plugin_config',1,'directory',1,'directory','A',0,NULL,NULL,'','BTREE',''),('plugin_config',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('plugin_config',1,'status',1,'status','A',0,NULL,NULL,'','BTREE',''),('plugin_db_changes',1,'method',1,'method','A',0,NULL,NULL,'','BTREE',''),('plugin_db_changes',1,'plugin',1,'plugin','A',0,NULL,NULL,'','BTREE',''),('plugin_db_changes',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('plugin_hooks',1,'hook',1,'hook','A',2,NULL,NULL,'','BTREE',''),('plugin_hooks',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('plugin_hooks',1,'status',1,'status','A',2,NULL,NULL,'','BTREE',''),('plugin_realms',1,'plugin',1,'plugin','A',1,NULL,NULL,'','BTREE',''),('plugin_realms',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('poller',1,'disabled',1,'disabled','A',1,NULL,NULL,'YES','BTREE',''),('poller',1,'name',1,'name','A',1,NULL,NULL,'YES','BTREE',''),('poller',0,'PRIMARY',1,'id','A',1,NULL,NULL,'','BTREE',''),('poller_command',1,'poller_id_last_updated',1,'poller_id','A',0,NULL,NULL,'','BTREE',''),('poller_command',1,'poller_id_last_updated',2,'last_updated','A',0,NULL,NULL,'','BTREE',''),('poller_command',0,'PRIMARY',1,'poller_id','A',0,NULL,NULL,'','BTREE',''),('poller_command',0,'PRIMARY',2,'action','A',0,NULL,NULL,'','BTREE',''),('poller_command',0,'PRIMARY',3,'command','A',0,NULL,NULL,'','BTREE',''),('poller_data_template_field_mappings',0,'PRIMARY',1,'data_template_id','A',2,NULL,NULL,'','BTREE',''),('poller_data_template_field_mappings',0,'PRIMARY',2,'data_name','A',2,NULL,NULL,'','BTREE',''),('poller_data_template_field_mappings',0,'PRIMARY',3,'data_source_names','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'action',1,'action','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'host_id',1,'host_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_action',1,'poller_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_action',2,'action','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_host_id',1,'poller_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_host_id',2,'host_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_last_updated',1,'poller_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_last_updated',2,'last_updated','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_rrd_next_step',1,'poller_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'poller_id_rrd_next_step',2,'rrd_next_step','A',2,NULL,NULL,'','BTREE',''),('poller_item',1,'present',1,'present','A',2,NULL,NULL,'','BTREE',''),('poller_item',0,'PRIMARY',1,'local_data_id','A',2,NULL,NULL,'','BTREE',''),('poller_item',0,'PRIMARY',2,'rrd_name','A',2,NULL,NULL,'','BTREE',''),('poller_output',0,'PRIMARY',1,'local_data_id','A',NULL,NULL,NULL,'','BTREE',''),('poller_output',0,'PRIMARY',2,'rrd_name','A',NULL,NULL,NULL,'','BTREE',''),('poller_output',0,'PRIMARY',3,'time','A',NULL,NULL,NULL,'','BTREE',''),('poller_output_boost',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('poller_output_boost',0,'PRIMARY',2,'time','A',0,NULL,NULL,'','BTREE',''),('poller_output_boost',0,'PRIMARY',3,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('poller_output_boost_processes',0,'PRIMARY',1,'sock_int_value',NULL,0,NULL,NULL,'','HASH',''),('poller_output_realtime',1,'poller_id',1,'poller_id','A',0,'191',NULL,'','BTREE',''),('poller_output_realtime',0,'PRIMARY',1,'local_data_id','A',0,NULL,NULL,'','BTREE',''),('poller_output_realtime',0,'PRIMARY',2,'rrd_name','A',0,NULL,NULL,'','BTREE',''),('poller_output_realtime',0,'PRIMARY',3,'time','A',0,NULL,NULL,'','BTREE',''),('poller_output_realtime',1,'time',1,'time','A',0,NULL,NULL,'','BTREE',''),('poller_reindex',1,'present',1,'present','A',0,NULL,NULL,'','BTREE',''),('poller_reindex',0,'PRIMARY',1,'host_id','A',0,NULL,NULL,'','BTREE',''),('poller_reindex',0,'PRIMARY',2,'data_query_id','A',0,NULL,NULL,'','BTREE',''),('poller_resource_cache',0,'path',1,'path','A',0,NULL,NULL,'YES','BTREE',''),('poller_resource_cache',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('poller_time',1,'poller_id_end_time',1,'poller_id','A',0,NULL,NULL,'','BTREE',''),('poller_time',1,'poller_id_end_time',2,'end_time','A',0,NULL,NULL,'','BTREE',''),('poller_time',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('reports',1,'mailtime',1,'mailtime','A',0,NULL,NULL,'','BTREE',''),('reports',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('reports_items',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('reports_items',1,'report_id',1,'report_id','A',0,NULL,NULL,'','BTREE',''),('sessions',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('settings',0,'PRIMARY',1,'name','A',19,NULL,NULL,'','BTREE',''),('settings_tree',0,'PRIMARY',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('settings_tree',0,'PRIMARY',2,'graph_tree_item_id','A',0,NULL,NULL,'','BTREE',''),('settings_user',0,'PRIMARY',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('settings_user',0,'PRIMARY',2,'name','A',0,NULL,NULL,'','BTREE',''),('settings_user_group',0,'PRIMARY',1,'group_id','A',0,NULL,NULL,'','BTREE',''),('settings_user_group',0,'PRIMARY',2,'name','A',0,NULL,NULL,'','BTREE',''),('sites',1,'alternate_id',1,'alternate_id','A',0,NULL,NULL,'YES','BTREE',''),('sites',1,'city',1,'city','A',0,NULL,NULL,'YES','BTREE',''),('sites',1,'country',1,'country','A',0,NULL,NULL,'YES','BTREE',''),('sites',1,'name',1,'name','A',0,NULL,NULL,'','BTREE',''),('sites',1,'postal_code',1,'postal_code','A',0,NULL,NULL,'YES','BTREE',''),('sites',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('sites',1,'state',1,'state','A',0,NULL,NULL,'YES','BTREE',''),('snmpagent_cache',1,'mib_name',1,'mib','A',5,NULL,NULL,'','BTREE',''),('snmpagent_cache',1,'mib_name',2,'name','A',147,NULL,NULL,'','BTREE',''),('snmpagent_cache',1,'name',1,'name','A',147,NULL,NULL,'','BTREE',''),('snmpagent_cache',0,'PRIMARY',1,'oid','A',147,NULL,NULL,'','BTREE',''),('snmpagent_cache_notifications',1,'name',1,'name','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_notifications',0,'PRIMARY',1,'name','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_notifications',0,'PRIMARY',2,'mib','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_notifications',0,'PRIMARY',3,'attribute','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_notifications',0,'PRIMARY',4,'sequence_id','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_textual_conventions',1,'mib',1,'mib','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_textual_conventions',1,'name',1,'name','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_textual_conventions',0,'PRIMARY',1,'name','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_textual_conventions',0,'PRIMARY',2,'mib','A',2,NULL,NULL,'','BTREE',''),('snmpagent_cache_textual_conventions',0,'PRIMARY',3,'type','A',2,NULL,NULL,'','BTREE',''),('snmpagent_managers',1,'hostname',1,'hostname','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',1,'manager_id_notification',1,'manager_id','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',1,'manager_id_notification',2,'notification','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',1,'mib',1,'mib','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',0,'PRIMARY',1,'manager_id','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',0,'PRIMARY',2,'notification','A',0,NULL,NULL,'','BTREE',''),('snmpagent_managers_notifications',0,'PRIMARY',3,'mib','A',0,NULL,NULL,'','BTREE',''),('snmpagent_mibs',0,'PRIMARY',1,'id','A',3,NULL,NULL,'','BTREE',''),('snmpagent_notifications_log',1,'manager_id_notification',1,'manager_id','A',0,NULL,NULL,'','BTREE',''),('snmpagent_notifications_log',1,'manager_id_notification',2,'notification','A',0,NULL,NULL,'','BTREE',''),('snmpagent_notifications_log',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('snmpagent_notifications_log',1,'severity',1,'severity','A',0,NULL,NULL,'','BTREE',''),('snmpagent_notifications_log',1,'time',1,'time','A',0,NULL,NULL,'','BTREE',''),('snmp_query',1,'name',1,'name','A',2,NULL,NULL,'','BTREE',''),('snmp_query',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('snmp_query_graph',1,'graph_template_id_name',1,'graph_template_id','A',22,NULL,NULL,'','BTREE',''),('snmp_query_graph',1,'graph_template_id_name',2,'name','A',22,NULL,NULL,'','BTREE',''),('snmp_query_graph',0,'PRIMARY',1,'id','A',22,NULL,NULL,'','BTREE',''),('snmp_query_graph',1,'snmp_query_id_name',1,'snmp_query_id','A',11,NULL,NULL,'','BTREE',''),('snmp_query_graph',1,'snmp_query_id_name',2,'name','A',22,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd',1,'data_template_rrd_id',1,'data_template_rrd_id','A',46,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd',0,'PRIMARY',1,'snmp_query_graph_id','A',46,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd',0,'PRIMARY',2,'data_template_id','A',46,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd',0,'PRIMARY',3,'data_template_rrd_id','A',46,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd',1,'snmp_query_graph_id',1,'snmp_query_graph_id','A',46,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd_sv',1,'data_template_id',1,'data_template_id','A',8,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd_sv',0,'PRIMARY',1,'id','A',75,NULL,NULL,'','BTREE',''),('snmp_query_graph_rrd_sv',1,'snmp_query_graph_id',1,'snmp_query_graph_id','A',37,NULL,NULL,'','BTREE',''),('snmp_query_graph_sv',0,'PRIMARY',1,'id','A',64,NULL,NULL,'','BTREE',''),('snmp_query_graph_sv',1,'snmp_query_graph_id',1,'snmp_query_graph_id','A',32,NULL,NULL,'','BTREE',''),('table_columns',0,'PRIMARY',1,'table_name','A',1159,NULL,NULL,'','BTREE',''),('table_columns',0,'PRIMARY',2,'table_sequence','A',1159,NULL,NULL,'','BTREE',''),('table_columns',0,'PRIMARY',3,'table_field','A',1159,NULL,NULL,'','BTREE',''),('table_indexes',0,'PRIMARY',1,'idx_table_name','A',58,NULL,NULL,'','BTREE',''),('table_indexes',0,'PRIMARY',2,'idx_key_name','A',58,NULL,NULL,'','BTREE',''),('table_indexes',0,'PRIMARY',3,'idx_seq_in_index','A',58,NULL,NULL,'','BTREE',''),('table_indexes',0,'PRIMARY',4,'idx_column_name','A',58,NULL,NULL,'','BTREE',''),('user_auth',1,'enabled',1,'enabled','A',2,NULL,NULL,'','BTREE',''),('user_auth',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('user_auth',1,'realm',1,'realm','A',2,NULL,NULL,'','BTREE',''),('user_auth',1,'username',1,'username','A',2,NULL,NULL,'','BTREE',''),('user_auth_cache',1,'hostname',1,'hostname','A',0,NULL,NULL,'','BTREE',''),('user_auth_cache',1,'last_update',1,'last_update','A',0,NULL,NULL,'','BTREE',''),('user_auth_cache',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('user_auth_cache',0,'tokenkey',1,'token','A',0,NULL,NULL,'','BTREE',''),('user_auth_cache',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group',0,'PRIMARY',1,'id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_members',0,'PRIMARY',1,'group_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_members',0,'PRIMARY',2,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_members',1,'realm_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_perms',1,'group_id',1,'group_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_perms',1,'group_id',2,'type','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_perms',0,'PRIMARY',1,'group_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_perms',0,'PRIMARY',2,'item_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_perms',0,'PRIMARY',3,'type','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_realm',0,'PRIMARY',1,'group_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_realm',0,'PRIMARY',2,'realm_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_group_realm',1,'realm_id',1,'realm_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_perms',0,'PRIMARY',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_perms',0,'PRIMARY',2,'item_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_perms',0,'PRIMARY',3,'type','A',0,NULL,NULL,'','BTREE',''),('user_auth_perms',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_auth_perms',1,'user_id',2,'type','A',0,NULL,NULL,'','BTREE',''),('user_auth_realm',0,'PRIMARY',1,'realm_id','A',23,NULL,NULL,'','BTREE',''),('user_auth_realm',0,'PRIMARY',2,'user_id','A',23,NULL,NULL,'','BTREE',''),('user_auth_realm',1,'user_id',1,'user_id','A',4,NULL,NULL,'','BTREE',''),('user_domains',0,'PRIMARY',1,'domain_id','A',0,NULL,NULL,'','BTREE',''),('user_domains_ldap',0,'PRIMARY',1,'domain_id','A',0,NULL,NULL,'','BTREE',''),('user_log',0,'PRIMARY',1,'username','A',0,NULL,NULL,'','BTREE',''),('user_log',0,'PRIMARY',2,'user_id','A',0,NULL,NULL,'','BTREE',''),('user_log',0,'PRIMARY',3,'time','A',0,NULL,NULL,'','BTREE',''),('user_log',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE',''),('vdef',1,'hash',1,'hash','A',2,NULL,NULL,'','BTREE',''),('vdef',1,'name',1,'name','A',2,'171',NULL,'','BTREE',''),('vdef',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('vdef_items',0,'PRIMARY',1,'id','A',2,NULL,NULL,'','BTREE',''),('vdef_items',1,'vdef_id_sequence',1,'vdef_id','A',2,NULL,NULL,'','BTREE',''),('vdef_items',1,'vdef_id_sequence',2,'sequence','A',2,NULL,NULL,'','BTREE',''),('version',0,'PRIMARY',1,'cacti','A',1,NULL,NULL,'','BTREE','');";
}
