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

function upgrade_to_0_8_2() {
	db_install_execute("0.8.2", "ALTER TABLE `data_input_data_cache` ADD `host_id` MEDIUMINT( 8 ) NOT NULL AFTER `local_data_id`;");
	db_install_execute("0.8.2", "ALTER TABLE `host` ADD `disabled` CHAR( 2 ) , ADD `status` TINYINT( 2 ) NOT NULL;");
	db_install_execute("0.8.2", "UPDATE host_snmp_cache set field_name='ifName' where field_name='ifAlias' and snmp_query_id=1;");
	db_install_execute("0.8.2", "UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,'ifAlias','ifName') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4);");
	db_install_execute("0.8.2", "UPDATE snmp_query_graph_sv set text = REPLACE(text,'ifAlias','ifName') where (snmp_query_graph_id=1 or snmp_query_graph_id=13 or snmp_query_graph_id=14 or snmp_query_graph_id=16 or snmp_query_graph_id=9 or snmp_query_graph_id=2 or snmp_query_graph_id=3 or snmp_query_graph_id=4);");
	db_install_execute("0.8.2", "UPDATE host set disabled = '';");
}

?>
