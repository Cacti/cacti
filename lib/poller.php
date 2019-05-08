<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

/* exec_poll - executes a command and returns its output
   @arg $command - the command to execute
   @returns - the output of $command after execution */
function exec_poll($command) {
	global $config;

	if (function_exists('popen')) {
		if ($config['cacti_server_os'] == 'unix') {
			$fp = popen($command, 'r');
		} else {
			$fp = popen($command, 'rb');
		}

		/* return if the popen command was not successfull */
		if (!is_resource($fp)) {
			cacti_log('WARNING; Problem with POPEN command.', false, 'POLLER');
			return 'U';
		}

		$output = fgets($fp, 8192);

		pclose($fp);
	} else {
		$output = `$command`;
	}

	return $output;
}

/* exec_poll_php - sends a command to the php script server and returns the
     output
   @arg $command - the command to send to the php script server
   @arg $using_proc_function - whether or not this version of php is making use
     of the proc_open() and proc_close() functions (php 4.3+)
   @arg $pipes - the array of r/w pipes returned from proc_open()
   @arg $proc_fd - the file descriptor returned from proc_open()
   @returns - the output of $command after execution against the php script
     server */
function exec_poll_php($command, $using_proc_function, $pipes, $proc_fd) {
	global $config;

	$output = '';

	/* execute using php process */
	if ($using_proc_function == 1) {
		if (is_resource($proc_fd)) {
			/* $pipes now looks like this:
			 * 0 => writeable handle connected to child stdin
			 * 1 => readable handle connected to child stdout
			 * 2 => any error output will be sent to child stderr */

			/* send command to the php server */
			fwrite($pipes[0], $command . "\r\n");
			fflush($pipes[0]);

			$output = fgets($pipes[1], 8192);

			if (substr_count($output, 'ERROR') > 0) {
				$output = 'U';
			}
		}
	/* execute the old fashion way */
	} else {
		/* formulate command */
		$command = read_config_option('path_php_binary') . ' ' . $command;

		if (function_exists('popen')) {
			if ($config['cacti_server_os'] == 'unix')  {
				$fp = popen($command, 'r');
			} else {
				$fp = popen($command, 'rb');
			}

			/* return if the popen command was not successfull */
			if (!is_resource($fp)) {
				cacti_log('WARNING; Problem with POPEN command.', false, 'POLLER');
				return 'U';
			}

			$output = fgets($fp, 8192);

			pclose($fp);
		} else {
			$output = `$command`;
		}
	}

	return $output;
}

/* exec_background - executes a program in the background so that php can continue
     to execute code in the foreground
   @arg $filename - the full pathname to the script to execute
   @arg $args - any additional arguments that must be passed onto the executable
   @arg $redirect_args - any additional arguments for file re-direction.  Otherwise output goes to /dev/null */
function exec_background($filename, $args = '', $redirect_args = '') {
	global $config, $debug;

	cacti_log("DEBUG: About to Spawn a Remote Process [CMD: $filename, ARGS: $args]", true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_DEBUG));

	if (file_exists($filename)) {
		if ($config['cacti_server_os'] == 'win32') {
			if (!file_escaped($filename)) {
				$filename = cacti_escapeshellcmd($filename);
			}

			if ($redirect_args == '') {
				pclose(popen('start "Cactiplus" /I ' . $filename . ' ' . $args, 'r'));
			} else {
				pclose(popen('start "Cactiplus" /I ' . $filename . ' ' . $args . ' ' . $redirect_args, 'r'));
			}
		} elseif ($redirect_args == '') {
			exec($filename . ' ' . $args . ' > /dev/null 2>&1 &');
		} else {
			exec($filename . ' ' . $args . ' ' . $redirect_args . ' &');
		}
	} elseif (file_exists_2gb($filename)) {
		if ($redirect_args == '') {
			exec($filename . ' ' . $args . ' > /dev/null 2>&1 &');
		} else {
			exec($filename . ' ' . $args . ' ' . $redirect_args . ' &');
		}
	}
}

function file_escaped($file) {
	if (substr($file, 0, 1) == '"' && substr($file, -1, 1) == '"') {
		return true;
	}

	return false;
}

/* file_exists_2gb - fail safe version of the file exists function to correct
     for errors in certain versions of php.
   @arg $filename - the name of the file to be tested. */
function file_exists_2gb($filename) {
	global $config;

	$rval = 0;
	if ($config['cacti_server_os'] != 'win32') {
		system("test -f $filename", $rval);
		return ($rval == 0);
	} else {
		return 0;
	}
}

/* update_reindex_cache - builds a cache that is used by the poller to determine if the
     indexes for a particular data query/host have changed
   @arg $host_id - the id of the host to which the data query belongs
   @arg $data_query_id - the id of the data query to rebuild the reindex cache for */
