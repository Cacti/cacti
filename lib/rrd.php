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

define('RRD_NL', " \\\n");
define('MAX_FETCH_CACHE_SIZE', 5);

if( read_config_option('storage_location') ) {
	/* load crypt libraries only if the Cacti RRDtool Proxy Server is in use */
	set_include_path($config["include_path"] . "/phpseclib/");
	include_once('Crypt/RSA.php');
	include_once('Crypt/AES.php');
}

function escape_command($command) {
	return $command;		# we escape every single argument now, no need for "special" escaping
	#return preg_replace("/(\\\$|`)/", "", $command); # current cacti code
	#TODO return preg_replace((\\\$(?=\w+|\*|\@|\#|\?|\-|\\\$|\!|\_|[0-9]|\(.*\))|`(?=.*(?=`)))","$2", $command);  #suggested by ldevantier to allow for a single $
}

function rrd_init($output_to_term = TRUE) {
	global $config;
	
	$args = func_get_args();
	$force_storage_location_local = ( isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true ) ? true : false;
	$function = (read_config_option('storage_location') && $force_storage_location_local === false ) ? '__rrd_proxy_init' : '__rrd_init';
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
	}else{
		if ($config['cacti_server_os'] == 'win32') {
			$command = read_config_option('path_rrdtool') . ' - > nul';
		}else{
			$command = read_config_option('path_rrdtool') . ' - > /dev/null 2>&1';
		}
	}

	return popen($command, 'w');
}

