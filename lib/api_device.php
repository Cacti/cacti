<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* api_device_remove - removes a device
   @arg $device_id - the id of the device to remove */
function api_device_remove($device_id) {
	db_execute("delete from host where id=$device_id");
	db_execute("delete from host_graph where host_id=$device_id");
	db_execute("delete from host_snmp_query where host_id=$device_id");
	db_execute("delete from host_snmp_cache where host_id=$device_id");
	db_execute("delete from data_input_data_cache where host_id=$device_id");
	db_execute("delete from graph_tree_items where host_id=$device_id");

	db_execute("update data_local set host_id=0 where host_id=$device_id");
	db_execute("update graph_local set host_id=0 where host_id=$device_id");
}

/* api_device_dq_remove - removes a device->data query mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $data_query_id - the id of the data query to remove the mapping for */
function api_device_dq_remove($device_id, $data_query_id) {
	db_execute("delete from host_snmp_cache where snmp_query_id=$data_query_id and host_id=$device_id");
	db_execute("delete from host_snmp_query where snmp_query_id=$data_query_id and host_id=$device_id");
}

/* api_device_gt_remove - removes a device->graph template mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $graph_template_id - the id of the graph template to remove the mapping for */
function api_device_gt_remove($device_id, $graph_template_id) {
	db_execute("delete from host_graph where graph_template_id=$graph_template_id and host_id=$device_id");
}

?>
