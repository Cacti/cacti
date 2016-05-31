<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function run_data_query($host_id, $snmp_query_id) {
	global $config, $input_types;

	query_debug_timer_start();

	include_once($config['library_path'] . '/poller.php');
	include_once($config['library_path'] . '/api_data_source.php');
	include_once($config['library_path'] . '/utility.php');

	query_debug_timer_offset('data_query', "Running data query [$snmp_query_id].");

	$type_id = db_fetch_cell_prepared('SELECT data_input.type_id 
		FROM snmp_query
		INNER JOIN data_input 
		ON snmp_query.data_input_id=data_input.id 
		WHERE snmp_query.id = ?', array($snmp_query_id));

	if (isset($input_types[$type_id])) query_debug_timer_offset('data_query', "Found type = '" . $type_id . "' [" . $input_types[$type_id] . "].");

	if ($type_id == DATA_INPUT_TYPE_SNMP_QUERY) {
		$result = query_snmp_host($host_id, $snmp_query_id);
	}elseif ($type_id == DATA_INPUT_TYPE_SCRIPT_QUERY) {
		$result = query_script_host($host_id, $snmp_query_id);
	}elseif ($type_id == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) {
		$result = query_script_host($host_id, $snmp_query_id);
	}else{
		query_debug_timer_offset('data_query', "Unknown type = '$type_id'");
	}

	/* update the sort cache */
	update_data_query_sort_cache($host_id, $snmp_query_id);
	query_debug_timer_offset('data_query', 'Update data query sort cache complete');

	/* update the auto reindex cache */
	update_reindex_cache($host_id, $snmp_query_id);
	query_debug_timer_offset('data_query', 'Update re-index cache complete');

	/* update the the 'local' data query cache */
	update_data_query_cache($host_id, $snmp_query_id);
	query_debug_timer_offset('data_query', 'Update data query cache complete');

	/* update the poller cache */
	update_poller_cache_from_query($host_id, $snmp_query_id);
	query_debug_timer_offset('data_query', 'Update poller cache from query complete');

	/* perform any automation on reindex */
	automation_execute_data_query($host_id, $snmp_query_id);
	query_debug_timer_offset('data_query', 'Automation execute data query complete');

	api_plugin_hook_function('run_data_query', array('host_id' => $host_id, 'snmp_query_id' => $snmp_query_id));
	query_debug_timer_offset('data_query', 'Plugin hooks complete');

	return (isset($result) ? $result : true);
}

function get_data_query_array($snmp_query_id) {
	global $config, $data_query_xml_arrays;

	include_once($config['library_path'] . '/xml.php');

	/* load the array into memory if it hasn't been done yet */
	if (!isset($data_query_xml_arrays[$snmp_query_id])) {
		$xml_file_path = db_fetch_cell("SELECT xml_path FROM snmp_query WHERE id=$snmp_query_id");
		$xml_file_path = str_replace('<path_cacti>', $config['base_path'], $xml_file_path);

		if (!file_exists($xml_file_path)) {
			query_debug_timer_offset('data_query', "Could not find data query XML file at '$xml_file_path'");
			return false;
		}

		query_debug_timer_offset('data_query', "Found data query XML file at '$xml_file_path'");

		$data = implode('',file($xml_file_path));

		$xml_data = xml2array($data);

		/* store the array value to the global array for future reference */
		$data_query_xml_arrays[$snmp_query_id] = $xml_data;
	}

	return $data_query_xml_arrays[$snmp_query_id];
}

function query_script_host($host_id, $snmp_query_id) {
	$script_queries = get_data_query_array($snmp_query_id);

	/* invalid xml check */
	if ((!is_array($script_queries)) || (sizeof($script_queries) == 0)) {
		query_debug_timer_offset('data_query', 'Error parsing XML file into an array.');
		return false;
	}

	query_debug_timer_offset('data_query', 'XML file parsed ok.');

	/* are we talking to script server? */
	if (isset($script_queries['script_server'])) {
		$script_queries['script_path'] = "\"|path_php_binary|\" -q " . $script_queries['script_path'];
	}

	if (!verify_index_order($script_queries)) {
		query_debug_timer_offset('data_query', 'Invalid field &lt;index_order&gt;' . $script_queries['index_order'] . '&lt;/index_order&gt;');
		query_debug_timer_offset('data_query', 'Must contain &lt;direction&gt;input&lt;/direction&gt; fields only');
		return false;
	}

	/* provide data for arg_num_indexes, if given */
	if (isset($script_queries['arg_num_indexes'])) {
		$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ': '') . $script_queries['arg_num_indexes'], $script_queries['script_path'], $host_id);

		/* fetch specified index at specified OID */
		$script_num_index_array = exec_into_array($script_path);

		query_debug_timer_offset('data_query', 'Executing script for num of indexes' . " '$script_path'");
		foreach($script_num_index_array as $element) {
			query_debug_timer_offset('data_query', 'Found number of indexes: ' . $element);
		}
	} else {
		if (isset($script_queries['script_server'])) {
			query_debug_timer_offset('data_query', "&lt;arg_num_indexes&gt; missing in XML file, 'Index Count Changed' not supported");
		} else {
			query_debug_timer_offset('data_query', "&lt;arg_num_indexes&gt; missing in XML file, 'Index Count Changed' emulated by counting arg_index entries");
		}
	}

	/* provide data for index, mandatory */
	$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ': '') . $script_queries['arg_index'], $script_queries['script_path'], $host_id);

	/* fetch specified index */
	$script_index_array = exec_into_array($script_path);

	query_debug_timer_offset('data_query', 'Executing script for list of indexes' . " '$script_path' " . 'Index Count: ' . sizeof($script_index_array));

	debug_log_insert_section_start('data_query', "Click to show data query output for 'index'");

	foreach($script_index_array as $element) {
		debug_log_insert('data_query', 'Found index: ' . $element);
	}

	debug_log_insert_section_end('data_query');

	/* set an array to host all updates */
	$output_array = array();


	while (list($field_name, $field_array) = each($script_queries['fields'])) {
		if ($field_array['direction'] == 'input') {
			$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ': '') . $script_queries['arg_query'] . ' ' . $field_array['query_name'], $script_queries['script_path'], $host_id);

			debug_log_insert_section_start('data_query', "Click to show data query output for field '$field_name'");

			$script_data_array = exec_into_array($script_path);

			debug_log_insert('data_query', "Executing script query '$script_path'");

			foreach($script_data_array as $element) {
				if (preg_match('/(.*)' . preg_quote($script_queries['output_delimeter']) . '(.*)/', $element, $matches)) {
					$script_index = $matches[1];
					$field_value = $matches[2];

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $field_value, $script_index, '');

					debug_log_insert('data_query', "Found item [$field_name='$field_value'] index: $script_index");
				}
			}

			debug_log_insert_section_end('data_query');
		}
	}

	data_query_update_host_cache_from_buffer($host_id, $snmp_query_id, $output_array);

	return true;
}

