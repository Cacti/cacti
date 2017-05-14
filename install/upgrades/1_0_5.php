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

function upgrade_to_1_0_5() {
	db_install_execute('ALTER TABLE host_snmp_cache MODIFY COLUMN snmp_index varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE poller_command MODIFY COLUMN command varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE poller_data_template_field_mappings MODIFY COLUMN data_source_names varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE snmpagent_managers_notifications MODIFY COLUMN notification varchar(180) NOT NULL');
	db_install_execute('ALTER TABLE snmpagent_notifications_log MODIFY COLUMN notification varchar(180) NOT NULL');

	/* poller reindex is more complicated due to reports of more than one entry */
	$duplicates = db_fetch_assoc('SELECT host_id, data_query_id, count(*) AS totals 
		FROM poller_reindex 
		GROUP BY host_id, data_query_id
		HAVING totals > 1');

	if (!sizeof($duplicates)) {
		db_install_execute('ALTER TABLE poller_reindex DROP PRIMARY KEY, ADD PRIMARY KEY (host_id, data_query_id)');
	} else {
		$nonhostzero = db_fetch_assoc('SELECT host_id, data_query_id, count(*) AS totals 
			FROM poller_reindex 
			WHERE host_id > 0
			GROUP BY host_id, data_query_id
			HAVING totals > 1');

		$haszero = db_fetch_assoc('SELECT host_id, data_query_id, count(*) AS totals 
			FROM poller_reindex 
			WHERE host_id = 0
			GROUP BY host_id, data_query_id
			HAVING totals > 1');

		if (sizeof($nonhostzero) && !sizeof($haszero)) {
			/* get rid of bad apples */
			db_execute('DELETE poller_reindex 
				FROM poller_reindex
				LEFT JOIN host_snmp_query
				ON poller_reindex.host_id=host_snmp_query.host_id
				AND poller_reindex.data_query_id=host_snmp_query.snmp_query_id
				WHERE host_snmp_query.host_id IS NULL');
			db_install_execute('ALTER TABLE poller_reindex DROP PRIMARY KEY, ADD PRIMARY KEY (host_id, data_query_id)');
		} else {
			cacti_log('WARNING: Unable to ajust poller_reindex table for UTF8mb4 Character Set.  Consider deleting host_id 0.', false, 'UPGRADE');
		}
	}

	/* bad data source profile id's */
	$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
	db_install_execute('UPDATE data_template_data SET data_source_profile_id = ' . $profile_id . ' WHERE data_source_profile_id = 0');

	/* engine id length */
	db_install_execute('ALTER TABLE automation_devices MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE automation_snmp_items MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE host MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE poller_item MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE snmpagent_managers MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');

	/* issue 399 external links ordering */
	$badlinks = db_fetch_cell('SELECT COUNT(*) FROM external_links WHERE sortorder=0');
	if ($badlinks) {
		$links = db_fetch_assoc('SELECT id FROM external_links ORDER BY id');
		$order = 1;

		foreach($links as $link) {
			db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', array($order, $link['id']));
			$order++;
		}
	}

	/* add external id column */
	if (!db_column_exists('host', 'external_id')) {
		db_install_execute('ALTER TABLE host ADD COLUMN external_id VARCHAR(40) DEFAULT NULL AFTER notes');
		db_install_execute('ALTER TABLE host ADD INDEX external_id (external_id)');
	}
}
