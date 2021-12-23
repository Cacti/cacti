#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
chdir('..');

if ($config['poller_id'] > 1) {
	print "FATAL: This utility is designed for the main Data Collector only" . PHP_EOL;
	exit(1);
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$upgrade    = false;
$create     = false;
$loadopt    = false;
$report     = false;
$repair     = false;
$altersopt  = false;

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = array(
		'create',
		'load',
		'report',
		'upgrade',
		'repair',
		'alters',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		switch($arg) {
		case 'create':
			$create = true;
			break;

		case 'load':
			$loadopt = true;

			break;
		case 'report':
			$report = true;

			break;
		case 'repair':
			$repair = true;

			break;
		case 'alters':
			$altersopt = true;

			break;
		case 'upgrade':
			$upgrade = true;

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

	$db_version = substring_index(db_fetch_cell('SELECT cacti FROM version'), '.', 3);
	if ($db_version != CACTI_VERSION && !isset($options['upgrade'])) {
		$upgrade_required = true;
	} else {
		$upgrade_required = false;
	}

	if ($upgrade_required) {
		print 'WARNING: Cacti must be upgraded first.  Use the --upgrade option to perform that upgrade' . PHP_EOL;
		exit(1);
	} elseif ($db_version != CACTI_VERSION && $upgrade) {
		upgrade_database();
	}

	if ($repair) {
		repair_database();
	} elseif ($create) {
		create_tables();
	} elseif ($report) {
		report_audit_results();
	} elseif ($altersopt) {
		repair_database(false);
	} elseif ($loadopt) {
		load_audit_database();
	} else {
		display_help();
	}

	exit(0);
} else {
	display_help();
	exit(1);
}

function upgrade_database() {
	global $config;

	$start = microtime(true);

	cacti_log('NOTE: Upgrading Cacti, this will take a few minutes.', true, 'UPGRADE');

	$return_var = 0;
	$output     = array();

	exec('php ' . $config['base_path'] . '/cli/upgrade_database.php --debug', $output, $return_var);

	$end = microtime(true);

	if ($return_var == 0) {
		cacti_log(sprintf('NOTE: Cacti Upgrade succeeded in %.2f seconds', $end - $start), true, 'UPGRADE');
	} else {
		cacti_log('WARNING: Cacti Upgrade Encountered Errors.  Messages below.  Details are below, but also in Cacti upgrade log.', true, 'UPGRADE');
		print '---------------------------------------------------------------------------------------------' . PHP_EOL;
		print implode(PHP_EOL, $output) . PHP_EOL;
		print '---------------------------------------------------------------------------------------------' . PHP_EOL;
	}

	$pistart = microtime(true);

	// Upgrade plugins now
	$plugins = glob($config['base_path'] . '/plugins/*', GLOB_ONLYDIR);

	// Do syslog and thold first if found
	$preorder[] = $config['base_path'] . '/plugins/thold';
	$preorder[] = $config['base_path'] . '/plugins/syslog';

	foreach($plugins as $p) {
		if (strpos($p, 'thold') !== false) {
			// Skip, upgrading this first
		} elseif (strpos($p, 'syslog') !== false) {
			// Skip, upgrading this second
		} else {
			$preorder[] = $p;
		}
	}

	$plugins = $preorder;

	if (cacti_sizeof($plugins)) {
		if (!defined('IN_PLUGIN_INSTALL')) {
			define('IN_PLUGIN_INSTALL', 1);
		}

		foreach($plugins as $plugin) {
			$parts = explode('/', $plugin);
			$pname = end($parts);
			$ufunc1 = 'plugin_' . $pname . '_upgrade';
			$ufunc2 = $pname . '_upgrade_database';
			$ufunc3 = $pname . '_setup_table_new';

			if (!plugin_installed($pname)) {
				cacti_log("NOTE: Plugin $pname is not installed, skipping.", true, 'UPGRADE');

				continue;
			}

			if (file_exists($plugin . '/INFO')) {
				// See if the plugin requires upgrading
				$info    = parse_ini_file($plugin . '/INFO', true);
				$version = $info['info']['version'];

				$old = db_fetch_cell_prepared('SELECT version
					FROM plugin_config
					WHERE directory = ?',
					array($pname));

				if ($version != $old) {
					if (file_exists($plugin . '/setup.php')) {
						include_once($plugin . '/setup.php');
						if (file_exists($plugin . '/includes/database.php')) {
							include_once($plugin . '/includes/database.php');
						}

						// Always run the new function if it's there
						// Some plugins don't upgrade in the proper way
						if (function_exists($ufunc3)) {
							cacti_log("NOTE: Running Plugin $pname install function due to some plugins not upgrading properly.", true, 'UPGRADE');
							$ufunc3(true);
						}

						if (function_exists($ufunc2)) {
							cacti_log("NOTE: Upgrading Plugin $pname from $old to $version using alternate upgrade path.", true, 'UPGRADE');
							$ufunc2(true);
						} elseif (function_exists($ufunc1)) {
							cacti_log("NOTE: Upgrading Plugin $pname from $old to $version using standard upgrade path.", true, 'UPGRADE');
							$ufunc1;
						} else {
							cacti_log("WARNING: Plugin $pname lacks an upgrade function.", true, 'UPGRADE');
						}

						if (file_exists($plugin . '/database_upgrade.php')) {
							cacti_log("NOTE: Upgrading Plugin $pname from $old to $version using upgrade script.", true, 'UPGRADE');
							$return_var = 0;
							$output     = array();

							exec('php ' . $config['base_path'] . '/plugins/' . $pname . '/database_upgrade.php --type=large --force-ver=' . $old, $output, $return_var);

							if ($return_var == 0) {
								print implode(PHP_EOL, $output) . PHP_EOL;
								cacti_log("NOTE: Cacti Plugin $pname Upgrade Succeeded.", true, 'UPGRADE');
								print '---------------------------------------------------------------------------------------------' . PHP_EOL;
								print implode(PHP_EOL, $output) . PHP_EOL;
								print '---------------------------------------------------------------------------------------------' . PHP_EOL;
							} else {
								cacti_log("WARNING: Cacti Plugin $pname Upgrade Encountered Errors.", true, 'UPGRADE');
								print '---------------------------------------------------------------------------------------------' . PHP_EOL;
								print implode(PHP_EOL, $output) . PHP_EOL;
								print '---------------------------------------------------------------------------------------------' . PHP_EOL;
							}
						}
					} else {
						cacti_log("WARNING: Plugin $pname lacks a setup file.", true, 'UPGRADE');
					}
				} else {
					cacti_log("NOTE: Plugin $pname Does not Require Upgrade", true, 'UPGRADE');
				}
			} else {
				cacti_log("WARNING: Plugin $pname lacks an INFO file.  Can not upgrade!", true, 'UPGRADE');
			}
		}
	}

	// Unregister plugins that no longer exist
	// We keep legacy tables due to potential
	// issues.

	print '---------------------------------------------------------------------------------------------' . PHP_EOL;
	cacti_log('NOTE: Pruning invalid and deprecated plugins while preserving tables', true, 'UPGRADE');

	$plugins = db_fetch_assoc('SELECT directory FROM plugin_config');
	if (cacti_sizeof($plugins)) {
		foreach($plugins as $p) {
			$pname = $p['directory'];

			if (!file_exists($config['base_path'] . '/plugins/' . $pname . '/INFO')) {
				if (file_exists($config['base_path'] . '/plugins/' . $pname . '/setup.php')) {
					cacti_log("NOTE: Uninstalling Plugin $pname which is not supported.  Preserving tables.", true, 'UPGRADE');

					api_plugin_uninstall($pname, false);
				} else {
					cacti_log("NOTE: Uninstalling Plugin $pname which is not supported and setup.php not found.  Preserving tables.", true, 'UPGRADE');
					db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', array($pname));
					db_execute_prepared('DELETE FROM plugin_db_changes WHERE plugin = ?', array($pname));
					db_execute_prepared('DELETE FROM plugin_hooks WHERE name = ?', array($pname));
					db_execute_prepared('DELETE FROM plugin_realms WHERE plugin = ?', array($pname));
				}
			}
		}
	}
	print '---------------------------------------------------------------------------------------------' . PHP_EOL;

	$end = microtime(true);

	cacti_log(sprintf('NOTE: Cacti Plugin Upgrades completed in %.2f seconds', $end - $pistart), true, 'UPGRADE');

	cacti_log(sprintf('NOTE: Audit Upgrade completed in %.2f seconds.', $end - $start), true, 'UPGRADE');
}

function plugin_installed($plugin) {
	$installed = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_config
		WHERE directory = ?
		AND status = 1',
		array($plugin));

	return $installed ? true:false;
}

function repair_database($run = true) {
	global $altersopt;

	$alters = report_audit_results(false);

	if (!db_has_permissions(array('ALTER', 'DROP','INSERT','LOCK TABLES'))) {
		echo "ERROR: Required a required permission is missing for DB repair" . PHP_EOL;
		exit(1);
	}

	$good = 0;
	$bad = 0;

	if (cacti_sizeof($alters)) {
		foreach($alters as $table => $changes) {
			$tblinfo = db_fetch_row_prepared('SELECT ENGINE, SUBSTRING_INDEX(TABLE_COLLATION, "_", 1) AS COLLATION
				FROM information_schema.tables
				WHERE TABLE_SCHEMA="cacti"
				AND TABLE_NAME = ?',
				array($table));

			if ($tblinfo['ENGINE'] = 'MyISAM') {
				$suffix = ",\n   ENGINE=InnoDB ROW_FORMAT=Dynamic CHARSET=" . $tblinfo['COLLATION'];
			} else {
				$suffix = ",\n   ROW_FORMAT=Dynamic CHARSET=" . $tblinfo['COLLATION'];
			}

			$sql = 'ALTER TABLE `' . $table . "`\n   " . implode(",\n   ", $changes) . $suffix . ';';

			if ($run) {
				print '---------------------------------------------------------------------------------------------' . PHP_EOL;
				print 'Executing Alter for Table : ' . $table;

				$result = db_execute($sql);

				if ($result) {
					$good++;
					print ' - Success' . PHP_EOL;
				} else {
					$bad++;
					print ' - Failed' . PHP_EOL;
					print $sql . PHP_EOL;
				}
			} else {
				print '---------------------------------------------------------------------------------------------' . PHP_EOL;
				print '-- Proposed Alter for Table : ' . $table . PHP_EOL . PHP_EOL;
				print $sql . PHP_EOL . PHP_EOL;
			}
		}
	}

	print '---------------------------------------------------------------------------------------------' . PHP_EOL;
	if ($bad == 0 && $good == 0) {
		print ($altersopt ? '-- ' : '') . 'Repair Completed!  No changes performed.' . PHP_EOL;
	} elseif ($bad) {
		print 'Repair Completed!  ' . $good . ' Alters succeeded and ' . $bad . ' failed!' . PHP_EOL;
	} else {
		print 'Repair Completed!  All ' . $good . ' Alters succeeded!' . PHP_EOL;
	}
}

function report_audit_results($output = true) {
	global $database_default, $altersopt;

	$db_name = 'Tables_in_' . $database_default;

	create_tables();

	$tables = db_fetch_assoc('SHOW TABLES');

	$alters  = array();

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

			$status  = db_fetch_row('SHOW TABLE STATUS LIKE "' . $table_name . '"');

			if ($status['Collation'] == 'utf8mb4_unicode_ci' || $status['Collation'] == 'utf8_general_ci') {
				$text = 'mediumtext';
			} else {
				$text = 'text';
			}

			if ($output) {
				print '---------------------------------------------------------------------------------------------' . PHP_EOL;
				printf('Checking Table: %-45s', '\'' . $table_name . '\'');
			} else {
				printf(($altersopt ? '-- ' : '') . 'Scanning Table: %-45s', '\'' . $table_name . '\'');
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
				} else {
					print ' - Completed' . PHP_EOL;
				}
			} elseif (!$output) {
				print ' - Completed' . PHP_EOL;
			}

			/* Column scanning comes in two parts.  In the first part, we
			 * scan the columns in the current database to the saved schema
			 * In the second pass, we look for the columns from the saved
			 * schema to look for missing ones.
			 */

			$i = 1;
			$errors = 0;
			$warnings = 0;
			$col_added = array();
			$col_alter = array();

			$columns = db_fetch_assoc('SHOW COLUMNS IN ' . $table_name);
			$exists  = db_fetch_cell_prepared('SELECT COUNT(*) FROM table_columns
				WHERE table_name = ?',
				array($table_name));

			if ($exists) {
				if (cacti_sizeof($columns)) {
					foreach($columns as $c) {
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

								/* work around MariaDB compatibility issue */
								$c[$col]     = ! $c[$col] ?: str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $c[$col]);
								$dbc[$dbcol] = ! $dbc[$dbcol] ?: str_replace('current_timestamp()', 'CURRENT_TIMESTAMP',$dbc[$dbcol]);

								/* work around MySQL 8.x simplified int columns */
								if (strpos($dbc[$dbcol], 'int(') !== false) {
									// Get the integer first
									$parts = explode('(', $dbc[$dbcol]);
									$adbccol = $parts[0];

									// Get attributes next
									$parts = explode(' ', $parts[1], 2);
									if (isset($parts[1])) {
										$adbccol .= ' ' . $parts[1];
									}

									$adbccol = trim($adbccol);
								} else {
									$adbccol = $dbc[$dbcol];
								}

								/* Work Around for MySQL 8 */
								$c[$col] = trim(str_replace('DEFAULT_GENERATED', '', $c[$col]));

								if (($c[$col] != $dbc[$dbcol] && $c[$col] != $adbccol) && $c[$col] != 'mediumtext') {
									if ($output) {
										if ($col != 'Key') {
											print PHP_EOL . 'ERROR Col: \'' . $c['Field'] . '\', Attribute \'' . $col . '\' invalid. Should be: \'' . $dbc[$dbcol] . '\', Is: \'' . $c[$col] . '\'';
										}
									}

									if (array_search($dbc['table_field'], $col_alter) === false) {
										$alter_cmds[] = make_column_alter($table_name, $dbc);
										$col_alter[]  = $dbc['table_field'];
										$errors++;
									}
								}
							}
						}

						$i++;
					}
				}

				/* In this pass, we will gather the default schema and look for
				 * missing information.
				 */
				$db_columns = db_fetch_assoc_prepared('SELECT *
					FROM table_columns
					WHERE table_name = ?',
					array($table_name));

				if (cacti_sizeof($db_columns)) {
					foreach($db_columns as $dbc) {
						if (!db_column_exists($table_name, $dbc['table_field'])) {
							if (array_search($dbc['table_field'], $col_added) === false) {
								if ($output) {
									print PHP_EOL . 'WARNING Col: \'' . $dbc['table_field'] . '\' is missing from \'' . $table_name . '\'';
								}

								$alter_cmds[] = make_column_add($table_name, $dbc);
								$col_added[] = $dbc['table_field'];
								$errors++;
							}
						}
					}
				}

				/* Index scanning comes in two parts.  In the first part, we
				 * scan the indexes in the current database to the saved schema
				 * In the second pass, we look for the indexes from the saved
				 * schema to look for missing ones.
				 */

				$indexes = db_fetch_assoc('SHOW INDEXES IN ' . $table_name);

				$idx_added = array();
				$idx_dropped = array();

				if (cacti_sizeof($indexes)) {
					foreach($indexes as $i) {
						$key_exists = db_fetch_cell_prepared('SELECT COUNT(*)
							FROM table_indexes
							WHERE idx_table_name = ?
							AND idx_key_name = ?',
							array($i['Table'], $i['Key_name']));

						$dbc = db_fetch_row_prepared('SELECT *
							FROM table_indexes
							WHERE idx_table_name = ?
							AND idx_key_name = ?
							AND idx_seq_in_index = ?
							AND idx_column_name = ?
							ORDER BY idx_seq_in_index',
							array($i['Table'], $i['Key_name'], $i['Seq_in_index'], $i['Column_name']));

						if (!cacti_sizeof($dbc)) {
							if ($key_exists) {
								// Ignore till Phase II
							} elseif (array_search($i['Key_name'], $idx_dropped) === false) {
								// Primary keys come in Phase II
								if ($i['Key_name'] != 'PRIMARY') {
									if ($output) {
										print PHP_EOL . 'WARNING Index: \'' . $i['Key_name'] . '\', does not exist in default Cacti.  Dropping.';
									}

									$alter_cmds[]  = 'DROP INDEX ' . $i['Key_name'];
									$idx_dropped[] = $i['Key_name'];
									$errors++;
								}
							}
						} else {
							foreach($idxs as $dbidx => $idx) {
								if ($i[$idx] != $dbc[$dbidx]) {
									// Primary keys come in Phase II
									if ($i['Key_name'] != 'PRIMARY') {
										if (array_search($i['Key_name'], $idx_added) === false) {
											if ($output) {
												print PHP_EOL . 'ERROR Index: \'' . $i['Key_name'] . '\', Attribute \'' . $idx . '\' invalid. Should be: \'' . $dbc[$dbidx] . '\', Is: \'' . $i[$idx] . '\'';
											}

											$alter_cmds = array_merge($alter_cmds, make_index_alter($table_name, $i['Key_name']));
											$idx_added[] = $i['Key_name'];
											$errors++;
										}
									}
								}
							}
						}
					}
				}

				/* check for missing indexes and add them */

				$db_indexes = db_fetch_assoc_prepared('SELECT *
					FROM table_indexes
					WHERE idx_table_name = ?',
					array($table_name));

				if (cacti_sizeof($db_indexes)) {
					foreach($db_indexes as $i) {
						if (!db_index_exists($table_name, $i['idx_key_name'])) {
							if (array_search($i['idx_key_name'], $idx_added) === false) {
								if ($output) {
									print PHP_EOL . 'ERROR Index: \'' . $i['idx_key_name'] . '\', is missing from \'' . $table_name . '\'';;
								}

								$alter_cmds = array_merge($alter_cmds, make_index_alter($table_name, $i['idx_key_name']));
								$idx_added[] = $i['idx_key_name'];
								$errors++;
							}
						} else {
							$prop_seq = db_fetch_cell_prepared('SELECT COUNT(*)
								FROM table_indexes
								WHERE idx_table_name = ?
								AND idx_key_name = ?',
								array($table_name, $i['idx_key_name']));

							$curr_seq = get_sequence_count($table_name, $i['idx_key_name']);

							$curr_column_seq = get_column_sequence_number($table_name, $i['idx_key_name'], $i['idx_column_name']);

							//print PHP_EOL . "Prop Seq:" . $prop_seq . ", Curr Seq:" . $curr_seq . PHP_EOL;

							if ($curr_seq != $prop_seq || $curr_column_seq != $i['idx_seq_in_index']) {
								if (array_search($i['idx_key_name'], $idx_dropped) === false) {
									if ($output) {
										if ($curr_seq != $prop_seq) {
											print PHP_EOL . 'WARNING Index: \'' . $i['idx_key_name'] . '\', has differing number of columns.  Dropping.';
										}

										if ($curr_column_seq != $i['idx_seq_in_index']) {
											print PHP_EOL . 'WARNING Index: \'' . $i['idx_key_name'] . '\', has resequenced columns.  Dropping.';
										}
									}

									$alter_cmds = array_merge($alter_cmds, make_index_alter($table_name, $i['idx_key_name']));
									$idx_added[]   = $i['idx_key_name'];
									$idx_dropped[] = $i['idx_key_name'];
									$errors++;
								}
							}
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

				if (cacti_sizeof($alter_cmds)) {
					$alters[$table_name] = $alter_cmds;
				}
			}
		}
	}

	if ($output) {
		print '---------------------------------------------------------------------------------------------' . PHP_EOL;
		if (cacti_sizeof($alters)) {
			print 'ERRORS are fixable using the --repair option.  WARNINGS will not be repaired' . PHP_EOL;
			print 'due to ambiguous use of the column.' . PHP_EOL;
		} else {
			print 'Audit was clean, no errors or warnings' . PHP_EOL;
		}
		print '---------------------------------------------------------------------------------------------' . PHP_EOL;
	}

	return $alters;
}

