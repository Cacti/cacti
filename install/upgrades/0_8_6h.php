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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_6h() {
	/* changes for ping result times */
	db_install_execute('ALTER TABLE `host` MODIFY COLUMN `min_time` DECIMAL(10,5) DEFAULT 9.99999;');
	db_install_execute('ALTER TABLE `host` MODIFY COLUMN `max_time` DECIMAL(10,5) DEFAULT 0.00000;');
	db_install_execute('ALTER TABLE `host` MODIFY COLUMN `cur_time` DECIMAL(10,5) DEFAULT 0.00000;');
	db_install_execute('ALTER TABLE `host` MODIFY COLUMN `avg_time` DECIMAL(10,5) DEFAULT 0.00000;');

	/* Changes to user_log */
	db_install_execute('ALTER TABLE `user_log` MODIFY COLUMN `ip` VARCHAR(40);');

	/* Fixes broken graphs that have graph items with legend text but no color assigned */
	db_install_execute("UPDATE graph_templates_item SET text_format = '' WHERE local_graph_id <> 0 AND color_id = 0 AND graph_type_id IN(4,5,6,7,8) AND text_format <> '';");
}
