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

// Publish one message through the same API used by production producers.

require dirname(__DIR__, 3) . '/include/cli_check.php';
require_once CACTI_PATH_LIBRARY . '/api_queue.php';

$value = (string) ($argv[1] ?? '');

if (!preg_match('/^[a-z0-9_-]{1,32}$/', $value)) {
	fwrite(STDERR, "Producer value is invalid.\n");

	exit(1);
}

$message = api_queue_publish('queue-e2e.record', ['value' => $value], [
	'queue'        => 'queue-e2e',
	'max_attempts' => 3,
]);

print api_queue_message_id_from_envelope($message) . PHP_EOL;
