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

// define standard file descriptors if not running under CLI
if (php_sapi_name() != 'cli') {
	define('STDIN', fopen('php://stdin', 'r'));
	define('STDOUT', fopen('php://stdout', 'w'));
	define('STDERR', fopen('php://stderr', 'w'));
}

// make sure data is flushed immediately
ob_implicit_flush();
ini_set('output_buffering', 'Off');

global $environ, $poller_id, $connection;

// some debugging
$pid         = getmypid();
$ctr         = 0;
$poller_id   = 1;
$environ     = 'cmd';
$conn_mode   = 'online';
$help        = false;
$version     = false;

$called_by_script_server = false;

$shortopts = 'VvHh';

$longopts = [
	'environ:',
	'poller:',
	'mode:',
	'version',
	'help'
];

$options         = getopt($shortopts, $longopts);
$arguments_valid = is_array($options);
$modern_options  = $arguments_valid && $options !== [];

if ($modern_options) {
	for ($index = 1; $index < $_SERVER['argc']; $index++) {
		$argument = $_SERVER['argv'][$index];

		if (preg_match('/^-[VvHh]+$/D', $argument) || in_array($argument, ['--help', '--version'], true) || preg_match('/^--(?:environ|poller|mode)=.*$/D', $argument)) {
			continue;
		}

		if (in_array($argument, ['--environ', '--poller', '--mode'], true) && isset($_SERVER['argv'][$index + 1]) && !str_starts_with($_SERVER['argv'][$index + 1], '-')) {
			$index++;

			continue;
		}

		$arguments_valid = false;

		break;
	}

	foreach ($options as $arg => $value) {
		switch($arg) {
			case 'environ':
				$environ = $value;

				break;
			case 'poller':
				$poller_id = $value;

				break;
			case 'mode':
				$conn_mode = $value;

				break;
			case 'h':
			case 'H':
			case 'help':
				$help = true;

				break;
			case 'v':
			case 'V':
			case 'version':
				$version = true;

				break;
		}
	}
} elseif ($_SERVER['argc'] >= 2) {
	$legacy_environment = $_SERVER['argv'][1] == 'cmd.php' ? 'cmd' : $_SERVER['argv'][1];

	if ($_SERVER['argc'] > 3 || !in_array($legacy_environment, ['cmd', 'spine', 'realtime', 'other'], true)) {
		$arguments_valid = false;
	} else {
		$environ = $legacy_environment;
	}

	if ($_SERVER['argc'] == 3) {
		$poller_id = $_SERVER['argv'][2];
	} else {
		$poller_id = 1;
	}
}

if (!$arguments_valid) {
	fwrite(STDERR, "Invalid Script Server arguments\n");

	exit(1);
}

if (!in_array($environ, ['cmd', 'spine', 'realtime', 'other'], true)) {
	fwrite(STDERR, "Invalid Script Server environment\n");

	exit(1);
}

if ((!is_string($poller_id) && !is_int($poller_id)) || !preg_match('/^(?:[1-9][0-9]*|[A-Fa-f0-9]{64})$/D', (string) $poller_id)) {
	fwrite(STDERR, "Invalid Script Server poller id\n");

	exit(1);
}

$poller_id = (string) $poller_id;

if (!in_array($conn_mode, ['online', 'offline'], true)) {
	fwrite(STDERR, "Invalid Script Server connection mode\n");

	exit(1);
}

/* Start the output guard before the bootstrap. Output while Cacti and its
 * plugins load, such as a plugin print or text after a closing tag in
 * config.php, would otherwise reach stdout ahead of the startup banner, and the
 * poller would reject every worker. Help and version print on purpose. */
require_once(__DIR__ . '/lib/script_server.php');

$output_guard_level = ($help || $version) ? 0 : script_server_start_output_guard();

require(__DIR__ . '/include/cli_check.php');

if ($help) {
	display_help();

	exit(0);
}

if ($version) {
	display_version();

	exit(0);
}

// record the script start time
$start = microtime(true);

$include_file = '';

