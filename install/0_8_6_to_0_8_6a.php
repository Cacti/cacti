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

function upgrade_to_0_8_6a() {
	/* fix import/export template bug */
	$item = db_fetch_assoc("select id from data_template");
	for ($i=0; $i<count($item); $i++) {
		db_execute("update data_template set hash='" . get_hash_data_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		$item2 = db_fetch_assoc("select id from data_template_rrd where data_template_id=" . $item[$i]["id"] . " and local_data_id=0");
		for ($j=0; $j<count($item2); $j++) {
			db_execute("update data_template_rrd set hash='" . get_hash_data_template($item2[$j]["id"], "data_template_item") . "' where id=" . $item2[$j]["id"] . ";");
		}
	}

	$item = db_fetch_assoc("select id from graph_templates");
	for ($i=0; $i<count($item); $i++) {
		db_execute("update graph_templates set hash='" . get_hash_graph_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		$item2 = db_fetch_assoc("select id from graph_templates_item where graph_template_id=" . $item[$i]["id"] . " and local_graph_id=0");
		for ($j=0; $j<count($item2); $j++) {
			db_execute("update graph_templates_item set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_item") . "' where id=" . $item2[$j]["id"] . ";");
		}
		$item2 = db_fetch_assoc("select id from graph_template_input where graph_template_id=" . $item[$i]["id"]);
		for ($j=0; $j<count($item2); $j++) {
			db_execute("update graph_template_input set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_input") . "' where id=" . $item2[$j]["id"] . ";");
		}
	}
}

?>
