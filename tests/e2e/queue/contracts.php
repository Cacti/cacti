<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
require dirname(__DIR__, 3) . '/include/cli_check.php';

if ($database_default !== 'cacti_queue_e2e') {
	throw new RuntimeException('Queue contracts require the disposable E2E database.');
}

function queue_e2e_command(string $script, array $arguments, int $status, string $expected = '') : string {
	$process = new Symfony\Component\Process\Process([PHP_BINARY, CACTI_PATH_BASE . '/cli/' . $script, ...$arguments]);
	$process->setTimeout(15);
	$process->run();

	if ($process->getExitCode() !== $status || !str_contains($process->getOutput(), $expected)) {
		throw new RuntimeException($script . ' contract failed: ' . $process->getOutput() . $process->getErrorOutput());
	}

	return $process->getOutput();
}

queue_e2e_command('queue_admin.php', ['--help'], 0, 'usage:');
queue_e2e_command('queue_admin.php', ['--version'], 0, 'Version');
queue_e2e_command('queue_admin.php', ['--invalid'], 1, 'Invalid parameter');
queue_e2e_command('queue_admin.php', ['--requeue=invalid'], 1, 'canonical lowercase UUID');
queue_e2e_command('queue_admin.php', ['--queue=invalid queue'], 1, 'ERROR:');
queue_e2e_command('queue_admin.php', ['--queue=queue-e2e', '--dead', '--limit=500'], 0, '[]');
queue_e2e_command('queue_admin.php', ['--queue=queue-e2e', '--purge'], 0, '"completed":0');
queue_e2e_command('queue_worker.php', ['--help'], 0, 'usage:');
queue_e2e_command('queue_worker.php', ['--version'], 0, 'Version');
queue_e2e_command('queue_worker.php', ['--invalid'], 1, 'Invalid parameter');
queue_e2e_command('queue_worker.php', ['--sleep=invalid', '--once'], 1, 'integer value');
queue_e2e_command('queue_worker.php', ['--queue=invalid queue', '--once'], 1, 'ERROR:');

$envelope  = api_queue_publish('queue-e2e.fail', [], ['queue' => 'queue-e2e', 'max_attempts' => 1]);
$messageId = api_queue_message_id_from_envelope($envelope);
queue_e2e_command('queue_worker.php', ['--queue=queue-e2e', '--lease=30', '--sleep=1', '--time-limit=1', '--once'], 1);
queue_e2e_command('queue_admin.php', ['--queue=queue-e2e', '--dead', '--limit=1'], 0, $messageId);
queue_e2e_command('queue_admin.php', ['--queue=queue-e2e', '--requeue=' . $messageId], 0, 'Requeued');

if (db_fetch_cell_prepared('SELECT status FROM queue_messages WHERE message_id = ?', [$messageId]) !== 'pending') {
	throw new RuntimeException('The dead-letter message was not requeued.');
}
queue_e2e_command('queue_worker.php', ['--queue=queue-e2e', '--once'], 1);
db_execute_prepared('UPDATE queue_messages SET completed_at = DATE_SUB(NOW(), INTERVAL 91 DAY) WHERE message_id = ?', [$messageId]);
queue_e2e_command('queue_admin.php', ['--queue=queue-e2e', '--purge'], 0, '"dead":1');
print "Queue CLI failure, dead-letter, requeue, and retention contracts passed.\n";
