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

function upgrade_to_1_1_20() {
	db_install_execute('ALTER TABLE snmpagent_cache
		MODIFY COLUMN `oid` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `name` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `mib` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `type` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `otype` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `kind` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `max-access` VARCHAR(50) NOT NULL');

	db_install_execute('ALTER TABLE snmpagent_mibs
		MODIFY COLUMN `name` VARCHAR(50) NOT NULL DEFAULT ""');

	db_install_drop_key('snmpagent_cache_notifications', 'key', 'PRIMARY');

	db_install_execute('ALTER TABLE snmpagent_cache_notifications
		MODIFY COLUMN `name` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `mib` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `attribute` VARCHAR(50) NOT NULL');

	db_install_add_key('snmpagent_cache_notifications', 'key', 'PRIMARY', array('name', 'mib', 'attribute', 'sequence_id'));

	db_install_drop_key('snmpagent_cache_textual_conventions', 'key', 'PRIMARY');

	db_install_execute('ALTER TABLE snmpagent_cache_textual_conventions
		MODIFY COLUMN name VARCHAR(50) NOT NULL,
		MODIFY COLUMN mib VARCHAR(50) NOT NULL,
		MODIFY COLUMN type VARCHAR(50) NOT NULL');

	db_install_add_key('snmpagent_cache_textual_conventions', 'key', 'PRIMARY', array('name', 'mib', 'type'));

	/* correct dumplicate notifications */
	$notifications_results = db_install_fetch_assoc('SELECT *, COUNT(*) AS totals
		FROM snmpagent_managers_notifications
		GROUP BY manager_id, notification, mib
		HAVING totals > 1');
	$notifications = $notifications_results['data'];

	if (cacti_sizeof($notifications)) {
		foreach ($notifications as $n) {
			$totals = $n['totals'];

			db_install_execute("DELETE FROM snmpagent_managers_notifications
				WHERE manager_id = ?
				AND notification = ?
				AND mib = ?
				LIMIT $totals",
				array($n['manager_id'], $n['notification'], $n['mib']));
		}
	}

	db_install_drop_key('snmpagent_managers_notifications', 'key', 'PRIMARY');

	db_install_execute('ALTER TABLE snmpagent_managers_notifications
		MODIFY COLUMN `notification` VARCHAR(50) NOT NULL,
		MODIFY COLUMN `mib` VARCHAR(50) NOT NULL');

	db_install_add_key('snmpagent_managers_notifications', 'key', 'PRIMARY', array('manager_id', 'notification', 'mib'));
}
