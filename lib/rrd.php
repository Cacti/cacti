<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

define('RRD_NL', " \\\n");
define('MAX_FETCH_CACHE_SIZE', 5);

if(read_config_option('storage_location')) {
	/* load crypt libraries only if the Cacti RRDtool Proxy Server is in use */
	set_include_path($config['include_path'] . '/phpseclib/');
	include_once('Math/BigInteger.php');
	include_once('Crypt/Base.php');
	include_once('Crypt/Hash.php');
	include_once('Crypt/Random.php');
	include_once('Crypt/RSA.php');
	include_once('Crypt/Rijndael.php');
}

function escape_command($command) {
	return $command;		# we escape every single argument now, no need for 'special' escaping
	#return preg_replace("/(\\\$|`)/", "", $command); # current cacti code
	#TODO return preg_replace((\\\$(?=\w+|\*|\@|\#|\?|\-|\\\$|\!|\_|[0-9]|\(.*\))|`(?=.*(?=`)))","$2", $command);  #suggested by ldevantier to allow for a single $
}

function rrd_init($output_to_term = TRUE) {
	global $config;

	$args = func_get_args();
	$force_storage_location_local = (isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true ) ? true : false;
	$function = ($force_storage_location_local === false && read_config_option('storage_location')) ? '__rrd_proxy_init' : '__rrd_init';
	return call_user_func_array($function, $args);
}

function __rrd_init($output_to_term = TRUE) {
	global $config;

	/* set the rrdtool default font */
	if (read_config_option('path_rrdtool_default_font')) {
		putenv('RRD_DEFAULT_FONT=' . read_config_option('path_rrdtool_default_font'));
	}

	if ($output_to_term) {
		$command = read_config_option('path_rrdtool') . ' - ';
	} elseif ($config['cacti_server_os'] == 'win32') {
		$command = read_config_option('path_rrdtool') . ' - > nul';
	} else {
		$command = read_config_option('path_rrdtool') . ' - > /dev/null 2>&1';
	}

	return popen($command, 'w');
}

function __rrd_proxy_init($logopt = 'WEBLOG') {
	$rsa = new \phpseclib\Crypt\RSA();

	$rrdp_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($rrdp_socket === false) {
		cacti_log('CACTI2RRDP ERROR: Unable to create socket to connect to RRDtool Proxy Server', false, $logopt, POLLER_VERBOSITY_LOW);
		return false;
	}

	if ( read_config_option('rrdp_load_balancing') == 'on' ) {
		$rrdp_id = rand(1,2);
		$rrdp = @socket_connect($rrdp_socket, (($rrdp_id == 1 ) ? read_config_option('rrdp_server') : read_config_option('rrdp_server_backup')), (($rrdp_id == 1 ) ? read_config_option('rrdp_port') : read_config_option('rrdp_port_backup')) );
	}else {
		$rrdp_id = 1;
		$rrdp = @socket_connect($rrdp_socket, read_config_option('rrdp_server'), read_config_option('rrdp_port'));
	}

	if($rrdp === false) {
		/* log entry ... */
		cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool Proxy Server #' . $rrdp_id, false, $logopt, POLLER_VERBOSITY_LOW);

		/* ... and try to use backup path */
		$rrdp_id = ($rrdp_id + 1) % 2;
		$rrdp = @socket_connect($rrdp_socket, (($rrdp_id == 1 ) ? read_config_option('rrdp_server') : read_config_option('rrdp_server_backup')), (($rrdp_id == 1 ) ? read_config_option('rrdp_port') : read_config_option('rrdp_port_backup')) );

		if($rrdp === false) {
			cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool Proxy Server #' . $rrdp_id, false, $logopt, POLLER_VERBOSITY_LOW);
			return false;
		}
	}

	$rrdp_fingerprint = ($rrdp_id == 1 ) ? read_config_option('rrdp_fingerprint') : read_config_option('rrdp_fingerprint_backup');

	socket_write($rrdp_socket, read_config_option('rsa_public_key') . "\r\n");

	/* read public key being returned by the proxy server */
	$rrdp_public_key = '';
	while(1) {
		$recv = socket_read($rrdp_socket, 1000, PHP_BINARY_READ );
		if($recv === false) {
			/* timeout  */
			cacti_log('CACTI2RRDP ERROR: Public RSA Key Exchange - Time-out while reading', false, $logopt, POLLER_VERBOSITY_LOW);
			$rrdp_public_key = false;
			break;
		}else if($recv == '') {
			cacti_log('CACTI2RRDP ERROR: Session closed by Proxy.', false, $logopt, POLLER_VERBOSITY_LOW);
			/* session closed by Proxy */
			break;
		}else {
			$rrdp_public_key .= $recv;
			if (substr($rrdp_public_key, -1) == "\n") {
				$rrdp_public_key = trim($rrdp_public_key);
				break;
			}
		}
	}

	$rsa->loadKey($rrdp_public_key);
	$fingerprint = $rsa->getPublicKeyFingerprint();

	if($rrdp_fingerprint != $fingerprint) {
		cacti_log('CACTI2RRDP ERROR: Mismatch RSA Fingerprint.', false, $logopt, POLLER_VERBOSITY_LOW);
		return false;
	}else {
		$rrdproxy = array($rrdp_socket, $rrdp_public_key);
		/* set the rrdtool default font */
		if (read_config_option('path_rrdtool_default_font')) {
			rrdtool_execute("setenv RRD_DEFAULT_FONT '" . read_config_option('path_rrdtool_default_font') . "'", false, RRDTOOL_OUTPUT_NULL, $rrdproxy, $logopt = 'WEBLOG');
		}

		return $rrdproxy;
	}
}

function rrd_close() {
	global $config;
	$args = func_get_args();
	$force_storage_location_local = (isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true) ? true : false;
	$function = ($force_storage_location_local === false && read_config_option('storage_location')) ? '__rrd_proxy_close' : '__rrd_close';
	return call_user_func_array($function, $args);
}

function __rrd_close($rrdtool_pipe) {
	/* close the rrdtool file descriptor */
	if (is_resource($rrdtool_pipe)) {
		pclose($rrdtool_pipe);
	}
}

function __rrd_proxy_close($rrdp) {
	/* close the rrdtool proxy server connection */
	if ($rrdp) {
		socket_write($rrdp[0], encrypt('quit', $rrdp[1]) . "\r\n");
		@socket_shutdown($rrdp[0], 2);
		@socket_close($rrdp[0]);
		return;
	}
}

function encrypt($output, $rsa_key) {

	$rsa = new \phpseclib\Crypt\RSA();
	$aes = new \phpseclib\Crypt\Rijndael();
	$aes_key = \phpseclib\Crypt\Random::string(192);

	$aes->setKey($aes_key);
	$ciphertext = base64_encode($aes->encrypt($output));
	$rsa->loadKey($rsa_key);
	$aes_key = base64_encode($rsa->encrypt($aes_key));
	$aes_key_length = str_pad(dechex(strlen($aes_key)),3,'0',STR_PAD_LEFT);

	return $aes_key_length . $aes_key . $ciphertext;
}

function decrypt($input){

	$rsa = new \phpseclib\Crypt\RSA();
	$aes = new \phpseclib\Crypt\Rijndael();

	$rsa_private_key = read_config_option('rsa_private_key');

	$aes_key_length = hexdec(substr($input,0,3));
	$aes_key = base64_decode(substr($input,3,$aes_key_length));
	$ciphertext = base64_decode(substr($input,3+$aes_key_length));

	$rsa->loadKey( $rsa_private_key );
	$aes_key = $rsa->decrypt($aes_key);
	$aes->setKey($aes_key);
	$plaintext = $aes->decrypt($ciphertext);

	return $plaintext;
}

function rrdtool_execute() {
	global $config;
	$args = func_get_args();
	$force_storage_location_local = (isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true) ? true : false;
	$function = ($force_storage_location_local === false && read_config_option('storage_location')) ? '__rrd_proxy_execute' : '__rrd_execute';
	return call_user_func_array($function, $args);
}

function __rrd_execute($command_line, $log_to_stdout, $output_flag, $rrdtool_pipe = '', $logopt = 'WEBLOG') {
	global $config;

	static $last_command;

	if (!is_numeric($output_flag)) {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;
	}

	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the '\' characters. Unix does not care; win32 does.
	Also make sure to replace all of the fancy \'s at the end of the line,
	but make sure not to get rid of the "\n"'s that are supposed to be
	in there (text format) */
	$command_line = str_replace("\\\n", ' ', $command_line);

	/* output information to the log file if appropriate */
	cacti_log('CACTI2RRD: ' . read_config_option('path_rrdtool') . " $command_line", $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);

	/* if we want to see the error output from rrdtool; make sure to specify this */
	if ($config['cacti_server_os'] != 'win32') {
		if ($output_flag == RRDTOOL_OUTPUT_STDERR && !is_resource($rrdtool_pipe)) {
			$command_line .= ' 2>&1';
		}
	}

	/* use popen to eliminate the zombie issue */
	if ($config['cacti_server_os'] == 'unix') {
		$pipe_mode = 'r';
	} else {
		$pipe_mode = 'rb';
	}

	/* an empty $rrdtool_pipe array means no fp is available */
	if (!is_resource($rrdtool_pipe)) {
		session_write_close();
		$fp = popen(read_config_option('path_rrdtool') . escape_command(" $command_line"), $pipe_mode);
		if (!is_resource($fp)) {
			unset($fp);
		}
	} else {
		$i = 0;
		while (1) {
			if (fwrite($rrdtool_pipe, escape_command(" $command_line") . "\r\n") === false) {
				cacti_log("ERROR: Detected RRDtool Crash on '$command_line'.  Last command was '$last_command'");

				/* close the invalid pipe */
				rrd_close($rrdtool_pipe);

				/* open a new rrdtool process */
				$rrdtool_pipe = rrd_init();

				if ($i > 4) {
					cacti_log("FATAL: RRDtool Restart Attempts Exceeded. Giving up on '$command_line'.");

					break;
				} else {
					$i++;
				}

				continue;
			} else {
				fflush($rrdtool_pipe);

				break;
			}
		}
	}

	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;

	if (!isset($fp)) {
		return;
	}

	switch ($output_flag) {
		case RRDTOOL_OUTPUT_STDOUT:
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			$output = '';
			while (!feof($fp)) {
				$output .= fgets($fp, 4096);
			}

			pclose($fp);

			return $output;
			break;
		case RRDTOOL_OUTPUT_STDERR:
			$output = fgets($fp, 1000000);

			pclose($fp);

			if (substr($output, 1, 3) == 'PNG') {
				return 'OK';
			}

			if (substr($output, 0, 5) == 'GIF87') {
				return 'OK';
			}

			if (substr($output, 0, 5) == '<?xml') {
				return 'SVG/XML Output OK';
			}

			print $output;
			break;
		default:
		case RRDTOOL_OUTPUT_NULL:
			return;
			break;
	}
}

function __rrd_proxy_execute($command_line, $log_to_stdout, $output_flag, $rrdp='', $logopt = 'WEBLOG') {
	global $config;

	static $last_command;

	if (!is_numeric($output_flag)) {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;
	}

	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the '\' characters. Unix does not care; win32 does.
	Also make sure to replace all of the fancy \'s at the end of the line,
	but make sure not to get rid of the "\n"'s that are supposed to be
	in there (text format) */
	$command_line = str_replace( array($config['rra_path'], "\\\n"), array(".", " "), $command_line);

	/* output information to the log file if appropriate */
	cacti_log("CACTI2RRDP: " . read_config_option("path_rrdtool") . " $command_line", $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);

	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;
	$rrdp_auto_close = FALSE;

	if(!$rrdp) {
		$rrdp = __rrd_proxy_init($logopt);
		$rrdp_auto_close = TRUE;
	}

	if(!$rrdp) {
		cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool proxy.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
		return null;
	}else {
		cacti_log('CACTI2RRDP NOTE: Connection to RRDtool proxy has already been established.', $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
	}

	$rrdp_socket = $rrdp[0];
	$rrdp_public_key = $rrdp[1];

	if(strlen($command_line) >= 8192) {
		$command_line = gzencode($command_line, 1);
	}
	socket_write($rrdp_socket, encrypt($command_line, $rrdp_public_key) . "\r\n");

	$input = '';
	$output = '';

	while(1) {
		$recv = socket_read($rrdp_socket, 100000, PHP_BINARY_READ );
		if($recv === false) {
			cacti_log('CACTI2RRDP ERROR: Data Transfer - Time-out while reading.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
			break;
		}else if($recv == '') {
			/* session closed by Proxy */
			if($output) {
				cacti_log('CACTI2RRDP ERROR: Session closed by Proxy.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
			}
			break;
		}else {
			$input .= $recv;
			if (strpos($input, "\n") !== false) {
				$chunks = explode("\n", $input);
				$input = array_pop($chunks);

				foreach($chunks as $chunk) {
					$output .= decrypt(trim($chunk));
					if(strpos($output, "\x1f\x8b") === 0) {
						$output = gzdecode($output);
					}

					if ( substr_count($output, "OK u") || substr_count($output, "ERROR:") ) {
						cacti_log("RRDP: " . $output, $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
						break 2;
					}
				}
			}
		}
	}

	if($rrdp_auto_close) {
		__rrd_proxy_close($rrdp);
	}

	switch ($output_flag) {
		case RRDTOOL_OUTPUT_NULL:
			return;
		case RRDTOOL_OUTPUT_STDOUT:
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			return $output;
			break;
		case RRDTOOL_OUTPUT_STDERR:
			if (substr($output, 1, 3) == "PNG") {
				return "OK";
			}
			if (substr($output, 0, 5) == "GIF87") {
				return "OK";
			}
			print $output;
			break;
		case RRDTOOL_OUTPUT_BOOLEAN :
			return (substr_count($output, "OK u")) ? true : false;
			break;
	}
}

function rrdtool_function_create($local_data_id, $show_source, $rrdtool_pipe = '') {
	global $config, $data_source_types, $consolidation_functions;

	include ($config['include_path'] . '/global_arrays.php');

	$data_source_path = get_data_source_path($local_data_id, true);

	/* ok, if that passes lets check to make sure an rra does not already
	exist, the last thing we want to do is overright data! */
	if ($show_source != true) {
		if (read_config_option('storage_location')) {
			if (rrdtool_execute("file_exists $data_source_path", true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER')) {
				return -1;
			}
		} elseif (file_exists($data_source_path)) {
			return -1;
		}
	}

	/* the first thing we must do is make sure there is at least one
	rra associated with this data source... *
	UPDATE: As of version 0.6.6, we are splitting this up into two
	SQL strings because of the multiple DS per RRD support. This is
	not a big deal however since this function gets called once per
	data source */

	$rras = db_fetch_assoc_prepared('SELECT dtd.rrd_step, dsp.x_files_factor,
		dspr.steps, dspr.rows, dspc.consolidation_function_id,
		(dspr.rows*dspr.steps) AS rra_order
		FROM data_template_data AS dtd
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		LEFT JOIN data_source_profiles_cf AS dspc
		ON dsp.id=dspc.data_source_profile_id
		WHERE dtd.local_data_id = ?
		AND (dspr.steps IS NOT NULL OR dspr.rows IS NOT NULL)
		ORDER BY dspc.consolidation_function_id, rra_order',
		array($local_data_id)
	);

	/* if we find that this DS has no RRA associated; get out */
	if (sizeof($rras) <= 0) {
		cacti_log("ERROR: There are no RRA's assigned to local_data_id: $local_data_id.");
		return false;
	}

	/* create the "--step" line */
	$create_ds = RRD_NL . '--step '. $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/* query the data sources to be used in this .rrd file */
	$data_sources = db_fetch_assoc_prepared('SELECT id, rrd_heartbeat,
		rrd_minimum, rrd_maximum, data_source_type_id
		FROM data_template_rrd
		WHERE local_data_id = ?
		ORDER BY local_data_template_rrd_id',
		array($local_data_id)
	);

	/* ONLY make a new DS entry if:
	- There is multiple data sources and this item is not the main one.
	- There is only one data source (then use it) */

	if (sizeof($data_sources)) {
		$data_local = db_fetch_row_prepared('SELECT host_id,
			snmp_query_id, snmp_index
			FROM data_local
			WHERE id = ?',
			array($local_data_id)
		);

		$highSpeed = db_fetch_cell_prepared('SELECT field_value
			FROM host_snmp_cache
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND snmp_index = ?
			AND field_name="ifHighSpeed"',
			array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index'])
		);

		$ssqdIfSpeed = substitute_snmp_query_data('|query_ifSpeed|', $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);

		foreach ($data_sources as $data_source) {
			/* use the cacti ds name by default or the user defined one, if entered */
			$data_source_name = get_data_source_item_name($data_source['id']);

			if (empty($data_source['rrd_maximum'])) {
				/* in case no maximum is given, use "Undef" value */
				$data_source['rrd_maximum'] = 'U';
			} elseif (strpos($data_source['rrd_maximum'], '|query_') !== false) {
				/* in case a query variable is given, evaluate it */
				if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
					if (!empty($highSpeed)) {
						$data_source['rrd_maximum'] = $highSpeed * 1000000;
					} else {
						$data_source['rrd_maximum'] = $ssqdIfSpeed;

						if (empty($data_source['rrd_maximum']) || $data_source['rrd_maximum'] == '|query_ifSpeed|') {
							$data_source['rrd_maximum'] = '10000000000000';
						}
					}
				} else {
					$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'], $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
				}
			} elseif ($data_source['rrd_maximum'] != 'U' && (int)$data_source['rrd_maximum'] <= (int)$data_source['rrd_minimum']) {
				/* max > min required, but take care of an "Undef" value */
				$data_source['rrd_maximum'] = (int)$data_source['rrd_minimum']+1;
			}

			/* min==max==0 won't work with rrdtool */
			if ($data_source['rrd_minimum'] == 0 && $data_source['rrd_maximum'] == 0) {
				$data_source['rrd_maximum'] = 'U';
			}

			$create_ds .= "DS:$data_source_name:" . $data_source_types[$data_source['data_source_type_id']] . ':' . $data_source['rrd_heartbeat'] . ':' . $data_source['rrd_minimum'] . ':' . $data_source['rrd_maximum'] . RRD_NL;
		}
	}

	$create_rra = '';
	/* loop through each available RRA for this DS */
	foreach ($rras as $rra) {
		$create_rra .= 'RRA:' . $consolidation_functions[$rra['consolidation_function_id']] . ':' . $rra['x_files_factor'] . ':' . $rra['steps'] . ':' . $rra['rows'] . RRD_NL;
	}

	/* check for structured path configuration, if in place verify directory
	   exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
		if (read_config_option('storage_location')) {
			if (false === rrdtool_execute("is_dir " . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
				if (false === rrdtool_execute("mkdir " . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			}
		}elseif (!is_dir(dirname($data_source_path))) {
			if ($config['is_web'] == false) {
				if (mkdir(dirname($data_source_path), 0775)) {
					if ($config['cacti_server_os'] != 'win32') {
						$owner_id = fileowner($config['rra_path']);
						$group_id = filegroup($config['rra_path']);

						if ((chown(dirname($data_source_path), $owner_id)) &&
								(chgrp(dirname($data_source_path), $group_id))) {
							/* permissions set ok */
						}else{
							cacti_log("ERROR: Unable to set directory permissions for '" . dirname($data_source_path) . "'", FALSE);
						}
					}
				}else{
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", FALSE);
				}
			}else{
				cacti_log("WARNING: Poller has not created structured path '" . dirname($data_source_path) . "' yet.", FALSE);
			}
		}
	}

	if ($show_source == true) {
		return read_config_option('path_rrdtool') . ' create' . RRD_NL . "$data_source_path$create_ds$create_rra";
	} else {
		rrdtool_execute("create $data_source_path $create_ds$create_rra", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');
	}
}

