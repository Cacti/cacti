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

/* update_data_source_title_cache_from_template - updates the title cache for all data sources
	that match a given data template
   @arg $data_template_id - (int) the ID of the data template to match */
function update_data_source_title_cache_from_template($data_template_id) {
	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' local_data_id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id > 0',
		array($data_template_id));

	if (cacti_sizeof($data)) {
		foreach ($data as $item) {
			update_data_source_title_cache($item['local_data_id']);
		}
	}
}

/* update_data_source_title_cache_from_query - updates the title cache for all data sources
	that match a given data query/index combination
   @arg $snmp_query_id - (int) the ID of the data query to match
   @arg $snmp_index - the index within the data query to match */
function update_data_source_title_cache_from_query($snmp_query_id, $snmp_index) {
	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
		FROM data_local
		WHERE snmp_query_id = ?
		AND snmp_index = ?',
		array($snmp_query_id, $snmp_index));

	if (cacti_sizeof($data) > 0) {
		foreach ($data as $item) {
			update_data_source_title_cache($item['id']);
		}
	}
}

/* update_data_source_title_cache_from_host - updates the title cache for all data sources
	that match a given host
   @arg $host_id - (int) the ID of the host to match */
function update_data_source_title_cache_from_host($host_id, $query_id = 0, $ids = array()) {
	if ($query_id > 0 && !cacti_sizeof($ids)) {
	$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
		FROM data_local
			WHERE host_id = ?
			AND snmp_query_id = ?',
			array($host_id, $query_id));
	} elseif ($query_id > 0) {
		$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
			FROM data_local
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND id IN (?)',
			array($host_id, $query_id, implode(',', $ids)));
	} else {
		$data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
			FROM data_local
			WHERE host_id = ?',
			array($host_id));
	}

	if (cacti_sizeof($data)) {
		foreach ($data as $item) {
			update_data_source_title_cache($item['id']);
		}
	}
}

/* update_data_source_title_cache - updates the title cache for a single data source
   @arg $local_data_id - (int) the ID of the data source to update the title cache for */
function update_data_source_title_cache($local_data_id) {
	$old_title = db_fetch_cell_prepared('SELECT name_cache
		FROM data_template_data
		WHERE local_data_id = ?',
		array($local_data_id));
	
	$data_source = get_data_source_title($local_data_id);

	if (strstr($data_source, '|query_') !== false || strstr($data_source, '|host_') !== false) {
		if ($old_title == '') {
			db_execute_prepared('UPDATE data_template_data
				SET name_cache = ?
				WHERE local_data_id = ?',
				array($data_source, $local_data_id));

			api_plugin_hook_function('update_data_source_title_cache', $local_data_id);
		}
	} else {
		db_execute_prepared('UPDATE data_template_data
			SET name_cache = ?
			WHERE local_data_id = ?',
			array($data_source, $local_data_id));

		api_plugin_hook_function('update_data_source_title_cache', $local_data_id);
	}
}

/* update_graph_title_cache_from_template - updates the title cache for all graphs
	that match a given graph template
   @arg $graph_template_id - (int) the ID of the graph template to match */
function update_graph_title_cache_from_template($graph_template_id) {
	$graphs = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' local_graph_id
		FROM graph_templates_graph
		WHERE graph_template_id = ?
		AND local_graph_id > 0',
		array($graph_template_id));

	if (cacti_sizeof($graphs) > 0) {
		foreach ($graphs as $item) {
			update_graph_title_cache($item['local_graph_id']);
		}
	}
}

/* update_graph_title_cache_from_query - updates the title cache for all graphs
	that match a given data query/index combination
   @arg $snmp_query_id - (int) the ID of the data query to match
   @arg $snmp_index - the index within the data query to match */
function update_graph_title_cache_from_query($snmp_query_id, $snmp_index) {
	$graphs = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
		FROM graph_local
		WHERE snmp_query_id = ?
		AND snmp_index = ?',
		array($snmp_query_id, $snmp_index));

	if (cacti_sizeof($graphs) > 0) {
		foreach ($graphs as $item) {
			update_graph_title_cache($item['id']);
		}
	}
}

/* update_graph_title_cache_from_host - updates the title cache for all graphs
	that match a given host
   @arg $host_id - (int) the ID of the host to match */
