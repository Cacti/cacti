<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * exec_poll - executes a command and returns its output
 *
 * @param string $command The command to execute
 * @param int    $timeout The command timeout
 *
 * @return string The output of $command after execution
 */
function exec_poll(string $command, int $timeout = 5) : string {
	$output = [];
	$return = 0;

	$data = exec_with_timeout($command, $output, $return, $timeout);

	if ($return != 0) {
		cacti_log(sprintf('WARNING: Script:%s, ErrorCode:%d, Output:%s', $command, $return, implode(',', $output)), false, 'POLLER');
	}

	if ($data == '') {
		return 'U';
	} else {
		return $data;
	}
}

/**
 * exec_poll_php - sends a command to the php script server and returns the output
 *
 * @param string $command             The command to send to the php script server
 * @param bool   $using_proc_function Whether or not this version of php is making use
 *                                    of the proc_open() and proc_close() functions (php 4.3+)
 * @param array  $pipes               The array of r/w pipes returned from proc_open()
 * @param mixed  $proc_fd             The file descriptor returned from proc_open()
 *
 * @return string The output of $command after execution against the php script server
 */
function exec_poll_php(string $command, bool $using_proc_function, array $pipes, mixed $proc_fd) : string {
	$output = '';

	// execute using php process
	if ($using_proc_function == 1) {
		if (is_resource($proc_fd)) {
			/* $pipes now looks like this:
			 * 0 => writeable handle connected to child stdin
			 * 1 => readable handle connected to child stdout
			 * 2 => any error output will be sent to child stderr */

			// send command to the php server
			fwrite($pipes[0], $command . "\r\n");
			fflush($pipes[0]);

			$output = fgets($pipes[1], 8192);

			if (substr_count($output, 'ERROR') > 0) {
				$output = 'U';
			}
		}
		// execute the old fashion way
	} else {
		// formulate command
		$command = read_config_option('path_php_binary') . ' ' . $command;

		if (function_exists('popen')) {
			if (CACTI_SERVER_OS == 'unix') {
				$fp = popen($command, 'r');
			} else {
				$fp = popen($command, 'rb');
			}

			// return if the popen command was not successful
			if (!is_resource($fp)) {
				cacti_log('WARNING; Problem with POPEN command.', false, 'POLLER');

				return 'U';
			}

			$output = fgets($fp, 8192);

			pclose($fp);
		} else {
			$output = shell_exec($command);
		}
	}

	return $output;
}

/**
 * exec_background - executes a program in the background so that php can continue
 * to execute code in the foreground.
 *
 * @param string       $filename      The full pathname to the script to execute
 * @param string|array $args          Any additional arguments. Arrays are escaped per-element via cacti_escapeshellarg.
 * @param string|array $redirect_args Any additional arguments for file re-direction.  Otherwise output goes to /dev/null
 *
 * @return void
 */
function exec_background(string $filename, string|array $args = '', string|array $redirect_args = '') : void {
	global $debug;

	if (is_array($args)) {
		$args = implode(' ', array_map('cacti_escapeshellarg', $args));
	}

	/* redirect_args intentionally bypass escapeshellarg because they contain
	 * shell operators (>, 2>&1, etc.) that must be passed through literally.
	 * Only hardcoded redirect strings should be passed here, never user input. */
	if (is_array($redirect_args)) {
		$redirect_args = implode(' ', $redirect_args);
	}

	cacti_log("DEBUG: About to Spawn a Remote Process [CMD: $filename, ARGS: $args]", true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE : POLLER_VERBOSITY_DEBUG));

	if ($filename != '') {
		if (file_exists($filename)) {
			if (CACTI_SERVER_OS == 'win32') {
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
		}
	} else {
		cacti_log('WARNING: Empty filename sent to exec_background()', false, 'POLLER');
		cacti_debug_backtrace('POLLER');
	}
}

/**
 * exec_with_timeout - Execute a command and return it's output. Either wait until the
 * command exits or the timeout has expired.
 *
 * @param string $cmd         Command to execute.
 * @param array  $output      A return array of output.
 * @param int    $return_code The return code from the script
 * @param int    $timeout     Timeout in seconds.
 *
 * @return mixed Either the last line of output or false on error
 */
function exec_with_timeout(string $cmd, array &$output, int &$return_code, int $timeout = 5) : mixed {
	// File descriptors passed to the process.
	$descriptors = [
		0 => ['pipe', 'r'],  // stdin
		1 => ['pipe', 'w'],  // stdout
		2 => ['pipe', 'w']   // stderr
	];

	// Use setsid when available (Linux) so the child becomes a process-group leader,
	// allowing posix_kill(-pid, 9) to reliably kill the entire subtree on timeout.
	// Falls back to plain exec on systems without setsid (macOS, BSD).
	$setsid = '';

	if (CACTI_SERVER_OS != 'win32') {
		$setsid_path = trim(shell_exec('which setsid 2>/dev/null') ?? '');

		if ($setsid_path !== '') {
			$setsid = 'setsid -- ';
		}
	}

	$cmd_full = $setsid . $cmd;

	// Start the process.
	$process = proc_open('exec ' . $cmd_full, $descriptors, $pipes);

	if (!is_resource($process)) {
		return false;
	}

	// Set the stdout stream to non-blocking.
	stream_set_blocking($pipes[1], false);

	// Set the stderr stream to non-blocking.
	stream_set_blocking($pipes[2], false);

	// Turn the timeout into microseconds.
	$timeout = (int) $timeout * 1000000;

	// Output buffer.
	$buffer = '';

	// While we have time to wait.
	while ($timeout > 0) {
		$start = microtime(true);

		// Wait until we have output or the timer expired.
		$read  = [$pipes[1]];
		$write = [];
		$other = [];
		stream_select($read, $write, $other, 0, $timeout);

		// Get the status of the process.
		// Do this before we read from the stream,
		// this way we can't lose the last bit of output if the process dies between these functions.
		$status = proc_get_status($process);

		// Read the contents from the buffer.
		// This function will always return immediately as the stream is non-blocking.
		$buffer .= stream_get_contents($pipes[1]);

		if (!$status['running']) {
			// Break from this loop if the process exited before the timeout.
			break;
		}

		// Subtract the number of microseconds that we waited.
		$timeout -= (int) ((microtime(true) - $start) * 1000000);
	}

	// Check if there were any errors.
	$errors = stream_get_contents($pipes[2]);

	if (isset($status['exitcode'])) {
		$return_code = $status['exitcode'];
	} else {
		$return_code = 1;
	}

	// Only surface stderr noise when the command actually failed; successful
	// commands may write informational output to stderr that isn't actionable.
	if ($return_code != 0 && !empty($errors)) {
		cacti_log("WARNING: Command '$cmd' exited with code $return_code, stderr: " . trim($errors), false, 'POLLER', POLLER_VERBOSITY_MEDIUM);
	}

	// Kill the process in case the timeout expired and it's still running.
	// If the process already exited this won't do anything.
	// Use negative PID to kill the entire process group (setsid makes child the group leader).
	if (isset($status['pid']) && $status['running'] && function_exists('posix_kill')) {
		posix_kill(-$status['pid'], 9);
	}

	proc_terminate($process, 9);

	// Close all streams.
	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	proc_close($process);

	if ($buffer != '') {
		$output = explode("\n", $buffer);

		return (end($output));
	} else {
		return null;
	}
}

function file_escaped(string $file) : bool {
	if (str_starts_with($file, '"') && str_ends_with($file, '"')) {
		return true;
	}

	return false;
}

/**
 * update_reindex_cache - builds a cache that is used by the poller to determine if the
 * indexes for a particular data query/host have changed
 *
 * @param int $host_id       The id of the host to which the data query belongs
 * @param int $data_query_id The id of the data query to rebuild the reindex cache for
 *
 * @return void
 */
