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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_1_17() {
	// Finalize fix to LDAP authentication
	db_install_execute('UPDATE user_auth SET realm=3 WHERE realm=1');

	if (!db_column_exists('data_source_profiles_rra', 'timespan')) {
		db_install_execute('ALTER TABLE data_source_profiles_rra
			ADD COLUMN timespan int(10) unsigned NOT NULL DEFAULT "0"');

		$rras_results = db_install_fetch_assoc("SELECT * FROM data_source_profiles_rra");
		$rras         = $rras_results['data'];

		if (cacti_sizeof($rras)) {
			foreach($rras as $rra) {
				$interval_results = db_install_fetch_cell('SELECT step
					FROM data_source_profiles
					WHERE id = ?',
					array($rra['data_source_profile_id']));
				$interval = $interval_results['data'];

				$timespan = $rra['steps'] * $interval * $rra['rows'];

				$timespan = get_nearest_timespan($timespan);

				db_install_execute('UPDATE data_source_profiles_rra
					SET timespan = ?
					WHERE id = ?',
					array($timespan, $rra['id']));
			}
		}
	}
}
