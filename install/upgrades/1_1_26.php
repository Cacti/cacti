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

function upgrade_to_1_1_26() {
	db_install_add_key('host', 'key', 'status', array('status'));
	db_install_add_key('user_auth_cache', 'key', 'last_update', array('last_update'));
	db_install_add_key('poller_output_realtime', 'key', 'time', array('time'));
	db_install_add_key('poller_time', 'key', 'poller_id_end_time', array('poller_id', 'end_time'));

	if (db_column_exists('poller_item', 'rrd_next_step')) {
		db_install_add_key('poller_item', 'key', 'poller_id_rrd_next_step', array('poller_id', 'rrd_next_step'));
	}

	if (db_column_exists('poller_item', 'rrd_next_step')) {
		db_install_drop_key('poller_item', 'key', 'rrd_next_step');
	}
}