function query_debug_timer_start() {
	global $query_debug_timer, $query_debug_start;

	/* record the start time */
	$query_debug_timer = microtime(true);
	$query_debug_start = $query_debug_timer;
}

function query_debug_timer_offset($section, $message) {
	global $query_debug_timer, $query_debug_start;

	if (empty($query_debug_timer)) {
		query_debug_timer_start();
	}

	/* record the start time */
	$cur_time = microtime(true);
	$delta = $cur_time - $query_debug_timer;
	$total = $cur_time - $query_debug_start;
	$query_debug_timer = $cur_time;

	debug_log_insert($section, 'Total: ' . round($total, 2) . ', Delta: ' . round($delta, 2) . ', ' . $message);

	return $delta;
}

function query_debug_timer_stop($sction, $message) {
	global $query_debug_timer, $query_debug_start;

	/* record the start time */
	$cur_time = microtime(true);
	$delta = $cur_time - $query_debug_timer;
	$total = $cur_time - $query_debug_start;
	unset($query_debug_timer);
	unset($query_debug_start);

	debug_log_insert($section, 'Total: ' . round($total, 2) . ', Delta: ' . round($delta, 2) . ', ' . $message);

	return $delta;
}

function query_snmp_host($host_id, $snmp_query_id) {
	global $config;

	include_once($config['library_path'] . '/snmp.php');

	$host = db_fetch_row("SELECT
		hostname,
		snmp_community,
		snmp_version,
		snmp_username,
		snmp_password,
		snmp_auth_protocol,
		snmp_priv_passphrase,
		snmp_priv_protocol,
		snmp_context,
		snmp_port,
		snmp_timeout,
		ping_retries,
		max_oids
		FROM host
		WHERE id='$host_id'");

	$snmp_queries = get_data_query_array($snmp_query_id);

	if ($host['hostname'] == '') {
		query_debug_timer_offset('data_query', "Invalid host_id: $host_id");
		return false;
	}

	/* invalid xml check */
	if ((!is_array($snmp_queries)) || (sizeof($snmp_queries) == 0)) {
		query_debug_timer_offset('data_query', 'Error parsing XML file into an array.');
		return false;
	}

	query_debug_timer_offset('data_query', 'XML file parsed ok.');

	if (!verify_index_order($snmp_queries)) {
		query_debug_timer_offset('data_query', 'Invalid field &lt;index_order&gt;' . $snmp_queries['index_order'] . '&lt;/index_order&gt;');
		query_debug_timer_offset('data_query', 'Must contain &lt;direction&gt;input&lt;/direction&gt; fields only');
		return false;
	}

	/* provide data for oid_num_indexes, if given */
	if (isset($snmp_queries['oid_num_indexes'])) {
		$snmp_num_indexes = cacti_snmp_get($host['hostname'], $host['snmp_community'], $snmp_queries['oid_num_indexes'],
			$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
			$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
			$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'],
			$host['ping_retries'], $host['max_oids'], SNMP_WEBUI);

		query_debug_timer_offset('data_query', "Executing SNMP get for num of indexes @ '" . $snmp_queries['oid_num_indexes'] . "' Index Count: " . $snmp_num_indexes);
	} else {
		query_debug_timer_offset('data_query', "&lt;oid_num_indexes&gt; missing in XML file, 'Index Count Changed' emulated by counting oid_index entries");
	}

	/* fetch specified index at specified OID */
	$snmp_indexes = cacti_snmp_walk($host['hostname'], $host['snmp_community'], $snmp_queries['oid_index'],
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
		$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], $host['ping_retries'], $host['max_oids'], SNMP_WEBUI);

	query_debug_timer_offset('data_query', "Executing SNMP walk for list of indexes @ '" . $snmp_queries['oid_index'] . "' Index Count: " . sizeof($snmp_indexes));

	/* no data found; get out */
	if (!sizeof($snmp_indexes)) {
		query_debug_timer_offset('data_query', 'No SNMP data returned');
		return false;
	} else {
		/* show list of indices found */
		foreach($snmp_indexes as $index) {
			query_debug_timer_offset('data_query', "Index found at OID: '" . $index['oid'] . "' value: '" . $index['value'] . "'");
		}
	}

	/* the last octet of the oid is the index by default */
	$index_parse_regexp = '.*\.([0-9]+)$';

	/* parse the index if required */
	if (isset($snmp_queries['oid_index_parse'])) {
		$index_parse_regexp = str_replace('OID/REGEXP:', '', $snmp_queries['oid_index_parse']);

		foreach($snmp_indexes as $index) {
			$index['value'] = preg_replace('/' . $index_parse_regexp . '/', "\\1", $index['oid']);
			query_debug_timer_offset('data_query', "index_parse at OID: '" . $index['oid'] . "' results: '" . $index['value'] . "'");
		}
	}

	/* set an array to host all updates */
	$output_array = array();

	while (list($field_name, $field_array) = each($snmp_queries['fields'])) {
		if ((!isset($field_array['oid'])) && ($field_array['source'] == 'index')) {
			foreach($snmp_indexes as $index) {
				query_debug_timer_offset('data_query', "Inserting index data for field '" . $field_name . "' [value='" . $index['value'] . "']");

				$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $index['value'], $index['value'], '');
			}
		}else if (($field_array['method'] == 'get') && ($field_array['direction'] == 'input')) {
			query_debug_timer_offset('data_query', "Located input field '$field_name' [get]");

			if ($field_array['source'] == 'value') {
				$oid_rewrite_pattern = null;
				$oid_rewrite_replacement = null;

				if (isset($field_array['oid_rewrite_pattern']) && isset($field_array['oid_rewrite_replacement'])) {
					$oid_rewrite_pattern = '/' . str_replace('OID/REGEXP:', '', $field_array['oid_rewrite_pattern']) . '/';
					$oid_rewrite_replacement = $field_array['oid_rewrite_replacement'];
					query_debug_timer_offset('data_query', "Found OID rewrite rule: 's/$oid_rewrite_pattern/$oid_rewrite_replacement/'");
				} 

				foreach($snmp_indexes as $index) {
					$oid = $field_array['oid'] .  '.' . $index['value'];
					$oid .= isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '';

					/* rewrite the oid if required */
					if (isset($oid_rewrite_pattern)) {
						$orig_oid = $oid;
						$oid = preg_replace($oid_rewrite_pattern, $oid_rewrite_replacement, $oid);
						query_debug_timer_offset('data_query', "oid_rewrite at OID: '" . $orig_oid . "' new OID: '" . $oid ."'");
					}

					$value = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid,
						$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
						$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
						$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], SNMP_WEBUI);

					query_debug_timer_offset('data_query', "Executing SNMP get for data @ '$oid' [value='$value']");

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $value, $index['value'],$oid);
				}
			} else if (substr($field_array['source'], 0, 13) == 'VALUE/REGEXP:') {
				foreach($snmp_indexes as $index) {
					$oid = $field_array['oid'] .  '.' . $index['value'];
					$oid .= isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '';

					$value = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid,
						$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
						$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
						$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], SNMP_WEBUI);

					$value = preg_replace('/' . str_replace('VALUE/REGEXP:', '', $field_array['source']) . '/', "\\1", $value);

					query_debug_timer_offset('data_query', "Executing SNMP get for data @ '$oid' [value='$value']");

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $value, $index['value'],$oid);
				}
			} 
		}else if (($field_array['method'] == 'walk') && ($field_array['direction'] == 'input')) {
			debug_log_insert_section_start('data_query', "Click to show data query output for field '$field_name'");

			query_debug_timer_offset('data_query', "Located input field '$field_name' [walk]");

			$snmp_data = array();
			$snmp_data = cacti_snmp_walk($host['hostname'], $host['snmp_community'], $field_array['oid'],
				$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
				$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
				$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], $host['ping_retries'], $host['max_oids'], SNMP_WEBUI);

			query_debug_timer_offset('data_query', "Executing SNMP walk for data @ '" . $field_array['oid'] . "'");

			if ($field_array['source'] == 'value') {
				foreach($snmp_data as $data) {
					$snmp_index = preg_replace('/' . (isset($field_array['oid_index_parse']) ? $field_array['oid_index_parse'] : $index_parse_regexp) . '/', "\\1", $data['oid']);

					$oid = $field_array['oid'] . ".$snmp_index";

					if ($field_name == 'ifOperStatus') {
						if ((substr_count(strtolower($data['value']), 'down')) ||
							($data['value'] == '2')) {
							$data['value'] = 'Down';
						}else if ((substr_count(strtolower($data['value']), 'up')) ||
							($data['value'] == '1')) {
							$data['value'] = 'Up';
						}else if ((substr_count(strtolower($data['value']), 'notpresent')) ||
							($data['value'] == '6')) {
							$data['value'] = 'notPresent';
						}else{
							$data['value'] = 'Testing';
						}
					}

					debug_log_insert('data_query', "Found item [$field_name='" . $data['value'] . "'] index: $snmp_index [from value]");

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $data['value'] , $snmp_index, $oid);
				}
			}elseif (substr($field_array['source'], 0, 11) == 'OID/REGEXP:') {
				foreach($snmp_data as $data) {
					$value = preg_replace('/' . str_replace('OID/REGEXP:', '', $field_array['source']) . '/', "\\1", $data['oid']);

					if (isset($snmp_queries['oid_index_parse'])) {
						$snmp_index = preg_replace('/' . $index_parse_regexp . '/', "\\1", $data['oid']);
					}else if ((isset($data['value'])) && ($data['value'] != '')) {
						$snmp_index = $data['value'];
					}

					/* correct bogus index value */
					/* found in some devices such as an EMC Cellera */
					if ($snmp_index == 0) {
						$snmp_index = 1;
					}

					$oid = $field_array['oid'] .  '.' . $value;

					/* rewrite octet strings */
					if (preg_match('/^\d{1,3}(\.\d{1,3}){2,}$/', $value)) {
						$octets = explode('.', $value);
						$size = array_shift($octets);

						if (count($octets) == $size) {
							$decoded = '';
							$isascii = true;

							foreach($octets as $octet) {
								if (($octet <= 31) || ($octet >= 127)) {
									$isascii = false;
								} else {
									$decoded .= chr($octet);
								}
							}
							if ($isascii) {
								query_debug_timer_offset('data_query', "Found OCTET STRING '$value' decoded value: '$decoded'");
								$value = $decoded;
							}
						}
					}

					debug_log_insert('data_query', "Found item [$field_name='$value'] index: $snmp_index [from regexp oid parse]");

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $value, $snmp_index, $oid);
				}
			}elseif (substr($field_array['source'], 0, 13) == 'VALUE/REGEXP:') {
				foreach($snmp_data as $data) {
					$value = preg_replace('/' . str_replace('VALUE/REGEXP:', '', $field_array['source']) . '/', "\\1", $data['value']);
					$snmp_index = preg_replace('/' . (isset($field_array['oid_index_parse']) ? $field_array['oid_index_parse'] : $index_parse_regexp) . '/', "\\1", $data['oid']);
					$oid = $field_array['oid'] . '.' . $snmp_index;

					debug_log_insert('data_query', "Found item [$field_name='$value'] index: $snmp_index [from regexp value parse]");

					$output_array[] = "('$host_id', '$snmp_query_id', '$field_name', '$value', '$snmp_index', '$oid', '1')";
				}
			}

			debug_log_insert_section_end('data_query');
		}
	}

	data_query_update_host_cache_from_buffer($host_id, $snmp_query_id, $output_array);

	return true;
}

