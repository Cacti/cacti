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

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require(__DIR__ . '/../include/cli_check.php');

if (!defined('CACTI_CLI') || !CACTI_CLI) {
	exit(1);
}

require_once(CACTI_PATH_LIBRARY . '/api_queue.php');

$queue      = 'default';
$once       = false;
$sleep      = 2;
$time_limit = 0;
$lease      = api_queue_lease_seconds();
$failed     = false;

$parameters = $_SERVER['argv'];
array_shift($parameters);

foreach ($parameters as $parameter) {
	if (str_contains($parameter, '=')) {
		[$argument, $value] = explode('=', $parameter, 2);
	} else {
		$argument = $parameter;
		$value    = '';
	}

	switch ($argument) {
		case '--queue':
			$queue = $value;

			break;
		case '--sleep':
			$sleep = queue_worker_parse_int('--sleep', $value, 1, 60);

			break;
		case '--time-limit':
			$time_limit = queue_worker_parse_int('--time-limit', $value, 0, 86400);

			break;
		case '--lease':
			$lease = queue_worker_parse_int('--lease', $value, 30, 86400);

			break;
		case '--once':
			$once = true;

			break;
		case '--version':
		case '-V':
		case '-v':
			queue_worker_display_version();

			exit(0);
		case '--help':
		case '-H':
		case '-h':
			queue_worker_display_help();

			exit(0);
		default:
			print 'ERROR: Invalid parameter ' . clean_up_lines($parameter) . PHP_EOL . PHP_EOL;
			queue_worker_display_help();

			exit(1);
	}
}

try {
	api_queue_validate_name($queue, 'queue');
	api_queue_set_lease_seconds($lease);
	$worker = new CactiQueueWorker(new CactiQueueReceiver($queue, api_queue_transport($queue)));
} catch (Throwable $e) {
	print 'ERROR: ' . clean_up_lines($e->getMessage()) . PHP_EOL;

	exit(1);
}

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
	pcntl_async_signals(true);
	pcntl_signal(SIGTERM, static function () use ($worker) : void {
		$worker->stop();
	});
	pcntl_signal(SIGINT, static function () use ($worker) : void {
		$worker->stop();
	});
}

try {
	$worker->run($once, $sleep, $time_limit);
} catch (Throwable $e) {
	api_queue_log('Queue worker failed: ' . $e->getMessage());
	$failed = true;
}

exit($failed || $worker->failed() ? 1 : 0);

/**
 * Print the worker version banner.
 *
 * @return void
 */
function queue_worker_display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Queue Worker, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * Print the worker usage.
 *
 * @return void
 */
function queue_worker_display_help() : void {
	queue_worker_display_version();

	print PHP_EOL;
	print 'usage: queue_worker.php [--queue=name] [--once] [--sleep=seconds] [--time-limit=seconds] [--lease=seconds]' . PHP_EOL . PHP_EOL;
	print 'Consumes registered handlers from a configured queue transport.' . PHP_EOL;
}

/**
 * Parse a bounded integer option, or print usage and exit.
 *
 * @param string $argument The option name, for messages
 * @param string $value    The supplied value
 * @param int    $minimum  The smallest accepted value
 * @param int    $maximum  The largest accepted value
 *
 * @return int The parsed value
 */
function queue_worker_parse_int(string $argument, string $value, int $minimum, int $maximum) : int {
	if (filter_var($value, FILTER_VALIDATE_INT) === false) {
		print "ERROR: $argument requires an integer value." . PHP_EOL;
		queue_worker_display_help();

		exit(1);
	}

	$number = (int) $value;

	if ($number < $minimum || $number > $maximum) {
		print "ERROR: $argument must be between $minimum and $maximum." . PHP_EOL;
		queue_worker_display_help();

		exit(1);
	}

	return $number;
}
