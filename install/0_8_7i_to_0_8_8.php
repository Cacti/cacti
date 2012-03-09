<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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

function upgrade_to_0_8_8() {
	/* speed up the joins */
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM poller_item"), "Field", "Field");
	if (in_array("host_id", $_columns)) {
		db_install_execute("0.8.8", "ALTER TABLE poller_item MODIFY COLUMN host_id MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0'");
		cacti_log(__FUNCTION__ . " upgrade table poller_item", false, "UPGRADE");
	}
	
	$_keys = array_rekey(db_fetch_assoc("SHOW KEYS FROM poller_output"), "Key_name", "Key_name");
	if (in_array("duplicate_dsname_contraint", $_keys)) {
		db_install_execute("0.8.8", "ALTER TABLE `poller_output` DROP PRIMARY KEY");
	}
	db_install_execute("0.8.8", "ALTER TABLE `poller_output` ADD PRIMARY KEY (`local_data_id`, `rrd_name`, `time`) USING BTREE");
	cacti_log(__FUNCTION__ . " upgrade table poller_output", false, "UPGRADE");

}
?>