function update_reindex_cache(int $host_id, int $data_query_id) : void {
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');
	include_once(CACTI_PATH_LIBRARY . '/snmp.php');

	// will be used to keep track of sql statements to execute later on
	$recache_stack = [];

	$host = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host
		WHERE id = ?',
		[$host_id]);

	$data_query = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM host_snmp_query
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	$data_query_type = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' data_input.type_id
		FROM data_input
		INNER JOIN snmp_query
		ON data_input.id = snmp_query.data_input_id
		WHERE snmp_query.id = ?',
		[$data_query_id]);

	$data_query_xml  = get_data_query_array($data_query_id);
	$assert_value    = '';

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
						$host['snmp_timeout'], $host['snmp_retries'], $host['max_oids']);

					if ($session !== false) {
						if ($oid_uptime == '.1.3.6.1.2.1.1.3.0') {
							$checks = [
								'.1.3.6.1.6.3.10.2.1.3.0',
								'.1.3.6.1.2.1.1.3.0'
							];

							foreach ($checks as $oid_uptime) {
								$assert_value = cacti_snmp_session_get($session, $oid_uptime);

								if (is_numeric($assert_value)) {
									if ($oid_uptime == '.1.3.6.1.6.3.10.2.1.3.0') {
										$assert_value *= 100;
									}

									break;
								}
							}

							$oid_uptime = '.1.3.6.1.2.1.1.3.0';
						} else {
							$assert_value = cacti_snmp_session_get($session, $oid_uptime);
						}
					}

					$session->close();

					$recache_stack[] = "('$host_id', '$data_query_id'," . POLLER_ACTION_SNMP . ", '<', '$assert_value', '$oid_uptime', 1)";
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
					[$host_id, $data_query_id]));

				/* now, we have to build the (list of) commands that are later used on a recache event
				 * the result of those commands will be compared to the assert_value we have just computed
				 * on a comparison failure, a reindex event will be generated
				 */
				switch ($data_query_type) {
					case DATA_INPUT_TYPE_SNMP_QUERY:
						if (isset($data_query_xml['oid_num_indexes'])) { // we have a specific OID for counting indexes
							$recache_stack[] = "($host_id, $data_query_id," . POLLER_ACTION_SNMP . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr($data_query_xml['oid_num_indexes']) . ', 1)';
						} else { // count all indexes found
							$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SNMP_COUNT . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr($data_query_xml['oid_index']) . ', 1)';
						}

						break;
					case DATA_INPUT_TYPE_SCRIPT_QUERY:
						if (isset($data_query_xml['arg_num_indexes'])) { // we have a specific request for counting indexes
							// escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string?
							$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ' : '') . $data_query_xml['arg_num_indexes'], $data_query_xml['script_path'], $host_id)) . ', 1)';
						} else { // count all indexes found
							// escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string?
							$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT_COUNT . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ' : '') . $data_query_xml['arg_index'], $data_query_xml['script_path'], $host_id)) . ', 1)';
						}

						break;
					case DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER:
						if (isset($data_query_xml['arg_num_indexes'])) { // we have a specific request for counting indexes
							// escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string?
							$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SCRIPT_PHP . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(get_script_query_path($data_query_xml['script_function'] . ' ' . (isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ' : '') . $data_query_xml['arg_num_indexes'], $data_query_xml['script_path'], $host_id)) . ', 1)';
						} else { // count all indexes found
							// TODO: push the correct assert value
							// escape path (windows!) and parameters for use with database sql; TODO: replace by db specific escape function like mysql_real_escape_string?
							// $recache_stack[] = "($host_id, $data_query_id," . POLLER_ACTION_SCRIPT_PHP_COUNT . ", '=', " . db_qstr($assert_value) . ", " . db_qstr(get_script_query_path($data_query_xml['script_function'] . ' ' . (isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ': '') . $data_query_xml['arg_index'], $data_query_xml['script_path'], $host_id)) . ", 1)";
							// omit the assert value until we are able to run an 'index' command through script server
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
					[$host_id, $data_query_id, $data_query['sort_field']]);

				if (cacti_sizeof($primary_indexes) > 0) {
					foreach ($primary_indexes as $index) {
						$assert_value = $index['field_value'];

						if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
							$recache_stack[] = "($host_id, $data_query_id, " . POLLER_ACTION_SNMP . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(($data_query_xml['fields'][$data_query['sort_field']]['source'] == 'index') ? $data_query_xml['oid_index'] . '.' . $index['snmp_index'] : $data_query_xml['fields'][$data_query['sort_field']]['oid'] . '.' . $index['snmp_index']) . ', 1)';
						} elseif ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
							$recache_stack[] = '(' . $host_id . ', ' . $data_query_id . ', ' . POLLER_ACTION_SCRIPT . ", '=', " . db_qstr($assert_value) . ', ' . db_qstr(get_script_query_path((isset($data_query_xml['arg_prepend']) ? $data_query_xml['arg_prepend'] . ' ' : '') . $data_query_xml['arg_get'] . ' ' . $data_query_xml['fields'][$data_query['sort_field']]['query_name'] . ' "' . $index['snmp_index'] . '"', $data_query_xml['script_path'], $host_id)) . ', 1)';
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

function poller_update_poller_reindex_from_buffer(int $host_id, int $data_query_id, array &$recache_stack) : void {
	// set all fields present value to 0, to mark the outliers when we are all done
	db_execute_prepared('UPDATE poller_reindex
		SET present = 0
		WHERE host_id = ?
		AND data_query_id = ?',
		[$host_id, $data_query_id]);

	// setup the database call
	$sql_prefix   = 'INSERT INTO poller_reindex (host_id, data_query_id, action, op, assert_value, arg1, present) VALUES';
	$sql_suffix   = ' ON DUPLICATE KEY UPDATE action=VALUES(action), op=VALUES(op), assert_value=VALUES(assert_value), present=VALUES(present)';

	// use a reasonable insert buffer, the default is 1MByte
	$max_packet   = 256000;

	// setup some defaults
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	foreach ($recache_stack as $record) {
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

	// remove stale records FROM the poller reindex
	db_execute_prepared('DELETE FROM poller_reindex
		WHERE host_id = ?
		AND data_query_id = ?
		AND present = 0', [$host_id, $data_query_id]);

	poller_push_reindex_only_data_to_main($host_id, $data_query_id);
}

/**
 * process_poller_output - grabs data from the 'poller_output' table and feeds the *completed*
 * results to RRDtool for processing
 *
 * @param mixed $rrdtool_pipe The array of pipes containing the file descriptor for rrdtool
 * @param int   $remainder    Don't use LIMIT if true
 *
 * @return int The number of rrdfiles processed
 */
function process_poller_output(mixed &$rrdtool_pipe, int $remainder = 0) : int {
	global $debug;

	static $rrd_field_names = [];
	static $checked_bad     = false;

	include_once(CACTI_PATH_LIBRARY . '/rrd.php');

	// let's count the number of rrd files we processed
	$rrds_processed = 0;
	$max_rows       = 40000;

	if ($remainder == 0) {
		$remainder = $max_rows;
	}

	cacti_log("Processing Poller Output with $remainder maximum items to be processed", false, 'POLLER', POLLER_VERBOSITY_HIGH);

	if ($remainder === 0) {
		$limit = 'LIMIT ' . $max_rows;
	} else {
		$limit = '';
	}

	// create/update the rrd files
	$results = db_fetch_assoc("SELECT po.output, po.time,
		UNIX_TIMESTAMP(po.time) as unix_time, po.local_data_id, dl.data_template_id,
		pi.rrd_path, pi.rrd_name, pi.rrd_num
		FROM poller_output AS po
		INNER JOIN poller_item AS pi
		ON po.local_data_id = pi.local_data_id
		AND po.rrd_name = pi.rrd_name
		INNER JOIN data_local AS dl
		ON dl.id = po.local_data_id
		ORDER BY po.local_data_id
		$limit");

	if (!cacti_sizeof($rrd_field_names)) {
		$rrd_field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
				CONCAT(data_template_id, "_", data_name) AS keyname, data_source_names AS data_source_name
				FROM poller_data_template_field_mappings'),
			'keyname', ['data_source_name']);
	}

	if (cacti_sizeof($results)) {
		// create an array keyed off of each .rrd file
		foreach ($results as $item) {
			// trim the default characters, but add single and double quotes
			$value            = $item['output'];
			$unix_time        = $item['unix_time'];
			$rrd_path         = $item['rrd_path'];
			$rrd_name         = $item['rrd_name'];
			$local_data_id    = $item['local_data_id'];
			$data_template_id = $item['data_template_id'];
			$rrd_tmpl         = '';

			$rrd_update_array[$rrd_path]['local_data_id'] = $local_data_id;

			if ((is_numeric($value)) || ($value == 'U' && $rrd_name != '')) {
				// single one value output
				$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = $value;
			} elseif (is_hexadecimal($value)) {
				/**
				 * special case of one value output: hexadecimal to decimal conversion
				 * attempt to accommodate 32bit and 64bit systems
				 */
				$value = str_replace(' ', '', $value);

				if (strlen($value) <= 8) {
					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = hexdec($value);
				} elseif (function_exists('bcpow')) {
					$dec    = 0;
					$vallen = strlen($value);

					for ($i = 1; $i <= $vallen; $i++) {
						$dec = bcadd($dec, bcmul(strval(hexdec($value[$i - 1])), bcpow('16', strval($vallen - $i))));
					}

					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = $dec;
				} else {
					$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = 'U';
				}
			} elseif (str_contains($value, ':')) {
				// multiple value output
				$values = preg_split('/\s+/', $value);

				if ($data_template_id > 0) {
					$unused_data_source_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
							FROM data_template_rrd AS dtr
							LEFT JOIN graph_templates_item AS gti
							ON dtr.id = gti.task_item_id
							WHERE dtr.local_data_id = ?
							AND gti.task_item_id IS NULL',
							[$local_data_id]),
						'data_source_name', 'data_source_name'
					);
				} else {
					$unused_data_source_names = [];
				}

				foreach ($values as $value) {
					$matches = explode(':', $value);

					if (cacti_sizeof($matches) == 2) {
						if (isset($rrd_field_names[$item['data_template_id'] . '_' . $matches[0]])) {
							$field = $rrd_field_names[$item['data_template_id'] . '_' . $matches[0]]['data_source_name'];

							if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
								continue;
							}

							cacti_log("Parsed MULTI output field '" . $matches[0] . ':' . $matches[1] . "' [map " . $matches[0] . '->' . $field . ']' , true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE : POLLER_VERBOSITY_HIGH));

							if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
								$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = $matches[1];
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
								$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = hexdec($matches[1]);
							} else {
								$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = 'U';
							}

							$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;

							$rrd_update_array[$rrd_path]['template'] = $rrd_tmpl;
						} else {
							// Handle data source without a data template
							if ($data_template_id > 0) {
								$nt_rrd_field_names = array_rekey(
									db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
										FROM graph_templates_item AS gti
										INNER JOIN data_template_rrd AS dtr
										ON gti.task_item_id = dtr.id
										INNER JOIN data_input_fields AS dif
										ON dtr.data_input_field_id = dif.id
										WHERE dtr.local_data_id = ?',
										[$local_data_id]),
									'data_name', 'data_source_name'
								);
							} else {
								$nt_rrd_field_names = array_rekey(
									db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
										FROM data_template_rrd AS dtr
										INNER JOIN data_input_fields AS dif
										ON dtr.data_input_field_id = dif.id
										WHERE dtr.local_data_id = ?',
										[$local_data_id]),
									'data_name', 'data_source_name'
								);
							}

							if (cacti_sizeof($nt_rrd_field_names)) {
								if (isset($nt_rrd_field_names[$matches[0]])) {
									$field = $nt_rrd_field_names[$matches[0]];

									if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
										continue;
									}

									cacti_log("Parsed MULTI output field '" . $matches[0] . ':' . $matches[1] . "' [map " . $matches[0] . '->' . $field . ']' , true, 'POLLER', ($debug ? POLLER_VERBOSITY_NONE : POLLER_VERBOSITY_HIGH));

									if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
										$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = $matches[1];
									} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
										$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = hexdec($matches[1]);
									} else {
										$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = 'U';
									}

									$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
								}
							}

							$rrd_update_array[$rrd_path]['template'] = $rrd_tmpl;
						}
					}
				}
			} else {
				if ($data_template_id > 0) {
					$unused_data_source_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
							FROM data_template_rrd AS dtr
							LEFT JOIN graph_templates_item AS gti
							ON dtr.id = gti.task_item_id
							WHERE dtr.local_data_id = ?
							AND gti.task_item_id IS NULL',
							[$local_data_id]),
						'data_source_name', 'data_source_name'
					);

					$nt_rrd_field_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
							FROM graph_templates_item AS gti
							INNER JOIN data_template_rrd AS dtr
							ON gti.task_item_id = dtr.id
							INNER JOIN data_input_fields AS dif
							ON dtr.data_input_field_id = dif.id
							WHERE dtr.local_data_id = ?',
							[$local_data_id]),
						'data_name', 'data_source_name'
					);
				} else {
					$unused_data_source_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
							FROM data_template_rrd AS dtr
							WHERE dtr.local_data_id = ?
							AND gti.task_item_id IS NULL',
							[$local_data_id]),
						'data_source_name', 'data_source_name'
					);

					$nt_rrd_field_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
							FROM data_template_rrd AS dtr
							INNER JOIN data_input_fields AS dif
							ON dtr.data_input_field_id = dif.id
							WHERE dtr.local_data_id = ?',
							[$local_data_id]),
						'data_name', 'data_source_name'
					);
				}

				$expected = '';

				if (cacti_sizeof($nt_rrd_field_names)) {
					foreach ($nt_rrd_field_names as $field) {
						if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
							continue;
						}

						$expected .= ($expected != '' ? ' ' : '') . "$field:value";

						$rrd_update_array[$rrd_path]['times'][$unix_time][$field] = 'U';
						$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
					}

					$rrd_update_array[$rrd_path]['template'] = $rrd_tmpl;
				}

				cacti_log(sprintf('WARNING: Invalid output! MULTI DS[%d] Encountered [%s] Expected[%s]', $item['local_data_id'], $value, $expected), false, 'POLLER');
			}

			// fallback values
			if ((!isset($rrd_update_array[$rrd_path]['times'][$unix_time])) && ($rrd_name != '')) {
				$rrd_update_array[$rrd_path]['times'][$unix_time][$rrd_name] = 'U';
			} elseif ((!isset($rrd_update_array[$rrd_path]['times'][$unix_time])) && ($rrd_name == '')) {
				unset($rrd_update_array[$rrd_path]);
			}
		}

		// make sure each .rrd file has complete data
		$k        = 0;
		$data_ids = [];

		foreach ($results as $item) {
			$unix_time = $item['unix_time'];
			$rrd_path  = $item['rrd_path'];
			$rrd_name  = $item['rrd_name'];

			if (isset($rrd_update_array[$rrd_path]['times'][$unix_time])) {
				/**
				 * Check to see if we have partial data sources.  If so
				 * we did not get a full update, so we should not be removing
				 * those data sources from the $rrd_update_array yet.
				 */
				if ($item['rrd_num'] <= cacti_sizeof($rrd_update_array[$rrd_path]['times'][$unix_time])) {
					$data_ids[] = $item['local_data_id'];
					$k++;

					if ($k % 10000 == 0) {
						db_execute('DELETE FROM poller_output WHERE local_data_id IN (' . implode(',', $data_ids) . ')');
						$data_ids = [];
						$k        = 0;
					}
				} else {
					unset($rrd_update_array[$rrd_path]['times'][$unix_time]);
				}
			}
		}

		if ($k > 0) {
			db_execute('DELETE FROM poller_output WHERE local_data_id IN (' . implode(',', $data_ids) . ')');
		}

		// process dsstats information
		dsstats_poller_output($rrd_update_array);
		dsdebug_poller_output($rrd_update_array);

		api_plugin_hook_function('poller_output', $rrd_update_array);

		if (boost_poller_on_demand($results)) {
			$rrds_processed = rrdtool_function_update($rrd_update_array, $rrdtool_pipe);
		}

		$results          = null;
		$rrd_update_array = null;

		// to much records in poller_output, process in chunks
		$rows = db_fetch_cell('SELECT COUNT(local_data_id)
			FROM poller_output');

		// to much records in poller_output, process in chunks
		if ($rows && $remainder == $max_rows) {
			$running = db_fetch_cell('SELECT COUNT(*)
				FROM poller_time
				WHERE end_time = "0000-00-00"');

			$rrds_processed += process_poller_output($rrdtool_pipe, $rows < $max_rows ? $rows : $max_rows);

			if ($running == 0 && !$checked_bad) {
				// Remove recently deleted items from the poller_output table
				db_execute('DELETE FROM poller_output WHERE local_data_id NOT IN (SELECT id FROM data_local)');

				// Identify data sources that are somehow not aligned
				$items = db_fetch_assoc('SELECT rrd_num,
					COUNT(DISTINCT po.local_data_id, po.rrd_name) AS ids, dt.name, dl.host_id,
					GROUP_CONCAT(DISTINCT po.local_data_id) AS local_data_ids
					FROM poller_output AS po
					LEFT JOIN poller_item AS pi
					ON po.local_data_id = pi.local_data_id
					LEFT JOIN data_local AS dl
					ON po.local_data_id = dl.id
					LEFT JOIN data_template AS dt
					ON dl.data_template_id = dt.id
					GROUP BY po.local_data_id
					HAVING rrd_num IS NULL OR rrd_num != ids
					ORDER BY dt.name');

				if (cacti_sizeof($items)) {
					cacti_log(sprintf('WARNING: There are %s Data Sources not returning all data leaving rows in the poller output table.  Details to follow.', cacti_sizeof($items)), false, 'POLLER');
					$prevName = '';

					foreach ($items as $item) {
						if ($prevName != $item['name']) {
							cacti_log(sprintf('WARNING: Data Template \'%s\' is impacted by lack of complete information', $item['name']), false, 'POLLER');
							$prevName = $item['name'];

							db_execute('DELETE FROM poller_output WHERE local_data_id IN(' . $item['local_data_ids'] . ')');
						}
					}
				}

				$checked_bad = true;
			}
		}
	}

	return $rrds_processed;
}

/**
 * update_resource_cache - place the cacti website in the poller_resource_cache
 * for remote pollers to consume
 *
 * @param int $poller_id The id of the poller.  1 is the main system
 *
 * @return bool
 */
function update_resource_cache($poller_id = 1) : bool {
	global $remote_db_cnn_id;

	if (CACTI_SERVER_OS == 'win32') {
		return false;
	}

	if ($poller_id == 1) {
		$db_conn = false;
	} else {
		$db_conn = $remote_db_cnn_id;
	}

	$mpath = CACTI_PATH_BASE;
	$spath = CACTI_PATH_SCRIPTS;
	$rpath = CACTI_PATH_RESOURCE;

	$excluded_extensions = ['tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo'];
	$excluded_dirs       = ['.git', 'log', '.gitattributes', '.github'];

	$paths = [
		'base'     => ['recursive' => false, 'path' => $mpath],
		'scripts'  => ['recursive' => true,  'path' => $spath],
		'resource' => ['recursive' => true,  'path' => $rpath],
		'lib'      => ['recursive' => true,  'path' => $mpath . '/lib'],
		'include'  => ['recursive' => true,  'path' => $mpath . '/include'],
		'install'  => ['recursive' => true,  'path' => $mpath . '/install'],
		'formats'  => ['recursive' => true,  'path' => $mpath . '/formats'],
		'locales'  => ['recursive' => true,  'path' => $mpath . '/locales'],
		'images'   => ['recursive' => true,  'path' => $mpath . '/images'],
		'mibs'     => ['recursive' => true,  'path' => $mpath . '/mibs'],
		'cli'      => ['recursive' => true,  'path' => $mpath . '/cli']
	];

	$pollers = db_fetch_cell('SELECT COUNT(*) FROM poller WHERE disabled=""', '', true, $db_conn);

	if ($poller_id == 1 && $pollers > 1) {
		foreach ($paths as $type => $path) {
			if (is_readable($path['path'])) {
				$pathinfo = pathinfo($path['path']);

				if (isset($pathinfo['extension'])) {
					$extension = cacti_strtolower($pathinfo['extension']);
				} else {
					$extension = '';
				}

				// exclude spurious extensions directories
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
				cacti_log('ERROR: Unable to read the ' . $type . " path '" . $path['path'] . "'", false, 'REPLICATE');
			}
		}

		// handle plugin paths
		$files_and_dirs = array_diff(scandir($mpath . '/plugins'), ['..', '.']);

		if (cacti_sizeof($files_and_dirs)) {
			foreach ($files_and_dirs as $path) {
				if (is_dir($mpath . '/plugins/' . $path)) {
					if (file_exists($mpath . '/plugins/' . $path . '/INFO')) {
						$info            = parse_ini_file($mpath . '/plugins/' . $path . '/INFO', true);
						$dir_exclusions  = ['..', '.', '.git', '.github', '.gitattributes'];
						$file_exclusions = $excluded_extensions;

						if (isset($info['info']['nosync'])) {
							$exclude_paths = explode(',', $info['info']['nosync']);

							if (cacti_sizeof($exclude_paths)) {
								foreach ($exclude_paths as $epath) {
									if (str_contains($epath, '*.')) {
										$file_exclusions[] = trim(str_replace('*.', '', $epath));
									} else {
										$dir_exclusions[]  = trim($epath);
									}
								}
							}
						}

						$fod = array_diff(scandir($mpath . '/plugins/' . $path), $dir_exclusions);

						if (cacti_sizeof($fod)) {
							foreach ($fod as $file_or_dir) {
								$fpath = $mpath . '/plugins/' . $path . '/' . $file_or_dir;

								if (is_dir($fpath)) {
									cache_in_path($fpath, $path . '_' . basename($file_or_dir), true);
								} else {
									$pathinfo = pathinfo($fpath);

									if (isset($pathinfo['extension'])) {
										$extension = cacti_strtolower($pathinfo['extension']);
									} else {
										$extension = '';
									}

									// exclude spurious extensions
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
						cacti_log("WARNING: INFO file does not exist for plugin directory '" . $mpath . '/plugins/' . $path . "'", false, 'REPLICATE');
					}
				} else {
					cache_in_path($mpath . '/plugins/' . $path, 'plugins', false);
				}
			}
		}

		// purge old entries
		$cache = db_fetch_assoc('SELECT path FROM poller_resource_cache');

		if (cacti_sizeof($cache)) {
			foreach ($cache as $item) {
				if (!file_exists($item['path'])) {
					db_execute_prepared('DELETE FROM poller_resource_cache
						WHERE `path` = ?',
						[$item['path']]);
				}
			}
		}
	} elseif ($poller_id > 1 && CACTI_CONNECTION == 'online') {
		if (read_config_option('disable_cache_replication') == 'on') {
			cacti_log('NOTE: Resource Cache Replication is currently Disabled!  Skipping Replication.', true, 'REPLICATE');

			return false;
		}

		$paths['plugins'] = ['recursive' => true, 'path' => $mpath . '/plugins'];
		$plugin_paths     = db_fetch_assoc('SELECT resource_type, `path`
			FROM poller_resource_cache
			WHERE `path` LIKE "plugins/%"', true, $db_conn);

		if (cacti_sizeof($plugin_paths)) {
			foreach ($plugin_paths as $path) {
				$paths[$path['resource_type']] = ['recursive' => false, 'path' => dirname($mpath . '/' . $path['path'])];
			}
		}

		foreach ($paths as $type => $path) {
			if (!file_exists($path['path'])) {
				cacti_log('INFO: Attempting to create directory \'' . $path['path'] . '\'', false, 'REPLICATE');
				@mkdir($path['path'], 0755, true);
			}

			if (is_writable($path['path'])) {
				resource_cache_out($type, $path);
			} else {
				cacti_log('FATAL: Unable to write to the ' . $type . " path '" . $path['path'] . "'", false, 'REPLICATE');
			}
		}
	}

	return true;
}

/**
 * cache_in_path - check to see if the directory in question has changed.
 * If so, send its data into the resource cache table
 *
 * @param string $path      The path to look for changes
 * @param string $type      The patch types being cached
 * @param bool   $recursive Should the path be scanned recursively
 *
 * @return void
 */
function cache_in_path(string $path, string $type, bool $recursive = true) : void {
	if (is_dir($path)) {
		$curr_md5      = md5sum_path($path, $recursive);
		$settings_path = "md5dirsum_$type";
		$last_md5      = read_config_option($settings_path);

		if (empty($last_md5) || $last_md5 != $curr_md5) {
			cacti_log('Type:' . $type . ', Path:' . $path . ', Last MD5:' . $last_md5 . ', Curr MD5:' . $curr_md5, false, 'REPLICATE', POLLER_VERBOSITY_MEDIUM);
			cacti_log("NOTE: Detecting Resource Change.  Updating Resource Cache for '$path'", false, 'REPLICATE');
			update_db_from_path($path, $type, $recursive);
		}

		set_config_option($settings_path, $curr_md5);
	} else {
		$spath               = ltrim(trim(str_replace(CACTI_PATH_BASE, '', $path), '/ \\'), '/ \\');
		$excluded_extensions = ['tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo'];
		$excluded_dirs_files = ['.git', '.travis.yml', '.gitattributes', '.github'];
		$pathinfo            = pathinfo($path);

		if (isset($pathinfo['extension'])) {
			$extension = cacti_strtolower($pathinfo['extension']);
		} else {
			$extension = '';
		}

		// exclude spurious extensions directories
		$exclude = false;

		if (array_search($extension, $excluded_extensions, true) !== false) {
			$exclude = true;
		}

		if (basename($path) == 'config.php' && str_contains($path, 'plugins')) {
			// Allow replication of plugin based config.php files
			$exclude = false;
		} elseif (basename($path) == 'config_local.php') {
			// Don't cache a plugin config_local.php
			$exclude = true;
		} elseif (basename($path) == 'config.php') {
			// Don't cache Cacti's config.php
			$exclude = true;
		} elseif (array_search(basename($path), $excluded_dirs_files, true) !== false) {
			$exclude = true;
		}

		// exclude spurious extensions
		if (!$exclude) {
			$curr_md5 = md5_file($path);
			$last_md5 = db_fetch_cell_prepared('SELECT md5sum FROM poller_resource_cache WHERE path = ?', [$spath]);

			if (str_starts_with($spath, 'plugins/')) {
				$ppath = CACTI_PATH_BASE . '/' . $spath;
			} else {
				$ppath = $spath;
			}

			if (empty($last_md5) || $last_md5 != $curr_md5) {
				cacti_log("NOTE: Detecting Resource Change.  Updating Resource Cache for '$ppath'", false, 'REPLICATE');
				update_db_from_path($path, $type, $recursive);
			}
		}
	}
}

/**
 * update_db_from_path - store the actual file in the databases resource cache.
 * Skip the include/config.php if it exists
 *
 * @param string $path      The path to look for changes
 * @param string $type      The patch types being cached
 * @param bool   $recursive Should the path be scanned recursively
 *
 * @return void
 */
function update_db_from_path(string $path, string $type, bool $recursive = true) : void {
	$excluded_extensions = ['tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo'];

	if (is_dir($path)) {
		$pobject = dir($path);

		while (($entry = $pobject->read()) !== false) {
			if (!should_ignore_from_replication($entry)) {
				$spath = ltrim(trim(str_replace(CACTI_PATH_BASE, '', $path), '/ \\') . '/' . $entry, '/ \\');

				if (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
					if ($recursive) {
						update_db_from_path($path . DIRECTORY_SEPARATOR . $entry, $type, $recursive);
					}
				} elseif (basename($spath) == 'config.php' && !str_contains($path, 'plugins')) {
					// Don't cache Cacti's config.php
					continue;
				} elseif (basename($path) == '.travis.yml') {
					continue;
				} else {
					$pathinfo = pathinfo($entry);

					if (isset($pathinfo['extension'])) {
						$extension = cacti_strtolower($pathinfo['extension']);
					} else {
						$extension = '';
					}

					// exclude spurious extensions
					if (array_search($extension, $excluded_extensions, true) !== false) {
						continue;
					}

					$entry_path = $path . DIRECTORY_SEPARATOR . $entry;
					$attributes = fileperms($entry_path);
					$attributes = empty($attributes) ? 33188 : $attributes;

					$save         = [];
					$save['path'] = $spath;
					$save['id']   = db_fetch_cell_prepared('SELECT id
						FROM poller_resource_cache
						WHERE `path` = ?',
						[$save['path']]);

					$save['resource_type'] = $type;
					$save['md5sum']        = md5_file($entry_path);
					$save['update_time']   = date('Y-m-d H:i:s');
					$save['attributes']    = $attributes;
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
				$extension = cacti_strtolower($pathinfo['extension']);
			} else {
				$extension = '';
			}

			// exclude spurious extensions
			if (array_search($extension, $excluded_extensions, true) === false) {
				$spath = ltrim(trim(str_replace(CACTI_PATH_BASE, '', $path), '/ \\'), '/ \\');

				$attributes = fileperms($path);
				$attributes = empty($attributes) ? 33188 : $attributes;

				$save         = [];
				$save['path'] = $spath;

				$save['id']   = db_fetch_cell_prepared('SELECT id
					FROM poller_resource_cache
					WHERE `path` = ?',
					[$save['path']]);

				$save['resource_type'] = $type;
				$save['md5sum']        = md5_file($path);
				$save['update_time']   = date('Y-m-d H:i:s');
				$save['attributes']    = $attributes;
				$save['contents']      = base64_encode(file_get_contents($path));

				sql_save($save, 'poller_resource_cache');
			}
		}
	}
}

/**
 * resource_cache_out - push the cache from the cacti database to the
 * remote database.  Check PHP files for errors before placing
 * them on the remote pollers file system.
 *
 * @param string $type The path type being cached
 * @param array  $path The path to store the contents
 *
 * @return void
 */
function resource_cache_out(string $type, array $path) : void {
	global $remote_db_cnn_id;

	$settings_path = "md5dirsum_$type";
	$php_path      = read_config_option('path_php_binary');
	$last_md5      = read_config_option($settings_path);
	$curr_md5      = md5sum_path($path['path'], $path['recursive']);

	if (empty($last_md5) || $last_md5 != $curr_md5) {
		$entries = db_fetch_assoc_prepared('SELECT id, `path`, md5sum, attributes
			FROM poller_resource_cache
			WHERE resource_type = ?',
			[$type], true, $remote_db_cnn_id);

		if (cacti_sizeof($entries)) {
			foreach ($entries as $e) {
				if (should_ignore_from_replication($e['path'])) {
					continue;
				}

				$mypath = CACTI_PATH_BASE . DIRECTORY_SEPARATOR . $e['path'];

				if (file_exists($mypath)) {
					$md5sum = md5_file($mypath);
				} else {
					$md5sum = '';
				}

				if (!is_dir(dirname($mypath))) {
					$relative_dir = str_replace(CACTI_PATH_BASE, '', dirname($mypath));
					mkdir('./' . $relative_dir, 0755, true);
				}

				if (is_dir(dirname($mypath))) {
					if ($md5sum != $e['md5sum'] && $e['path'] != 'include/config.php') {
						// If for some reason, the attributes are empty, assume 0644
						$attributes = empty($e['attributes']) ? 33188 : $e['attributes'];

						$extension = substr(strrchr($e['path'], '.'), 1);
						$exit      = -1;
						$contents  = base64_decode(db_fetch_cell_prepared('SELECT contents
							FROM poller_resource_cache
							WHERE id = ?',
							[$e['id']], '', true, $remote_db_cnn_id), true);

						// if the file type is PHP check syntax
						if ($extension == 'php' && $contents != '') {
							// Executable check
							$executable = false;

							if (!str_contains($e['path'], 'lib/poller.php')) {
								if (str_contains($contents, '#!/usr/bin/env php')) {
									$executable = true;
								} elseif (str_contains($contents, '#!/usr/bin/php')) {
									$executable = true;
								}
							}

							$tmpdir  = sys_get_temp_dir();
							$tmpfile = tempnam($tmpdir,'ccp');

							if ((is_writable($tmpdir) && !file_exists($tmpfile)) || (file_exists($tmpfile) && is_writable($tmpfile))) {
								if (file_put_contents($tmpfile, $contents) !== false) {
									$output = system($php_path . ' -l ' . $tmpfile, $exit);

									if ($exit == 0) {
										cacti_log("INFO: Updating '$mypath' from Cache!", false, 'REPLICATE');

										if (is_writable($mypath) || (!file_exists($mypath) && is_writable(dirname($mypath)))) {
											file_put_contents($mypath, $contents);

											if ($executable) {
												$attributes = 33261;
											}

											chmod($mypath, $attributes);

											if (fileperms($mypath) != $attributes) {
												cacti_log("WARNING: Cache cannot update permissions on '$mypath'", false, 'REPLICATE');
											}
										} else {
											cacti_log("ERROR: Cache in cannot write to '$mypath', purge this location", false, 'REPLICATE');
										}
									} else {
										cacti_log("ERROR: PHP Source File '$mypath' from Cache has an error while checking syntax ($exit) while executing: '$php_path -l $tmpfile'", false, 'REPLICATE');
										cacti_log("ERROR: PHP Source File '$mypath'': " . str_replace("\n", ' ', str_replace("\t", ' ', $output)), false, 'REPLICATE');
									}

									unlink($tmpfile);
								} else {
									cacti_log("ERROR: Unable to write file '" . $tmpfile . "' for PHP Syntax verification", false, 'REPLICATE');
								}
							} else {
								cacti_log("ERROR: Cache in cannot write to '" . $tmpfile . "', purge this location", false, 'REPLICATE');
							}
						} elseif (is_writable($mypath) || (!file_exists($mypath) && is_writable(dirname($mypath)))) {
							cacti_log("INFO: Updating '" . $mypath . "' from Cache!", false, 'REPLICATE');

							file_put_contents($mypath, $contents);

							if ($attributes != 0) {
								chmod($mypath, $attributes);

								if (fileperms($mypath) != $attributes) {
									cacti_log("WARNING: Cache cannot update permissions on '$mypath'", false, 'REPLICATE');
								}
							}
						} else {
							cacti_log("ERROR: Cache in cannot write to '" . $mypath . "', purge this location", false, 'REPLICATE');
						}
					}
				} else {
					cacti_log("ERROR: Directory does not exist '" . dirname($mypath) . "'", false, 'REPLICATE');
				}
			}
		}
	}
}

/**
 * md5sum_path - get a recursive md5sum on an entire directory.
 *
 * @param string $path      The path to check for the md5sum
 * @param bool   $recursive The path should be verified recursively
 *
 * @return mixed
 */
function md5sum_path(string $path, bool $recursive = true) : mixed {
	if (!is_dir($path)) {
		return false;
	}

	$filemd5s = [];
	$pobject  = dir($path);

	$excluded_extensions = ['tar', 'gz', 'zip', 'tgz', 'ttf', 'z', 'exe', 'pack', 'swp', 'swo'];

	while (($entry = $pobject->read()) !== false) {
		if (!should_ignore_from_replication($entry)) {
			$pathinfo = pathinfo($entry);

			if (isset($pathinfo['extension'])) {
				$extension = cacti_strtolower($pathinfo['extension']);
			} else {
				$extension = '';
			}

			// exclude spurious extensions
			if (array_search($extension, $excluded_extensions, true) !== false) {
				continue;
			}

			if (is_dir($path . DIRECTORY_SEPARATOR . $entry) && $recursive) {
				$filemd5s[] = md5sum_path($path . DIRECTORY_SEPARATOR . $entry, $recursive);
			} elseif (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
				// Ignore directories who are not recursive
			} elseif (is_readable($path . DIRECTORY_SEPARATOR . $entry)) {
				$filemd5s[] = md5_file($path . DIRECTORY_SEPARATOR . $entry);
			} else {
				cacti_log('WARNING: Unable to read file \'' . $path . DIRECTORY_SEPARATOR . $entry . '\' into Cacti resource cache.', false, 'REPLICATE');
			}
		}
	}

	$pobject->close();

	return md5(implode('', $filemd5s));
}

/**
 * poller_push_to_remote_db_connect - given the device or poller_id connect
 * to the data collector.
 *
 * @param int  $device_or_poller The id of the object
 * @param bool $is_poller        Don't let cacti guess, the id is a poller
 *
 * @return mixed The connection or false when the connection fails
 */
function poller_push_to_remote_db_connect(int $device_or_poller, bool $is_poller = false) : mixed {
	static $device_poller_ids = [];

	$rcnn_id = false;

	if ($is_poller) {
		$poller_id = $device_or_poller;
	} elseif (!isset($device_poller_ids[$device_or_poller])) {
		$poller_id = db_fetch_cell_prepared('SELECT poller_id
			FROM host
			WHERE id = ?',
			[$device_or_poller]);

		if (!empty($poller_id)) {
			$device_poller_ids[$device_or_poller] = $poller_id;
		} elseif (POLLER_ID > 1) {
			$poller_id = POLLER_ID;

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

/**
 * poller_connect_to_remote - this function connects to the remote
 * data collector and returns either false or the connection
 * resource.
 *
 * @param int $poller_id The remote poller id
 *
 * @return mixed The connection or false when the connection fails
 */
function poller_connect_to_remote(int $poller_id) : mixed {
	global $local_db_cnn_id;

	if ($poller_id == 1) {
		return false;
	}

	if (POLLER_ID > 1) {
		if ($poller_id === POLLER_ID) {
			return $local_db_cnn_id;
		}
	}

	if (POLLER_ID == 1) {
		$cinfo = db_fetch_row_prepared('SELECT *
			FROM poller
			WHERE id = ?',
			[$poller_id]);

		if (!cacti_sizeof($cinfo)) {
			cacti_log('ERROR: Remote Data Collector ID[' . $poller_id . '] to be Sync\'d does not exist!', false, 'REPLICATE');
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
			$cinfo['dbretries'],
			$cinfo['dbssl'],
			$cinfo['dbsslkey'],
			$cinfo['dbsslcert'],
			$cinfo['dbsslca'],
			$cinfo['dbsslcapath'],
			$cinfo['dbsslverifyservercert']);

		if (!is_object($rcnn_id)) {
			cacti_log('ERROR: Unable to connect to Remote Data Collector ' . $cinfo['name'], false, 'REPLICATE');
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

/**
 * replicate_out - this function sends table changes from the resource
 * cache to the remote database.  This happens as a result of a full
 * sync within Cacti.
 *
 * @param int    $remote_poller_id The poller to send data to
 * @param string $class            The class of data to push to the poller
 *
 * @return bool
 */
function replicate_out(int $remote_poller_id = 1, string $class = 'all') : bool {
	replicate_log('Attempting to replicate to Poller ' . $remote_poller_id);

	$rcnn_id = poller_connect_to_remote($remote_poller_id);

	if ($rcnn_id === false) {
		replicate_log('Failed to connect to Poller ' . $remote_poller_id . ' Database');

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
		$data = db_fetch_assoc('SELECT * FROM automation_graph_rule_items');
		replicate_out_table($rcnn_id, $data, 'automation_graph_rule_items', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_graph_rules');
		replicate_out_table($rcnn_id, $data, 'automation_graph_rules', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_match_rule_items');
		replicate_out_table($rcnn_id, $data, 'automation_match_rule_items', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_snmp');
		replicate_out_table($rcnn_id, $data, 'automation_snmp', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_snmp_items');
		replicate_out_table($rcnn_id, $data, 'automation_snmp_items', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_templates');
		replicate_out_table($rcnn_id, $data, 'automation_templates', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_tree_rule_items');
		replicate_out_table($rcnn_id, $data, 'automation_tree_rule_items', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM automation_tree_rules');
		replicate_out_table($rcnn_id, $data, 'automation_tree_rules', $remote_poller_id);

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

	// Plugin tables
	if ($class == 'all' || $class == 'plugins') {
		$data = db_fetch_assoc('SELECT * FROM plugin_config');
		replicate_out_table($rcnn_id, $data, 'plugin_config', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM plugin_hooks');
		replicate_out_table($rcnn_id, $data, 'plugin_hooks', $remote_poller_id);

		$data = db_fetch_assoc('SELECT * FROM plugin_realms');
		replicate_out_table($rcnn_id, $data, 'plugin_realms', $remote_poller_id);
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

		$data = db_fetch_assoc('SELECT * FROM user_auth_row_cache');
		replicate_out_table($rcnn_id, $data, 'user_auth_row_cache', $remote_poller_id);

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
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'host_snmp_query', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT pc.*
			FROM poller_command AS pc
			WHERE pc.poller_id = ?',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'poller_command', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT h.*
			FROM host AS h
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'host', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT h.*
			FROM host_errors AS h
			WHERE h.poller_id = ?',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'host_errors', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT hsc.*
			FROM host_snmp_cache AS hsc
			INNER JOIN host AS h
			ON h.id=hsc.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'host_snmp_cache', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT hvc.*
			FROM host_value_cache AS hvc
			INNER JOIN host AS h
			ON h.id = hvc.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""
			UNION
			SELECT 0, "dummy", 0, 30, NOW()',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'host_value_cache', $remote_poller_id);

		// Special class of 'update' for the table following tables 'poller_reindex', 'poller_item'
		$data = db_fetch_assoc_prepared('SELECT pri.*
			FROM poller_reindex AS pri
			INNER JOIN host AS h
			ON h.id=pri.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'poller_reindex', $remote_poller_id, false, ['assert_value']);

		// Since we are doing an update, remove stale data
		db_execute('DELETE pr
			FROM poller_reindex AS pr
			LEFT JOIN host_snmp_query AS hsq
			ON pr.host_id = hsq.host_id
			AND pr.data_query_id = hsq.snmp_query_id
			WHERE hsq.host_id IS NULL', false, $rcnn_id);

		$data = db_fetch_assoc_prepared('SELECT pi.*
			FROM poller_item AS pi
			WHERE pi.poller_id = ?',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'poller_item', $remote_poller_id, false, ['last_updated']);

		// Remove anything not updated recently
		$time = db_fetch_cell('SELECT MAX(UNIX_TIMESTAMP(last_updated)) FROM poller_item', '', false, $rcnn_id);

		if (!empty($time)) {
			// You must update the last_updated locally
			db_execute_prepared('UPDATE poller_item
				SET last_updated = FROM_UNIXTIME(?)
				WHERE poller_id = ?',
				[$time, $remote_poller_id]);

			db_execute_prepared("DELETE FROM poller_item
				WHERE last_updated < ?
				AND last_updated > '0000-00-00'
				AND last_updated NOT NULL",
				[date('Y-m-d H:i:s', $time)], false, $rcnn_id);
		}

		$data = db_fetch_assoc_prepared('SELECT dl.*
			FROM data_local AS dl
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'data_local', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT gl.*
			FROM graph_local AS gl
			INNER JOIN host AS h
			ON h.id=gl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'graph_local', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT dtd.*
			FROM data_template_data AS dtd
			INNER JOIN data_local AS dl
			ON dtd.local_data_id=dl.id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'data_template_data', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT dtr.*
			FROM data_template_rrd AS dtr
			INNER JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'data_template_rrd', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT gti.*
			FROM graph_templates_item AS gti
			INNER JOIN graph_local AS gl
			ON gti.local_graph_id=gl.id
			INNER JOIN host AS h
			ON h.id=gl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'graph_templates_item', $remote_poller_id);

		$data = db_fetch_assoc_prepared('SELECT did.*
			FROM data_input_data AS did
			INNER JOIN data_template_data AS dtd
			ON did.data_template_data_id=dtd.id
			INNER JOIN data_local AS dl
			ON dl.id=dtd.local_data_id
			INNER JOIN host AS h
			ON h.id=dl.host_id
			WHERE h.poller_id = ?
			AND h.deleted = ""',
			[$remote_poller_id]);
		replicate_out_table($rcnn_id, $data, 'data_input_data', $remote_poller_id);
	}

	if ($class == 'all') {
		api_plugin_hook_function('replicate_out', ['remote_poller_id' => $remote_poller_id, 'rcnn_id' => $rcnn_id, 'class' => $class]);
	}

	$stats = db_fetch_row_prepared('SELECT
		SUM(CASE WHEN action=0 THEN 1 ELSE 0 END) AS snmp,
		SUM(CASE WHEN action=1 THEN 1 ELSE 0 END) AS script,
		SUM(CASE WHEN action=2 THEN 1 ELSE 0 END) AS server
		FROM poller_item
		WHERE poller_id = ?',
		[$remote_poller_id]);

	if (cacti_sizeof($stats)) {
		db_execute_prepared('UPDATE poller
			SET snmp = ?, script = ?, server = ?
			WHERE id = ?',
			[$stats['snmp'], $stats['script'], $stats['server'], $remote_poller_id]);
	}

	if ($class != 'plugins' && CACTI_WEB) {
		replicate_log('Synchronization of Poller ' . $remote_poller_id . ' completed', POLLER_VERBOSITY_LOW);
		raise_message('poller_sync');
	}

	return true;
}

/**
 * replicate_out_table - replicate out an entire table to
 * remote database.  Optionally, performs update rather
 * than a truncate and excluding columns from the on duplicate
 * clause. By default, the remote table will be recreated
 * if it's table structure does not match the main table.
 *
 * @param mixed  $db_conn          to remote database
 * @param array  $data             Associative array of the table data
 * @param string $table            The remote table to replicate to
 * @param int    $remote_poller_id The remote data collector's id
 * @param bool   $truncate         A flag that if true, truncates, otherwise updates
 * @param mixed  $exclude          An array of column names to not update on replication
 * @param int    $level            The logging level
 *
 * @return void
 */
function replicate_out_table(mixed $db_conn, array &$data, string $table, int $remote_poller_id, bool $truncate = true,
	mixed $exclude = false, int $level = POLLER_VERBOSITY_NONE) : void {
	if (cacti_sizeof($data)) {
		if (!db_table_exists($table)) {
			cacti_log('WARNING: Unable to replicate table ' . $table . ' because it does not exist locally', false, 'REPLICATE');

			return;
		}

		// check if the table structure changed, and if so, recreate
		$local_columns  = db_fetch_assoc('SHOW COLUMNS FROM ' . $table);
		$remote_columns = db_fetch_assoc('SHOW COLUMNS FROM ' . $table, false, $db_conn);
		$remote_rows    = db_fetch_cell('SELECT COUNT(*) FROM ' . $table, '', false, $db_conn);

		if ($exclude !== false) {
			if (!is_array($exclude)) {
				$exclude = [$exclude];
			}
		}

		if ($remote_rows == 0) {
			$exclude = false;
		}

		if (cacti_sizeof($local_columns) != cacti_sizeof($remote_columns)) {
			replicate_log('NOTE: Replicate Out Detected a Table Structure Change for ' . $table, $level);
			$create = db_fetch_row('SHOW CREATE TABLE ' . $table);

			if (isset($create["CREATE TABLE `$table`"]) || isset($create['Create Table'])) {
				replicate_log('NOTE: Replication Recreating Remote Table Structure for ' . $table, $level);
				db_execute('DROP TABLE IF EXISTS ' . $table, true, $db_conn);

				if (isset($create["CREATE TABLE `$table`"])) {
					db_execute($create["CREATE TABLE `$table`"], true, $db_conn);
				} else {
					db_execute($create['Create Table'], true, $db_conn);
				}
			}
		}

		if ($truncate) {
			$prefix = "REPLACE INTO $table (";
			$suffix = '';

			db_execute("TRUNCATE TABLE $table", true, $db_conn);
		} else {
			$prefix = "INSERT INTO $table (";
			$suffix = ' ON DUPLICATE KEY UPDATE ';

			$cols = array_rekey($local_columns, 'Field', 'Field');

			$i = 0;

			foreach ($cols as $col) {
				if ($exclude !== false) {
					if (array_search($col, $exclude, true) === false) {
						$suffix .= ($i > 0 ? ', ' : '') . " `$col`=VALUES($col)";
						$i++;
					}
				} else {
					$suffix .= ($i > 0 ? ', ' : '') . " `$col`=VALUES($col)";
					$i++;
				}
			}
		}

		$sql       = '';
		$colcnt    = 0;
		$rows_done = 0;
		$columns   = array_keys($data[0]);
		$skipcols  = [];

		// Find columns to skip, or exclude from updates
		foreach ($columns as $index => $c) {
			if (!db_column_exists($table, $c, false, $db_conn)) {
				$skipcols[$index] = $c;
			} else {
				$prefix .= ($colcnt > 0 ? ', ' : '') . "`$c`";
				$colcnt++;
			}
		}
		$prefix .= ') VALUES ';

		$rowcnt = 0;

		foreach ($data as $row) {
			$colcnt  = 0;
			$sql_row = '(';

			foreach ($row as $col => $value) {
				if (array_search($col, $skipcols, true) === false) {
					$sql_row .= ($colcnt > 0 ? ', ' : '') . db_qstr($value);
					$colcnt++;
				}
			}
			$sql_row .= ')';
			$sql .= ($rowcnt > 0 ? ', ' : '') . $sql_row;

			$rowcnt++;

			if ($rowcnt > 1000) {
				db_execute($prefix . $sql . $suffix, true, $db_conn);
				$rows_affected = db_affected_rows($db_conn);
				$rows_log      = ((($rows_done % 100000) + $rows_affected) > 100000);
				$rows_done    += $rows_affected;

				if ($rows_log) {
					replicate_log('NOTE: Table ' . $table . ' Replicated to Remote Poller ' . $remote_poller_id . ' With ' . $rows_done . ' Rows Updated', $level);
				}
				$sql    = '';
				$rowcnt = 0;
			}
		}

		if ($rowcnt > 0) {
			db_execute($prefix . $sql . $suffix, true, $db_conn);
			$rows_done += db_affected_rows($db_conn);
		}

		replicate_log('INFO: Table ' . $table . ' Replicated to Remote Poller ' . $remote_poller_id . ' With ' . $rows_done . ' Rows Updated', $level);
	} else {
		if (!db_table_exists($table, false, $db_conn)) {
			replicate_log('NOTE: Replicate Out Detected a missing remote table for ' . $table, $level);

			$create = db_fetch_row('SHOW CREATE TABLE ' . $table);

			if (isset($create["CREATE TABLE `$table`"]) || isset($create['Create Table'])) {
				replicate_log('NOTE: Replication Creating Remote Table Structure for ' . $table, $level);
				db_execute('DROP TABLE IF EXISTS ' . $table, true, $db_conn);

				if (isset($create["CREATE TABLE `$table`"])) {
					db_execute($create["CREATE TABLE `$table`"], true, $db_conn);
				} else {
					db_execute($create['Create Table'], true, $db_conn);
				}
			}
		} else {
			replicate_log('INFO: Table ' . $table . ' Not Replicated to Remote Poller ' . $remote_poller_id . ' Due to No Rows Found', $level);

			db_execute("TRUNCATE TABLE $table", true, $db_conn);
		}
	}
}

function replicate_log(string $text, int $level = POLLER_VERBOSITY_NONE) : void {
	if (defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		log_install_and_file($level, $text, 'REPLICATE', true);
	} elseif ($level != POLLER_VERBOSITY_NONE) {
		cacti_log($text, false, 'REPLICATE', $level);
	}
}

function poller_push_reindex_only_data_to_main(int $device_id, int $data_query_id) : void {
	global $remote_db_cnn_id;

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host
		WHERE id = ?',
		[$device_id]);

	if ($poller_id > 1) {
		$sql_where  = 'WHERE host_id = ' . $device_id . ' AND data_query_id = ' . $data_query_id;

		$poller_reindex = db_fetch_assoc("SELECT *
			FROM poller_reindex
			$sql_where");

		replicate_table_to_poller($remote_db_cnn_id, $poller_reindex, 'poller_reindex');
	}
}

function poller_push_reindex_data_to_poller(int $device_id = 0, int $data_query_id = 0, bool $force = false, mixed $db_conn = false) : void {
	global $remote_db_cnn_id, $local_db_cnn_id, $database_hostname, $rdatabase_hostname;

	// If the hostnames are the same, replication is from main to remote
	if ($db_conn) {
		// We are pushing forcibly to remote
	} elseif (isset($rdatabase_hostname) && $database_hostname == $rdatabase_hostname) {
		$db_conn = $local_db_cnn_id;
	} else {
		$db_conn = $remote_db_cnn_id;
	}

	$sql_params  = [];
	$sql_params1 = [];
	$sql_where   = '';
	$sql_where1  = '';
	$sql_where2  = '';

	if ($device_id > 0) {
		$sql_where .= 'WHERE host_id = ?';
		$sql_where1 .= 'WHERE host_id = ?';

		$sql_params[]  = $device_id;
		$sql_params1[] = $device_id;
	}

	if ($data_query_id > 0) {
		$sql_where .= ($sql_where  != '' ? ' AND' : 'WHERE') . ' snmp_query_id = ?';
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' snmp_query_id = ?';

		$sql_params[]  = $data_query_id;
		$sql_params1[] = $data_query_id;
	}

	// Give the snmp query up to an hour to run
	$min_reindex_cache = db_fetch_cell_prepared("SELECT MIN(UNIX_TIMESTAMP(last_updated)-3600)
		FROM host_snmp_cache
		$sql_where",
		$sql_params);

	if (!$force) {
		$sql_where2    = $sql_where1 . ($sql_where1 != '' ? ' AND' : 'WHERE') . ' UNIX_TIMESTAMP(last_updated) > ?';
		$sql_params2   = $sql_params1;
		$sql_params2[] = $min_reindex_cache;

		$recache_hosts = array_rekey(
			db_fetch_assoc_prepared("SELECT DISTINCT host_id
				FROM host_snmp_cache
				$sql_where2",
				$sql_params2),
			'host_id', 'host_id'
		);
	} else {
		$recache_hosts = array_rekey(
			db_fetch_assoc_prepared("SELECT DISTINCT host_id
				FROM host_snmp_cache
				$sql_where1",
				$sql_params1),
			'host_id', 'host_id'
		);
	}

	if (cacti_sizeof($recache_hosts)) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' host_id IN (' . implode(', ', $recache_hosts) . ')';

		$local_data_ids = db_fetch_assoc_prepared("SELECT *
			FROM data_local
			$sql_where1",
			$sql_params1);

		replicate_table_to_poller($db_conn, $local_data_ids, 'data_local');

		$local_graph_ids = db_fetch_assoc_prepared("SELECT *
			FROM graph_local
			$sql_where1",
			$sql_params1);

		replicate_table_to_poller($db_conn, $local_graph_ids, 'graph_local');

		$host_snmp_cache = db_fetch_assoc_prepared("SELECT *
			FROM host_snmp_cache
			$sql_where1",
			$sql_params1);

		replicate_table_to_poller($db_conn, $host_snmp_cache, 'host_snmp_cache');

		// TODO: Make schema's equivalent renamed snmp_query_id to data_query_id everywhere
		$sql_where1 = str_replace('snmp_query_id', 'data_query_id', $sql_where1);

		$poller_reindex = db_fetch_assoc_prepared("SELECT *
			FROM poller_reindex
			$sql_where1",
			$sql_params1);

		replicate_table_to_poller($db_conn, $poller_reindex, 'poller_reindex', ['assert_value']);
	}
}

function replicate_table_to_poller(mixed $db_conn, array &$data, string $table, mixed $exclude = false) : void {
	$max_packet  = db_fetch_row("SHOW GLOBAL VARIABLES LIKE 'max_allowed_packet'", true, $db_conn);

	if (cacti_sizeof($max_packet)) {
		$max_packet = $max_packet['Value'];
	} else {
		$max_packet = 2500000;
	}

	if (cacti_sizeof($data)) {
		$prefix    = "INSERT INTO $table (";
		$suffix    = ' ON DUPLICATE KEY UPDATE ';
		$sql       = '';
		$colcnt    = 0;
		$rows_done = 0;
		$columns   = array_keys($data[0]);
		$skipcols  = [];

		$remote_rows = db_fetch_cell("SELECT COUNT(*) FROM $table", '', true, $db_conn);

		if ($exclude !== false && !is_array($exclude)) {
			$exclude = [$exclude];
		}

		if ($remote_rows == 0) {
			$exclude = false;
		}

		// Find columns to skip from updates
		foreach ($columns as $index => $c) {
			if (!db_column_exists($table, $c, false, $db_conn)) {
				$skipcols[$index] = $c;
			} elseif ($exclude !== false && array_search($c, $exclude, true) !== false) {
				$skipcols[$index] = $c;
			} else {
				$prefix .= ($colcnt > 0 ? ', ' : '') . $c;
				$suffix .= ($colcnt > 0 ? ', ' : '') . "$c=VALUES($c)";
				$colcnt++;
			}
		}
		$prefix .= ') VALUES ';

		$rowcnt = 0;
		$ohead  = strlen($prefix) + strlen($suffix);
		$sqllen = $ohead;

		foreach ($data as $row) {
			$colcnt  = 0;
			$sql_row = '(';

			foreach ($row as $col => $value) {
				if (array_search($col, $skipcols, true) === false) {
					$sql_row .= ($colcnt > 0 ? ', ' : '') . db_qstr($value);
					$colcnt++;
				}
			}
			$sql_row .= ')';
			$sql .= ($rowcnt > 0 ? ', ' : '') . $sql_row;

			$rowcnt++;
			$sqllen += strlen($sql_row);

			if ($rowcnt > 150000 || ($sqllen + 1000 > $max_packet)) {
				db_execute($prefix . $sql . $suffix, true, $db_conn);
				$rows_done += db_affected_rows($db_conn);
				$sql    = '';
				$rowcnt = 0;
				$sqllen = $ohead;
			}
		}

		if ($rowcnt > 0) {
			db_execute($prefix . $sql . $suffix, true, $db_conn);
			$rows_done += db_affected_rows($db_conn);
		}

		cacti_log('NOTE: Table ' . $table . ' Replicated to Poller With ' . $rows_done . ' Rows Updated', true, 'REPLICATE', POLLER_VERBOSITY_MEDIUM);
	}
}

function poller_recovery_flush_boost(int $poller_id) : void {
	if ($poller_id > 1) {
		if (CACTI_CONNECTION == 'recovery') {
			$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
			$extra_args     = '-q ' . cacti_escapeshellarg(CACTI_PATH_BASE . '/poller_recovery.php');
			exec_background($command_string, $extra_args);
		}
	}
}

function poller_push_data_to_main() : void {
	global $remote_db_cnn_id;

	if (POLLER_ID > 1) {
		$host_records = db_fetch_assoc_prepared('SELECT id, snmp_sysDescr, snmp_sysObjectID,
			snmp_sysUpTimeInstance, snmp_sysContact, snmp_sysName, snmp_sysLocation,
			status, status_event_count, status_fail_date, status_rec_date,
			status_last_error, min_time, max_time, cur_time, avg_time, polling_time,
			total_polls, failed_polls, availability, last_updated
			FROM host
			WHERE poller_id = ?',
			[POLLER_ID]);

		$updates = [
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
		];

		poller_push_table($remote_db_cnn_id, $host_records, 'host', true, $updates);

		$poller_item_records = db_fetch_assoc_prepared('SELECT local_data_id, host_id, rrd_name, rrd_step, rrd_next_step
			FROM poller_item
			WHERE poller_id = ?',
			[POLLER_ID]);

		$updates = [
			'rrd_next_step'
		];

		poller_push_table($remote_db_cnn_id, $poller_item_records, 'poller_item', true, $updates);

		$host_errors = db_fetch_assoc_prepared('SELECT *
			FROM host_errors
			WHERE poller_id = ?',
			[POLLER_ID]);

		$updates = [
			'errors',
			'local_data_ids'
		];

		poller_push_table($remote_db_cnn_id, $host_errors, 'host_errors', true, $updates);
	}
}

function poller_push_table(object $db_cnn, array $records, string $table, bool $ignore = false, array $dupes = []) : int {
	$prefix = 'INSERT ' . ($ignore ? 'IGNORE' : '') . ' INTO ' . $table . ' ';
	$first  = true;
	$dupe   = '';
	$sql    = [];

	if (cacti_sizeof($records)) {
		foreach ($records as $r) {
			if ($first) {
				$prefix .= '(`' . implode('`,`', array_keys($r)) . '`) VALUES ';
				$first = false;
			}

			$string = '(';

			foreach ($r as $c) {
				$string .= ($string == '(' ? '' : ', ') . db_qstr($c);
			}
			$string .= ')';

			$sql[] = $string;
		}

		if (cacti_sizeof($dupes)) {
			$dupe = ' ON DUPLICATE KEY UPDATE ';
			$i    = 0;

			foreach ($dupes as $item) {
				$dupe .= ($i == 0 ? '' : ', ') . "$item=VALUES($item)";
				$i++;
			}
		}

		$sqln = array_chunk($sql, 1000);

		foreach ($sqln as $sql) {
			$osql = implode(',', $sql);
			db_execute($prefix . $osql . $dupe, true, $db_cnn);
		}
	}

	return cacti_sizeof($records);
}

/**
 * remote_poller_up - Given a remote poller id, check if it has responded
 * recently, and if so, return true else return false.
 *
 * @param int $poller_id The remote poller id
 *
 * @return bool True if up else false
 */
function remote_poller_up(int $poller_id) : bool {
	$gone_time = intval(read_config_option('poller_interval')) * 2;

	if (empty($poller_id) || $poller_id <= 1) {
		return false;
	}

	$up_poller = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM poller
		WHERE UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) < ?
		AND id = ?
		AND disabled = ""',
		[$gone_time, $poller_id]);

	if ($up_poller) {
		return true;
	} else {
		return false;
	}
}

function should_ignore_from_replication(string $path) : bool {
	$entry = basename($path);

	return ($entry == '.' || $entry == '..' || $entry == '.git' || $entry == '');
}

function get_remote_poller_ids_from_graphs(mixed $graphs) : array {
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
		return [];
	}
}

function get_remote_poller_ids_from_data_sources(mixed $data_sources) : array {
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
		return [];
	}
}

function get_remote_poller_ids_from_devices(mixed $devices) : array {
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
		return [];
	}
}

/**
 * is_process_running - Check to see if a process is running
 * or has timed out.
 *
 * @param string $tasktype The Task Type
 * @param string $taskname The Task Name
 * @param int    $taskid   The Task ID
 *
 * @return mixed false if not running, true if running, 97 exited but not unregistered, 98 timeout and running, 99 timeout and dead
 */
function is_process_running(string $tasktype, string $taskname, int $taskid = 0) : mixed {
	if (!db_table_exists('processes')) {
		return true;
	}

	$r = db_fetch_row_prepared('SELECT *,
		IF(UNIX_TIMESTAMP(started) + timeout < UNIX_TIMESTAMP(), UNIX_TIMESTAMP(started), 0) AS timeout_exceeded,
		UNIX_TIMESTAMP() AS `current_timestamp`
		FROM processes
		WHERE tasktype = ?
		AND taskname = ?
		AND taskid = ?',
		[$tasktype, $taskname, $taskid]);

	if (!cacti_sizeof($r)) {
		// Process is not registered or running
		return false;
	}

	if ($r['timeout_exceeded']) {
		// Process Timeed Out
		if ($r['pid'] > 0) {
			return 98;
		} else {
			return 99;
		}
	} elseif ($r['pid'] > 0 && posix_kill($r['pid'], 0)) {
		// Process Running and fine
		return true;
	} else {
		// Exited process but not unregistered
		unregister_process($tasktype, $taskname, $taskid);

		return 97;
	}
}

/**
 * register_process_start - public function to register a process
 * in Cacti's process table
 *
 * @param string $tasktype Mandatory task type
 * @param string $taskname Mandatory task name
 * @param int    $taskid   Optional task id
 * @param int    $timeout  Optional timeout
 *
 * @return bool success true if you can start running, else false if
 *              another version is running and has not ended.
 */
function register_process_start(string $tasktype, string $taskname, int $taskid = 0, int $timeout = 300) : bool {
	$pid = getmypid();

	if (!db_table_exists('processes')) {
		return true;
	}

	$r = db_fetch_row_prepared('SELECT *,
		IF(UNIX_TIMESTAMP(started) + timeout < UNIX_TIMESTAMP(), UNIX_TIMESTAMP(started), 0) AS timeout_exceeded,
		UNIX_TIMESTAMP() AS `current_timestamp`
		FROM processes
		WHERE tasktype = ?
		AND taskname = ?
		AND taskid = ?',
		[$tasktype, $taskname, $taskid]);

	if (!cacti_sizeof($r)) {
		cacti_log(sprintf('NOTE: Registering process! (%s, %s, %s, %s)', $tasktype, $taskname, $taskid, $pid), false, 'POLLER', POLLER_VERBOSITY_MEDIUM);

		register_process($tasktype, $taskname, $taskid, $pid, $timeout);
	} elseif ($r['timeout_exceeded']) {
		if ($r['pid'] > 0) {
			cacti_log(sprintf('ERROR: Process being killed due to timeout! (%s, %s, %s, Process %s, Time %s, Timeout %s, Timestamp %s)', $tasktype, $taskname, $taskid, $r['pid'], $r['timeout_exceeded'], $r['timeout'], $r['current_timestamp']), false, 'POLLER');

			posix_kill($r['pid'], SIGTERM);

			unregister_process($tasktype, $taskname, $taskid);
			register_process($tasktype, $taskname, $taskid, $pid, $timeout);
		} else {
			// Should never be reached
			cacti_log(sprintf('ERROR: Failed registering process.  Invalid pid found.  Unable to kill! (%s, %s, %s, %s)', $tasktype, $taskname, $taskid, $r['pid']), false, 'POLLER');

			return false;
		}
	} elseif ($r['pid'] > 0 && posix_kill($r['pid'], 0)) {
		cacti_log(sprintf('NOTE: Failed registering process.  Old process still running and has not timed out! (%s, %s, %s, %s)', $tasktype, $taskname, $taskid, $pid), false, 'POLLER', POLLER_VERBOSITY_MEDIUM);

		return false;
	} else {
		cacti_log(sprintf('WARNING: Detected process that is exited and did not unregister first! (%s, %s, %s, %s)', $tasktype, $taskname, $taskid, $pid), false, 'POLLER');

		unregister_process($tasktype, $taskname, $taskid);
		register_process($tasktype, $taskname, $taskid, $pid, $timeout);
	}

	return true;
}

/**
 * register_process - register a process in Cacti's process table
 *
 * @param string $tasktype Mandatory task type
 * @param string $taskname Mandatory task name
 * @param int    $taskid   Mandatory task id
 * @param int    $pid      Mandatory pid
 * @param int    $timeout  Mandatory timeout
 *
 * @return bool
 */
function register_process(string $tasktype, string $taskname, int $taskid, int $pid, int $timeout) : bool {
	if (!db_table_exists('processes')) {
		return true;
	}

	db_execute_prepared('INSERT INTO processes (tasktype, taskname, taskid, pid, timeout, started, last_update)
		VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
		[$tasktype, $taskname, $taskid, $pid, $timeout]);

	return true;
}

/**
 * unregister_process - remove a process from Cacti's process table
 *
 * @param string $tasktype Mandatory task type
 * @param string $taskname Mandatory task name
 * @param int    $taskid   Optional task id
 * @param int    $pid      Optional task pid
 *
 * @return bool
 */
function unregister_process(string $tasktype, string $taskname, int $taskid = 0, int $pid = -1) : bool {
	if (!db_table_exists('processes')) {
		return true;
	}

	if ($pid == -1) {
		db_execute_prepared('DELETE FROM processes
			WHERE tasktype = ?
			AND taskname = ?
			AND taskid = ?',
			[$tasktype, $taskname, $taskid]);
	} else {
		db_execute_prepared('DELETE FROM processes
			WHERE tasktype = ?
			AND taskname = ?
			AND taskid = ?
			AND pid = ?',
			[$tasktype, $taskname, $taskid, $pid]);
	}

	return true;
}

/**
 * heartbeat_process - update the process table last_update timestamp
 *
 * @param string $tasktype Mandatory task type
 * @param string $taskname Mandatory task name
 * @param int    $taskid   Optional task id
 *
 * @return bool
 */
function heartbeat_process(string $tasktype, string $taskname, int $taskid = 0) : bool {
	if (!db_table_exists('processes')) {
		return true;
	}

	db_execute_prepared('UPDATE processes
		SET last_update = NOW()
		WHERE tasktype = ?
		AND taskname = ?
		AND taskid = ?',
		[$tasktype, $taskname, $taskid]);

	return true;
}

/**
 * timeout_kill_registered_processes - allow a Cacti plugin or scheduled task to
 * be bulk cleaned.
 *
 * @param string $tasktype Optional task type
 * @param string $taskname Optional task name
 * @param int    $taskid   Optional task id
 * @param int    $pid      Optional pid
 *
 * @return bool
 */
function timeout_kill_registered_processes(string $tasktype = '', string $taskname = '', int $taskid = 0, int $pid = -1) : bool {
	if (!db_table_exists('processes')) {
		return true;
	}

	$sql_where = '';
	$params    = [];

	if ($tasktype != '') {
		$sql_where .= ' AND tasktype = ?';
		$params[] = $tasktype;
	}

	if ($taskname != '') {
		$sql_where .= ' AND taskname = ?';
		$params[] = $taskname;
	}

	if ($taskid != 0) {
		$sql_where .= ' AND taskid = ?';
		$params[] = $taskid;
	}

	if ($pid != -1) {
		$sql_where .= ' AND pid = ?';
		$params[] = $pid;
	}

	$processes = db_fetch_assoc_prepared("SELECT *
		FROM processes
		WHERE UNIX_TIMESTAMP() > FROM_UNIXTIME(started) + timeout
		$sql_where", $params);

	if (cacti_sizeof($processes)) {
		foreach ($processes as $r) {
			if ($r['pid'] > 0 && posix_kill($r['pid'], 0)) {
				cacti_log(sprintf('ERROR: Process killed due to timeout! (%s, %s, %s, %s)', $r['tasktype'], $r['taskname'], $r['taskid'], $r['pid']), false, 'POLLER');
				posix_kill($r['pid'], SIGTERM);
			} else {
				cacti_log(sprintf('ERROR: Detected process that is gone and did not unregister first! (%s, %s, %s, %s)', $r['tasktype'], $r['taskname'], $r['taskid'], $r['pid']), false, 'POLLER');
			}

			unregister_process($r['tasktype'], $r['taskname'], $r['taskid'], $r['pid']);
		}
	}

	return true;
}