function rrdtool_function_update($update_cache_array, $rrdtool_pipe = '') {
	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	foreach ($update_cache_array as $rrd_path => $rrd_fields) {
		$create_rrd_file = false;

		if (is_array($rrd_fields['times']) && sizeof($rrd_fields['times'])) {
			/* create the rrd if one does not already exist */
			if (read_config_option('storage_location')) {
				$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER');
			} else {
				$file_exists = file_exists($rrd_path);
			}

			if ($file_exists === false) {
				rrdtool_function_create($rrd_fields['local_data_id'], false, $rrdtool_pipe);
				$create_rrd_file = true;
			}

			ksort($rrd_fields['times']);

			foreach ($rrd_fields['times'] as $update_time => $field_array) {
				if (empty($update_time)) {
					/* default the rrdupdate time to now */
					$rrd_update_values = 'N:';
				} elseif ($create_rrd_file == true) {
					/* for some reason rrdtool will not let you update using times less than the
					rrd create time */
					$rrd_update_values = 'N:';
				} else {
					$rrd_update_values = $update_time . ':';
				}

				$rrd_update_template = '';

				foreach ($field_array as $field_name => $value) {
					if ($rrd_update_template != '') {
						$rrd_update_template .= ':';
						$rrd_update_values .= ':';
					}

					$rrd_update_template .= $field_name;

					/* if we have "invalid data", give rrdtool an Unknown (U) */
					if (!isset($value) || !is_numeric($value)) {
						$value = 'U';
					}

					$rrd_update_values .= $value;
				}

				rrdtool_execute("update $rrd_path --template $rrd_update_template $rrd_update_values", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');
				$rrds_processed++;
			}
		}
	}

	return $rrds_processed;
}

function rrdtool_function_tune($rrd_tune_array) {
	global $config, $data_source_types;

	include($config['include_path'] . '/global_arrays.php');

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types[$rrd_tune_array['data-source-type']];
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);

	$rrd_tune = '';
	if ($rrd_tune_array['heartbeat'] != '') {
		$rrd_tune .= " --heartbeat $data_source_name:" . $rrd_tune_array['heartbeat'];
	}

	if ($rrd_tune_array['minimum'] != '') {
		$rrd_tune .= " --minimum $data_source_name:" . $rrd_tune_array['minimum'];
	}

	if ($rrd_tune_array['maximum'] != '') {
		$rrd_tune .= " --maximum $data_source_name:" . $rrd_tune_array['maximum'];
	}

	if ($rrd_tune_array['data-source-type'] != '') {
		$rrd_tune .= " --data-source-type $data_source_name:" . $data_source_type;
	}

	if ($rrd_tune_array['data-source-rename'] != '') {
		$rrd_tune .= " --data-source-rename $data_source_name:" . $rrd_tune_array['data-source-rename'];
	}

	if ($rrd_tune != '') {
		if (file_exists($data_source_path) == true) {
			$fp = popen(read_config_option('path_rrdtool') . " tune $data_source_path $rrd_tune", 'r');
			pclose($fp);

			cacti_log('CACTI2RRD: ' . read_config_option('path_rrdtool') . " tune $data_source_path $rrd_tune", false, 'WEBLOG', POLLER_VERBOSITY_DEBUG);
		}
	}
}

/* rrdtool_function_fetch - given a data source, return all of its data in an array
   @arg $local_data_id - the data source to fetch data for
   @arg $start_time - the start time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $end_time - the end time to use for the data calculation. this value can
     either be absolute (unix timestamp) or relative (to now)
   @arg $resolution - the accuracy of the data measured in seconds
   @arg $show_unknown - Show unknown 'NAN' values in the output as 'U'
   @arg $rrdtool_file - Don't force Cacti to calculate the file
   @arg $cf - Specify the consolidation function to use
   @arg $rrdtool_pipe - a pipe to an rrdtool command
   @returns - (array) an array containing all data in this data source broken down
     by each data source item. the maximum of all data source items is included in
     an item called 'ninety_fifth_percentile_maximum' */
function rrdtool_function_fetch($local_data_id, $start_time, $end_time, $resolution = 0, $show_unknown = false, $rrdtool_file = null, $cf = 'AVERAGE', $rrdtool_pipe = '') {
	global $config;

	include_once($config['library_path'] . '/boost.php');

	/* validate local data id */
	if (empty($local_data_id) && is_null($rrdtool_file)) {
		return array();
	}

	/* initialize fetch array */
	$fetch_array = array();

	/* check if we have been passed a file instead of lodal data source to look up */
	if (is_null($rrdtool_file)) {
		$data_source_path = get_data_source_path($local_data_id, true);
	} else {
		$data_source_path = $rrdtool_file;
	}

	/* update the rrdfile if performing a fetch */
	boost_fetch_cache_check($local_data_id);

	/* build and run the rrdtool fetch command with all of our data */
	$cmd_line = "fetch $data_source_path $cf -s $start_time -e $end_time";
	if ($resolution > 0) {
		$cmd_line .= " -r $resolution";
	}
	$output = rrdtool_execute($cmd_line, false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe);
	$output = explode("\n", $output);

	$first  = true;
	$count  = 0;

	if (sizeof($output)) {
		$timestamp = 0;
		foreach($output as $line) {
			$line      = trim($line);
			$max_array = array();

			if ($first) {
				/* get the data source names */
				$fetch_array['data_source_names'] = preg_split('/\s+/', $line);
				$first = false;

				/* set the nth percentile source, and index */
				$fetch_array['data_source_names'][] = 'nth_percentile_maximum';
				$nthindex = sizeof($fetch_array['data_source_names']) - 1;
			} elseif ($line != '') {
				/* process the data sources into an array */
				$parts     = explode(':', $line);
				$timestamp = $parts[0];
				$data      = explode(' ', trim($parts[1]));

				if (!isset($fetch_array['timestamp']['start_time'])) {
					$fetch_array['timestamp']['start_time'] = $timestamp;
				}

				/* process out bad data */
				foreach($data as $index => $number) {
					if (strtolower($number) == 'nan' || strtolower($number) == '-nan') {
						if ($show_unknown) {
							$fetch_array['values'][$index][$count] = 'U';
						}
					} else {
						$fetch_array['values'][$index][$count] = $number + 0;
						$max_array[] = $number + 0;
					}
				}

				if (sizeof($max_array)) {
					$fetch_array['values'][$nthindex][$count] = max($max_array);
				} else {
					$fetch_array['values'][$nthindex][$count] = 0;
				}

				$count++;
			}
		}

		$fetch_array['timestamp']['end_time'] = $timestamp;
	}

	return $fetch_array;
}