function make_column_props(&$dbc) {
	$alter_cmd = '';

	$dbc['table_default'] = str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $dbc['table_default']);
	$dbc['table_extra']   = str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $dbc['table_extra']);
	$dbc['table_extra']   = trim(str_replace('DEFAULT_GENERATED', '', $dbc['table_extra']));

	if ($dbc['table_null'] == 'YES') {
		if ($dbc['table_default'] == 'NULL') {
			// Ignore
		} elseif ($dbc['table_default'] === NULL) {
			// Ignore
		} elseif ($dbc['table_default'] === '') {
			// Ignore
		} else {
			$alter_cmd .= ' DEFAULT "' . $dbc['table_default'] . '"';
		}
	} elseif ($dbc['table_default'] !== 'NULL' && $dbc['table_default'] !== NULL) {
		if ($dbc['table_default'] == 'CURRENT_TIMESTAMP') {
			$alter_cmd .= ' DEFAULT CURRENT_TIMESTAMP';
		} elseif ($dbc['table_extra'] != 'auto_increment') {
			if (strpos($dbc['table_type'], 'int(') !== false && $dbc['table_default'] == '') {
				$alter_cmd .= ' DEFAULT "0"';
			} else {
				$alter_cmd .= ' DEFAULT \'' . $dbc['table_default'] . '\'';
			}
		}
	}

	if ($dbc['table_extra'] != '') {
		$alter_cmd .= ' ' . $dbc['table_extra'];
	}

	return $alter_cmd;
}

