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

function upgrade_to_1_2_21() {
	global $config;

	db_install_drop_key('data_source_profiles_cf', 'index', 'data_source_profile_id');
	db_install_drop_key('data_template_rrd', 'index', 'local_data_id');
	db_install_drop_key('graph_template_input_defs', 'index', 'graph_template_input_id');
	db_install_drop_key('host', 'index', 'site_id');
	db_install_drop_key('host_snmp_query', 'index', 'host_id');
	db_install_drop_key('host_template_graph', 'index', 'host_template_id');
	db_install_drop_key('host_template_snmp_query', 'index', 'host_template_id');
	db_install_drop_key('processes', 'index', 'pid');
	db_install_drop_key('snmpagent_cache_notifications', 'index', 'name');
	db_install_drop_key('snmpagent_cache_textual_conventions', 'index', 'name');
	db_install_drop_key('snmpagent_managers_notifications', 'index', 'manager_id_notification');
	db_install_drop_key('snmp_query_graph_rrd', 'index', 'snmp_query_graph_id');
}
