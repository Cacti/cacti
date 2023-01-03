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

function upgrade_to_0_8_7a() {
	/* add alpha channel to graph items */
	db_install_add_column('graph_templates_item', array('name' => 'alpha', 'type' => 'char(2)', 'NULL' => false, 'after' => 'color_id', 'default' => 'FF'));

	/* add units=si as an option */
	db_install_add_column('graph_templates_graph', array('name' => 't_scale_log_units', 'type' => 'char(2)', 'NULL' => false, 'after' => 'auto_scale_log', 'default' => 0));
	db_install_add_column('graph_templates_graph', array('name' => 'scale_log_units', 'type' => 'char(2)', 'NULL' => false, 'after' => 't_scale_log_units', 'default' => ''));
}
