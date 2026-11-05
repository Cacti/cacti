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
 * @param float $drain_wait Seconds to wait for the remainder of a started line
 *
 * @return array{0: string|false, 1: bool, 2: bool} The line, whether it was overlong, and whether framing remains healthy
 */
function script_server_read_request(mixed $stream, int $max_length = SCRIPT_SERVER_MAX_REQUEST_LENGTH, float $drain_wait = 0.5) : array {
	$read   = [$stream];
	$write  = null;
	$except = null;

	try {
		$ready = @stream_select($read, $write, $except, null);
	} catch (Throwable $e) {
		$ready = false;
	}

	$metadata     = stream_get_meta_data($stream);
	$was_blocking = $metadata['blocked'];
	$selectable   = $ready !== false;

	// Windows anonymous pipes and some in-memory streams cannot be selected,
	// but they can normally be polled in non-blocking mode. Retain the legacy
	// bounded read only for a stream that cannot be made non-blocking at all.
	if (!@stream_set_blocking($stream, false)) {
		return script_server_read_request_blocking($stream, $max_length, $drain_wait);
	}

	$line     = '';
	$deadline = $selectable ? hrtime(true) + (int) ($drain_wait * 1000000000) : 0;

	try {
		while (true) {
			// Room for the request and its CR LF. fgets() stops at one LF, so
			// it cannot consume bytes belonging to the following request.
			$chunk = fgets($stream, max(2, $max_length + 3 - strlen($line)));

			if ($chunk !== false && $chunk !== '') {
				$line .= $chunk;

				if ($deadline === 0) {
					$deadline = hrtime(true) + (int) ($drain_wait * 1000000000);
				}

				if (str_ends_with($line, "\n")) {
					return [$line, strlen(rtrim($line, "\r\n")) > $max_length, true];
				}

				if (strlen($line) >= $max_length + 2) {
					return [$line, true, script_server_drain_line($stream, $drain_wait)];
				}

				continue;
			}

			if (feof($stream)) {
				return $line === '' ? [false, false, true] : [$line, true, false];
			}

			// An unselectable pipe waits indefinitely for its first byte. Once
			// a request starts, the line-completion deadline above applies.
			if ($deadline === 0) {
				usleep(10000);

				continue;
			}

			$remaining = $deadline - hrtime(true);

			if ($remaining <= 0) {
				return [$line, true, false];
			}

			$read         = [$stream];
			$write        = null;
			$except       = null;
			$seconds      = intdiv($remaining, 1000000000);
			$microseconds = intdiv($remaining % 1000000000, 1000);

			try {
				$ready = @stream_select($read, $write, $except, $seconds, $microseconds);
			} catch (Throwable $e) {
				$ready = false;
			}

			if ($ready === 0) {
				return [$line, true, false];
			}

			if ($ready === false) {
				usleep((int) min(10000, max(1, intdiv($remaining, 1000))));
			}
		}
	} finally {
		@stream_set_blocking($stream, $was_blocking);
	}
}

/**
 * Fall back for stream types that cannot use stream_select().
 *
 * @param mixed $stream     The input stream
 * @param int   $max_length The longest accepted request
 * @param float $drain_wait Seconds to drain an overlong request
 *
 * @return array{0: string|false, 1: bool, 2: bool}
 */
function script_server_read_request_blocking(mixed $stream, int $max_length, float $drain_wait) : array {
	$line = fgets($stream, max(1, $max_length + 3));

	if ($line === false) {
		return [false, false, true];
	}

	if (!str_ends_with($line, "\n") && feof($stream)) {
		return [$line, true, false];
	}

	if (str_ends_with($line, "\n") || strlen($line) < $max_length + 2) {
		return [$line, strlen(rtrim($line, "\r\n")) > $max_length, true];
	}

	$framing_healthy = script_server_drain_line($stream, $drain_wait);

	return [$line, true, $framing_healthy];
}

/**
 * script_server_normalize_request - validate and remove one line terminator
 *
 * @param string $request The raw request read from the protocol stream
 *
 * @return string|false The trimmed request, or false when it contains controls
 */
