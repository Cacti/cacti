<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

function upgrade_to_1_2_32() {
	/* #7728 landed these columns in cacti.sql and in upgrade_to_1_2_31() after
	 * 1.2.31 had already shipped. Installs sitting on 1.2.31 never re-run that
	 * file. db_install_add_column() is a no-op when the column exists, so
	 * 1.2.30 -> 1.2.32 is safe. */
	if (!db_table_exists('poller_output_boost_processes')) {
		db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_processes` (
			`sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
			`status` varchar(255) default NULL,
			PRIMARY KEY (`sock_int_value`))
			ENGINE=MEMORY");
	} else {
		db_install_execute('TRUNCATE TABLE poller_output_boost_processes');
	}

	db_install_add_column('poller_output_boost_processes', array('name' => 'run_id', 'type' => 'char(32)', 'NULL' => false, 'default' => '', 'after' => 'sock_int_value'));
	db_install_add_column('poller_output_boost_processes', array('name' => 'child_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'run_id'));
	db_install_add_key('poller_output_boost_processes', 'UNIQUE', 'run_child', array('run_id', 'child_id'));
}
