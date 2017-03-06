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
	db_install_execute('ALTER TABLE poller_reindex DROP PRIMARY KEY, ADD PRIMARY KEY (host_id, data_query_id)');
	db_install_execute('ALTER TABLE snmpagent_managers_notifications MODIFY COLUMN notification varchar(180) NOT NULL');
	db_install_execute('ALTER TABLE snmpagent_notifications_log MODIFY COLUMN notification varchar(180) NOT NULL');
}
