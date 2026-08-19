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

/**
 * Portable queue envelope used by every Cacti queue transport.
 */
final class CactiQueueEnvelope {
	private string $message_id;
	private string $queue;
	private string $topic;
	private array $payload;
	private array $metadata;
	private int|null $receipt_id;
	private string|null $receipt_token;

	public function __construct(
		string $message_id,
		string $queue,
		string $topic,
		array $payload,
		array $metadata = [],
		int|null $receipt_id = null,
		string|null $receipt_token = null
	) {
		$this->message_id    = $message_id;
		$this->queue         = $queue;
		$this->topic         = $topic;
		$this->payload       = $payload;
		$this->metadata      = $metadata;
		$this->receipt_id    = $receipt_id;
		$this->receipt_token = $receipt_token;
	}

	public function messageId() : string {
		return $this->message_id;
	}

	public function queue() : string {
		return $this->queue;
	}

	public function topic() : string {
		return $this->topic;
	}

	public function payload() : array {
		return $this->payload;
	}

	public function metadata() : array {
		return $this->metadata;
	}

	public function receiptId() : int|null {
		return $this->receipt_id;
	}

	public function receiptToken() : string|null {
		return $this->receipt_token;
	}

	public function withReceipt(int $id, string $token, int $attempt) : self {
		$metadata            = $this->metadata;
		$metadata['attempt'] = $attempt;

		return new self(
			$this->message_id,
			$this->queue,
			$this->topic,
			$this->payload,
			$metadata,
			$id,
			$token
		);
	}
}

final class CactiQueueMessageException extends RuntimeException {
}

final class CactiQueueStaleReceiptException extends RuntimeException {
}

/**
 * Transport contract intentionally follows Symfony Messenger's portable
 * send/get/ack/reject model. Broker-specific concepts stay in plugins.
 */
interface CactiQueueTransportInterface {
	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope;

	/**
	 * @return iterable<CactiQueueEnvelope>
	 */
	public function get() : iterable;

	public function ack(CactiQueueEnvelope $envelope) : void;

	public function reject(CactiQueueEnvelope $envelope, string $reason) : void;

	public function retry(CactiQueueEnvelope $envelope, int $delay_seconds, string $reason) : void;

	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void;

	public function health() : array;
}

interface CactiQueueAdminTransportInterface {
	public function dead(int $limit = 50) : array;

	public function requeue(string $message_id) : void;

	public function purge(int $completed_days, int $dead_days) : array;
}

/**
 * Built-in durable transport for installations without an external broker.
 */
final class CactiDatabaseQueueTransport implements CactiQueueTransportInterface, CactiQueueAdminTransportInterface {
	private string $queue;
	private int $lease_seconds;

	public function __construct(string $queue, int $lease_seconds = 3600) {
		$this->queue         = $queue;
		$this->lease_seconds = max(1, $lease_seconds);
	}

	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope {
		$metadata     = $envelope->metadata();
		$delay        = min(31536000, max(0, (int) ($metadata['delay'] ?? 0)));
		$priority     = min(100, max(0, (int) ($metadata['priority'] ?? 50)));
		$max_attempts = min(100, max(1, (int) ($metadata['max_attempts'] ?? 5)));

		$stored = db_execute_prepared('INSERT INTO queue_messages
			(message_id, queue_name, topic, payload, metadata, status, priority,
			available_at, attempts, max_attempts, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 0, ?, NOW())',
			[
				$envelope->messageId(),
				$envelope->queue(),
				$envelope->topic(),
				api_queue_json_encode($envelope->payload()),
				api_queue_json_encode($metadata),
				'pending',
				$priority,
				$delay,
				$max_attempts,
			]
		);

		if (!$stored) {
			throw new RuntimeException('Unable to persist the queue message.');
		}

		return $envelope;
	}