function update_graph_title_cache_from_host($host_id, $query_id = 0, $ids = array()) {
	if ($query_id > 0 && !cacti_sizeof($ids)) {
	$graphs = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
		FROM graph_local
			WHERE host_id = ?
			AND snmp_query_id = ?',
			array($host_id, $query_id));
	} elseif ($query_id > 0) {
		$graphs = db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . ' gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_item AS gti
			ON gti.local_graph_id = gl.id
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND dtr.local_data_id IN(?)',
			array($host_id, $query_id, implode(',', $ids)));
	} else {
		$graphs = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
			FROM graph_local
			WHERE host_id = ?',
			array($host_id));
	}

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $item) {
			update_graph_title_cache($item['id']);
		}
	}
}

/* update_graph_title_cache - updates the title cache for a single graph
   @arg $local_graph_id - (int) the ID of the graph to update the title cache for */
function update_graph_title_cache($local_graph_id) {
	$old_title = db_fetch_cell_prepared('SELECT title_cache
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($local_graph_id));

	$graph_title = get_graph_title($local_graph_id);

	if (strstr($graph_title, '|query_') !== false || strstr($graph_title, '|host_') !== false) {
		if ($old_title == '') {
			db_execute_prepared('UPDATE graph_templates_graph
				SET title_cache = ?
				WHERE local_graph_id = ?',
				array($graph_title, $local_graph_id));
		}
	} else {
		db_execute_prepared('UPDATE graph_templates_graph
			SET title_cache = ?
			WHERE local_graph_id = ?',
			array($graph_title, $local_graph_id));
	}
}

/* null_out_substitutions - takes a string and cleans out any host variables that do not have values
   @arg $string - the string to clean out unsubstituted variables for
   @returns - the cleaned up string */
function null_out_substitutions($string) {
	if ($string != '') {
		return preg_replace("/\|host_" . VALID_HOST_FIELDS . "\|( - )?/i", '', $string);
	} else {
		return $string;
	}
}

/* expand_title - takes a string and substitutes all data query variables contained in it or cleans
	them out if no data query is in use
   @arg $host_id - (int) the host ID to match
   @arg $snmp_query_id - (int) the data query ID to match
   @arg $snmp_index - the data query index to match
   @arg $title - the original string that contains the data query variables
   @returns - the original string with all of the variable substitutions made */
function expand_title($host_id, $snmp_query_id, $snmp_index, $title) {
	if ((strstr($title, '|')) && (!empty($host_id))) {
		if (($snmp_query_id != '0') && ($snmp_index != '')) {
			$title = substitute_snmp_query_data(null_out_substitutions(substitute_host_data($title, '|', '|', $host_id)), $host_id, $snmp_query_id, $snmp_index, read_config_option('max_data_query_field_length'));
		} else {
			$title = null_out_substitutions(substitute_host_data($title, '|', '|', $host_id));
		}
	} else {
		$title = null_out_substitutions($title);
	}

	$data = array(
		'host_id'       => $host_id,
		'snmp_query_id' => $snmp_query_id,
		'snmp_index'    => $snmp_index,
		'title'         => $title
	);

	$data = api_plugin_hook_function('expand_title', $data);
	if (isset($data['title'])) {
		$title = $data['title'];
	}

	return $title;
}

/* substitute_script_query_path - takes a string and substitutes all path variables contained in it
   @arg $path - the string to make path variable substitutions on
   @returns - the original string with all of the variable substitutions made */
function substitute_script_query_path($path) {
	global $config;

	$path = clean_up_path(str_replace('|path_cacti|', $config['base_path'], $path));
	$path = clean_up_path(str_replace('|path_php_binary|', read_config_option('path_php_binary'), $path));

	return $path;
}

/* substitute_host_data - takes a string and substitutes all host variables contained in it
   @arg $string - the string to make host variable substitutions on
   @arg $l_escape_string - the character used to escape each variable on the left side
   @arg $r_escape_string - the character used to escape each variable on the right side
   @arg $host_id - (int) the host ID to match
   @returns - the original string with all of the variable substitutions made */
