<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function upgrade_to_1_2_27() {
	db_install_execute("ALTER TABLE `poller_item` MODIFY `snmp_priv_protocol` char(7) NOT NULL DEFAULT ''");
	db_install_execute("ALTER TABLE `host` MODIFY `snmp_priv_protocol` char(7) DEFAULT ''");
	db_install_execute("ALTER TABLE `automation_devices` MODIFY `snmp_priv_protocol` char(7) DEFAULT ''");
	db_install_execute("ALTER TABLE `automation_snmp_items` MODIFY `snmp_priv_protocol` char(7) DEFAULT ''");
	db_install_execute("ALTER TABLE `snmpagent_managers` MODIFY `snmp_priv_protocol` char(7) NOT NULL DEFAULT ''");
	
	$data_input_field_id = db_fetch_cell_prepared('SELECT id FROM data_input_fields WHERE hash = ?', array('35637c344d84d8aa3a4dc50e4a120b3f'));

	if ($data_input_field_id > 0) {
		db_execute_prepared('DELETE FROM data_input_fields WHERE id = ?', array($data_input_field_id));
		db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', array($data_input_field_id));
	}
}