	public function get() : iterable {
		$token  = bin2hex(random_bytes(24));
		$reaped = db_execute_prepared('UPDATE queue_messages
			SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL,
			completed_at = NOW()
			WHERE queue_name = ?
			AND status = ?
			AND reserved_until < NOW()
			AND attempts >= max_attempts
			LIMIT 1000',
			['dead', 'Visibility lease expired after the maximum delivery attempts.', $this->queue, 'reserved']
		);

		if (!$reaped) {
			throw new RuntimeException('Unable to reap expired queue messages.');
		}

		$released = db_execute_prepared('UPDATE queue_messages
			SET status = ?, reservation_token = NULL, reserved_until = NULL, available_at = NOW()
			WHERE queue_name = ?
			AND status = ?
			AND reserved_until < NOW()
			AND attempts < max_attempts
			LIMIT 1000',
			['pending', $this->queue, 'reserved']
		);

		if (!$released) {
			throw new RuntimeException('Unable to release expired queue messages.');
		}

		$claimed = db_execute_prepared('UPDATE queue_messages
			SET status = ?, reservation_token = ?, reserved_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
			attempts = attempts + 1, available_at = NOW()
			WHERE queue_name = ?
			AND status = ?
			AND available_at <= NOW()
			AND attempts < max_attempts
			ORDER BY priority ASC, available_at ASC, id ASC
			LIMIT 1',
			['reserved', $token, $this->lease_seconds, $this->queue, 'pending']
		);

		if (!$claimed) {
			throw new RuntimeException('Unable to claim the next queue message.');
		}

		if ((int) db_affected_rows() !== 1) {
			return [];
		}

		$row = db_fetch_row_prepared('SELECT * FROM queue_messages
			WHERE queue_name = ?
			AND reservation_token = ?
			AND status = ?',
			[$this->queue, $token, 'reserved']);

		if (!is_array($row) || !$row) {
			return [];
		}

		try {
			$payload  = api_queue_json_decode($row['payload']);
			$metadata = api_queue_json_decode($row['metadata']);
		} catch (Throwable $e) {
			$dead = db_execute_prepared('UPDATE queue_messages
				SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL,
				completed_at = NOW()
				WHERE id = ?
				AND reservation_token = ?
				AND status = ?',
				['dead', api_queue_limit_error($e->getMessage()), $row['id'], $token, 'reserved']);

			$this->requireReceiptUpdated($dead, 'Unable to dead-letter an invalid queue message.');

			throw new CactiQueueMessageException('Invalid queue message was moved to the dead-letter state.', 0, $e);
		}
		$envelope = new CactiQueueEnvelope(
			(string) $row['message_id'],
			(string) $row['queue_name'],
			(string) $row['topic'],
			$payload,
			$metadata
		);

		return [$envelope->withReceipt((int) $row['id'], $token, (int) $row['attempts'])];
	}

	public function ack(CactiQueueEnvelope $envelope) : void {
		$this->requireReceipt($envelope);

		$acknowledged = db_execute_prepared('UPDATE queue_messages
			SET status = ?, completed_at = NOW(), reservation_token = NULL, reserved_until = NULL
			WHERE id = ?
			AND reservation_token = ?
			AND status = ?',
			['completed', $envelope->receiptId(), $envelope->receiptToken(), 'reserved']);

		$this->requireReceiptUpdated($acknowledged, 'Queue message receipt is stale; acknowledgement was refused.');
	}

	public function reject(CactiQueueEnvelope $envelope, string $reason) : void {
		$this->requireReceipt($envelope);
		$attempt = (int) ($envelope->metadata()['attempt'] ?? 1);
		$delay   = min(3600, 2 ** min(10, $attempt));

		$this->retry($envelope, $delay, $reason);
	}

	public function retry(CactiQueueEnvelope $envelope, int $delay_seconds, string $reason) : void {
		$this->requireReceipt($envelope);
		$row = db_fetch_row_prepared('SELECT attempts, max_attempts FROM queue_messages
			WHERE id = ?
			AND reservation_token = ?',
			[$envelope->receiptId(), $envelope->receiptToken()]);

		if (!is_array($row) || !$row) {
			throw new CactiQueueStaleReceiptException('Queue message receipt is stale or invalid.');
		}

		if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
			$dead = db_execute_prepared('UPDATE queue_messages
				SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL,
				completed_at = NOW()
				WHERE id = ?
				AND reservation_token = ?',
				['dead', api_queue_limit_error($reason), $envelope->receiptId(), $envelope->receiptToken()]);

			$this->requireReceiptUpdated($dead, 'Queue message receipt is stale; dead-letter update was refused.');

			return;
		}

		$retried = db_execute_prepared('UPDATE queue_messages
			SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?,
			reservation_token = NULL, reserved_until = NULL, completed_at = NULL
			WHERE id = ?
			AND reservation_token = ?',
			[
				'pending',
				max(0, $delay_seconds),
				api_queue_limit_error($reason),
				$envelope->receiptId(),
				$envelope->receiptToken(),
			]
		);