function script_server_normalize_request(string $request) : string|false {
	if (str_ends_with($request, "\n")) {
		$request = substr($request, 0, -1);

		if (str_ends_with($request, "\r")) {
			$request = substr($request, 0, -1);
		}
	}

	if (preg_match('/[\x00-\x1F\x7F]/', $request)) {
		return false;
	}

	return trim($request);
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
 * @return bool True when the rest of the line was consumed through LF
 */
function script_server_drain_line(mixed $stream, float $wait) : bool {
	$metadata     = stream_get_meta_data($stream);
	$was_blocking = $metadata['blocked'];
	$nonblocking  = @stream_set_blocking($stream, false);
	$deadline     = hrtime(true) + (int) ($wait * 1000000000);

	try {
		while (true) {
			$remaining = $deadline - hrtime(true);

			if ($remaining <= 0) {
				return false;
			}

			$read    = [$stream];
			$write   = null;
			$except  = null;
			$seconds = intdiv($remaining, 1000000000);
			$micro   = intdiv($remaining % 1000000000, 1000);

			// Windows pipes cannot be selected; their fallback must remain non-blocking.
			try {
				$ready = @stream_select($read, $write, $except, $seconds, $micro);
			} catch (Throwable $e) {
				$ready = false;
			}

			if ($ready === 0 || ($ready === false && !$nonblocking)) {
				return false;
			}

			$chunk = fgets($stream, 8192);

			if ($chunk === false) {
				return false;
			}

			if (str_ends_with($chunk, "\n")) {
				return true;
			}
		}
	} finally {
		if ($nonblocking) {
			@stream_set_blocking($stream, $was_blocking);
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
					$function     = trim(substr($input_string,0,$pos));

					if (strlen($function) >= 2 && str_starts_with($function, "'") && str_ends_with($function, "'")) {
						$function = substr($function, 1, -1);
					}

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
 * script_server_function_name_is_valid - validate a PHP function token
 *
 * Namespaced user functions and the legacy leading namespace separator are
 * accepted.  Method syntax and malformed tokens are rejected before a script
 * is loaded, so an invalid request cannot trigger top-level script code.
 *
 * @param string $function The requested function name
 *
 * @return bool True when the token can name a PHP function
 */
function script_server_function_name_is_valid(string $function) : bool {
	return preg_match('/^\\\\?(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\\\\)*[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', $function) === 1;
}

/**
 * script_server_validate_request - validate one complete request before load
 *
 * @param string $request The normalized request line
 * @param array  $roots   Canonical roots from which scripts may be loaded
 *
 * @return array{0: string, 1: string, 2: array}|false Canonical script, function, and arguments
 */
function script_server_validate_request(string $request, array $roots) : array|false {
	[$include_file, $function, $parameters] = script_server_split_request($request);

	if ($include_file === '' || !script_server_function_name_is_valid($function)) {
		return false;
	}

	$parameter_array = [];

	if ($parameters !== '' && !parseArgs($parameters, $parameter_array)) {
		return false;
	}

	$include_file = script_server_resolve_include($include_file, $roots);

	if ($include_file === false) {
		return false;
	}

	return [$include_file, $function, $parameter_array];
}

/**
 * script_server_log_token - bound and sanitize one value used in a log context
 *
 * @param string $value The untrusted value
 *
 * @return string A single safe log token
 */
function script_server_log_token(string $value) : string {
	$value = preg_replace('/[^A-Za-z0-9_.:@+\\\\-]/', '?', $value);

	return substr($value ?? '', 0, 128);
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

	if ($real_include === false || !is_file($real_include) || strtolower(pathinfo($real_include, PATHINFO_EXTENSION)) !== 'php' || !script_server_path_is_allowed($real_include, $roots)) {
		cacti_log("WARNING: Script file '" . script_server_log_token(basename($include_file)) . "' is missing or outside the allowed script roots. Rejected.", false, 'PHPSVR');

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

	if (str_contains($string, '`')) {
		if ($debug) {
			print "ERROR: Backtick (`) characters not allowed\n";
		}

		return false;
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
		CACTI_PATH_SCRIPTS,
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
	static $last_roots       = [];
	static $last_cache_key   = '';

	if ($resolved_path === false || $resolved_path === '') {
		return false;
	}

	$opath            = $resolved_path;
	$resolved_path    = rtrim(str_replace('\\', '/', $resolved_path), '/');
	$case_insensitive = (DIRECTORY_SEPARATOR === '\\');

	if ($roots === $last_roots) {
		$cache_key = $last_cache_key;
	} else {
		$last_roots     = $roots;
		$last_cache_key = implode("\0", $roots);
		$cache_key      = $last_cache_key;
	}

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

	// Do not retain rejected attacker-controlled paths in a long-lived worker.
	return false;
}

/**
 * script_server_function_rejection - decide whether a function may be dispatched
 *
 * The Script Server only runs a function defined by the requested script.
 * PHP internals and functions loaded by Cacti or another script are refused.
 *
 * @param string $function     The requested function name
 * @param string $include_file The resolved script file named by the request
 *
 * @return string An empty string when allowed, otherwise the reason it was refused
 */
function script_server_function_rejection(string $function, string $include_file) : string {
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

	$file             = $ref->getFileName();
	$resolved_file    = ($file === false ? false : realpath($file));
	$resolved_include = realpath($include_file);

	if ($resolved_file === false || $resolved_include === false) {
		return 'does not have a resolvable source file';
	}

	if (DIRECTORY_SEPARATOR === '\\') {
		$matches = strcasecmp($resolved_file, $resolved_include) === 0;
	} else {
		$matches = $resolved_file === $resolved_include;
	}

	if (!$matches) {
		return 'is not defined by the requested script file';
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
 * @param string $function         The function to call
 * @param array  $parameters       The arguments, empty to call without arguments
 * @param bool   $response_healthy Set false when the result cannot be safely framed
 *
 * @return string The trimmed result, or 'U' on failure
 */
function script_server_dispatch(string $function, array $parameters, bool &$response_healthy = true) : string {
	$response_healthy = true;

	if (!is_callable($function)) {
		return 'U';
	}

	try {
		$result = $function(...$parameters);
	} catch (Throwable $e) {
		cacti_log(sprintf("WARNING: Script Server function '%s' raised %s at %s:%d, returning U", $function, get_class($e), basename($e->getFile()), $e->getLine()), false, 'PHPSVR');

		return 'U';
	}

	return script_server_normalize_response($result, $response_healthy);
}

/**
 * script_server_normalize_response - enforce the one-line response contract
 *
 * @param mixed $result           The script function result
 * @param bool  $response_healthy Set false for a framing or length violation
 *
 * @return string A bounded response, or U when the result cannot be framed
 */
function script_server_normalize_response(mixed $result, bool &$response_healthy = true) : string {
	$response_healthy = true;

	if (is_array($result) || is_object($result) || is_resource($result)) {
		return 'U';
	}

	$result = (string) $result;

	if (preg_match('/[\x00-\x1F\x7F]/', $result)) {
		$response_healthy = false;

		return 'U';
	}

	$result = trim($result);

	if ($result === '') {
		return 'U';
	}

	if (strlen($result) > SCRIPT_SERVER_MAX_RESPONSE_LENGTH) {
		$response_healthy = false;

		return 'U';
	}

	return $result;
}

/**
 * script_server_start_output_guard - discard output outside framed replies
 *
 * Script functions may print, flush, or create nested output buffers. A
 * persistent non-removable buffer keeps that output off the protocol stream;
 * replies use fwrite(STDOUT) and bypass PHP output buffering.
 *
 * @return int The guard's output-buffer level, or zero when it could not start
 */
function script_server_start_output_guard() : int {
	static $guard_level = 0;

	if ($guard_level > 0) {
		return $guard_level;
	}

	$flags = PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;

	if (!ob_start(static fn (string $buffer) : string => '', 4096, $flags)) {
		return 0;
	}

	return $guard_level = ob_get_level();
}

/**
 * script_server_reset_output_guard - discard script-created output buffers
 *
 * @param int $guard_level The persistent guard's output-buffer level
 *
 * @return bool True when only the clean guard remains
 */
function script_server_reset_output_guard(int $guard_level) : bool {
	if ($guard_level <= 0) {
		return false;
	}

	while (ob_get_level() > $guard_level) {
		if (!@ob_end_clean()) {
			return false;
		}
	}

	if (ob_get_level() !== $guard_level) {
		return false;
	}

	return ob_get_length() === 0 || @ob_clean();
}

/**
 * script_server_run - check and dispatch one Script Server request
 *
 * @param string $function         The requested function
 * @param array  $parameters       The parsed arguments, empty to call without arguments
 * @param string $include_file     The script file the request named
 * @param bool   $response_healthy Set false when the result cannot be safely framed
 *
 * @return string The function result, or 'U' when refused or failed
 */
function script_server_run(string $function, array $parameters, string $include_file, bool &$response_healthy = true) : string {
	$response_healthy = true;
	$rejection        = script_server_function_rejection($function, $include_file);

	if ($rejection != '') {
		$safe_function = script_server_log_token($function);
		$safe_include  = script_server_log_token(basename($include_file));
		cacti_log("WARNING: Function '$safe_function' $rejection. Rejected.  INC: '$safe_include'", false, 'PHPSVR');

		return 'U';
	}

	return script_server_dispatch($function, $parameters, $response_healthy);
}
