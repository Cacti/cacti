<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

function upgrade_to_1_2_31() {
	global $config;

	db_install_execute('ALTER TABLE automation_devices MODIFY COLUMN snmp_priv_protocol char(7) default ""');
	db_install_execute('ALTER TABLE automation_snmp_items MODIFY COLUMN snmp_priv_protocol char(7) default ""');
	db_install_execute('ALTER TABLE snmpagent_managers MODIFY COLUMN snmp_priv_protocol char(7) NOT NULL');

	db_install_execute('ALTER TABLE settings MODIFY COLUMN `name` varchar(255) NOT NULL default ""');

	if (!db_index_exists('snmp_query_graph', 'snmp_query_id')) {
		db_install_execute('ALTER TABLE snmp_query_graph ADD INDEX snmp_query_id (snmp_query_id)');
	}

	if (!db_index_exists('snmp_query_graph', 'graph_template_id')) {
		db_install_execute('ALTER TABLE snmp_query_graph ADD INDEX graph_template_id (graph_template_id)');
	}

	db_install_execute('ALTER TABLE settings_user MODIFY COLUMN name varchar(255) NOT NULL default ""');
}