		$this->requireReceiptUpdated($retried, 'Queue message receipt is stale; retry was refused.');
	}

	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void {
		$this->requireReceipt($envelope);
		$renewed = db_execute_prepared('UPDATE queue_messages
			SET reserved_until = GREATEST(
				DATE_ADD(NOW(), INTERVAL ? SECOND),
				DATE_ADD(reserved_until, INTERVAL 1 SECOND)
			)
			WHERE id = ?
			AND reservation_token = ?
			AND status = ?',
			[
				max(1, $lease_seconds),
				$envelope->receiptId(),
				$envelope->receiptToken(),
				'reserved',
			]
		);

		$this->requireReceiptUpdated($renewed, 'Queue message receipt is stale; lease renewal was refused.');
	}

	public function health() : array {
		$rows = db_fetch_assoc_prepared('SELECT status, COUNT(*) AS messages
			FROM queue_messages
			WHERE queue_name = ?
			GROUP BY status',
			[$this->queue]);
		$health = ['queue' => $this->queue, 'transport' => 'database', 'counts' => []];

		foreach (is_array($rows) ? $rows : [] as $row) {
			$health['counts'][$row['status']] = (int) $row['messages'];
		}

		return $health;
	}

	public function dead(int $limit = 50) : array {
		$maximum = min(500, max(1, $limit));
		// LIMIT is safe to interpolate because $maximum is an int clamped to 1-500.
		$rows = db_fetch_assoc_prepared('SELECT message_id, topic, attempts, max_attempts,
			last_error, created_at, completed_at
			FROM queue_messages
			WHERE queue_name = ?
			AND status = ?
			ORDER BY completed_at DESC, id DESC
			LIMIT ' . $maximum,
			[$this->queue, 'dead']);

		return is_array($rows) ? array_slice($rows, 0, $maximum) : [];
	}

	public function requeue(string $message_id) : void {
		if (!api_queue_is_message_id($message_id)) {
			throw new InvalidArgumentException('Queue message_id must be a canonical UUID version 4.');
		}

		$requeued = db_execute_prepared('UPDATE queue_messages
			SET status = ?, attempts = 0, available_at = NOW(), reserved_until = NULL,
			reservation_token = NULL, last_error = NULL, completed_at = NULL
			WHERE queue_name = ?
			AND message_id = ?
			AND status = ?',
			['pending', $this->queue, $message_id, 'dead']);

		if (!$requeued || (int) db_affected_rows() !== 1) {
			throw new RuntimeException('Dead-letter message was not found or could not be requeued.');
		}
	}

	public function purge(int $completed_days, int $dead_days) : array {
		$completed = db_execute_prepared('DELETE FROM queue_messages
			WHERE queue_name = ?
			AND status = ?
			AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
			[$this->queue, 'completed', max(1, $completed_days)]);

		if (!$completed) {
			throw new RuntimeException('Unable to purge completed queue messages.');
		}

		$completed_count = (int) db_affected_rows();
		$dead            = db_execute_prepared('DELETE FROM queue_messages
			WHERE queue_name = ?
			AND status = ?
			AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
			[$this->queue, 'dead', max(1, $dead_days)]);

		if (!$dead) {
			throw new RuntimeException('Unable to purge dead-letter queue messages.');
		}

		return ['completed' => $completed_count, 'dead' => (int) db_affected_rows()];
	}

	private function requireReceipt(CactiQueueEnvelope $envelope) : void {
		if ($envelope->receiptId() === null || $envelope->receiptToken() === null) {
			throw new InvalidArgumentException('A received queue envelope is required.');
		}
	}

	private function requireReceiptUpdated(mixed $result, string $message) : void {
		if (!$result || (int) db_affected_rows() !== 1) {
			throw new CactiQueueStaleReceiptException($message);
		}
	}
}

function api_queue_publish(string $topic, array $payload, array $options = []) : CactiQueueEnvelope {
	api_queue_validate_name($topic, 'topic');
	$queue = (string) ($options['queue'] ?? api_queue_topic_queue($topic));
	api_queue_validate_name($queue, 'queue');
	$encoded = api_queue_json_encode($payload);
	$maximum = max(1024, (int) ($options['max_payload_bytes'] ?? 262144));

	if (strlen($encoded) > $maximum) {
		throw new LengthException("Queue payload exceeds the $maximum byte limit.");
	}

	$message_id = (string) ($options['message_id'] ?? api_queue_message_id());

	if (!api_queue_is_message_id($message_id)) {
		throw new InvalidArgumentException('Queue message_id must be a canonical UUID version 4.');
	}

	$correlation_id = api_queue_metadata_value($options, 'correlation_id');
	$metadata       = [
		'schema'          => 'cacti.queue.v1',
		'created_at'      => gmdate('c'),
		'delay'           => min(31536000, max(0, (int) ($options['delay'] ?? 0))),
		'priority'        => min(100, max(0, (int) ($options['priority'] ?? 50))),
		'max_attempts'    => min(100, max(1, (int) ($options['max_attempts'] ?? 5))),
		'correlation_id'  => $correlation_id,
	];
	$envelope = new CactiQueueEnvelope($message_id, $queue, $topic, $payload, $metadata);

	return api_queue_transport($queue)->send($envelope);
}