function data_query_format_record($host_id, $snmp_query_id, $field_name, $value, $snmp_index, $oid) {
	return "($host_id, $snmp_query_id, " . db_qstr($field_name) . ', ' . db_qstr($value) . ', ' . db_qstr($snmp_index) . ', ' . db_qstr($oid) . ', 1)';
}

function data_query_update_host_cache_from_buffer($host_id, $snmp_query_id, &$output_array) {
	/* set all fields present value to 0, to mark the outliers when we are all done */
	db_execute("UPDATE host_snmp_cache SET present=0 WHERE host_id='$host_id' AND snmp_query_id='$snmp_query_id'");

	/* setup the database call */
	$sql_prefix   = "INSERT INTO host_snmp_cache (host_id, snmp_query_id, field_name, field_value, snmp_index, oid, present) VALUES";
	$sql_suffix   = " ON DUPLICATE KEY UPDATE field_value=VALUES(field_value), oid=VALUES(oid), present=VALUES(present)";

	/* use a reasonable insert buffer, the default is 1MByte */
	$max_packet   = 256000;

	/* setup somme defaults */
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	if (sizeof($output_array)) {
		foreach($output_array as $record) {
			if ($buf_count == 0) {
				$delim = ' ';
			} else {
				$delim = ', ';
			}

			$buffer .= $delim . $record;

			$buf_len += strlen($record);

			if (($overhead + $buf_len) > ($max_packet - 1024)) {
				db_execute($sql_prefix . $buffer . $sql_suffix);

				$buffer    = '';
				$buf_len   = 0;
				$buf_count = 0;
			} else {
				$buf_count++;
			}
		}
	}

	if ($buf_count > 0) {
		db_execute($sql_prefix . $buffer . $sql_suffix);
	}

	/* remove stale records FROM the host cache */
	db_execute("DELETE FROM host_snmp_cache WHERE host_id='$host_id' AND snmp_query_id='$snmp_query_id' AND present='0'");
}

