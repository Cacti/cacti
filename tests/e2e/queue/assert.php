#!/usr/bin/env php
<?php
// Verify handler output and transport-owned acknowledgement state.

require dirname(__DIR__, 3) . '/include/cli_check.php';

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
