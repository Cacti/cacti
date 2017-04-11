<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

function upgrade_to_1_1_3() {
	db_install_execute(
		'ALTER TABLE `cdef` ADD INDEX (`hash`)'
	);

	db_install_execute(
		'ALTER TABLE `cdef_items` 
			DROP INDEX `cdef_id`,
			ADD INDEX `cdef_id_sequence` (`cdef_id`, `sequence`)'
	);

	db_install_execute(
		'ALTER TABLE `snmpagent_cache` 
			DROP INDEX `mib`,
			ADD INDEX `mib_name` (`mib`, `name`)'
	);

	db_install_execute(
		'ALTER TABLE `host` ADD INDEX (`hostname`)'
	);

	db_install_execute(
		'ALTER TABLE `snmpagent_managers_notifications` DROP INDEX `manager_id`'
	);

	db_install_execute(
		'ALTER TABLE `snmpagent_notifications_log` DROP INDEX `manager_id`'
	);

	db_install_execute(
		'ALTER TABLE `user_auth_group_members` DROP INDEX `group_id`'
	);

	db_install_execute(
		'ALTER TABLE `user_auth_group_realm` DROP INDEX `group_id`'
	);

	db_install_execute(
		'ALTER TABLE `user_log` DROP INDEX `username`'
	);

	db_install_execute(
		'ALTER TABLE `vdef` ADD INDEX `hash` (`hash`)'
	);

	db_install_execute(
		'ALTER TABLE `vdef_items` 
			DROP INDEX `vdef_id`, 
			ADD INDEX `vdef_id_sequence` (`vdef_id`, `sequence`)'
	);

	db_install_execute(
		'ALTER TABLE `graph_templates_item`
  			ADD INDEX `lgi_gti` (`local_graph_id`, `graph_template_id`)'
	);

	db_install_execute(
		'ALTER TABLE `poller_item`
  			ADD INDEX `poller_id_host_id` (`poller_id`, `host_id`)'
	);
}
