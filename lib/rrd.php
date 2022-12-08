<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

define('RRD_NL', " \\\n");
define('MAX_FETCH_CACHE_SIZE', 5);

if (read_config_option('storage_location')) {
	/* load crypt libraries only if the Cacti RRDtool Proxy Server is in use */
	set_include_path($config['include_path'] . '/vendor/phpseclib/');
	include_once('Math/BigInteger.php');
	include_once('Crypt/Base.php');
	include_once('Crypt/Hash.php');
	include_once('Crypt/Random.php');
	include_once('Crypt/RSA.php');
	include_once('Crypt/Rijndael.php');

	global $encryption;
	$encryption = true;
}

function escape_command($command) {
	return $command;		# we escape every single argument now, no need for 'special' escaping
	#return preg_replace("/(\\\$|`)/", "", $command); # current cacti code
	#TODO return preg_replace((\\\$(?=\w+|\*|\@|\#|\?|\-|\\\$|\!|\_|[0-9]|\(.*\))|`(?=.*(?=`)))","$2", $command);  #suggested by ldevantier to allow for a single $
}

/** set the language environment variable for rrdtool functions
 * @param string $lang		- the desired language to set
 * @return null
 */
function rrdtool_set_language($lang = -1) {
	global $prev_lang;

	$prev_lang = getenv('LANG');

	if ($lang == -1) {
		putenv('LANG=' . str_replace('-', '_', CACTI_LOCALE) . '.UTF-8');
	} else {
		putenv('LANG=en_EN.UTF-8');
	}
}

/** restore the default language environment variable after rrdtool functions
 * @return null
 */
function rrdtool_reset_language() {
	global $prev_lang;

	putenv('LANG=' . $prev_lang);
}

function rrd_init($output_to_term = true) {
	global $config;

	$args = func_get_args();
	$force_storage_location_local = (isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true ) ? true : false;
	$function = ($force_storage_location_local === false && read_config_option('storage_location')) ? '__rrd_proxy_init' : '__rrd_init';
	return call_user_func_array($function, $args);
}

