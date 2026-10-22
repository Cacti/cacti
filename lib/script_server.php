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
 * script_server_read_request - read one request line from the Script Server input
 *
 * A request longer than $max_length is reported as overlong and the rest of
 * its line is discarded, so the tail can never be dispatched as a request of
 * its own.
 *
 * @param mixed $stream     The input stream, normally STDIN
 * @param int   $max_length The longest accepted request, excluding CR LF
 * @param float $drain_wait Seconds to wait for more of an overlong line
 *
 * @return array{0: string|false, 1: bool} The line as read, and whether it was overlong
 */
function script_server_read_request(mixed $stream, int $max_length = SCRIPT_SERVER_MAX_REQUEST_LENGTH, float $drain_wait = 0.5) : array {
	// Room for the request and its CR LF, so a full buffer without LF means overlong.
	$line = fgets($stream, max(1, $max_length + 3));

	if ($line === false) {
		return [false, false];
	}

	if (str_ends_with($line, "\n") || strlen($line) < $max_length + 2) {
		return [$line, strlen(rtrim($line, "\r\n")) > $max_length];
	}

	script_server_drain_line($stream, $drain_wait);

	return [$line, true];
}

/**
 * script_server_drain_line - discard input up to and including the next LF
 *
 * Spine truncates an overlong request without a terminator and then waits for
 * the reply, so a blocking read would stall both sides. Stop once no more
 * input arrives within $wait seconds.
 *
 * @param mixed $stream The input stream
 * @param float $wait   Seconds to wait for each further chunk
 *
 * @return void
 */
function script_server_drain_line(mixed $stream, float $wait) : void {
	$seconds = (int) floor($wait);
	$micro   = (int) (($wait - $seconds) * 1000000);

	while (true) {
		$read   = [$stream];
		$write  = null;
		$except = null;

		// stream_select() is unsupported for pipes on Windows, fall back to blocking reads there
		try {
			$ready = @stream_select($read, $write, $except, $seconds, $micro);
		} catch (ValueError $e) {
			$ready = false;
		}

		if ($ready === 0) {
			return;
		}

		$chunk = fgets($stream, 8192);

		if ($chunk === false || str_ends_with($chunk, "\n")) {
			return;
		}
	}
}

/**
 * script_server_split_request - split a request into include file, function and parameters
 *
 * @param string $input_string The trimmed request line
 *
 * @return array{0: string, 1: string, 2: string} The include file, function and parameter string
 */
function script_server_split_request(string $input_string) : array {
	$include_file = '';
	$function     = '';
	$parameters   = '';

	// pull off the parameters
	$i = 0;

	while (true) {
		$pos = strpos($input_string, ' ');

		if ($pos > 0) {
			switch ($i) {
				case 0:
					// cut off include file as first part of input string and keep rest for further parsing
					$include_file = trim(substr($input_string,0,$pos));
					$input_string = trim((string) strchr($input_string, ' ')) . ' ';

					break;
				case 1:
					// cut off function as second part of input string and keep rest for further parsing
					$function     = trim(substr($input_string,0,$pos), "' ");
					$input_string = trim((string) strchr($input_string, ' ')) . ' ';

					break;
				case 2:
					// take the rest as parameter(s) to the function stripped off previously
					$parameters = trim($input_string);

					break 2;
			}
		} else {
			break;
		}

		$i++;
	}

	return [$include_file, $function, $parameters];
}

/**
 * script_server_resolve_include - resolve and check a requested script file
 *
 * The path is checked on every request. PHP internals satisfy function_exists()
 * without loading any script, so the check cannot depend on the function being
 * undefined.
 *
 * @param string $include_file The requested script file
 * @param array  $roots        The allowed roots
 *
 * @return string|false The resolved path, or false when it is missing or outside the roots
 */
function script_server_resolve_include(string $include_file, array $roots) : string|false {
	$real_include = realpath($include_file);

	if ($real_include === false || !script_server_path_is_allowed($real_include, $roots)) {
		cacti_log("WARNING: Script file '" . basename($include_file) . "' is missing or outside the allowed script roots. Rejected.", false, 'PHPSVR');

		return false;
	}

	// quirk in php on Windows, believe it or not.... path must be lower case
	if (CACTI_SERVER_OS == 'win32') {
		$real_include = cacti_strtolower($real_include);
	}

	return $real_include;
}