function substitute_host_data($string, $l_escape_string, $r_escape_string, $host_id) {
	if (!empty($host_id)) {
		$host = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' h.*, s.name AS site_name
			FROM host AS h
			LEFT JOIN sites AS s
			ON h.site_id = s.id
			WHERE h.id = ?', array($host_id));

		if (!cacti_sizeof($host)) {
			return $string;
		}

		$search  = array();
		$replace = array();

		$search[]  = $l_escape_string . 'host_management_ip' . $r_escape_string; /* for compatibility */
		$replace[] = $host['hostname']; /* for compatibility */

		/* common host columns */
		$search[]  = $l_escape_string . 'host_id' . $r_escape_string;
		$replace[] = $host['id'];
		$search[]  = $l_escape_string . 'host_hostname' . $r_escape_string;
		$replace[] = $host['hostname'];
		$search[]  = $l_escape_string . 'host_description' . $r_escape_string;
		$replace[] = $host['description'];
		$search[]  = $l_escape_string . 'host_site' . $r_escape_string;
		$replace[] = $host['site_name'];
		$search[]  = $l_escape_string . 'host_notes' . $r_escape_string;
		$replace[] = $host['notes'];
		$search[]  = $l_escape_string . 'host_location' . $r_escape_string;
		$replace[] = $host['location'];
		$search[]  = $l_escape_string . 'host_polling_time' . $r_escape_string;
		$replace[] = $host['polling_time'];
		$search[]  = $l_escape_string . 'host_avg_time' . $r_escape_string;
		$replace[] = $host['avg_time'];
		$search[]  = $l_escape_string . 'host_cur_time' . $r_escape_string;
		$replace[] = $host['cur_time'];
		$search[]  = $l_escape_string . 'host_availability' . $r_escape_string;
		$replace[] = $host['availability'];
		$search[]  = $l_escape_string . 'host_uptime' . $r_escape_string;
		$replace[] = get_uptime($host);

		/* snmp connectivity information */
		$search[]  = $l_escape_string . 'host_snmp_community' . $r_escape_string;
		$replace[] = $host['snmp_community'];
		$search[]  = $l_escape_string . 'host_snmp_version' . $r_escape_string;
		$replace[] = $host['snmp_version'];
		$search[]  = $l_escape_string . 'host_snmp_username' . $r_escape_string;
		$replace[] = $host['snmp_username'];
		$search[]  = $l_escape_string . 'host_snmp_password' . $r_escape_string;
		$replace[] = $host['snmp_password'];
		$search[]  = $l_escape_string . 'host_snmp_auth_protocol' . $r_escape_string;
		$replace[] = $host['snmp_auth_protocol'];
		$search[]  = $l_escape_string . 'host_snmp_priv_passphrase' . $r_escape_string;
		$replace[] = $host['snmp_priv_passphrase'];
		$search[]  = $l_escape_string . 'host_snmp_priv_protocol' . $r_escape_string;
		$replace[] = $host['snmp_priv_protocol'];
		$search[]  = $l_escape_string . 'host_snmp_context' . $r_escape_string;
		$replace[] = $host['snmp_context'];
		$search[]  = $l_escape_string . 'host_snmp_engine_id' . $r_escape_string;
		$replace[] = $host['snmp_engine_id'];
		$search[]  = $l_escape_string . 'host_snmp_port' . $r_escape_string;
		$replace[] = $host['snmp_port'];
		$search[]  = $l_escape_string . 'host_snmp_timeout' . $r_escape_string;
		$replace[] = $host['snmp_timeout'];

		/* snmp system information */
		$search[]  = $l_escape_string . 'host_snmp_sysDescr' . $r_escape_string;
		$replace[] = $host['snmp_sysDescr'];
		$search[]  = $l_escape_string . 'host_snmp_sysObjectID' . $r_escape_string;
		$replace[] = $host['snmp_sysObjectID'];
		$search[]  = $l_escape_string . 'host_snmp_sysContact' . $r_escape_string;
		$replace[] = $host['snmp_sysContact'];
		$search[]  = $l_escape_string . 'host_snmp_sysLocation' . $r_escape_string;
		$replace[] = $host['snmp_sysLocation'];
		$search[]  = $l_escape_string . 'host_snmp_sysName' . $r_escape_string;
		$replace[] = $host['snmp_sysName'];
		$search[]  = $l_escape_string . 'host_snmp_sysUpTimeInstance' . $r_escape_string;
		$replace[] = $host['snmp_sysUpTimeInstance'];

		$search[]  = $l_escape_string . 'host_ping_retries' . $r_escape_string;
		$replace[] = $host['ping_retries'];
		$search[]  = $l_escape_string . 'host_max_oids' . $r_escape_string;
		$replace[] = $host['max_oids'];

		/* handle the external id */
		$search[]  = $l_escape_string . 'host_external_id' . $r_escape_string;
		$replace[] = $host['external_id'];

		$string = str_replace($search, $replace, $string);

		$temp = api_plugin_hook_function(
			'substitute_host_data',
			array('string' => $string, 'l_escape_string' => $l_escape_string, 'r_escape_string' => $r_escape_string, 'host_id' => $host_id)
		);

		$string = $temp['string'];
	}

	return $string;
}