if (CACTI_SERVER_OS == 'win32') {
	cacti_log('DEBUG: GETCWD: ' . cacti_strtolower(strtr((string) getcwd(),'\\','/')),
		false,
		'PHPSVR',
		POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . cacti_strtolower(strtr(__DIR__,'\\','/')), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
} else {
	cacti_log('DEBUG: GETCWD: ' . strtr((string) getcwd(),'\\','/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
	cacti_log('DEBUG: DIRNAM: ' . strtr(__DIR__,'\\','/'), false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
}

cacti_log('DEBUG: SERVER: ' . $environ . ' PARENT: ' . $parent_pid, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
cacti_log('DEBUG: FILENM: ' . __FILE__, false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);

// if multiple polling intervals are defined, compensate for them
$polling_interval = read_config_option('poller_interval');

if (!empty($polling_interval)) {
	define('MAX_POLLER_RUNTIME', $polling_interval);
} else {
	define('MAX_POLLER_RUNTIME', 300);
}

// Let PHP only run 1 second longer than the max runtime
ini_set('max_execution_time', MAX_POLLER_RUNTIME + 1);

require_once(__DIR__ . '/lib/snmp.php');

if ($output_guard_level === 0) {
	cacti_log('ERROR: Script Server could not establish its output guard', false, 'PHPSVR');
	db_close();

	exit(1);
}

snmp_auth_cache_load();

$allowed_roots     = script_server_allowed_roots();
$failed_includes   = [];
$input_nonblocking = @stream_set_blocking(STDIN, false);
$debug_logging     = script_server_debug_logging_enabled();

// send status only after every part of the worker is ready
cacti_log('PHP Script Server has Started - Parent is ' . $environ, false, 'PHPSVR', POLLER_VERBOSITY_HIGH);

fputs(STDOUT, 'PHP Script Server has Started - Parent is ' . $environ . "\n");
fflush(STDOUT);

// process waits for input and then calls functions as required
while (1) {
	$result = '';

	[$input_string, $overlong, $framing_healthy] = script_server_read_request(
		STDIN,
		SCRIPT_SERVER_MAX_REQUEST_LENGTH,
		0.5,
		$input_nonblocking
	);

	if ($overlong) {
		cacti_log('WARNING: Script Server request exceeds ' . SCRIPT_SERVER_MAX_REQUEST_LENGTH . ' bytes and was discarded', false, 'PHPSVR');

		if (!$framing_healthy) {
			cacti_log('WARNING: Script Server request framing could not be recovered, replacing worker', false, 'PHPSVR');
			db_close();

			exit(1);
		}

		fputs(STDOUT, "U\n");
		fflush(STDOUT);

		continue;
	}

	if ($input_string === false) {
		cacti_log('DEBUG: Script Server input closed, exiting', false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
		db_close();

		exit(0);
	}

	$input_string = script_server_normalize_request($input_string);

	if ($input_string === false) {
		cacti_log('WARNING: Script Server request contains an invalid control character and was discarded', false, 'PHPSVR');
		fputs(STDOUT, "U\n");
		fflush(STDOUT);

		continue;
	}

	$isParentRunning = true;

	if (empty($input_string)) {
		if (!empty($parent_pid)) {
			if (cacti_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$out  = [];
				$ppid = intval($parent_pid);
				exec("TASKLIST /FO LIST /FI \"PID eq $ppid\"", $out);

				$isParentRunning = (cacti_count($out) > 1);
			} elseif (function_exists('posix_kill')) {
				$isParentRunning = posix_kill(intval($parent_pid), 0);
			}
		}

		if ($isParentRunning) {
			if (!empty($parent_pid)) {
				cacti_log('WARNING: Input Expected, parent process ' . $parent_pid . ' should have sent non-blank line',
					false,
					'PHPSVR',
					POLLER_VERBOSITY_DEBUG);
			} else {
				cacti_log('WARNING: Input Expected, unable to check parent process', false, 'PHPSVR', POLLER_VERBOSITY_MEDIUM);
			}
		} else {
			cacti_log('WARNING: Parent (' . $parent_pid . ') of Script Server (' . getmypid() . ') has been lost, forcing exit',
				false,
				'PHPSVR',
				POLLER_VERBOSITY_HIGH);
			$input_string = 'quit';
		}
	}

	if (!empty($input_string)) {
		if ($input_string == 'quit') {
			fputs(STDOUT, 'PHP Script Server Shutdown request received, exiting' . PHP_EOL);
			fflush(STDOUT);
			cacti_log('DEBUG: PHP Script Server Shutdown request received, exiting', false, 'PHPSVR', POLLER_VERBOSITY_DEBUG);
			db_close();

			exit(0);
		}

		if ($input_string != '') {
			$validated_request = script_server_validate_request($input_string, $allowed_roots);

			if ($validated_request === false) {
				cacti_log('WARNING: Script Server rejected an invalid request before loading a script', false, 'PHPSVR');
				fputs(STDOUT, "U\n");
				fflush(STDOUT);

				continue;
			}

			[$include_file, $function, $parameter_array] = $validated_request;
			$script_ready                                = true;

			if ($debug_logging) {
				script_server_log_request((int) $pid, $ctr, $include_file, $function, $parameter_array, true);
			}

			$script_ready = !isset($failed_includes[$include_file]);

			if ($script_ready && !function_exists($function)) {
				/**
				 * set this variable so the calling script can determine if it was called
				 * by the script server or stand-alone
				 */
				$called_by_script_server = true;

				try {
					require_once($include_file);
				} catch (Throwable $e) {
					$failed_includes[$include_file] = true;
					$script_ready                   = false;
					cacti_log("WARNING: Script file '" . script_server_log_token(basename($include_file)) . "' raised " . get_class($e) . ' while loading', false, 'PHPSVR');
				}

				if (!script_server_reset_output_guard($output_guard_level)) {
					cacti_log('WARNING: Script Server output buffering was corrupted while loading a script, replacing worker', false, 'PHPSVR');
					db_close();

					exit(1);
				}

				error_reporting(0);
			}

			error_reporting(0);

			$response_healthy = true;
			$result           = $script_ready
					? script_server_run($function, $parameter_array, $include_file, $response_healthy, true)
				: 'U';

			if (!script_server_reset_output_guard($output_guard_level)) {
				cacti_log('WARNING: Script Server output buffering was corrupted while dispatching a request, replacing worker', false, 'PHPSVR');
				db_close();

				exit(1);
			}

			if (!$response_healthy) {
				cacti_log('WARNING: Script Server response violated the framing or output limit, replacing worker', false, 'PHPSVR');
				db_close();

				exit(1);
			}

			fputs(STDOUT, $result . "\n");
			fflush(STDOUT);

			if ($debug_logging) {
				script_server_log_response((int) $pid, $ctr, $result, true);
			}

			$ctr++;
		}
	}

	// end the process if the runtime exceeds MAX_POLLER_RUNTIME
	if (($start + MAX_POLLER_RUNTIME) < time()) {
		cacti_log('Maximum runtime of ' . MAX_POLLER_RUNTIME . ' seconds exceeded for the Script Server. Exiting.',
			true,
			'PHPSVR');

		exit(-1);
	}
}

/**
 * sig_handler - properly handle signals and shutdown
 *
 * @param int $signo
 *
 * @return void
 */
function sig_handler(int $signo) : void {
	global $include_file, $function, $parameter_array;

	// argument values can carry device credentials, so only their count is logged
	$parameters = (is_array($parameter_array) ? cacti_count($parameter_array) : 0) . ' argument(s)';
	$log_file   = script_server_log_token(basename((string) $include_file));
	$log_func   = script_server_log_token((string) $function);

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGABRT:
		case SIGQUIT:
		case SIGSEGV:
			cacti_log("WARNING: Script Server terminated with signal '$signo' in file:'$log_file', function:'$log_func', params:'$parameters'",
				false,
				'PHPSVR',
				POLLER_VERBOSITY_MEDIUM);
			db_close();

			exit;
		default:
			cacti_log("WARNING: Script Server received signal '$signo' in file:'$log_file', function:'$log_func', params:'$parameters'",
				false,
				'PHPSVR',
				POLLER_VERBOSITY_HIGH);

			break;
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_version();
	print "Cacti Script Server, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print ('usage: script_server.php [environ poller_id] | [--environ=S ' .
		'--poller=N --mode=S] | [-hH] | [-vV]') . PHP_EOL . PHP_EOL;
	print 'Cacti\'s Script Server.  The Script Server provides a memory resident server for executing php' . PHP_EOL;
	print ('scripts saving reducing the time to process the scripts through ' .
		'pre-compiling them for all consumers.') . PHP_EOL;
	print ('The Script Server can be called using two methods.  The first method ' .
		'is the legacy method.  When using') . PHP_EOL;
	print ('the legacy method, the first argument will be the environment, and ' .
		'the second will be the poller id.') . PHP_EOL . PHP_EOL;

	print ('The modern calling method has multiple options processed by getopt.  ' .
		'Those options include:') . PHP_EOL . PHP_EOL;

	print '  --environ=S      The default being \'cmd\', and options being \'spine\', \'cmd\', and \'realtime\'' . PHP_EOL;
	print '  --poller=N       The poller id for the Data Collector in use' . PHP_EOL;
	print '  --mode=S         Force a Database mode \'online\' or \'offline\'.  This is only relevant for' . PHP_EOL;
	print '                   Remote Data Collectors.' . PHP_EOL . PHP_EOL;
	print '  -h, -H, --help   Display this help text.' . PHP_EOL;
	print '  -v, -V, --version Display the Script Server version.' . PHP_EOL . PHP_EOL;

	print ('If no options are passed to the Script Server, it assumes that the ' .
		'environ is \'cmd\' and the poller id') . PHP_EOL;
	print 'is 1 or the Main Data Collector.' . PHP_EOL . PHP_EOL;

	print 'It is common that when developing Cacti Data Input methods one will use the Script Server to test' . PHP_EOL;
	print 'scripts prior to creating thousands of Graphs using them.  To to that simply create at least' . PHP_EOL;
	print 'one Data Source, start the Script Server, and then copy the Poller Cache entry into a running' . PHP_EOL;
	print 'Script Server.  When doing so you should see the output you expect printed to standard output.  When' . PHP_EOL;
	print 'running the Script Server, simply enter \'quit\' to exit.' . PHP_EOL . PHP_EOL;
}
