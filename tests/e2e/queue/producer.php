#!/usr/bin/env php
<?php
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

print $message->messageId() . PHP_EOL;
