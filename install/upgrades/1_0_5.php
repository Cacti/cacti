<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

function upgrade_to_1_0_5() {
	db_install_execute('ALTER TABLE host_snmp_cache MODIFY COLUMN snmp_index varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE poller_command MODIFY COLUMN command varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE poller_data_template_field_mappings MODIFY COLUMN data_source_names varchar(191) NOT NULL default ""');
	db_install_execute('ALTER TABLE snmpagent_managers_notifications MODIFY COLUMN notification varchar(180) NOT NULL');
	db_install_execute('ALTER TABLE snmpagent_notifications_log MODIFY COLUMN notification varchar(180) NOT NULL');

	/* bad data source profile id's */
	$profile_id = db_install_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');

	if ($profile_id > 0) {
		db_install_execute('UPDATE data_template_data
			SET data_source_profile_id = ?
			WHERE data_source_profile_id = 0',
			array($profile_id));
	}

	/* engine id length */
	db_install_execute('ALTER TABLE automation_devices MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE automation_snmp_items MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE host MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE poller_item MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');
	db_install_execute('ALTER TABLE snmpagent_managers MODIFY COLUMN snmp_engine_id VARCHAR(64) DEFAULT ""');

	/* issue 399 external links ordering */
	$badlinks_results = db_install_fetch_cell('SELECT COUNT(*) FROM external_links WHERE sortorder=0');
	$badlinks         = $badlinks_results['data'];

	if ($badlinks) {
		$links_results = db_install_fetch_assoc('SELECT id FROM external_links ORDER BY id');
		$links         = $links_results['data'];
		$order         = 1;

		foreach ($links as $link) {
			db_install_execute('UPDATE external_links SET sortorder = ? WHERE id = ?', array($order, $link['id']));
			$order++;
		}
	}

	/* add external id column */
	if (!db_column_exists('host', 'external_id')) {
		db_install_execute('ALTER TABLE host ADD COLUMN external_id VARCHAR(40) DEFAULT NULL AFTER notes');
		db_install_add_key('host', 'index', 'external_id', array('external_id'));
	}
}
