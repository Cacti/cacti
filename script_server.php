#!/usr/bin/env php
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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

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

error_reporting(0);

/* define STDOUT/STDIN file descriptors if not running under CLI */
if (php_sapi_name() != 'cli') {
	define('STDIN', fopen('php://stdin', 'r'));
	define('STDOUT', fopen('php://stdout', 'w'));
}

/* make sure data is flushed immediately */
ob_implicit_flush();
ini_set('output_buffering', 'Off');

global $environ, $poller_id, $connection;

/* some debugging */
$pid         = getmypid();
$ctr         = 0;
$poller_id   = 1;
$environ     = 'cmd';
$conn_mode   = 'online';
$legacy      = true;
$options     = array();
$help        = false;
$version     = false;

$called_by_script_server = false;

$shortopts = 'VvHh';

$longopts = array(
	'environ::',
	'poller::',
	'mode::',
	'version',
	'help'
);

$options = getopt($shortopts, $longopts);

if (sizeof($options)) {
	foreach($options as $arg => $value) {
		$allow_multi = false;

		switch($arg) {
			case 'enviorn':
				$environ = $value;

				break;
			case 'poller':
				$poller_id = $value;

				break;
			case 'mode':
				$conn_mode = $value;

				break;
			case 'help':
				$help = true;

				break;
			case 'version':
				$version = true;

				break;
		}
	}
} elseif ($_SERVER['argc'] >= 2) {
	if (in_array('spine', $_SERVER['argv'])) {
		$environ = 'spine';
	} elseif (in_array('realtime', $_SERVER['argv'])) {
		$environ = 'realtime';
	} elseif (in_array('cmd', $_SERVER['argv']) || in_array('cmd.php', $_SERVER['argv'])) {
		$environ = 'cmd';
	} else {
		$environ = 'other';
	}

	if ($_SERVER['argc'] == 3) {
		$poller_id = $_SERVER['argv'][2];
	} else {
		$poller_id = 1;
	}
}

require(__DIR__ . '/include/cli_check.php');

if ($help) {
	display_help();
	exit(0);
} elseif ($version) {
	display_version();
	exit(0);
}

/* record the script start time */
$start = microtime(true);

