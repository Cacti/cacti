<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/include/cli_check.php');

/* need to capture signals from users */
function sig_handler($signo) {
	global $include_file, $function, $parameters;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGABRT:
		case SIGQUIT:
		case SIGSEGV:
			cacti_log("WARNING: Script Server terminated with signal '$signo' in file:'" . basename($include_file) . "', function:'$function', params:'$parameters'", false, 'PHPSVR', POLLER_VERBOSITY_MEDIUM);
			db_close();

			exit;
			break;
		default:
			cacti_log("WARNING: Script Server received signal '$signo' in file:'$include_file', function:'$function', params:'$parameters'", false, 'PHPSVR', POLLER_VERBOSITY_HIGH);

			break;
	}
}

/* used for includes */
/* install signal handlers for UNIX only */
$parent_pid = '';
if (function_exists('posix_getppid')) {
	$parent_pid = posix_getppid();
}

if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
	pcntl_signal(SIGABRT, 'sig_handler');
	pcntl_signal(SIGQUIT, 'sig_handler');
	pcntl_signal(SIGSEGV, 'sig_handler');
}

error_reporting(E_ALL);

/* define STDOUT/STDIN file descriptors if not running under CLI */
if (php_sapi_name() != 'cli') {
	define('STDIN', fopen('php://stdin', 'r'));
	define('STDOUT', fopen('php://stdout', 'w'));
}

/* make sure data is flushed immediately */
ob_implicit_flush();

/* signal for realtime */
global $environ, $poller_id;

/* record the script start time */
$start = microtime(true);

/* some debugging */
$pid = getmypid();
$ctr = 0;
$called_by_script_server = false;

/* if multiple polling intervals are defined, compensate for them */
$polling_interval = read_config_option('poller_interval');

if (!empty($polling_interval)) {
	$num_polling_items = db_fetch_cell_prepared('SELECT count(*) FROM poller_item WHERE rrd_next_step <= 0 AND poller_id = ?', array($config['poller_id']), 'count(*)');
	define('MAX_POLLER_RUNTIME', $polling_interval);
} else {
	$num_polling_items = db_fetch_cell_prepared('SELECT count(*) FROM poller_item WHERE poller_id = ?', array($config['poller_id']), 'count(*)');
	define('MAX_POLLER_RUNTIME', 300);
}

/* Let PHP only run 1 second longer than the max runtime */
ini_set('max_execution_time', MAX_POLLER_RUNTIME + 1);

/* Record the calling environment */
if ($_SERVER['argc'] >= 2) {
	if (substr( $_SERVER['argv'][1], 0, 5) == 'spine')
		global $force_level;
		$environ = 'spine';
		if (strlen($_SERVER['argv'][1]) > 5) {
			$force_level = intval(substr($_SERVER['argv'][1], 5));
			cacti_log('INFO: Forcing log level to: ' . $force_level, false, 'PHPSVR', POLLER_VERBOSITY_HIGH);
		}
	else
		if (($_SERVER['argv'][1] == 'cmd.php') || ($_SERVER['argv'][1] == 'cmd'))
			$environ = 'cmd';
		elseif ($_SERVER['argv'][1] == 'realtime')
			$environ = 'realtime';
		else
			$environ = 'other';

	if ($_SERVER['argc'] == 3)
		$poller_id = $_SERVER['argv'][2];
	else
		$poller_id = 1;
} else {
	$environ = 'cmd';
	$poller_id = 1;
}