function make_column_alter($table, $dbc) {
	$alter_cmd = 'MODIFY COLUMN `' . $dbc['table_field'] . '` ' .
		$dbc['table_type'] . ($dbc['table_null'] == 'NO' ? ' NOT NULL':'');

	$alter_cmd .= make_column_props($dbc);

	return $alter_cmd;
}

function make_column_add($table, $dbc) {
	$after = get_previous_column($table, $dbc['table_field']);
	if ($after != 'first') {
		$after = 'AFTER `' . $after . '`';
	}

	$alter_cmd = 'ADD COLUMN `' . $dbc['table_field'] . '` ' .
		$dbc['table_type'] . ($dbc['table_null'] == 'NO' ? ' NOT NULL':'');

	$alter_cmd .= make_column_props($dbc);

	$alter_cmd .= ' ' . $after;

	return $alter_cmd;
}

function get_previous_column($table, $column) {
	$sequence = db_fetch_cell_prepared('SELECT table_sequence
		FROM table_columns
		WHERE table_name = ?
		AND table_field = ?',
		array($table, $column));

	if (!empty($sequence)) {
		if ($sequence == 1) {
			return 'first';
		} else {
			$previous = db_fetch_cell_prepared('SELECT table_field
				FROM table_columns
				WHERE table_name = ?
				AND table_sequence = ?',
				array($table, $sequence - 1));

			return $previous;
		}
	}
}

