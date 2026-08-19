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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/api_queue.php';

beforeEach(function () {
	$dsn = getenv('CACTI_QUEUE_TEST_DSN');

	if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
		test()->markTestSkipped('Set CACTI_QUEUE_TEST_DSN to a dedicated MySQL/MariaDB test database.');
	}

	preg_match('/host=([^;]+)/', $dsn, $host_match);
	preg_match('/port=([^;]+)/', $dsn, $port_match);
	preg_match('/dbname=([^;]+)/', $dsn, $database_match);
	$host     = $host_match[1] ?? '127.0.0.1';
	$port     = $port_match[1] ?? '3306';
	$database = $database_match[1] ?? 'cacti_queue_test';

	if (!preg_match('/(?:^|_)test(?:_|$)/i', $database)) {
		throw new RuntimeException('CACTI_QUEUE_TEST_DSN must name a dedicated test database.');
	}

	$username = (string) (getenv('CACTI_QUEUE_TEST_USER') ?: 'root');
	$password = (string) (getenv('CACTI_QUEUE_TEST_PASSWORD') ?: '');
	$this->db = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

	$GLOBALS['database_hostname']                          = $host;
	$GLOBALS['database_port']                              = $port;
	$GLOBALS['database_default']                           = $database;
	$GLOBALS['database_sessions']["$host:$port:$database"] = $this->db;
	$GLOBALS['config']                                     = ['DEBUG_SQL_CONNECT' => false, 'DEBUG_SQL_CMD' => false];
	$GLOBALS['database_total_queries']                     = 0;
	$GLOBALS['database_log']                               = false;
	$GLOBALS['error_logged']                               = [];
	$GLOBALS['affected_rows']                              = [];
	$GLOBALS['database_details']                           = [];

	$table_exists = $this->db->query("SHOW TABLES LIKE 'queue_messages'")->fetchColumn();

	if ($table_exists !== false) {
		$rows = (int) $this->db->query('SELECT COUNT(*) FROM queue_messages')->fetchColumn();

		if ($rows > 0) {
			throw new RuntimeException('Refusing to replace a non-empty queue_messages table.');
		}

		$this->db->exec('DROP TABLE queue_messages');
	}

	$this->db->exec("CREATE TABLE queue_messages (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		message_id char(36) NOT NULL,
		queue_name varchar(64) NOT NULL,
		topic varchar(128) NOT NULL,
		payload longblob NOT NULL,
		metadata blob NOT NULL,
		status varchar(16) NOT NULL default 'pending',
		priority tinyint unsigned NOT NULL default 50,
		available_at timestamp NOT NULL default current_timestamp(),
		reserved_until timestamp NULL default NULL,
		reservation_token char(48) default NULL,
		attempts smallint unsigned NOT NULL default 0,
		max_attempts smallint unsigned NOT NULL default 5,
		last_error text,
		created_at timestamp NOT NULL default current_timestamp(),
		completed_at timestamp NULL default NULL,
		PRIMARY KEY (id),
		UNIQUE KEY message_id (message_id),
		KEY queue_ready (queue_name,status,priority,available_at,id),
		KEY queue_terminal (queue_name,status,completed_at),
		KEY status_completed (status,completed_at),
		KEY reservation_token (reservation_token)
	) ENGINE=InnoDB");
});

afterEach(function () {
	if (isset($this->db) && $this->db instanceof PDO) {
		$this->db->exec('DROP TABLE IF EXISTS queue_messages');
	}
});

it('returns no messages when the queue is idle', function () {
	$transport = new CactiDatabaseQueueTransport('reports', 60);

	expect(queue_test_messages($transport->get()))->toBe([]);
});

it('claims lower numeric priorities first', function () {
	$transport = new CactiDatabaseQueueTransport('reports', 60);

	foreach ([90, 10, 50] as $index => $priority) {
		$transport->send(new CactiQueueEnvelope(
			sprintf('550e8400-e29b-41d4-a716-%012d', 100 + $index),
			'reports',
			'reports.generate',
			[],
			['priority' => $priority]
		));
	}

	$claimed = [];

	while ($messages = queue_test_messages($transport->get())) {
		$claimed[] = $messages[0]->metadata()['priority'];
		$transport->ack($messages[0]);
	}

	expect($claimed)->toBe([10, 50, 90]);
});

