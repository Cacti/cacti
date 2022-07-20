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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_1_31() {
	db_install_execute(
		'ALTER TABLE `host` MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""'
	);

	db_install_execute(
		'ALTER TABLE `automation_devices` MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""'
	);

	db_install_execute(
		'ALTER TABLE `automation_snmp_items` MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""'
	);

	db_install_execute(
		'ALTER TABLE `poller_item` MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""'
	);

	db_install_execute(
		'ALTER TABLE `snmpagent_managers`
			MODIFY COLUMN `snmp_auth_protocol` char(6) NOT NULL DEFAULT "",
			MODIFY COLUMN `snmp_username` varchar(50) NOT NULL DEFAULT "",
			MODIFY COLUMN `snmp_priv_protocol` char(6) NOT NULL DEFAULT ""'
	);

	if (!db_column_exists('snmpagent_managers', 'snmp_password')) {
		db_install_execute(
			'ALTER TABLE `snmpagent_managers`
				CHANGE COLUMN `snmp_auth_password` `snmp_password` varchar(50) NOT NULL DEFAULT ""'
		);
	}

	if (!db_column_exists('snmpagent_managers', 'snmp_priv_passphrase')) {
		db_install_execute(
			'ALTER TABLE `snmpagent_managers`
				CHANGE COLUMN `snmp_priv_password` `snmp_priv_passphrase` varchar(200) NOT NULL DEFAULT ""'
		);
	}

	if (!db_column_exists('automation_snmp_items', 'snmp_community')) {
		db_install_execute(
			'ALTER TABLE `automation_snmp_items`
				CHANGE COLUMN `snmp_readstring` `snmp_community` varchar(50) NOT NULL DEFAULT ""'
		);
	}

	$snmp_version = read_config_option('snmp_version');
	if ($snmp_version == '') {
		db_install_execute('UPDATE settings SET name="snmp_version" WHERE name="snmp_ver"');
	}
}

