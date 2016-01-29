<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function upgrade_to_0_8_7a() {
	/* add alpha channel to graph items */
	db_install_execute("0.8.7a", "ALTER TABLE `graph_templates_item` ADD COLUMN `alpha` CHAR(2) DEFAULT 'FF' AFTER `color_id`;");
	/* add units=si as an option */
	db_install_execute("0.8.7a", "ALTER TABLE `graph_templates_graph` ADD COLUMN `t_scale_log_units` CHAR(2) DEFAULT 0 AFTER `auto_scale_log`, ADD COLUMN `scale_log_units` CHAR(2) DEFAULT '' AFTER `t_scale_log_units`;");
}
?>