it('claims, renews, rejects stale receipts, and acknowledges a message', function () {
	$transport = new CactiDatabaseQueueTransport('reports', 60);
	$transport->send(new CactiQueueEnvelope(
		'550e8400-e29b-41d4-a716-446655440000',
		'reports',
		'reports.generate',
		['report_id' => 42],
		['max_attempts' => 3]
	));

	$messages = queue_test_messages($transport->get());
	expect($messages)->toHaveCount(1)
		->and($messages[0]->metadata()['attempt'])->toBe(1)
		->and(queue_test_messages($transport->get()))->toBe([]);

	$transport->touch($messages[0], 120);
	$stale = $messages[0]->withReceipt((int) $messages[0]->receiptId(), str_repeat('0', 48), 1);
	expect(fn () => $transport->ack($stale))->toThrow(RuntimeException::class, 'stale');
	expect(fn () => $transport->retry($stale, 1, 'failed'))->toThrow(CactiQueueStaleReceiptException::class, 'stale');

	$transport->ack($messages[0]);
	$row = $this->db->query('SELECT status, payload FROM queue_messages')->fetch(PDO::FETCH_ASSOC);
	expect($row['status'])->toBe('completed')
		->and(json_decode($row['payload'], true))->toBe(['report_id' => 42]);

	$this->db->exec('UPDATE queue_messages SET completed_at = DATE_SUB(NOW(), INTERVAL 2 DAY)');
	expect($transport->purge(1, 90))->toBe(['completed' => 1, 'dead' => 0]);
});

it('dead-letters exhausted and corrupt messages and can requeue them', function () {
	$transport = new CactiDatabaseQueueTransport('reports', 60);
	$transport->send(new CactiQueueEnvelope(
		'550e8400-e29b-41d4-a716-446655440001',
		'reports',
		'reports.generate',
		[],
		['max_attempts' => 1]
	));
	$message = queue_test_messages($transport->get())[0];
	$transport->reject($message, 'handler failed');

	expect($this->db->query('SELECT status FROM queue_messages')->fetchColumn())->toBe('dead')
		->and($transport->dead())->toHaveCount(1);

	$transport->requeue($message->messageId());
	expect($this->db->query('SELECT status FROM queue_messages')->fetchColumn())->toBe('pending');

	$this->db->exec('DELETE FROM queue_messages');
	$this->db->exec("INSERT INTO queue_messages
		(message_id, queue_name, topic, payload, metadata, status, available_at)
		VALUES ('550e8400-e29b-41d4-a716-446655440002', 'reports', 'reports.generate', '{', '{}', 'pending', NOW())");
	expect(fn () => $transport->get())->toThrow(CactiQueueMessageException::class);
	expect($this->db->query('SELECT status FROM queue_messages')->fetchColumn())->toBe('dead');
});

it('reclaims an expired lease and reaps it after exhausting attempts', function () {
	$transport = new CactiDatabaseQueueTransport('reports', 60);
	$transport->send(new CactiQueueEnvelope(
		'550e8400-e29b-41d4-a716-446655440003',
		'reports',
		'reports.generate',
		[],
		['max_attempts' => 2]
	));
	queue_test_messages($transport->get());
	$this->db->exec('UPDATE queue_messages SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 SECOND)');
	$reclaimed = queue_test_messages($transport->get());
	expect($reclaimed)->toHaveCount(1)
		->and($reclaimed[0]->metadata()['attempt'])->toBe(2);

	$this->db->exec('UPDATE queue_messages SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 SECOND)');
	expect(queue_test_messages($transport->get()))->toBe([])
		->and($this->db->query('SELECT status FROM queue_messages')->fetchColumn())->toBe('dead');
});

it('purges terminal rows across queues in bounded maintenance batches', function () {
	$this->db->exec("INSERT INTO queue_messages
		(message_id, queue_name, topic, payload, metadata, status, completed_at)
		VALUES
		('550e8400-e29b-41d4-a716-446655440004', 'reports', 'reports.generate', '{}', '{}', 'completed', DATE_SUB(NOW(), INTERVAL 8 DAY)),
		('550e8400-e29b-41d4-a716-446655440005', 'discovery', 'discovery.run', '{}', '{}', 'dead', DATE_SUB(NOW(), INTERVAL 91 DAY))");

	expect(api_queue_purge_all())->toBe(['completed' => 1, 'dead' => 1])
		->and((int) $this->db->query('SELECT COUNT(*) FROM queue_messages')->fetchColumn())->toBe(0);
});

it('caps each retention purge so maintenance yields to other work', function () {
	$this->db->exec("INSERT INTO queue_messages
		(message_id, queue_name, topic, payload, metadata, status, completed_at)
		SELECT CONCAT('00000000-0000-4000-8000-', LPAD(sequence_number, 12, '0')),
			'reports', 'reports.generate', '{}', '{}', 'completed', DATE_SUB(NOW(), INTERVAL 8 DAY)
		FROM (
			SELECT ones.d + tens.d * 10 + hundreds.d * 100 + thousands.d * 1000 + ten_thousands.d * 10000 AS sequence_number
			FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
				UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
			CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
				UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
			CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
				UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
			CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
				UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) thousands
			CROSS JOIN (SELECT 0 d UNION ALL SELECT 1) ten_thousands
		) numbered
		ORDER BY sequence_number
		LIMIT 10001");

	expect(api_queue_purge_status('completed', 7))->toBe(10000)
		->and((int) $this->db->query('SELECT COUNT(*) FROM queue_messages')->fetchColumn())->toBe(1);
});

function queue_test_messages(iterable $messages) : array {
	return is_array($messages) ? $messages : iterator_to_array($messages);
}