function update_reindex_cache($host_id, $data_query_id) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/snmp.php');

	/* will be used to keep track of sql statements to execute later on */
	$recache_stack = array();

	$host = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host
		WHERE id = ?',
		array($host_id));

	$data_query = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host_snmp_query
		WHERE host_id = ?
		AND snmp_query_id = ?',
		array($host_id, $data_query_id));

	$data_query_type = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' data_input.type_id
		FROM data_input
		INNER JOIN snmp_query
		ON data_input.id = snmp_query.data_input_id
		WHERE snmp_query.id = ?',
		array($data_query_id));

	$data_query_xml  = get_data_query_array($data_query_id);

	if (cacti_sizeof($data_query)) {
		switch ($data_query['reindex_method']) {
		case DATA_QUERY_AUTOINDEX_NONE:
			break;
		case DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME:
			/* the uptime backwards method requires snmp, so make sure snmp is actually enabled
			 * on this device first */
			if ($host['snmp_version'] > 0) {
				if (isset($data_query_xml['oid_uptime'])) {
					$oid_uptime = $data_query_xml['oid_uptime'];
				} elseif (isset($data_query_xml['uptime_oid'])) {
					$oid_uptime = $data_query_xml['uptime_oid'];
				} else {
					$oid_uptime = '.1.3.6.1.2.1.1.3.0';
				}

				$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
					$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
					$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

				if ($session !== false) {
					$assert_value = cacti_snmp_session_get($session, $oid_uptime);
				}

				$session->close();

				$recache_stack[] = "('$host_id', '$data_query_id'," .  POLLER_ACTION_SNMP . ", '<', '$assert_value', '$oid_uptime', 1)";
			}

			break;
		case DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE:
			/* this method requires that some command/oid can be used to determine the
			 * current number of indexes in the data query
			 * pay ATTENTION to quoting!
			 * the script parameters are usually enclosed in single tics: '
			 * so we have to enclose the whole list of parameters in double tics: "
			 * */

			/* the assert_value counts the number of distinct indexes currently available in host_snmp_cache
			 * we do NOT make use of <oid_num_indexes> or the like!
			 * this works, even if no <oid_num_indexes> was given
			 */
			$assert_value = cacti_sizeof(db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . ' snmp_index
				FROM host_snmp_cache
				WHERE host_id = ?
				AND snmp_query_id = ?
				AND snmp_index != ""',
				array($host_id, $data_query_id)));

			/* now, we have to build the (list of) commands that are later used on a recache event
			 * the result of those commands will be compared to the assert_value we have just computed
			 * on a comparison failure, a reindex event will be generated
			 */
			switch ($data_query_type) {
				case DATA_INPUT_TYPE_SNMP_QUERY:
					if (isset($data_query_xml['oid_num_indexes'])) { /* we have a specific OID for counting indexes */
						$recache_stack[] = "($host_id, $data_query_id," .  POLLER_ACTION_SNMP . ", '=', " . db_qstr($assert_value) . ", " . db_qstr($data_query_xml['oid_num_indexes']) . ", 1)";
					} else { /* count all indexes found */
						$recache_stack[] = "($host_id, $data_query_id, " .  POLLER_ACTION_SNMP_COUNT . ", '=', " . db_qstr($assert_value) . ", " . db_qstr($data_query_xml['oid_index']) . ", 1)";
					}
					break;
				case DATA_INPUT_TYPE_SCRIPT_QUERY:
					if (isset($data_query_xml['arg_num_indexes'])) { /* we have a specific request for counting indexes */
						/* escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string? */
						$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT . ", '=', " . db_qstr($assert_value) . ", " . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_num_indexes'], $data_query_xml['script_path'], $host_id)) . ", 1)";
					} else { /* count all indexes found */
						/* escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string? */
						$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT_COUNT . ", '=', " . db_qstr($assert_value) . ", " . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_index'], $data_query_xml['script_path'], $host_id)) . ", 1)";
					}
					break;
				case DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER:
					if (isset($data_query_xml['arg_num_indexes'])) { /* we have a specific request for counting indexes */
						/* escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string? */
						$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT_PHP . ", '=', " . db_qstr($assert_value) . ", " . db_qstr(get_script_query_path($data_query_xml['script_function'] . ' ' . (isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_num_indexes'], $data_query_xml['script_path'], $host_id)) . ", 1)";
					} else { /* count all indexes found */
						# TODO: push the correct assert value
						/* escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string? */
						#$recache_stack[] = "($host_id, $data_query_id," . POLLER_ACTION_SCRIPT_PHP_COUNT . ", '=', " . db_qstr($assert_value) . ", " . db_qstr(get_script_query_path($data_query_xml['script_function'] . ' ' . (isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_index'], $data_query_xml['script_path'], $host_id)) . ", 1)";
						# omit the assert value until we are able to run an 'index' command through script server
					}
					break;
			}

			break;
		case DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION:
			$primary_indexes = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' snmp_index, oid, field_value
				FROM host_snmp_cache
				WHERE host_id = ?
				AND snmp_query_id = ?
				AND field_name = ?',
				array($host_id, $data_query_id, $data_query['sort_field']));

			if (cacti_sizeof($primary_indexes) > 0) {
				foreach ($primary_indexes as $index) {
					$assert_value = $index['field_value'];

					if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
						$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SNMP . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(($data_query_xml['fields'][$data_query['sort_field']]['source'] == 'index') ? $data_query_xml['oid_index']:$data_query_xml['fields'][$data_query['sort_field']]['oid'] . '.' . $index['snmp_index']) . ", 1)";
					} elseif ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
						$recache_stack[] = '(' . $host_id . ', ' . $data_query_id . ', ' . POLLER_ACTION_SCRIPT . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_get'] . ' ' . $data_query_xml['fields'][$data_query['sort_field']]['query_name'] . ' ' . $index['snmp_index'], $data_query_xml['script_path'], $host_id)) . ", 1)";
					}
				}
			}

			break;
		}
	}

	if (cacti_sizeof($recache_stack)) {
		poller_update_poller_reindex_from_buffer($host_id, $data_query_id, $recache_stack);
	}
}

