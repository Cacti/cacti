<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2016 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
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

class MibCache{

	private $active_mib				= '';
	private $active_object			= '';
	private $active_table			= '';
	private $active_table_entry 	= '';
	private $cache__tables			= array();
	private $cache__tables_columns 	= array();

	public function __construct($mib='CACTI-MIB') {
		$this->active_mib = $mib;
		return $this;
	}

	public function __destruct() {

	}

	public function uninstall() {
		/* avoid that our default mib will be dropped by some plugin developer */
		if($this->active_mib == 'CACTI-MIB') {
			return false;
		}else {
			db_execute("DELETE FROM snmpagent_cache WHERE `mib` = '" . $this->active_mib . "'");
			db_execute("DELETE FROM snmpagent_cache_notifications WHERE `mib` = '" . $this->active_mib . "'");
			db_execute("DELETE FROM snmpagent_cache_textual_conventions WHERE `mib` = '" . $this->active_mib . "'");
			db_execute("DELETE FROM snmpagent_mibs WHERE `name` = '" . $this->active_mib . "'");
		}
	}

	public function install($path, $replace=false, $mib_name='optional') {
		global $config;
		include_once($config['library_path'] . '/mib_parser.php');

		$mp = new MibParser();
		$mp->add_mib($path, $mib_name);
		$mp->generate();

		if(isset($mp->mib) && isset($mp->oids) && $mp->mib ) {
			/* check if this mib has already been installed */
			$existing = db_fetch_cell("SELECT 1 FROM snmpagent_mibs WHERE `name` = '" . $mp->mib ."'");
			if($existing) {
				if($replace == false) {
					unset($mp->oids);
					unset($mp->mib);
					return false;
				}else {
					$this->uninstall();
				}
			}
			db_execute("INSERT INTO snmpagent_mibs SET `id` = 0, `name` = '" . $mp->mib . "', `file` = '" . $path . "'");

			foreach($mp->oids as $object_name => $object_params) {
				if($object_params["otype"] != "TEXTUAL-CONVENTION") {
					db_execute("INSERT IGNORE INTO `snmpagent_cache` (`oid`, `name`, `mib`, `type`, `otype`, `kind`, `max-access`, `description`) VALUES ('"
						. $object_params["oid"] . "','"
						. $object_name . "','"
						. $object_params["mib"] . "','"
						. $object_params["syntax"] . "','"
						. $object_params["otype"] . "','"
						. $object_params["kind"] . "','"
						. $object_params["max-access"] . "',"
						. db_qstr(str_replace("\r\n", '\r\n', trim($object_params["description"]))) . ")"
					);
					if($object_params["otype"] == "NOTIFICATION-TYPE") {
						foreach($object_params["objects"] as $notication_object_index => $notication_object) {
							db_execute("INSERT INTO `snmpagent_cache_notifications` (`name`, `mib`, `attribute`, `sequence_id`) VALUES ('"
								. $object_name . "','"
								. $object_params["mib"] . "','"
								. $notication_object . "','"
								. $notication_object_index ."')"
							);
						}
					}
				}else {
					db_execute_prepared("INSERT INTO `snmpagent_cache_textual_conventions` (`name`, `mib`, `type`, `description`) VALUES ('"
								. $object_name . "','"
								. $object_params["mib"] . "','"
								. $object_params["syntax"] . "','"
								. nl2br(addslashes($object_params["description"])) . "')"
					);
				}
			}

			unset($mp->oids);
			unset($mp->mib);
		}else {
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
		if( $this->active_table != $table ) {
			if(!isset($this->cache__tables[$this->active_mib][$table])) {
				$oid_table = db_fetch_cell("SELECT oid FROM `snmpagent_cache` WHERE `mib` = '" . $this->active_mib . "' AND `name` = '" . $table . "' AND `type` = 'SEQUENCE OF'");
				if($oid_table) {
					/* cache table oid and columns */
					$this->cache__tables[$this->active_mib][$table] = $oid_table;
					$this->active_table = $table;
					$this->cache__tables_columns[$this->active_mib][$table] = $this->columns();
					$this->active_table_entry = '';
					return $this;
				}else {
					/* MIB table does not exist */
					$this->active_table = '';
					$this->active_table_entry = '';
					return "ERROR";
				}
			}else {
				/* table exists and has already been cached */
				$this->active_table = $table;
				$this->active_table_entry = '';
				return $this;
			}
		}else {
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
		return db_execute("UPDATE `snmpagent_cache` SET `value` = '$value' WHERE `mib` = '" . $this->active_mib . "' AND `name` = '" . $this->active_object . "' LIMIT 1");
	}

	public function get() {
		return db_fetch_row("SELECT * FROM snmpagent_cache WHERE name='" . $this->active_object . "' AND mib='" . $this->active_mib . "' LIMIT 1");
	}

	public function count() {
		return db_execute( "UPDATE LOW_PRIORITY snmpagent_cache
						SET `value` = CASE
							WHEN `type`='Counter32' AND `value`= 4294967295 THEN 0
							WHEN `type`='Counter64' AND `value`= 18446744073709551615 THEN 0
							ELSE `value`+1 END
						WHERE `mib` = '" . $this->active_mib . "' AND `name` = '" . $this->active_object . "' LIMIT 1;");
	}

	public function insert($values) {
		$oid_entry = $this->exists();
		if($oid_entry == false) {
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if($columns & sizeof($columns)>0) {
				foreach($columns as $column_params) {
					$column_params["oid"] .= "." . $this->active_table_entry;
					$column_params["otype"] = "DATA";
					if(isset($values[$column_params["name"]])) {
						$column_params["value"] = $values[$column_params["name"]];
					}
					db_execute("INSERT IGNORE INTO `snmpagent_cache` (`oid`, `name`, `mib`, `type`, `otype`, `kind`, `max-access`, `value`) VALUES ('"
						. $column_params["oid"] . "','"
						. $column_params["name"] . "','"
						. $column_params["mib"] . "','"
						. $column_params["type"] . "','"
						. $column_params["otype"] . "','"
						. "Column Data" . "','"
						. $column_params["max-access"] . "',"
						. db_qstr(trim($column_params["value"])) . ")");
				}
				return true;
			}
		}
		return false;
	}


	public function select($column=false) {
		$result = array();
		if($this->active_table_entry) {
			/* focus on a dedicated MIB table row only */
			$oid_entry = $this->exists();
			if($oid_entry !== false) {
				if($column == false) {
					/* fetch the whole row */
					$entries =  db_fetch_assoc("SELECT name, value FROM snmpagent_cache WHERE oid LIKE '" . $oid_entry . ".%." . $this->active_table_entry . "' GROUP BY name ORDER BY oid");
					if($entries && sizeof($entries)>0) {
						foreach($entries as $entry) { $result[$entry['name']] = $entry['value']; }
						return $result;
					}
				}elseif(is_string($column)) {
					/* fetch only the value of a given column */
					return db_fetch_cell("SELECT value FROM snmpagent_cache WHERE name = '" . $column . "' AND oid LIKE '" . $oid_entry . ".%." . $this->active_table_entry . "' LIMIT 1");
				}elseif(is_array($column) && sizeof($column)>0) {
					/* fetch all values of specific columns given for that MIB table row */
					$entries = db_fetch_assoc("SELECT name, value FROM snmpagent_cache WHERE name IN ('" . implode("','", $column) . "') AND oid LIKE '" . $oid_entry . ".%." . $this->active_table_entry . "' GROUP BY name ORDER BY oid");
					if($entries && sizeof($entries)>0) {
						foreach($entries as $entry) { $result[$entry['name']] = $entry['value']; }
						return $result;
					}
				}
			}
		}else {
			/* query the whole MIB table */
			$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1';
			if($column == false) {
				/* fetch all rows */
				$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
				$num_columns = sizeof($columns);
				$entries = db_fetch_assoc("SELECT name, value FROM snmpagent_cache WHERE oid LIKE '" . $oid_entry . ".%.%' ORDER BY oid");
				if($num_columns && $entries && sizeof($entries)>0) {
					$num_entries = sizeof($entries);
					$entries_per_object = $num_entries/$num_columns;
					for($i = 0; $i < $entries_per_object; $i++) {
						$result[$i]=array();
						for($j=0; $j < $num_columns; $j++) {
							$result[$i][$entries[$i+$j*$entries_per_object]["name"]] = $entries[$i+$j*$entries_per_object]["value"];
						}
					}
					return $result;
				}else {
					return $entries;
				}
			}elseif(is_string($column)) {
				/* fetch only the values of one single column */
				return db_fetch_assoc("SELECT value as '" . $column . "' FROM snmpagent_cache WHERE name = '" . $column . "' AND oid LIKE '" . $oid_entry . ".%.%' ORDER BY oid");
			}elseif(is_array($column) && sizeof($column)>0) {
				/* fetch values of specific columns given */
				$entries = db_fetch_assoc("SELECT name, value FROM snmpagent_cache WHERE name IN ('" . implode("','", $column) . "') AND oid LIKE '" . $oid_entry . ".%.%' ORDER BY oid");
				if($entries && sizeof($entries)>0) {
					$num_objects = sizeof($column);
					$num_entries = sizeof($entries);
					$entries_per_object = $num_entries/$num_objects;
					for($i = 0; $i < $entries_per_object; $i++) {
						$result[$i]=array();
						for($j=0; $j < $num_objects; $j++) {
							$result[$i][$entries[$i+$j*$entries_per_object]["name"]] = $entries[$i+$j*$entries_per_object]["value"];
						}
					}
					return $result;
				}else {
					return $entries;
				}
			}
		}
		return false;
	}

	public function delete() {
		$oid_entry = $this->exists();
		if($oid_entry !== false) {
			/* get list of columns for this mib table */
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if($columns & sizeof($columns)>0) {
				foreach($columns as $column_params) {
					$column_params["oid"] .= "." . $this->active_table_entry;
					db_execute("DELETE FROM `snmpagent_cache` WHERE `oid` = '" . $column_params["oid"] . "' LIMIT 1");
				}
				return true;
			}
		}
		return false;
	}

	public function update($values) {
		$oid_entry = $this->exists();
		if($oid_entry !== false) {
			$columns = $this->cache__tables_columns[$this->active_mib][$this->active_table];
			if($columns & sizeof($columns)>0) {
				foreach($columns as $column_params) {
					$column_params["oid"] .= "." . $this->active_table_entry;
					if(isset($values[$column_params["name"]])) {
						db_execute("UPDATE `snmpagent_cache` SET `value` = '" . $values[$column_params["name"]] . "' WHERE `oid` = '" . $column_params["oid"] . "'");
					}
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
		$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1';
		db_execute("DELETE FROM `snmpagent_cache` WHERE `mib` = '" . $this->active_mib . "' AND `otype` = 'DATA' AND `oid` LIKE '" . $oid_entry . ".%'");
		return true;
	}

	public function columns() {
		/* As defined by SMI the OID value assigned to the row must be the same as the OID value assigned to the table containing
		   the row with addition of a single value of one. */
		return db_fetch_assoc("SELECT * FROM `snmpagent_cache` WHERE `oid` LIKE '" . $this->cache__tables[$this->active_mib][$this->active_table] . ".1.%' GROUP BY name ORDER BY oid");
	}

	private function exists() {
		$oid_entry = $this->cache__tables[$this->active_mib][$this->active_table] . '.1';
		/* check if entry exists */
		$exists = db_fetch_cell("SELECT 1 FROM `snmpagent_cache` WHERE `oid` = '" . $oid_entry . ".1." . $this->active_table_entry . "'");
		return ($exists) ? $oid_entry : false;
	}
}
?>
