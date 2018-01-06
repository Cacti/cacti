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

function upgrade_to_1_1_2() {
	db_install_execute('ALTER TABLE `graph_templates_item`
		DROP INDEX `local_graph_id`,
		ADD INDEX `local_graph_id_sequence` (`local_graph_id`, `sequence`)');

	db_install_execute('ALTER TABLE `graph_tree_items`
		DROP INDEX `parent`,
		ADD INDEX `parent_position` (`parent`, `position`)');
	
	db_install_execute('ALTER TABLE `graph_template_input_defs`
		COMMENT = \'Stores the relationship for what graph items are associated\';');

	db_install_execute('ALTER TABLE `graph_tree` ADD INDEX `sequence` (`sequence`)');

	db_install_execute('UPDATE graph_templates_item SET hash="" WHERE local_graph_id>0');
}
