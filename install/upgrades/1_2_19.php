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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_2_19() {
	// Correct name values in data input fields
	db_install_execute("UPDATE data_input_fields
		SET name='SNMP Authenticaion Protocol (v3)'
		WHERE hash IN ('20832ce12f099c8e54140793a091af90', '2cf7129ad3ff819a7a7ac189bee48ce8')");

	if (!db_column_exists('graph_templates', 'test_source')) {
		db_install_execute("ALTER TABLE graph_templates
		ADD COLUMN test_source CHAR(2) NOT NULL default '' AFTER multiple");
	}

	db_install_execute('ALTER TABLE graph_templates
		ENGINE=InnoDB ROW_FORMAT=Dynamic,
		DROP INDEX multiple_name,
		DROP INDEX name,
		ADD INDEX multiple_name(multiple, name),
		ADD INDEX name(name)');

	// Add missing indexes to graph_templates_item table
	$alter   = '';
	$indexes = array('cdef_id', 'vdef_id', 'color_id', 'gprint_id', 'local_graph_template_item_id');

	foreach ($indexes as $i) {
		if (!db_index_exists('graph_templates_item', $i, false)) {
			$alter .= ($alter != '' ? ', ':'') . " ADD INDEX $i($i)";
		}
	}

	if ($alter != '') {
		db_install_execute('ALTER table graph_templates_item' . $alter);
	}

	if (!db_column_exists('poller', 'dbretries')) {
		db_install_execute('ALTER TABLE poller
			ADD COLUMN dbretries int unsigned NOT NULL default "2" AFTER dbport');
	}

	db_install_execute('ALTER TABLE host
		MODIFY COLUMN snmp_sysUpTimeInstance bigint(20) unsigned not null default "0"');

	db_install_execute('ALTER TABLE automation_devices
		MODIFY COLUMN sysUpTime bigint(20) unsigned not null default "0"');
}
