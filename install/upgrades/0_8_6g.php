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

function upgrade_to_0_8_6g() {
	/* changes for even longer OIDs */
	db_install_execute('ALTER TABLE `host_snmp_cache` CHANGE `snmp_index` `snmp_index` VARCHAR( 255 ) NOT NULL;');
	db_install_execute('ALTER TABLE `data_local` CHANGE `snmp_index` `snmp_index` VARCHAR( 255 ) NOT NULL;');
	db_install_execute('ALTER TABLE `graph_local` CHANGE `snmp_index` `snmp_index` VARCHAR( 255 ) NOT NULL;');
	db_install_execute("ALTER TABLE `graph_templates_graph` CHANGE `lower_limit` `lower_limit` VARCHAR ( 20 ) DEFAULT '0';");
	db_install_execute("ALTER TABLE `graph_templates_graph` CHANGE `upper_limit` `upper_limit` VARCHAR ( 20 ) DEFAULT '0';");
}
