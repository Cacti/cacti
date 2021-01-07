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

function upgrade_to_1_2_3() {
	// Correct max values in templates and data sources: GAUGE/ABSOLUTE (1,4)
	db_install_execute("UPDATE data_template_rrd
		SET rrd_maximum='U'
		WHERE rrd_maximum = '0'
		AND rrd_minimum = '0'
		AND data_source_type_id IN(1,4)");

	// Correct min/max values in templates and data sources: DERIVE/DDERIVE (3,7)
	db_install_execute("UPDATE data_template_rrd
		SET rrd_maximum='U', rrd_minimum='U'
		WHERE (rrd_maximum = '0' OR rrd_minimum = '0')
		AND data_source_type_id IN(3,7)");

	// Speed up Data Sources page
	db_install_add_key('data_template_data', 'key', 'name_cache', array('name_cache(191)'));
}
