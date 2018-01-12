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

function upgrade_to_0_8_1() {
	db_install_add_column ('user_log', array('name' => 'user_id', 'type' => 'mediumint(8)', 'NULL' => false, 'after' => 'username'));
	db_install_execute("ALTER TABLE user_log change time time datetime not null;");
	db_install_execute("ALTER TABLE user_log drop primary key;");
	db_install_execute("ALTER TABLE user_log add primary key (username, user_id, time);");
	db_install_add_column ('user_auth', array('name' => 'realm', 'type' => 'mediumint(8)', 'NULL' => false, 'after' => 'password'));
	db_install_execute("UPDATE user_auth set realm = 1 where full_name='ldap user';");

	$_src = db_fetch_assoc("select id, username from user_auth");

	if (sizeof($_src) > 0) {
		foreach ($_src as $item) {
			db_install_execute("UPDATE user_log set user_id = " . $item["id"] . " where username = '" . $item["username"] . "';");
		}
	}
}