/**
 * parseArgs - split a Script Server parameter string into arguments
 *
 * Follows POSIX shell quoting for the forms cacti_escapeshellarg() produces:
 * adjacent quoted and unquoted segments join into one argument, so the
 * sequence '\'' inside a single quoted argument yields an apostrophe. Inside
 * double quotes \" yields a quote, which is the Windows escaping form.
 *
 * @param string $string   The parameter string
 * @param array  $str_list The parsed arguments
 * @param bool   $debug    Print the input and output
 *
 * @return bool True when the string parsed cleanly
 */
function parseArgs(string $string, array &$str_list, bool $debug = false) : bool {
	$str_list = [];

	if ($debug) {
		print "String: '" . $string . "'\n";
	}

	// process the simple case
	if (!str_contains($string, "'") && !str_contains($string, '"')) {
		$str_list = explode(' ', $string);

		if ($debug) {
			print "Output: '" . implode(',', $str_list) . "'\n";
		}

		return true;
	}

	$length   = strlen($string);
	$quote    = '';
	$escaping = false;
	$in_word  = false;
	$curstr   = '';
	$msg      = '';

	for ($i = 0; $i < $length; $i++) {
		$char = $string[$i];

		if ($char == '`') {
			$msg = 'Backtick (`) characters not allowed';

			break;
		}

		if ($escaping) {
			// outside quotes only a quote or a backslash may be escaped
			if ($char != "'" && $char != '"' && $char != '\\') {
				$msg = 'Parse error attempting to parse string';

				break;
			}

			$curstr .= $char;
			$in_word  = true;
			$escaping = false;
		} elseif ($quote == "'") {
			if ($char == "'") {
				$quote = '';
			} else {
				$curstr .= $char;
			}
		} elseif ($quote == '"') {
			if ($char == '\\' && $i + 1 < $length && $string[$i + 1] == '"') {
				$curstr .= '"';
				$i++;
			} elseif ($char == '"') {
				$quote = '';
			} else {
				$curstr .= $char;
			}
		} elseif ($char == "'" || $char == '"') {
			$quote   = $char;
			$in_word = true;
		} elseif ($char == '\\') {
			$escaping = true;
		} elseif ($char == ' ') {
			if ($in_word) {
				$str_list[] = $curstr;
				$curstr     = '';
				$in_word    = false;
			}
		} else {
			$curstr .= $char;
			$in_word = true;
		}
	}

	if ($msg == '' && ($quote != '' || $escaping)) {
		$msg = 'Parse error attempting to parse string';
	}

	if ($msg != '') {
		// stdout carries the request replies, so errors only print when debugging
		if ($debug) {
			print 'ERROR: ' . $msg . "\n";
		}

		$str_list = [];

		return false;
	}

	if ($in_word) {
		$str_list[] = $curstr;
	}

	if ($debug) {
		print "Output: '" . implode(',', $str_list) . "'\n";
	}

	return true;
}

/**
 * script_server_redact_args - mask SNMP secrets in Script Server arguments
 *
 * Data query arguments carry a colon joined SNMP string of the form
 * version:port:timeout:retries:max_oids:community[:v3 fields]. Keep the first
 * five fields for troubleshooting and mask the rest.
 *
 * Known secrets are masked as well. Arguments are compared after parsing, so
 * the shell escaping a secret carries on the command line does not hide it.
 *
 * @param array $args    The parsed arguments
 * @param array $secrets Known secret values, such as the device credentials
 *
 * @return array The arguments safe for logging
 */
function script_server_redact_args(array $args, array $secrets = []) : array {
	$secrets = array_filter(array_map('strval', $secrets), fn (string $secret) : bool => $secret !== '');

	// longest first, so a secret contained in a longer one cannot leave part of it behind
	usort($secrets, fn ($a, $b) => strlen($b) <=> strlen($a));

	foreach ($args as $key => $arg) {
		if (!is_string($arg)) {
			continue;
		}

		if (preg_match('/^([0-3]:\d*:\d*:\d*:\d*):/', $arg, $matches)) {
			$args[$key] = $matches[1] . ':<redacted>';

			continue;
		}

		foreach ($secrets as $secret) {
			$arg = str_replace($secret, '<redacted>', $arg);
		}

		$args[$key] = $arg;
	}

	return $args;
}

