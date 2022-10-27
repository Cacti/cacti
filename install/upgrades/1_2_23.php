<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

function upgrade_to_1_2_23() {
	db_install_execute("CREATE TABLE IF NOT EXISTS `rrdcheck` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`test_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`message` varchar(250) default '')
		 ENGINE=InnoDB ROW_FORMAT=Dynamic
		 COMMENT='Store result of RRDcheck'");

	if (!db_column_exists('host_template', 'class')) {
		db_install_execute('ALTER TABLE host_template ADD COLUMN class varchar(40) NOT NULL default "" AFTER name');
	}
}