function __rrd_proxy_init() {
	global $config;
	
	$rsa = new Crypt_RSA();
	
	$rrdp_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($rrdp_socket === false) {
		// TODO: log entry ..
		return false;
	}
	
	$rrdp_address = read_config_option('rrdp_server');
	$rrdp_port = read_config_option('rrdp_port');
	$rrdp_fingerprint = read_config_option('rrdp_fingerprint');
	
	$rrdp = @socket_connect($rrdp_socket, $rrdp_address, $rrdp_port);
		
	if($rrdp === false) {
		// TODO: log entry ..
		return false;
	}else {
		socket_write($rrdp_socket, read_config_option('rsa_public_key') . "\r\n");
	
		/* read public key being returned by the proxy server */
		$rrdp_public_key = '';
		while(1) {
			$recv = socket_read($rrdp_socket, 1000, PHP_BINARY_READ );
			if($recv === false) {
				/* timeout  */
				/* TODO error log */
				$rrdp_public_key = false;
				break;
			}else if($recv == '') {
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
			/* todo -- error logging */
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
}

function rrd_close() {
	global $config;
	$args = func_get_args();
	$force_storage_location_local = ( isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true ) ? true : false;
	$function = (read_config_option('storage_location') && $force_storage_location_local === false ) ? '__rrd_proxy_close' : '__rrd_close';
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

	$rsa = new Crypt_RSA();
	$aes = new Crypt_AES();

	$aes_key = crypt_random_string(192);
	
	$aes->setKey($aes_key);
	$ciphertext = base64_encode($aes->encrypt($output));
	$rsa->loadKey($rsa_key);
	$aes_key = base64_encode($rsa->encrypt($aes_key));
	$aes_key_length = str_pad(dechex(strlen($aes_key)),3,'0',STR_PAD_LEFT); 
	
	return $aes_key_length . $aes_key . $ciphertext;
}

function decrypt($input){
			 
	$rsa = new Crypt_RSA();
	$aes = new Crypt_AES();

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
	$force_storage_location_local = ( isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true ) ? true : false;
	$function = (read_config_option('storage_location') && $force_storage_location_local === false ) ? '__rrd_proxy_execute' : '__rrd_execute';
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
	if (($output_flag == RRDTOOL_OUTPUT_STDERR) && (!is_resource($rrdtool_pipe))) {
		$command_line .= ' 2>&1';
	}

	/* use popen to eliminate the zombie issue */
	if ($config['cacti_server_os'] == 'unix') {
		$pipe_mode = 'r';
	}else{
		$pipe_mode = 'rb';
	}

	/* an empty $rrdtool_pipe array means no fp is available */
	if (!is_resource($rrdtool_pipe)) {
		session_write_close();
		$fp = popen(read_config_option('path_rrdtool') . escape_command(" $command_line"), $pipe_mode);
		if (!is_resource($fp)) {
			unset($fp);
		}
	}else{
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
				}else{
					$i++;
				}

				continue;
			}else{
				fflush($rrdtool_pipe);

				break;
			}
		}
	}

	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;

	switch ($output_flag) {
		case RRDTOOL_OUTPUT_NULL:
			return; 

			break;
		case RRDTOOL_OUTPUT_STDOUT:
			if (isset($fp) && is_resource($fp)) {
				$line = '';
				while (!feof($fp)) {
					$line .= fgets($fp, 4096);
				}

				pclose($fp);

				return $line;
			}

			break;
		case RRDTOOL_OUTPUT_STDERR:
			if (isset($fp) && is_resource($fp)) {
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
			}

			break;
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			if (isset($fp) && is_resource($fp)) {
				$line = '';
				while (!feof($fp)) {
					$line .= fgets($fp, 4096);
				}

				pclose($fp);

				return $line;
			}

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
	$command_line = str_replace("\\\n", " ", $command_line);

	/* output information to the log file if appropriate */
	cacti_log("CACTI2RRDP: " . read_config_option("path_rrdtool") . " $command_line", $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
	
	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;
	$rrdp_auto_close = FALSE;
	
	if(!$rrdp) {
		$rrdp = __rrd_proxy_init();
		$rrdp_auto_close = TRUE;
	}
	
	if(!$rrdp) {
		/* TODO log */
		return null;	
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
			/* timeout  */
			/* TODO error log */
			break;
		}else if($recv == '') {
			/* session closed by Proxy */
			if($output) {
				cacti_log("RRDP: Connection closed by proxy" , $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
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
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			return $output;
			break;
		case RRDTOOL_OUTPUT_BOOLEAN :
			return (substr_count($output, "OK u")) ? TRUE : FALSE;
			break;
	}
	
}

function rrdtool_function_create($local_data_id, $show_source, $rrdtool_pipe = '') {
	global $config;

	include ($config['include_path'] . '/global_arrays.php');

	$data_source_path = get_data_source_path($local_data_id, true);

	/* ok, if that passes lets check to make sure an rra does not already
	exist, the last thing we want to do is overright data! */
	if ($show_source != true) {
		if(read_config_option('storage_location')) {
			$file_exists = rrdtool_execute("file_exists $data_source_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER');
		}else {
			$file_exists = file_exists($data_source_path);
		}
		if ($file_exists == true) {
			return -1;
		}
	}

	/* the first thing we must do is make sure there is at least one
	rra associated with this data source... *
	UPDATE: As of version 0.6.6, we are splitting this up into two
	SQL strings because of the multiple DS per RRD support. This is
	not a big deal however since this function gets called once per
	data source */

	$rras = db_fetch_assoc("SELECT dtd.rrd_step, dsp.x_files_factor,
		dspr.steps, dspr.rows, dspc.consolidation_function_id,
		(dspr.rows*dspr.steps) as rra_order
		FROM data_template_data AS dtd
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		LEFT JOIN data_source_profiles_cf AS dspc 
		ON dsp.id=dspc.data_source_profile_id
		WHERE dtd.local_data_id=$local_data_id
		AND (dspr.steps IS NOT NULL OR dspr.rows IS NOT NULL)
		ORDER BY dspc.consolidation_function_id, rra_order");

	/* if we find that this DS has no RRA associated; get out */
	if (sizeof($rras) <= 0) {
		cacti_log("ERROR: There are no RRA's assigned to local_data_id: $local_data_id.");
		return false;
	}

	/* create the "--step" line */
	$create_ds = RRD_NL . '--step '. $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/* query the data sources to be used in this .rrd file */
	$data_sources = db_fetch_assoc("SELECT dtr.id, dtr.rrd_heartbeat,
		dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
		FROM data_template_rrd AS dtr
		WHERE dtr.local_data_id=$local_data_id
		ORDER BY local_data_template_rrd_id");

	/* ONLY make a new DS entry if:
	- There is multiple data sources and this item is not the main one.
	- There is only one data source (then use it) */

	if (sizeof($data_sources)) {
	foreach ($data_sources as $data_source) {
		/* use the cacti ds name by default or the user defined one, if entered */
		$data_source_name = get_data_source_item_name($data_source['id']);

		if (empty($data_source['rrd_maximum'])) {
			/* in case no maximum is given, use "Undef" value */
			$data_source['rrd_maximum'] = 'U';
		} elseif (strpos($data_source['rrd_maximum'], '|query_') !== false) {
			/* in case a query variable is given, evaluate it */
			$data_local = db_fetch_row('SELECT * FROM data_local WHERE id=' . $local_data_id);
			if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
				$highSpeed = db_fetch_cell("SELECT field_value
					FROM host_snmp_cache
					WHERE host_id=" . $data_local['host_id'] . "
					AND snmp_query_id=" . $data_local['snmp_query_id'] . "
					AND snmp_index='" . $data_local['snmp_index'] . "'
					AND field_name='ifHighSpeed'");

				if (!empty($highSpeed)) {
					$data_source['rrd_maximum'] = $highSpeed * 1000000;
				}else{
					$data_source['rrd_maximum'] = substitute_snmp_query_data('|query_ifSpeed|',$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);

					if (empty($data_source['rrd_maximum']) || $data_source['rrd_maximum'] == '|query_ifSpeed|') {
						$data_source['rrd_maximum'] = '10000000000000';
					}
				}
			}else{
				$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'],$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
			}
		} elseif (($data_source['rrd_maximum'] != 'U') && (int)$data_source['rrd_maximum']<=(int)$data_source['rrd_minimum']) {
			/* max > min required, but take care of an "Undef" value */
			$data_source['rrd_maximum'] = (int)$data_source['rrd_minimum']+1;
		}

		/* min==max==0 won't work with rrdtool */
		if ($data_source['rrd_minimum'] == 0 && $data_source['rrd_maximum'] == 0) {
			$data_source['rrd_maximum'] = 'U';
		}

		$create_ds .= "DS:$data_source_name:" . $data_source_types{$data_source['data_source_type_id']} . ':' . $data_source['rrd_heartbeat'] . ':' . $data_source['rrd_minimum'] . ':' . $data_source['rrd_maximum'] . RRD_NL;
	}
	}

	$create_rra = '';
	/* loop through each available RRA for this DS */
	foreach ($rras as $rra) {
		$create_rra .= 'RRA:' . $consolidation_functions{$rra['consolidation_function_id']} . ':' . $rra['x_files_factor'] . ':' . $rra['steps'] . ':' . $rra['rows'] . RRD_NL;
	}

	/* check for structured path configuration, if in place verify directory
	   exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
	
		if(read_config_option('storage_location')) {
			if( false === rrdtool_execute("is_dir " . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
				if( false === rrdtool_execute("mkdir " . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", FALSE);
				}
			}
		}else {
			if (!is_dir(dirname($data_source_path))) {
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
			}
		}
	}

	if ($show_source == true) {
		return read_config_option('path_rrdtool') . ' create' . RRD_NL . "$data_source_path$create_ds$create_rra";
	}else{
		rrdtool_execute("create $data_source_path $create_ds$create_rra", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');
	}
}

function rrdtool_function_update($update_cache_array, $rrdtool_pipe = '') {
	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	while (list($rrd_path, $rrd_fields) = each($update_cache_array)) {
		$create_rrd_file = false;

		if ((is_array($rrd_fields['times'])) && (sizeof($rrd_fields['times']))) {
		
		/* create the rrd if one does not already exist */
		if(read_config_option('storage_location')) {
			$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER');
		}else {
			$file_exists = file_exists($rrd_path);
		}

		if($file_exists === false) {
			rrdtool_function_create($rrd_fields['local_data_id'], false, $rrdtool_pipe);
			$create_rrd_file = true;
		}

			ksort($rrd_fields['times']);

			while (list($update_time, $field_array) = each($rrd_fields['times'])) {
				if (empty($update_time)) {
					/* default the rrdupdate time to now */
					$current_rrd_update_time = 'N';
				}else if ($create_rrd_file == true) {
					/* for some reason rrdtool will not let you update using times less than the
					rrd create time */
					$current_rrd_update_time = 'N';
				}else{
					$current_rrd_update_time = $update_time;
				}

				$i = 0; $rrd_update_template = ''; $rrd_update_values = $current_rrd_update_time . ':';
				while (list($field_name, $value) = each($field_array)) {
					$rrd_update_template .= $field_name;

					/* if we have "invalid data", give rrdtool an Unknown (U) */
					if ((!isset($value)) || (!is_numeric($value))) {
						$value = 'U';
					}

					$rrd_update_values .= $value;

					if (($i+1) < count($field_array)) {
						$rrd_update_template .= ':';
						$rrd_update_values .= ':';
					}

					$i++;
				}

				rrdtool_execute("update $rrd_path --template $rrd_update_template $rrd_update_values", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');
				$rrds_processed++;
			}
		}
	}

	return $rrds_processed;
}

function rrdtool_function_tune($rrd_tune_array) {
	global $config;

	include($config['include_path'] . '/global_arrays.php');

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types{$rrd_tune_array['data-source-type']};
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);

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
   @returns - (array) an array containing all data in this data source broken down
     by each data source item. the maximum of all data source items is included in
     an item called 'ninety_fifth_percentile_maximum' */
function rrdtool_function_fetch($local_data_id, $start_time, $end_time, $resolution = 0, $show_unknown = false, $rrdtool_file = null) {
	global $config;

	static $rrd_fetch_cache = array();

	include_once($config['library_path'] . '/boost.php');

	/* validate local data id */
	if (empty($local_data_id) && is_null($rrdtool_file)) {
		return array();
	}

	/* the cache hash is used to identify unique items in the cache */
	$current_hash_cache = md5($local_data_id . $start_time . $end_time . $resolution . $show_unknown . $rrdtool_file);

	/* return the cached entry if available */
	if (isset($rrd_fetch_cache[$current_hash_cache])) {
		return $rrd_fetch_cache[$current_hash_cache];
	}

	/* initialize fetch array */
	$fetch_array = array();

	/* check if we have been passed a file instead of lodal data source to look up */
	if (is_null($rrdtool_file)) {
		$data_source_path = get_data_source_path($local_data_id, true);
	}else{
		$data_source_path = $rrdtool_file;
	}

	/* update the rrdfile if performing a fetch */
	boost_fetch_cache_check($local_data_id);

	/* build and run the rrdtool fetch command with all of our data */
	$cmd_line = "fetch $data_source_path AVERAGE -s $start_time -e $end_time";
	if ($resolution > 0) {
		$cmd_line .= " -r $resolution";
	}
	$output = rrdtool_execute($cmd_line, false, RRDTOOL_OUTPUT_STDOUT);

	/* grab the first line of the output which contains a list of data sources in this rrd output */
	$line_one_eol = strpos($output, "\n");
	$line_one = substr($output, 0, $line_one_eol);
	$output = substr($output, $line_one_eol);

	/* split the output into an array */
	$output = preg_split('/[\r\n]{1,2}/', $output, null, PREG_SPLIT_NO_EMPTY);

	/* find the data sources in the rrdtool output */
	if (preg_match_all('/\S+/', $line_one, $data_source_names)) {
		/* version 1.0.49 changed the output slightly, remove the timestamp label if present */
		if (preg_match('/^timestamp/', $line_one)) {
			array_shift($data_source_names[0]);
		}
		$fetch_array['data_source_names'] = $data_source_names[0];

		/* build a regular expression to match each data source value in the rrdtool output line */
		$regex = '/[0-9]+:\s+';
		for ($i=0; $i < count($fetch_array['data_source_names']); $i++) {
			$regex .= '([\-]?[0-9]{1}[.,][0-9]+e[\+-][0-9]{2,3}|-?[Nn][Aa][Nn])';

			if ($i < count($fetch_array['data_source_names']) - 1) {
				$regex .= '\s+';
			}
		}
		$regex .= '/';
	}

	/* loop through each line of the output */
	$fetch_array['values'] = array();
	for ($j = 0; $j < count($output); $j++) {
		$matches = array();
		$max_array = array();
		/* match the output line */
		if (preg_match($regex, $output[$j], $matches)) {
			/* only process the output line if we have the correct number of matches */
			if (count($matches) - 1 == count($fetch_array['data_source_names'])) {
				/* get all values from the line and set them to the appropriate data source */
				for ($i=1; $i <= count($fetch_array['data_source_names']); $i++) {
					if (! isset($fetch_array['values'][$i - 1])) {
						$fetch_array['values'][$i - 1] = array();
					}
					if ((strtolower($matches[$i]) == 'nan') || (strtolower($matches[$i]) == '-nan')) {
						if ($show_unknown) {
							$fetch_array['values'][$i - 1][$j] = 'U';
						}
					} else {
						list($mantisa, $exponent) = explode('e', $matches[$i]);
						$mantisa = str_replace(',','.',$mantisa);
						$value = ($mantisa * (pow(10, (float)$exponent)));
						$mantisa = str_replace(',','.',$mantisa);
						$fetch_array['values'][$i - 1][$j] = ($value * 1);
						$max_array[$i - 1] = $value;
					}
				}
				/* get max value for values on the line */
				if (count($max_array) > 0) {
					$fetch_array['values'][count($fetch_array['data_source_names'])][$j] = max($max_array);
				}
			}
		}
	}
	/* add nth percentile maximum data source */
	if (isset($fetch_array['values'][count($fetch_array['data_source_names'])])) {
		$fetch_array['data_source_names'][count($fetch_array['data_source_names'])] = 'nth_percentile_maximum';
	}

	/* clear the cache if it gets too big */
	if (sizeof($rrd_fetch_cache) >= MAX_FETCH_CACHE_SIZE) {
		$rrd_fetch_cache = array();
	}

	/* update the cache */
	if (MAX_FETCH_CACHE_SIZE > 0) {
		$rrd_fetch_cache[$current_hash_cache] = $fetch_array;
	}

	return $fetch_array;
}

function rrd_function_process_graph_options($graph_start, $graph_end, &$graph, &$graph_data_array) {
	global $config;

	include($config['include_path'] . '/global_arrays.php');

	/* define some variables */
	$scale               = '';
	$rigid               = '';
	$unit_value          = '';
	$version             = read_config_option('rrdtool_version');
	$unit_exponent_value = '';
	$graph_legend        = '';

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
	}else{
		if (strlen($graph['upper_limit'])) {
			$scale =  '--upper-limit=' . cacti_escapeshellarg($graph['upper_limit']) . RRD_NL;
		}
		if (strlen($graph['lower_limit'])) {
			$scale .= '--lower-limit=' . cacti_escapeshellarg($graph['lower_limit']) . RRD_NL;
		}
	}

	if ($graph['auto_scale_log'] == 'on') {
		$scale .= '--logarithmic' . RRD_NL;
	}

	/* --units=si only defined for logarithmic y-axis scaling, even if it doesn't hurt on linear graphs */
	if (($graph['scale_log_units'] == 'on') &&
		($graph['auto_scale_log'] == 'on')) {
		$scale .= '--units=si' . RRD_NL;
	}

	if ($graph['auto_scale_rigid'] == 'on') {
		$rigid = '--rigid' . RRD_NL;
	}

	if (!empty($graph['unit_value'])) {
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
	}else{
		$graph_height = $graph['height'];
	}

	/* override: graph width (in pixels) */
	if (isset($graph_data_array['graph_width'])) {
		$graph_width = $graph_data_array['graph_width'];
	}else{
		$graph_width = $graph['width'];
	}

	/* override: skip drawing the legend? */
	if (isset($graph_data_array['graph_nolegend'])) {
		$graph_legend = '--no-legend' . RRD_NL;
	}else{
		$graph_legend = '';
	}

	/* export options */
	if (isset($graph_data_array['export'])) {
		$graph_opts = read_config_option('path_html_export') . '/' . $graph_data_array['export_filename'] . RRD_NL;
	}else{
		if (empty($graph_data_array['output_filename'])) {
				$graph_opts = '-' . RRD_NL;
		}else{
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

	foreach($graph as $key => $value) {
		switch($key) {
		case "title_cache":
			if (!empty($value)) {
				$graph_opts .= "--title=" . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "alt_y_grid":
			if ($value == CHECKED)  {$graph_opts .= "--alt-y-grid" . RRD_NL;}
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
			}else{
				$graph_opts .= "--height=" . $value . RRD_NL;
			}
			break;
		case "width":
			if (isset($graph_data_array["graph_width"]) && preg_match("/^[0-9]+$/", $graph_data_array["graph_width"])) {
				$graph_opts .= "--width=" . $graph_data_array["graph_width"] . RRD_NL;
			}else{
				$graph_opts .= "--width=" . $value . RRD_NL;
			}
			break;
		case "graph_nolegend":
			if (isset($graph_data_array["graph_nolegend"])) {
				$graph_opts .= "--no-legend" . RRD_NL;
			}else{
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
			$graph_opts .= "--vertical-label=" . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case "slope_mode":
			if ($value == CHECKED) {
				$graph_opts .= "--slope-mode" . RRD_NL;
			}
			break;
		case "right_axis":
			if ($version != RRD_VERSION_1_2) {
				if (!empty($value)) {
					$graph_opts .= "--right-axis " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case "right_axis_label":
			if ($version != RRD_VERSION_1_2) {
				if (!empty($value)) {
					$graph_opts .= "--right-axis-label " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case "right_axis_format":
			if ($version != RRD_VERSION_1_2) {
				if (!empty($value)) {
					$format = db_fetch_cell('SELECT gprint_text from graph_templates_gprint WHERE id=' . $value);
					$graph_opts .= "--right-axis-format " . cacti_escapeshellarg($format) . RRD_NL;
				}
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
			if ($version != RRD_VERSION_1_2 && $version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--legend-position " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case "legend_direction":
			if ($version != RRD_VERSION_1_2 && $version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--legend-direction " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'left_axis_formatter':
			if ($version != RRD_VERSION_1_2 && $version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--left-axis-formatter " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'right_axis_formatter':
			if ($version != RRD_VERSION_1_2 && $version != RRD_VERSION_1_3) {
				if (!empty($value)) {
					$graph_opts .= "--right-axis-formatter " . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		}
	}

	$graph_opts .= "$rigid" . trim("$scale $unit_value $unit_exponent_value $graph_legend ", "\n\r " . RRD_NL) . RRD_NL;

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
	if (read_config_option('graph_wathermark') != '') {
		$graph_opts .= '--watermark ' . cacti_escapeshellarg(read_config_option('graph_wathermark')) . RRD_NL;
	}

	return $graph_opts;
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe = '', &$xport_meta = array(), $user = 0) {
	global $config, $consolidation_functions;

	include_once($config['library_path'] . '/cdef.php');
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
	if (!is_graph_allowed($local_graph_id, $user)) {
		return 'GRAPH ACCESS DENIED';
	}

	/* check the boost image cache and if we find a live file there return it instead */
	if (!isset($graph_data_array['export_csv']) && !isset($graph_data_array['export_realtime']) && !isset($graph_data_array['disable_cache'])) {
		$graph_data = boost_graph_cache_check($local_graph_id, $rra_id, $rrdtool_pipe, $graph_data_array, false);
		if ($graph_data != false) {
			return $graph_data;
		}
	}

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell("SELECT
		data_template_data.rrd_step
		FROM (data_template_data,data_template_rrd,graph_templates_item)
		WHERE graph_templates_item.task_item_id=data_template_rrd.id
		AND data_template_rrd.local_data_id=data_template_data.local_data_id
		AND graph_templates_item.local_graph_id=$local_graph_id
		LIMIT 0,1");
	$ds_step = empty($ds_step) ? 300 : $ds_step;

	/* if no rra was specified, we need to figure out which one RRDTool will choose using
	 * "best-fit" resolution fit algorithm */
	if (empty($rra_id)) {
		if ((empty($graph_data_array['graph_start'])) || (empty($graph_data_array['graph_end']))) {
			$rra['rows'] = 600;
			$rra['steps'] = 1;
			$rra['timespan'] = 86400;
		}else{
			/* get a list of RRAs related to this graph */
			$rras = get_associated_rras($local_graph_id);

			if (sizeof($rras) > 0) {
				foreach ($rras as $unchosen_rra) {
					/* the timespan specified in the RRA "timespan" field may not be accurate */
					$real_timespan = ($ds_step * $unchosen_rra['steps'] * $unchosen_rra['rows']);

					/* make sure the current start/end times fit within each RRA's timespan */
					if ( (($graph_data_array['graph_end'] - $graph_data_array['graph_start']) <= $real_timespan) && ((time() - $graph_data_array['graph_start']) <= $real_timespan) ) {
						/* is this RRA better than the already chosen one? */
						if ((isset($rra)) && ($unchosen_rra['steps'] < $rra['steps'])) {
							$rra = $unchosen_rra;
						}else if (!isset($rra)) {
							$rra = $unchosen_rra;
						}
					}
				}
			}

			if (!isset($rra)) {
				$rra['rows'] = 600;
				$rra['steps'] = 1;
			}
		}
	}else{
		$rra = db_fetch_row_prepared('SELECT 
			rows, step, steps 
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dspr.data_source_profile_id=dsp.id
			WHERE dspr.id = ?', array($rra_id));

		$rra['timespan'] = $rra['rows'] * $rra['step'] * $rra['steps'];
	}

	if (!isset($graph_data_array['export_realtime'])) {
		$seconds_between_graph_updates = ($ds_step * $rra['steps']);
	}else{
		$seconds_between_graph_updates = 5;
	}

	$graph = db_fetch_row_prepared('SELECT gl.id AS local_graph_id, gl.host_id,
		gl.snmp_query_id, gl.snmp_index, gtg.title_cache, gtg.vertical_label,
		gtg.slope_mode, gtg.auto_scale, gtg.auto_scale_opts, gtg.auto_scale_log,
		gtg.scale_log_units, gtg.auto_scale_rigid, gtg.auto_padding, gtg.base_value,
		gtg.upper_limit, gtg.lower_limit, gtg.height, gtg.width, gtg.image_format_id,
		gtg.unit_value, gtg.unit_exponent_value, gtg.export, gtg.alt_y_grid, 
		gtg.right_axis, gtg.right_axis_label, gtg.right_axis_format, gtg.no_gridfit,
		gtg.unit_length, gtg.tab_width, gtg.dynamic_labels, gtg.force_rules_legend,
		gtg.legend_position, gtg.legend_direction, gtg.right_axis_formatter,
		gtg.left_axis_formatter
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id=gtg.local_graph_id
		WHERE gtg.local_graph_id = ?', array($local_graph_id));

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
		ORDER BY gti.sequence', array($local_graph_id));

	/* variables for use below */
	$graph_defs       = '';
	$txt_graph_items  = '';
	$padding_estimate = 0;
	$last_graph_type  = '';
	$rrdtool_version = read_config_option("rrdtool_version");

	/* override: graph start time */
	if ((!isset($graph_data_array['graph_start'])) || ($graph_data_array['graph_start'] == '0')) {
		$graph_start = -($rra['timespan']);
	}else{
		$graph_start = $graph_data_array['graph_start'];
	}

	/* override: graph end time */
	if ((!isset($graph_data_array['graph_end'])) || ($graph_data_array['graph_end'] == '0')) {
		$graph_end = -($seconds_between_graph_updates);
	}else{
		$graph_end = $graph_data_array['graph_end'];
	}

	/* +++++++++++++++++++++++ GRAPH OPTIONS +++++++++++++++++++++++ */

	if (!isset($graph_data_array['export_csv'])) {
		$graph_opts = rrd_function_process_graph_options($graph_start, $graph_end, $graph, $graph_data_array);
	}else{
		/* basic export options */
		$graph_opts =
			'--start=' . cacti_escapeshellarg($graph_start) . RRD_NL .
			'--end=' . cacti_escapeshellarg($graph_end) . RRD_NL .
			'--maxrows=10000' . RRD_NL;
	}

	/* +++++++++++++++++++++++ LEGEND: MAGIC +++++++++++++++++++++++ */

	$i = 0; $j = 0;
	$last_graph_cf = array();
	if (sizeof($graph_items)) {
		/* we need to add a new column 'cf_reference', so unless PHP 5 is used, this foreach syntax is required */
		foreach ($graph_items as $key => $graph_item) {
			/* mimic the old behavior: LINE[123], AREA and STACK items use the CF specified in the graph item */
			if (($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE1) ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE2) ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINE3) ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_LINESTACK) ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_TIC) ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_AREA)  ||
				($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_STACK)) {

				$graph_cf = $graph_item['consolidation_function_id'];
				/* remember the last CF for this data source for use with GPRINT
				 * if e.g. an AREA/AVERAGE and a LINE/MAX is used
				 * we will have AVERAGE first and then MAX, depending on GPRINT sequence */
				$last_graph_cf['data_source_name']['local_data_template_rrd_id'] = $graph_cf;
				/* remember this for second foreach loop */
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}elseif ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT) {
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
			}elseif ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_AVERAGE) {
				$graph_cf = $graph_item['consolidation_function_id'];
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}elseif ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_LAST) {
				$graph_cf = $graph_item['consolidation_function_id'];
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}elseif ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_MAX) {
				$graph_cf = $graph_item['consolidation_function_id'];
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}elseif ($graph_item['graph_type_id'] == GRAPH_ITEM_TYPE_GPRINT_MIN) {
				$graph_cf = $graph_item['consolidation_function_id'];
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}else{
				/* all other types are based on the best matching CF */
				$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id']);
				/* remember this for second foreach loop */
				$graph_items[$key]['cf_reference'] = $graph_cf;
			}

			if ((!empty($graph_item['local_data_id'])) && (!isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[$graph_cf]))) {
				/* use a user-specified ds path if one is entered */
				if (isset($graph_data_array['export_realtime'])) {
					$data_source_path = read_config_option('realtime_cache_path') . '/user_' . session_id() . '_' . $graph_item['local_data_id'] . '.rrd';
				}else{
					$data_source_path = get_data_source_path($graph_item['local_data_id'], true);
				}

				/* FOR WIN32: Escape all colon for drive letters (ex. D\:/path/to/rra) */
				$data_source_path = str_replace(':', '\:', $data_source_path);

				if (!empty($data_source_path)) {
					/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
					to a function that matches the digits with letters. rrdtool likes letters instead
					of numbers in DEF names; especially with CDEF's. cdef's are created
					the same way, except a 'cdef' is put on the beginning of the hash */
					$graph_defs .= 'DEF:' . generate_graph_def_name(strval($i)) . '=' . cacti_escapeshellarg($data_source_path) . ':' . cacti_escapeshellarg($graph_item['data_source_name'], true) . ':' . $consolidation_functions[$graph_cf] . RRD_NL;

					$cf_ds_cache{$graph_item['data_template_rrd_id']}[$graph_cf] = "$i";

					$i++;
				}
			}

			/* cache cdef value here to support data query variables in the cdef string */
			if (empty($graph_item['cdef_id'])) {
				$graph_item['cdef_cache'] = '';
				$graph_items[$j]['cdef_cache'] = '';
			}else{
				$graph_item['cdef_cache'] = get_cdef($graph_item['cdef_id']);
				$graph_items[$j]['cdef_cache'] = get_cdef($graph_item['cdef_id']);
			}

			/* cache vdef value here */
			if (empty($graph_item['vdef_id'])) {
				$graph_item['vdef_cache'] = '';
				$graph_items[$j]['vdef_cache'] = '';
			}else{
				$graph_item['vdef_cache'] = get_vdef($graph_item['vdef_id']);
				$graph_items[$j]['vdef_cache'] = get_vdef($graph_item['vdef_id']);
			}

			/* +++++++++++++++++++++++ LEGEND: TEXT SUBSTITUTION (<>'s) +++++++++++++++++++++++ */

			/* note the current item_id for easy access */
			$graph_item_id = $graph_item['graph_templates_item_id'];

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

			/* loop through each field that we want to substitute values for:
			currently: text format and value */
			while (list($field_name, $field_array) = each($variable_fields)) {
				/* certain fields do not require values when the legend is not to be shown */
				if (($field_array['process_no_legend'] == false) && (isset($graph_data_array['graph_nolegend']))) {
					continue;
				}

				$graph_variables[$field_name][$graph_item_id] = $graph_item[$field_name];

				/* date/time substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], '|date_time|')) {
					$graph_variables[$field_name][$graph_item_id] = str_replace('|date_time|', date('D d M H:i:s T Y', strtotime(db_fetch_cell("SELECT value FROM settings WHERE name='date'"))), $graph_variables[$field_name][$graph_item_id]);
				}

				/* data source title substitution */
				if (strstr($graph_variables[$field_name][$graph_item_id], '|data_source_title|')) {
					$graph_variables[$field_name][$graph_item_id] = str_replace('|data_source_title|', get_data_source_title($graph_item['local_data_id']), $graph_variables[$field_name][$graph_item_id]);
				}

				/* data query variables */
				$graph_variables[$field_name][$graph_item_id] = rrd_substitute_host_query_data($graph_variables[$field_name][$graph_item_id], $graph, $graph_item);

				/* Nth percentile */
				if (preg_match_all('/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate):(\d)?\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$graph_variables[$field_name][$graph_item_id] = str_replace($match[0], variable_nth_percentile($match, $graph_item, $graph_items, $graph_start, $graph_end), $graph_variables[$field_name][$graph_item_id]);
					}
				}

				/* bandwidth summation */
				if (preg_match_all('/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$graph_variables[$field_name][$graph_item_id] = str_replace($match[0], variable_bandwidth_summation($match, $graph_item, $graph_items, $graph_start, $graph_end, $rra['steps'], $ds_step), $graph_variables[$field_name][$graph_item_id]);
					}
				}
			}

			/* if we are not displaying a legend there is no point in us even processing the auto padding,
			text format stuff. */
			if (!isset($graph_data_array['graph_nolegend'])) {
				/* set hard return variable if selected (\n) */
				if ($graph_item['hard_return'] == 'on') {
					$hardreturn[$graph_item_id] = "\\n";
				}else{
					$hardreturn[$graph_item_id] = '';
				}

				/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>'s) +++++++++++++++++++++++ */

				/* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
				AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
				of course it can be used in other situations, however may not work as intended.
				If you have any additions to this small peice of code, feel free to send them to me. */
				if ($graph['auto_padding'] == 'on') {
					/* only applies to AREA, STACK and LINEs */
					if (preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types{$graph_item['graph_type_id']})) {
						$text_format_length = strlen(trim($graph_variables['text_format'][$graph_item_id]));

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
	reset($graph_items);

	/* hack for rrdtool 1.2.x support */
	$graph_item_stack_type = '';

	if (sizeof($graph_items)) {
	foreach ($graph_items as $graph_item) {
		/* first we need to check if there is a DEF for the current data source/cf combination. if so,
		we will use that */
		if (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}{$graph_item['consolidation_function_id']})) {
			$cf_id = $graph_item['consolidation_function_id'];
		}else{
		/* if there is not a DEF defined for the current data source/cf combination, then we will have to
		improvise. choose the first available cf in the following order: AVERAGE, MAX, MIN, LAST */
			if (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[1])) {
				$cf_id = 1; /* CF: AVERAGE */
			}elseif (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[3])) {
				$cf_id = 3; /* CF: MAX */
			}elseif (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[2])) {
				$cf_id = 2; /* CF: MIN */
			}elseif (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[4])) {
				$cf_id = 4; /* CF: LAST */
			}else{
				$cf_id = 1; /* CF: AVERAGE */
			}
		}
		/* now remember the correct CF reference */
		$cf_id = $graph_item['cf_reference'];

		/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's START +++++++++++++++++++++++ */

		/* make cdef string here; a note about CDEF's in cacti. A CDEF is neither unique to a
		data source of global cdef, but is unique when those two variables combine. */
		$cdef_graph_defs = '';

		if ((!empty($graph_item['cdef_id'])) && (!isset($cdef_cache{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id]))) {

			$cdef_string 	= $graph_variables['cdef_cache']{$graph_item['graph_templates_item_id']};
			$magic_item 	= array();
			$already_seen	= array();
			$sources_seen	= array();
			$count_all_ds_dups = 0;
			$count_all_ds_nodups = 0;
			$count_similar_ds_dups = 0;
			$count_similar_ds_nodups = 0;

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
				for ($t=0;($t<count($graph_items));$t++) {

					/* only work on graph items, omit GRPINTs, COMMENTs and stuff */
					if ((preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types{$graph_items[$t]['graph_type_id']})) && (!empty($graph_items[$t]['data_template_rrd_id']))) {
						/* if the user screws up CF settings, PHP will generate warnings if left unchecked */

						/* matching consolidation function? */
						if (isset($cf_ds_cache{$graph_items[$t]['data_template_rrd_id']}[$cf_id])) {
							$def_name = generate_graph_def_name(strval($cf_ds_cache{$graph_items[$t]['data_template_rrd_id']}[$cf_id]));

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
							if(!isset($already_seen[$def_name])) {
								if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
									$magic_item['ALL_DATA_SOURCES_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}
								if (isset($magic_item['COUNT_ALL_DS_NODUPS'])) {
									$magic_item['COUNT_ALL_DS_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}
								$count_all_ds_nodups++;
								$already_seen[$def_name]=TRUE;
							}

							/* check for SIMILAR data sources */
							if ($graph_item['data_source_name'] == $graph_items[$t]['data_source_name']) {

								/* do we need SIMILAR_DATA_SOURCES_DUPS? */
								if (isset($magic_item['SIMILAR_DATA_SOURCES_DUPS']) && ($graph_item['data_source_name'] == $graph_items[$t]['data_source_name'])) {
									$magic_item['SIMILAR_DATA_SOURCES_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}

								/* do we need COUNT_SIMILAR_DS_DUPS? */
								if (isset($magic_item['COUNT_SIMILAR_DS_DUPS']) && ($graph_item['data_source_name'] == $graph_items[$t]['data_source_name'])) {
									$magic_item['COUNT_SIMILAR_DS_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}

								$count_similar_ds_dups++;

								/* check if this item also qualifies for NODUPS  */
								if(!isset($sources_seen{$graph_items[$t]['data_template_rrd_id']})) {
									if (isset($magic_item['SIMILAR_DATA_SOURCES_NODUPS'])) {
										$magic_item['SIMILAR_DATA_SOURCES_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}
									if (isset($magic_item['COUNT_SIMILAR_DS_NODUPS']) && ($graph_item['data_source_name'] == $graph_items[$t]['data_source_name'])) {
										$magic_item['COUNT_SIMILAR_DS_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $seconds_between_graph_updates) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}
									$count_similar_ds_nodups++;
									$sources_seen{$graph_items[$t]['data_template_rrd_id']} = TRUE;
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

			$cdef_string = str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval((isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id]) ? $cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id] : '0'))), $cdef_string);

			/* ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
			if (isset($magic_item['ALL_DATA_SOURCES_DUPS'])) {
				$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS', $magic_item['ALL_DATA_SOURCES_DUPS'], $cdef_string);
			}
			if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
				$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS', $magic_item['ALL_DATA_SOURCES_NODUPS'], $cdef_string);
			}
			if (isset($magic_item['SIMILAR_DATA_SOURCES_DUPS'])) {
				$cdef_string = str_replace('SIMILAR_DATA_SOURCES_DUPS', $magic_item['SIMILAR_DATA_SOURCES_DUPS'], $cdef_string);
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

			/* make the initial 'virtual' cdef name: 'cdef' + [a,b,c,d...] */
			$cdef_graph_defs .= 'CDEF:cdef' . generate_graph_def_name(strval($i)) . '=';
			/* prohibit command injection and provide platform specific quoting */
			$cdef_graph_defs .= cacti_escapeshellarg(sanitize_cdef($cdef_string), true);
			$cdef_graph_defs .= " \\\n";

			/* the CDEF cache is so we do not create duplicate CDEF's on a graph */
			$cdef_cache{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id] = "$i";
		}

		/* add the cdef string to the end of the def string */
		$graph_defs .= $cdef_graph_defs;

		/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's END   +++++++++++++++++++++++ */

		/* +++++++++++++++++++++++ GRAPH ITEMS: VDEF's START +++++++++++++++++++++++ */

		/* make vdef string here, copied from cdef stuff */
		$vdef_graph_defs = '';

		if ((!empty($graph_item['vdef_id'])) && (!isset($vdef_cache{$graph_item['vdef_id']}{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id]))) {
			$vdef_string = $graph_variables['vdef_cache']{$graph_item['graph_templates_item_id']};
			/* do we refer to a CDEF within this VDEF? */
			if ($graph_item['cdef_id'] != '0') {
				/* 'calculated' VDEF: use (cached) CDEF as base, only way to get calculations into VDEFs */
				$vdef_string = 'cdef' . str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval(isset($cdef_cache{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id]) ? $cdef_cache{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id] : '0')), $vdef_string);
			} else {
				/* 'pure' VDEF: use DEF as base */
				$vdef_string = str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval(isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id]) ? $cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id] : '0')), $vdef_string);
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
			$vdef_cache{$graph_item['vdef_id']}{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id] = "$i";
		}

		/* add the cdef string to the end of the def string */
		$graph_defs .= $vdef_graph_defs;

		/* +++++++++++++++++++++++ GRAPH ITEMS: VDEF's END +++++++++++++++++++++++ */

		/* note the current item_id for easy access */
		$graph_item_id = $graph_item['graph_templates_item_id'];

		/* if we are not displaying a legend there is no point in us even processing the auto padding,
		text format stuff. */
		$pad_nubmer = 0;

		if ((!isset($graph_data_array['graph_nolegend'])) && ($graph['auto_padding'] == 'on')) {
			/* only applies to AREA, STACK and LINEs */
			if (preg_match('/(AREA|STACK|LINE[123]|TICK)/', $graph_item_types{$graph_item['graph_type_id']})) {
				$text_format_length = strlen($graph_variables['text_format'][$graph_item_id]);

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

			$last_graph_type = $graph_item_types{$graph_item['graph_type_id']};
		}

		/* we put this in a variable so it can be manipulated before mainly used
		if we want to skip it, like below */
		$current_graph_item_type = $graph_item_types{$graph_item['graph_type_id']};

		/* IF this graph item has a data source... get a DEF name for it, or the cdef if that applies
		to this graph item */
		if ($graph_item['cdef_id'] == '0') {
			if (isset($cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id])) {
				$data_source_name = generate_graph_def_name(strval($cf_ds_cache{$graph_item['data_template_rrd_id']}[$cf_id]));
			}else{
				$data_source_name = '';
			}
		}else{
			$data_source_name = 'cdef' . generate_graph_def_name(strval($cdef_cache{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id]));
		}

		/* IF this graph item has a data source... get a DEF name for it, or the vdef if that applies
		to this graph item */
		if ($graph_item['vdef_id'] == '0') {
			/* do not overwrite $data_source_name that stems from cdef above */
		}else{
			$data_source_name = 'vdef' . generate_graph_def_name(strval($vdef_cache{$graph_item['vdef_id']}{$graph_item['cdef_id']}{$graph_item['data_template_rrd_id']}[$cf_id]));
		}

		/* to make things easier... if there is no text format set; set blank text */
		if (!isset($graph_variables['text_format'][$graph_item_id])) {
			$graph_variables['text_format'][$graph_item_id] = '';
		} else {
			$graph_variables['text_format'][$graph_item_id] = str_replace('"', '\"', $graph_variables['text_format'][$graph_item_id]); /* escape doublequotes */
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
			if ($rrdtool_version != RRD_VERSION_1_2) {
				if (!empty($graph_item['dashes'])) {
					$dash .= ':dashes=' . $graph_item['dashes'];
				}

				if (!empty($graph_item['dash_offset'])) {
					$dash .= ':dash-offset=' . $graph_item['dash_offset'];
				}
			}
		}

		if (!isset($graph_data_array['export_csv'])) {
			switch($graph_item['graph_type_id']) {
			case GRAPH_ITEM_TYPE_COMMENT:
				if (!isset($graph_data_array['graph_nolegend'])) {
					# perform variable substitution first (in case this will yield an empty results or brings command injection problems)
					$comment_arg = rrd_substitute_device_query_data($graph_variables['text_format'][$graph_item_id], $graph, $graph_item);
					# next, compute the argument of the COMMENT statement and perform injection counter measures
					if (trim($comment_arg) == '') { # an empty COMMENT must be treated with care
						$comment_arg = cacti_escapeshellarg(' ' . $hardreturn[$graph_item_id]);
					} else {
						$comment_arg = cacti_escapeshellarg($comment_arg . $hardreturn[$graph_item_id]);
					}

					# create rrdtool specific command line
					$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . str_replace(':', '\:', $comment_arg) . ' ';
				}

				break;
			case GRAPH_ITEM_TYPE_TEXTALIGN:
				if (!isset($graph_data_array['graph_nolegend'])) {
					if (!empty($graph_item['textalign']) && $rrdtool_version != RRD_VERSION_1_2) {
						$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $graph_item['textalign'];
					}
				}

				break;
			case GRAPH_ITEM_TYPE_GPRINT:
				$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', $graph_variables['text_format'][$graph_item_id]);

				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $data_source_name . ':' . $consolidation_functions{$graph_item['consolidation_function_id']} . ':' . cacti_escapeshellarg(trim($graph_variables['text_format'][$graph_item_id]) . trim($graph_item['gprint_text']) . ($hardreturn[$graph_item_id] != '' ? $hardreturn[$graph_item_id]:'')) . ' ';

				break;
			case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
				if (!isset($graph_data_array['graph_nolegend'])) {
					$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', $graph_variables['text_format'][$graph_item_id]);

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":AVERAGE:'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}else{
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}
				}

				break;
			case GRAPH_ITEM_TYPE_GPRINT_LAST:
				if (!isset($graph_data_array['graph_nolegend'])) {
					$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', $graph_variables['text_format'][$graph_item_id]);

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":LAST:'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}else{
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}
				}

				break;
			case GRAPH_ITEM_TYPE_GPRINT_MAX:
				if (!isset($graph_data_array['graph_nolegend'])) {
					$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', $graph_variables['text_format'][$graph_item_id]);

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":MAX:'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}else{
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}
				}

				break;
			case GRAPH_ITEM_TYPE_GPRINT_MIN:
				if (!isset($graph_data_array['graph_nolegend'])) {
					$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', $graph_variables['text_format'][$graph_item_id]);

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":MIN:'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}else{
						$txt_graph_items .= 'GPRINT:' . $data_source_name . ":'" . $graph_variables['text_format'][$graph_item_id] . $graph_item['gprint_text'] . $hardreturn[$graph_item_id] . "' ";
					}
				}

				break;
			case GRAPH_ITEM_TYPE_AREA:
				$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', ($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id],$pad_number):''));

				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $data_source_name . $graph_item_color_code . ':' . "'" . $graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id] . "' ";

				if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
					$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
				}

				break;
			case GRAPH_ITEM_TYPE_STACK:
				$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', ($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id],$pad_number):''));

				$txt_graph_items .= 'AREA:' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . ':STACK';

				if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
					$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
				}

				break;
			case GRAPH_ITEM_TYPE_LINE1:
			case GRAPH_ITEM_TYPE_LINE2:
			case GRAPH_ITEM_TYPE_LINE3:
				$graph_variables['text_format'][$graph_item_id] = str_replace(':', '\:', ($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):'')); 

				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]) . ' ';

				if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
					$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
				}

				break;
			case GRAPH_ITEM_TYPE_LINESTACK:
				$txt_graph_items .= 'LINE' . $graph_item['line_width'] . ':' . $data_source_name . $graph_item_color_code . ':' . "'" . $graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id] . "':STACK" . $dash;

				if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
					$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
				}

				break;
			case GRAPH_ITEM_TYPE_TIC:
				$_fraction = (empty($graph_item['graph_type_id']) ? '' : (':' . $graph_item['value']));
				$_legend   = (empty($graph_variables['text_format'][$graph_item_id]) ? '' : (':' . "'" . $graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id] . "'"));
				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $data_source_name . $graph_item_color_code . $_fraction . $_legend;

				break;
			case GRAPH_ITEM_TYPE_HRULE:
				$graph_variables['value'][$graph_item_id] = str_replace(':', '\:', $graph_variables['value'][$graph_item_id]); /* escape colons */

				/* perform variable substitution; if this does not return a number, rrdtool will FAIL! */
				$substitute = rrd_substitute_device_query_data($graph_variables['value'][$graph_item_id], $graph, $graph_item);

				if (is_numeric($substitute)) {
					$graph_variables['value'][$graph_item_id] = $substitute;
				}

				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $graph_variables['value'][$graph_item_id] . $graph_item_color_code . ":'" . $graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id] . "'" . $dash;

				break;
			case GRAPH_ITEM_TYPE_VRULE:
				if (substr_count($graph_item['value'], ':')) {
					$value_array = explode(':', $graph_item['value']);

					if ($value_array[0] < 0) {
					$value = date('U') - (-3600 * $value_array[0]) - 60 * $value_array[1];
					}else{
					$value = date('U', mktime($value_array[0],$value_array[1],0));
					}
				}else if (is_numeric($graph_item['value'])) {
					$value = $graph_item['value'];
				}

				$txt_graph_items .= $graph_item_types{$graph_item['graph_type_id']} . ':' . $value . $graph_item_color_code . ":'" . $graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id] . "'" . $dash;

				break;
			default:
				$need_rrd_nl = FALSE;
			}
		}else{
			if (preg_match('/^(AREA|AREA:STACK|LINE[123]|STACK)$/', $graph_item_types{$graph_item['graph_type_id']})) {
				/* give all export items a name */
				if (trim($graph_variables['text_format'][$graph_item_id]) == '') {
					$legend_name = 'col' . $j . '-' . $data_source_name;
				}else{
					$legend_name = $graph_variables['text_format'][$graph_item_id];
				}
				$stacked_columns['col' . $j] = ($graph_item_types{$graph_item['graph_type_id']} == 'STACK') ? 1 : 0;
				$j++;

				$txt_graph_items .= 'XPORT:' . cacti_escapeshellarg($data_source_name) . ':' . str_replace(':', '', cacti_escapeshellarg($legend_name)) ;
			}else{
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
		}else{
			if (isset($graph_data_array['graphv'])) {
				$graph = 'graphv';
			}else{
				$graph = 'graph';
			}

			if (isset($graph_data_array['export'])) {
				rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, RRDTOOL_OUTPUT_NULL, $rrdtool_pipe);

				return 0;
			}elseif (isset($graph_data_array['export_realtime'])) {
			
				$output_flag = RRDTOOL_OUTPUT_GRAPH_DATA;
				$output = rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe);

				if ($fp = fopen($graph_data_array['export_realtime'], 'w')) {
					fwrite($fp, $output, strlen($output));
					fclose($fp);
					chmod($graph_data_array['export_realtime'], 0644);
				}

				return $output;
			}else{
				$graph_data_array = boost_prep_graph_array($graph_data_array);

				if (!isset($graph_data_array['output_flag'])) {
					$output_flag = RRDTOOL_OUTPUT_GRAPH_DATA;
				}else{
					$output_flag = $graph_data_array['output_flag'];
				}

				$output = rrdtool_execute("$graph $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe);

				boost_graph_set_file($output, $local_graph_id, $rra_id);

				return $output;
			}
		}
	}else{
		$output_flag = RRDTOOL_OUTPUT_STDOUT;

		$xport_array = rrdxport2array(rrdtool_execute("xport $graph_opts$graph_defs$txt_graph_items", false, $output_flag, $rrdtool_pipe));

		/* add host and graph information */
		$xport_array['meta']['stacked_columns']= $stacked_columns;
		$xport_array['meta']['title_cache']    = cacti_escapeshellarg(htmlspecialchars($graph['title_cache']));
		$xport_array['meta']['vertical_label'] = cacti_escapeshellarg(htmlspecialchars($graph['vertical_label']));
		$xport_array['meta']['local_graph_id'] = $local_graph_id;
		$xport_array['meta']['host_id']        = $graph['host_id'];

		return $xport_array;
	}
}