cacti_log('DEBUG: SERVER: ' . $environ . ' PARENT: ' . $parent_pid, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

if ($config['cacti_server_os'] == 'win32') {
	cacti_log('DEBUG: GETCWD: ' . strtolower(strtr(getcwd(),"\\",'/')), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . strtolower(strtr(dirname(__FILE__),"\\",'/')), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
} else {
	cacti_log('DEBUG: GETCWD: ' . strtr(getcwd(),"\\",'/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . strtr(dirname(__FILE__),"\\",'/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
}

cacti_log('DEBUG: FILENM: ' . __FILE__, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

/* send status back to the server */
cacti_log('PHP Script Server has Started - Parent is ' . $environ, false, 'PHPSVR', POLLER_VERBOSITY_HIGH);

fputs(STDOUT, 'PHP Script Server has Started - Parent is ' . $environ . "\n");
fflush(STDOUT);

/* process waits for input and then calls functions as required */
while (1) {
	$result = '';

	$input_string    = fgets(STDIN, 1024);
	$function        = '';
	$parameters      = '';
	$parameter_array = array();

	$isParentRunning = true;
	if (empty($input_string)) {
		if (!empty($parent_pid)) {
			if(strncasecmp(PHP_OS, "win", 3) == 0) {
				$out = [];
				exec("TASKLIST /FO LIST /FI \"PID eq $parent_pid\"", $out);

				$isParentRunning = (cacti_count($out) > 1);
			} elseif (function_exists('posix_kill')) {
				$isParentRunning = posix_kill(intval($parent_pid), 0);
			}
		}

		if ($isParentRunning) {
			if (!empty($parent_pid)) {
				cacti_log('WARNING: Input Expected, parent process ' . $parent_pid . ' should have sent non-blank line', false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
			} else {
				cacti_log('WARNING: Input Expected, unable to check parent process', false, 'PHPSVR', POLLER_VERBOSITY_MEDIUM);
			}
		} else {
			cacti_log('WARNING: Parent (' . $parent_pid . ') of Script Server (' . getmypid() . ') has been lost, forcing exit', false, 'PHPSVR', POLLER_VERBOSITY_HIGH);
			$input_string = 'quit';
		}
	}

	if (!empty($input_string)) {
		$input_string = trim($input_string);

		if (substr($input_string,0,4) == 'quit') {
			if (!$called_by_script_server) {
				fputs(STDOUT, 'PHP Script Server Shutdown request received, exiting' . PHP_EOL);
				fflush(STDOUT);
				cacti_log('DEBUG: PHP Script Server Shutdown request received, exiting', false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
			}
			db_close();
			exit(0);
		}

		if ($input_string != '') {
			/* pull off the parameters */
			$i = 0;
			while ( true ) {
				$pos = strpos($input_string, ' ');

				if ($pos > 0) {
					switch ($i) {
					case 0:
						/* cut off include file as first part of input string and keep rest for further parsing */
						$include_file = trim(substr($input_string,0,$pos));
						$input_string = trim(strchr($input_string, ' ')) . ' ';
						break;
					case 1:
						/* cut off function as second part of input string and keep rest for further parsing */
						$function = trim(substr($input_string,0,$pos), "' ");
						$input_string = trim(strchr($input_string, ' ')) . ' ';
						break;
					case 2:
						/* take the rest as parameter(s) to the function stripped off previously */
						$parameters = trim($input_string);
						break 2;
					}
				} else {
					break;
				}

				$i++;
			}

			if (!parseArgs($parameters, $parameter_array)) {
				cacti_log("WARNING: Script Server count not parse '$parameters' for $function", false, 'PHPSVR');
				fputs(STDOUT, "U\n");
				fflush(STDOUT);
				continue;
			}

			cacti_log("DEBUG: PID[$pid] CTR[$ctr] INC: '". basename($include_file) .
				"' FUNC: '$function' PARMS: '" . implode('\', \'',$parameter_array) .
				"'", false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

			/* validate the existence of the function, and include if applicable */
			if (!function_exists($function)) {
				if (file_exists($include_file)) {
					/* quirk in php on Windows, believe it or not.... */
					/* path must be lower case */
					if ($config['cacti_server_os'] == 'win32') {
						$include_file = strtolower($include_file);
					}

					/* set this variable so the calling script can determine if it was called
					 * by the script server or stand-alone */
					$called_by_script_server = true;

					/* turn on output buffering to avoid problems with nasty scripts */
					ob_start();
					include_once($include_file);
					ob_end_clean();
				} else {
					cacti_log('WARNING: PHP Script File to be included, does not exist', false, 'PHPSVR');
				}
			}

			if (function_exists($function)) {
				if ($parameters == '') {
					$result = call_user_func($function);
				} else {
					$result = call_user_func_array($function, $parameter_array);
				}

				fputs(STDOUT, trim($result) . "\n");
				fflush(STDOUT);

				cacti_log("DEBUG: PID[$pid] CTR[$ctr] RESPONSE:'$result'", false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

				$ctr++;
			} else {
				cacti_log("WARNING: Function does not exist  INC: '". basename($include_file) . "' FUNC: '" .$function . "' PARMS: '" . $parameters . "'", false, 'PHPSVR');
				fputs(STDOUT, "U\n");
				fflush(STDOUT);
			}
		}
	}

	/* end the process if the runtime exceeds MAX_POLLER_RUNTIME */
	if (($start + MAX_POLLER_RUNTIME) < time()) {
		cacti_log('Maximum runtime of ' . MAX_POLLER_RUNTIME . ' seconds exceeded for the Script Server. Exiting.', true, 'PHPSVR');
		exit (-1);
	}
}

function parseArgs($string, &$str_list, $debug = false) {
	$delimiters = array("'",'"');
	$delimited  = false;
	$str_list   = array();

	if ($debug) echo "String: '" . $string . "'\n";

	foreach($delimiters as $delimiter) {
		if (strpos($string, $delimiter) !== false) {
			$delimited = true;
			break;
		}
	}

	/* process the simple case */
	if (!$delimited) {
		$str_list = explode(' ', $string);

		if ($debug) echo "Output: '" . implode(",", $str_list) . "'\n";

		return true;
	}

	/* Break str down into an array of characters and process */
	$char_array = str_split($string);
	$escaping = false;
	$indelim  = false;
	$parse_ok = true;
	$curstr   = '';
	foreach($char_array as $char) {
		switch ($char) {
		case '\'':
		case '"':
			if (!$indelim) {
				if (!$escaping) {
					$indelim = true;
				} else {
					$curstr .= $char;
					$escaping = false;
				}
			} elseif (!$escaping) {
				$str_list[] = $curstr;
				$curstr     = '';
				$indelim    = false;
			} elseif ($escaping) {
				$curstr  .= $char;
				$escaping = false;
			}

			break;
		case '\\':
			if ($indelim) {
				$curstr  .= $char;
			} elseif ($escaping) {
				$curstr  .= $char;
				$escaping = false;
			} else {
				$escaping = true;
			}

			break;
		case ' ':
			if ($escaping) {
				$parse_ok = false;
				$msg = 'Parse error attempting to parse string';
			} elseif ($indelim) {
				$curstr .= $char;
			} elseif ($curstr != '') {
				$str_list[] = $curstr;
				$curstr = '';
			}

			break;
		case '`':
			$parse_ok = false;
			$msg   = 'Backtic (`) characters not allowed';

			break;
		default:
			if ($escaping) {
				$parse_ok = false;
				$msg   = 'Parse error attempting to parse string';
			} else {
				$curstr .= $char;
			}
			break;
		}

		if (!$parse_ok) {
			break;
		}
	}

	/* Add the last str to the string array */
	if ($indelim || $escaping) {
		$parse_ok = false;
		$msg = 'Parse error attempting to parse string';
	}

	if (!$parse_ok) {
		echo 'ERROR: ' . $msg . " '" . $string . "'\n";
	} elseif ($curstr != '') {
		$str_list[] = $curstr;
	}

	if ($debug) echo "Output: '" . implode(",", $str_list) . "'\n";

	return $parse_ok;
}