function api_queue_register_transport(string $name, callable $factory) : void {
	api_queue_validate_name($name, 'transport');
	$GLOBALS['cacti_queue_transport_factories'][$name] = $factory;
}

function api_queue_register_handler(string $topic, callable $handler) : void {
	api_queue_validate_name($topic, 'topic');
	$GLOBALS['cacti_queue_handlers'][$topic] = $handler;
}

function api_queue_route(string $queue, string $transport) : void {
	api_queue_validate_name($queue, 'queue');
	api_queue_validate_name($transport, 'transport');
	$GLOBALS['cacti_queue_transport_routes'][$queue] = $transport;
	unset($GLOBALS['cacti_queue_transport_instances'][$queue]);
}

function api_queue_transport(string $queue) : CactiQueueTransportInterface {
	$instances = &$GLOBALS['cacti_queue_transport_instances'];

	if (!is_array($instances)) {
		$instances = [];
	}

	if (isset($instances[$queue])) {
		return $instances[$queue];
	}

	$factories = $GLOBALS['cacti_queue_transport_factories'] ?? [];
	$factories['database'] ??= static fn (string $queue_name) : CactiQueueTransportInterface => new CactiDatabaseQueueTransport($queue_name, api_queue_lease_seconds());

	if (function_exists('api_plugin_hook_function')) {
		$plugin_factories = api_plugin_hook_function('queue_transports', $factories);

		if (is_array($plugin_factories)) {
			$factories = $plugin_factories;
		}
	}

	$transport_name = api_queue_transport_name($queue);

	if (!isset($factories[$transport_name]) || !is_callable($factories[$transport_name])) {
		throw new RuntimeException("Queue transport '$transport_name' is not registered.");
	}

	$transport = $factories[$transport_name]($queue);

	if (!$transport instanceof CactiQueueTransportInterface) {
		throw new RuntimeException("Queue transport '$transport_name' does not implement the Cacti queue contract.");
	}

	return $instances[$queue] = $transport;
}

function api_queue_dispatch(CactiQueueEnvelope $envelope) : void {
	$handlers = $GLOBALS['cacti_queue_handlers'] ?? [];

	if (function_exists('api_plugin_hook_function')) {
		$plugin_handlers = api_plugin_hook_function('queue_handlers', $handlers);

		if (is_array($plugin_handlers)) {
			$handlers = $plugin_handlers;
		}
	}

	if (!isset($handlers[$envelope->topic()]) || !is_callable($handlers[$envelope->topic()])) {
		throw new RuntimeException("No handler is registered for queue topic '{$envelope->topic()}'.");
	}

	$handlers[$envelope->topic()]($envelope);
}

function api_queue_renew(CactiQueueEnvelope $envelope, int|null $lease_seconds = null) : void {
	api_queue_transport($envelope->queue())->touch($envelope, $lease_seconds ?? api_queue_lease_seconds());
}

function api_queue_set_lease_seconds(int $lease_seconds) : void {
	$GLOBALS['cacti_queue_lease_seconds'] = min(86400, max(30, $lease_seconds));
	unset($GLOBALS['cacti_queue_transport_instances']);
}

function api_queue_lease_seconds() : int {
	if (isset($GLOBALS['cacti_queue_lease_seconds'])) {
		return (int) $GLOBALS['cacti_queue_lease_seconds'];
	}

	if (function_exists('read_config_option')) {
		$configured = (int) read_config_option('queue_lease_seconds');

		if ($configured > 0) {
			return min(86400, max(30, $configured));
		}
	}

	return 3600;
}

function api_queue_completed_retention_days() : int {
	return api_queue_retention_days('queue_completed_retention_days', 7);
}

function api_queue_dead_retention_days() : int {
	return api_queue_retention_days('queue_dead_retention_days', 90);
}

function api_queue_retention_days(string $setting, int $default) : int {
	if (function_exists('read_config_option')) {
		$configured = (int) read_config_option($setting);

		if ($configured > 0) {
			return min(3650, $configured);
		}
	}

	return $default;
}