function rrdtool_function_xport($local_graph_id, $rra_id, $xport_data_array, &$xport_meta) {
	return rrdtool_function_graph($local_graph_id, $rra_id, $xport_data_array, '', $xport_meta);
}

function rrdtool_function_format_graph_date(&$graph_data_array) {
	$graph_legend = '';
	/* setup date format */
	$date_fmt = read_user_setting('default_date_format');
	$datechar = read_user_setting('default_datechar');

	if ($datechar == GDC_HYPHEN) {
		$datechar = '-';
	}else {
		$datechar = '/';
	}

	switch ($date_fmt) {
		case GD_MO_D_Y:
			$graph_date = 'm' . $datechar . 'd' . $datechar . 'Y H:i:s';
			break;
		case GD_MN_D_Y:
			$graph_date = 'M' . $datechar . 'd' . $datechar . 'Y H:i:s';
			break;
		case GD_D_MO_Y:
			$graph_date = 'd' . $datechar . 'm' . $datechar . 'Y H:i:s';
			break;
		case GD_D_MN_Y:
			$graph_date = 'd' . $datechar . 'M' . $datechar . 'Y H:i:s';
			break;
		case GD_Y_MO_D:
			$graph_date = 'Y' . $datechar . 'm' . $datechar . 'd H:i:s';
			break;
		case GD_Y_MN_D:
			$graph_date = 'Y' . $datechar . 'M' . $datechar . 'd H:i:s';
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
	}else{
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
		}else{
			$font = read_config_option($type . '_font');
			$size = read_config_option($type . '_size');
		}
	}elseif (isset($themefonts[$type]['font']) && isset($themefonts[$type]['size'])) {
		$font = $themefonts[$type]['font'];
		$size = $themefonts[$type]['size'];
	}else{
		return;
	}

	if(strlen($font)) {
		/* do some simple checks */
		if (read_config_option('rrdtool_version') == RRD_VERSION_1_2) {
			# rrdtool 1.2 uses font files
			if (!is_file($font)) {
				$font = '';
			}
		} else {	
			# rrdtool 1.3+ use fontconfig
			/* verifying all possible pango font params is too complex to be tested here
			 * so we only escape the font
			 */
			$font = cacti_escapeshellarg($font);
		}
	}

	if ($type == 'title') {
		if (!empty($no_legend)) {
			$size = $size * .70;
		}elseif (($size <= 4) || !is_numeric($size)) {
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
			$host_id = db_fetch_cell("SELECT host_id FROM data_local WHERE id='" . $graph_item['local_data_id'] . "'");
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
		}else{
			$data_local = db_fetch_row("SELECT snmp_index,snmp_query_id,host_id FROM data_local WHERE id='" . $graph_item['local_data_id'] . "'");
			$txt_graph_item = substitute_snmp_query_data($txt_graph_item, $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
		}
	}

	/* replace query variables in graph elements */
	if (preg_match('/\|input_[a-zA-Z0-9_]+\|/', $txt_graph_item)) {
		return substitute_data_input_data($txt_graph_item, $graph, $graph_item['local_data_id']);
	}

	return $txt_graph_item;
}

