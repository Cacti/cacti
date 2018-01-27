<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

function upgrade_to_1_1_34() {
	if (db_column_exists('graph_templates_graph', 'export')) {
		db_install_execute(
			'ALTER TABLE `graph_templates_graph` DROP COLUMN `export`';
		);
	}

	if (db_column_exists('graph_templates_graph', 't_export')) {
		db_install_execute(
			'ALTER TABLE `graph_templates_graph` DROP COLUMN `t_export`';
		);
	}

	if (!db_column_exists('automation_snmp_items', 'snmp_community')) {
		db_install_execute(
			'ALTER TABLE `automation_snmp_items`
				CHANGE COLUMN `snmp_readstring` `snmp_community` varchar(50) NOT NULL DEFAULT ""'
		);
	}

	if (!db_index_exists('data_input_fields', 'input_output')) {
		db_install_execute(
			'ALTER TABLE `data_input_fields`
				ADD INDEX `input_output` (`input_output`)'
		);
	}

	if (!db_index_exists('poller_command', 'poller_id_last_updated')) {
		db_install_execute(
			'ALTER TABLE `poller_command`
				ADD INDEX `poller_id_last_updated` (`poller_id`, `last_updated`)'
		);
	}

	if (!db_index_exists('poller_item', 'poller_id_last_updated')) {
		db_install_execute(
			'ALTER TABLE `poller_item`
				ADD INDEX `poller_id_last_updated` (`poller_id`, `last_updated`)'
		);
	}

	if (!db_index_exists('host', 'hostname')) {
		db_install_execute(
			'ALTER TABLE `host`
				ADD INDEX `hostname` (`hostname`)'
		);
	}
}

