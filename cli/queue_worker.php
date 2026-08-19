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
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
$stop       = false;
$failed     = false;
$failures   = 0;

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
	$transport = api_queue_transport($queue);
} catch (Throwable $e) {
	print 'ERROR: ' . clean_up_lines($e->getMessage()) . PHP_EOL;

	exit(1);
}

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
	pcntl_async_signals(true);
	pcntl_signal(SIGTERM, static function () use (&$stop) : void {
		$stop = true;
	});
	pcntl_signal(SIGINT, static function () use (&$stop) : void {
		$stop = true;
	});
}

$started = time();

do {
	$received = false;

	try {
		foreach ($transport->get() as $envelope) {
			$received = true;
			$failures = 0;

			try {
				api_queue_dispatch($envelope);
			} catch (Throwable $e) {
				try {
					$transport->reject($envelope, $e->getMessage());
				} catch (CactiQueueStaleReceiptException $reject_error) {
					queue_worker_log("Queue receipt expired before rejection: {$reject_error->getMessage()}");
				} catch (Throwable $reject_error) {
					queue_worker_log("Queue rejection failed: {$reject_error->getMessage()}");
					$stop   = true;
					$failed = true;
				}

				queue_worker_log("Queue '{$envelope->queue()}' topic '{$envelope->topic()}' failed: {$e->getMessage()}");

				continue;
			}

			try {
				$transport->ack($envelope);
			} catch (CactiQueueStaleReceiptException $e) {
				queue_worker_log("Queue receipt expired before acknowledgement: {$e->getMessage()}");
			} catch (Throwable $e) {
				queue_worker_log("Queue acknowledgement failed; its lease will expire for visible redelivery: {$e->getMessage()}");
			}
		}
	} catch (Throwable $e) {
		queue_worker_log('Queue receive failed: ' . $e->getMessage());

		if ($e instanceof CactiQueueMessageException) {
			$failures = 0;
		} else {
			$failures++;

			if ($once || $failures >= 5) {
				$stop   = true;
				$failed = true;
			}
		}
	}

	if (!$once && !$received && !$stop && ($time_limit === 0 || time() - $started < $time_limit)) {
		sleep($sleep);
	}
} while (!$once && !$stop && ($time_limit === 0 || time() - $started < $time_limit));

exit($failed ? 1 : 0);

function queue_worker_display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Queue Worker, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function queue_worker_display_help() : void {
	queue_worker_display_version();

	print PHP_EOL;
	print 'usage: queue_worker.php [--queue=name] [--once] [--sleep=seconds] [--time-limit=seconds] [--lease=seconds]' . PHP_EOL . PHP_EOL;
	print 'Consumes registered handlers from a configured queue transport.' . PHP_EOL;
}

function queue_worker_log(string $message) : void {
	cacti_log((string) clean_up_lines($message), false, 'QUEUE');
}

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