function rrd_function_process_graph_options($graph_start, $graph_end, &$graph, &$graph_data_array) {
	global $config, $image_types;

	include($config['include_path'] . '/global_arrays.php');

	/* define some variables */
	$scale               = '';
	$rigid               = '';
	$unit_value          = '';
	$version             = read_config_option('rrdtool_version');
	$unit_exponent_value = '';

	if ($graph['auto_scale'] == 'on') {
		switch ($graph['auto_scale_opts']) {
			case '1': /* autoscale ignores lower, upper limit */
				$scale = '--alt-autoscale' . RRD_NL;
				break;
			case '2': /* autoscale-max, accepts a given lower limit */
				$scale = '--alt-autoscale-max' . RRD_NL;
				if (is_numeric($graph['lower_limit'])) {
					$scale .= '--lower-limit=' . cacti_escapeshellarg($graph['lower_limit']) . RRD_NL;
				}
				break;
			case '3': /* autoscale-min, accepts a given upper limit */
				$scale = '--alt-autoscale-min' . RRD_NL;
				if ( is_numeric($graph['upper_limit'])) {
					$scale .= '--upper-limit=' . cacti_escapeshellarg($graph['upper_limit']) . RRD_NL;
				}
				break;
			case '4': /* auto_scale with limits */
				$scale = '--alt-autoscale' . RRD_NL;
				if ( is_numeric($graph['upper_limit'])) {
					$scale .= '--upper-limit=' . cacti_escapeshellarg($graph['upper_limit']) . RRD_NL;
				}
				if ( is_numeric($graph['lower_limit'])) {
					$scale .= '--lower-limit=' . cacti_escapeshellarg($graph['lower_limit']) . RRD_NL;
				}
				break;
		}
	} else {
		if ($graph['upper_limit'] != '') {
			$scale =  '--upper-limit=' . cacti_escapeshellarg($graph['upper_limit']) . RRD_NL;
		}
		if ($graph['lower_limit'] != '') {
			$scale .= '--lower-limit=' . cacti_escapeshellarg($graph['lower_limit']) . RRD_NL;
		}
	}

	if ($graph['auto_scale_log'] == 'on') {
		$scale .= '--logarithmic' . RRD_NL;
	}

	/* --units=si only defined for logarithmic y-axis scaling, even if it doesn't hurt on linear graphs */
	if ($graph['scale_log_units'] == 'on' && $graph['auto_scale_log'] == 'on') {
		$scale .= '--units=si' . RRD_NL;
	}

	if ($graph['auto_scale_rigid'] == 'on') {
		$rigid = '--rigid' . RRD_NL;
	}

	if ($graph['unit_value'] != '') {
		$unit_value = '--y-grid=' . cacti_escapeshellarg($graph['unit_value']) . RRD_NL;
	}

	if (preg_match('/^[0-9]+$/', $graph['unit_exponent_value'])) {
		$unit_exponent_value = '--units-exponent=' . cacti_escapeshellarg($graph['unit_exponent_value']) . RRD_NL;
	}

	/*
	 * optionally you can specify and array that overrides some of the db's values, lets set
	 * that all up here
	 */

	/* override: graph height (in pixels) */
	if (isset($graph_data_array['graph_height'])) {
		$graph_height = $graph_data_array['graph_height'];
	} else {
		$graph_height = $graph['height'];
	}

	/* override: graph width (in pixels) */
	if (isset($graph_data_array['graph_width'])) {
		$graph_width = $graph_data_array['graph_width'];
	} else {
		$graph_width = $graph['width'];
	}

	/* override: skip drawing the legend? */
	if (isset($graph_data_array['graph_nolegend'])) {
		$graph_legend = '--no-legend' . RRD_NL;
	} else {
		$graph_legend = '';
	}

	/* export options */
	if (isset($graph_data_array['export'])) {
		$graph_opts = $graph_data_array['export_filename'] . RRD_NL;
	} else {
		if (empty($graph_data_array['output_filename'])) {
				$graph_opts = '-' . RRD_NL;
		} else {
			$graph_opts = $graph_data_array['output_filename'] . RRD_NL;
		}
	}

	/* if realtime, the image format id is always png */
	if (isset($graph_data_array['export_realtime']) || (isset($graph_data_array['image_format']) && $graph_data_array['image_format'] == 'png')) {
		$graph['image_format_id'] = 1;
	}

	/* basic graph options */
	$graph_opts .=
		'--imgformat=' . $image_types{$graph['image_format_id']} . RRD_NL .
		'--start=' . cacti_escapeshellarg($graph_start) . RRD_NL .
		'--end=' . cacti_escapeshellarg($graph_end) . RRD_NL;

	$graph_opts .= '--pango-markup ' . RRD_NL;

	foreach($graph as $key => $value) {
		switch($key) {
		case "title_cache":
			if (!empty($value)) {
				$graph_opts .= "--title=" . cacti_escapeshellarg(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . RRD_NL;
			}
			break;
		case "alt_y_grid":
			if ($value == CHECKED)  {
				$graph_opts .= "--alt-y-grid" . RRD_NL;
			}
			break;
		case "unit_value":
			if (!empty($value)) {
				$graph_opts .= "--y-grid=" . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "unit_exponent_value":
			if (preg_match("/^[0-9]+$/", $value)) {
				$graph_opts .= "--units-exponent=" . $value . RRD_NL;
			}
			break;
		case "height":
			if (isset($graph_data_array["graph_height"]) && preg_match("/^[0-9]+$/", $graph_data_array["graph_height"])) {
				$graph_opts .= "--height=" . $graph_data_array["graph_height"] . RRD_NL;
			} else {
				$graph_opts .= "--height=" . $value . RRD_NL;
			}
			break;
		case "width":
			if (isset($graph_data_array["graph_width"]) && preg_match("/^[0-9]+$/", $graph_data_array["graph_width"])) {
				$graph_opts .= "--width=" . $graph_data_array["graph_width"] . RRD_NL;
			} else {
				$graph_opts .= "--width=" . $value . RRD_NL;
			}
			break;
		case "graph_nolegend":
			if (isset($graph_data_array["graph_nolegend"])) {
				$graph_opts .= "--no-legend" . RRD_NL;
			} else {
				$graph_opts .= "";
			}
			break;
		case "base_value":
			if ($value == 1000 || $value == 1024) {
			$graph_opts .= "--base=" . $value . RRD_NL;
			}
			break;
		case "vertical_label":
			if (!empty($value)) {
				$graph_opts .= "--vertical-label=" . cacti_escapeshellarg(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . RRD_NL;
			}
			break;
		case "slope_mode":
			if ($value == CHECKED) {
				$graph_opts .= "--slope-mode" . RRD_NL;
			}
			break;
		case "right_axis":
			if (!empty($value)) {
				$graph_opts .= "--right-axis " . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "right_axis_label":
			if (!empty($value)) {
				$graph_opts .= "--right-axis-label " . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "right_axis_format":
			if (!empty($value)) {
				$format = db_fetch_cell_prepared('SELECT gprint_text from graph_templates_gprint WHERE id = ?', array($value));
				$graph_opts .= "--right-axis-format " . cacti_escapeshellarg($format) . RRD_NL;
			}
			break;
		case "no_gridfit":
			if ($value == CHECKED) {
				$graph_opts .= "--no-gridfit" . RRD_NL;
			}
			break;
		case "unit_length":
			if (!empty($value)) {
				$graph_opts .= "--units-length " . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "tab_width":
			if (!empty($value)) {
				$graph_opts .= "--tabwidth " . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "dynamic_labels":
			if ($value == CHECKED) {
				$graph_opts .= "--dynamic-labels" . RRD_NL;
			}
			break;
		case "force_rules_legend":
			if ($value == CHECKED) {
				$graph_opts .= "--force-rules-legend" . RRD_NL;
			}
			break;
		case "legend_position":
			if ($version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--legend-position " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case "legend_direction":
			if ($version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--legend-direction " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'left_axis_formatter':
			if ($version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--left-axis-formatter " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'right_axis_formatter':
			if ($version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--right-axis-formatter " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		}
	}

	$graph_opts .= "$rigid" . trim("$scale$unit_value$unit_exponent_value$graph_legend", "\n\r " . RRD_NL) . RRD_NL;

	/* add a date to the graph legend */
	$graph_opts .= rrdtool_function_format_graph_date($graph_data_array);

	/* process theme and font styling options */
	$graph_opts .= rrdtool_function_theme_font_options($graph_data_array);

	/* Replace "|query_*|" in the graph command to replace e.g. vertical_label.  */
	$graph_opts = rrd_substitute_host_query_data($graph_opts, $graph, array());

	/* provide smooth lines */
	if ($graph['slope_mode'] == 'on') {
		$graph_opts .= '--slope-mode' . RRD_NL;
	}

	/* if the user desires a wartermark set it */
	if (read_config_option('graph_watermark') != '') {
		$graph_opts .= '--watermark ' . cacti_escapeshellarg(read_config_option('graph_watermark')) . RRD_NL;
	}

	return $graph_opts;
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe = '', &$xport_meta = array(), $user = 0) {
	global $config, $consolidation_functions, $graph_item_types;

	include_once($config['library_path'] . '/cdef.php');
	include_once($config['library_path'] . '/vdef.php');
	include_once($config['library_path'] . '/graph_variables.php');
	include_once($config['library_path'] . '/boost.php');
	include_once($config['library_path'] . '/xml.php');
	include($config['include_path'] . '/global_arrays.php');

	/* prevent command injection
	 * This function prepares an rrdtool graph statement to be executed by the web server.
	 * We have to take care, that the attacker does not insert shell code.
	 * As some rrdtool parameters accept "Cacti variables", we have to perform the
	 * variable substitution prior to vulnerability checks.
	 * We will enclose all parameters in quotes and substitute quotation marks within
	 * those parameters.
	 */

	/* before we do anything; make sure the user has permission to view this graph,
	if not then get out */
	if ($user > 0) {
		if (!is_graph_allowed($local_graph_id, $user)) {
			return 'GRAPH ACCESS DENIED';
		}
	}

	/* check the purge the boost poller output cache, and check for a live image file if caching is enabled */
	$graph_data = boost_graph_cache_check($local_graph_id, $rra_id, $rrdtool_pipe, $graph_data_array, false);
	if ($graph_data !== false) {
		return $graph_data;
	}

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell_prepared('SELECT
		data_template_data.rrd_step
		FROM (data_template_data,data_template_rrd,graph_templates_item)
		WHERE graph_templates_item.task_item_id=data_template_rrd.id
		AND data_template_rrd.local_data_id=data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		LIMIT 1',
		array($local_graph_id)
	);

	$ds_step = empty($ds_step) ? 300 : $ds_step;

	/* if no rra was specified, we need to figure out which one RRDTool will choose using
	 * "best-fit" resolution fit algorithm */
	if (empty($rra_id)) {
		if (empty($graph_data_array['graph_start']) || empty($graph_data_array['graph_end'])) {
			$rra['rows']     = 600;
			$rra['steps']    = 1;
			$rra['timespan'] = 86400;
		} else {
			/* get a list of RRAs related to this graph */
			$rras = get_associated_rras($local_graph_id);

			if (sizeof($rras)) {
				foreach ($rras as $unchosen_rra) {
					/* the timespan specified in the RRA "timespan" field may not be accurate */
					$real_timespan = ($ds_step * $unchosen_rra['steps'] * $unchosen_rra['rows']);

					/* make sure the current start/end times fit within each RRA's timespan */
					if ($graph_data_array['graph_end'] - $graph_data_array['graph_start'] <= $real_timespan && time() - $graph_data_array['graph_start'] <= $real_timespan) {
						/* is this RRA better than the already chosen one? */
						if (isset($rra) && $unchosen_rra['steps'] < $rra['steps']) {
							$rra = $unchosen_rra;
						} elseif (!isset($rra)) {
							$rra = $unchosen_rra;
						}
					}
				}
			}

			if (!isset($rra)) {
				$rra['rows']     = 600;
				$rra['steps']    = 1;
				$rra['timespan'] = 86400;
			}
		}
	} else {
		$rra = db_fetch_row_prepared('SELECT
			dspr.rows, dsp.step, dspr.steps
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dspr.data_source_profile_id=dsp.id
			WHERE dspr.id = ?',
			array($rra_id)
		);

		if (isset($rra['steps'])) {
			$rra['timespan'] = $rra['rows'] * $rra['step'] * $rra['steps'];
		} else {
			$rra['timespan'] = 86400;
			$rra['steps']    = 1;
			$rra['rows']     = 600;
		}
	}

	if (!isset($graph_data_array['export_realtime']) && isset($rra['steps'])) {
		$seconds_between_graph_updates = ($ds_step * $rra['steps']);
	} else {
		$seconds_between_graph_updates = 5;
	}

	$graph = db_fetch_row_prepared('SELECT gl.id AS local_graph_id, gl.host_id,
		gl.snmp_query_id, gl.snmp_index, gtg.title_cache, gtg.vertical_label,
		gtg.slope_mode, gtg.auto_scale, gtg.auto_scale_opts, gtg.auto_scale_log,
		gtg.scale_log_units, gtg.auto_scale_rigid, gtg.auto_padding, gtg.base_value,
		gtg.upper_limit, gtg.lower_limit, gtg.height, gtg.width, gtg.image_format_id,
		gtg.unit_value, gtg.unit_exponent_value, gtg.alt_y_grid,
		gtg.right_axis, gtg.right_axis_label, gtg.right_axis_format, gtg.no_gridfit,
		gtg.unit_length, gtg.tab_width, gtg.dynamic_labels, gtg.force_rules_legend,
		gtg.legend_position, gtg.legend_direction, gtg.right_axis_formatter,
		gtg.left_axis_formatter
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id=gtg.local_graph_id
		WHERE gtg.local_graph_id = ?',
		array($local_graph_id)
	);

	/* handle the case where the graph has been deleted */
	if (!sizeof($graph)) {
		return false;
	}

	/* lets make that sql query... */
	$graph_items = db_fetch_assoc_prepared('SELECT gti.id AS graph_templates_item_id,
		gti.cdef_id, gti.vdef_id, gti.text_format, gti.value, gti.hard_return,
		gti.consolidation_function_id, gti.graph_type_id, gtgp.gprint_text,
		colors.hex, gti.alpha, gti.line_width, gti.dashes, gti.shift,
		gti.dash_offset, gti.textalign,
		dtr.id AS data_template_rrd_id, dtr.local_data_id,
		dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_name, dtr.local_data_template_rrd_id
		FROM graph_templates_item AS gti
		LEFT JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		LEFT JOIN colors
		ON gti.color_id=colors.id
		LEFT JOIN graph_templates_gprint AS gtgp
		ON gti.gprint_id=gtgp.id
		WHERE gti.local_graph_id = ?
		ORDER BY gti.sequence',
		array($local_graph_id)
	);

	/* variables for use below */
	$graph_defs       = '';
	$txt_graph_items  = '';
	$padding_estimate = 0;
	$last_graph_type  = '';

	/* override: graph start time */
	if (!isset($graph_data_array['graph_start']) || $graph_data_array['graph_start'] == '0') {
		$graph_start = -($rra['timespan']);
	} else {
		$graph_start = $graph_data_array['graph_start'];
	}

	/* override: graph end time */
	if (!isset($graph_data_array['graph_end']) || $graph_data_array['graph_end'] == '0') {
		$graph_end = -($seconds_between_graph_updates);
	} else {
		$graph_end = $graph_data_array['graph_end'];
	}

	/* +++++++++++++++++++++++ GRAPH OPTIONS +++++++++++++++++++++++ */

	if (!isset($graph_data_array['export_csv'])) {
		$graph_opts = rrd_function_process_graph_options($graph_start, $graph_end, $graph, $graph_data_array);
	} else {
		/* basic export options */
		$graph_opts =
			'--start=' . cacti_escapeshellarg($graph_start) . RRD_NL .
			'--end=' . cacti_escapeshellarg($graph_end) . RRD_NL .
			'--maxrows=10000' . RRD_NL;
	}

	/* +++++++++++++++++++++++ LEGEND: MAGIC +++++++++++++++++++++++ */

	$realtimeCachePath = read_config_option('realtime_cache_path');
	$dateTime = date('D d M H:i:s T Y', strtotime(read_config_option('date')));

	/* the following fields will be searched for graph variables */
	$variable_fields = array(
		'text_format' => array(
			'process_no_legend' => false
		),
		'value' => array(
			'process_no_legend' => true
		),
		'cdef_cache' => array(
			'process_no_legend' => true
		),
		'vdef_cache' => array(
			'process_no_legend' => true
		)
	);

	$i = 0;
	$j = 0;
	$last_graph_cf = array();
	if (sizeof($graph_items)) {
		/* we need to add a new column 'cf_reference', so unless PHP 5 is used, this foreach syntax is required */
		foreach ($graph_items as $key => $graph_item) {
			/* mimic the old behavior: LINE[123], AREA and STACK items use the CF specified in the graph item */
			switch ($graph_item['graph_type_id']) {
				case GRAPH_ITEM_TYPE_LINE1:
				case GRAPH_ITEM_TYPE_LINE2:
				case GRAPH_ITEM_TYPE_LINE3:
				case GRAPH_ITEM_TYPE_LINESTACK:
				case GRAPH_ITEM_TYPE_TIC:
				case GRAPH_ITEM_TYPE_AREA:
				case GRAPH_ITEM_TYPE_STACK:
					$graph_cf = $graph_item['consolidation_function_id'];
					/* remember the last CF for this data source for use with GPRINT
					 * if e.g. an AREA/AVERAGE and a LINE/MAX is used
					 * we will have AVERAGE first and then MAX, depending on GPRINT sequence */
					$last_graph_cf['data_source_name']['local_data_template_rrd_id'] = $graph_cf;
					/* remember this for second foreach loop */
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
				case GRAPH_ITEM_TYPE_GPRINT:
					/* ATTENTION!
					 * the 'CF' given on graph_item edit screen for GPRINT is indeed NOT a real 'CF',
					 * but an aggregation function
					 * see 'man rrdgraph_data' for the correct VDEF based notation
					 * so our task now is to 'guess' the very graph_item, this GPRINT is related to
					 * and to use that graph_item's CF */
					if (isset($last_graph_cf['data_source_name']['local_data_template_rrd_id'])) {
						$graph_cf = $last_graph_cf['data_source_name']['local_data_template_rrd_id'];
						/* remember this for second foreach loop */
						$graph_items[$key]['cf_reference'] = $graph_cf;
					} else {
						$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id']);
						/* remember this for second foreach loop */
						$graph_items[$key]['cf_reference'] = $graph_cf;
					}
					break;
				case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
					$graph_cf = $graph_item['consolidation_function_id'];
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
				case GRAPH_ITEM_TYPE_GPRINT_LAST:
					$graph_cf = $graph_item['consolidation_function_id'];
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
				case GRAPH_ITEM_TYPE_GPRINT_MAX:
					$graph_cf = $graph_item['consolidation_function_id'];
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
				case GRAPH_ITEM_TYPE_GPRINT_MIN:
					$graph_cf = $graph_item['consolidation_function_id'];
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
				default:
					/* all other types are based on the best matching CF */
					$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id']);
					/* remember this for second foreach loop */
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
			}

			if (!empty($graph_item['local_data_id']) && !isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$graph_cf])) {
				/* use a user-specified ds path if one is entered */
				if (isset($graph_data_array['export_realtime'])) {
					$data_source_path = $realtimeCachePath . '/user_' . session_id() . '_' . $graph_item['local_data_id'] . '.rrd';
				} else {
					$data_source_path = get_data_source_path($graph_item['local_data_id'], true);
				}

				/* FOR WIN32: Escape all colon for drive letters (ex. D\:/path/to/rra) */
				$data_source_path = rrdtool_escape_string($data_source_path);

				if (!empty($data_source_path)) {
					/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
					to a function that matches the digits with letters. rrdtool likes letters instead
					of numbers in DEF names; especially with CDEF's. cdef's are created
					the same way, except a 'cdef' is put on the beginning of the hash */
					$graph_defs .= 'DEF:' . generate_graph_def_name(strval($i)) . '=' . cacti_escapeshellarg($data_source_path) . ':' . cacti_escapeshellarg($graph_item['data_source_name'], true) . ':' . $consolidation_functions[$graph_cf] . RRD_NL;

					$cf_ds_cache[$graph_item['data_template_rrd_id']][$graph_cf] = "$i";

					$i++;
				}
			}

			/* cache cdef value here to support data query variables in the cdef string */
			if (empty($graph_item['cdef_id'])) {
				$graph_item['cdef_cache'] = '';
				$graph_items[$j]['cdef_cache'] = '';
			} else {
				$cdef = get_cdef($graph_item['cdef_id']);

				$graph_item['cdef_cache'] = $cdef;
				$graph_items[$j]['cdef_cache'] = $cdef;
			}

			/* cache vdef value here */
			if (empty($graph_item['vdef_id'])) {
				$graph_item['vdef_cache'] = '';
				$graph_items[$j]['vdef_cache'] = '';
			} else {
				$vdef = get_vdef($graph_item['vdef_id']);

				$graph_item['vdef_cache'] = $vdef;
				$graph_items[$j]['vdef_cache'] = $vdef;
			}

			/* +++++++++++++++++++++++ LEGEND: TEXT SUBSTITUTION (<>'s) +++++++++++++++++++++++ */

			/* note the current item_id for easy access */
			$graph_item_id = $graph_item['graph_templates_item_id'];

			/* loop through each field that we want to substitute values for:
			currently: text format and value */
			foreach ($variable_fields as $field_name => $field_array) {
				/* certain fields do not require values when the legend is not to be shown */
				if ($field_array['process_no_legend'] == false && isset($graph_data_array['graph_nolegend'])) {
					continue;
				}

				$graph_variables[$field_name][$graph_item_id] = $graph_item[$field_name];

				$search  = array();
				$replace = array();

				/* date/time substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], '|date_time|')) {
					$search[]  = '|date_time|';
					$replace[] =  $dateTime;
				}

				/* data source title substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], '|data_source_title|')) {
					$search[]  = '|data_source_title|';
					$replace[] =  get_data_source_title($graph_item['local_data_id']);
				}

				/* data query variables */
				$graph_variables[$field_name][$graph_item_id] = rrd_substitute_host_query_data($graph_variables[$field_name][$graph_item_id], $graph, $graph_item);

				/* Nth percentile */
				if (preg_match_all('/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate):(\d)?\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$search[]  = $match[0];
						$replace[] = variable_nth_percentile($match, $graph_item, $graph_items, $graph_start, $graph_end);
					}
				}

				/* bandwidth summation */
				if (preg_match_all('/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$search[]  = $match[0];
						$replace[] = variable_bandwidth_summation($match, $graph_item, $graph_items, $graph_start, $graph_end, $rra['steps'], $ds_step);
					}
				}

				if (count($search)) {
					$graph_variables[$field_name][$graph_item_id] = str_replace($search, $replace, $graph_variables[$field_name][$graph_item_id]);
				}
			}

			/* if we are not displaying a legend there is no point in us even processing the auto padding,
			text format stuff. */
			if (!isset($graph_data_array['graph_nolegend'])) {
				/* set hard return variable if selected (\n) */
				if ($graph_item['hard_return'] == 'on') {
					$hardreturn[$graph_item_id] = "\\n";
				} else {
					$hardreturn[$graph_item_id] = '';
				}

				/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>'s) +++++++++++++++++++++++ */

				/* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
				AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
				of course it can be used in other situations, however may not work as intended.
				If you have any additions to this small peice of code, feel free to send them to me. */
				if ($graph['auto_padding'] == 'on') {
					/* only applies to AREA, STACK and LINEs */
					if (preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_item['graph_type_id']])) {
						$text_format_length = mb_strlen(trim($graph_variables['text_format'][$graph_item_id]), 'UTF-8');

						if ($text_format_length > $padding_estimate) {
							$padding_estimate = $text_format_length;
						}
					}
				}
			}

			$j++;
		}
	}

	/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's +++++++++++++++++++++++ */

	$i = 0;

	/* hack for rrdtool 1.2.x support */
	$graph_item_stack_type = '';

	if (sizeof($graph_items)) {
		foreach ($graph_items as $graph_item) {
			/* first we need to check if there is a DEF for the current data source/cf combination. if so,
			we will use that */
			if (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$graph_item['consolidation_function_id']])) {
				$cf_id = $graph_item['consolidation_function_id'];
			} else {
			/* if there is not a DEF defined for the current data source/cf combination, then we will have to
			improvise. choose the first available cf in the following order: AVERAGE, MAX, MIN, LAST */
				if (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][1])) {
					$cf_id = 1; /* CF: AVERAGE */
				} elseif (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][3])) {
					$cf_id = 3; /* CF: MAX */
				} elseif (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][2])) {
					$cf_id = 2; /* CF: MIN */
				} elseif (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][4])) {
					$cf_id = 4; /* CF: LAST */
				} else {
					$cf_id = 1; /* CF: AVERAGE */
				}
			}
			/* now remember the correct CF reference */
			$cf_id = $graph_item['cf_reference'];

			/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's START +++++++++++++++++++++++ */

			/* make cdef string here; a note about CDEF's in cacti. A CDEF is neither unique to a
			data source of global cdef, but is unique when those two variables combine. */
			$cdef_graph_defs = '';

			if ((!empty($graph_item['cdef_id'])) && (!isset($cdef_cache[$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id]))) {

				$cdef_string 	= $graph_variables['cdef_cache'][$graph_item['graph_templates_item_id']];
				$magic_item 	= array();
				$already_seen	= array();
				$sources_seen	= array();

				$count_all_ds_dups       = 0;
				$count_all_ds_nodups     = 0;
				$count_similar_ds_dups   = 0;
				$count_similar_ds_nodups = 0;

				//cacti_log('Original:' . $cdef_string);

				/* if any of those magic variables are requested ... */
				if (preg_match('/(ALL_DATA_SOURCES_(NO)?DUPS|SIMILAR_DATA_SOURCES_(NO)?DUPS)/', $cdef_string) ||
					preg_match('/(COUNT_ALL_DS_(NO)?DUPS|COUNT_SIMILAR_DS_(NO)?DUPS)/', $cdef_string)) {

					/* now walk through each case to initialize array*/
					if (preg_match('/ALL_DATA_SOURCES_DUPS/', $cdef_string)) {
						$magic_item['ALL_DATA_SOURCES_DUPS'] = '';
					}

					if (preg_match('/ALL_DATA_SOURCES_NODUPS/', $cdef_string)) {
						$magic_item['ALL_DATA_SOURCES_NODUPS'] = '';
					}

					if (preg_match('/SIMILAR_DATA_SOURCES_DUPS/', $cdef_string)) {
						$magic_item['SIMILAR_DATA_SOURCES_DUPS'] = '';
					}

					if (preg_match('/SIMILAR_DATA_SOURCES_NODUPS/', $cdef_string)) {
						$magic_item['SIMILAR_DATA_SOURCES_NODUPS'] = '';
					}

					if (preg_match('/COUNT_ALL_DS_DUPS/', $cdef_string)) {
						$magic_item['COUNT_ALL_DS_DUPS'] = '';
					}

					if (preg_match('/COUNT_ALL_DS_NODUPS/', $cdef_string)) {
						$magic_item['COUNT_ALL_DS_NODUPS'] = '';
					}

					if (preg_match('/COUNT_SIMILAR_DS_DUPS/', $cdef_string)) {
						$magic_item['COUNT_SIMILAR_DS_DUPS'] = '';
					}

					if (preg_match('/COUNT_SIMILAR_DS_NODUPS/', $cdef_string)) {
						$magic_item['COUNT_SIMILAR_DS_NODUPS'] = '';
					}

					/* loop over all graph items */
					foreach($graph_items as $gi_check) {
						/* only work on graph items, omit GRPINTs, COMMENTs and stuff */
						if ((preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$gi_check['graph_type_id']])) && (!empty($gi_check['data_template_rrd_id']))) {
							/* if the user screws up CF settings, PHP will generate warnings if left unchecked */

							/* matching consolidation function? */
							if (isset($cf_ds_cache[$gi_check['data_template_rrd_id']][$cf_id])) {
								$def_name = generate_graph_def_name(strval($cf_ds_cache[$gi_check['data_template_rrd_id']][$cf_id]));

								/* do we need ALL_DATA_SOURCES_DUPS? */
								if (isset($magic_item['ALL_DATA_SOURCES_DUPS'])) {
									$magic_item['ALL_DATA_SOURCES_DUPS'] .= ($count_all_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}

								/* do we need COUNT_ALL_DS_DUPS? */
								if (isset($magic_item['COUNT_ALL_DS_DUPS'])) {
									$magic_item['COUNT_ALL_DS_DUPS'] .= ($count_all_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}

								$count_all_ds_dups++;

								/* check if this item also qualifies for NODUPS  */
								if (!isset($already_seen[$def_name])) {
									if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
										$magic_item['ALL_DATA_SOURCES_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}

									if (isset($magic_item['COUNT_ALL_DS_NODUPS'])) {
										$magic_item['COUNT_ALL_DS_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}

									$count_all_ds_nodups++;
									$already_seen[$def_name] = true;
								}

								/* check for SIMILAR data sources */
								if ($graph_item['data_source_name'] == $gi_check['data_source_name']) {
									/* do we need SIMILAR_DATA_SOURCES_DUPS? */
									if (isset($magic_item['SIMILAR_DATA_SOURCES_DUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
										$magic_item['SIMILAR_DATA_SOURCES_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}

									/* do we need COUNT_SIMILAR_DS_DUPS? */
									if (isset($magic_item['COUNT_SIMILAR_DS_DUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
										$magic_item['COUNT_SIMILAR_DS_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}

									$count_similar_ds_dups++;

									/* check if this item also qualifies for NODUPS  */
									if(!isset($sources_seen[$gi_check['data_template_rrd_id']])) {
										if (isset($magic_item['SIMILAR_DATA_SOURCES_NODUPS'])) {
											$magic_item['SIMILAR_DATA_SOURCES_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
										}

										if (isset($magic_item['COUNT_SIMILAR_DS_NODUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
											$magic_item['COUNT_SIMILAR_DS_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
										}

										$count_similar_ds_nodups++;
										$sources_seen[$gi_check['data_template_rrd_id']] = TRUE;
									}
								} # SIMILAR data sources
							} # matching consolidation function?
						} # only work on graph items, omit GRPINTs, COMMENTs and stuff
					} #  loop over all graph items

					/* if there is only one item to total, don't even bother with the summation.
					 * Otherwise cdef=a,b,c,+,+ is fine. */
					if ($count_all_ds_dups > 1 && isset($magic_item['ALL_DATA_SOURCES_DUPS'])) {
						$magic_item['ALL_DATA_SOURCES_DUPS'] .= str_repeat(',+', ($count_all_ds_dups - 2)) . ',+';
					}

					if ($count_all_ds_nodups > 1 && isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
						$magic_item['ALL_DATA_SOURCES_NODUPS'] .= str_repeat(',+', ($count_all_ds_nodups - 2)) . ',+';
					}

					if ($count_similar_ds_dups > 1 && isset($magic_item['SIMILAR_DATA_SOURCES_DUPS'])) {
						$magic_item['SIMILAR_DATA_SOURCES_DUPS'] .= str_repeat(',+', ($count_similar_ds_dups - 2)) . ',+';
					}

					if ($count_similar_ds_nodups > 1 && isset($magic_item['SIMILAR_DATA_SOURCES_NODUPS'])) {
						$magic_item['SIMILAR_DATA_SOURCES_NODUPS'] .= str_repeat(',+', ($count_similar_ds_nodups - 2)) . ',+';
					}

					if ($count_all_ds_dups > 1 && isset($magic_item['COUNT_ALL_DS_DUPS'])) {
						$magic_item['COUNT_ALL_DS_DUPS'] .= str_repeat(',+', ($count_all_ds_dups - 2)) . ',+';
					}

					if ($count_all_ds_nodups > 1 && isset($magic_item['COUNT_ALL_DS_NODUPS'])) {
						$magic_item['COUNT_ALL_DS_NODUPS'] .= str_repeat(',+', ($count_all_ds_nodups - 2)) . ',+';
					}

					if ($count_similar_ds_dups > 1 && isset($magic_item['COUNT_SIMILAR_DS_DUPS'])) {
						$magic_item['COUNT_SIMILAR_DS_DUPS'] .= str_repeat(',+', ($count_similar_ds_dups - 2)) . ',+';
					}

					if ($count_similar_ds_nodups > 1 && isset($magic_item['COUNT_SIMILAR_DS_NODUPS'])) {
						$magic_item['COUNT_SIMILAR_DS_NODUPS'] .= str_repeat(',+', ($count_similar_ds_nodups - 2)) . ',+';
					}
				}

				/* allow automatic rate calculations on raw guage data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('CURRENT_DATA_SOURCE_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('CURRENT_DATA_SOURCE_PI', read_config_option('poller_interval'), $cdef_string);
				}

				$cdef_string = str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval((isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id]) ? $cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id] : '0'))), $cdef_string);

				/* allow automatic rate calculations on raw guage data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS_PI', read_config_option('poller_interval'), $cdef_string);
				}

				/* ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
				if (isset($magic_item['ALL_DATA_SOURCES_DUPS'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS', $magic_item['ALL_DATA_SOURCES_DUPS'], $cdef_string);
				}

				/* allow automatic rate calculations on raw guage data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS_PI', read_config_option('poller_interval'), $cdef_string);
				}

				if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS', $magic_item['ALL_DATA_SOURCES_NODUPS'], $cdef_string);
				}

				/* allow automatic rate calculations on raw guage data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_DUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_DUPS_PI', read_config_option('poller_interval'), $cdef_string);
				}

				if (isset($magic_item['SIMILAR_DATA_SOURCES_DUPS'])) {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_DUPS', $magic_item['SIMILAR_DATA_SOURCES_DUPS'], $cdef_string);
				}

				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_NODUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_NODUPS_PI', read_config_option('poller_id'), $cdef_string);
				}

				if (isset($magic_item['SIMILAR_DATA_SOURCES_NODUPS'])) {
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_NODUPS', $magic_item['SIMILAR_DATA_SOURCES_NODUPS'], $cdef_string);
				}

				/* COUNT_ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
				if (isset($magic_item['COUNT_ALL_DS_DUPS'])) {
					$cdef_string = str_replace('COUNT_ALL_DS_DUPS', $magic_item['COUNT_ALL_DS_DUPS'], $cdef_string);
				}
				if (isset($magic_item['COUNT_ALL_DS_NODUPS'])) {
					$cdef_string = str_replace('COUNT_ALL_DS_NODUPS', $magic_item['COUNT_ALL_DS_NODUPS'], $cdef_string);
				}
				if (isset($magic_item['COUNT_SIMILAR_DS_DUPS'])) {
					$cdef_string = str_replace('COUNT_SIMILAR_DS_DUPS', $magic_item['COUNT_SIMILAR_DS_DUPS'], $cdef_string);
				}
				if (isset($magic_item['COUNT_SIMILAR_DS_NODUPS'])) {
					$cdef_string = str_replace('COUNT_SIMILAR_DS_NODUPS', $magic_item['COUNT_SIMILAR_DS_NODUPS'], $cdef_string);
				}

				/* data source item variables */
				$cdef_string = str_replace('CURRENT_DS_MINIMUM_VALUE', (empty($graph_item['rrd_minimum']) ? '0' : $graph_item['rrd_minimum']), $cdef_string);
				$cdef_string = str_replace('CURRENT_DS_MAXIMUM_VALUE', (empty($graph_item['rrd_maximum']) ? '0' : $graph_item['rrd_maximum']), $cdef_string);
				$cdef_string = str_replace('CURRENT_GRAPH_MINIMUM_VALUE', (empty($graph['lower_limit']) ? '0' : $graph['lower_limit']), $cdef_string);
				$cdef_string = str_replace('CURRENT_GRAPH_MAXIMUM_VALUE', (empty($graph['upper_limit']) ? '0' : $graph['upper_limit']), $cdef_string);

				/* replace query variables in cdefs */
				$cdef_string = rrd_substitute_host_query_data($cdef_string, $graph, $graph_item);

				//cacti_log('Final:' . $cdef_string);

				/* make the initial 'virtual' cdef name: 'cdef' + [a,b,c,d...] */
				$cdef_graph_defs .= 'CDEF:cdef' . generate_graph_def_name(strval($i)) . '=';
				/* prohibit command injection and provide platform specific quoting */
				$cdef_graph_defs .= cacti_escapeshellarg(sanitize_cdef($cdef_string), true);
				$cdef_graph_defs .= " \\\n";

				/* the CDEF cache is so we do not create duplicate CDEF's on a graph */
				$cdef_cache[$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id] = $i;
			}

			/* add the cdef string to the end of the def string */
			$graph_defs .= $cdef_graph_defs;

			/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's END   +++++++++++++++++++++++ */

			/* +++++++++++++++++++++++ GRAPH ITEMS: VDEF's START +++++++++++++++++++++++ */

			/* make vdef string here, copied from cdef stuff */
			$vdef_graph_defs = '';

			if ((!empty($graph_item['vdef_id'])) && (!isset($vdef_cache[$graph_item['vdef_id']][$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id]))) {
				$vdef_string = $graph_variables['vdef_cache'][$graph_item['graph_templates_item_id']];
				/* do we refer to a CDEF within this VDEF? */
				if ($graph_item['cdef_id'] != '0') {
					/* 'calculated' VDEF: use (cached) CDEF as base, only way to get calculations into VDEFs */
					$vdef_string = 'cdef' . str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval(isset($cdef_cache[$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id]) ? $cdef_cache[$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id] : '0')), $vdef_string);
				} else {
					/* 'pure' VDEF: use DEF as base */
					$vdef_string = str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval(isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id]) ? $cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id] : '0')), $vdef_string);
				}

				# TODO: It would be possible to refer to a CDEF, but that's all. So ALL_DATA_SOURCES_NODUPS and stuff can't be used directly!
				# $vdef_string = str_replace('ALL_DATA_SOURCES_NODUPS', $magic_item['ALL_DATA_SOURCES_NODUPS'], $vdef_string);
				# $vdef_string = str_replace('ALL_DATA_SOURCES_DUPS', $magic_item['ALL_DATA_SOURCES_DUPS'], $vdef_string);
				# $vdef_string = str_replace('SIMILAR_DATA_SOURCES_NODUPS', $magic_item['SIMILAR_DATA_SOURCES_NODUPS'], $vdef_string);
				# $vdef_string = str_replace('SIMILAR_DATA_SOURCES_DUPS', $magic_item['SIMILAR_DATA_SOURCES_DUPS'], $vdef_string);

				/* make the initial 'virtual' vdef name */
				$vdef_graph_defs .= 'VDEF:vdef' . generate_graph_def_name(strval($i)) . '=';
				$vdef_graph_defs .= cacti_escapeshellarg(sanitize_cdef($vdef_string));
				$vdef_graph_defs .= " \\\n";

				/* the VDEF cache is so we do not create duplicate VDEF's on a graph,
				* but take info account, that same VDEF may use different CDEFs
				* so index over VDEF_ID, CDEF_ID per DATA_TEMPLATE_RRD_ID, lvm */
				$vdef_cache[$graph_item['vdef_id']][$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id] = $i;
			}

			/* add the cdef string to the end of the def string */
			$graph_defs .= $vdef_graph_defs;

			/* +++++++++++++++++++++++ GRAPH ITEMS: VDEF's END +++++++++++++++++++++++ */

			/* note the current item_id for easy access */
			$graph_item_id = $graph_item['graph_templates_item_id'];

			/* if we are not displaying a legend there is no point in us even processing the auto padding,
			text format stuff. */
			$pad_number = 0;

			if ((!isset($graph_data_array['graph_nolegend'])) && ($graph['auto_padding'] == 'on')) {
				/* only applies to AREA, STACK and LINEs */
				if (preg_match('/(AREA|STACK|LINE[123]|TICK)/', $graph_item_types[$graph_item['graph_type_id']])) {
					$text_format_length = mb_strlen($graph_variables['text_format'][$graph_item_id], 'UTF-8');

					$pad_number = $padding_estimate;
				} else if (($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_AVERAGE ||
					$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT ||
					$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_LAST ||
					$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_MAX ||
					$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_MIN) && (
					$last_graph_type == GRAPH_ITEM_TYPE_GPRINT ||
					$last_graph_type == GRAPH_ITEM_TYPE_GPRINT_AVERAGE ||
					$last_graph_type == GRAPH_ITEM_TYPE_GPRINT_LAST ||
					$last_graph_type == GRAPH_ITEM_TYPE_GPRINT_MAX ||
					$last_graph_type == GRAPH_ITEM_TYPE_GPRINT_MIN)) {

					/* get the maximum text_format length from the database */
					if (!isset($max_length)) {
						$max_length = db_fetch_cell_prepared('SELECT MAX(LENGTH(TRIM(text_format)))
							FROM graph_templates_item
							WHERE local_graph_id=?
							AND graph_type_id IN(' .
								GRAPH_ITEM_TYPE_LINE1 . ',' .
								GRAPH_ITEM_TYPE_LINE2 . ',' .
								GRAPH_ITEM_TYPE_LINE3 . ',' .
								GRAPH_ITEM_TYPE_LINESTACK . ',' .
								GRAPH_ITEM_TYPE_AREA . ',' .
								GRAPH_ITEM_TYPE_STACK . ')',
							array($local_graph_id));
					}

					$pad_number = $max_length;
				}

				$last_graph_type = $graph_item_types[$graph_item['graph_type_id']];
			}

			/* we put this in a variable so it can be manipulated before mainly used
			if we want to skip it, like below */
			$current_graph_item_type = $graph_item_types[$graph_item['graph_type_id']];

			/* IF this graph item has a data source... get a DEF name for it, or the cdef if that applies
			to this graph item */
			if ($graph_item['cdef_id'] == '0') {
				if (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id])) {
					$data_source_name = generate_graph_def_name(strval($cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id]));
				} else {
					$data_source_name = '';
				}
			} else {
				$data_source_name = 'cdef' . generate_graph_def_name(strval($cdef_cache[$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id]));
			}

			/* IF this graph item has a data source... get a DEF name for it, or the vdef if that applies
			to this graph item */
			if ($graph_item['vdef_id'] == '0') {
				/* do not overwrite $data_source_name that stems from cdef above */
			} else {
				$data_source_name = 'vdef' . generate_graph_def_name(strval($vdef_cache[$graph_item['vdef_id']][$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id]));
			}

			/* to make things easier... if there is no text format set; set blank text */
			if (!isset($graph_variables['text_format'][$graph_item_id])) {
				$graph_variables['text_format'][$graph_item_id] = '';
			}

			if (!isset($hardreturn[$graph_item_id])) {
				$hardreturn[$graph_item_id] = '';
			}

			/* +++++++++++++++++++++++ GRAPH ITEMS +++++++++++++++++++++++ */

			/* most of the calculations have been done above. now we have for print everything out
			in an RRDTool-friendly fashion */

			$need_rrd_nl = TRUE;

			/* initialize color support */
			$graph_item_color_code = '';
			if (!empty($graph_item['hex'])) {
				$graph_item_color_code = '#' . $graph_item['hex'];
				$graph_item_color_code .= $graph_item['alpha'];
			}

			/* initialize dash support */
			$dash = '';
			if ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE1 ||
				$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE2 ||
				$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE3 ||
				$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINESTACK ||
				$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_HRULE ||
				$graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_VRULE) {
				if (!empty($graph_item['dashes'])) {
					$dash .= ':dashes=' . $graph_item['dashes'];
				}

				if (!empty($graph_item['dash_offset'])) {
					$dash .= ':dash-offset=' . $graph_item['dash_offset'];
				}
			}

			if (!isset($graph_data_array['export_csv'])) {
				switch($graph_item['graph_type_id']) {
				case GRAPH_ITEM_TYPE_COMMENT:
					if (!isset($graph_data_array['graph_nolegend'])) {
						# perform variable substitution first (in case this will yield an empty results or brings command injection problems)
						$comment_arg = rrd_substitute_host_query_data($graph_variables['text_format'][$graph_item_id], $graph, $graph_item);
						# next, compute the argument of the COMMENT statement and perform injection counter measures
						if (trim($comment_arg) == '') { # an empty COMMENT must be treated with care
							$comment_arg = cacti_escapeshellarg(' ' . $hardreturn[$graph_item_id]);
						} else {
							$comment_arg = cacti_escapeshellarg(rrdtool_escape_string(htmlspecialchars($comment_arg, ENT_QUOTES, 'UTF-8')) . $hardreturn[$graph_item_id]);
						}

						# create rrdtool specific command line
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $comment_arg . ' ';
					}

					break;
				case GRAPH_ITEM_TYPE_TEXTALIGN:
					if (!isset($graph_data_array['graph_nolegend'])) {
						if (!empty($graph_item['textalign'])) {
							$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $graph_item['textalign'];
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT:
					$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . ':' . $consolidation_functions[$graph_item['consolidation_function_id']] . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
					} else {
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':AVERAGE:' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_LAST:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':LAST:' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_MAX:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':MAX:' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_MIN:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':MIN:' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_AREA:
					$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):'', ENT_QUOTES, 'UTF-8'));

					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . ' ';

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_STACK:
					$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id],$pad_number):'', ENT_QUOTES, 'UTF-8'));

					$txt_graph_items .= 'AREA:' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . ':STACK';

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_LINE1:
				case GRAPH_ITEM_TYPE_LINE2:
				case GRAPH_ITEM_TYPE_LINE3:
					$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):''), ENT_QUOTES, 'UTF-8');

					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . ' ';

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_LINESTACK:
					$txt_graph_items .= 'LINE' . $graph_item['line_width'] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg(rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8')) . $hardreturn[$graph_item_id]) . ':STACK' . $dash;

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_TIC:
					$_fraction = (empty($graph_item['graph_type_id']) ? '' : (':' . $graph_item['value']));
					$_legend   = ':' . cacti_escapeshellarg(rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8')) . $hardreturn[$graph_item_id]);
					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . $_fraction . $_legend;

					break;
				case GRAPH_ITEM_TYPE_HRULE:
					/* perform variable substitution; if this does not return a number, rrdtool will FAIL! */
					$substitute = rrd_substitute_host_query_data($graph_variables['value'][$graph_item_id], $graph, $graph_item);

					$graph_variables['text_format'][$graph_item_id] = rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8'));

					if (is_numeric($substitute)) {
						$graph_variables['value'][$graph_item_id] = $substitute;
					}

					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $graph_variables['value'][$graph_item_id] . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . '' . $dash;

					break;
				case GRAPH_ITEM_TYPE_VRULE:
					if (substr_count($graph_item['value'], ':')) {
						$value_array = explode(':', $graph_item['value']);

						if ($value_array[0] < 0) {
							$value = date('U') - (-3600 * $value_array[0]) - 60 * $value_array[1];
						} else {
							$value = date('U', mktime($value_array[0],$value_array[1],0));
						}

						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $value . $graph_item_color_code . ':' . cacti_escapeshellarg(rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8')) . $hardreturn[$graph_item_id]) . $dash;
					}else if (is_numeric($graph_item['value'])) {
						$value = $graph_item['value'];

						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $value . $graph_item_color_code . ':' . cacti_escapeshellarg(rrdtool_escape_string(htmlspecialchars($graph_variables['text_format'][$graph_item_id], ENT_QUOTES, 'UTF-8')) . $hardreturn[$graph_item_id]) . $dash;
					}

					break;
				default:
					$need_rrd_nl = FALSE;
				}
			} else {
				if (preg_match('/^(AREA|AREA:STACK|LINE[123]|STACK)$/', $graph_item_types[$graph_item['graph_type_id']])) {
					/* give all export items a name */
					if (trim($graph_variables['text_format'][$graph_item_id]) == '') {
						$legend_name = 'col' . $j . '-' . $data_source_name;
					} else {
						$legend_name = $graph_variables['text_format'][$graph_item_id];
					}
					$stacked_columns['col' . $j] = ($graph_item_types[$graph_item['graph_type_id']] == 'STACK') ? 1 : 0;
					$j++;

					$txt_graph_items .= 'XPORT:' . cacti_escapeshellarg($data_source_name) . ':' . str_replace(':', '', cacti_escapeshellarg($legend_name)) ;
				} else {
					$need_rrd_nl = FALSE;
				}
			}

			$i++;

			if (($i < sizeof($graph_items)) && ($need_rrd_nl)) {
				$txt_graph_items .= RRD_NL;
			}
		}
	}

	if (!isset($graph_data_array['export_csv']) || $graph_data_array['export_csv'] != true) {
		$graph_array = api_plugin_hook_function('rrd_graph_graph_options', array('graph_opts' => $graph_opts, 'graph_defs' => $graph_defs, 'txt_graph_items' => $txt_graph_items, 'graph_id' => $local_graph_id, 'start' => $graph_start, 'end' => $graph_end));

		if (!empty($graph_array)) {
			$graph_defs = $graph_array['graph_defs'];
			$txt_graph_items = $graph_array['txt_graph_items'];
			$graph_opts = $graph_array['graph_opts'];
		}

		/* either print out the source or pass the source onto rrdtool to get us a nice PNG */
		if (isset($graph_data_array['print_source'])) {
			print '<PRE>' . htmlspecialchars(read_config_option('path_rrdtool') . ' graph ' . $graph_opts . $graph_defs . $txt_graph_items) . '</PRE>';
		} else {
			if (isset($graph_data_array['graphv'])) {
				$graph = 'graphv';
			} else {
				$graph = 'graph';
			}

			if (isset($graph_data_array['get_error'])) {
				return rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, RRDTOOL_OUTPUT_STDERR);
			} elseif (isset($graph_data_array['export'])) {
				rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, RRDTOOL_OUTPUT_NULL, $rrdtool_pipe);

				return 0;
			} elseif (isset($graph_data_array['export_realtime'])) {
				$output_flag = RRDTOOL_OUTPUT_GRAPH_DATA;
				$output = rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe);

				if ($fp = fopen($graph_data_array['export_realtime'], 'w')) {
					fwrite($fp, $output, strlen($output));
					fclose($fp);
					chmod($graph_data_array['export_realtime'], 0644);
				}

				return $output;
			} else {
				$graph_data_array = boost_prep_graph_array($graph_data_array);

				if (!isset($graph_data_array['output_flag'])) {
					$output_flag = RRDTOOL_OUTPUT_GRAPH_DATA;
				} else {
					$output_flag = $graph_data_array['output_flag'];
				}

				$output = rrdtool_execute("$graph $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe);

				boost_graph_set_file($output, $local_graph_id, $rra_id);

				return $output;
			}
		}
	} else {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;

		$xport_array = rrdxport2array(rrdtool_execute("xport $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe));

		/* add host and graph information */
		$xport_array['meta']['stacked_columns']= $stacked_columns;
		$xport_array['meta']['title_cache']    = $graph['title_cache'];
		$xport_array['meta']['vertical_label'] = $graph['vertical_label'];
		$xport_array['meta']['local_graph_id'] = $local_graph_id;
		$xport_array['meta']['host_id']        = $graph['host_id'];

		return $xport_array;
	}
}

function rrdtool_escape_string($text) {
	return str_replace(array('"', ":", '%'), array('\"', "\:", ''), $text);
}

function rrdtool_function_xport($local_graph_id, $rra_id, $xport_data_array, &$xport_meta) {
	return rrdtool_function_graph($local_graph_id, $rra_id, $xport_data_array, '', $xport_meta);
}

function rrdtool_function_format_graph_date(&$graph_data_array) {
	global $datechar;

	$graph_legend = '';
	/* setup date format */
	$date_fmt = read_user_setting('default_date_format');
	$dateCharSetting = read_config_option('default_datechar');
	if (empty($dateCharSetting)) {
		$dateCharSetting = GDC_SLASH;
	}
	$datecharacter = $datechar[$dateCharSetting];

	switch ($date_fmt) {
		case GD_MO_D_Y:
			$graph_date = 'm' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
		case GD_MN_D_Y:
			$graph_date = 'M' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
		case GD_D_MO_Y:
			$graph_date = 'd' . $datecharacter . 'm' . $datecharacter . 'Y H:i:s';
			break;
		case GD_D_MN_Y:
			$graph_date = 'd' . $datecharacter . 'M' . $datecharacter . 'Y H:i:s';
			break;
		case GD_Y_MO_D:
			$graph_date = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
			break;
		case GD_Y_MN_D:
			$graph_date = 'Y' . $datecharacter . 'M' . $datecharacter . 'd H:i:s';
			break;
	}

	/* display the timespan for zoomed graphs */
	if ((isset($graph_data_array['graph_start'])) && (isset($graph_data_array['graph_end']))) {
		if (($graph_data_array['graph_start'] < 0) && ($graph_data_array['graph_end'] < 0)) {
			$graph_legend = "COMMENT:\"From " . str_replace(':', '\:', date($graph_date, time()+$graph_data_array['graph_start'])) . ' To ' . str_replace(':', '\:', date($graph_date, time()+$graph_data_array['graph_end'])) . "\\c\"" . RRD_NL . "COMMENT:\"  \\n\"" . RRD_NL;
		}else if (($graph_data_array['graph_start'] >= 0) && ($graph_data_array['graph_end'] >= 0)) {
			$graph_legend = "COMMENT:\"From " . str_replace(':', '\:', date($graph_date, $graph_data_array['graph_start'])) . ' To ' . str_replace(':', '\:', date($graph_date, $graph_data_array['graph_end'])) . "\\c\"" . RRD_NL . "COMMENT:\"  \\n\"" . RRD_NL;
		}
	}

	return $graph_legend;
}

function rrdtool_function_theme_font_options(&$graph_data_array) {
	global $config;

	/* implement theme colors */
	$graph_opts = '';
	$themefonts = array();

	if (isset($graph_data_array['graph_theme'])) {
		$rrdtheme = $config['base_path'] . '/include/themes/' . $graph_data_array['graph_theme'] . '/rrdtheme.php';
	} else {
		$rrdtheme = $config['base_path'] . '/include/themes/' . get_selected_theme() . '/rrdtheme.php';
	}

	if (file_exists($rrdtheme) && is_readable($rrdtheme)) {
		$rrdversion = str_replace('rrd-', '', str_replace('.x', '', read_config_option('rrdtool_version')));
		include($rrdtheme);

		if (isset($rrdcolors)) {
			foreach($rrdcolors as $colortag => $color) {
				$graph_opts .= '--color ' . strtoupper($colortag) . '#' . strtoupper($color) . RRD_NL;
			}
		}

		if (isset($rrdborder) && $rrdversion >= 1.4) {
			$graph_opts .= "--border $rrdborder " ;
		}

		if (isset($rrdfonts)) {
			$themefonts = $rrdfonts;
		}
	}

	/* title fonts */
	$graph_opts .= rrdtool_function_set_font('title', ((!empty($graph_data_array['graph_nolegend'])) ? $graph_data_array['graph_nolegend'] : ''), $themefonts);

	/* axis fonts */
	$graph_opts .= rrdtool_function_set_font('axis', '', $themefonts);

	/* legend fonts */
	$graph_opts .= rrdtool_function_set_font('legend', '', $themefonts);

	/* unit fonts */
	$graph_opts .= rrdtool_function_set_font('unit', '', $themefonts);

	/* watermark fonts */
	if (isset($rrdversion) && $rrdversion > 1.3) {
		$graph_opts .= rrdtool_function_set_font('watermark', '', $themefonts);
	}

	return $graph_opts;
}

function rrdtool_set_font($type, $no_legend = '', $themefonts = array()) {
	return rrdtool_function_set_font($type, $no_legend, $themefonts);
}

function rrdtool_function_set_font($type, $no_legend, $themefonts) {
	global $config;

	if (read_config_option('font_method') == 0) {
		if (read_user_setting('custom_fonts') == 'on') {
			$font = read_user_setting($type . '_font');
			$size = read_user_setting($type . '_size');
		} else {
			$font = read_config_option($type . '_font');
			$size = read_config_option($type . '_size');
		}
	} elseif (isset($themefonts[$type]['font']) && isset($themefonts[$type]['size'])) {
		$font = $themefonts[$type]['font'];
		$size = $themefonts[$type]['size'];
	} else {
		return;
	}

	if($font != '') {
		/* verifying all possible pango font params is too complex to be tested here
		 * so we only escape the font
		 */
		$font = cacti_escapeshellarg($font);
	}

	if ($type == 'title') {
		if (!empty($no_legend)) {
			$size = $size * .70;
		} elseif (($size <= 4) || !is_numeric($size)) {
			$size = 12;
		}
	}else if (($size <= 4) || !is_numeric($size)) {
		$size = 8;
	}

	return '--font ' . strtoupper($type) . ':' . floatval($size) . ':' . $font . RRD_NL;
}

function rrd_substitute_host_query_data($txt_graph_item, $graph, $graph_item) {
	/* replace host variables in graph elements */
	$host_id = 0;
	if (empty($graph['host_id'])) {
		/* if graph has no associated host determine host_id from graph item data source */
		if (isset($graph_item['local_data_id']) && !empty($graph_item['local_data_id'])) {
			$host_id = db_fetch_cell_prepared('SELECT host_id FROM data_local WHERE id = ?', array($graph_item['local_data_id']));
		}
	} else {
		$host_id = $graph['host_id'];
	}
	$txt_graph_item = substitute_host_data($txt_graph_item, '|','|', $host_id);

	/* replace query variables in graph elements */
	if (preg_match('/\|query_[a-zA-Z0-9_]+\|/', $txt_graph_item)) {
		/* default to the graph data query information from the graph */
		if (!isset($graph_item['local_data_id']) || empty($graph_item['local_data_id'])) {
			$txt_graph_item = substitute_snmp_query_data($txt_graph_item, $graph['host_id'], $graph['snmp_query_id'], $graph['snmp_index']);
		/* use the data query information from the data source if possible */
		} else {
			$data_local = db_fetch_row_prepared('SELECT snmp_index, snmp_query_id, host_id FROM data_local WHERE id = ?', array($graph_item['local_data_id']));
			$txt_graph_item = substitute_snmp_query_data($txt_graph_item, $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
		}
	}

	/* replace query variables in graph elements */
	if (preg_match('/\|input_[a-zA-Z0-9_]+\|/', $txt_graph_item)) {
		return substitute_data_input_data($txt_graph_item, $graph, $graph_item['local_data_id']);
	}

	return $txt_graph_item;
}

/** given a data source id, return rrdtool info array
 * @param $data_source_id - data source id
 * @return - (array) an array containing all data from rrdtool info command
 */
function rrdtool_function_info($data_source_id) {
	/* Get the path to rrdtool file */
	$data_source_path = get_data_source_path($data_source_id, true);

	/* Execute rrdtool info command */
	$cmd_line = ' info ' . $data_source_path;
	$output = rrdtool_execute($cmd_line, RRDTOOL_OUTPUT_NULL, RRDTOOL_OUTPUT_STDOUT);
	if (sizeof($output) == 0) {
		return false;
	}

	/* Parse the output */
	$matches  = array();
	$rrd_info = array('rra' => array(), 'ds' => array());
	$output   = explode("\n", $output);

	foreach ($output as $line) {
		$line = trim($line);
		if (preg_match('/^ds\[(\S+)\]\.(\S+) = (\S+)$/', $line, $matches)) {
			$rrd_info['ds'][$matches[1]][$matches[2]] = trim($matches[3], '"');
		} elseif (preg_match('/^rra\[(\S+)\]\.(\S+)\[(\S+)\]\.(\S+) = (\S+)$/', $line, $matches)) {
			$rrd_info['rra'][$matches[1]][$matches[2]][$matches[3]][$matches[4]] = trim($matches[5], '"');
		} elseif (preg_match('/^rra\[(\S+)\]\.(\S+) = (\S+)$/', $line, $matches)) {
			$rrd_info['rra'][$matches[1]][$matches[2]] = trim($matches[3], '"');
		} elseif (preg_match("/^(\S+) = \"(\S+)\"$/", $line, $matches)) {
			$rrd_info[$matches[1]] = trim($matches[2], '"');
		} elseif (preg_match('/^(\S+) = (\S+)$/', $line, $matches)) {
			$rrd_info[$matches[1]] = trim($matches[2], '"');
		}
	}

	$output = '';
	$matches = array();

	/* Return parsed values */
	return $rrd_info;
}

/** rrdtool_cacti_compare 	compares cacti information to rrd file information
 * @param $data_source_id		the id of the data source
 * @param $info				rrdtool info as an array
 * @return					array build like $info defining html class in case of error
 */
function rrdtool_cacti_compare($data_source_id, &$info) {
	global $data_source_types, $consolidation_functions;

	/* get cacti header information for given data source id */
	$cacti_header_array = db_fetch_row_prepared('SELECT
		local_data_template_data_id, rrd_step, data_source_profile_id
		FROM data_template_data
		WHERE local_data_id = ?', array($data_source_id));

	/* get cacti DS information */
	$cacti_ds_array = db_fetch_assoc_prepared('SELECT data_source_name, data_source_type_id,
		rrd_heartbeat, rrd_maximum, rrd_minimum
		FROM data_template_rrd
		WHERE local_data_id = ?', array($data_source_id));

	/* get cacti RRA information */
	$cacti_rra_array = db_fetch_assoc_prepared('SELECT
		dspc.consolidation_function_id AS cf,
		dsp.x_files_factor AS xff,
		dspr.steps AS steps,
		dspr.rows AS rows
		FROM data_source_profiles AS dsp
		INNER JOIN data_source_profiles_cf AS dspc
		ON dsp.id=dspc.data_source_profile_id
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		WHERE dsp.id = ?
		ORDER BY dspc.consolidation_function_id, dspr.steps',
		array($cacti_header_array['data_source_profile_id']));

	$diff = array();
	/* -----------------------------------------------------------------------------------
	 * header information
	 -----------------------------------------------------------------------------------*/
	if ($cacti_header_array['rrd_step'] != $info['step']) {
		$diff['step'] = __("Required RRD step size is '%s'", $cacti_header_array['rrd_step']);
	}

	/* -----------------------------------------------------------------------------------
	 * data source information
	 -----------------------------------------------------------------------------------*/
	if (sizeof($cacti_ds_array) > 0) {
		$data_local = db_fetch_row_prepared('SELECT host_id,
			snmp_query_id, snmp_index
			FROM data_local
			WHERE id = ?',
			array($data_source_id)
		);

		$highSpeed = db_fetch_cell_prepared('SELECT field_value
			FROM host_snmp_cache
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND snmp_index = ?
			AND field_name="ifHighSpeed"',
			array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index'])
		);

		$ssqdIfSpeed = substitute_snmp_query_data('|query_ifSpeed|', $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);

		foreach ($cacti_ds_array as $key => $data_source) {
			$ds_name = $data_source['data_source_name'];

			/* try to print matching rrd file's ds information */
			if (isset($info['ds'][$ds_name]) ) {
				if (!isset($info['ds'][$ds_name]['seen'])) {
					$info['ds'][$ds_name]['seen'] = TRUE;
				} else {
					continue;
				}

				$ds_type = trim($info['ds'][$ds_name]['type'], '"');
				if ($data_source_types[$data_source['data_source_type_id']] != $ds_type) {
					$diff['ds'][$ds_name]['type'] = __("Type for Data Source '%s' should be '%s'", $ds_name, $data_source_types[$data_source['data_source_type_id']]);
					$diff['tune'][] = $info['filename'] . ' ' . '--data-source-type ' . $ds_name . ':' . $data_source_types[$data_source['data_source_type_id']];
				}

				if ($data_source['rrd_heartbeat'] != $info['ds'][$ds_name]['minimal_heartbeat']) {
					$diff['ds'][$ds_name]['minimal_heartbeat'] = __("Heartbeat for Data Source '%s' should be '%s'", $ds_name, $data_source['rrd_heartbeat']);
					$diff['tune'][] = $info['filename'] . ' ' . '--heartbeat ' . $ds_name . ':' . $data_source['rrd_heartbeat'];
				}

				if ($data_source['rrd_minimum'] != $info['ds'][$ds_name]['min']) {
					if ($data_source['rrd_minimum'] == 'U' && $info['ds'][$ds_name]['min'] == 'NaN') {
						$data_source['rrd_minimum'] = 'NaN';
					} else {
						$diff['ds'][$ds_name]['min'] = __("RRD minimum for Data Source '%s' should be '%s'", $ds_name, $data_source['rrd_minimum']);
						$diff['tune'][] = $info['filename'] . ' ' . '--minimum ' . $ds_name . ':' . $data_source['rrd_minimum'];
					}
				}

				if ($data_source['rrd_maximum'] != $info['ds'][$ds_name]['max']) {
					if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
						if (!empty($highSpeed)) {
							$data_source['rrd_maximum'] = $highSpeed * 1000000;
						} else {
							$data_source['rrd_maximum'] = $ssqdIfSpeed;
						}
					} elseif ($data_source['rrd_maximum'] == '0' || $data_source['rrd_maximum'] == 'U') {
						$data_source['rrd_maximum'] = 'NaN';
					}else {
						$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'], $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
					}

					if (empty($data_source['rrd_maximum']) || $data_source['rrd_maximum'] == '|query_ifSpeed|') {
						$data_source['rrd_maximum'] = '10000000000000';
					}
				}

				if ($data_source['rrd_maximum'] != $info['ds'][$ds_name]['max']) {
					$diff['ds'][$ds_name]['max'] = __("RRD maximum for Data Source '%s' should be '%s'", $ds_name, $data_source['rrd_maximum']);
					$diff['tune'][] = $info['filename'] . ' ' . '--maximum ' . $ds_name . ':' . $data_source['rrd_maximum'];
				}
			} else {
				# cacti knows this ds, but the rrd file does not
				$info['ds'][$ds_name]['type'] = $data_source_types[$data_source['data_source_type_id']];
				$info['ds'][$ds_name]['minimal_heartbeat'] = $data_source['rrd_heartbeat'];
				$info['ds'][$ds_name]['min'] = $data_source['rrd_minimum'];
				$info['ds'][$ds_name]['max'] = $data_source['rrd_maximum'];
				$info['ds'][$ds_name]['seen'] = TRUE;
				$diff['ds'][$ds_name]['error'] = __("DS '%s' missing in RRDfile", $ds_name);
			}
		}
	}

	/* print all data sources still known to the rrd file (no match to cacti ds will happen here) */
	if (sizeof($info['ds']) > 0) {
		foreach ($info['ds'] as $ds_name => $data_source) {
			if (!isset($data_source['seen'])) {
				$diff['ds'][$ds_name]['error'] = __("DS '%s' missing in Cacti definition", $ds_name);
			}
		}
	}

	/* -----------------------------------------------------------------------------------
	 * RRA information
	 -----------------------------------------------------------------------------------*/
	$resize = TRUE;		# assume a resize operation as long as no rra duplicates are found

	/* scan cacti rra information for duplicates of (CF, STEPS) */
	if (sizeof($cacti_rra_array) > 0) {
		for ($i=0; $i<= sizeof($cacti_rra_array)-1; $i++) {
			$cf = $cacti_rra_array{$i}['cf'];
			$steps = $cacti_rra_array{$i}['steps'];
			foreach($cacti_rra_array as $cacti_rra_id => $cacti_rra) {
				if ($cf == $cacti_rra['cf'] && $steps == $cacti_rra['steps'] && ($i != $cacti_rra_id)) {
					$diff['rra'][$i]['error'] = __("Cacti RRA '%s' has same CF/steps (%s, %s) as '%s'", $i, $consolidation_functions{$cf}, $steps, $cacti_rra_id);
					$diff['rra'][$cacti_rra_id]['error'] = __("Cacti RRA '%s' has same CF/steps (%s, %s) as '%s'", $cacti_rra_id, $consolidation_functions{$cf}, $steps, $i);
					$resize = FALSE;
				}
			}
		}
	}

	/* scan file rra information for duplicates of (CF, PDP_PER_ROWS) */
	if (sizeof($info['rra']) > 0) {
		for ($i=0; $i<= sizeof($info['rra'])-1; $i++) {
			$cf = $info['rra']{$i}['cf'];
			$steps = $info['rra']{$i}['pdp_per_row'];
			foreach($info['rra'] as $file_rra_id => $file_rra) {
				if (($cf == $file_rra['cf']) && ($steps == $file_rra['pdp_per_row']) && ($i != $file_rra_id)) {
					$diff['rra'][$i]['error'] = __("File RRA '%s' has same CF/steps (%s, %s) as '%s'", $i, $cf, $steps, $file_rra_id);
					$diff['rra'][$file_rra_id]['error'] = __("File RRA '%s' has same CF/steps (%s, %s) as '%s'", $file_rra_id, $cf, $steps, $i);
					$resize = FALSE;
				}
			}
		}
	}

	/* print all RRAs known to cacti and add those from matching rrd file */
	if (sizeof($cacti_rra_array) > 0) {
		foreach($cacti_rra_array as $cacti_rra_id => $cacti_rra) {
			/* find matching rra info from rrd file
			 * do NOT assume, that rra sequence is kept ($cacti_rra_id != $file_rra_id may happen)!
			 * Match is assumed, if CF and STEPS/PDP_PER_ROW match; so go for it */
			foreach ($info['rra'] as $file_rra_id => $file_rra) {
				/* in case of mismatch, $file_rra['pdp_per_row'] might not be defined */
				if (!isset($file_rra['pdp_per_row'])) {
					$file_rra['pdp_per_row'] = 0;
				}

				if ($consolidation_functions{$cacti_rra['cf']} == trim($file_rra['cf'], '"') &&
					$cacti_rra['steps'] == $file_rra['pdp_per_row']) {

					if (isset($info['rra'][$file_rra_id]['seen'])) {
						continue;
					}

					# mark both rra id's as seen to avoid printing them as non-matching
					$info['rra'][$file_rra_id]['seen'] = TRUE;
					$cacti_rra_array[$cacti_rra_id]['seen'] = TRUE;

					if ($cacti_rra['xff'] != $file_rra['xff']) {
						$diff['rra'][$file_rra_id]['xff'] = __("XFF for cacti RRA id '%s' should be '%s'", $cacti_rra_id, $cacti_rra['xff']);
					}

					if ($cacti_rra['rows'] != $file_rra['rows'] && $resize) {
						$diff['rra'][$file_rra_id]['rows'] = __("Number of rows for Cacti RRA id '%s' should be '%s'", $cacti_rra_id, $cacti_rra['rows']);
						if ($cacti_rra['rows'] > $file_rra['rows']) {
							$diff['resize'][] = $info['filename'] . ' ' . $cacti_rra_id . ' GROW ' . ($cacti_rra['rows'] - $file_rra['rows']);
						} else {
							$diff['resize'][] = $info['filename'] . ' ' . $cacti_rra_id . ' SHRINK ' . ($file_rra['rows'] - $cacti_rra['rows']);
						}
					}
				}
			}
			# if cacti knows an rra that has no match, consider this as an error
			if (!isset($cacti_rra_array[$cacti_rra_id]['seen'])) {
				# add to info array for printing, the index $cacti_rra_id has no real meaning
				$info['rra']['cacti_' . $cacti_rra_id]['cf']    = $consolidation_functions{$cacti_rra['cf']};
				$info['rra']['cacti_' . $cacti_rra_id]['steps'] = $cacti_rra['steps'];
				$info['rra']['cacti_' . $cacti_rra_id]['xff']   = $cacti_rra['xff'];
				$info['rra']['cacti_' . $cacti_rra_id]['rows']  = $cacti_rra['rows'];
				$diff['rra']['cacti_' . $cacti_rra_id]['error'] = __("RRA '%s' missing in RRDfile", $cacti_rra_id);
			}
		}
	}

	# if the rrd file has an rra that has no cacti match, consider this as an error
	if (sizeof($info['rra']) > 0) {
		foreach ($info['rra'] as $file_rra_id => $file_rra) {
			if (!isset($info['rra'][$file_rra_id]['seen'])) {
				$diff['rra'][$file_rra_id]['error'] = __("RRA '%s' missing in Cacti definition", $file_rra_id);
			}
		}
	}

	return $diff;

}

/** take output from rrdtool info array and build html table
 * @param array $info_array - array of rrdtool info data
 * @param array $diff - array of differences between definition and current rrd file settings
 * @return string - html code
 */
function rrdtool_info2html($info_array, $diff=array()) {
	global $config;

	include_once($config['library_path'] . '/time.php');

	html_start_box(__('RRD File Information'), '100%', '', '3', 'center', '');

	# header data
	$header_items = array(
		array('display' =>__('Header'), 'align' => 'left'),
		array('display' => '', 'align' => 'left')
	);

	html_header($header_items, 1);

	# add human readable timestamp
	if (isset($info_array['last_update'])) {
		$info_array['last_update'] .= ' [' . date(CACTI_DATE_TIME_FORMAT, $info_array['last_update']) . ']';
	}

	$loop = array(
		'filename' 		=> $info_array['filename'],
		'rrd_version'	=> $info_array['rrd_version'],
		'step' 			=> $info_array['step'],
		'last_update'	=> $info_array['last_update']);

	foreach ($loop as $key => $value) {
		form_alternate_row($key, true);
		form_selectable_cell($key, 'key');
		form_selectable_cell($value, 'value', '', ((isset($diff[$key]) ? 'color:red' : '')));
		form_end_row();
	}

	html_end_box();

	# data sources
	$header_items = array(
		array('display' => __('Data Source Items'), 'align' => 'left'),
		array('display' => __('Type'),              'align' => 'left'),
		array('display' => __('Minimal Heartbeat'), 'align' => 'right'),
		array('display' => __('Min'),               'align' => 'right'),
		array('display' => __('Max'),               'align' => 'right'),
		array('display' => __('Last DS'),           'align' => 'right'),
		array('display' => __('Value'),             'align' => 'right'),
		array('display' => __('Unknown Sec'),       'align' => 'right')
	);

	html_start_box('', '100%', '', '3', 'center', '');

	html_header($header_items, 1);

	if (sizeof($info_array['ds'])) {
		foreach ($info_array['ds'] as $key => $value) {
			form_alternate_row('line' . $key, true);

			form_selectable_cell($key, 'name', '', (isset($diff['ds'][$key]['error']) ? 'color:red' : ''));
			form_selectable_cell((isset($value['type']) ? $value['type'] : ''), 'type', '', (isset($diff['ds'][$key]['type']) ? 'color:red' : ''));
			form_selectable_cell((isset($value['minimal_heartbeat']) ? $value['minimal_heartbeat'] : ''), 'minimal_heartbeat', '', (isset($diff['ds'][$key]['minimal_heartbeat']) ? 'color:red, text-align:right' : 'text-align:right'));
			form_selectable_cell((isset($value['min']) && is_numeric($value['min']) ? number_format_i18n($value['min']): (isset($value['min']) ? $value['min']:'')), 'min', '', (isset($diff['ds'][$key]['min']) ? 'color:red;text-align:right' : 'text-align:right'));
			form_selectable_cell((isset($value['max']) && is_numeric($value['max']) ? number_format_i18n($value['max']): (isset($value['max']) ? $value['max']:'')), 'max', '', (isset($diff['ds'][$key]['max']) ? 'color:red;text-align:right' : 'text-align:right'));
			form_selectable_cell((isset($value['last_ds']) && is_numeric($value['last_ds']) ? number_format_i18n($value['last_ds']) : (isset($value['last_ds']) ? $value['last_ds']:'')), 'last_ds', '', 'text-align:right');
			form_selectable_cell((isset($value['value']) ? is_numeric($value['value']) ? number_format_i18n($value['value']) : $value['value'] : ''), 'value', '', 'text-align:right');
			form_selectable_cell((isset($value['unknown_sec']) && is_numeric($value['unknown_sec']) ? number_format_i18n($value['unknown_sec']) : (isset($value['unknown_sec']) ? $value['unknown_sec']:'')), 'unknown_sec', '', 'text-align:right');

			form_end_row();
		}
	}

	html_end_box();

	# round robin archive
	$header_items = array(
		array('display' => __('Round Robin Archive'),         'align' => 'left'),
		array('display' => __('Consolidation Function'),      'align' => 'left'),
		array('display' => __('Rows'),                        'align' => 'right'),
		array('display' => __('Cur Row'),                     'align' => 'right'),
		array('display' => __('PDP per Row'),                 'align' => 'right'),
		array('display' => __('X Files Factor'),              'align' => 'right'),
		array('display' => __('CDP Prep Value (0)'),          'align' => 'right'),
		array('display' => __('CDP Unknown Data points (0)'), 'align' => 'right')
	);

	html_start_box('', '100%', '', '3', 'center', '');

	html_header($header_items, 1);

	if (sizeof($info_array['rra'])) {
		foreach ($info_array['rra'] as $key => $value) {
			form_alternate_row('line_' . $key, true);

			form_selectable_cell($key, 'name', '', (isset($diff['rra'][$key]['error']) ? 'color:red' : ''));
			form_selectable_cell((isset($value['cf']) ? $value['cf'] : ''), 'cf');
			form_selectable_cell((isset($value['rows']) ? $value['rows'] : ''), 'rows', '', (isset($diff['rra'][$key]['rows']) 	? 'color:red;text-align:right' : 'text-align:right'));
			form_selectable_cell((isset($value['cur_row']) ? $value['cur_row'] : ''), 'cur_row', '', 'text-align:right');
			form_selectable_cell((isset($value['pdp_per_row']) ? $value['pdp_per_row'] : ''), 'pdp_per_row', '', 'text-align:right');
			form_selectable_cell((isset($value['xff']) ? floatval($value['xff']) : ''), 'xff', '', (isset($diff['rra'][$key]['xff']) 	? 'color:red;text-align:right' : 'text-align:right'));
			form_selectable_cell((isset($value['cdp_prep'][0]['value']) ? (strtolower($value['cdp_prep'][0]['value']) == 'nan') ? $value['cdp_prep'][0]['value'] : floatval($value['cdp_prep'][0]['value']) : ''), 'value', '', 'text-align:right');
			form_selectable_cell((isset($value['cdp_prep'][0]['unknown_datapoints'])? $value['cdp_prep'][0]['unknown_datapoints'] : ''), 	'unknown_datapoints', '', 'text-align:right');

			form_end_row();
		}
	}

	html_end_box();
}

/** rrdtool_tune			- create rrdtool tune/resize commands
 * 						  html+cli enabled
 * @param $rrd_file		- rrd file name
 * @param $diff			- array of discrepancies between cacti setttings and rrd file info
 * @param $show_source	- only show text+commands or execute all commands, execute is for cli mode only!
 */
function rrdtool_tune($rrd_file, $diff, $show_source = true) {
	function print_leaves($array, $nl) {
		foreach ($array as $key => $line) {
			if (!is_array($line)) {
				print $line . $nl;
			} else {
				if ($key === 'tune') continue;
				if ($key === 'resize') continue;
				print_leaves($line, $nl);
			}
		}

	}


	$cmd = array();
	# for html/cli mode
	if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
		$nl = '<br/>';
	} else {
		$nl = "\n";
	}

	if ($show_source && sizeof($diff)) {
		# print error descriptions
		print_leaves($diff, $nl);
	}

	if (isset($diff['tune']) && sizeof($diff['tune'])) {
		# create tune commands
		foreach ($diff['tune'] as $line) {
			if ($show_source == true) {
				print read_config_option('path_rrdtool') . ' tune ' . $line . $nl;
			} else {
				rrdtool_execute("tune $line", true, RRDTOOL_OUTPUT_STDOUT);
			}
		}
	}

	if (isset($diff['resize']) && sizeof($diff['resize'])) {
		# each resize goes into an extra line
		foreach ($diff['resize'] as $line) {
			if ($show_source == true) {
				print read_config_option('path_rrdtool') . ' resize ' . $line . $nl;
				print __('rename %s to %s', dirname($rrd_file) . '/resize.rrd', $rrd_file) . $nl;
			} else {
				rrdtool_execute("resize $line", true, RRDTOOL_OUTPUT_STDOUT);
				rename(dirname($rrd_file) . '/resize.rrd', $rrd_file);
			}
		}
	}
}

/** Given a data source id, check the rrdtool file to the data source definition
 * @param $data_source_id - data source id
 * @return - (array) an array containing issues with the rrdtool file definition vs data source
 */
function rrd_check($data_source_id) {
	global $rrd_tune_array, $data_source_types;

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types{$rrd_tune_array['data-source-type']};
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);
}

/** Given a data source id, update the rrdtool file to match the data source definition
 * @param $data_source_id - data source id
 * @return - 1 success, 2 false
 */
function rrd_repair($data_source_id) {
	global $rrd_tune_array, $data_source_types;

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types{$rrd_tune_array['data-source-type']};
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);
}

