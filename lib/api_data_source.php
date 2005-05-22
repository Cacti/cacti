<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

function api_data_source_remove($local_data_id) {
	if (empty($local_data_id)) {
		return;
	}

	$data_template_data_id = db_fetch_cell("select id from data_template_data where local_data_id=$local_data_id");

	if (!empty($data_template_data_id)) {
		db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id");
		db_execute("delete from data_input_data where data_template_data_id=$data_template_data_id");
	}

	db_execute("delete from data_template_data where local_data_id=$local_data_id");
	db_execute("delete from data_template_rrd where local_data_id=$local_data_id");
	db_execute("delete from poller_item where local_data_id=$local_data_id");
	db_execute("delete from data_local where id=$local_data_id");
}

function api_data_source_enable($local_data_id) {
    db_execute("UPDATE data_template_data SET active='on' WHERE local_data_id=$local_data_id");
	update_poller_cache($local_data_id, false);
 }

function api_data_source_disable($local_data_id) {
	db_execute("DELETE FROM poller_item WHERE local_data_id=$local_data_id");
	db_execute("UPDATE data_template_data SET active='' WHERE local_data_id=$local_data_id");
 }

?>