function poller_update_poller_reindex_from_buffer($host_id, $data_query_id, &$recache_stack) {
	/* set all fields present value to 0, to mark the outliers when we are all done */
	db_execute_prepared('UPDATE poller_reindex
		SET present = 0
		WHERE host_id = ?
		AND data_query_id = ?',
		array($host_id, $data_query_id));

	/* setup the database call */
	$sql_prefix   = 'INSERT INTO poller_reindex (host_id, data_query_id, action, op, assert_value, arg1, present) VALUES';
	$sql_suffix   = ' ON DUPLICATE KEY UPDATE action=VALUES(action), op=VALUES(op), assert_value=VALUES(assert_value), present=VALUES(present)';

	/* use a reasonable insert buffer, the default is 1MByte */
	$max_packet   = 256000;

	/* setup somme defaults */
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	foreach($recache_stack AS $record) {
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

	if ($buf_count > 0) {
		db_execute($sql_prefix . $buffer . $sql_suffix);
	}

	/* remove stale records FROM the poller reindex */
	db_execute_prepared('DELETE FROM poller_reindex
		WHERE host_id = ?
		AND data_query_id = ?
		AND present = 0', array($host_id, $data_query_id));

	poller_push_reindex_only_data_to_main($host_id, $data_query_id);
}

/* process_poller_output - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDtool for processing
  @arg $rrdtool_pipe - the array of pipes containing the file descriptor for rrdtool
  @arg $remainder - don't use LIMIT if true */
function process_poller_output(&$rrdtool_pipe, $remainder = false) {
	global $config, $debug;

	static $have_deleted_rows = true;
	static $rrd_field_names = array();

	include_once($config['library_path'] . '/rrd.php');

	/* let's count the number of rrd files we processed */
	$rrds_processed = 0;
	$max_rows = 40000;

	if ($remainder) {
		/* check if too many rows pending */
		$rows = db_fetch_cell('SELECT COUNT(*) FROM poller_output');
		if ($rows > $max_rows && $have_deleted_rows === true) {
			$limit = ' LIMIT ' . $max_rows;
		} else {
			$limit = '';
		}
	} else {
		$limit = 'LIMIT ' . $max_rows;
	}

	$have_deleted_rows = false;

	/* create/update the rrd files */
	$results = db_fetch_assoc("SELECT po.output, po.time,
		UNIX_TIMESTAMP(po.time) as unix_time, po.local_data_id, dl.data_template_id,
		pi.rrd_path, pi.rrd_name, pi.rrd_num
		FROM poller_output AS po
		INNER JOIN poller_item AS pi
		ON po.local_data_id=pi.local_data_id
		AND po.rrd_name=pi.rrd_name
		INNER JOIN data_local AS dl
		ON dl.id=po.local_data_id
		ORDER BY po.local_data_id
		$limit");

	if (!cacti_sizeof($rrd_field_names)) {
		$rrd_field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
				CONCAT(data_template_id, "_", data_name) AS keyname, data_source_names AS data_source_name
				FROM poller_data_template_field_mappings'),
			'keyname', array('data_source_name'));
	}

	if (cacti_sizeof($results)) {
		/* create an array keyed off of each .rrd file */
		foreach ($results as $item) {
			/* trim the default characters, but add single and double quotes */
			$value     = $item['output'];
			$unix_time = $item['unix_time'];
			$rrd_path  = $item['rrd_path'];
			$rrd_name  = $item['rrd_name'];

			$rrd_update_array[$rrd_path]['local_data_id'] = $item['local_data_id'];

			/* single one value output */
			if ((is_numeric($value)) || ($value == 'U')) {
				$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = $value;
			/* special case of one value output: hexadecimal to decimal conversion */
			} elseif (is_hexadecimal($value)) {
				/* attempt to accomodate 32bit and 64bit systems */
				$value = str_replace(' ', '', $value);
				if (strlen($value) <= 8 || ((2147483647+1) == intval(2147483647+1))) {
					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = hexdec($value);
				} elseif (function_exists('bcpow')) {
					$dec = 0;
					$vallen = strlen($value);
					for ($i = 1; $i <= $vallen; $i++) {
						$dec = bcadd($dec, bcmul(strval(hexdec($value[$i - 1])), bcpow('16', strval($vallen - $i))));
					}
					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = $dec;
				} else {
					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = 'U';
				}
			/* multiple value output */
			} elseif (strpos($value, ':') !== false) {
				$values = preg_split('/\s+/', $value);

				foreach($values as $value) {
					$matches = explode(':', $value);

					if (cacti_sizeof($matches) == 2) {
						$fields = array();

						if (isset($rrd_field_names[$item['data_template_id'] . '_' . $matches[0]])) {
							$field_map = $rrd_field_names[$item['data_template_id'] . '_' . $matches[0]]['data_source_name'];

							if (strpos($field_map, ',') !== false) {
								$fields = explode(',', $field_map);
							} else {
								$fields[] = $field_map;
							}

							foreach($fields as $field) {
								cacti_log("Parsed MULTI output field '" . $matches[0] . ':' . $matches[1] . "' [map " . $matches[0] . '->' . $field . ']' , true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_MEDIUM));
								$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = $matches[1];
							}
						} else {
							// Handle data source without a data template
							$nt_rrd_field_names = array_rekey(
								db_fetch_assoc_prepared('SELECT dtr.data_source_name, dif.data_name
									FROM data_template_rrd AS dtr
									INNER JOIN data_input_fields AS dif
									ON dtr.data_input_field_id=dif.id
									WHERE dtr.local_data_id = ?', array($item['local_data_id'])),
								'data_name', 'data_source_name'
							);

							if (cacti_sizeof($nt_rrd_field_names)) {
								if (isset($nt_rrd_field_names[$matches[0]])) {
									cacti_log("Parsed MULTI output field '" . $matches[0] . ':' . $matches[1] . "' [map " . $matches[0] . '->' . $nt_rrd_field_names[$matches[0]] . ']' , true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_MEDIUM));

									$rrd_update_array[$item['rrd_path']]['times'][$unix_time][$nt_rrd_field_names[$matches[0]]] = $matches[1];
								}
							}
						}
					}
				}
			}

			/* fallback values */
			if ((!isset($rrd_update_array[$rrd_path]['times'][$unix_time])) && ($rrd_name != '')) {
				$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = 'U';
			} elseif ((!isset($rrd_update_array[$rrd_path]['times'][$unix_time])) && ($rrd_name == '')) {
				unset($rrd_update_array[$rrd_path]);
			}
		}

		/* make sure each .rrd file has complete data */
		$k = 0;
		$data_ids = array();
		foreach ($results as $item) {
			$unix_time = $item['unix_time'];
			$rrd_path  = $item['rrd_path'];
			$rrd_name  = $item['rrd_name'];

			if (isset($rrd_update_array[$rrd_path]['times'][$unix_time])) {
				if ($item['rrd_num'] <= cacti_sizeof($rrd_update_array[$rrd_path]['times'][$unix_time])) {
					$data_ids[] = $item['local_data_id'];
					$k++;
					if ($k % 10000 == 0) {
						db_execute('DELETE FROM poller_output WHERE local_data_id IN (' . implode(',', $data_ids) . ')');
						$have_deleted_rows = true;
						$data_ids = array();
						$k = 0;
					}
				} else {
					unset($rrd_update_array[$rrd_path]['times'][$unix_time]);
				}
			}
		}

		if ($k > 0) {
			db_execute('DELETE FROM poller_output WHERE local_data_id IN (' . implode(',', $data_ids) . ')');
			$have_deleted_rows = true;
		}

		/* process dsstats information */
		dsstats_poller_output($rrd_update_array);
		dsdebug_poller_output($rrd_update_array);

		api_plugin_hook_function('poller_output', $rrd_update_array);

		if (boost_poller_on_demand($results)) {
			$rrds_processed = rrdtool_function_update($rrd_update_array, $rrdtool_pipe);
		}

		$results = NULL;
		$rrd_update_array = NULL;

		/* to much records in poller_output, process in chunks */
		if ($remainder && $limit != '') {
			$rrds_processed += process_poller_output($rrdtool_pipe, $remainder);
		}
	}

	return $rrds_processed;
}

/** update_resource_cache - place the cacti website in the poller_resource_cache
 *
 *  for remote pollers to consume
 * @param int $poller_id    - The id of the poller.  1 is the main system
 * @return null             - No data is returned
 */
function update_resource_cache($poller_id = 1) {
	global $config, $remote_db_cnn_id;

	if ($config['cacti_server_os'] == 'win32') return;

	if ($poller_id == 1) {
		$conn = false;
	} else {
		$conn = $remote_db_cnn_id;
	}

	$mpath = $config['base_path'];
	$spath = $config['scripts_path'];
	$rpath = $config['resource_path'];

	$excluded_extensions = array('tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo');
	$excluded_dirs       = array('.git', 'log');

	$paths = array(
		'base'     => array('recursive' => false, 'path' => $mpath),
		'scripts'  => array('recursive' => true,  'path' => $spath),
		'resource' => array('recursive' => true,  'path' => $rpath),
		'lib'      => array('recursive' => true,  'path' => $mpath . '/lib'),
		'include'  => array('recursive' => true,  'path' => $mpath . '/include'),
		'install'  => array('recursive' => true,  'path' => $mpath . '/install'),
		'formats'  => array('recursive' => true,  'path' => $mpath . '/formats'),
		'locales'  => array('recursive' => true,  'path' => $mpath . '/locales'),
		'images'   => array('recursive' => true,  'path' => $mpath . '/images'),
		'mibs'     => array('recursive' => true,  'path' => $mpath . '/mibs'),
		'cli'      => array('recursive' => true,  'path' => $mpath . '/cli')
	);

	$pollers = db_fetch_cell('SELECT COUNT(*) FROM poller WHERE disabled=""', '', true, $conn);

	if ($poller_id == 1 && $pollers > 1) {
		foreach($paths as $type => $path) {
			if (is_readable($path['path'])) {
				$pathinfo = pathinfo($path['path']);
				if (isset($pathinfo['extension'])) {
					$extension = strtolower($pathinfo['extension']);
				} else {
					$extension = '';
				}

				/* exclude spurious extensions directories */
				$exclude = false;
				if (array_search($extension, $excluded_extensions, true) !== false) {
					$exclude = true;
				}

				if (array_search(basename($path['path']), $excluded_dirs, true) !== false) {
					$exclude = true;
				}

				if (!$exclude) {
					cache_in_path($path['path'], $type, $path['recursive']);
				}
			} else {
				cacti_log("ERROR: Unable to read the " . $type . " path '" . $path['path'] . "'", false, 'POLLER');
			}
		}

		/* handle plugin paths */
		$files_and_dirs = array_diff(scandir($mpath . '/plugins'), array('..', '.'));

		if (cacti_sizeof($files_and_dirs)) {
			foreach($files_and_dirs as $path) {
				if (is_dir($mpath . '/plugins/' . $path)) {
					if (file_exists($mpath . '/plugins/' . $path . '/INFO')) {
						$info = parse_ini_file($mpath . '/plugins/' . $path . '/INFO', true);
						$dir_exclusions  = array('..', '.', '.git');
						$file_exclusions = $excluded_extensions;

						if (isset($info['info']['nosync'])) {
							$exclude_paths = explode(',', $info['info']['nosync']);
							if (cacti_sizeof($exclude_paths)) {
								foreach($exclude_paths as $epath) {
									if (strpos($epath, '*.') !== false) {
										$file_exclusions[] = trim(str_replace('*.', '', $epath));
									} else {
										$dir_exclusions[]  = trim($epath);
									}
								}
							}
						}

						$fod = array_diff(scandir($mpath . '/plugins/' . $path), $dir_exclusions);
						if (cacti_sizeof($fod)) {
							foreach($fod as $file_or_dir) {
								$fpath = $mpath . '/plugins/' . $path . '/' . $file_or_dir;
								if (is_dir($fpath)) {
									cache_in_path($fpath, $path . '_' . basename($file_or_dir), true);
								} else {
									$pathinfo = pathinfo($fpath);

									if (isset($pathinfo['extension'])) {
										$extension = strtolower($pathinfo['extension']);
									} else {
										$extension = '';
									}

									/* exclude spurious extensions */
									$exclude = false;
									if (array_search($extension, $file_exclusions, true) !== false) {
										$exclude = true;
									}

									if (!$exclude) {
										cache_in_path($fpath, 'plugins', false);
									}
								}
							}
						}
					} else {
						cacti_log("WARNING: INFO file does not exist for plugin directory '" . $mpath . '/plugins/' . $path . "'", false, 'POLLER');
					}
				} else {
					cache_in_path($mpath . '/plugins/' . $path, 'plugins', false);
				}
			}
		}

		/* purge old entries */
		$cache = db_fetch_assoc('SELECT path FROM poller_resource_cache');
		if (cacti_sizeof($cache)) {
			foreach($cache as $item) {
				if (!file_exists($item['path'])) {
					db_execute_prepared('DELETE FROM poller_resource_cache
						WHERE `path` = ?',
						array($item['path']));
				}
			}
		}
	} elseif ($poller_id > 1) {
		$paths['plugins'] = array('recursive' => true, 'path' => $mpath . '/plugins');
		$plugin_paths = db_fetch_assoc('SELECT resource_type, `path`
			FROM poller_resource_cache
			WHERE `path` LIKE "plugins/%"', true, $conn);

		if (cacti_sizeof($plugin_paths)) {
			foreach ($plugin_paths as $path) {
				$paths[$path['resource_type']] = array('recursive' => false, 'path' => dirname($mpath . '/' . $path['path']));
			}
		}

		foreach($paths as $type => $path) {
			if (!file_exists($path['path'])) {
				cacti_log('INFO: Attempting to create directory \'' . $path['path'] . '\'', false, 'POLLER');
				@mkdir($path['path'], 0755, true);
			}

			if (is_writable($path['path'])) {
				resource_cache_out($type, $path);
			} else {
				cacti_log("FATAL: Unable to write to the " . $type . " path '" . $path['path'] . "'", false, 'POLLER');
			}
		}
	}
}

/** cache_in_path - check to see if the directory in question has changed.
 *  If so, send its data into the resource cache table
 *
 * @param string $path      - The path to look for changes
 * @param string $type      - The patch types being cached
 * @param bool   $recursive - Should the path be scanned recursively
 * @return null             - No data is returned
 */
function cache_in_path($path, $type, $recursive = true) {
	global $config;

	if (is_dir($path)) {
		$curr_md5      = md5sum_path($path, $recursive);
		$settings_path = "md5dirsum_$type";
		$last_md5      = read_config_option($settings_path);

		if (empty($last_md5) || $last_md5 != $curr_md5) {
			cacti_log('Type:' . $type . ', Path:' . $path . ', Last MD5:' . $last_md5 . ', Curr MD5:' . $curr_md5, false, 'POLLER', POLLER_VERBOSITY_MEDIUM);
			cacti_log("NOTE: Detecting Resource Change.  Updating Resource Cache for '$path'", false, 'POLLER');
			update_db_from_path($path, $type, $recursive);
		}

		set_config_option($settings_path, $curr_md5);
	} else {
		$spath = ltrim(trim(str_replace($config['base_path'], '', $path), '/ \\'), '/ \\');
		$excluded_extensions = array('tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo');
		$excluded_dirs_files = array('.git', '.travis.yml', 'config.php', '.gitattributes');
		$pathinfo = pathinfo($path);

		if (isset($pathinfo['extension'])) {
			$extension = strtolower($pathinfo['extension']);
		} else {
			$extension = '';
		}

		/* exclude spurious extensions directories */
		$exclude = false;
		if (array_search($extension, $excluded_extensions, true) !== false) {
			$exclude = true;
		}

		if (array_search(basename($path), $excluded_dirs_files, true) !== false) {
			$exclude = true;
		}

		/* exclude spurious extensions */
		if (!$exclude && basename($path) != 'config.php') {
			$curr_md5 = md5_file($path);
			$last_md5 = db_fetch_cell_prepared('SELECT md5sum FROM poller_resource_cache WHERE path = ?', array($spath));

			if (substr($spath, 0, 8) == 'plugins/') {
				$ppath = $config['base_path'] . $spath;
			} else {
				$ppath = $spath;
			}

			if (empty($last_md5) || $last_md5 != $curr_md5) {
				cacti_log("NOTE: Detecting Resource Change.  Updating Resource Cache for '$ppath'", false, 'POLLER');
				update_db_from_path($path, $type, $recursive);
			}
		}
	}
}

/** update_db_from_path - store the actual file in the databases resource cache.
 *  Skip the include/config.php if it exists
 *
 * @param string $path      - The path to look for changes
 * @param string $type      - The patch types being cached
 * @param bool   $recursive - Should the path be scanned recursively
 * @return null             - No data is returned
 */
function update_db_from_path($path, $type, $recursive = true) {
	global $config;

	$excluded_extensions = array('tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo');

	if (is_dir($path)) {
		$pobject = dir($path);

		while (($entry = $pobject->read()) !== false) {
			if (!should_ignore_from_replication($entry)) {
				$spath = ltrim(trim(str_replace($config['base_path'], '', $path), '/ \\') . '/' . $entry, '/ \\');
				if (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
					if ($recursive) {
						update_db_from_path($path . DIRECTORY_SEPARATOR . $entry, $type, $recursive);
					}
				} elseif (basename($path) == 'config.php') {
					continue;
				} elseif (basename($path) == '.travis.yml') {
					continue;
				} else {
					$pathinfo = pathinfo($entry);
					if (isset($pathinfo['extension'])) {
						$extension = strtolower($pathinfo['extension']);
					} else {
						$extension = '';
					}

					/* exclude spurious extensions */
					if (array_search($extension, $excluded_extensions, true) !== false) {
						continue;
					}

					$save         = array();
					$save['path'] = $spath;
					$save['id']   = db_fetch_cell_prepared('SELECT id
						FROM poller_resource_cache
						WHERE `path` = ?',
						array($save['path']));

					$entry_path = $path. DIRECTORY_SEPARATOR . $entry;
					$save['resource_type'] = $type;
					$save['md5sum']        = md5_file($entry_path);
					$save['update_time']   = date('Y-m-d H:i:s');
					$save['attributes']    = fileperms($entry_path);
					$save['contents']      = base64_encode(file_get_contents($entry_path));

					sql_save($save, 'poller_resource_cache');
				}
			}
		}

		$pobject->close();
	} else {
		if (!should_ignore_from_replication($path)) {
			$pathinfo = pathinfo($path);
			if (isset($pathinfo['extension'])) {
				$extension = strtolower($pathinfo['extension']);
			} else {
				$extension = '';
			}

			/* exclude spurious extensions */
			if (array_search($extension, $excluded_extensions, true) === false) {
				$spath = ltrim(trim(str_replace($config['base_path'], '', $path), '/ \\'), '/ \\');

				$save         = array();
				$save['path'] = $spath;

				$save['id']   = db_fetch_cell_prepared('SELECT id
					FROM poller_resource_cache
					WHERE `path` = ?',
					array($save['path']));

				$save['resource_type'] = $type;
				$save['md5sum']        = md5_file($path);
				$save['update_time']   = date('Y-m-d H:i:s');
				$save['contents']      = base64_encode(file_get_contents($path));

				sql_save($save, 'poller_resource_cache');
			}
		}
	}
}

/** resource_cache_out - push the cache from the cacti database to the
 *  remote database.  Check PHP files for errors
 *
 * before placing them on the remote pollers file system.
 * @param string $type      - The path type being cached
 * @param string $path      - The path to store the contents
 * @return null             - No data is returned
 */
function resource_cache_out($type, $path) {
	global $config, $remote_db_cnn_id;

	$settings_path = "md5dirsum_$type";
	$php_path      = read_config_option('path_php_binary');
	$last_md5      = read_config_option($settings_path);
	$curr_md5      = md5sum_path($path['path'], $path['recursive']);

	if (empty($last_md5) || $last_md5 != $curr_md5) {
		$entries = db_fetch_assoc_prepared('SELECT id, `path`, md5sum, attributes
			FROM poller_resource_cache
			WHERE resource_type = ?',
			array($type), true, $remote_db_cnn_id);

		if (cacti_sizeof($entries)) {
			foreach($entries as $e) {
				if (should_ignore_from_replication($e['path'])) {
					continue;
				}

				$mypath = $config['base_path'] . DIRECTORY_SEPARATOR . $e['path'];

				if (file_exists($mypath)) {
					$md5sum = md5_file($mypath);
				} else {
					$md5sum = '';
				}

				if (!is_dir(dirname($mypath))) {
					$relative_dir = str_replace($config['base_path'], '', dirname($mypath));
					mkdir('./' . $relative_dir, 0755, true);
				}

				if (is_dir(dirname($mypath))) {
					if ($md5sum != $e['md5sum'] && basename($e['path']) != 'config.php') {
						$attributes = empty($e['attributes']) ? 0 : $e['attributes'];
						$extension = substr(strrchr($e['path'], "."), 1);
						$exit = -1;
						$contents = base64_decode(db_fetch_cell_prepared('SELECT contents
							FROM poller_resource_cache
							WHERE id = ?',
							array($e['id']), '', true, $remote_db_cnn_id));

						/* if the file type is PHP check syntax */
						if ($extension == 'php' && $contents != '') {
							$tmpdir = sys_get_temp_dir();
							$tmpfile = tempnam($tmpdir,'ccp');

							if ((is_writeable($tmpdir) && !file_exists($tmpfile)) || (file_exists($tmpfile) && is_writable($tmpfile))) {
								if (file_put_contents($tmpfile, $contents) !== false) {
									$output = system($php_path . ' -l ' . $tmpfile, $exit);

									if ($exit == 0) {
										cacti_log("INFO: Updating '$mypath' from Cache!", false, 'POLLER');

										if (is_writable($mypath) || (!file_exists($mypath) && is_writable(dirname($mypath)))) {
											file_put_contents($mypath, $contents);

											if ($attributes != 0) {
												chmod($mypath, $attributes);

												if (fileperms($mypath) != $attributes) {
													cacti_log("WARNING: Cache cannot update permissions on '$mypath'", false, 'POLLER');
												}
											}
										} else {
											cacti_log("ERROR: Cache in cannot write to '$mypath', purge this location");
										}
									} else {
										cacti_log("ERROR: PHP Source File '$mypath' from Cache has an error while checking syntax ($exit) while executing: '$php_path -l $tmpfile'", false, 'POLLER');
										cacti_log("ERROR: PHP Source File '$mypath'': " . str_replace("\n", ' ', str_replace("\t", ' ', $output)), false, 'POLLER');
									}

									unlink($tmpfile);
								} else {
									cacti_log("ERROR: Unable to write file '" . $tmpfile . "' for PHP Syntax verification", false, 'POLLER');
								}
							} else {
								cacti_log("ERROR: Cache in cannot write to '" . $tmpfile . "', purge this location");
							}
						} elseif (is_writeable($mypath) || (!file_exists($mypath) && is_writable(dirname($mypath)))) {
							cacti_log("INFO: Updating '" . $mypath . "' from Cache!", false, 'POLLER');
							file_put_contents($mypath, $contents);
							if ($attributes != 0) {
								chmod($mypath, $attributes);
								if (fileperms($mypath) != $attributes) {
									cacti_log("WARNING: Cache cannot update permissions on '$mypath'", false, 'POLLER');
								}
							}
						} else {
							cacti_log("ERROR: Cache in cannot write to '" . $mypath . "', purge this location");
						}
					}
				} else {
					cacti_log("ERROR: Directory does not exist '" . dirname($mypath) . "'", false, 'POLLER');
				}
			}
		}
	}
}

/** md5sum_path - get a recursive md5sum on an entire directory.
 *
 * @param string $path      - The path to check for the md5sum
 * @param bool   $recursive - The path should be verified recursively
 * @return null             - No data is returned
 */
function md5sum_path($path, $recursive = true) {
    if (!is_dir($path)) {
        return false;
    }

    $filemd5s = array();
    $pobject = dir($path);

	$excluded_extensions = array('tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo');

    while (($entry = $pobject->read()) !== false) {
		if (!should_ignore_from_replication($entry)) {
			$pathinfo = pathinfo($entry);
			if (isset($pathinfo['extension'])) {
				$extension = strtolower($pathinfo['extension']);
			} else {
				$extension = '';
			}

			/* exclude spurious extensions */
			if (array_search($extension, $excluded_extensions, true) !== false) {
				continue;
			}

			if (is_dir($path . DIRECTORY_SEPARATOR . $entry) && $recursive) {
				$filemd5s[] = md5sum_path($path . DIRECTORY_SEPARATOR. $entry, $recursive);
			} elseif (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
				// Ignore directories who are not recursive
			} elseif (is_readable($path . DIRECTORY_SEPARATOR . $entry)) {
				$filemd5s[] = md5_file($path . DIRECTORY_SEPARATOR . $entry);
			} else {
				cacti_log('WARNING: Unable to read file \'' . $path . DIRECTORY_SEPARATOR . $entry . '\' into Cacti resource cache.', false, 'POLLER');
			}
         }
    }

    $pobject->close();

    return md5(implode('', $filemd5s));
}

function poller_push_to_remote_db_connect($device_or_poller, $is_poller = false) {
	global $config;
	static $device_poller_ids = array();

	$rcnn_id = false;

	if ($is_poller) {
		$poller_id = $device_or_poller;
	} elseif (!isset($device_poller_ids[$device_or_poller])) {
		$poller_id = db_fetch_cell_prepared('SELECT poller_id
			FROM host
			WHERE id = ?',
			array($device_or_poller));

		if (!empty($poller_id)) {
			$device_poller_ids[$device_or_poller] = $poller_id;
		}
	} else {
		$poller_id = $device_poller_ids[$device_or_poller];
	}

	if ($poller_id > 1) {
		$rcnn_id = poller_connect_to_remote($poller_id);
	}

	return $rcnn_id;
}


function poller_connect_to_remote($poller_id) {
	global $config;

	if ($poller_id == 1) {
		return false;
	}

	if ($config['poller_id'] == 1) {
		$cinfo = db_fetch_row_prepared('SELECT *
			FROM poller
			WHERE id = ?',
			array($poller_id));

		if (!cacti_sizeof($cinfo)) {
			cacti_log('ERROR: Remote Data Collector ID[' . $poller_id . '] to be Sync\'d does not exist!', false, 'POLLER');
			raise_message('poller_notfound');
			return false;
		}

		if ($cinfo['dbhost'] == 'localhost' || $cinfo['dbhost'] == '127.0.0.1') {
			return false;
		}

		$rcnn_id = db_connect_real(
			$cinfo['dbhost'],
			$cinfo['dbuser'],
			$cinfo['dbpass'],
			$cinfo['dbdefault'],
			'mysql',
			$cinfo['dbport'],
			$cinfo['dbssl'],
			$cinfo['dbsslkey'],
			$cinfo['dbsslcert'],
			$cinfo['dbsslca']);

		if (!is_object($rcnn_id)) {
			cacti_log('ERROR: Unable to connect to Remote Data Collector ' . $cinfo['name'], false, 'POLLER');
			raise_message('poller_noconnect');
			return false;
		}
	} else {
		// We only allow sync from the main cacti server
		raise_message('poller_nosync');
		return false;
	}

	return $rcnn_id;
}

function replicate_out($remote_poller_id = 1, $class = 'all') {
	global $config;

	$rcnn_id = poller_connect_to_remote($remote_poller_id);

	if ($rcnn_id === false) {
		return false;
	}

	// Start Push Replication
	if ($class == 'all' || $class == 'settings') {
		$data = db_fetch_assoc('SELECT *
			FROM settings
			WHERE name NOT LIKE "%_lastrun%"
			AND name NOT LIKE "path_%"
			AND name NOT LIKE "%_path"
			AND name NOT LIKE "stats%"
			AND name != "rrdtool_version"
			AND name NOT LIKE "poller_replicate%"
			AND name NOT LIKE "install%"
			AND name != "poller_enabled"
			AND name NOT LIKE "md5dirsum%"
			UNION
			SELECT *
			FROM settings
			WHERE name LIKE "path_spine%"
			AND name="poller_type"');
		replicate_table_to_poller($rcnn_id, $data, 'settings');
	}

	// Core tables
	if ($class == 'all') {
		$data = db_fetch_assoc('SELECT * FROM data_input');
		replicate_out_table($rcnn_id, $data, 'data_input', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM host_template');
		replicate_out_table($rcnn_id, $data, 'host_template', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM host_template_graph');
		replicate_out_table($rcnn_id, $data, 'host_template_graph', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM host_template_snmp_query');
		replicate_out_table($rcnn_id, $data, 'host_template_snmp_query', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM snmp_query');
		replicate_out_table($rcnn_id, $data, 'snmp_query', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM data_input_fields');
		replicate_out_table($rcnn_id, $data, 'data_input_fields', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM poller');
		replicate_out_table($rcnn_id, $data, 'poller', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM version');
		replicate_out_table($rcnn_id, $data, 'version', $remote_poller_id);
	}

	// Auth tables
	if ($class == 'all' || $class == 'auth') {
		$data = db_fetch_assoc('SELECT * FROM user_auth');
		replicate_out_table($rcnn_id, $data, 'user_auth', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_auth_group');
		replicate_out_table($rcnn_id, $data, 'user_auth_group', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_auth_group_members');
		replicate_out_table($rcnn_id, $data, 'user_auth_group_members', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_auth_group_perms');
		replicate_out_table($rcnn_id, $data, 'user_auth_group_perms', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_auth_group_realm');
		replicate_out_table($rcnn_id, $data, 'user_auth_group_realm', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_auth_realm');
		replicate_out_table($rcnn_id, $data, 'user_auth_realm', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_domains');
		replicate_out_table($rcnn_id, $data, 'user_domains', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM user_domains_ldap');
		replicate_out_table($rcnn_id, $data, 'user_domains_ldap', $remote_poller_id);
	}

	if ($class == 'all' || $class == 'data') {
		$data = db_fetch_assoc_prepared('SELECT hsq.*
			FROM host_snmp_query AS hsq
			INNER JOIN host AS h
			ON h.id=hsq.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'host_snmp_query', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT pc.*
			FROM poller_command AS pc
			WHERE pc.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'poller_command', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT pi.*
			FROM poller_item AS pi
			WHERE pi.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'poller_item', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT h.*
			FROM host AS h
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'host', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT hsc.*
			FROM host_snmp_cache AS hsc
			INNER JOIN host AS h
			ON h.id=hsc.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'host_snmp_cache', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT pri.*
			FROM poller_reindex AS pri
			INNER JOIN host AS h
			ON h.id=pri.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'poller_reindex', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT dl.*
			FROM data_local AS dl
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'data_local', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT gl.*
			FROM graph_local AS gl
			INNER JOIN host AS h
			ON h.id=gl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'graph_local', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT dtd.*
			FROM data_template_data AS dtd
			INNER JOIN data_local AS dl
			ON dtd.local_data_id=dl.id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'data_template_data', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT dtr.*
			FROM data_template_rrd AS dtr
			INNER JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'data_template_rrd', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT gti.*
			FROM graph_templates_item AS gti
			INNER JOIN graph_local AS gl
			ON gti.local_graph_id=gl.id
			INNER JOIN host AS h
			ON h.id=gl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'graph_templates_item', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT did.*
			FROM data_input_data AS did
			INNER JOIN data_template_data AS dtd
			ON did.data_template_data_id=dtd.id
			INNER JOIN data_local AS dl
			ON dl.id=dtd.local_data_id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?',
			array($remote_poller_id));
		replicate_out_table($rcnn_id, $data, 'data_input_data', $remote_poller_id);
	}

	api_plugin_hook_function('replicate_out', $remote_poller_id);

	$stats = db_fetch_row_prepared('SELECT
		SUM(CASE WHEN action=0 THEN 1 ELSE 0 END) AS snmp,
		SUM(CASE WHEN action=1 THEN 1 ELSE 0 END) AS script,
		SUM(CASE WHEN action=2 THEN 1 ELSE 0 END) AS server
		FROM poller_item
		WHERE poller_id = ?',
		array($remote_poller_id));

	if (cacti_sizeof($stats)) {
		db_execute_prepared('UPDATE poller
			SET snmp = ?, script = ?, server = ?
			WHERE id = ?',
			array($stats['snmp'], $stats['script'], $stats['server'], $remote_poller_id));
	}

	raise_message('poller_sync');

	return true;
}

function replicate_in() {
	$replicate_inout_tables = array(
		'host' => array(
			'direction'   => 'inout', // Relatively small table
			'in_freq'     => 'onchange',
			'out_freq'    => 'onrecovery',
			'out_columns' => 'status, status_event_count, status_fail_date, status_rec_date, status_last_errors, min_time, max_time, cur_time, avg_time, polling_time, total_polls, failed_polls, availability',
			'in_columns'  => 'all',
			'setting'     => 'poller_replicate_device_cache_crc_|poller_id|'
		),
		'host_snmp_cache' => array(
			'direction'   => 'inout', // Potentially large table
			'in_freq'     => 'onchange',
			'out_freq'    => 'onrecovery',
			'out_columns' => 'all_changed',
			'in_columns'  => 'all',
			'setting'     => 'poller_replicate_device_cache_crc_|poller_id|'
		),
		'poller_reindex' => array(
			'direction'   => 'inout', // Small table
			'in_freq'     => 'always',
			'out_freq'    => 'onrecovery',
			'out_columns' => 'assert_value',
			'in_columns'  => 'all',
			'setting'     => 'poller_replicate_device_cache_crc_|poller_id|'
		)
	);

	api_plugin_hook_function('replicate_in', $remote_poller_id);
}

function replicate_out_table($conn, &$data, $table, $remote_poller_id) {
	if (cacti_sizeof($data)) {
		/* check if the table structure changed */
		$local_columns  = db_fetch_assoc('SHOW COLUMNS FROM ' . $table);
		$remote_columns = db_fetch_assoc('SHOW COLUMNS FROM ' . $table, true, $conn);

		if (cacti_sizeof($local_columns) != cacti_sizeof($remote_columns)) {
			cacti_log('NOTE: Replicate Out Detected a Table Structure Change for ' . $table);
			$create = db_fetch_row('SHOW CREATE TABLE ' . $table);
			if (isset($create['Create Table'])) {
				cacti_log('NOTE: Replication Recreating Remote Table Structure for ' . $table);
				db_execute('DROP TABLE IF EXISTS ' . $table, true, $conn);
				db_execute($create['Create Table'], true, $conn);
			}
		}

		db_execute("TRUNCATE TABLE $table", true, $conn);

		$prefix    = "REPLACE INTO $table (";
		$sql       = '';
		$colcnt    = 0;
		$rows_done = 0;
		$columns   = array_keys($data[0]);
		$skipcols  = array();

		foreach($columns as $index => $c) {
			if (!db_column_exists($table, $c, false, $conn)) {
				$skipcols[$index] = $c;
			} else {
				$prefix .= ($colcnt > 0 ? ', ':'') . $c;
				$colcnt++;
			}
		}
		$prefix .= ') VALUES ';

		$rowcnt = 0;
		foreach($data as $row) {
			$colcnt  = 0;
			$sql_row = '(';
			foreach($row as $col => $value) {
				if (array_search($col, $skipcols) === false) {
					$sql_row .= ($colcnt > 0 ? ', ':'') . db_qstr($value);
					$colcnt++;
				}
			}
			$sql_row .= ')';
			$sql     .= ($rowcnt > 0 ? ', ':'') . $sql_row;

			$rowcnt++;

			if ($rowcnt > 1000) {
				db_execute($prefix . $sql, true, $conn);
				$rows_done += db_affected_rows($conn);
				$sql = '';
				$rowcnt = 0;
			}
		}

		if ($rowcnt > 0) {
			db_execute($prefix . $sql, true, $conn);
			$rows_done += db_affected_rows($conn);
		}

		cacti_log('NOTE: Table ' . $table . ' Replicated to Remote Poller ' . $remote_poller_id . ' With ' . $rows_done . ' Rows Updated');
	} else {
		db_execute("TRUNCATE TABLE $table", true, $conn);

		cacti_log('NOTE: Table ' . $table . ' Not Replicated to Remote Poller ' . $remote_poller_id . ' Due to No Rows Found');
	}
}

function poller_push_reindex_only_data_to_main($device_id, $data_query_id) {
	global $remote_db_cnn_id;

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host
		WHERE id = ?',
		array($device_id));

	if ($poller_id > 1) {
		$sql_where  = 'WHERE host_id = ' . $device_id . ' AND data_query_id = ' . $data_query_id;

		$poller_reindex = db_fetch_assoc("SELECT *
			FROM poller_reindex
			$sql_where");

		replicate_table_to_poller($remote_db_cnn_id, $poller_reindex, 'poller_reindex');
	}
}

function poller_push_reindex_data_to_main($device_id = 0, $data_query_id = 0) {
	global $remote_db_cnn_id;

	$sql_where   = '';
	$sql_where1  = '';
	$sql_where  .= $device_id > 0 ? 'WHERE host_id = ' . $device_id:'';
	$sql_where1 .= $device_id > 0 ? ' AND host_id = ' . $device_id:'';
	if ($data_query_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE ') . ' snmp_query_id = ' . $data_query_id;
		$sql_where1 .= ' AND snmp_query_id = ' . $data_query_id;
	}

	$min_reindex_cache = db_fetch_cell("SELECT MIN(last_updated)
		FROM host_snmp_cache
		$sql_where");

	$recache_hosts = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT host_id
			FROM host_snmp_cache
			WHERE last_updated > ?
			$sql_where1",
			array($min_reindex_cache)),
		'host_id', 'host_id'
	);

	if (sizeof($recache_hosts)) {
		$local_data_ids = db_fetch_assoc("SELECT *
			FROM data_local
			WHERE host_id IN (" . implode(', ', $recache_hosts) . ")
			$sql_where1");

		replicate_table_to_poller($remote_db_cnn_id, $local_data_ids, 'data_local');

		$local_graph_ids = db_fetch_assoc("SELECT *
			FROM graph_local
			WHERE host_id IN (" . implode(', ', $recache_hosts) . ")
			$sql_where1");

		replicate_table_to_poller($remote_db_cnn_id, $local_graph_ids, 'graph_local');

		$host_snmp_cache = db_fetch_assoc("SELECT *
			FROM host_snmp_cache
			WHERE host_id IN (" . implode(', ', $recache_hosts) . ")
			$sql_where1");

		replicate_table_to_poller($remote_db_cnn_id, $host_snmp_cache, 'host_snmp_cache');

		// TODO: Make schema's equivalent renamed snmp_query_id to data_query_id everywhere
		$sql_where1 = str_replace('snmp_query_id', 'data_query_id', $sql_where1);

		$poller_reindex = db_fetch_assoc("SELECT *
			FROM poller_reindex
			WHERE host_id IN (" . implode(', ', $recache_hosts) . ")
			$sql_where1");

		replicate_table_to_poller($remote_db_cnn_id, $poller_reindex, 'poller_reindex');
	}
}

function replicate_table_to_poller($conn, &$data, $table) {
	if (cacti_sizeof($data)) {
		$prefix    = "INSERT INTO $table (";
		$suffix    = ' ON DUPLICATE KEY UPDATE ';
		$sql       = '';
		$colcnt    = 0;
		$rows_done = 0;
		$columns   = array_keys($data[0]);
		$skipcols  = array();

		foreach($columns as $index => $c) {
			if (!db_column_exists($table, $c, false, $conn)) {
				$skipcols[$index] = $c;
			} else {
				$prefix .= ($colcnt > 0 ? ', ':'') . $c;
				$suffix .= ($colcnt > 0 ? ', ':'') . "$c=VALUES($c)";
				$colcnt++;
			}
		}
		$prefix .= ') VALUES ';

		$rowcnt = 0;
		foreach($data as $row) {
			$colcnt  = 0;
			$sql_row = '(';
			foreach($row as $col => $value) {
				if (array_search($col, $skipcols) === false) {
					$sql_row .= ($colcnt > 0 ? ', ':'') . db_qstr($value);
					$colcnt++;
				}
			}
			$sql_row .= ')';
			$sql     .= ($rowcnt > 0 ? ', ':'') . $sql_row;

			$rowcnt++;

			if ($rowcnt > 1000) {
				db_execute($prefix . $sql . $suffix, true, $conn);
				$rows_done += db_affected_rows($conn);
				$sql = '';
				$rowcnt = 0;
			}
		}

		if ($rowcnt > 0) {
			db_execute($prefix . $sql . $suffix, true, $conn);
			$rows_done += db_affected_rows($conn);
		}

		cacti_log('NOTE: Table ' . $table . ' Replicated to Poller With ' . $rows_done . ' Rows Updated', true, 'WEBUI', POLLER_VERBOSITY_MEDIUM);
	}
}

function poller_recovery_flush_boost($poller_id) {
	global $config;

	if ($poller_id > 1) {
		if ($config['connection'] == 'recovery') {
			$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
			$extra_args = '-q ' . cacti_escapeshellarg($config['base_path'] . '/poller_recovery.php');
			exec_background($command_string, $extra_args);
		}
	}
}

function poller_push_data_to_main() {
	global $config, $remote_db_cnn_id;

	if ($config['poller_id'] > 1) {
		$host_records = db_fetch_assoc_prepared('SELECT id, snmp_sysDescr, snmp_sysObjectID,
			snmp_sysUpTimeInstance, snmp_sysContact, snmp_sysName, snmp_sysLocation,
			status, status_event_count, status_fail_date, status_rec_date,
			status_last_error, min_time, max_time, cur_time, avg_time, polling_time,
			total_polls, failed_polls, availability, last_updated
			FROM host
			WHERE poller_id = ?',
			array($config['poller_id']));

		$updates = array(
			'snmp_sysDescr',
			'snmp_sysObjectID',
			'snmp_sysUpTimeInstance',
			'snmp_sysContact',
			'snmp_sysName',
			'snmp_sysLocation',
			'status',
			'status_event_count',
			'status_fail_date',
			'status_rec_date',
			'status_last_error',
			'min_time',
			'max_time',
			'cur_time',
			'avg_time',
			'polling_time',
			'total_polls',
			'failed_polls',
			'availability',
			'last_updated'
		);

		poller_push_table($remote_db_cnn_id, $host_records, 'host', true, $updates);

		$poller_item_records = db_fetch_assoc_prepared('SELECT local_data_id, host_id, rrd_name, rrd_step, rrd_next_step
			FROM poller_item
			WHERE poller_id = ?',
			array($config['poller_id']));

		$updates = array(
			'rrd_next_step'
		);

		poller_push_table($remote_db_cnn_id, $poller_item_records, 'poller_item', true, $updates);
	}
}

function poller_push_table($db_cnn, $records, $table, $ignore = false, $dupes = array()) {
	$prefix = 'INSERT ' . ($ignore ? 'IGNORE':'') . ' INTO ' . $table . ' ';
	$first  = true;
	$dupe   = '';

	if (cacti_sizeof($records)) {
		foreach($records as $r) {
			if ($first) {
				$prefix .= '(`' . implode('`,`', array_keys($r)) . '`) VALUES ';
				$first = false;
			}

			$string = '(';
			foreach($r as $c) {
				$string .= ($string == '(' ? '':', ') . db_qstr($c);
			}
			$string .= ')';

			$sql[] = $string;
		}

		if (sizeof($dupes)) {
			$dupe = ' ON DUPLICATE KEY UPDATE ';
			$i = 0;
			foreach($dupes as $item) {
				$dupe .= ($i == 0 ? '':', ') . "$item=VALUES($item)";
				$i++;
			}
		}

		$sqln = array_chunk($sql, 1000);

		foreach($sqln as $sql) {
			$osql = implode(',', $sql);
			db_execute($prefix . $osql . $dupe, true, $db_cnn);
		}
	}

	return sizeof($records);
}

function should_ignore_from_replication($path) {
	$entry = basename($path);
	return ($entry == '.' || $entry == '..' || $entry == '.git' || $entry == '' || $entry == 'config.php');
}

function get_remote_poller_ids_from_graphs(&$graphs) {
	if (cacti_sizeof($graphs)) {
		$graphs = implode(', ', $graphs);
	}

	if ($graphs != '') {
		return array_rekey(
			db_fetch_assoc('SELECT DISTINCT poller_id
				FROM host AS h
				INNER JOIN graph_local AS gl
				ON h.id = gl.host_id
				WHERE poller_id != 1
				AND gl.id IN (' . $graphs . ')'),
			'poller_id', 'poller_id'
		);
	} else {
		return array();
	}
}

function get_remote_poller_ids_from_data_sources(&$data_sources) {
	if (cacti_sizeof($data_sources)) {
		$data_sources = implode(', ', $data_sources);
	}

	if ($data_sources != '') {
		return array_rekey(
			db_fetch_assoc('SELECT DISTINCT poller_id
				FROM host AS h
				INNER JOIN data_local AS dl
				ON h.id = dl.host_id
				WHERE poller_id != 1
				AND dl.id IN (' . $data_sources . ')'),
			'poller_id', 'poller_id'
		);
	} else {
		return array();
	}
}

function get_remote_poller_ids_from_devices(&$devices) {
	if (cacti_sizeof($devices)) {
		$devices = implode(', ', $devices);
	}

	if ($devices != '') {
		return array_rekey(
			db_fetch_assoc('SELECT DISTINCT poller_id
				FROM host AS h
				WHERE poller_id != 1
				AND id IN (' . $devices . ')'),
			'poller_id', 'poller_id'
		);
	} else {
		return array();
	}
}