if ($config['cacti_server_os'] == 'win32') {
	cacti_log('DEBUG: GETCWD: ' . strtolower(strtr(getcwd(),"\\",'/')), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . strtolower(strtr(dirname(__FILE__),"\\",'/')), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
} else {
	cacti_log('DEBUG: GETCWD: ' . strtr(getcwd(),"\\",'/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . strtr(dirname(__FILE__),"\\",'/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
}

cacti_log('DEBUG: SERVER: ' . $environ . ' PARENT: ' . $parent_pid, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
cacti_log('DEBUG: FILENM: ' . __FILE__, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

/* if multiple polling intervals are defined, compensate for them */
$polling_interval = read_config_option('poller_interval');

if (!empty($polling_interval)) {
	define('MAX_POLLER_RUNTIME', $polling_interval);
} else {
	define('MAX_POLLER_RUNTIME', 300);
}

/* Let PHP only run 1 second longer than the max runtime */
ini_set('max_execution_time', MAX_POLLER_RUNTIME + 1);

/* send status back to the server */
cacti_log('PHP Script Server has Started - Parent is ' . $environ, false, 'PHPSVR', POLLER_VERBOSITY_HIGH);

fputs(STDOUT, 'PHP Script Server has Started - Parent is ' . $environ . "\n");
fflush(STDOUT);

require_once(__DIR__ . '/lib/snmp.php');
snmp_auth_cache_load();

/* process waits for input and then calls functions as required */
while (1) {
	$result = '';

	$input_string    = script_server_read_request(STDIN);
	$function        = '';
	$parameters      = '';
	$parameter_array = array();

	if ($input_string === null) {
		cacti_log('WARNING: Script Server request exceeds ' . SCRIPT_SERVER_MAX_REQUEST_LENGTH . ' bytes and was discarded', false, 'PHPSVR');

		fputs(STDOUT, "U\n");
		fflush(STDOUT);

		continue;
	}

	$isParentRunning = true;
	if (empty($input_string)) {
		if (!empty($parent_pid)) {
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
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
				cacti_log('WARNING: Script Server could not parse the ' . strlen($parameters) . " byte argument list for $function", false, 'PHPSVR');
				fputs(STDOUT, "U\n");
				fflush(STDOUT);
				continue;
			}

			cacti_log("DEBUG: PID[$pid] CTR[$ctr] INC: '". basename($include_file) .
				"' FUNC: '$function' PARMS: '" . implode('\', \'', script_server_redact_params($parameter_array)) .
				"'", false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

			/* Validate the include path unconditionally. Built-ins like system,
			 * passthru, exec satisfy function_exists() without ever loading a
			 * script file, so the path guard cannot be gated on the function
			 * being undefined. include_once() is idempotent, so re-running it
			 * on cached entries is a no-op. The scripts_path setting is an
			 * explicitly configured Cacti script root and may live outside the
			 * web root, so it is an allowed containment root as well. */
			$real_include = realpath($include_file);
			$allowed_roots = [$config['base_path']];

			if (!empty($config['scripts_path'])) {
				$allowed_roots[] = $config['scripts_path'];
			}

			$path_ok = script_server_path_is_allowed($real_include, $allowed_roots);

			if (!$path_ok) {
				if ($real_include !== false) {
					cacti_log("WARNING: Script file '$include_file' resolves outside the allowed script roots. Rejected.", false, 'PHPSVR');
				} else {
					cacti_log("WARNING: Script file '$include_file' could not be resolved. Rejected.", false, 'PHPSVR');
				}

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			$include_file = $real_include;

			if (!file_exists($include_file)) {
				cacti_log('WARNING: PHP Script File to be included, does not exist', false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			/* quirk in php on Windows, believe it or not....path must be lower case */
			if ($config['cacti_server_os'] == 'win32') {
				$include_file = strtolower($include_file);
			}

			/* set this variable so the calling script can determine if it was
			 * called by the script server or stand-alone */
			$called_by_script_server = true;

			/* turn on output buffering to avoid problems with nasty scripts */
			ob_start();
			include_once($include_file);
			ob_end_clean();

			error_reporting(0);

			if (!function_exists($function)) {
				cacti_log("WARNING: Function does not exist  INC: '". basename($include_file) . "' FUNC: '" .$function . "' PARMS: '" . implode('\', \'', script_server_redact_params($parameter_array)) . "'", false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			/* Refuse to call PHP internals (system, passthru, exec, ...) and
			 * any function whose source file lives outside base_path. The
			 * script-server contract is to dispatch into user scripts in the
			 * Cacti tree; anything else is a containment failure. */
			try {
				$ref = new ReflectionFunction($function);
			} catch (ReflectionException $e) {
				cacti_log("WARNING: Function '$function' not introspectable. Rejected.", false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			if ($ref->isInternal()) {
				cacti_log("WARNING: Refusing to dispatch PHP internal function '$function' from script server.", false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			$fn_file = $ref->getFileName();

			if ($fn_file === false) {
				cacti_log("WARNING: Function '$function' has no source file. Rejected.", false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			$fn_real = realpath($fn_file);

			if ($fn_real !== false) {
				$fn_real = str_replace('\\', '/', $fn_real);
			}

			if (!script_server_path_is_allowed($fn_real, $allowed_roots)) {
				cacti_log("WARNING: Function '$function' defined outside the allowed script roots ('$fn_file'). Rejected.", false, 'PHPSVR');

				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			$result = script_server_call($function, $parameters, $parameter_array);

			fputs(STDOUT, trim($result) . "\n");
			fflush(STDOUT);

			cacti_log("DEBUG: PID[$pid] CTR[$ctr] RESPONSE:'$result'", false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

			$ctr++;
		}
	}

	/* end the process if the runtime exceeds MAX_POLLER_RUNTIME */
	if (($start + MAX_POLLER_RUNTIME) < time()) {
		cacti_log('Maximum runtime of ' . MAX_POLLER_RUNTIME . ' seconds exceeded for the Script Server. Exiting.', true, 'PHPSVR');

		exit (-1);
	}
}

/**
 * Handles the parseargs. Used as part of Cacti's script server functionality.
 *
 * @param string $string The string.
 * @param array &$str_list The str list.
 * @param bool $debug The debug.
 *
 * @return bool True on success, false otherwise.
 */
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

	/* Break str down into an array of characters and process.  $indelim holds
	 * the quote that opened the current quoted section, so only that quote
	 * closes it.  Quoted and unquoted pieces with no space between them form a
	 * single argument, which is how cacti_escapeshellarg() encodes a value that
	 * contains an apostrophe. */
	$char_array = str_split($string);
	$escaping = false;
	$indelim  = false;
	$inword   = false;
	$parse_ok = true;
	$curstr   = '';
	foreach($char_array as $char) {
		switch ($char) {
		case '\'':
		case '"':
			if ($indelim === false) {
				if (!$escaping) {
					$indelim = $char;
					$inword  = true;
				} else {
					$curstr .= $char;
					$escaping = false;
				}
			} elseif ($char === $indelim) {
				$indelim = false;
			} else {
				$curstr .= $char;
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
			} elseif ($indelim !== false) {
				$curstr .= $char;
			} elseif ($curstr != '' || $inword) {
				$str_list[] = $curstr;
				$curstr = '';
				$inword = false;
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
	if ($indelim !== false || $escaping) {
		$parse_ok = false;
		$msg = 'Parse error attempting to parse string';
	}

	if (!$parse_ok) {
		/* the caller answers U on STDOUT; an extra line here would be read as
		 * the reply to the next request */
		if ($debug) echo 'ERROR: ' . $msg . " '" . $string . "'\n";
	} elseif ($curstr != '' || $inword) {
		$str_list[] = $curstr;
	}

	if ($debug) echo "Output: '" . implode(",", $str_list) . "'\n";

	return $parse_ok;
}

/**
 * Read one request line from the poller. Used as part of Cacti's script server functionality.
 *
 * A line longer than SCRIPT_SERVER_MAX_REQUEST_LENGTH is reported as null and
 * the rest of it is discarded, so its tail is never read back as a separate
 * request.
 *
 * @param resource $stream The stream to read from.
 * @param float $drain_wait Seconds to wait for each further piece of an over-length line.
 *
 * @return string|false|null The raw line, false when nothing was read, or null when the line was too long.
 */
function script_server_read_request($stream, $drain_wait = 0.5) {
	/* room for the request, its CR LF and fgets' own terminator, so a full
	 * buffer without a LF can only be an over-length line */
	$line = fgets($stream, SCRIPT_SERVER_MAX_REQUEST_LENGTH + 3);

	if ($line === false || $line === '') {
		return $line;
	}

	if (substr($line, -1) != "\n" && strlen($line) >= SCRIPT_SERVER_MAX_REQUEST_LENGTH + 2) {
		script_server_drain_line($stream, $drain_wait);

		return null;
	}

	if (strlen(rtrim($line, "\r\n")) > SCRIPT_SERVER_MAX_REQUEST_LENGTH) {
		return null;
	}

	return $line;
}

/**
 * Discard input up to and including the next line feed. Used as part of Cacti's script server
 * functionality.
 *
 * spine truncates an over-length request without a terminator and then waits
 * for the reply, so a blocking read for the line feed would stall both sides.
 * The drain stops once no further input arrives within $wait seconds.
 *
 * @param resource $stream The input stream.
 * @param float $wait Seconds to wait for each further piece of input.
 *
 * @return void No value is returned.
 */
function script_server_drain_line($stream, $wait) {
	$seconds = (int) floor($wait);
	$micro   = (int) (($wait - $seconds) * 1000000);

	stream_set_blocking($stream, false);

	while (true) {
		$read   = array($stream);
		$write  = null;
		$except = null;

		/* stream_select() cannot watch every stream type (Windows pipes,
		 * memory streams); those are read until a line feed or end of input */
		try {
			$ready = @stream_select($read, $write, $except, $seconds, $micro);
		} catch (Throwable $e) {
			$ready = false;
		}

		if ($ready === 0) {
			break;
		}

		if ($ready === false) {
			stream_set_blocking($stream, true);
		}

		/* non-blocking fgets() stops at the line feed, leaving the next request */
		$chunk = fgets($stream, 8192);

		if ($chunk !== false && substr($chunk, -1) == "\n") {
			break;
		}

		if ($chunk === false && (feof($stream) || $ready === false)) {
			break;
		}
	}

	stream_set_blocking($stream, true);
}

/**
 * Call a validated script function, containing any error it raises. Used as part of Cacti's
 * script server functionality.
 *
 * @param string $function The function name.
 * @param string $parameters The raw parameter string.
 * @param array $parameter_array The parsed parameters.
 *
 * @return mixed The function result, or 'U' when the function failed.
 */
function script_server_call($function, $parameters, $parameter_array) {
	/* one failing script must not take down the server and every request
	 * queued behind it; the parameters can carry device credentials, so
	 * they are left out of the log */
	try {
		if ($parameters == '') {
			$result = call_user_func($function);
		} else {
			$result = call_user_func_array($function, $parameter_array);
		}
	} catch (Throwable $e) {
		cacti_log("WARNING: Function '$function' raised " . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ', returning U', false, 'PHPSVR');

		return 'U';
	}

	if ($result !== null && !is_scalar($result)) {
		cacti_log("WARNING: Function '$function' returned a " . gettype($result) . ', returning U', false, 'PHPSVR');

		return 'U';
	}

	return $result;
}

/**
 * Mask any device SNMP credential among script arguments before they are logged. Used as part
 * of Cacti's script server functionality.
 *
 * The script server cannot tell which argument is a credential, so every
 * argument, and every colon separated field of one, is compared with the
 * credentials configured on the devices.
 *
 * @param array $params The parsed parameters.
 * @param array|null $secrets Credential values as keys, or null to load them once from the database.
 *
 * @return array The parameters with credentials masked.
 */
function script_server_redact_params($params, $secrets = null) {
	static $known = null;

	if ($secrets === null) {
		if ($known === null) {
			$known = array();

			$rows = db_fetch_assoc('SELECT DISTINCT snmp_community, snmp_password, snmp_priv_passphrase FROM host');

			if (is_array($rows)) {
				foreach ($rows as $row) {
					foreach ($row as $value) {
						if ($value != '') {
							$known[$value] = true;
						}
					}
				}
			}
		}

		$secrets = $known;
	}

	foreach ($params as $index => $param) {
		$param = (string) $param;

		if (isset($secrets[$param])) {
			$params[$index] = '********';
		} elseif (strpos($param, ':') !== false) {
			$fields = explode(':', $param);

			foreach ($fields as $findex => $field) {
				if ($field != '' && isset($secrets[$field])) {
					$fields[$findex] = '********';
				}
			}

			$params[$index] = implode(':', $fields);
		}
	}

	return $params;
}

/**
 * Check whether a resolved script path is below one of the configured roots. Used as part of
 * Cacti's script server functionality.
 *
 * @param mixed $resolved_path The realpath() result for the candidate file.
 * @param array $roots Configured Cacti path roots.
 *
 * @return bool Bool.
 */
function script_server_path_is_allowed($resolved_path, array $roots) {
	static $normalized_roots = [];
	static $resolved_paths   = [];

	// Quick short circuit based upon prior checks
	if ($resolved_path === false || !is_string($resolved_path)) {
		return false;
	}

	$opath            = $resolved_path;
	$resolved_path    = rtrim(str_replace('\\', '/', $resolved_path), '/');
	$case_insensitive = (DIRECTORY_SEPARATOR === '\\');
	$cache_key        = implode("\0", $roots);

	/* The allow/deny result depends on both the candidate path and the root
	 * set, so the result cache must be keyed by both. Keying by path alone
	 * would leak a decision made under one set of roots into a later call
	 * using a different, possibly more restrictive, set of roots. */
	$result_key = $cache_key . "\0" . $opath;

	if (isset($resolved_paths[$result_key])) {
		return $resolved_paths[$result_key];
	}

	if (!isset($normalized_roots[$cache_key])) {
		$normalized_roots[$cache_key] = [];

		foreach ($roots as $root) {
			$root = realpath($root);

			if ($root !== false) {
				$normalized_roots[$cache_key][] = rtrim(str_replace('\\', '/', $root), '/');
			}
		}
	}

	foreach ($normalized_roots[$cache_key] as $root) {
		$prefix = $root . '/';

		if ($case_insensitive) {
			if (stripos($resolved_path, $prefix) === 0) {
				return $resolved_paths[$result_key] = true;
			}
		} elseif (strpos($resolved_path, $prefix) === 0) {
			return $resolved_paths[$result_key] = true;
		}
	}

	return $resolved_paths[$result_key] = false;
}

/**
 * Properly handle signals and shutdown. Used as part of Cacti's script server functionality.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void No value is returned.
 */
function sig_handler($signo) {
	global $include_file, $function, $parameter_array;

	/* argument values can carry device credentials, so only their count is logged */
	$parameters = (is_array($parameter_array) ? count($parameter_array) : 0) . ' argument(s)';

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

/**
 * Displays version information. Used as part of Cacti's script server functionality.
 *
 * @return void No value is returned.
 */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Script Server, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * Displays help information. Used as part of Cacti's script server functionality.
 *
 * @return void No value is returned.
 */
function display_help () {
	display_version();

	print PHP_EOL;
	print 'usage: script_server.php [environ poller_id] | [ --environ=S --poller=N --mode [--force-level=N] ]' . PHP_EOL . PHP_EOL;
	print 'Cacti\'s Script Server.  The Script Server provides a memory resident server for executing php' . PHP_EOL;
	print 'scripts saving reducing the time to process the scripts through pre-compiling them for all consumers.' . PHP_EOL;
	print 'The Script Server can be called using two methods.  The first method is the legacy method.  When using' . PHP_EOL;
	print 'the legacy method, the first argument will be the environment, and the second will be the poller id.' . PHP_EOL . PHP_EOL;

	print 'The modern calling method has multiple options processed by getopt.  Those options include:' . PHP_EOL . PHP_EOL;

	print '  --environ=S      The default being \'cmd\', and options being \'spine\', \'cmd\', and \'realtime\'' . PHP_EOL;
	print '  --poller=N       The poller id for the Data Collector in use' . PHP_EOL;
	print '  --force-level=N  Force the logging at the level specified.' . PHP_EOL;
	print '  --mode=S         Force a Database mode \'online\' or \'offline\'.  This is only relevant for' . PHP_EOL;
	print '                   Remote Data Collectors.' . PHP_EOL . PHP_EOL;

	print 'If no options are passed to the Script Server, it assumes that the environ is \'cmd\' and the poller id' . PHP_EOL;
	print 'is 1 or the Main Data Collector.' . PHP_EOL . PHP_EOL;

	print 'It is common that when developing Cacti Data Input methods one will use the Script Server to test' . PHP_EOL;
	print 'scripts prior to creating thousands of Graphs using them.  To to that simply create at least' . PHP_EOL;
	print 'one Data Source, start the Script Server, and then copy the Poller Cache entry into a running' . PHP_EOL;
	print 'Script Server.  When doing so you should see the output you expect printed to standard output.  When' . PHP_EOL;
	print 'running the Script Server, simply enter \'quit\' to exit.' . PHP_EOL . PHP_EOL;
}