/** add a (list of) datasource(s) to an (array of) rrd file(s)
 * @param array $file_array	- array of rrd files
 * @param array $ds_array	- array of datasouce parameters
 * @param bool $debug		- debug mode
 * @return mixed			- success (bool) or error message (array)
 */
function rrd_datasource_add($file_array, $ds_array, $debug) {
	global $data_source_types, $consolidation_functions;

	$rrdtool_pipe = rrd_init();

	/* iterate all given rrd files */
	foreach ($file_array as $file) {
		/* create a DOM object from an rrdtool dump */
		$dom = new domDocument;
		$dom->loadXML(rrdtool_execute("dump $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL'));
		if (!$dom) {
			$check['err_msg'] = __('Error while parsing the XML of rrdtool dump');
			return $check;
		}

		/* rrdtool dump depends on rrd file version:
		 * version 0001 => RRDTool 1.0.x
		 * version 0003 => RRDTool 1.2.x, 1.3.x, 1.4.x, 1.5.x, 1.6.x
		 */
		$version = trim($dom->getElementsByTagName('version')->item(0)->nodeValue);

		/* now start XML processing */
		foreach ($ds_array as $ds) {
			/* first, append the <DS> strcuture in the rrd header */
			if ($ds['type'] === $data_source_types[DATA_SOURCE_TYPE_COMPUTE]) {
				rrd_append_compute_ds($dom, $version, $ds['name'], $ds['type'], $ds['cdef']);
			} else {
				rrd_append_ds($dom, $version, $ds['name'], $ds['type'], $ds['heartbeat'], $ds['min'], $ds['max']);
			}
			/* now work on the <DS> structure as part of the <cdp_prep> tree */
			rrd_append_cdp_prep_ds($dom, $version);
			/* add <V>alues to the <database> tree */
			rrd_append_value($dom);
		}

		if ($debug) {
			echo $dom->saveXML();
		} else {
			/* for rrdtool restore, we need a file, so write the XML to disk */
			$xml_file = $file . '.xml';
			$rc = $dom->save($xml_file);
			/* verify, if write was successful */
			if ($rc === false) {
				$check['err_msg'] = __('ERROR while writing XML file: %s', $xml_file);
				return $check;
			} else {
				/* are we allowed to write the rrd file? */
				if (is_writable($file)) {
					/* restore the modified XML to rrd */
					rrdtool_execute("restore -f $xml_file $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL');
					/* scratch that XML file to avoid filling up the disk */
					unlink($xml_file);
					cacti_log(__('Added Data Source(s) to RRDfile: %s', $file), false, 'UTIL');
				} else {
					$check['err_msg'] = __('ERROR: RRDfile %s not writeable', $file);
					return $check;
				}
			}
		}
	}

	rrd_close($rrdtool_pipe);

	return true;
}

/** delete a (list of) rra(s) from an (array of) rrd file(s)
 * @param array $file_array	- array of rrd files
 * @param array $rra_array	- array of rra parameters
 * @param bool $debug		- debug mode
 * @return mixed			- success (bool) or error message (array)
 */
function rrd_rra_delete($file_array, $rra_array, $debug) {
	$rrdtool_pipe = '';

	/* iterate all given rrd files */
	foreach ($file_array as $file) {
		/* create a DOM document from an rrdtool dump */
		$dom = new domDocument;
		$dom->loadXML(rrdtool_execute("dump $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL'));
		if (!$dom) {
			$check['err_msg'] = __('Error while parsing the XML of RRDtool dump');
			return $check;
		}

		/* now start XML processing */
		foreach ($rra_array as $rra) {
			rrd_delete_rra($dom, $rra, $debug);
		}

		if ($debug) {
			echo $dom->saveXML();
		} else {
			/* for rrdtool restore, we need a file, so write the XML to disk */
			$xml_file = $file . '.xml';
			$rc = $dom->save($xml_file);
			/* verify, if write was successful */
			if ($rc === false) {
				$check['err_msg'] = __('ERROR while writing XML file: %s', $xml_file);
				return $check;
			} else {
				/* are we allowed to write the rrd file? */
				if (is_writable($file)) {
					/* restore the modified XML to rrd */
					rrdtool_execute("restore -f $xml_file $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL');
					/* scratch that XML file to avoid filling up the disk */
					unlink($xml_file);
					cacti_log(__('Deleted RRA(s) from RRDfile: %s', $file), false, 'UTIL');
				} else {
					$check['err_msg'] = __('ERROR: RRDfile %s not writeable', $file);
					return $check;
				}
			}
		}
	}

	rrd_close($rrdtool_pipe);

	return true;
}

/** clone a (list of) rra(s) from an (array of) rrd file(s)
 * @param array $file_array	- array of rrd files
 * @param string $cf		- new consolidation function
 * @param array $rra_array	- array of rra parameters
 * @param bool $debug		- debug mode
 * @return mixed			- success (bool) or error message (array)
 */
function rrd_rra_clone($file_array, $cf, $rra_array, $debug) {
	$rrdtool_pipe = '';

	/* iterate all given rrd files */
	foreach ($file_array as $file) {
		/* create a DOM document from an rrdtool dump */
		$dom = new domDocument;
		$dom->loadXML(rrdtool_execute("dump $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL'));
		if (!$dom) {
			$check['err_msg'] = __('Error while parsing the XML of RRDtool dump');
			return $check;
		}

		/* now start XML processing */
		foreach ($rra_array as $rra) {
			rrd_copy_rra($dom, $cf, $rra, $debug);
		}

		if ($debug) {
			echo $dom->saveXML();
		} else {
			/* for rrdtool restore, we need a file, so write the XML to disk */
			$xml_file = $file . '.xml';
			$rc = $dom->save($xml_file);
			/* verify, if write was successful */
			if ($rc === false) {
				$check['err_msg'] = __('ERROR while writing XML file: %s', $xml_file);
				return $check;
			} else {
				/* are we allowed to write the rrd file? */
				if (is_writable($file)) {
					/* restore the modified XML to rrd */
					rrdtool_execute("restore -f $xml_file $file", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'UTIL');
					/* scratch that XML file to avoid filling up the disk */
					unlink($xml_file);
					cacti_log(__('Deleted rra(s) from rrd file: %s', $file), false, 'UTIL');
				} else {
					$check['err_msg'] = __('ERROR: RRD file %s not writeable', $file);
					return $check;
				}
			}
		}
	}

	rrd_close($rrdtool_pipe);

	return true;
}

/** appends a <DS> subtree to an RRD XML structure
 * @param object $dom	- the DOM object, where the RRD XML is stored
 * @param string $version- rrd file version
 * @param string $name	- name of the new ds
 * @param string $type	- type of the new ds
 * @param int $min_hb	- heartbeat of the new ds
 * @param string $min	- min value of the new ds or [NaN|U]
 * @param string $max	- max value of the new ds or [NaN|U]
 * @return object		- modified DOM
 */
function rrd_append_ds($dom, $version, $name, $type, $min_hb, $min, $max) {
	/* rrdtool version dependencies */
	if ($version === RRD_FILE_VERSION1) {
		$last_ds = 'U';
	}
	elseif ($version === RRD_FILE_VERSION3) {
		$last_ds = 'UNKN';
	}

	/* create <DS> subtree */
	$new_dom = new DOMDocument;
	/* pretty print */
	$new_dom->formatOutput = true;
	/* this defines the new node structure */
	$new_dom->loadXML("
			<ds>
				<name> $name </name>
				<type> $type </type>
				<minimal_heartbeat> $min_hb </minimal_heartbeat>
				<min> $min </min>
				<max> $max </max>

				<!-- PDP Status -->
				<last_ds> $last_ds </last_ds>
				<value> 0.0000000000e+00 </value>
				<unknown_sec> 0 </unknown_sec>
			</ds>");

	/* create a node element from new document */
	$new_node = $new_dom->getElementsByTagName('ds')->item(0);
	#echo $new_dom->saveXML();	# print new node

	/* get XPATH notation required for positioning */
	#$xpath = new DOMXPath($dom);
	/* get XPATH for entry where new node will be inserted
	 * which is the <rra> entry */
	#$insert = $xpath->query('/rrd/rra')->item(0);
	$insert = $dom->getElementsByTagName('rra')->item(0);

	/* import the new node */
	$new_node = $dom->importNode($new_node, true);
	/* and insert it at the correct place */
	$insert->parentNode->insertBefore($new_node, $insert);
}

/** COMPUTE DS: appends a <DS> subtree to an RRD XML structure
 * @param object $dom	- the DOM object, where the RRD XML is stored
 * @param string $version- rrd file version
 * @param string $name	- name of the new ds
 * @param string $type	- type of the new ds
 * @param int $cdef		- the cdef rpn used for COMPUTE
 * @return object		- modified DOM
 */
function rrd_append_compute_ds($dom, $version, $name, $type, $cdef) {
	/* rrdtool version dependencies */
	if ($version === RRD_FILE_VERSION1) {
		$last_ds = 'U';
	}
	elseif ($version === RRD_FILE_VERSION3) {
		$last_ds = 'UNKN';
	}

	/* create <DS> subtree */
	$new_dom = new DOMDocument;
	/* pretty print */
	$new_dom->formatOutput = true;
	/* this defines the new node structure */
	$new_dom->loadXML("
			<ds>
				<name> $name </name>
				<type> $type </type>
				<cdef> $cdef </cdef>

				<!-- PDP Status -->
				<last_ds> $last_ds </last_ds>
				<value> 0.0000000000e+00 </value>
				<unknown_sec> 0 </unknown_sec>
			</ds>");

	/* create a node element from new document */
	$new_node = $new_dom->getElementsByTagName('ds')->item(0);

	/* get XPATH notation required for positioning */
	#$xpath = new DOMXPath($dom);
	/* get XPATH for entry where new node will be inserted
	 * which is the <rra> entry */
	#$insert = $xpath->query('/rrd/rra')->item(0);
	$insert = $dom->getElementsByTagName('rra')->item(0);

	/* import the new node */
	$new_node = $dom->importNode($new_node, true);
	/* and insert it at the correct place */
	$insert->parentNode->insertBefore($new_node, $insert);
}

/** append a <DS> subtree to the <CDP_PREP> subtrees of a RRD XML structure
 * @param object $dom		- the DOM object, where the RRD XML is stored
 * @param string $version	- rrd file version
 * @return object			- the modified DOM object
 */
function rrd_append_cdp_prep_ds($dom, $version) {
	/* get all <cdp_prep><ds> entries */
	#$cdp_prep_list = $xpath->query('/rrd/rra/cdp_prep');
	$cdp_prep_list = $dom->getElementsByTagName('rra')->item(0)->getElementsByTagName('cdp_prep');

	/* get XPATH notation required for positioning */
	#$xpath = new DOMXPath($dom);

	/* get XPATH for source <ds> entry */
	#$src_ds = $xpath->query('/rrd/rra/cdp_prep/ds')->item(0);
	$src_ds = $dom->getElementsByTagName('rra')->item(0)->getElementsByTagName('cdp_prep')->item(0)->getElementsByTagName('ds')->item(0);
	/* clone the source ds entry to preserve RRDTool notation */
	$new_ds = $src_ds->cloneNode(true);

	/* rrdtool version dependencies */
	if ($version === RRD_FILE_VERSION3) {
		$new_ds->getElementsByTagName('primary_value')->item(0)->nodeValue = ' NaN ';
		$new_ds->getElementsByTagName('secondary_value')->item(0)->nodeValue = ' NaN ';
	}

	/* the new node always has default entries */
	$new_ds->getElementsByTagName('value')->item(0)->nodeValue = ' NaN ';
	$new_ds->getElementsByTagName('unknown_datapoints')->item(0)->nodeValue = ' 0 ';


	/* iterate all entries found, equals 'number of <rra>' times 'number of <ds>' */
	if ($cdp_prep_list->length) {
		foreach ($cdp_prep_list as $cdp_prep) {
			/* $cdp_prep now points to the next <cdp_prep> XML Element
			 * and append new ds entry at end of <cdp_prep> child list */
			$cdp_prep->appendChild($new_ds);
		}
	}
}

/** append a <V>alue element to the <DATABASE> subtrees of a RRD XML structure
 * @param object $dom	- the DOM object, where the RRD XML is stored
 * @return object		- the modified DOM object
 */
function rrd_append_value($dom) {
	/* get XPATH notation required for positioning */
	#$xpath = new DOMXPath($dom);

	/* get all <cdp_prep><ds> entries */
	#$itemList = $xpath->query('/rrd/rra/database/row');
	$itemList = $dom->getElementsByTagName('row');

	/* create <V> entry to preserve RRDTool notation */
	$new_v = $dom->createElement('v', ' NaN ');

	/* iterate all entries found, equals 'number of <rra>' times 'number of <ds>' */
	if ($itemList->length) {
		foreach ($itemList as $item) {
			/* $item now points to the next <cdp_prep> XML Element
			 * and append new ds entry at end of <cdp_prep> child list */
			$item->appendChild($new_v);
		}
	}
}

/** delete an <RRA> subtree from the <RRD> XML structure
 * @param object $dom		- the DOM document, where the RRD XML is stored
 * @param array $rra_parm	- a single rra parameter set, given by the user
 * @return object			- the modified DOM object
 */
function rrd_delete_rra($dom, $rra_parm) {
	/* find all RRA DOMNodes */
	$rras = $dom->getElementsByTagName('rra');

	/* iterate all entries found */
	$nb = $rras->length;
	for ($pos = 0; $pos < $nb; $pos++) {
		/* retrieve all RRA DOMNodes one by one */
		$rra = $rras->item($pos);
		$cf = $rra->getElementsByTagName('cf')->item(0)->nodeValue;
		$pdp_per_row = $rra->getElementsByTagName('pdp_per_row')->item(0)->nodeValue;
		$xff = $rra->getElementsByTagName('xff')->item(0)->nodeValue;
		$rows = $rra->getElementsByTagName('row')->length;

		if ($cf 			== $rra_parm['cf'] &&
			$pdp_per_row 	== $rra_parm['pdp_per_row'] &&
			$xff 			== $rra_parm['xff'] &&
			$rows 			== $rra_parm['rows']) {
			print(__("RRA (CF=%s, ROWS=%d, PDP_PER_ROW=%d, XFF=%1.2f) removed from RRD file\n", $cf, $rows, $pdp_per_row, $xff));
			/* we need the parentNode for removal operation */
			$parent = $rra->parentNode;
			$parent->removeChild($rra);
			break; /* do NOT accidentally remove more than one element, else loop back to forth */
		}
	}
	return $dom;
}

/** clone an <RRA> subtree of the <RRD> XML structure, replacing cf
 * @param object $dom		- the DOM document, where the RRD XML is stored
 * @param string $cf		- new consolidation function
 * @param array $rra_parm	- a single rra parameter set, given by the user
 * @return object			- the modified DOM object
 */
function rrd_copy_rra($dom, $cf, $rra_parm) {
	/* find all RRA DOMNodes */
	$rras = $dom->getElementsByTagName('rra');

	/* iterate all entries found */
	$nb = $rras->length;
	for ($pos = 0; $pos < $nb; $pos++) {
		/* retrieve all RRA DOMNodes one by one */
		$rra          = $rras->item($pos);
		$_cf          = $rra->getElementsByTagName('cf')->item(0)->nodeValue;
		$_pdp_per_row = $rra->getElementsByTagName('pdp_per_row')->item(0)->nodeValue;
		$_xff         = $rra->getElementsByTagName('xff')->item(0)->nodeValue;
		$_rows        = $rra->getElementsByTagName('row')->length;

		if ($_cf 			== $rra_parm['cf'] &&
			$_pdp_per_row 	== $rra_parm['pdp_per_row'] &&
			$_xff 			== $rra_parm['xff'] &&
			$_rows 			== $rra_parm['rows']) {
			print(__("RRA (CF=%s, ROWS=%d, PDP_PER_ROW=%d, XFF=%1.2f) adding to RRD file\n", $cf, $_rows, $_pdp_per_row, $_xff));
			/* we need the parentNode for append operation */
			$parent = $rra->parentNode;

			/* get a clone of the matching RRA */
			$new_rra = $rra->cloneNode(true);
			/* and find the 'old' cf */
			#$old_cf = $new_rra->getElementsByTagName('cf')->item(0);
			/* now replace old cf with new one */
			#$old_cf->childNodes->item(0)->replaceData(0,20,$cf);
			$new_rra->getElementsByTagName('cf')->item(0)->nodeValue = $cf;

			/* append new rra entry at end of the list */
			$parent->appendChild($new_rra);
			break; /* do NOT accidentally clone more than one element, else loop back to forth */
		}
	}

	return $dom;
}

function rrdtool_create_error_image($string, $width = '', $height = '') {
	global $config;

	/* put image in buffer */
	ob_start();

	$image_data  = false;
	$font_color  = '000000';
	$font_size   = 8;
	$back_color  = 'F3F3F3';
	$shadea      = 'CBCBCB';
	$shadeb      = '999999';

	if ($config['cacti_server_os'] == 'unix') {
		$font_file = '/usr/share/fonts/dejavu/DejaVuSans.ttf';
	} else {
		$font_file = 'C:/Windows/Fonts/Arial.ttf';
	}

	$themefile  = $config['base_path'] . '/include/themes/' . get_selected_theme() . '/rrdtheme.php';

	if (file_exists($themefile) && is_readable($themefile)) {
		include($themefile);

		if (isset($rrdfonts['legend']['size'])) {
			$font_size   = $rrdfonts['legend']['size'];
		}

		if (isset($rrdcolors['font'])) {
			$font_color  = $rrdcolors['font'];
		}

		if (isset($rrdcolors['canvas'])) {
			$back_color  = $rrdcolors['canvas'];
		}

		if (isset($rrdcolors['shadea'])) {
			$shadea = $rrdcolors['shadea'];
		}

		if (isset($rrdcolors['shadeb'])) {
			$shadeb = $rrdcolors['shadeb'];
		}
	}

	$image = imagecreatetruecolor(450, 200);
	imagesavealpha($image, true);

	/* create a transparent color */
	$transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
	imagefill($image, 0, 0, $transparent);

	/* background the entire image with the frame */
	list($red, $green, $blue) = sscanf($shadeb, '%02x%02x%02x');
	$shadeb = imagecolorallocate($image, $red, $green, $blue);
	imagefill($image, 0, 0, $shadeb);

	/* set the background color */
	list($red, $green, $blue) = sscanf($shadea, '%02x%02x%02x');
	$shadea = imagecolorallocate($image, $red, $green, $blue);
	imagefilledrectangle($image, 1, 1, 448, 198, $shadea);

	/* set the background color */
	list($red, $green, $blue) = sscanf($back_color, '%02x%02x%02x');
	$back_color = imagecolorallocate($image, $red, $green, $blue);
	imagefilledrectangle($image, 2, 2, 447, 197, $back_color);

	/* allocate the image */
	$logo = imagecreatefrompng($config['base_path'] . '/images/cacti_error_image.png');

	/* merge the two images */
	imagecopy($image, $logo, 0, 0, 0, 0, 450, 200);

	/* set the background color */
	list($red, $green, $blue) = sscanf($font_color, '%02x%02x%02x');
	$text_color = imagecolorallocate($image, $red, $green, $blue);

	/* see the size of the string */
	$string    = trim($string);
	$maxstring = (450 - (125 + 10)) / ($font_size / 1.4);
	$stringlen = strlen($string) * $font_size;
	$padding   = 5;
	if ($stringlen > $maxstring) {
		$cstring = wordwrap($string, $maxstring, "\n", true);
		$strings = explode("\n", $cstring);
		$strings = array_reverse($strings);
		$lines   = sizeof($strings);
	} else {
		$strings = array($string);
		$lines   = 1;
	}

	/* setup the text position, image is 450x200, we start at 125 pixels from the left */
	$xpos  = 125;
	$texth = ($lines * $font_size + (($lines - 1) * $padding));
	$ypos  = round((200 / 2) + ($texth / 2),0);

	/* set the font of the image */
	if (file_exists($font_file) && is_readable($font_file) && function_exists('imagettftext')) {
		foreach($strings as $string) {
			if (!imagettftext($image, $font_size, 0, $xpos, $ypos, $text_color, $font_file, $string)) {
				cacti_log('TTF text overlay failed');
			}
			$ypos -= ($font_size + $padding);
		}
	} else {
		foreach($strings as $string) {
			if (!imagestring($image, $font_size, $xpos, $ypos, $string, $font_color)) {
				cacti_log('Text overlay failed');
			}
			$ypos -= ($font_size + $padding);
		}
	}

	if ($width != '' && $height != '') {
		$nimage = imagecreatetruecolor($width, $height);
		imagecopyresized($nimage, $image, 0, 0, 0, 0, $width, $height, 450, 200);

		/* create the image */
		imagepng($image);
	} else {
		/* create the image */
		imagepng($image);
	}

	/* get the image from the buffer */
	$image_data = ob_get_contents();

	/* destroy the image object */
	imagedestroy($image);
	imagedestroy($logo);
	if (isset($nimage)) {
		imagedestroy($nimage);
	}

	/* flush the buffer */
	ob_end_clean();

	return $image_data;
}
