<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

function api_data_source_remove_multi($local_data_ids) {
	$ids_to_delete     = "";
	$dtd_ids_to_delete = "";
	$i = 0;
	$j = 0;

	if (sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			if ($i == 0) {
				$ids_to_delete .= $local_data_id;
			}else{
				$ids_to_delete .= ", " . $local_data_id;
			}

			$i++;

			if (($i % 1000) == 0) {
				$data_template_data_ids = db_fetch_assoc("SELECT id
					FROM data_template_data
					WHERE local_data_id IN ($ids_to_delete)");

				if (sizeof($data_template_data_ids)) {
					foreach($data_template_data_ids as $data_template_data_id) {
						if ($j == 0) {
							$dtd_ids_to_delete .= $data_template_data_id["id"];
						}else{
							$dtd_ids_to_delete .= ", " . $data_template_data_id["id"];
						}

						$j++;

						if (($j % 1000) == 0) {
							db_execute("DELETE FROM data_template_data_rra WHERE data_template_data_id IN ($dtd_ids_to_delete)");
							db_execute("DELETE FROM data_input_data WHERE data_template_data_id IN ($dtd_ids_to_delete)");

							$dtd_ids_to_delete = "";
							$j = 0;
						}
					}

					if ($j > 0) {
						db_execute("DELETE FROM data_template_data_rra WHERE data_template_data_id IN ($dtd_ids_to_delete)");
						db_execute("DELETE FROM data_input_data WHERE data_template_data_id IN ($dtd_ids_to_delete)");
					}
				}

				db_execute("DELETE FROM data_template_data WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM data_template_rrd WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM data_local WHERE id IN ($ids_to_delete)");

				$i = 0;
				$ids_to_delete = "";
			}
		}
	}

	if ($i > 0) {
		db_execute("DELETE FROM data_template_data WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM data_template_rrd WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM data_local WHERE id IN ($ids_to_delete)");
	}
}

function api_data_source_enable($local_data_id) {
	db_execute("UPDATE data_template_data SET active='on' WHERE local_data_id=$local_data_id");
	update_poller_cache($local_data_id, true);
 }

function api_data_source_disable($local_data_id) {
	db_execute("DELETE FROM poller_item WHERE local_data_id=$local_data_id");
	db_execute("UPDATE data_template_data SET active='' WHERE local_data_id=$local_data_id");
}

function api_data_source_disable_multi($local_data_ids) {
	/* initialize variables */
	$ids_to_disable = "";
	$i = 0;

	/* build the array */
	if (sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			if ($i == 0) {
				$ids_to_disable .= $local_data_id;
			}else{
				$ids_to_disable .= ", " . $local_data_id;
			}

			$i++;

			if (!($i % 1000)) {
				db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)");
				db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)");

				$i = 0;
				$ids_to_disable = "";
			}
		}

		if ($i > 0) {
			db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)");
			db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)");
		}
	}
}

function api_reapply_suggested_data_source_title($local_data_id) {
	global $config;

	$data_template_data_id = db_fetch_cell("select id from data_template_data where local_data_id=$local_data_id");
	if (empty($data_template_data_id)) {
		return;
	}

	/* require query type data sources only (snmp_query_id > 0) */
	$data_local = db_fetch_row("SELECT id, host_id, data_template_id, snmp_query_id, snmp_index FROM data_local WHERE snmp_query_id>0 AND id=$local_data_id");
	/* if this is not a data query graph, simply return */
	if (!isset($data_local["host_id"])) {
		return;
	}

	$snmp_query_graph_id = db_fetch_cell("SELECT " .
		"data_input_data.value from data_input_data " .
		"JOIN data_input_fields ON (data_input_data.data_input_field_id=data_input_fields.id) " .
		"JOIN data_template_data ON (data_template_data.id = data_input_data.data_template_data_id) ".
		"WHERE data_input_fields.type_code = 'output_type' " .
		"AND data_template_data.local_data_id=" . $data_local["id"] );

	/* no snmp query graph id found */
	if ($snmp_query_graph_id == 0) {
		return;
	}

	$suggested_values = db_fetch_assoc("SELECT " .
		"text, " .
		"field_name " .
		"FROM snmp_query_graph_rrd_sv " .
		"WHERE snmp_query_graph_id=" . $snmp_query_graph_id . " " . 
		"AND data_template_id=" . $data_local["data_template_id"] . " " .
		"AND field_name = 'name' " .
		"ORDER BY sequence");

	$suggested_values_data = array();
	if (sizeof($suggested_values) > 0) {
		foreach ($suggested_values as $suggested_value) {
			if(!isset($suggested_values_data{$suggested_value["field_name"]})) {
 				$subs_string = substitute_snmp_query_data($suggested_value["text"],$data_local["host_id"],
								$data_local["snmp_query_id"], $data_local["snmp_index"],
								read_config_option("max_data_query_field_length"));
				/* if there are no '|query' characters, all of the substitutions were successful */
				if (!substr_count($subs_string, "|query")) {
					db_execute("UPDATE data_template_data SET " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' WHERE local_data_id=" . $local_data_id);
					/* once we find a working value for that very field, stop */
					$suggested_values_data{$suggested_value["field_name"]} = true;
				}
			}
		}
	}
}
?>
