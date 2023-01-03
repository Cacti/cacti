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

function upgrade_to_0_8_2() {
	global $database_log;

	db_install_add_column('data_input_data_cache', array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false, 'after' => 'local_data_id'));
	db_install_add_column('host', array('name' => 'disabled', 'type' => 'char(2)'));
	db_install_add_column('host', array('name' => 'status', 'type' => 'tinyint(2)', 'NULL' => false));

	db_install_execute("UPDATE host_snmp_cache set field_name='ifName' where field_name='ifAlias' and snmp_query_id=1;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,'ifAlias','ifName') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4);");
	db_install_execute("UPDATE snmp_query_graph_sv set text = REPLACE(text,'ifAlias','ifName') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4);");
	db_install_execute("UPDATE host set disabled = '';");
}
