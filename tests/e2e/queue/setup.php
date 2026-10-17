#!/usr/bin/env php
<?php
// Prepare the disposable database and install the queue E2E handler hook.

require dirname(__DIR__, 3) . '/include/cli_check.php';

if ($database_default !== 'cacti_queue_e2e') {
	fwrite(STDERR, "Refusing to prepare a non-E2E database.\n");

	exit(1);
}

$queries = [
	'DROP TABLE IF EXISTS queue_e2e_results',
	'CREATE TABLE queue_e2e_results (
		message_id char(36) NOT NULL,
		payload blob NOT NULL,
		handled_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (message_id)
	) ENGINE=InnoDB',
	"DELETE FROM plugin_hooks WHERE name = 'queue_e2e'",
	"DELETE FROM plugin_config WHERE directory = 'queue_e2e'",
	"INSERT INTO plugin_config
		(directory, name, status, author, webpage, version)
		VALUES ('queue_e2e', 'Queue E2E', 1, 'Cacti', '', '1.0')",
	"INSERT INTO plugin_hooks
		(name, hook, file, `function`, status)
		VALUES ('queue_e2e', 'queue_handlers', 'queue.php', 'queue_e2e_handlers', 1)",
];

foreach ($queries as $index => $query) {
	if (!db_execute_prepared($query)) {
		$error = (string) ($GLOBALS['database_last_error'] ?? 'unknown database error');
		fwrite(STDERR, sprintf("Unable to prepare the queue E2E database at statement %d: %s\n", $index + 1, $error));

		exit(1);
	}
}
