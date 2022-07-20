<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

function upgrade_to_1_2_11() {
	db_install_execute("CREATE TABLE IF NOT EXISTS `processes` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`pid` int(10) unsigned NOT NULL DEFAULT 0,
		`tasktype` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
		`taskname` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
		`taskid` int(10) unsigned NOT NULL DEFAULT 0,
		`timeout` int(11) DEFAULT 300,
		`started` timestamp NOT NULL DEFAULT current_timestamp(),
		`last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (`pid`,`tasktype`,`taskname`,`taskid`),
		KEY `tasktype` (`tasktype`),
		KEY `pid` (`pid`),
		KEY `id` (`id`))
		ENGINE=MEMORY
		COMMENT='Stores Process Status for Cacti Background Processes'");
}

