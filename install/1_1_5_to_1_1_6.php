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

function upgrade_to_1_1_6() {
	db_install_execute('ALTER TABLE `data_input` MODIFY COLUMN `input_string` varchar(512) default NULL');

	db_install_execute("ALTER TABLE `snmp_query_graph` ADD KEY `graph_template_id_name` (`graph_template_id`, `name`)");

	db_install_execute(
		"ALTER TABLE `graph_templates` 
			ADD `multiple` TINYINT(1) UNSIGNED NULL DEFAULT '0' AFTER `name`,
			ADD KEY `multiple_name` (`multiple`, `name`)"
	);

	db_execute_prepared("UPDATE graph_templates SET multiple = 1 WHERE hash = '010b90500e1fc6a05abfd542940584d0'");
}
