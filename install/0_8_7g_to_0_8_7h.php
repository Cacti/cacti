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

function upgrade_to_0_8_7h() {
	/* speed up the reindexing */
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM host_snmp_cache"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_execute("ALTER TABLE host_snmp_cache ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `oid`");
		db_execute("ALTER TABLE host_snmp_cache ADD INDEX present (present)");
	}
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM poller_item"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_execute("ALTER TABLE poller_item ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `action`");
		db_execute("ALTER TABLE poller_item ADD INDEX present (present)");
	}
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM poller_reindex"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_execute("ALTER TABLE poller_reindex ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `action`");
		db_execute("ALTER TABLE poller_reindex ADD INDEX present (present)");
	}

	/* update the reindex cache, as we now introduced more options for "index count changed" */
	$host_snmp_query = db_fetch_assoc("select host_id,snmp_query_id from host_snmp_query");
	if (sizeof($host_snmp_query) > 0) {
		foreach ($host_snmp_query as $item) {
			update_reindex_cache($item["host_id"], $item["snmp_query_id"]);
		}
	}

}
?>