function api_queue_purge_all() : array {
	if (function_exists('db_table_exists') && !db_table_exists('queue_messages')) {
		return ['completed' => 0, 'dead' => 0];
	}

	return [
		'completed' => api_queue_purge_status('completed', api_queue_completed_retention_days()),
		'dead'      => api_queue_purge_status('dead', api_queue_dead_retention_days()),
	];
}

function api_queue_purge_status(string $status, int $days) : int {
	$total   = 0;
	$maximum = 10000;

	do {
		$purged = db_execute_prepared('DELETE FROM queue_messages
			WHERE status = ?
			AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
			LIMIT 1000',
			[$status, $days]);

		if (!$purged) {
			throw new RuntimeException("Unable to purge $status queue messages.");
		}

		$count  = (int) db_affected_rows();
		$total += $count;
	} while ($count === 1000 && $total < $maximum);

	return $total;
}

function api_queue_transport_name(string $queue) : string {
	$routes         = [];
	$runtime_routes = $GLOBALS['cacti_queue_transport_routes'] ?? [];

	if (function_exists('read_config_option')) {
		$configured_routes = read_config_option('queue_transport_routes');
		$decoded_routes    = json_decode((string) $configured_routes, true);

		if (is_array($decoded_routes)) {
			$routes = $decoded_routes;
		}
	}

	if (is_array($runtime_routes)) {
		$routes = array_merge($routes, $runtime_routes);
	}

	if (isset($routes[$queue]) && is_string($routes[$queue])) {
		try {
			api_queue_validate_name($routes[$queue], 'transport');

			return $routes[$queue];
		} catch (InvalidArgumentException $e) {
			// Ignore invalid stored routes and continue to the validated default.
		}
	}

	if (function_exists('read_config_option')) {
		$default = read_config_option('queue_default_transport');

		if (is_string($default) && $default !== '') {
			try {
				api_queue_validate_name($default, 'transport');

				return $default;
			} catch (InvalidArgumentException $e) {
				// Ignore invalid stored defaults and use the built-in transport.
			}
		}
	}

	return 'database';
}

function api_queue_topic_queue(string $topic) : string {
	$separator = strpos($topic, '.');

	return $separator === false ? $topic : substr($topic, 0, $separator);
}

function api_queue_validate_name(string $name, string $kind) : void {
	$maximum = $kind === 'topic' ? 128 : 64;

	if (strlen($name) > $maximum || !preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $name)) {
		throw new InvalidArgumentException("Invalid queue $kind '$name'.");
	}
}

function api_queue_metadata_value(array $options, string $name) : string {
	$value = (string) ($options[$name] ?? '');

	if (strlen($value) > 255) {
		throw new LengthException("Queue $name exceeds the 255 byte limit.");
	}

	return $value;
}

function api_queue_json_encode(array $value) : string {
	api_queue_validate_json_value($value);

	try {
		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	} catch (JsonException $e) {
		throw new InvalidArgumentException('Queue data must be valid JSON.', 0, $e);
	}
}

function api_queue_validate_json_value(mixed $value) : void {
	$pending = [[$value, 0]];

	while ($pending) {
		[$current, $depth] = array_pop($pending);

		if (is_array($current)) {
			if ($depth >= 512) {
				throw new InvalidArgumentException('Queue data exceeds the maximum JSON depth.');
			}

			foreach ($current as $item) {
				$pending[] = [$item, $depth + 1];
			}

			continue;
		}

		if ($current === null || is_string($current) || is_int($current) || is_bool($current)) {
			continue;
		}

		if (is_float($current) && is_finite($current)) {
			continue;
		}

		throw new InvalidArgumentException('Queue data must contain only JSON-compatible scalar and array values.');
	}
}

function api_queue_json_decode(string $value) : array {
	try {
		$decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
	} catch (JsonException $e) {
		throw new RuntimeException('Stored queue data is not valid JSON.', 0, $e);
	}

	if (!is_array($decoded)) {
		throw new RuntimeException('Stored queue data must decode to an array.');
	}

	return $decoded;
}

function api_queue_message_id() : string {
	$bytes    = random_bytes(16);
	$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
	$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
	$hex      = bin2hex($bytes);

	return sprintf('%s-%s-%s-%s-%s',
		substr($hex, 0, 8),
		substr($hex, 8, 4),
		substr($hex, 12, 4),
		substr($hex, 16, 4),
		substr($hex, 20)
	);
}

function api_queue_is_message_id(string $message_id) : bool {
	return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $message_id);
}

function api_queue_limit_error(string $reason) : string {
	return mb_strcut($reason, 0, 65535, 'UTF-8');
}
