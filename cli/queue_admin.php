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

$queue   = 'default';
$action  = 'health';
$message = '';
$limit   = 50;

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
		case '--health':
			$action = 'health';

			break;
		case '--dead':
			$action = 'dead';

			break;
		case '--limit':
			if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 500) {
				queue_admin_error('--limit must be an integer between 1 and 500.');
			}

			$limit = (int) $value;

			break;
		case '--requeue':
			if (!api_queue_is_message_id($value)) {
				queue_admin_error('--requeue requires a 32 character lowercase hexadecimal message ID.');
			}

			$action  = 'requeue';
			$message = $value;

			break;
		case '--purge':
			$action = 'purge';

			break;
		case '--version':
		case '-V':
		case '-v':
			queue_admin_display_version();

			exit(0);
		case '--help':
		case '-H':
		case '-h':
			queue_admin_help();

			exit(0);
		default:
			queue_admin_error('Invalid parameter ' . clean_up_lines($parameter));
	}
}

try {
	api_queue_validate_name($queue, 'queue');
	$transport = api_queue_transport($queue);

	if (!$transport instanceof CactiQueueAdminTransportInterface) {
		throw new RuntimeException("Queue '$queue' uses a transport without Cacti queue administration support.");
	}

	if ($action === 'health') {
		print api_queue_json_encode($transport->health()) . PHP_EOL;
	} elseif ($action === 'dead') {
		print api_queue_json_encode($transport->dead($limit)) . PHP_EOL;
	} elseif ($action === 'requeue') {
		$transport->requeue($message);
		print "Requeued $message." . PHP_EOL;
	} elseif ($action === 'purge') {
		print api_queue_json_encode($transport->purge(api_queue_completed_retention_days(), api_queue_dead_retention_days())) . PHP_EOL;
	}
} catch (Throwable $e) {
	queue_admin_error((string) clean_up_lines($e->getMessage()));
}

/**
 * Print an error and exit with status 1.
 *
 * @param string $message The error description
 *
 * @return never
 */
function queue_admin_error(string $message) : never {
	print 'ERROR: ' . $message . PHP_EOL;

	exit(1);
}

/**
 * Print the administrator usage.
 *
 * @return void
 */
function queue_admin_help() : void {
	queue_admin_display_version();

	print PHP_EOL;
	print 'usage: queue_admin.php [--queue=name] [--health|--dead|--requeue=id|--purge] [--limit=50]' . PHP_EOL . PHP_EOL;
	print 'Checks queue health and administers dead or completed messages.' . PHP_EOL;
}

/**
 * Print the administrator version banner.
 *
 * @return void
 */
function queue_admin_display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Queue Administrator, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}
