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

class MibCache{
	private $active_mib            = '';
	private $active_object         = '';
	private $active_table          = '';
	private $active_table_entry    = '';
	private $cache__tables         = array();
	private $cache__tables_columns = array();

	public function __construct($mib='CACTI-MIB') {
		$this->active_mib = $mib;
		return $this;
	}

	public function __destruct() {

	}

	public function uninstall() {
		/* avoid that our default mib will be dropped by some plugin developer */
		if ($this->active_mib == 'CACTI-MIB') {
			return false;
		} else {
			db_execute_prepared('DELETE FROM snmpagent_cache WHERE `mib` = ?', array($this->active_mib));
			db_execute_prepared('DELETE FROM snmpagent_cache_notifications WHERE `mib` = ?', array($this->active_mib));
			db_execute_prepared('DELETE FROM snmpagent_cache_textual_conventions WHERE `mib` = ?', array($this->active_mib));
			db_execute_prepared('DELETE FROM snmpagent_mibs WHERE `name` = ?', array($this->active_mib));
		}
	}

	public function install($path, $replace=false, $mib_name='optional') {
		global $config;

		include_once($config['include_path'] . '/vendor/phpsnmp/mib_parser.php');

		$mp = new MibParser();
		$mp->add_mib($path, $mib_name);
		$mp->generate();

		if (isset($mp->mib) && isset($mp->oids) && $mp->mib ) {
			/* check if this mib has already been installed */
			$existing = db_fetch_cell_prepared('SELECT 1 FROM snmpagent_mibs WHERE `name` = ?', array($mp->mib));
			if ($existing) {
				if ($replace == false) {
					unset($mp->oids);
					unset($mp->mib);
					return false;
				} else {
					$this->uninstall();
				}
			}
			db_execute_prepared('INSERT INTO snmpagent_mibs SET `id` = 0, `name` = ?, `file` = ?', array($mp->mib, $path));

			foreach($mp->oids as $object_name => $object_params) {
				if ($object_params['otype'] != 'TEXTUAL-CONVENTION') {
					db_execute_prepared('INSERT IGNORE INTO `snmpagent_cache`
						(`oid`, `name`, `mib`, `type`, `otype`, `kind`, `max-access`, `description`)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
						array($object_params['oid'], $object_name, $object_params['mib'], $object_params['syntax'],
							$object_params['otype'], $object_params['kind'], $object_params['max-access'],
							str_replace("\r\n", '<br>', trim($object_params['description']))));

					if ($object_params['otype'] == 'NOTIFICATION-TYPE') {
						foreach($object_params['objects'] as $notification_object_index => $notification_object) {
							db_execute_prepared('INSERT INTO `snmpagent_cache_notifications`
								(`name`, `mib`, `attribute`, `sequence_id`)
								VALUES (?, ?, ?, ?)',
								array($object_name, $object_params['mib'], $notification_object, $notification_object_index));
						}
					}
				} else {
					db_execute_prepared('INSERT INTO `snmpagent_cache_textual_conventions`
						(`name`, `mib`, `type`, `description`)
						VALUES (?, ?, ?, ?)',
						array($object_name, $object_params['mib'], $object_params['syntax'], nl2br($object_params['description'])));
				}
			}

			unset($mp->oids);
			unset($mp->mib);
		} else {
			return false;
		}
	}

	public function mib($mib) {
		$this->active_mib = $mib;
		$this->active_object = '';
		$this->active_table = '';
		$this->active_table_entry = '';
		return $this;
	}

	public function object($object) {
		$this->active_object = $object;
		return $this;
	}

	public function table($table) {
		if ($this->active_table != $table) {
			if (!isset($this->cache__tables[$this->active_mib][$table])) {
				$oid_table = db_fetch_cell_prepared('SELECT oid
					FROM `snmpagent_cache`
					WHERE `mib` = ?
					AND `name` = ?
					AND `type` = "SEQUENCE OF"',
					array($this->active_mib, $table));

				if ($oid_table) {
					/* cache table oid and columns */
					$this->cache__tables[$this->active_mib][$table] = $oid_table;
					$this->active_table = $table;
					$this->cache__tables_columns[$this->active_mib][$table] = $this->columns();
					$this->active_table_entry = '';
					return $this;
				} else {
					/* MIB table does not exist */
					$this->active_table = '';
					$this->active_table_entry = '';
					throw new Exception('MIB table does not exist');
				}
			} else {
				/* table exists and has already been cached */
				$this->active_table = $table;
				$this->active_table_entry = '';
				return $this;
			}
		} else {
			/* no changes necessary */
			return $this;
		}
	}

