<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

function upgrade_to_1_2_5() {
	db_install_execute('UPDATE graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gti.local_graph_id = gl.id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_local AS dl
		ON dl.id = dtr.local_data_id
		SET gl.snmp_query_id = dl.snmp_query_id, gl.snmp_index = dl.snmp_index
		WHERE gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
		AND gl.snmp_query_id = 0');

	db_install_execute("UPDATE graph_local AS gl
		INNER JOIN (
			SELECT DISTINCT local_graph_id, task_item_id
			FROM graph_templates_item
		) AS gti
		ON gl.id = gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id = dtd.local_data_id
		INNER JOIN data_input_fields AS dif
		ON dif.data_input_id = dtd.data_input_id
		INNER JOIN data_input_data AS did
		ON did.data_template_data_id = dtd.id
		AND did.data_input_field_id = dif.id
		INNER JOIN snmp_query_graph_rrd AS sqgr
		ON sqgr.snmp_query_graph_id = did.value
		SET gl.snmp_query_graph_id = did.value
		WHERE input_output = 'in'
		AND type_code = 'output_type'
		AND gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)");

	db_install_execute('ALTER TABLE poller_output_realtime ROW_FORMAT=Dynamic, DROP PRIMARY KEY, ADD PRIMARY KEY (local_data_id, rrd_name, time, poller_id)');

	repair_automation();
}
