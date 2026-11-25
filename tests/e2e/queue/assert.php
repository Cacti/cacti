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

// Verify handler output and transport-owned acknowledgement state.

require dirname(__DIR__, 3) . '/include/cli_check.php';
require_once CACTI_PATH_LIBRARY . '/api_queue.php';

$results = db_fetch_assoc_prepared('SELECT message_id, payload
	FROM queue_e2e_results
	ORDER BY payload');
$messages = db_fetch_assoc_prepared('SELECT message_id, status, attempts, reservation_token
	FROM queue_messages
	WHERE queue_name = ?
	ORDER BY message_id',
	['queue-e2e']);

if (!is_array($results) || !is_array($messages) || count($results) !== 2 || count($messages) !== 2) {
	fwrite(STDERR, "Expected exactly two handled and acknowledged messages.\n");

	exit(1);
}

$values = [];

foreach ($results as $result) {
	$payload  = api_queue_json_decode($result['payload']);
	$values[] = $payload['value'] ?? null;
}

sort($values);

if ($values !== ['alpha', 'beta']) {
	fwrite(STDERR, "Handler payloads did not match the published messages.\n");

	exit(1);
}

foreach ($messages as $message) {
	if ($message['status'] !== 'completed' || (int) $message['attempts'] !== 1 || $message['reservation_token'] !== null) {
		fwrite(STDERR, "A queue message was not acknowledged exactly once.\n");

		exit(1);
	}
}

print "Queue Docker E2E passed: two concurrent workers handled and acknowledged two messages.\n";