	public function row($index) {
		/* limited to one single $index so far */
		$this->active_table_entry = $index;
		return $this;
	}

	public function gettype() {

	}

	public function set($value) {
		return db_execute_prepared('UPDATE `snmpagent_cache`
			SET `value` = ?
			WHERE `mib` = ?
			AND `name` = ?',
			array($value, $this->active_mib, $this->active_object));
	}

	public function get() {
		return db_fetch_row_prepared('SELECT *
			FROM snmpagent_cache
			WHERE name = ?
			AND mib = ?',
			array($this->active_object, $this->active_mib));
	}

	public function count() {
		return db_execute_prepared('UPDATE snmpagent_cache
			SET `value` = CASE
			WHEN `type`="Counter32" AND `value`= 4294967295 THEN 0
			WHEN `type`="Counter64" AND `value`= 18446744073709551615 THEN 0
			ELSE `value`+1 END
			WHERE `mib` = ? AND `name` = ?',
			array($this->active_mib, $this->active_object));
	}

	public function insert($values) {
		$oid_entry = $this->exists();
		if ($oid_entry == false) {
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if ($columns && cacti_sizeof($columns) > 0) {
				foreach($columns as $column_params) {
					$column_params['oid'] .= '.' . $this->active_table_entry;
					$column_params['otype'] = 'DATA';

					if (isset($values[$column_params['name']])) {
						$column_params['value'] = $values[$column_params['name']];
					}

					db_execute_prepared('INSERT INTO `snmpagent_cache`
						(`oid`, `name`, `mib`, `type`, `otype`, `kind`, `max-access`, `value`)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)
						ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `mib`=VALUES(`mib`),
						`type`=VALUES(`type`), `otype`=VALUES(`otype`), `kind`=VALUES(`kind`),
						`max-access`=VALUES(`max-access`), `value`=VALUES(`value`)',
						array($column_params['oid'], $column_params['name'], $column_params['mib'],
							$column_params['type'], $column_params['otype'], 'Column Data',
							$column_params['max-access'], trim($column_params['value'])));
				}
				return true;
			}
		}
		return false;
	}

	public function select($column=false) {
		$result = array();
		if ($this->active_table_entry) {
			/* focus on a dedicated MIB table row only */
			$oid_entry = $this->exists();
			if ($oid_entry !== false) {
				if ($column == false) {
					/* fetch the whole row */
					$filter = $oid_entry . '.%.' . $this->active_table_entry;

					$entries =  db_fetch_assoc_prepared('SELECT name, value
						FROM snmpagent_cache
						WHERE oid LIKE ?
						GROUP BY name
						ORDER BY oid',
						array($filter));

					if ($entries && cacti_sizeof($entries)>0) {
						foreach($entries as $entry) { $result[$entry['name']] = $entry['value']; }
						return $result;
					}
				} elseif (is_string($column)) {
					/* fetch only the value of a given column */
					$filter = $oid_entry . '.%.' . $this->active_table_entry;

					return db_fetch_cell_prepared('SELECT value
						FROM snmpagent_cache
						WHERE name = ?
						AND oid LIKE ?
						LIMIT 1',
						array($column, $filter));
				} elseif (is_array($column) && cacti_sizeof($column)>0) {
					$filter = $oid_entry . '.%.' . $this->active_table_entry;

					/* fetch all values of specific columns given for that MIB table row */
					$entries = db_fetch_assoc_prepared("SELECT name, value
						FROM snmpagent_cache
						WHERE name IN ('" . implode("','", $column) . "')
						AND oid LIKE ?
						GROUP BY name
						ORDER BY oid",
						array($filter));

					if ($entries && cacti_sizeof($entries)>0) {
						foreach($entries as $entry) { $result[$entry['name']] = $entry['value']; }
						return $result;
					}
				}
			}
		} else {
			/* query the whole MIB table */
			$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1';
			if ($column == false) {
				/* fetch all rows */
				$columns     = $this->cache__tables_columns[$this->active_mib][$this->active_table];
				$num_columns = cacti_sizeof($columns);
				$filter      = $oid_entry . '.%.%';

				$entries = db_fetch_assoc_prepared('SELECT name, value
					FROM snmpagent_cache
					WHERE oid LIKE ?
					ORDER BY oid',
					array($filter));

				if ($num_columns && $entries && cacti_sizeof($entries)) {
					$num_entries = cacti_sizeof($entries);
					$entries_per_object = $num_entries/$num_columns;
					for($i = 0; $i < $entries_per_object; $i++) {
						$result[$i]=array();
						for($j=0; $j < $num_columns; $j++) {
							$result[$i][$entries[$i+$j*$entries_per_object]['name']] = $entries[$i+$j*$entries_per_object]['value'];
						}
					}
					return $result;
				} else {
					return $entries;
				}
			} elseif (is_string($column)) {
				/* fetch only the values of one single column */
				$filter = $oid_entry . '.%.%';

				return db_fetch_assoc_prepared("SELECT value AS '" . $column . "'
					FROM snmpagent_cache
					WHERE name = ?
					AND oid LIKE ?
					ORDER BY oid",
					array($column, $filter));
			} elseif (is_array($column) && cacti_sizeof($column)>0) {
				/* fetch values of specific columns given */
				$filter = $oid_entry . '.%.%';

				$entries = db_fetch_assoc_prepared("SELECT name, value
					FROM snmpagent_cache
					WHERE name IN ('" . implode("','", $column) . "')
					AND oid LIKE ?
					ORDER BY oid", array($filter));

				if (cacti_sizeof($entries)) {
					$num_objects = cacti_sizeof($column);
					$num_entries = cacti_sizeof($entries);
					$entries_per_object = ceil($num_entries/$num_objects);

					for($i = 0; $i < $entries_per_object; $i++) {
						$result[$i]=array();
						for($j=0; $j < $num_objects; $j++) {
							$index = (int) $i + ($j * $entries_per_object);
							$result[$i][$entries[$index]['name']] = $entries[$index]['value'];
						}
					}
					return $result;
				} else {
					return $entries;
				}
			}
		}

		return false;
	}

	public function delete() {
		$oid_entry = $this->exists();
		if ($oid_entry !== false) {
			/* get list of columns for this mib table */
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if ($columns && cacti_sizeof($columns) > 0) {
				foreach($columns as $column_params) {
					$column_params['oid'] .= '.' . $this->active_table_entry;
					db_execute_prepared('DELETE FROM `snmpagent_cache` WHERE `oid` = ?', array($column_params['oid']));
				}
				return true;
			}
		}
		return false;
	}

	public function update($values) {
		$oid_entry = $this->exists();
		if ($oid_entry !== false) {
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if (cacti_sizeof($columns)>0) {
				$sql = array();

				foreach($columns as $column_params) {
					$column_params['oid'] .= '.' . $this->active_table_entry;
					if (isset($values[$column_params['name']])) {
						$sql[] = '(' . db_qstr($column_params['name']) . ', ' . db_qstr($values[$column_params['name']]) . ', ' . db_qstr($column_params['oid']) . ')';
					}
				}

				if (cacti_sizeof($sql)) {
					db_execute('INSERT INTO `snmpagent_cache`
						(name, value, oid)
						VALUES ' . implode(', ', $sql) . '
						ON DUPLICATE KEY UPDATE value=VALUES(value)', $sql);
				}

				return true;
			}
		}

		return false;
	}

	public function replace($values) {
		$this->delete();
		return $this->insert($values);
	}

	public function truncate() {
		$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1.%';
		db_execute_prepared('DELETE FROM `snmpagent_cache`
			WHERE `mib` = ?
			AND `otype` = "DATA"
			AND `oid` LIKE ?',
			array($this->active_mib, $oid_entry));

		return true;
	}

	public function columns() {
		/* As defined by SMI the OID value assigned to the row must be the same as the OID value assigned to the table containing
		   the row with addition of a single value of one. */
		$filter = $this->cache__tables[$this->active_mib][$this->active_table] . '.1.%';

		return db_fetch_assoc_prepared('SELECT *
			FROM `snmpagent_cache`
			WHERE `oid` LIKE ?
			GROUP BY name
			ORDER BY oid',
			array($filter));
	}

	private function exists() {
		$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1';

		/* check if entry exists */
		$exists = db_fetch_cell_prepared('SELECT 1 FROM `snmpagent_cache` WHERE `oid` = ?',
			array($oid_entry . '.1.' . $this->active_table_entry));

		return ($exists) ? $oid_entry : false;
	}
}