function __rrd_init($output_to_term = true) {
	global $config;

	/* set the rrdtool default font */
	if (read_config_option('path_rrdtool_default_font')) {
		putenv('RRD_DEFAULT_FONT=' . read_config_option('path_rrdtool_default_font'));
	}

	rrdtool_set_language();

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
	global $encryption;
	$terminator = "_EOT_\r\n";
	$encryption = true;
	$rsa = new \phpseclib\Crypt\RSA();

	$rrdp_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($rrdp_socket === false) {
		cacti_log('CACTI2RRDP ERROR: Unable to create socket to connect to RRDtool Proxy Server', false, $logopt, POLLER_VERBOSITY_LOW);
		return false;
	}

	if ( read_config_option('rrdp_load_balancing') == 'on' ) {
		$rrdp_id = rand(1,2);
		$rrdp = @socket_connect($rrdp_socket, (($rrdp_id == 1 ) ? read_config_option('rrdp_server') : read_config_option('rrdp_server_backup')), (($rrdp_id == 1 ) ? read_config_option('rrdp_port') : read_config_option('rrdp_port_backup')) );
	} else {
		$rrdp_id = 1;
		$rrdp = @socket_connect($rrdp_socket, read_config_option('rrdp_server'), read_config_option('rrdp_port'));
	}

	if ($rrdp === false) {
		/* log entry ... */
		cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool Proxy Server #' . $rrdp_id, false, $logopt, POLLER_VERBOSITY_LOW);

		/* ... and try to use backup path */
		$rrdp_id = ($rrdp_id + 1) % 2;
		$rrdp = @socket_connect($rrdp_socket, (($rrdp_id == 1 ) ? read_config_option('rrdp_server') : read_config_option('rrdp_server_backup')), (($rrdp_id == 1 ) ? read_config_option('rrdp_port') : read_config_option('rrdp_port_backup')) );

		if ($rrdp === false) {
			cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool Proxy Server #' . $rrdp_id, false, $logopt, POLLER_VERBOSITY_LOW);
			return false;
		}
	}

	$rrdp_fingerprint = ($rrdp_id == 1 ) ? read_config_option('rrdp_fingerprint') : read_config_option('rrdp_fingerprint_backup');

	socket_write($rrdp_socket, read_config_option('rsa_public_key') . $terminator);

	/* read public key being returned by the proxy server */
	$rrdp_public_key = '';
	while(1) {
		$recv = socket_read($rrdp_socket, 1000, PHP_BINARY_READ );
		if ($recv === false) {
			/* timeout  */
			cacti_log('CACTI2RRDP ERROR: Public RSA Key Exchange - Time-out while reading', false, $logopt, POLLER_VERBOSITY_LOW);
			$rrdp_public_key = false;
			break;
		} elseif ($recv == '') {
			cacti_log('CACTI2RRDP ERROR: Session closed by Proxy.', false, $logopt, POLLER_VERBOSITY_LOW);
			/* session closed by Proxy */
			break;
		} else {
			$rrdp_public_key .= $recv;
			if (strpos($rrdp_public_key, $terminator) !== false) {
				$rrdp_public_key = trim(trim($rrdp_public_key, $terminator));
				break;
			}
		}
	}

	$rsa->loadKey($rrdp_public_key);
	$fingerprint = $rsa->getPublicKeyFingerprint();

	if ($rrdp_fingerprint != $fingerprint) {
		cacti_log('CACTI2RRDP ERROR: Mismatch RSA Fingerprint.', false, $logopt, POLLER_VERBOSITY_LOW);
		return false;
	} else {
		$rrdproxy = array($rrdp_socket, $rrdp_public_key);
		/* set the rrdtool default font */
		if (read_config_option('path_rrdtool_default_font')) {
			rrdtool_execute("setenv RRD_DEFAULT_FONT '" . read_config_option('path_rrdtool_default_font') . "'", false, RRDTOOL_OUTPUT_NULL, $rrdproxy, $logopt = 'WEBLOG');
		}

		/* disable encryption */
		$encryption = rrdtool_execute('setcnn encryption off', false, RRDTOOL_OUTPUT_BOOLEAN, $rrdproxy, $logopt = 'WEBLOG') ? false : true;
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

	rrdtool_reset_language();
}

function __rrd_proxy_close($rrdp) {
	/* close the rrdtool proxy server connection */
	$terminator = "_EOT_\r\n";
	if ($rrdp) {
		socket_write($rrdp[0], encrypt('quit', $rrdp[1]) . $terminator);
		@socket_shutdown($rrdp[0], 2);
		@socket_close($rrdp[0]);
		return;
	}
}

function encrypt($output, $rsa_key) {
	global $encryption;

	if($encryption) {
		$rsa = new \phpseclib\Crypt\RSA();
		$aes = new \phpseclib\Crypt\Rijndael();
		$aes_key = \phpseclib\Crypt\Random::string(192);

		$aes->setKey($aes_key);
		$ciphertext = base64_encode($aes->encrypt($output));
		$rsa->loadKey($rsa_key);
		$aes_key = base64_encode($rsa->encrypt($aes_key));
		$aes_key_length = str_pad(dechex(strlen($aes_key)),3,'0',STR_PAD_LEFT);

		return $aes_key_length . $aes_key . $ciphertext;
	}else {
		return $output;
	}
}

function decrypt($input){
	global $encryption;

	if($encryption) {
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
	}else {
		return $input;
	}
}

function rrdtool_execute() {
	global $config;

	$args = func_get_args();
	$force_storage_location_local = (isset($config['force_storage_location_local']) && $config['force_storage_location_local'] === true) ? true : false;
	$function = ($force_storage_location_local === false && read_config_option('storage_location')) ? '__rrd_proxy_execute' : '__rrd_execute';

	return call_user_func_array($function, $args);
}

function __rrd_execute($command_line, $log_to_stdout, $output_flag, $rrdtool_pipe = false, $logopt = 'WEBLOG') {
	global $config;

	static $last_command;

	if (!is_numeric($output_flag)) {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;
	}

	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the backslash (\) characters. Unix does not care; win32 does.
	Also make sure to replace all of the backslashes at the end of the line,
	but make sure not to get rid of newlines (\n) that are supposed to be
	in there (text format) */
	$command_line = str_replace("\\\n", ' ', $command_line);

	/* output information to the log file if appropriate */
	cacti_log('CACTI2RRD: ' . read_config_option('path_rrdtool') . " $command_line", $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);

	$debug = '';
	/* if we want to see the error output from rrdtool; make sure to specify this */
	if ($config['cacti_server_os'] != 'win32') {
		if (($output_flag == RRDTOOL_OUTPUT_STDERR || $output_flag == RRDTOOL_OUTPUT_RETURN_STDERR) && !is_resource($rrdtool_pipe)) {
			$debug .= ' 2>&1';
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
		if (substr($command_line, 0, 5) == 'fetch' || substr($command_line, 0, 4) == 'info') {
			rrdtool_set_language('en');
		} else {
			rrdtool_set_language();
		}

		cacti_session_close();

		if (is_file(read_config_option('path_rrdtool')) && is_executable(read_config_option('path_rrdtool'))) {
			$descriptorspec = array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w')
			);

            if ($config['is_web']) {
                if (isset($_COOKIE['CactiTimeZone'])) {
                    $gmt_offset = $_COOKIE['CactiTimeZone'];
                    cacti_time_zone_set($gmt_offset);
                }
            }

			$process = proc_open(read_config_option('path_rrdtool') . ' - ' . $debug, $descriptorspec, $pipes);

			if (!is_resource($process)) {
				unset($process);
			} else {
				fwrite($pipes[0], escape_command($command_line) . "\r\nquit\r\n");
				fclose($pipes[0]);
				$fp = $pipes[1];
			}
		} else {
			cacti_log("ERROR: RRDtool executable not found, not executable or error in path '" . read_config_option('path_rrdtool') . "'.  No output written to RRDfile.");
		}

		rrdtool_reset_language();
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

			if (isset($process)) {
				fclose($fp);
				proc_close($process);
			}

			rrdtool_trim_output($output);

			return $output;
			break;
		case RRDTOOL_OUTPUT_STDERR:
		case RRDTOOL_OUTPUT_RETURN_STDERR:
			$output = fgets($fp, 1000000);

			if (isset($process)) {
				fclose($fp);
				proc_close($process);
			}

			rrdtool_trim_output($output);

			if (substr($output, 1, 3) == 'PNG') {
				return 'OK';
			}

			if (substr($output, 0, 5) == '<?xml') {
				return 'SVG/XML Output OK';
			}

			if ($output_flag == RRDTOOL_OUTPUT_RETURN_STDERR) {
				return $output;
			} else {
				print $output;
			}

			break;
		case RRDTOOL_OUTPUT_NULL:
		default:
			return;
			break;
	}
}

function rrdtool_trim_output(&$output) {
	/* When using RRDtool with proc_open for long strings
	 * and using the '-' to handle standard in from inside
	 * the process, RRDtool automatically appends stderr
	 * to stdout for batch programs to parse the output
	 * string.  So, therefore, we have to prune that
	 * output.
	 */
	$okpos = strrpos($output, 'OK u:');
	if ($okpos !== false) {
		$output = substr($output, 0, $okpos);
	}
}

function __rrd_proxy_execute($command_line, $log_to_stdout, $output_flag, $rrdp='', $logopt = 'WEBLOG') {
	global $config, $encryption;

	static $last_command;
	$end_of_packet = "_EOP_\r\n";
	$end_of_sequence = "_EOT_\r\n";

	if (!is_numeric($output_flag)) {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;
	}

	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the '\' characters. Unix does not care; win32 does.
	Also make sure to replace all of the fancy "\"s at the end of the line,
	but make sure not to get rid of the "\n"s that are supposed to be
	in there (text format) */
	$command_line = str_replace(array($config['rra_path'], "\\\n"), array('.', ' '), $command_line);

	/* output information to the log file if appropriate */
	cacti_log('CACTI2RRDP: ' . read_config_option('path_rrdtool') . " $command_line", $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);

	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;
	$rrdp_auto_close = false;

	if (!$rrdp) {
		$rrdp = __rrd_proxy_init($logopt);
		$rrdp_auto_close = true;
	}

	if (!$rrdp) {
		cacti_log('CACTI2RRDP ERROR: Unable to connect to RRDtool proxy.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
		return null;
	} else {
		cacti_log('CACTI2RRDP NOTE: Connection to RRDtool proxy has already been established.', $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
	}

	$rrdp_socket = $rrdp[0];
	$rrdp_public_key = $rrdp[1];

	if (strlen($command_line) >= 8192) {
		$command_line = gzencode($command_line, 1);
	}
	socket_write($rrdp_socket, encrypt($command_line, $rrdp_public_key) . $end_of_sequence);

	$input = '';
	$output = '';

	while(1) {
		$recv = socket_read($rrdp_socket, 100000, PHP_BINARY_READ );
		if ($recv === false) {
			cacti_log('CACTI2RRDP ERROR: Data Transfer - Time-out while reading.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
			break;
		} elseif ($recv == '') {
			/* session closed by Proxy */
			if ($output) {
				cacti_log('CACTI2RRDP ERROR: Session closed by Proxy.', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
			}
			break;
		} else {
			$input .= $recv;
			if (strpos($input, $end_of_sequence) !== false) {
				$input = str_replace($end_of_sequence, '', $input);
				$transactions = explode($end_of_packet, $input);
				foreach ($transactions as $transaction) {
					$packet = $transaction;
					$transaction = decrypt($transaction);
					if($transaction === false){
						cacti_log("CACTI2RRDP ERROR: Proxy message decryption failed: ###". $packet . '###', $log_to_stdout, $logopt, POLLER_VERBOSITY_LOW);
						break 2;
					}
					if(strpos($transaction, "\x1f\x8b") === 0) {
						$transaction = gzdecode($transaction);
					}
					$output .= $transaction;
					if (substr_count($output, 'OK u') || substr_count($output, 'ERROR:')) {
						cacti_log('RRDP: ' . $output, $log_to_stdout, $logopt, POLLER_VERBOSITY_DEBUG);
						break 2;
					}
				}
			}
		}
	}

	if ($rrdp_auto_close) {
		__rrd_proxy_close($rrdp);
	}

	switch ($output_flag) {
		case RRDTOOL_OUTPUT_NULL:
			return;
		case RRDTOOL_OUTPUT_STDOUT:
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			return rtrim(substr($output, 0, strpos($output, 'OK u')));
			break;
		case RRDTOOL_OUTPUT_STDERR:
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
		case RRDTOOL_OUTPUT_BOOLEAN :
			return (substr_count($output, 'OK u')) ? true : false;
			break;
	}
}

function rrdtool_function_interface_speed($data_local) {
	$ifHighSpeed = db_fetch_cell_prepared('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND snmp_index = ?
		AND field_name="ifHighSpeed"',
		array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index'])
	);

	$ifSpeed = db_fetch_cell_prepared('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND snmp_index = ?
		AND field_name="ifSpeed"',
		array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index'])
	);

	if (!empty($ifHighSpeed)) {
		$speed = $ifHighSpeed * 1000000;
	} elseif (!empty($ifSpeed)) {
		$speed = $ifSpeed;
	} else {
		$speed = read_config_option('default_interface_speed');

		if (empty($speed)) {
			$speed = '10000000000000';
		} else {
			$speed = $speed * 1000000;
		}
	}

	return $speed;
}

function rrdtool_function_create($local_data_id, $show_source, $rrdtool_pipe = false) {
	global $config, $data_source_types, $consolidation_functions, $encryption;

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
	if (cacti_sizeof($rras) <= 0) {
		cacti_log("ERROR: There are no RRA's assigned to local_data_id: $local_data_id.");
		return false;
	}

	/* create the "--step" line */
	$create_ds = RRD_NL . '--start 0 --step '. $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/**
	 * Only use the Data Sources that are included in the Graph in the case that there
	 * is a Data Template that includes more Data Sources than there Graph Template
	 * uses.
	 */
	$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.rrd_heartbeat,
		dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
		FROM data_template_rrd AS dtr
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		WHERE local_data_id = ?
		ORDER BY local_data_template_rrd_id',
		array($local_data_id)
	);

	/**
	 * ONLY make a new DS entry if:
	 *
	 * - There are multiple data sources and this item is not the main one.
	 * - There are only one data source (then use it)
	 */
	if (cacti_sizeof($data_sources)) {
		$data_local = db_fetch_row_prepared('SELECT host_id,
			snmp_query_id, snmp_index
			FROM data_local
			WHERE id = ?',
			array($local_data_id)
		);

		$speed = rrdtool_function_interface_speed($data_local);

		foreach ($data_sources as $data_source) {
			/* use the cacti ds name by default or the user defined one, if entered */
			$data_source_name = get_data_source_item_name($data_source['id']);

			// Trim the data source maximum
			$data_source['rrd_maximum'] = trim($data_source['rrd_maximum']);

			if ($data_source['rrd_maximum'] == 'U') {
				/* in case no maximum is given, use "Undef" value */
				$data_source['rrd_maximum'] = 'U';
			} elseif (strpos($data_source['rrd_maximum'], '|query_') !== false) {
				/* in case a query variable is given, evaluate it */
				if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
					$data_source['rrd_maximum'] = $speed;
				} else {
					$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'], $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
				}
			} elseif ($data_source['rrd_maximum'] != 'U' && (int)$data_source['rrd_maximum'] <= (int)$data_source['rrd_minimum']) {
				/* max > min required, but take care of an "Undef" value */
				if ($data_source['data_source_type_id'] == 1 || $data_source['data_source_type_id'] == 4) {
					$data_source['rrd_maximum'] = 'U';
				} else {
					$data_source['rrd_maximum'] = (int)$data_source['rrd_minimum'] + 1;
				}
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

	if ($config['cacti_server_os'] != 'win32') {
		$owner_id = fileowner($config['rra_path']);
		$group_id = filegroup($config['rra_path']);
	}

	/**
	 * check for structured path configuration, if in place verify directory
	 * exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
		if (read_config_option('storage_location')) {
			if (false === rrdtool_execute('is_dir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
				if (false === rrdtool_execute('mkdir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER') ) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			}
		} elseif (!is_dir(dirname($data_source_path))) {
			if ($config['is_web'] == false || is_writable($config['rra_path'])) {
				if (mkdir(dirname($data_source_path), 0775, true)) {
					if ($config['cacti_server_os'] != 'win32' && posix_getuid() == 0) {
						$success  = true;
						$paths    = explode('/', str_replace($config['rra_path'], '/', dirname($data_source_path)));
						$spath    = '';

						foreach($paths as $path) {
							if ($path == '') {
								continue;
							}

							$spath .= '/' . $path;

							$powner_id = fileowner($config['rra_path'] . $spath);
							$pgroup_id = fileowner($config['rra_path'] . $spath);

							if ($powner_id != $owner_id) {
								$success = chown($config['rra_path'] . $spath, $owner_id);
							}

							if ($pgroup_id != $group_id && $success) {
								$success = chgrp($config['rra_path'] . $spath, $group_id);
							}

							if (!$success) {
								cacti_log("ERROR: Unable to set directory permissions for '" . $config['rra_path'] . $spath . "'", false);
								break;
							}
						}
					}
				} else {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			} else {
				cacti_log("WARNING: Poller has not created structured path '" . dirname($data_source_path) . "' yet.", false);
			}
		}
	}

	if ($show_source == true) {
		return read_config_option('path_rrdtool') . ' create' . RRD_NL . "$data_source_path$create_ds$create_rra";
	} else {
		$success = rrdtool_execute("create $data_source_path $create_ds$create_rra", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');

		if ($config['cacti_server_os'] != 'win32' && posix_getuid() == 0) {
			shell_exec("chown $owner_id:$group_id $data_source_path");
		}

		return $success;
	}
}

function rrdtool_function_update($update_cache_array, $rrdtool_pipe = false) {
	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	foreach ($update_cache_array as $rrd_path => $rrd_fields) {
		$create_rrd_file = false;

		if (is_array($rrd_fields['times']) && cacti_sizeof($rrd_fields['times'])) {
			/* create the rrd if one does not already exist */
			if (read_config_option('storage_location') > 0) {
				$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER');
			} else {
				$file_exists = file_exists($rrd_path);
			}

			ksort($rrd_fields['times']);

			if ($file_exists === false) {
				$times = array_keys($rrd_fields['times']);
				rrdtool_function_create($rrd_fields['local_data_id'], false, $rrdtool_pipe);
				$create_rrd_file = true;
			}

			foreach ($rrd_fields['times'] as $update_time => $field_array) {
				if (empty($update_time)) {
					/* default the rrdupdate time to now */
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

				if (cacti_version_compare(get_rrdtool_version(),'1.5','>=')) {
					$update_options='--skip-past-updates';
				} else {
					$update_options='';
				}

				rrdtool_execute("update $rrd_path $update_options --template $rrd_update_template $rrd_update_values", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'POLLER');
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
			if (is_file(read_config_option('path_rrdtool')) && is_executable(read_config_option('path_rrdtool'))) {
				$fp = popen(read_config_option('path_rrdtool') . " tune $data_source_path $rrd_tune", 'r');
				pclose($fp);

				cacti_log('CACTI2RRD: ' . read_config_option('path_rrdtool') . " tune $data_source_path $rrd_tune", false, 'WEBLOG', POLLER_VERBOSITY_DEBUG);
			} else {
				cacti_log("ERROR: RRDtool executable not found, not executable or error in path '" . read_config_option('path_rrdtool') . "'.  No output written to RRDfile.");
			}
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
     an item called 'nth_percentile_maximum'.  The array will look as follows:

     $fetch_array['data_source_names'][0] = 'ds1'
     $fetch_array['data_source_names'][1] = 'ds2'
     $fetch_array['data_source_names'][2] = 'nth_percentile_maximum'
     $fetch_array['start_time'] = $timestamp;
     $fetch_array['end_time']   = $timestamp;
     $fetch_array['values'][$dsindex1][...]  = $value;
     $fetch_array['values'][$dsindex2][...]  = $value;
     $fetch_array['values'][$nth_index][...] = $value;

     Again, the 'nth_percentile_maximum' will have the maximum value amongst all the
     data sources for each set of data.  So, if you have traffic_in and traffic_out,
     each member element in the array will have the maximum of traffic_in and traffic_out
     in it.
 */
function rrdtool_function_fetch($local_data_id, $start_time, $end_time, $resolution = 0, $show_unknown = false, $rrdtool_file = null, $cf = 'AVERAGE', $rrdtool_pipe = false) {
	global $config;

	include_once($config['library_path'] . '/boost.php');

	/* validate local data id */
	if (empty($local_data_id) && is_null($rrdtool_file)) {
		return array();
	}

	$time = time();

	/* initialize fetch array */
	$fetch_array = array();

	/* check if we have been passed a file instead of local data source to look up */
	if (is_null($rrdtool_file)) {
		$data_source_path = get_data_source_path($local_data_id, true);
	} else {
		$data_source_path = $rrdtool_file;
	}

	// Find the correct resolution
	if ($resolution == 0) {
		$resolution = rrdtool_function_get_resstep($local_data_id, $start_time, $end_time, 'res');
	}

	/* update the rrdfile if performing a fetch */
	boost_fetch_cache_check($local_data_id, $rrdtool_pipe);

	/* build and run the rrdtool fetch command with all of our data */
	$cmd_line = "fetch $data_source_path $cf -s $start_time -e $end_time";
	if ($resolution > 0) {
		$cmd_line .= " -r $resolution";
	}

	$output = rrdtool_execute($cmd_line, false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe);
	$output = explode("\n", $output);

	$first  = true;
	$count  = 0;

	if (cacti_sizeof($output)) {
		$timestamp = 0;

		foreach($output as $line) {
			$line      = trim($line);
			$max_array = array();

			if ($first) {
				/* get the data source names */
				$fetch_array['data_source_names'] = preg_split('/\s+/', $line);
				$first = false;
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
							$fetch_array['values'][$index][$timestamp] = 'U';
						}
					} else {
						$fetch_array['values'][$index][$timestamp] = $number + 0;
					}
				}
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
	$version             = get_rrdtool_version();
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

	if (isset($graph_data_array['image_format']) && $graph_data_array['image_format'] == 'png') {
		$graph['image_format_id'] = 1;
	}

	/* basic graph options */
	$graph_opts .=
		'--imgformat=' . $image_types[$graph['image_format_id']] . RRD_NL .
		'--start=' . cacti_escapeshellarg($graph_start) . RRD_NL .
		'--end=' . cacti_escapeshellarg($graph_end) . RRD_NL;

	$graph_opts .= '--pango-markup ' . RRD_NL;

	if (read_config_option('rrdtool_watermark') == 'on') {
		$graph_opts .= '--disable-rrdtool-tag ' . RRD_NL;
	}

	foreach($graph as $key => $value) {
		switch($key) {
		case 'title_cache':
			if (!empty($value)) {
				$graph_opts .= '--title=' . cacti_escapeshellarg(html_escape($value)) . RRD_NL;
			}
			break;
		case 'alt_y_grid':
			if ($value == CHECKED)  {
				$graph_opts .= '--alt-y-grid' . RRD_NL;
			}
			break;
		case 'unit_value':
			if (!empty($value)) {
				$graph_opts .= '--y-grid=' . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case 'unit_exponent_value':
			if (preg_match('/^[0-9]+$/', $value)) {
				$graph_opts .= '--units-exponent=' . $value . RRD_NL;
			}
			break;
		case 'height':
			if (isset($graph_data_array['graph_height']) && preg_match('/^[0-9]+$/', $graph_data_array['graph_height'])) {
				$graph_opts .= '--height=' . $graph_data_array['graph_height'] . RRD_NL;
			} else {
				$graph_opts .= '--height=' . $value . RRD_NL;
			}
			break;
		case 'width':
			if (isset($graph_data_array['graph_width']) && preg_match('/^[0-9]+$/', $graph_data_array['graph_width'])) {
				$graph_opts .= '--width=' . $graph_data_array['graph_width'] . RRD_NL;
			} else {
				$graph_opts .= '--width=' . $value . RRD_NL;
			}
			break;
		case 'graph_nolegend':
			if (isset($graph_data_array['graph_nolegend'])) {
				$graph_opts .= '--no-legend' . RRD_NL;
			} else {
				$graph_opts .= '';
			}
			break;
		case 'base_value':
			if ($value == 1000 || $value == 1024) {
			$graph_opts .= '--base=' . $value . RRD_NL;
			}
			break;
		case 'vertical_label':
			if (!empty($value)) {
				$graph_opts .= '--vertical-label=' . cacti_escapeshellarg(html_escape($value)) . RRD_NL;
			}
			break;
		case 'slope_mode':
			if ($value == CHECKED) {
				$graph_opts .= '--slope-mode' . RRD_NL;
			}
			break;
		case 'right_axis':
			if (!empty($value)) {
				$graph_opts .= '--right-axis ' . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case 'right_axis_label':
			if (!empty($value)) {
				$graph_opts .= '--right-axis-label ' . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case 'right_axis_format':
			if (!empty($value)) {
				$format = db_fetch_cell_prepared('SELECT gprint_text from graph_templates_gprint WHERE id = ?', array($value));
				$graph_opts .= '--right-axis-format ' . cacti_escapeshellarg(trim(str_replace('%s', '', $format))) . RRD_NL;
			}
			break;
		case 'no_gridfit':
			if ($value == CHECKED) {
				$graph_opts .= '--no-gridfit' . RRD_NL;
			}
			break;
		case 'unit_length':
			if (!empty($value)) {
				$graph_opts .= '--units-length ' . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case 'tab_width':
			if (!empty($value)) {
				$graph_opts .= '--tabwidth ' . cacti_escapeshellarg($value) . RRD_NL;
			}
			break;
		case 'dynamic_labels':
			if ($value == CHECKED) {
				$graph_opts .= '--dynamic-labels' . RRD_NL;
			}
			break;
		case 'force_rules_legend':
			if ($value == CHECKED) {
				$graph_opts .= '--force-rules-legend' . RRD_NL;
			}
			break;
		case 'legend_position':
			if (cacti_version_compare($version, '1.4', '>=')) {
				if (!empty($value)) {
					$graph_opts .= '--legend-position ' . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'legend_direction':
			if (cacti_version_compare($version, '1.4', '>=')) {
				if (!empty($value)) {
					$graph_opts .= '--legend-direction ' . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'left_axis_formatter':
			if (cacti_version_compare($version, '1.4', '>=')) {
				if (!empty($value)) {
					$graph_opts .= '--left-axis-formatter ' . cacti_escapeshellarg($value) . RRD_NL;
				}
			}
			break;
		case 'right_axis_formatter':
			if (cacti_version_compare($version, '1.4', '>=')) {
				if (!empty($value)) {
					$graph_opts .= '--right-axis-formatter ' . cacti_escapeshellarg($value) . RRD_NL;
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

	/* if the user desires a watermark set it */
	$watermark = str_replace("'", '"', read_config_option('graph_watermark'));
	if ($watermark != '') {
		$graph_opts .= '--watermark ' . cacti_escapeshellarg($watermark) . RRD_NL;
	}

	return $graph_opts;
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe = false, &$xport_meta = array(), $user = 0) {
	global $config, $consolidation_functions, $graph_item_types, $encryption;

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

	if (getenv('LANG') == '') {
		putenv('LANG=' . str_replace('-', '_', CACTI_LOCALE) . '.UTF-8');
	}

	/* check the purge the boost poller output cache, and check for a live image file if caching is enabled */
	$graph_data = boost_graph_cache_check($local_graph_id, $rra_id, $rrdtool_pipe, $graph_data_array, false);
	if ($graph_data !== false) {
		return $graph_data;
	}

	if (empty($graph_data_array['graph_start'])) {
		$graph_data_array['graph_start'] = -86400;
	}

	if (empty($graph_data_array['graph_end'])) {
		$graph_data_array['graph_end']   = -300;
	}

	$local_data_ids = array_rekey(
		db_fetch_assoc_prepared('SELECT dtr.local_data_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			WHERE dtr.local_data_id > 0
			AND gti.local_graph_id = ?',
			array($local_graph_id)),
		'local_data_id', 'local_data_id'
	);

	$ds_step = rrdtool_function_get_resstep($local_data_ids, $graph_data_array['graph_start'], $graph_data_array['graph_end'], 'step');

	/* if no rra was specified, we need to figure out which one RRDtool will choose using
	 * "best-fit" resolution fit algorithm */
	if (empty($rra_id)) {
		if (empty($graph_data_array['graph_start']) || empty($graph_data_array['graph_end'])) {
			$rra['rows']     = 600;
			$rra['steps']    = 1;
			$rra['timespan'] = 86400;
		} else {
			/* get a list of RRAs related to this graph */
			$rras = get_associated_rras($local_graph_id);

			if (cacti_sizeof($rras)) {
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
		$rra_seconds = ($ds_step * $rra['steps']);
	} else {
		$rra_seconds = 5;
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
	if (!cacti_sizeof($graph)) {
		return false;
	}

	/* lets make that sql query... */
	$graph_items = db_fetch_assoc_prepared('SELECT gti.id AS graph_templates_item_id,
		gti.cdef_id, gti.vdef_id, gti.text_format, gti.value, gti.hard_return,
		gti.consolidation_function_id, gti.graph_type_id, gtgp.gprint_text,
		colors.hex, gti.alpha, gti.line_width, gti.dashes, gti.shift,
		gti.dash_offset, gti.textalign, dl.snmp_query_id, dl.snmp_index,
		dtr.id AS data_template_rrd_id, dtr.local_data_id,
		dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_name, dtr.local_data_template_rrd_id
		FROM graph_templates_item AS gti
		LEFT JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		LEFT JOIN data_local AS dl
		ON dl.id = dtr.local_data_id
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
	$pad_number       = 0;

	/* override: graph start time */
	if (!isset($graph_data_array['graph_start']) || $graph_data_array['graph_start'] == '0') {
		$graph_start = -($rra['timespan']);
	} else {
		$graph_start = $graph_data_array['graph_start'];
	}

	/* override: graph end time */
	if (!isset($graph_data_array['graph_end']) || $graph_data_array['graph_end'] == '0') {
		$graph_end = -($rra_seconds);
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
	$dateTimeFormat    = read_config_option('graph_dateformat');
	$cactiLastDate     = read_config_option('date');

	if (empty($cactiLastDate)) {
		$cactiLastDate = date('Y-m-d H:i:s');
	}

	$dateTime = date($dateTimeFormat, strtotime($cactiLastDate));

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
	$nth = 0;
	$sum = 0;
	$last_graph_cf = array();
	if (cacti_sizeof($graph_items)) {
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
					$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id'], $rra_seconds);
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
						$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id'], $rra_seconds);
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
					$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id'], $rra_seconds);
					/* remember this for second foreach loop */
					$graph_items[$key]['cf_reference'] = $graph_cf;
					break;
			}

			if (!empty($graph_item['local_data_id']) && !isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$graph_cf])) {
				/* use a user-specified ds path if one is entered */
				if (isset($graph_data_array['export_realtime'])) {
					$data_source_path = $realtimeCachePath . '/user_' . hash('sha256',session_id()) . '_' . $graph_item['local_data_id'] . '.rrd';
				} else {
					$data_source_path = get_data_source_path($graph_item['local_data_id'], true);
				}

				/* FOR WIN32: Escape all colon for drive letters (ex. D\:/path/to/rra) */
				$data_source_path = rrdtool_escape_string($data_source_path);

				if (!empty($data_source_path)) {
					/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
					to a function that matches the digits with letters. rrdtool likes letters instead
					of numbers in DEF names; especially with CDEFs. CDEFs are created
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

			/* +++++++++++++++++++++++ LEGEND: TEXT SUBSTITUTION (<>) +++++++++++++++++++++++ */

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
				if (preg_match_all('/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate_peak|aggregate):(\d)?\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$search[]  = $match[0];
						$value     = variable_nth_percentile($match, $graph, $graph_item, $graph_items, $graph_start, $graph_end);
						$replace[] = $value;

						if ($field_name == 'value') {
							$xport_meta['NthPercentile'][$nth]['format'] = $match[0];
							$xport_meta['NthPercentile'][$nth]['value']  = str_replace($match[0], $value, $graph_variables[$field_name][$graph_item_id]);
							$nth++;
						}
					}
				}

				/* bandwidth summation */
				if (preg_match_all('/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/', $graph_variables[$field_name][$graph_item_id], $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$search[]  = $match[0];
						$value     = variable_bandwidth_summation($match, $graph, $graph_item, $graph_items, $graph_start, $graph_end, $rra['steps'], $ds_step);
						$replace[] = $value;

						if ($field_name == 'text_format') {
							$xport_meta['Summation'][$sum]['format'] = $match[0];
							$xport_meta['Summation'][$sum]['value']  = str_replace($match[0], $value, $graph_variables[$field_name][$graph_item_id]);
							$sum++;
						}
					}
				}

				if (cacti_count($search)) {
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
			}

			$j++;
		}
	}

	/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>) +++++++++++++++++++++++ */
	if (cacti_sizeof($graph_items)) {
		/* we need to add a new column 'cf_reference', so unless PHP 5 is used, this foreach
		 * syntax is required
		 */
		foreach ($graph_items as $key => $graph_item) {
			/* note the current item_id for easy access */
			$graph_item_id = $graph_item['graph_templates_item_id'];

			/* if we are not displaying a legend there is no point in us even processing the
			 * auto padding, text format stuff.
			 */
			if (!isset($graph_data_array['graph_nolegend'])) {
				/* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
				 * AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
				 * of course it can be used in other situations, however may not work as intended.
				 * If you have any additions to this small piece of code, feel free to send them to me.
				 */
				if ($graph['auto_padding'] == 'on') {
					/* only applies to AREA, STACK and LINEs */
					if (preg_match('/(AREA|STACK|LINE[123])/', $graph_item_types[$graph_item['graph_type_id']])) {
						$text_format_length = mb_strlen(trim($graph_variables['text_format'][$graph_item_id]), 'UTF-8');

						if ($text_format_length > $pad_number) {
							$pad_number = $text_format_length;
						}
					}
				}
			}
		}
	}
	/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>) +++++++++++++++++++++++ */

	/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF +++++++++++++++++++++++ */

	$i = 0;

	/* hack for rrdtool 1.2.x support */
	$graph_item_stack_type = '';

	if (cacti_sizeof($graph_items)) {
		foreach ($graph_items as $graph_item) {
			/* hack around RRDtool behavior in first RRA */
			$graph_cf = generate_graph_best_cf($graph_item['local_data_id'], $graph_item['consolidation_function_id'], $rra_seconds);

			/* first we need to check if there is a DEF for the current data source/cf combination. if so,
			we will use that */
			if (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$graph_cf])) {
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

			/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF START +++++++++++++++++++++++ */

			/* make cdef string here; a note about CDEFs in cacti. A CDEF is neither unique to a
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
									$magic_item['ALL_DATA_SOURCES_DUPS'] .= ($count_all_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
								}

								/* do we need COUNT_ALL_DS_DUPS? */
								if (isset($magic_item['COUNT_ALL_DS_DUPS'])) {
									$magic_item['COUNT_ALL_DS_DUPS'] .= ($count_all_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
								}

								$count_all_ds_dups++;

								/* check if this item also qualifies for NODUPS  */
								if (!isset($already_seen[$def_name])) {
									if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
										$magic_item['ALL_DATA_SOURCES_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}

									if (isset($magic_item['COUNT_ALL_DS_NODUPS'])) {
										$magic_item['COUNT_ALL_DS_NODUPS'] .= ($count_all_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}

									$count_all_ds_nodups++;
									$already_seen[$def_name] = true;
								}

								/* check for SIMILAR data sources */
								if ($graph_item['data_source_name'] == $gi_check['data_source_name']) {
									/* do we need SIMILAR_DATA_SOURCES_DUPS? */
									if (isset($magic_item['SIMILAR_DATA_SOURCES_DUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
										$magic_item['SIMILAR_DATA_SOURCES_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
									}

									/* do we need COUNT_SIMILAR_DS_DUPS? */
									if (isset($magic_item['COUNT_SIMILAR_DS_DUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
										$magic_item['COUNT_SIMILAR_DS_DUPS'] .= ($count_similar_ds_dups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
									}

									$count_similar_ds_dups++;

									/* check if this item also qualifies for NODUPS  */
									if (!isset($sources_seen[$gi_check['data_template_rrd_id']])) {
										if (isset($magic_item['SIMILAR_DATA_SOURCES_NODUPS'])) {
											$magic_item['SIMILAR_DATA_SOURCES_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,$def_name,$def_name,UN,0,$def_name,IF,IF"; /* convert unknowns to '0' first */
										}

										if (isset($magic_item['COUNT_SIMILAR_DS_NODUPS']) && ($graph_item['data_source_name'] == $gi_check['data_source_name'])) {
											$magic_item['COUNT_SIMILAR_DS_NODUPS'] .= ($count_similar_ds_nodups == 0 ? '' : ',') . 'TIME,' . (time() - $rra_seconds) . ",GT,1,$def_name,UN,0,1,IF,IF"; /* convert unknowns to '0' first */
										}

										$count_similar_ds_nodups++;
										$sources_seen[$gi_check['data_template_rrd_id']] = true;
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

				/* allow automatic rate calculations on raw gauge data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('CURRENT_DATA_SOURCE_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('CURRENT_DATA_SOURCE_PI', read_config_option('poller_interval'), $cdef_string);
				}

				$cdef_string = str_replace('CURRENT_DATA_SOURCE', generate_graph_def_name(strval((isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id]) ? $cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id] : '0'))), $cdef_string);

				/* allow automatic rate calculations on raw gauge data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS_PI', read_config_option('poller_interval'), $cdef_string);
				}

				/* ALL|SIMILAR_DATA_SOURCES(NO)?DUPS are to be replaced here */
				if (isset($magic_item['ALL_DATA_SOURCES_DUPS'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_DUPS', $magic_item['ALL_DATA_SOURCES_DUPS'], $cdef_string);
				}

				/* allow automatic rate calculations on raw gauge data */
				if (isset($graph_item['local_data_id'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS_PI', db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', array($graph_item['local_data_id'])), $cdef_string);
				} else {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS_PI', read_config_option('poller_interval'), $cdef_string);
				}

				if (isset($magic_item['ALL_DATA_SOURCES_NODUPS'])) {
					$cdef_string = str_replace('ALL_DATA_SOURCES_NODUPS', $magic_item['ALL_DATA_SOURCES_NODUPS'], $cdef_string);
				}

				/* allow automatic rate calculations on raw gauge data */
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
					$cdef_string = str_replace('SIMILAR_DATA_SOURCES_NODUPS_PI', read_config_option('poller_interval'), $cdef_string);
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

				if ((strpos($cdef_string, '|query_ifHighSpeed|') !== false) ||
					(strpos($cdef_string, '|query_ifSpeed|') !== false)) {
					$local_data = db_fetch_row_prepared('SELECT *
						FROM data_local
						WHERE id = ?',
						array($graph_item['local_data_id']));

					$speed = rrdtool_function_interface_speed($local_data);

					$cdef_string = str_replace(array('|query_ifHighSpeed|','|query_ifSpeed|'), array($speed, $speed), $cdef_string);
				}

				/* replace query variables in cdefs */
				$cdef_string = rrd_substitute_host_query_data($cdef_string, $graph, $graph_item);

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

			/* +++++++++++++++++++++++ GRAPH ITEMS: CDEFs END   +++++++++++++++++++++++ */

			/* +++++++++++++++++++++++ GRAPH ITEMS: VDEFs START +++++++++++++++++++++++ */

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

				/* the VDEF cache is so we do not create duplicate VDEFs on a graph,
				* but take info account, that same VDEF may use different CDEFs
				* so index over VDEF_ID, CDEF_ID per DATA_TEMPLATE_RRD_ID, lvm */
				$vdef_cache[$graph_item['vdef_id']][$graph_item['cdef_id']][$graph_item['data_template_rrd_id']][$cf_id] = $i;
			}

			/* add the cdef string to the end of the def string */
			$graph_defs .= $vdef_graph_defs;

			/* +++++++++++++++++++++++ GRAPH ITEMS: VDEFs END +++++++++++++++++++++++ */

			/* note the current item_id for easy access */
			$graph_item_id = $graph_item['graph_templates_item_id'];

			/* we put this in a variable so it can be manipulated before mainly used
			if we want to skip it, like below */
			$current_graph_item_type = $graph_item_types[$graph_item['graph_type_id']];

			/* IF this graph item has a data source... get a DEF name for it, or the cdef if that applies
			to this graph item */
			if ($graph_item['cdef_id'] == '0') {
				if (isset($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id])) {
					$data_source_name = generate_graph_def_name(strval($cf_ds_cache[$graph_item['data_template_rrd_id']][$cf_id]));
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
			in an RRDtool-friendly fashion */

			$need_rrd_nl = true;

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
						$comments = array();

						$comment_arg = rrd_substitute_host_query_data($graph_variables['text_format'][$graph_item_id], $graph, $graph_item);

						// Check for a wrapping comment
						$max = read_config_option('max_title_length') - 20;
						if (strlen($comment_arg) > $max) {
							$comments = explode("\n", wordwrap($comment_arg, $max));
						}else{
							$comments[] = $comment_arg;
						}

						foreach($comments as $comment) {
							# next, compute the argument of the COMMENT statement and perform injection counter measures
							if (trim($comment) == '') { # an empty COMMENT must be treated with care
								$comment = cacti_escapeshellarg(' ' . $hardreturn[$graph_item_id]);
							} else {
								$comment = cacti_escapeshellarg(rrdtool_escape_string(html_escape($comment)) . $hardreturn[$graph_item_id]);
							}

							# create rrdtool specific command line
							$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $comment . ' ';
						}
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
					$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id]), false);

					if ($graph_item['vdef_id'] == '0') {
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . ':' . $consolidation_functions[$graph_item['consolidation_function_id']] . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
					} else {
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id]));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':AVERAGE:' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_LAST:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id]));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':LAST:' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_MAX:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id]));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':MAX:' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_GPRINT_MIN:
					if (!isset($graph_data_array['graph_nolegend'])) {
						$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id]));

						if ($graph_item['vdef_id'] == '0') {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':MIN:' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						} else {
							$txt_graph_items .= 'GPRINT:' . $data_source_name . ':' . cacti_escapeshellarg($text_format . $graph_item['gprint_text'] . $hardreturn[$graph_item_id]) . ' ';
						}
					}

					break;
				case GRAPH_ITEM_TYPE_AREA:
					$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):''));

					if (read_config_option('enable_rrdtool_gradient_support') == 'on') {
						/* End color is a 40% (0.4) darkened (negative number) version of the original color */
						$end_color        = colourBrightness( "#" . $graph_item[ "hex" ], -0.4 );
						$txt_graph_items .= gradient($data_source_name, $graph_item_color_code, $end_color . $graph_item['alpha'], cacti_escapeshellarg($graph_variables['text_format'][$graph_item_id] . $hardreturn[$graph_item_id]), 20, false, $graph_item[ "alpha" ]);
					} else {
						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($text_format . $hardreturn[$graph_item_id]) . ' ';
					}

					if ($graph_item['shift'] == CHECKED && abs($graph_item['value']) > 0) {
						/* create a SHIFT statement */
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_STACK:
					$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id],$pad_number):''));

					$txt_graph_items .= 'AREA:' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($text_format . $hardreturn[$graph_item_id]) . ':STACK';

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_LINE1:
				case GRAPH_ITEM_TYPE_LINE2:
				case GRAPH_ITEM_TYPE_LINE3:
					$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):''));

					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($text_format . $hardreturn[$graph_item_id]) . $dash;

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_LINESTACK:
					$text_format = rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id] != '' ? str_pad($graph_variables['text_format'][$graph_item_id], $pad_number):''));

					$txt_graph_items .= 'LINE' . $graph_item['line_width'] . ':' . $data_source_name . $graph_item_color_code . ':' . cacti_escapeshellarg($text_format . $hardreturn[$graph_item_id]) . ':STACK' . $dash;

					if ($graph_item['shift'] == CHECKED && $graph_item['value'] > 0) {      # create a SHIFT statement
						$txt_graph_items .= RRD_NL . 'SHIFT:' . $data_source_name . ':' . $graph_item['value'];
					}

					break;
				case GRAPH_ITEM_TYPE_TIC:
					$_fraction = (empty($graph_item['graph_type_id']) ? '' : (':' . $graph_item['value']));
					$_legend   = ':' . cacti_escapeshellarg(rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id])) . $hardreturn[$graph_item_id]);
					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $data_source_name . $graph_item_color_code . $_fraction . $_legend;

					break;
				case GRAPH_ITEM_TYPE_HRULE:
					/* perform variable substitution; if this does not return a number, rrdtool will FAIL! */
					$substitute = strip_alpha(rrd_substitute_host_query_data($graph_variables['value'][$graph_item_id], $graph, $graph_item));

					$text_format = rrdtool_escape_string(html_escape(rrd_substitute_host_query_data($graph_variables['text_format'][$graph_item_id], $graph, $graph_item)));

					/* don't break rrdtool if the strip_alpha() returns false */
					if ($substitute !== false) {
						$graph_variables['value'][$graph_item_id] = $substitute;
					} else {
						$graph_variables['value'][$graph_item_id] = '0';
					}

					$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $graph_variables['value'][$graph_item_id] . $graph_item_color_code . ':' . cacti_escapeshellarg($text_format . $hardreturn[$graph_item_id]) . '' . $dash;

					break;
				case GRAPH_ITEM_TYPE_VRULE:
					if (substr_count($graph_item['value'], ':')) {
						$value_array = explode(':', $graph_item['value']);

						if ($value_array[0] < 0) {
							$value = date('U') - (-3600 * $value_array[0]) - 60 * $value_array[1];
						} else {
							$value = date('U', mktime($value_array[0],$value_array[1],0));
						}

						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $value . $graph_item_color_code . ':' . cacti_escapeshellarg(rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id])) . $hardreturn[$graph_item_id]) . $dash;
					} elseif (is_numeric($graph_item['value'])) {
						$value = $graph_item['value'];

						$txt_graph_items .= $graph_item_types[$graph_item['graph_type_id']] . ':' . $value . $graph_item_color_code . ':' . cacti_escapeshellarg(rrdtool_escape_string(html_escape($graph_variables['text_format'][$graph_item_id])) . $hardreturn[$graph_item_id]) . $dash;
					}

					break;
				default:
					$need_rrd_nl = false;
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
					$need_rrd_nl = false;
				}
			}

			$i++;

			if (($i < cacti_sizeof($graph_items)) && ($need_rrd_nl)) {
				$txt_graph_items .= RRD_NL;
			}
		}
	}

	if (!isset($graph_data_array['export_csv']) || $graph_data_array['export_csv'] != true) {
		$graph_array = api_plugin_hook_function('rrd_graph_graph_options', array('graph_opts' => $graph_opts, 'graph_defs' => $graph_defs, 'txt_graph_items' => $txt_graph_items, 'graph_id' => $local_graph_id, 'start' => $graph_start, 'end' => $graph_end));

        $graph_array = add_business_hours($graph_array);

		if (!empty($graph_array)) {
			$graph_defs = $graph_array['graph_defs'];
			$txt_graph_items = $graph_array['txt_graph_items'];
			$graph_opts = $graph_array['graph_opts'];
		}

		/* either print out the source or pass the source onto rrdtool to get us a nice PNG */
		if (isset($graph_data_array['print_source'])) {
			$source_command_line = read_config_option('path_rrdtool') . ' graph ' . $graph_opts . $graph_defs . $txt_graph_items;
			$source_command_line_lengths = strlen(str_replace("\\\n", ' ', $source_command_line));
			print '<PRE>' . html_escape($source_command_line) . '</PRE>';
			print '<span class="textInfo">' . 'RRDtool Command lengths = ' . $source_command_line_lengths . ' characters.</span><br>';
			if ( $config['cacti_server_os'] == 'win32' && $source_command_line_lengths > 8191 ) {
				print '<PRE>' . 'Warning: The Cacti OS is Windows system, RRDtool Command lengths should not exceed 8191 characters.' . '</PRE>';
			}
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

function rrdtool_escape_string($text, $ignore_percent = true) {
	if ($ignore_percent) {
		return str_replace(array('"', ':'), array('\"', '\:'), $text);
	} else {
		return str_replace(array('"', ':', '%'), array('\"', '\:', '%%'), $text);
	}
}

function rrdtool_function_xport($local_graph_id, $rra_id, $xport_data_array, &$xport_meta, $user = 0) {
	return rrdtool_function_graph($local_graph_id, $rra_id, $xport_data_array, null, $xport_meta, $user);
}

function rrdtool_function_format_graph_date(&$graph_data_array) {
	global $datechar;

	$graph_legend = '';
	/* setup date format */
	$date_fmt = read_user_setting('default_date_format',read_config_option('default_date_format'));
	$dateCharSetting = read_user_setting('default_datechar',read_config_option('default_datechar'));
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
		} elseif (($graph_data_array['graph_start'] >= 0) && ($graph_data_array['graph_end'] >= 0)) {
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
	$themecolors = 'rrdcolors';
	$themeborder = 'rrdborder';


	if (isset($graph_data_array['graph_theme'])) {
		$rrdtheme = $config['base_path'] . '/include/themes/' . $graph_data_array['graph_theme'] . '/rrdtheme.php';
	} else {
		$rrdtheme = $config['base_path'] . '/include/themes/' . get_selected_theme() . '/rrdtheme.php';
	}

	if (file_exists($rrdtheme) && is_readable($rrdtheme)) {
		$rrdversion = get_rrdtool_version();
		include($rrdtheme);

		if(isset($_COOKIE['CactiColorMode']) && in_array($_COOKIE['CactiColorMode'], array('dark', 'light', 'dark-dimmed'))) {
				$themecolors = 'rrdcolors_' . $_COOKIE['CactiColorMode'];
				$themeborder = 'rrdborder_' . $_COOKIE['CactiColorMode'];
				if (!isset($$themecolors) || !is_array($$themecolors)) {
					$themecolors = 'rrdcolors';
					$themeborder = 'rrdborder';
				}
		}

		if (isset($$themecolors) && is_array($$themecolors)) {
			foreach($$themecolors as $colortag => $color) {
				$graph_opts .= '--color ' . strtoupper($colortag) . '#' . strtoupper($color) . RRD_NL;
			}
		}

		if (isset($$themeborder) && cacti_version_compare($rrdversion,'1.4','>=')) {
                        $graph_opts .= "--border " . $$themeborder . RRD_NL;
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
	if (isset($rrdversion) && cacti_version_compare($rrdversion,'1.3','>')) {
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

	if ($font != '') {
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
	} elseif (($size <= 4) || !is_numeric($size)) {
		$size = 8;
	}

	return '--font ' . strtoupper($type) . ':' . floatval($size) . ':' . $font . RRD_NL;
}

function rrd_substitute_host_query_data($txt_graph_item, $graph, $graph_item) {
	/* replace host variables in graph elements */
	$host_id = 0;

	if (!preg_match('/(\|query_|\|host_|\|input_)/', $txt_graph_item)) {
		return $txt_graph_item;
	}

	if (empty($graph['host_id'])) {
		/* if graph has no associated host determine host_id from graph item data source */
		if (isset($graph_item['local_data_id']) && !empty($graph_item['local_data_id'])) {
			$host_id = db_fetch_cell_prepared('SELECT host_id
				FROM data_local
				WHERE id = ?',
				array($graph_item['local_data_id']));
		}
	} else {
		$host_id = $graph['host_id'];
	}

	$txt_graph_item = substitute_host_data($txt_graph_item, '|', '|', $host_id);

	/* replace query variables in graph elements */
	if (strpos($txt_graph_item, '|query_') !== false){
		if(isset($graph_item['snmp_query_id'])) {
			$txt_graph_item = substitute_snmp_query_data($txt_graph_item, $host_id, $graph_item['snmp_query_id'], $graph_item['snmp_index']);
		} else if (isset($graph['snmp_query_id'])) {
			$txt_graph_item = substitute_snmp_query_data($txt_graph_item, $host_id, $graph['snmp_query_id'], $graph['snmp_index']);
		}
	}

	/* replace query variables in graph elements */
	if (strpos($txt_graph_item, '|input_') !== false && isset($graph_item['local_data_id'])) {
		return substitute_data_input_data($txt_graph_item, $graph, $graph_item['local_data_id']);
	} else {
		return $txt_graph_item;
	}
}

function rrdtool_function_get_resstep($local_data_ids, $graph_start, $graph_end, $type = 'res') {
	if (!is_array($local_data_ids)) {
		$local_data_ids = array($local_data_ids);
	}

	$time = time();

	if ($graph_start < 0) {
		$graph_start = $time + $graph_start;
	}

	if ($graph_end < 0) {
		$graph_end = $time + $graph_end;
	}

	if (cacti_sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			$data_source_info = db_fetch_assoc_prepared('SELECT dsp.step, dspr.steps, dspr.rows, dspr.timespan
				FROM data_source_profiles AS dsp
				INNER JOIN data_source_profiles_rra AS dspr
				ON dspr.data_source_profile_id=dsp.id
				INNER JOIN data_template_data AS dtd
				ON dtd.data_source_profile_id=dsp.id
				WHERE dtd.local_data_id = ?
				ORDER BY step, steps ASC',
				array($local_data_id));

			if (cacti_sizeof($data_source_info)) {
				foreach($data_source_info as $resolution) {
					if ($graph_start > ($time - ($resolution['step'] * $resolution['steps'] * $resolution['rows']))) {
						if ($type == 'res') {
							return $resolution['step'] * $resolution['steps'];
						} else {
							return $resolution['step'];
						}
					}
				}
			}
		}
	}

	return 0;
}

/**
 * rrdtool_function_info - given a data source id, return rrdtool info array
 *
 * @param  (int)   $local_data_id - data source id
 *
 * @return (array) an array containing all data from rrdtool info command
 */
function rrdtool_function_info($local_data_id) {
	/* Get the path to rrdtool file */
	$data_source_path = get_data_source_path($local_data_id, true);

	/* Execute rrdtool info command */
	$cmd_line = ' info ' . $data_source_path;
	$output = rrdtool_execute($cmd_line, RRDTOOL_OUTPUT_NULL, RRDTOOL_OUTPUT_STDOUT);
	if ($output == '') {
		return false;
	}

	/* Hack for i18n */
	if (strpos($output, ',') !== false) {
		$output = str_replace(',', '.', $output);
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

/**
 * rrdtool_function_contains_cf  verifies if the RRDfile contains the 'MAX' consolidation function
 *
 * @param  (int)  $local_data_id - the id of the data source
 * @param  (int)  $cf - the consolidation function to search for
 *
 * @return (bool) true or false depending on the result
 */
function rrdtool_function_contains_cf($local_data_id, $cf) {
	$info = rrdtool_function_info($local_data_id);

	if (cacti_sizeof($info)) {
		if (isset($info['rra'])) {
			foreach($info['rra'] as $ds) {
				if ($ds['cf'] == $cf) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * rrdtool_cacti_compare - compares cacti information to rrd file information
 *
 * @param $data_source_idi  the id of the data source
 * @param $info				rrdtool info as an array
 *
 * @return					array build like $info defining html class in case of error
 */
function rrdtool_cacti_compare($data_source_id, &$info) {
	global $data_source_types, $consolidation_functions;

	/* get cacti header information for given data source id */
	$cacti_header_array = db_fetch_row_prepared('SELECT
		local_data_template_data_id, rrd_step, data_source_profile_id
		FROM data_template_data
		WHERE local_data_id = ?',
		array($data_source_id));

	/* get cacti DS information */
	$cacti_ds_array = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.data_source_type_id,
		dtr.rrd_heartbeat, dtr.rrd_maximum, dtr.rrd_minimum
		FROM data_template_rrd AS dtr
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		WHERE local_data_id = ?',
		array($data_source_id));

	/* get cacti RRA information */
	$cacti_rra_array = db_fetch_assoc_prepared('SELECT
		dspc.consolidation_function_id AS cf,
		dsp.x_files_factor AS xff,
		dsp.heartbeat,
		dspr.steps AS steps,
		dspr.rows AS `rows`
		FROM data_source_profiles AS dsp
		INNER JOIN data_source_profiles_cf AS dspc
		ON dsp.id=dspc.data_source_profile_id
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
		WHERE dsp.id = ?
		ORDER BY dspc.consolidation_function_id, dspr.steps',
		array($cacti_header_array['data_source_profile_id']));

	$profile_heartbeat = db_fetch_cell_prepared('SELECT heartbeat
		FROM data_source_profiles
		WHERE id = ?',
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
	if (cacti_sizeof($cacti_ds_array)) {
		$data_local = db_fetch_row_prepared('SELECT host_id,
			snmp_query_id, snmp_index
			FROM data_local
			WHERE id = ?',
			array($data_source_id)
		);

		$speed = rrdtool_function_interface_speed($data_local);

		foreach ($cacti_ds_array as $key => $data_source) {
			/**
			 * Accomodate a Cacti bug where the heartbeat was not
			 * propagated.
			 */
			if ($data_source['rrd_heartbeat'] != $profile_heartbeat) {
				cacti_log(sprintf('NOTE: Incorrect Data Source heartbeat found and corrected for Local Data ID %s and Data Source \'%s\'', $data_source_id, $data_source['data_source_name']), false, 'DSDEBUG');

				db_execute_prepared('UPDATE data_template_rrd
					SET rrd_heartbeat = ?
					WHERE id = ?',
					array($profile_heartbeat, $data_source['id']));

				$data_source['rrd_heartbeat'] = $profile_heartbeat;
			}

			$ds_name = $data_source['data_source_name'];

			/* try to print matching rrd file's ds information */
			if (isset($info['ds'][$ds_name]) ) {
				if (!isset($info['ds'][$ds_name]['seen'])) {
					$info['ds'][$ds_name]['seen'] = true;
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
					if (($data_source['rrd_minimum'] == '0' || $data_source['rrd_maximum'] == 'U') &&
						$info['ds'][$ds_name]['min'] == 'NaN') {
						$info['ds'][$ds_name]['min'] = 'U';
					}
				}

				if ($data_source['rrd_minimum'] != $info['ds'][$ds_name]['min']) {
					$diff['ds'][$ds_name]['min'] = __("RRD minimum for Data Source '%s' should be '%s'", $ds_name, $data_source['rrd_minimum']);
					$diff['tune'][] = $info['filename'] . ' ' . '--minimum ' . $ds_name . ':' . $data_source['rrd_minimum'];
				}

				// Trim the max value
				$data_source['rrd_maximum'] = trim($data_source['rrd_maximum']);

				if ($data_source['rrd_maximum'] != $info['ds'][$ds_name]['max']) {
					if ($data_source['rrd_maximum'] == '|query_ifSpeed|' ||
						$data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
						$data_source['rrd_maximum'] = $speed;
					} elseif (($data_source['rrd_maximum'] == '0' || $data_source['rrd_maximum'] == 'U') &&
						$info['ds'][$ds_name]['max'] == 'NaN') {
						$info['ds'][$ds_name]['max'] = 'U';
					} else {
						$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'], $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
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
				$info['ds'][$ds_name]['seen'] = true;
				$diff['ds'][$ds_name]['error'] = __("DS '%s' missing in RRDfile", $ds_name);
			}
		}
	}

	/* print all data sources still known to the rrd file (no match to cacti ds will happen here) */
	if (cacti_sizeof($info['ds']) > 0) {
		foreach ($info['ds'] as $ds_name => $data_source) {
			if (!isset($data_source['seen'])) {
				$diff['ds'][$ds_name]['error'] = __("DS '%s' missing in Cacti definition", $ds_name);
			}
		}
	}

	/* -----------------------------------------------------------------------------------
	 * RRA information
	 -----------------------------------------------------------------------------------*/
	$resize = true;		# assume a resize operation as long as no rra duplicates are found

	/* scan cacti rra information for duplicates of (CF, STEPS) */
	if (cacti_sizeof($cacti_rra_array) > 0) {
		for ($i=0; $i<= cacti_sizeof($cacti_rra_array)-1; $i++) {
			$cf = $cacti_rra_array[$i]['cf'];
			$steps = $cacti_rra_array[$i]['steps'];
			foreach($cacti_rra_array as $cacti_rra_id => $cacti_rra) {
				if ($cf == $cacti_rra['cf'] && $steps == $cacti_rra['steps'] && ($i != $cacti_rra_id)) {
					$diff['rra'][$i]['error'] = __("Cacti RRA '%s' has same CF/steps (%s, %s) as '%s'", $i, $consolidation_functions[$cf], $steps, $cacti_rra_id);
					$diff['rra'][$cacti_rra_id]['error'] = __("Cacti RRA '%s' has same CF/steps (%s, %s) as '%s'", $cacti_rra_id, $consolidation_functions[$cf], $steps, $i);
					$resize = false;
				}
			}
		}
	}

	/* scan file rra information for duplicates of (CF, PDP_PER_ROWS) */
	if (cacti_sizeof($info['rra']) > 0) {
		for ($i=0; $i<= cacti_sizeof($info['rra'])-1; $i++) {
			$cf = $info['rra'][$i]['cf'];
			$steps = $info['rra'][$i]['pdp_per_row'];
			foreach($info['rra'] as $file_rra_id => $file_rra) {
				if (($cf == $file_rra['cf']) && ($steps == $file_rra['pdp_per_row']) && ($i != $file_rra_id)) {
					$diff['rra'][$i]['error'] = __("File RRA '%s' has same CF/steps (%s, %s) as '%s'", $i, $cf, $steps, $file_rra_id);
					$diff['rra'][$file_rra_id]['error'] = __("File RRA '%s' has same CF/steps (%s, %s) as '%s'", $file_rra_id, $cf, $steps, $i);
					$resize = false;
				}
			}
		}
	}

	/* print all RRAs known to cacti and add those from matching rrd file */
	if (cacti_sizeof($cacti_rra_array) > 0) {
		foreach($cacti_rra_array as $cacti_rra_id => $cacti_rra) {
			/* find matching rra info from rrd file
			 * do NOT assume, that rra sequence is kept ($cacti_rra_id != $file_rra_id may happen)!
			 * Match is assumed, if CF and STEPS/PDP_PER_ROW match; so go for it */
			foreach ($info['rra'] as $file_rra_id => $file_rra) {
				/* in case of mismatch, $file_rra['pdp_per_row'] might not be defined */
				if (!isset($file_rra['pdp_per_row'])) {
					$file_rra['pdp_per_row'] = 0;
				}

				if ($consolidation_functions[$cacti_rra['cf']] == trim($file_rra['cf'], '"') &&
					$cacti_rra['steps'] == $file_rra['pdp_per_row']) {

					if (isset($info['rra'][$file_rra_id]['seen'])) {
						continue;
					}

					# mark both rra id's as seen to avoid printing them as non-matching
					$info['rra'][$file_rra_id]['seen'] = true;
					$cacti_rra_array[$cacti_rra_id]['seen'] = true;

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
				$info['rra']['cacti_' . $cacti_rra_id]['cf']    = $consolidation_functions[$cacti_rra['cf']];
				$info['rra']['cacti_' . $cacti_rra_id]['steps'] = $cacti_rra['steps'];
				$info['rra']['cacti_' . $cacti_rra_id]['xff']   = $cacti_rra['xff'];
				$info['rra']['cacti_' . $cacti_rra_id]['rows']  = $cacti_rra['rows'];
				$diff['rra']['cacti_' . $cacti_rra_id]['error'] = __("RRA '%s' missing in RRDfile", $cacti_rra_id);
			}
		}
	}

	# if the rrd file has an rra that has no cacti match, consider this as an error
	if (cacti_sizeof($info['rra']) > 0) {
		foreach ($info['rra'] as $file_rra_id => $file_rra) {
			if (!isset($info['rra'][$file_rra_id]['seen'])) {
				$diff['rra'][$file_rra_id]['error'] = __("RRA '%s' missing in Cacti definition", $file_rra_id);
			}
		}
	}

	return $diff;

}

/**
 * rrdtool_info2html - take output from rrdtool info array and build html table
 *
 * @param  (array) $info_array - array of rrdtool info data
 * @param  (array) $diff - array of differences between definition and current rrd file settings
 *
 * @return (string) - html code
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
		'filename'    => $info_array['filename'],
		'rrd_version' => $info_array['rrd_version'],
		'step'        => $info_array['step'],
		'last_update' => $info_array['last_update']);

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

	if (cacti_sizeof($info_array['ds'])) {
		foreach ($info_array['ds'] as $key => $value) {
			form_alternate_row('line' . $key, true);

			form_selectable_cell($key, 'name', '', (isset($diff['ds'][$key]['error']) ? 'color:red' : ''));
			form_selectable_cell((isset($value['type']) ? $value['type'] : ''), 'type', '', (isset($diff['ds'][$key]['type']) ? 'color:red' : ''));
			form_selectable_cell((isset($value['minimal_heartbeat']) ? $value['minimal_heartbeat'] : ''), 'minimal_heartbeat', '', (isset($diff['ds'][$key]['minimal_heartbeat']) ? 'color:red, text-align:right' : 'text-align:right'));

			if (isset($value['min'])) {
				if ($value['min'] == 'U') {
					form_selectable_cell($value['min'], 'min', '', 'right');
				} elseif (is_numeric($value['min'])) {
					form_selectable_cell(number_format_i18n($value['min']), 'min', '', 'right');
				} else {
					form_selectable_cell($value['min'], 'min', '', 'color:red;text-align:right');
				}
			} else {
				form_selectable_cell(__('Unknown'), 'min', '', 'color:red;text-align:right');
			}

			if (isset($value['max'])) {
				if ($value['max'] == 'U') {
					form_selectable_cell($value['max'], 'max', '', 'right');
				} elseif (is_numeric($value['max'])) {
					form_selectable_cell(number_format_i18n($value['max']), 'max', '', 'right');
				} else {
					form_selectable_cell($value['max'], 'max', '', 'color:red;text-align:right');
				}
			} else {
				form_selectable_cell(__('Unknown'), 'max', '', 'color:red;text-align:right');
			}

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
		array('display' => __('X-Files Factor'),              'align' => 'right'),
		array('display' => __('CDP Prep Value (0)'),          'align' => 'right'),
		array('display' => __('CDP Unknown Data points (0)'), 'align' => 'right')
	);

	html_start_box('', '100%', '', '3', 'center', '');

	html_header($header_items, 1);

	if (cacti_sizeof($info_array['rra'])) {
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

/**
 * rrdtool_tune - create rrdtool tune/resize commands html+cli enabled
 *
 * @param (string) $rrd_file - rrd file name
 * @param (array)  $diff - array of discrepancies between cacti settings and rrd file info
 * @param (bool)   $show_source - only show text+commands or execute all commands, execute is for cli mode only!
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
	if (CACTI_CLI) {
		$nl = "\n";
	} else {
		$nl = '<br/>';
	}

	if ($show_source && cacti_sizeof($diff)) {
		# print error descriptions
		print_leaves($diff, $nl);
	}

	if (isset($diff['tune']) && cacti_sizeof($diff['tune'])) {
		# create tune commands
		foreach ($diff['tune'] as $line) {
			if ($show_source == true) {
				print read_config_option('path_rrdtool') . ' tune ' . $line . $nl;
			} else {
				rrdtool_execute("tune $line", true, RRDTOOL_OUTPUT_STDOUT);
			}
		}
	}

	if (isset($diff['resize']) && cacti_sizeof($diff['resize'])) {
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

/**
 * rrd_check - Given a data source id, check the rrdtool file to the data source definition
 *
 * @param  (int) $data_source_id - data source id
 *
 * @return (array) an array containing issues with the rrdtool file definition vs data source
 */
function rrd_check($data_source_id) {
	global $rrd_tune_array, $data_source_types;

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types[$rrd_tune_array['data-source-type']];
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);
}

/**
 * rrd_repair - Given a data source id, update the rrdtool file to match the data source definition
 *
 * @param  (int) $data_source_id - data source id
 *
 * @return (int) 1 success, 2 false
 */
function rrd_repair($data_source_id) {
	global $rrd_tune_array, $data_source_types;

	$data_source_name = get_data_source_item_name($rrd_tune_array['data_source_id']);
	$data_source_type = $data_source_types[$rrd_tune_array['data-source-type']];
	$data_source_path = get_data_source_path($rrd_tune_array['data_source_id'], true);
}

/**
 * rrd_datasource_add - add a (list of) datasource(s) to an (array of) rrd file(s)
 *
 * @param  (array) $file_array - array of rrd files
 * @param  (array) $ds_array   - array of datasource parameters
 * @param  (bool) $debug       - debug mode
 *
 * @return (mixed) - success (bool) or error message (array)
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
		 * version 0001 => RRDtool 1.0.x
		 * version 0003 => RRDtool 1.2.x, 1.3.x, 1.4.x, 1.5.x, 1.6.x
		 */
		$version = trim($dom->getElementsByTagName('version')->item(0)->nodeValue);

		/* now start XML processing */
		foreach ($ds_array as $ds) {
			/* first, append the <DS> structure in the rrd header */
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
					cacti_log('Added Data Source(s) to RRDfile: ' . $file, false, 'UTIL');
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

/**
 * rrd_rra_delete - delete a (list of) rra(s) from an (array of) rrd file(s)
 *
 * @param  (array) $file_array - array of rrd files
 * @param  (array) $rra_array  - array of rra parameters
 * @param  (bool) $debug       - debug mode
 *
 * @return (mixed) true for success (bool) or error message (array)
 */
function rrd_rra_delete($file_array, $rra_array, $debug) {
	$rrdtool_pipe = rrd_init();

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
					cacti_log('Deleted RRA(s) from RRDfile: ' . $file, false, 'UTIL');
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

/**
 * rrd_rra_clone - clone a (list of) rra(s) from an (array of) rrd file(s)
 *
 * @param  (array)  $file_array - array of rrd files
 * @param  (string) $cf         - new consolidation function
 * @param  (array)  $rra_array  - array of rra parameters
 * @param  (bool)   $debug      - debug mode
 *
 * @return (mixed)  success (bool) or error message (array)
 */
function rrd_rra_clone($file_array, $cf, $rra_array, $debug) {
	$rrdtool_pipe = rrd_init();

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
					cacti_log('Deleted RRA(s) from RRDfile: ' . $file, false, 'UTIL');
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

/**
 * rrd_append_ds - appends a <DS> subtree to an RRD XML structure
 *
 * @param  (object) $dom     - the DOM object, where the RRD XML is stored
 * @param  (string) $version - rrd file version
 * @param  (string) $name    - name of the new ds
 * @param  (string) $type    - type of the new ds
 * @param  (int)    $min_hb  - heartbeat of the new ds
 * @param  (string) $min     - min value of the new ds or [NaN|U]
 * @param  (string) $max     - max value of the new ds or [NaN|U]
 *
 * @return (object) - modified DOM
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

/**
 * rrd_append_compute_ds - COMPUTE DS: appends a <DS> subtree to an RRD XML structure
 *
 * @param  (object) $dom     - the DOM object, where the RRD XML is stored
 * @param  (string) $version - rrd file version
 * @param  (string) $name    - name of the new ds
 * @param  (string) $type    - type of the new ds
 * @param  (int)    $cdef    - the cdef rpn used for COMPUTE
 *
 * @return (object) - modified DOM
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

/**
 * rrd_append_cdp_prep_ds - append a <DS> subtree to the <CDP_PREP> subtrees of a RRD XML structure
 *
 * @param (object) $dom     - the DOM object, where the RRD XML is stored
 * @param (string) $version - rrd file version
 *
 * @return (object) - the modified DOM object
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
	/* clone the source ds entry to preserve RRDtool notation */
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

/**
 * rrd_append_value - append a <V>alue element to the <DATABASE> subtrees of a RRD XML structure
 *
 * @param  (object) $dom - the DOM object, where the RRD XML is stored
 *
 * @return (object) - the modified DOM object
 */
function rrd_append_value($dom) {
	/* get XPATH notation required for positioning */
	#$xpath = new DOMXPath($dom);

	/* get all <cdp_prep><ds> entries */
	#$itemList = $xpath->query('/rrd/rra/database/row');
	$itemList = $dom->getElementsByTagName('row');

	/* create <V> entry to preserve RRDtool notation */
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

/**
 * rrd_delete_rra - delete an <RRA> subtree from the <RRD> XML structure
 *
 * @param  (object) $dom     - the DOM document, where the RRD XML is stored
 * @param  (array) $rra_parm - a single rra parameter set, given by the user
 *
 * @return (object) - the modified DOM object
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
			print(__("RRA (CF=%s, ROWS=%d, PDP_PER_ROW=%d, XFF=%1.2f) removed from RRD file", $cf, $rows, $pdp_per_row, $xff)) . PHP_EOL;
			/* we need the parentNode for removal operation */
			$parent = $rra->parentNode;
			$parent->removeChild($rra);
			break; /* do NOT accidentally remove more than one element, else loop back to forth */
		}
	}
	return $dom;
}

/**
 * rrd_copy_rra - clone an <RRA> subtree of the <RRD> XML structure, replacing cf
 *
 * @param  (object) $dom     - the DOM document, where the RRD XML is stored
 * @param  (string) $cf      - new consolidation function
 * @param  (array) $rra_parm - a single rra parameter set, given by the user
 *
 * @return (object) - the modified DOM object
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
			print(__("RRA (CF=%s, ROWS=%d, PDP_PER_ROW=%d, XFF=%1.2f) adding to RRD file", $cf, $_rows, $_pdp_per_row, $_xff)) . PHP_EOL;
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

function rrdtool_parse_error($string) {
	global $config;

	if (preg_match('/ERROR. opening \'(.*)\': (No such|Permiss).*/', $string, $matches)) {
		if (cacti_sizeof($matches) >= 2) {
			$filename = $matches[1];
			$rra_name = basename($filename);
			$rra_path = dirname($filename) . "/";
			if (!is_resource_writable($rra_path)) {
				$message = __('Website does not have write access to %s, may be unable to create/update RRDs', 'folder');
				$rra_name = str_replace($config['base_path'],'', $rra_path);
				$rra_path = "";
			} else {
				if (stripos($filename, $config['base_path']) >= 0) {
					$rra_file = str_replace($config['base_path'] . '/rra/', '', $filename);
					$rra_name = basename($rra_file);
					$rra_path = dirname($rra_file);
				} else {
					$rra_name = basename($filename);
					$rra_path = __('(Custom)');
				}

				if (!is_resource_writable($filename)) {
					$message = __('Website does not have write access to %s, may be unable to create/update RRDs', 'data file');
				} else {
					$message = __('Failed to open data file, poller may not have run yet');
				}

				$rra_path = '(' . __('RRA Folder') . ': ' . ((empty($rra_path) || $rra_path == ".") ? __('Root') : $rra_path) . ')';
			}

			$string = $message . ":\n\0x27\n" . $rra_name;
			if (!empty($rra_path)) {
				$string .= "\n" . $rra_path;
			}
		}
	}

	return $string;
}

function rrdtool_create_error_image($string, $width = '', $height = '') {
	global $config, $dejavu_paths;

	$string = rrdtool_parse_error($string);

	/* put image in buffer */
	ob_start();

	$image_data  = false;
	$font_color  = '000000';
	$font_size   = 8;
	$back_color  = 'F3F3F3';
	$shadea      = 'CBCBCB';
	$shadeb      = '999999';

	if ($config['cacti_server_os'] == 'unix') {
		foreach ($dejavu_paths as $dejavupath) {
			if (file_exists($dejavupath . '/DejaVuSans.ttf')) {
				$font_file = $dejavupath . '/DejaVuSans.ttf';
				break;
			}
		}
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
	$maxstring = ceil((450 - (125 + 10)) / ($font_size / 0.9));
	$stringlen = strlen($string) * $font_size;
	$padding   = 5;

	if ($stringlen > $maxstring) {
		$cstring = wordwrap($string, $maxstring, "\n", true);
		$strings = explode("\n", $cstring);
		$strings = array_reverse($strings);
		$lines   = cacti_sizeof($strings);
	} elseif (strlen(trim($string)) == 0) {
		$strings = array(__('Unknown RRDtool Error'));
		$lines   = 1;
	} else {
		$strings = array($string);
		$lines   = 1;
	}

	/* setup the text position, image is 450x200, we start at 125 pixels from the left */
	$xpos  = 125;
	$texth = ($lines * $font_size + (($lines - 1) * $padding));
	$ypos  = round((200 / 2) + ($texth / 2),0);

	/* set the font of the image */
	if (isset($font_file) && file_exists($font_file) && is_readable($font_file) && function_exists('imagettftext')) {
		foreach($strings as $string) {
			if (trim($string) != '') {
				if (@imagettftext($image, $font_size, 0, $xpos, $ypos, $text_color, $font_file, $string) === false) {
					cacti_log('TTF text overlay failed');
				}
				$ypos -= ($font_size + $padding);
			}
		}
	} else {
		foreach($strings as $string) {
			if (trim($string) != '') {
				if (@imagestring($image, $font_size, $xpos, $ypos, $string, $text_color) === false) {
					cacti_log('Text overlay failed');
				}
				$ypos -= ($font_size + $padding);
			}
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

/**
 * gradient - Add gradient support for AREA type charts. This function adds several CDEF with different shading
 *
 * @param  (bool)   $vname       - the data source name
 * @param  (string) $start_color - the start color for the gradient
 * @param  (string) $end_color   - the end color for the gradient
 * @param  (bool)   $label       - any label attached to it
 * @param  (string) $steps       - defaults to 20
 * @param  (bool)   $lower       - defaults to faulse
 * @param  (string) $alpha       - Alpha channel to be used
 *
 * @return (string) - the additional CDEF/AREA command lines for rrdtool
 *
 * License: GPLv2
 * Original Code: https://github.com/lingej/pnp4nagios/blob/master/share/pnp/application/helpers/rrd.php
 */
function gradient($vname=FALSE, $start_color='#0000a0', $end_color='#f0f0f0', $label=FALSE, $steps=20, $lower=FALSE, $alpha='FF'){
	$label = preg_replace("/'/", "", $label);
	$label = preg_replace("/:/", "\:", $label);

	if (preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i',$start_color,$matches)) {
		$r1=hexdec($matches[1]);
		$g1=hexdec($matches[2]);
		$b1=hexdec($matches[3]);
	}

	if (preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i',$end_color,$matches)) {
		$r2=hexdec($matches[1]);
		$g2=hexdec($matches[2]);
		$b2=hexdec($matches[3]);
	}

	$diff_r=$r2-$r1;
	$diff_g=$g2-$g1;
	$diff_b=$b2-$b1;
	$spline =  "";
	$spline_vname = "var".substr(sha1(rand()),1,4);
	$vnamet = $vname.substr(sha1(rand()),1,4);

	if(preg_match('/^([0-9]{1,3})%$/', $lower, $matches)) {
		$lower   = $matches[1];
		$spline .= sprintf("CDEF:%sminimum=%s,100,/,%d,* ".RRD_NL, $vnamet, $vname, $lower);
	} elseif (preg_match('/^([0-9]+)$/', $lower, $matches)) {
		$lower   = $matches[1];
		$spline .= sprintf("CDEF:%sminimum=%s,%d,- ".RRD_NL, $vnamet, $vname, $lower);
	} else {
		$lower   = 0;
		$spline .= sprintf("CDEF:%sminimum=%s,%s,- ".RRD_NL, $vnamet, $vname, $vname);
	}

	for ($i=$steps; $i>0; $i--){
		$spline .=  sprintf("CDEF:%s%d=%s,%sminimum,-,%d,/,%d,*,%sminimum,+ ".RRD_NL,$spline_vname,$i,$vname,$vnamet,$steps,$i,$vnamet);
	}

	// We don't use alpha blending for the area right now
	$alpha = 'ff';

	for ($i=$steps; $i>0; $i--){
		$factor=$i / $steps;
		$r=round($r1 + $diff_r * $factor);
		$g=round($g1 + $diff_g * $factor);
		$b=round($b1 + $diff_b * $factor);

		if (($i == $steps) && ($label != false) && (strlen($label) > 2)) {
			$spline .=  sprintf("AREA:%s%d#%02X%02X%02X%s:\"%s\" ".RRD_NL, $spline_vname,$i,$r,$g,$b,$alpha,$label);
		} else {
			$spline .=  sprintf("AREA:%s%d#%02X%02X%02X%s ".RRD_NL, $spline_vname,$i,$r,$g,$b,$alpha);
		}
	}

	$spline .=  sprintf("AREA:%s%d#%02X%02X%02X%s ".RRD_NL, $spline_vname,$steps,$r2,$g2,$b2,'00',$label);

	return $spline;
}

/**
 * colourBrightness - Add colourBrightness support for the gradient charts. This function calculates the darker version of a given color
 *
 * @param  (bool)   $hex     - The hex representation of a color
 * @param  (string) $percent - the percentage to darken the given color. decimal number ( 0.4 -> 40% )
 *
 * @return (string) - the darker version of the given color
 *
 * License:			GPLv2
 * Original Code		http://www.barelyfitz.com/projects/csscolor/
 */
function colourBrightness($hex, $percent) {
 	// Work out if hash given
	$hash = '';

	if (stristr($hex,'#')) {
		$hex = str_replace('#','',$hex);
		$hash = '#';
	}

	/// HEX TO RGB
	$rgb = array(hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)));

	//// CALCULATE
	for ($i=0; $i<3; $i++) { // See if brighter or darker
		if ($percent > 0) {
			// Lighter
			$rgb[$i] = round($rgb[$i] * $percent) + round(255 * (1-$percent));
		} else {
			// Darker
			$positivePercent = $percent - ($percent*2);
			$rgb[$i] = round($rgb[$i] * (1-$positivePercent)); // round($rgb[$i] * (1-$positivePercent));
		}

		// In case rounding up causes us to go to 256
		if ($rgb[$i] > 255) {
			$rgb[$i] = 255;
		}
	}

	//// RBG to Hex
	$hex = '';

	for ($i=0; $i < 3; $i++) {
		// Convert the decimal digit to hex
		$hexDigit = dechex($rgb[$i]);

		// Add a leading zero if necessary
		if (strlen($hexDigit) == 1) {
			$hexDigit = '0' . $hexDigit;
		}

		// Append to the hex string
		$hex .= $hexDigit;
	}

	return $hash.$hex;
}

/**
 * add_business_hours - Add business hours highlight support for all rrdtool based charts
 *
 * @param  (array)  $data    - The graph_array data containing all rrdtool graph options
 *
 * @return (array) - the graph_array containing AREA definitions for the business hours
 *
 */
function add_business_hours($data) {
    if (read_config_option('business_hours_enable') == 'on') {
        if ($data['start'] < 0 ) {
            $bh_graph_start = time() + $data['start'];
            $bh_graph_end   = time() + $data['end'];
        } else {
            $bh_graph_start = $data['start'];
            $bh_graph_end   =  $data['end'];
        }

        preg_match('/(\d+)\:(\d+)/',read_config_option('business_hours_start'), $bh_start_matches);
        preg_match('/(\d+)\:(\d+)/',read_config_option('business_hours_end'), $bh_end_matches);

        $start_bh_time = mktime( $bh_start_matches[1],$bh_start_matches[2],0,date('m',$bh_graph_start),date('d',$bh_graph_start),date('Y',$bh_graph_start));
        $end_bh_time   = mktime( $bh_end_matches[1],$bh_end_matches[2],0,date('m',$bh_graph_end),date('d',$bh_graph_end),date('Y',$bh_graph_end));

        if ($start_bh_time < $bh_graph_start) {
            if ($start_bh_time < $end_bh_time) {
                $start_bh_time = $bh_graph_start;
            } else {
                $start_bh_time = mktime($bh_start_matches[1], $bh_start_matches[2], 0, date('m', $bh_graph_start), date('d', $bh_graph_start) + 1, date('Y', $bh_graph_start));
            }
        }

        // Get the number of days:
        $datediff    = $bh_graph_end - $bh_graph_start;
        $num_of_days = round($datediff / (60 * 60 * 24)) + 1;

        if ($num_of_days <= read_config_option('business_hours_max_days')) {
            for ($day=0; $day<$num_of_days; $day++ ) {
                $current_start_bh_time = mktime($bh_start_matches[1],$bh_start_matches[2],0,date('m',$start_bh_time),date('d',$start_bh_time)+$day,date('Y',$start_bh_time));
                $current_end_bh_time   = mktime( $bh_end_matches[1],$bh_end_matches[2],0,date('m',$start_bh_time),date('d',$start_bh_time)+$day,date('Y',$start_bh_time));

                if ($current_start_bh_time < $bh_graph_start) {
                    $current_start_bh_time = $bh_graph_start;
                }

                if ($current_end_bh_time > $bh_graph_end) {
                    $current_end_bh_time = $bh_graph_end;
                }

                $data['graph_defs'] .= 'CDEF:officehours' . $day . '=a,POP,TIME,' . $current_start_bh_time . ',LT,1,0,IF,TIME,' . $current_end_bh_time . ',GT,1,0,IF,MAX,0,GT,0,1,IF' . RRD_NL;
                $data['graph_defs'] .= 'CDEF:dslimit' . $day . '=INF,officehours' . $day . ',*' . RRD_NL;

                if (preg_match('/[0-9A-Fa-f]{6,8}/',read_config_option('business_hours_color'))) {
                    $bh_color = read_config_option('business_hours_color');
                } else {
                    $bh_color = 'ccccccff';
                }

                if (date('N',$current_start_bh_time) < 6) {
                    $data['graph_defs'] .= 'AREA:dslimit' . $day . '#' . $bh_color . RRD_NL;
                }

                if ((date('N',$current_start_bh_time) > 5) && (read_config_option('business_hours_hideWeekends') == '')) {
                    $data['graph_defs'] .= 'AREA:dslimit' . $day . '#'.$bh_color . RRD_NL;
                }
            }
        }
    }

    return $data;
}

