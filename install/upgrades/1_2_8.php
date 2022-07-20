<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

function upgrade_to_1_2_8() {
	db_install_execute('ALTER TABLE host_snmp_cache ROW_FORMAT=Dynamic, DROP INDEX snmp_index, DROP INDEX field_value');
	db_install_execute('ALTER TABLE host_snmp_cache MODIFY COLUMN snmp_index VARCHAR(255) NOT NULL default ""');
	db_install_execute('ALTER TABLE host_snmp_cache ADD INDEX snmp_index(snmp_index), ADD INDEX field_value(field_value)');
	db_install_execute('ALTER TABLE data_local ROW_FORMAT=Dynamic, DROP INDEX snmp_index, ADD INDEX snmp_index(snmp_index)');
	db_install_execute('ALTER TABLE graph_local ROW_FORMAT=Dynamic, DROP INDEX snmp_index, ADD INDEX snmp_index(snmp_index)');

	// Needed to fix aggregate bug
	if (!db_column_exists('aggregate_graphs', 'gprint_format')) {
		db_install_execute('ALTER TABLE aggregate_graphs ADD COLUMN gprint_format CHAR(2) default "" AFTER gprint_prefix');
	}

	if (!db_column_exists('aggregate_graph_templates', 'gprint_format')) {
		db_install_execute('ALTER TABLE aggregate_graph_templates ADD COLUMN gprint_format CHAR(2) default "" AFTER gprint_prefix');
	}

	// Reimport colors
	import_colors();
}
