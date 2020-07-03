<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

function upgrade_to_1_3_0() {
	db_install_change_column('version', array('name' => 'cacti', 'type' => 'char(30)', 'null' => false, 'default' => ''));
	db_install_add_column('user_auth', array('name' => 'tfa_enabled', 'type' => 'char(3)', 'null' => false, 'default' => ''));
	db_install_add_column('user_auth', array('name' => 'tfa_secret', 'type' => 'char(50)', 'null' => false, 'default' => ''));
	db_install_add_column('poller', array('name' => 'log_level', 'type' => 'int', 'null' => false, 'default' => '-1'));
	db_install_add_column('host', array('name' => 'created', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'));
}
