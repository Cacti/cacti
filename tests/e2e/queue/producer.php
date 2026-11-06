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

// Publish one message through the same API used by production producers.

require dirname(__DIR__, 3) . '/include/cli_check.php';

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