/* substitute_snmp_query_data - takes a string and substitutes all data query variables contained in it
   @arg $string - the original string that contains the data query variables
   @arg $host_id - (int) the host ID to match
   @arg $snmp_query_id - (int) the data query ID to match
   @arg $snmp_index - the data query index to match
   @arg $max_chars - the maximum number of characters to substitute
   @returns - the original string with all of the variable substitutions made */
function substitute_snmp_query_data($string, $host_id, $snmp_query_id, $snmp_index, $max_chars = 0) {
	if ($host_id > 0) {
		$snmp_cache_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' field_name, field_value
			FROM host_snmp_cache
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND snmp_index = ?',
			array($host_id, $snmp_query_id, $snmp_index));
	} else {
		$snmp_cache_data = db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . ' field_name, field_value
			FROM host_snmp_cache
			WHERE snmp_query_id = ?
			AND snmp_index = ?
			AND host_id = 0',
			array($snmp_query_id, $snmp_index));
	}

	if (cacti_sizeof($snmp_cache_data)) {
		foreach ($snmp_cache_data as $data) {
			if (trim($data['field_value']) != '') {
				if ($max_chars > 0) {
					$data['field_value'] = substr($data['field_value'], 0, $max_chars);
				}

				$string = stri_replace('|query_' . $data['field_name'] . '|', $data['field_value'], $string);

				if (strpos($string, 'query_') === false) {
					break;
				}
			}
		}
	}

	return $string;
}

/* substitute_data_input_data - takes a string and substitutes all data input variables contained in it
   @arg $string - the original string that contains the data input variables
   @arg $local_data_id - (int) the local data id to match
   @arg $max_chars - the maximum number of characters to substitute
   @returns - the original string with all of the variable substitutions made */
function substitute_data_input_data($string, $graph, $local_data_id, $max_chars = 0) {
	if (empty($local_data_id)) {
		if (isset($graph['local_graph_id'])) {
			$local_data_ids = array_rekey(db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' DISTINCT local_data_id
				FROM data_template_rrd
				INNER JOIN graph_templates_item
				ON data_template_rrd.id = graph_templates_item.task_item_id
				WHERE local_graph_id = ?', array($graph['local_graph_id'])), 'local_data_id', 'local_data_id');

			if (cacti_sizeof($local_data_ids)) {
				$data_template_data_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' id
					FROM data_template_data
					WHERE local_data_id IN (' . implode(',', $local_data_ids) . ')');
			} else {
				$data_template_data_id = 0;
			}
		} else {
			$data_template_data_id = 0;
		}
	} else {
		$data_template_data_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' id
			FROM data_template_data
			WHERE local_data_id = ?',
			array($local_data_id));
	}

	if (!empty($data_template_data_id)) {
		$data = db_fetch_assoc_prepared("SELECT " . SQL_NO_CACHE . "
			dif.data_name, did.value
			FROM data_input_fields AS dif
			INNER JOIN data_input_data AS did
			ON dif.id = did.data_input_field_id
			WHERE data_template_data_id = ?
			AND input_output = 'in'",
			array($data_template_data_id));

		if (cacti_sizeof($data)) {
			foreach ($data as $item) {
				if ($item['value'] != '') {
					if ($max_chars > 0) {
						$item['value'] = substr($item['field_value'], 0, $max_chars);
					}

					$string = stri_replace('|input_' . $item['data_name'] . '|', $item['value'], $string);
				}
			}
		}
	}

	return $string;
}