function make_index_alter($table, $key) {
	$alter_cmds = array();
	$alter_cmd  = '';
	$primary_dropped = false;

	$parts = db_fetch_assoc_prepared('SELECT *
		FROM table_indexes
		WHERE idx_table_name = ?
		AND idx_key_name = ?
		ORDER BY idx_seq_in_index',
		array($table, $key));

	$sequence_cnt = get_sequence_count($table, $key);

	//print PHP_EOL . "NOTE: INDEX KEY is $key, Baseline Sequence: " . cacti_sizeof($parts) . ", Actual Sequence: $sequence_cnt" . PHP_EOL;

	if ($sequence_cnt != cacti_sizeof($parts) && $sequence_cnt > 0) {
		if ($key == 'PRIMARY') {
			$primary_dropped = true;
			$alter_cmd .= "DROP PRIMARY KEY,\n   ";
		} else {
			$alter_cmd .= "DROP INDEX `" . $key . "`,\n   ";
		}
	} elseif (db_index_exists($table, $key)) {
		if ($key == 'PRIMARY') {
			$primary_dropped = true;
			$alter_cmd .= "DROP PRIMARY KEY,\n   ";
		} else {
			$alter_cmd .= "DROP INDEX `" . $key . "`,\n   ";
		}
	}

	if (cacti_sizeof($parts)) {
		$i = 0;

		foreach($parts as $p) {
			if ($i == 0 && $p['idx_key_name'] == 'PRIMARY') {
				if ($primary_dropped == false) {
					$alter_cmd .= "DROP PRIMARY KEY,\n   ";
				}
				$alter_cmd .= 'ADD PRIMARY KEY (';
			} elseif ($i == 0) {
				if ($p['idx_non_unique'] == 1) {
					$alter_cmd .= 'ADD INDEX `' . $key . '` (';
				} else {
					$alter_cmd .= 'ADD UNIQUE INDEX `' . $key . '` (';
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

function get_sequence_count($table, $index) {
	$indexes = db_fetch_assoc("SHOW INDEXES IN $table");
	$sequence_cnt = 0;

	if (cacti_sizeof($indexes)) {
		foreach($indexes as $i) {
			if ($index == $i['Key_name']) {
				$sequence_cnt++;
			}
		}
	}

	return $sequence_cnt;
}

function get_column_sequence_number($table, $index, $column) {
	$indexes = db_fetch_assoc("SHOW INDEXES IN $table");

	if (cacti_sizeof($indexes)) {
		foreach($indexes as $i) {
			$sequence = $i['Seq_in_index'];

			if ($i['Key_name'] == $index) {
				if ($i['Column_name'] == $column) {
					return $sequence;
				}
			}
		}
	}

	return -1;
}

function create_tables($load = true) {
	global $config, $database_default, $database_username, $database_password, $database_port, $database_hostname;
	global $altersopt;

	if (!db_has_permissions('CREATE')) {
		echo "ERROR: Unable to create audit tables, permission required" . PHP_EOL;
		exit(1);
	}

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
		print "Failed to create 'table_columns'";
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
		if (!db_has_permissions(array('DROP','INSERT','LOCK TABLES'))) {
			echo "ERROR: Required a required permission is missing for DB load" . PHP_EOL;
			exit(1);
		}

		db_execute('TRUNCATE table_columns');
		db_execute('TRUNCATE table_indexes');

		$output = array();
		$error  = 0;

		if (file_exists($config['base_path'] . '/docs/audit_schema.sql')) {
			$cmd = 'MYSQL_PWD="' . cacti_escapeshellarg($database_password) . '" mysql' .
				' -u' . cacti_escapeshellarg($database_username) .
				' -h' . cacti_escapeshellarg($database_hostname) .
				' -P' . cacti_escapeshellarg($database_port) .
				' ' . $database_default .
				' < ' . $config['base_path'] . '/docs/audit_schema.sql';
			exec($cmd, $output, $error);

			if ($error == 0) {
				print ($altersopt ? '-- ' : '') . 'SUCCESS: Loaded the Audit Schema' . PHP_EOL;
			} else {
				print '@ ' . $config['base_path'] . '/docs/audit_schema.sql' . PHP_EOL;
				print 'FATAL: Failed Load the Audit Schema' . PHP_EOL;
				print 'ERROR: ' . implode(",\n   ", $output) . PHP_EOL;
				exit(1);
			}
		} else {
			print 'FATAL: Failed to find Audit Schema' . PHP_EOL;
		}
	}
}

function load_audit_database() {
	global $config, $database_default, $database_username, $database_password;

	$db_name = 'Tables_in_' . $database_default;

	if (!db_has_permissions(array('DROP','INSERT','LOCK TABLES'))) {
		echo "ERROR: Required a required permission is missing for DB load" . PHP_EOL;
		exit(1);
	}

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

		$retval = db_dump_data($database_default, 'table_columns table_indexes', array(), $config['base_path'] . '/docs/audit_schema.sql');
		if ($retval) {
			print 'Finished Creating Audit Schema with ERROR' . PHP_EOL . PHP_EOL;
		} else {
			print 'Finished Creating Audit Schema' . PHP_EOL . PHP_EOL;
		}

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

	print PHP_EOL . 'usage: audit_database.php --report | --repair [ --upgrade ]' . PHP_EOL . PHP_EOL;
	print 'Cacti utility for auditing and correcting your Cacti database.  This utility can' . PHP_EOL;
	print 'will scan your Cacti database and report any problems in the schema that it finds.' . PHP_EOL . PHP_EOL;
	print 'Options:' . PHP_EOL;
	print '    --report  - Report on any issues found in the audit of the database' . PHP_EOL;
	print '    --repair  - Repair any issues found during the audit of the database' . PHP_EOL;
	print '    --upgrade - Upgrade the Cacti database before running' . PHP_EOL . PHP_EOL;
	print 'Developer Options:' . PHP_EOL;
	print '    --create  - Initialize or Re-initialize the Audit Schema tables.' . PHP_EOL;
	print '    --load    - Take a pristine Cacti install and create Audit Schema and file.' . PHP_EOL;
	print '    --alters  - Print out all the alter commands vs. executing for debugging.' . PHP_EOL . PHP_EOL;
}

