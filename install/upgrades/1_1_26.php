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

function upgrade_to_1_1_26() {
	db_install_execute(
		'ALTER TABLE `host` ADD KEY `status` (`status`);'
	);

	db_install_execute(
		'ALTER TABLE `user_auth_cache` ADD KEY `last_update` (`last_update`);'
	);

	db_install_execute(
		'ALTER TABLE `poller_output_realtime` ADD KEY `time` (`time`);'
	);

	db_install_execute(
		'ALTER TABLE `poller_time` ADD KEY `poller_id_end_time` (`poller_id`, `end_time`);'
	);

	db_install_execute(
		'ALTER TABLE `poller_item` 
		 DROP KEY `rrd_next_step`,
		 ADD KEY `poller_id_rrd_next_step` (`poller_id`, `rrd_next_step`);'
	);
}