/**
 * script_server_redact_command - mask SNMP secrets in a Script Server request
 *
 * @param string $command A request line or its parameter string
 * @param array  $secrets Known secret values, such as the device credentials
 *
 * @return string The request safe for logging
 */
function script_server_redact_command(string $command, array $secrets = []) : string {
	$args = [];

	if (!parseArgs($command, $args)) {
		return '<unparsable request>';
	}

	return implode(' ', script_server_redact_args($args, $secrets));
}

/**
 * script_server_allowed_roots - the directories Script Server functions may come from
 *
 * @return array The configured Cacti roots
 */
function script_server_allowed_roots() : array {
	return array_values(array_unique([
		CACTI_PATH_BASE,
		CACTI_PATH_SCRIPTS,
		CACTI_PATH_RESOURCE,
		CACTI_PATH_PLUGINS
	]));
}

/**
 * script_server_path_is_allowed - check that a resolved path lies below an allowed root
 *
 * @param string|false $resolved_path The realpath() result for the candidate file
 * @param array        $roots         The allowed roots
 *
 * @return bool True when the path is inside one of the roots
 */
function script_server_path_is_allowed(string|false $resolved_path, array $roots) : bool {
	static $normalized_roots = [];
	static $resolved_paths   = [];

	if ($resolved_path === false || $resolved_path === '') {
		return false;
	}

	$opath            = $resolved_path;
	$resolved_path    = rtrim(str_replace('\\', '/', $resolved_path), '/');
	$case_insensitive = (DIRECTORY_SEPARATOR === '\\');
	$cache_key        = implode("\0", $roots);

	// the decision depends on the root set too, so key the result by both
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
		} elseif (str_starts_with($resolved_path, $prefix)) {
			return $resolved_paths[$result_key] = true;
		}
	}

	return $resolved_paths[$result_key] = false;
}

/**
 * script_server_function_rejection - decide whether a function may be dispatched
 *
 * The Script Server only runs functions defined by script files in the Cacti
 * tree. PHP internals and functions declared anywhere else are refused.
 *
 * @param string $function The requested function name
 * @param array  $roots    The allowed roots
 *
 * @return string An empty string when allowed, otherwise the reason it was refused
 */
function script_server_function_rejection(string $function, array $roots) : string {
	if (!function_exists($function)) {
		return 'is not defined';
	}

	try {
		$ref = new ReflectionFunction($function);
	} catch (ReflectionException $e) {
		return 'is not introspectable';
	}

	if ($ref->isInternal()) {
		return 'is a PHP internal function';
	}

	$file = $ref->getFileName();

	if ($file === false || !script_server_path_is_allowed(realpath($file), $roots)) {
		return 'is defined outside the allowed script roots';
	}

	return '';
}

/**
 * script_server_dispatch - call a Script Server function and contain any failure
 *
 * One script throwing must not end the shared Script Server process, so any
 * Throwable becomes 'U'. The message is not logged because it may quote the
 * request parameters.
 *
 * @param string $function   The function to call
 * @param array  $parameters The arguments, empty to call without arguments
 *
 * @return string The trimmed result, or 'U' on failure
 */
function script_server_dispatch(string $function, array $parameters) : string {
	if (!is_callable($function)) {
		return 'U';
	}

	try {
		if (!cacti_sizeof($parameters)) {
			$result = call_user_func($function);
		} else {
			$result = call_user_func_array($function, $parameters);
		}
	} catch (Throwable $e) {
		cacti_log(sprintf("WARNING: Script Server function '%s' raised %s at %s:%d, returning U", $function, get_class($e), basename($e->getFile()), $e->getLine()), false, 'PHPSVR');

		return 'U';
	}

	if (is_array($result) || is_object($result)) {
		return 'U';
	}

	return trim((string) $result);
}

/**
 * script_server_run - check and dispatch one Script Server request
 *
 * @param string $function     The requested function
 * @param array  $parameters   The parsed arguments, empty to call without arguments
 * @param array  $roots        The allowed roots
 * @param string $include_file The script file the request named
 *
 * @return string The function result, or 'U' when refused or failed
 */
function script_server_run(string $function, array $parameters, array $roots, string $include_file) : string {
	$rejection = script_server_function_rejection($function, $roots);

	if ($rejection != '') {
		cacti_log("WARNING: Function '$function' $rejection. Rejected.  INC: '" . basename($include_file) . "'", false, 'PHPSVR');

		return 'U';
	}

	return script_server_dispatch($function, $parameters);
}