/* data_query_index - returns an array containing the data query ID AND index value given
	a data query index type/value combination AND a host ID
   @arg $index_type - the name of the index to match
   @arg $index_value - the value of the index to match
   @arg $host_id - (int) the host ID to match
   @arg $data_query_id - (int) the data query ID to match
   @returns - (array) the data query ID AND index that matches the three arguments */
function data_query_index($index_type, $index_value, $host_id, $data_query_id) {
	return db_fetch_cell("SELECT
		host_snmp_cache.snmp_index
		FROM host_snmp_cache
		WHERE host_snmp_cache.field_name='$index_type'
		AND host_snmp_cache.field_value='" . addslashes($index_value) . "'
		AND host_snmp_cache.host_id='$host_id'
		AND host_snmp_cache.snmp_query_id='$data_query_id'");
}

/* data_query_field_list - returns an array containing data query information for a given data source
   @arg $data_template_data_id - the ID of the data source to retrieve information for
   @returns - (array) an array that looks like:
	Array
	(
	   [index_type] => ifIndex
	   [index_value] => 3
	   [output_type] => 13
	) */
function data_query_field_list($data_template_data_id) {
	if (!is_numeric($data_template_data_id)) {
		return 0;
	}

	$field = db_fetch_assoc_prepared("SELECT dif.type_code, did.value
		FROM data_input_fields AS dif
		INNER JOIN data_input_data AS did
		ON dif.id=did.data_input_field_id
		WHERE did.data_template_data_id = ?
		AND dif.type_code IN ('index_type','index_value','output_type')", array($data_template_data_id));

	$field = array_rekey($field, 'type_code', 'value');

	if ((!isset($field['index_type'])) || (!isset($field['index_value'])) || (!isset($field['output_type']))) {
		return 0;
	}else{
		return $field;
	}
}

/* encode_data_query_index - encodes a data query index value so that it can be included
	inside of a form
   @arg $index - the index name to encode
   @returns - the encoded data query index */
function encode_data_query_index($index) {
	return md5($index);
}

/* decode_data_query_index - decodes a data query index value so that it can be read from
	a form
   @arg $encoded_index - the index that was encoded with encode_data_query_index()
   @arg $data_query_id - the id of the data query that this index belongs to
   @arg $encoded_index - the id of the host that this index belongs to
   @returns - the decoded data query index */
function decode_data_query_index($encoded_index, $data_query_id, $host_id) {
	/* yes, i know MySQL has a MD5() function that would make this a bit quicker. however i would like to
	keep things abstracted for now so Cacti works with ADODB fully when i get around to porting my db calls */
	$indexes = db_fetch_assoc("SELECT snmp_index FROM host_snmp_cache WHERE host_id=$host_id AND snmp_query_id=$data_query_id  group by snmp_index");

	if (sizeof($indexes) > 0) {
	foreach ($indexes as $index) {
		if (encode_data_query_index($index['snmp_index']) == $encoded_index) {
			return $index['snmp_index'];
		}
	}
	}
}

/* update_data_query_cache - updates the local data query cache for each graph AND data
	source tied to this host/data query
   @arg $host_id - the id of the host to refresh
   @arg $data_query_id - the id of the data query to refresh */
function update_data_query_cache($host_id, $data_query_id) {
	$graphs = db_fetch_assoc_prepared('SELECT * FROM graph_local 
		WHERE host_id = ?  AND snmp_query_id = ?', array($host_id, $data_query_id));

	if (sizeof($graphs) > 0) {
		foreach ($graphs as $graph) {
			update_graph_data_query_cache($graph['id'], $host_id, $data_query_id, $graph['snmp_index']);
		}
	}
	query_debug_timer_offset('data_query', 'Update graph data query cache complete');

	$data_sources = db_fetch_assoc_prepared('SELECT * FROM data_local 
		WHERE host_id = ? AND snmp_query_id = ?', array($host_id, $data_query_id));

	if (sizeof($data_sources) > 0) {
		foreach ($data_sources as $data_source) {
			$changed = update_data_source_data_query_cache($data_source['id'], $host_id, $data_query_id, $data_source['snmp_index']);
			if ($changed) {
				update_poller_cache($data_source, true);
			}
		}
	}
	query_debug_timer_offset('data_query', 'Update data source data query cache complete');
}

/* update_graph_data_query_cache - updates the local data query cache for a particular
	graph
   @arg $local_graph_id - the id of the graph to update the data query cache for */
function update_graph_data_query_cache($local_graph_id, $host_id = '', $data_query_id = '', $data_query_index = '') {
	if (empty($host_id)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', array($local_graph_id));
	}

	$field = data_query_field_list(db_fetch_cell_prepared('SELECT
		data_template_data.id
		FROM (graph_templates_item,data_template_rrd,data_template_data)
		WHERE graph_templates_item.task_item_id=data_template_rrd.id
		AND data_template_rrd.local_data_id=data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		LIMIT 1', array($local_graph_id)));

	if (empty($field)) { return; }

	if (empty($data_query_id)) {
		$data_query_id = db_fetch_cell_prepared('SELECT snmp_query_id FROM snmp_query_graph WHERE id = ?', array($field['output_type']));
	}

	$index = data_query_index($field['index_type'], $field['index_value'], $host_id, $data_query_id);

	if ($data_query_id != 0 && $index != '' && $index != $data_query_index) {
		db_execute_prepared('UPDATE graph_local SET snmp_query_id = ?, snmp_index = ? WHERE id = ?', array($data_query_id, $index, $local_graph_id));

		/* update graph title cache */
		update_graph_title_cache($local_graph_id);
	}
}

/* update_data_source_data_query_cache - updates the local data query cache for a particular
	data source
   @arg $local_data_id - the id of the data source to update the data query cache for */
function update_data_source_data_query_cache($local_data_id, $host_id = '', $data_query_id = '', $data_query_index = '') {
	if (empty($host_id)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id FROM data_local WHERE id = ?', array($local_data_id));
	}

	$field = data_query_field_list(db_fetch_cell_prepared('SELECT
		data_template_data.id
		FROM data_template_data
		WHERE data_template_data.local_data_id = ?', array($local_data_id)));

	if (empty($field)) { return; }

	if (empty($data_query_id)) {
		$data_query_id = db_fetch_cell_prepared('SELECT snmp_query_id FROM snmp_query_graph WHERE id = ?', array($field['output_type']));
	}

	$index = data_query_index($field['index_type'], $field['index_value'], $host_id, $data_query_id);

	if ($data_query_id != 0 && $index != '' && $index != $data_query_index) {
		db_execute_prepared('UPDATE data_local SET snmp_query_id = ?, snmp_index = ? WHERE id = ?', array($data_query_id, $index, $local_data_id));

		/* update data source title cache */
		update_data_source_title_cache($local_data_id);

		return true;
	}

	return false;
}

/* get_formatted_data_query_indexes - obtains a list of indexes for a host/data query that
	is sorted by the chosen index field AND formatted using the data query index title
	format
   @arg $host_id - the id of the host which contains the data query
   @arg $data_query_id - the id of the data query to retrieve a list of indexes for
   @returns - an array formatted like the following:
	$arr[snmp_index] = 'formatted data query index string' */
function get_formatted_data_query_indexes($host_id, $data_query_id) {
	global $config;

	include_once($config['library_path'] . '/sort.php');

	if (empty($data_query_id)) {
		return array('' => 'Unknown Index');
	}

	/* FROM the xml; cached in 'host_snmp_query' */
	$sort_cache = db_fetch_row("SELECT sort_field,title_format FROM host_snmp_query WHERE host_id='$host_id' AND snmp_query_id='$data_query_id'");
	/* in case no unique index is available, fallback to first field in XML */
	if (strlen($sort_cache['sort_field']) == 0){
		$snmp_queries = get_data_query_array($data_query_id);
		if (isset($snmp_queries['index_order'])){
			$i = explode(':', $snmp_queries['index_order']);
			if (sizeof($i) > 0){
				$sort_cache['sort_field'] = array_shift($i);
			}
		}
	}

	/* get a list of data query indexes AND the field value that we are supposed
	to sort */
	$sort_field_data = array_rekey(db_fetch_assoc("SELECT graph_local.snmp_index, host_snmp_cache.field_value
		FROM (graph_local,host_snmp_cache)
		WHERE graph_local.host_id=host_snmp_cache.host_id
		AND graph_local.snmp_query_id=host_snmp_cache.snmp_query_id
		AND graph_local.snmp_index=host_snmp_cache.snmp_index
		AND graph_local.snmp_query_id=$data_query_id
		AND graph_local.host_id=$host_id
		AND host_snmp_cache.field_name='" . $sort_cache['sort_field'] . "'
		GROUP BY graph_local.snmp_index"), 'snmp_index', 'field_value');

	/* sort the data using the 'data query index' sort algorithm */
	uasort($sort_field_data, 'usort_data_query_index');

	$sorted_results = array();

	while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
		$sorted_results[$snmp_index] = substitute_snmp_query_data($sort_cache['title_format'], $host_id, $data_query_id, $snmp_index);
	}

	return $sorted_results;
}

/* get_formatted_data_query_index - obtains a single index for a host/data query/data query
	index that is formatted using the data query index title format
   @arg $host_id - the id of the host which contains the data query
   @arg $data_query_id - the id of the data query which contains the data query index
   @arg $data_query_index - the index to retrieve the formatted name for
   @returns - a string containing the formatted name for the given data query index */
function get_formatted_data_query_index($host_id, $data_query_id, $data_query_index) {
	/* FROM the xml; cached in 'host_snmp_query' */
	$sort_cache = db_fetch_row("SELECT sort_field,title_format FROM host_snmp_query WHERE host_id='$host_id' AND snmp_query_id='$data_query_id'");

	return substitute_snmp_query_data($sort_cache['title_format'], $host_id, $data_query_id, $data_query_index);
}

/* get_ordered_index_type_list - builds an ordered list of data query index types that are
	valid given a list of data query indexes that will be checked against the data query
	cache
   @arg $host_id - the id of the host which contains the data query
   @arg $data_query_id - the id of the data query to build the type list from
   @arg $data_query_index_array - an array containing each data query index to use when checking
	each data query type for validity. a valid data query type will contain no empty or duplicate
	values for each row in the cache that matches one of the $data_query_index_array
   @returns - an array of data query types either ordered or unordered depending on whether
	the xml file has a manual ordering preference specified */
function get_ordered_index_type_list($host_id, $data_query_id, $data_query_index_array = array()) {
	$raw_xml = get_data_query_array($data_query_id);

	/* invalid xml check */
	if ((!is_array($raw_xml)) || (sizeof($raw_xml) == 0)) {
		return array();
	}

	$xml_outputs = array();

	/* create an SQL string that contains each index in this snmp_index_id */
	$sql_or = array_to_sql_or($data_query_index_array, 'snmp_index');

	/* check for nonunique query parameter, set value */
	if (isset($raw_xml['index_type'])) {
		if ($raw_xml['index_type'] == 'nonunique') {
			$nonunique = 1;
		}else{
			$nonunique = 0;
		}
	} else {
		$nonunique = 0;
	}

	/* list each of the input fields for this snmp query */
	while (list($field_name, $field_array) = each($raw_xml['fields'])) {
		if ($field_array['direction'] == 'input') {
			/* create a list of all values for this index */
			if (sizeof($data_query_index_array) == 0) {
				$field_values = db_fetch_assoc("SELECT field_value FROM host_snmp_cache WHERE host_id=$host_id AND snmp_query_id=$data_query_id and field_name='$field_name'");
			}else{
				$field_values = db_fetch_assoc("SELECT field_value FROM host_snmp_cache WHERE host_id=$host_id AND snmp_query_id=$data_query_id and field_name='$field_name' and $sql_or");
			}

			/* aggregate the above list so there is no duplicates */
			$aggregate_field_values = array_rekey($field_values, 'field_value', 'field_value');

			/* fields that contain duplicate or empty values are not suitable to index off of */
			if (!((sizeof($aggregate_field_values) < sizeof($field_values)) || (in_array('', $aggregate_field_values) == true) || (sizeof($aggregate_field_values) == 0)) || ($nonunique)) {
				array_push($xml_outputs, $field_name);
			}
		}
	}

	$return_array = array();

	/* the xml file contains an ordered list of 'indexable' fields */
	if (isset($raw_xml['index_order'])) {
		$index_order_array = explode(':', $raw_xml['index_order']);

		foreach($index_order_array as $element) {
			if (in_array($element, $xml_outputs)) {
				$return_array[] = $element;
			}
		}
	/* the xml file does not contain a field list, ignore the order */
	}else{
		foreach($xml_outputs as $output) {
			$return_array[] = $output;
		}
	}

	return $return_array;
}

/* update_data_query_sort_cache - updates the sort cache for a particular host/data query
	combination. this works by fetching a list of valid data query index types AND choosing
	the first one in the list. the user can optionally override how the cache is updated
	in the data query xml file
   @arg $host_id - the id of the host which contains the data query
   @arg $data_query_id - the id of the data query update the sort cache for */
function update_data_query_sort_cache($host_id, $data_query_id) {
	$raw_xml = get_data_query_array($data_query_id);

	/* get a list of valid data query types */
	$valid_index_types = get_ordered_index_type_list($host_id, $data_query_id);

	/* something is probably wrong with the data query */
	if (sizeof($valid_index_types) == 0) {
		$sort_field = '';
	}else{
		/* grab the first field off the list */
		$sort_field = $valid_index_types[0];
	}

	/* substitute variables */
	if (isset($raw_xml['index_title_format'])) {
		$title_format = str_replace('|chosen_order_field|', "|query_$sort_field|", $raw_xml['index_title_format']);
	}else{
		$title_format = "|query_$sort_field|";
	}

	/* update the cache */
	/* TODO: if both $sort field AND $title_format are empty, this yields funny results */
	db_execute("UPDATE host_snmp_query SET sort_field = '$sort_field', title_format = '$title_format' WHERE host_id = '$host_id' AND snmp_query_id = '$data_query_id'");
}

/* update_data_query_sort_cache_by_host - updates the sort cache for all data queries associated
	with a particular host. see update_data_query_sort_cache() for details about updating the cache
   @arg $host_id - the id of the host to update the cache for */
function update_data_query_sort_cache_by_host($host_id) {
	$data_queries = db_fetch_assoc("SELECT snmp_query_id FROM host_snmp_query WHERE host_id = '$host_id'");

	if (sizeof($data_queries) > 0) {
		foreach ($data_queries as $data_query) {
			update_data_query_sort_cache($host_id, $data_query['snmp_query_id']);
		}
	}
}

/* get_best_data_query_index_type - returns the best available data query index type using the
	sort cache
   @arg $host_id - the id of the host which contains the data query
   @arg $data_query_id - the id of the data query to fetch the best data query index type for
   @returns - a string containing containing best data query index type. this will be one of the
	valid input field names as specified in the data query xml file */
function get_best_data_query_index_type($host_id, $data_query_id) {
	return db_fetch_cell("SELECT sort_field FROM host_snmp_query WHERE host_id = '$host_id' AND snmp_query_id = '$data_query_id'");
}

/* get_script_query_path - builds the complete script query executable path
   @arg $args - the variable that contains any arguments to be appended to the argument
	list (variables will be substituted in this function)
   @arg $script_path - the path on the disk to the script file
   @arg $host_id - the id of the host that this script query belongs to
   @returns - a full path to the script query script containing all arguments */
function get_script_query_path($args, $script_path, $host_id) {
	global $config;

	include_once($config['library_path'] . '/variables.php');

	/* get any extra arguments that need to be passed to the script */
	if (!empty($args)) {
		$extra_arguments = substitute_host_data($args, '|', '|', $host_id);
	}else{
		$extra_arguments = '';
	}

	/* get a complete path for out target script */
	return substitute_script_query_path($script_path) . " $extra_arguments";
}


/**
 * verify a given index_order
 * @param array $raw_xml 	- parsed XML array
 * @return bool 			- index_order field valid
 */
function verify_index_order($raw_xml) {

	/* invalid xml check */
	if ((!is_array($raw_xml)) || (sizeof($raw_xml) == 0)) {
		query_debug_timer_offset('data_query', 'Error parsing XML file into an array.');
		return false;
	}

	$xml_inputs = array();

	/* list each of the input fields for this snmp query */
	while (list($field_name, $field_array) = each($raw_xml['fields'])) {
		if ($field_array['direction'] == 'input') {
			/* create a list of all values for this index */
			array_push($xml_inputs, $field_name);
		}
	}

	$all_index_order_fields_found = true;
	/* the xml file contains an ordered list of 'indexable' fields */
	if (isset($raw_xml['index_order'])) {
		$index_order_array = explode(':', $raw_xml['index_order']);

		foreach($index_order_array as $element) {
			$all_index_order_fields_found = $all_index_order_fields_found && (in_array($element, $xml_inputs));
		}
	} else {
		/* the xml file does not contain an index order */
	}

	return $all_index_order_fields_found;
}

