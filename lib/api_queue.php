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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;

if (!interface_exists(StampInterface::class)) {
	$queue_autoloader = dirname(__DIR__) . '/include/vendor/autoload.php';

	if (!is_file($queue_autoloader)) {
		throw new RuntimeException('The Composer autoloader required by the Cacti queue API was not found.');
	}

	require_once($queue_autoloader);
}

interface CactiQueueMessageInterface {
	public function queueTopic() : string;
	public function toQueuePayload() : array;
	public static function fromQueuePayload(array $payload) : static;
}

final class CactiQueueMessage implements CactiQueueMessageInterface {
	public function __construct(private string $topic, private array $payload) {
		api_queue_validate_name($topic, 'topic');
		api_queue_validate_json_value($payload);
	}

	public function queueTopic() : string {
		return $this->topic;
	}

	public function payload() : array {
		return $this->payload;
	}

	public function toQueuePayload() : array {
		return ['topic' => $this->topic, 'payload' => $this->payload];
	}

	public static function fromQueuePayload(array $payload) : static {
		if (!is_string($payload['topic'] ?? null) || !is_array($payload['payload'] ?? null)) {
			throw new InvalidArgumentException('Stored generic queue message is invalid.');
		}

		return new static($payload['topic'], $payload['payload']);
	}
}

final class CactiQueueStamp implements StampInterface {
	public function __construct(
		private string $queue,
		private int $priority = 50,
		private int $max_attempts = 5,
		private string $correlation_id = '',
		private int $max_payload_bytes = 262144
	) {
		api_queue_validate_name($queue, 'queue');
		$this->priority          = min(100, max(0, $priority));
		$this->max_attempts      = min(100, max(1, $max_attempts));
		$this->max_payload_bytes = max(1024, $max_payload_bytes);

		if (strlen($correlation_id) > 255) {
			throw new LengthException('Queue correlation_id exceeds the 255 byte limit.');
		}
	}

	public function queue() : string {
		return $this->queue;
	}
	public function priority() : int {
		return $this->priority;
	}
	public function maxAttempts() : int {
		return $this->max_attempts;
	}
	public function correlationId() : string {
		return $this->correlation_id;
	}
	public function maxPayloadBytes() : int {
		return $this->max_payload_bytes;
	}
}

final class CactiQueueReceiptStamp implements NonSendableStampInterface {
	public function __construct(private int $id, private string $token, private int $attempt) {
	}

	public function id() : int {
		return $this->id;
	}
	public function token() : string {
		return $this->token;
	}
	public function attempt() : int {
		return $this->attempt;
	}
}

final class CactiQueueFailureStamp implements NonSendableStampInterface {
	public function __construct(private string $reason) {
	}

	public function reason() : string {
		return $this->reason;
	}
}

final class CactiQueueMessageException extends RuntimeException {
}

final class CactiQueueStaleReceiptException extends RuntimeException {
}

interface CactiQueueLeaseAwareTransportInterface {
	public function touch(Envelope $envelope, int $lease_seconds) : void;
}

interface CactiQueueAdminTransportInterface {
	public function health() : array;
	public function dead(int $limit = 50) : array;
	public function requeue(string $message_id) : void;
	public function purge(int $completed_days, int $dead_days) : array;
}

/** Safe JSON serializer: broker data cannot instantiate arbitrary classes or stamps. */
final class CactiQueueSerializer implements SerializerInterface {
	public function decode(array $encodedEnvelope) : Envelope {
		$type = $encodedEnvelope['headers']['type'] ?? null;
		$body = $encodedEnvelope['body'];

		if (!is_string($type) || !is_string($body) || !class_exists($type) || !is_subclass_of($type, CactiQueueMessageInterface::class)) {
			throw new CactiQueueMessageException('Stored queue message type is not allowed.');
		}

		try {
			$payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

			if (!is_array($payload)) {
				throw new UnexpectedValueException('Message body must be an object or array.');
			}

			return new Envelope($type::fromQueuePayload($payload));
		} catch (Throwable $e) {
			throw new CactiQueueMessageException('Stored queue message could not be decoded.', 0, $e);
		}
	}

	public function encode(Envelope $envelope) : array {
		$message = $envelope->getMessage();

		if (!$message instanceof CactiQueueMessageInterface) {
			throw new InvalidArgumentException('Queued messages must implement CactiQueueMessageInterface.');
		}

		return ['body' => api_queue_json_encode($message->toQueuePayload()), 'headers' => ['type' => $message::class]];
	}
}

final class CactiDatabaseQueueTransport implements TransportInterface, CactiQueueLeaseAwareTransportInterface, CactiQueueAdminTransportInterface {
	private SerializerInterface $serializer;

	public function __construct(private string $queue, private int $lease_seconds = 3600, SerializerInterface|null $serializer = null) {
		api_queue_validate_name($queue, 'queue');
		$this->lease_seconds = max(1, $lease_seconds);
		$this->serializer    = $serializer ?? new CactiQueueSerializer();
	}

	public function send(Envelope $envelope) : Envelope {
		$stamp = api_queue_stamp($envelope);

		if ($stamp->queue() !== $this->queue) {
			throw new InvalidArgumentException("Message for queue '{$stamp->queue()}' cannot be sent through '{$this->queue}'.");
		}

		$encoded = $this->serializer->encode($envelope);
		$body    = (string) $encoded['body'];

		if (strlen($body) > $stamp->maxPayloadBytes()) {
			throw new LengthException("Queue payload exceeds the {$stamp->maxPayloadBytes()} byte limit.");
		}

		$message_id = api_queue_message_id();
		$message    = $envelope->getMessage();
		$delay      = $envelope->last(DelayStamp::class);
		$delay      = $delay instanceof DelayStamp ? (int) ceil($delay->getDelay() / 1000) : 0;
		$metadata   = ['schema' => 'cacti.queue.v2', 'created_at' => gmdate('c'), 'correlation_id' => $stamp->correlationId()];
		$stored     = db_execute_prepared('INSERT INTO queue_messages
			(message_id, queue_name, topic, message_type, payload, metadata, status, priority, available_at, attempts, max_attempts, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 0, ?, NOW())', [
			$message_id, $this->queue, $message->queueTopic(), (string) $encoded['headers']['type'], $body,
			api_queue_json_encode($metadata), 'pending', $stamp->priority(), min(31536000, max(0, $delay)), $stamp->maxAttempts(),
		]);

		if (!$stored) {
			throw new RuntimeException('Unable to persist the queue message.');
		}

		return $envelope->with(new TransportMessageIdStamp($message_id));
	}

	public function get() : iterable {
		$token = bin2hex(random_bytes(24));
		$this->reapExpired();
		$claimed = db_execute_prepared('UPDATE queue_messages
			SET status = ?, reservation_token = ?, reserved_until = DATE_ADD(NOW(), INTERVAL ? SECOND), attempts = attempts + 1, available_at = NOW()
			WHERE queue_name = ? AND status = ? AND available_at <= NOW() AND attempts < max_attempts
			ORDER BY priority ASC, available_at ASC, id ASC LIMIT 1', ['reserved', $token, $this->lease_seconds, $this->queue, 'pending']);

		if (!$claimed) {
			throw new RuntimeException('Unable to claim the next queue message.');
		}

		if ((int) db_affected_rows() !== 1) {
			return [];
		}

		$row = db_fetch_row_prepared('SELECT * FROM queue_messages WHERE queue_name = ? AND reservation_token = ? AND status = ?', [$this->queue, $token, 'reserved']);

		// A row can disappear here only through an out-of-band database delete
		// between the atomic claim and this token-scoped read.
		// @codeCoverageIgnoreStart
		if (!is_array($row) || !$row) {
			return [];
		}
		// @codeCoverageIgnoreEnd

		try {
			$envelope = $this->serializer->decode(['body' => (string) $row['payload'], 'headers' => ['type' => (string) $row['message_type']]]);
		} catch (Throwable $e) {
			$this->markDead((int) $row['id'], $token, $e->getMessage());

			throw new CactiQueueMessageException('Invalid queue message was moved to the dead-letter state.', 0, $e);
		}

		$metadata = api_queue_json_decode((string) $row['metadata']);
		$stamps   = [
			new CactiQueueStamp((string) $row['queue_name'], (int) $row['priority'], (int) $row['max_attempts'], (string) ($metadata['correlation_id'] ?? '')),
			new CactiQueueReceiptStamp((int) $row['id'], $token, (int) $row['attempts']),
			new TransportMessageIdStamp((string) $row['message_id']),
		];

		if ((int) $row['attempts'] > 1) {
			$stamps[] = new RedeliveryStamp((int) $row['attempts'] - 1);
		}

		return [$envelope->with(...$stamps)];
	}

	public function ack(Envelope $envelope) : void {
		$receipt = $this->receipt($envelope);
		$result  = db_execute_prepared('UPDATE queue_messages SET status = ?, completed_at = NOW(), reservation_token = NULL, reserved_until = NULL WHERE id = ? AND reservation_token = ? AND status = ?', ['completed', $receipt->id(), $receipt->token(), 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; acknowledgement was refused.');
	}

	public function reject(Envelope $envelope) : void {
		$receipt = $this->receipt($envelope);
		$failure = $envelope->last(CactiQueueFailureStamp::class);
		$reason  = $failure instanceof CactiQueueFailureStamp ? $failure->reason() : 'Queue handler rejected the message.';
		$row     = db_fetch_row_prepared('SELECT attempts, max_attempts FROM queue_messages WHERE id = ? AND reservation_token = ?', [$receipt->id(), $receipt->token()]);

		if (!is_array($row) || !$row) {
			throw new CactiQueueStaleReceiptException('Queue message receipt is stale or invalid.');
		}

		if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
			$this->markDead($receipt->id(), $receipt->token(), $reason);

			return;
		}

		$delay  = min(3600, 2 ** min(10, (int) $row['attempts']));
		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?, reservation_token = NULL, reserved_until = NULL, completed_at = NULL WHERE id = ? AND reservation_token = ?', ['pending', $delay, api_queue_limit_error($reason), $receipt->id(), $receipt->token()]);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; retry was refused.');
	}

	public function touch(Envelope $envelope, int $lease_seconds) : void {
		$receipt = $this->receipt($envelope);
		$result  = db_execute_prepared('UPDATE queue_messages SET reserved_until = GREATEST(DATE_ADD(NOW(), INTERVAL ? SECOND), DATE_ADD(reserved_until, INTERVAL 1 SECOND)) WHERE id = ? AND reservation_token = ? AND status = ?', [max(1, $lease_seconds), $receipt->id(), $receipt->token(), 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; lease renewal was refused.');
	}

	public function health() : array {
		$rows   = db_fetch_assoc_prepared('SELECT status, COUNT(*) AS messages FROM queue_messages WHERE queue_name = ? GROUP BY status', [$this->queue]);
		$health = ['queue' => $this->queue, 'transport' => 'database', 'counts' => []];

		if (!is_array($rows)) {
			throw new RuntimeException('Unable to read queue health.');
		}

		foreach ($rows as $row) {
			$health['counts'][$row['status']] = (int) $row['messages'];
		}

		return $health;
	}

	public function dead(int $limit = 50) : array {
		$maximum = min(500, max(1, $limit));
		$rows    = db_fetch_assoc_prepared('SELECT message_id, topic, message_type, attempts, max_attempts, last_error, created_at, completed_at FROM queue_messages WHERE queue_name = ? AND status = ? ORDER BY completed_at DESC, id DESC LIMIT ' . $maximum, [$this->queue, 'dead']);

		if (!is_array($rows)) {
			throw new RuntimeException('Unable to read dead-letter queue messages.');
		}

		return array_slice($rows, 0, $maximum);
	}

	public function requeue(string $message_id) : void {
		if (!api_queue_is_message_id($message_id)) {
			throw new InvalidArgumentException('Queue message_id must be a canonical UUID.');
		}
		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, attempts = 0, available_at = NOW(), reserved_until = NULL, reservation_token = NULL, last_error = NULL, completed_at = NULL WHERE queue_name = ? AND message_id = ? AND status = ?', ['pending', $this->queue, $message_id, 'dead']);

		if (!$result || (int) db_affected_rows() !== 1) {
			throw new RuntimeException('Dead-letter message was not found or could not be requeued.');
		}
	}

	public function purge(int $completed_days, int $dead_days) : array {
		$completed = db_execute_prepared('DELETE FROM queue_messages WHERE queue_name = ? AND status = ? AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$this->queue, 'completed', max(1, $completed_days)]);

		if (!$completed) {
			throw new RuntimeException('Unable to purge completed queue messages.');
		}
		$completed_count = (int) db_affected_rows();
		$dead            = db_execute_prepared('DELETE FROM queue_messages WHERE queue_name = ? AND status = ? AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$this->queue, 'dead', max(1, $dead_days)]);

		if (!$dead) {
			throw new RuntimeException('Unable to purge dead-letter queue messages.');
		}

		return ['completed' => $completed_count, 'dead' => (int) db_affected_rows()];
	}

	private function reapExpired() : void {
		$dead = db_execute_prepared('UPDATE queue_messages SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL, completed_at = NOW() WHERE queue_name = ? AND status = ? AND reserved_until < NOW() AND attempts >= max_attempts LIMIT 1000', ['dead', 'Visibility lease expired after the maximum delivery attempts.', $this->queue, 'reserved']);

		if (!$dead) {
			throw new RuntimeException('Unable to reap expired queue messages.');
		}
		$released = db_execute_prepared('UPDATE queue_messages SET status = ?, reservation_token = NULL, reserved_until = NULL, available_at = NOW() WHERE queue_name = ? AND status = ? AND reserved_until < NOW() AND attempts < max_attempts LIMIT 1000', ['pending', $this->queue, 'reserved']);

		if (!$released) {
			throw new RuntimeException('Unable to release expired queue messages.');
		}
	}

	private function markDead(int $id, string $token, string $reason) : void {
		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL, completed_at = NOW() WHERE id = ? AND reservation_token = ? AND status = ?', ['dead', api_queue_limit_error($reason), $id, $token, 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; dead-letter update was refused.');
	}

	private function receipt(Envelope $envelope) : CactiQueueReceiptStamp {
		$receipt = $envelope->last(CactiQueueReceiptStamp::class);

		if (!$receipt instanceof CactiQueueReceiptStamp) {
			throw new InvalidArgumentException('A received queue envelope is required.');
		}

		return $receipt;
	}

	private function requireReceiptUpdated(mixed $result, string $message) : void {
		if (!$result || (int) db_affected_rows() !== 1) {
			throw new CactiQueueStaleReceiptException($message);
		}
	}
}

final class CactiQueueSendersLocator implements SendersLocatorInterface {
	public function getSenders(Envelope $envelope) : iterable {
		$queue = api_queue_stamp($envelope)->queue();

		yield $queue => api_queue_transport($queue);
	}
}

/** Restores the Cacti route when a broker serializer does not persist stamps. */
final class CactiQueueReceiver implements ReceiverInterface {
	public function __construct(private string $queue, private ReceiverInterface $receiver) {
	}

	public function get() : iterable {
		foreach ($this->receiver->get() as $envelope) {
			if (!$envelope->last(CactiQueueStamp::class)) {
				$envelope = $envelope->with(new CactiQueueStamp($this->queue));
			}

			yield $envelope;
		}
	}

	public function ack(Envelope $envelope) : void {
		$this->receiver->ack($envelope);
	}

	public function reject(Envelope $envelope) : void {
		$this->receiver->reject($envelope);
	}
}

final class CactiQueueHandlersLocator implements HandlersLocatorInterface {
	public function getHandlers(Envelope $envelope) : iterable {
		$message  = $envelope->getMessage();
		$handlers = api_queue_handlers();
		$handler  = $message instanceof CactiQueueMessageInterface ? ($handlers[$message->queueTopic()] ?? null) : null;

		if (!is_callable($handler)) {
			$handler = $handlers[$message::class] ?? null;
		}

		if (!is_callable($handler)) {
			return;
		}

		yield new HandlerDescriptor(static function (object $handled_message) use ($handler, $envelope) : mixed {
			$id                                           = spl_object_id($handled_message);
			$GLOBALS['cacti_queue_active_envelopes'][$id] = $envelope;

			try {
				return $handler($handled_message);
			} finally {
				unset($GLOBALS['cacti_queue_active_envelopes'][$id]);
			}
		});
	}
}

function api_queue_publish(string $topic, array $payload, array $options = []) : Envelope {
	return api_queue_dispatch_message(new CactiQueueMessage($topic, $payload), $options);
}

function api_queue_dispatch_message(CactiQueueMessageInterface $message, array $options = []) : Envelope {
	$queue   = (string) ($options['queue'] ?? api_queue_topic_queue($message->queueTopic()));
	$stamps  = [new CactiQueueStamp($queue, (int) ($options['priority'] ?? 50), (int) ($options['max_attempts'] ?? 5), api_queue_metadata_value($options, 'correlation_id'), (int) ($options['max_payload_bytes'] ?? 262144))];
	$encoded = (new CactiQueueSerializer())->encode(new Envelope($message));

	if (strlen((string) $encoded['body']) > $stamps[0]->maxPayloadBytes()) {
		throw new LengthException("Queue payload exceeds the {$stamps[0]->maxPayloadBytes()} byte limit.");
	}
	$delay  = min(31536000000, max(0, ((int) ($options['delay'] ?? 0)) * 1000));

	if ($delay > 0) {
		$stamps[] = new DelayStamp($delay);
	}

	return api_queue_bus()->dispatch(new Envelope($message, $stamps));
}

function api_queue_bus() : MessageBusInterface {
	return $GLOBALS['cacti_queue_send_bus'] ??= new MessageBus([new SendMessageMiddleware(new CactiQueueSendersLocator(), null, false)]);
}

function api_queue_worker_bus() : MessageBusInterface {
	return new MessageBus([new HandleMessageMiddleware(new CactiQueueHandlersLocator(), false)]);
}

function api_queue_register_transport(string $name, callable $factory) : void {
	api_queue_validate_name($name, 'transport');
	$GLOBALS['cacti_queue_transport_factories'][$name] = $factory;
}

function api_queue_register_handler(string $topic_or_class, callable $handler) : void {
	if (!class_exists($topic_or_class)) {
		api_queue_validate_name($topic_or_class, 'topic');
	} elseif (!is_subclass_of($topic_or_class, CactiQueueMessageInterface::class)) {
		throw new InvalidArgumentException('Handler must target a Cacti queue message class or topic.');
	}
	$GLOBALS['cacti_queue_handlers'][$topic_or_class] = $handler;
}

function api_queue_handlers() : array {
	$handlers        = $GLOBALS['cacti_queue_handlers'] ?? [];
	$plugin_handlers = api_queue_apply_hook('queue_handlers', $handlers);

	if (is_array($plugin_handlers)) {
		$handlers = $plugin_handlers;
	}

	return $handlers;
}

function api_queue_route(string $queue, string $transport) : void {
	api_queue_validate_name($queue, 'queue');
	api_queue_validate_name($transport, 'transport');
	$GLOBALS['cacti_queue_transport_routes'][$queue] = $transport;
	unset($GLOBALS['cacti_queue_transport_instances'][$queue]);
}

function api_queue_transport(string $queue) : TransportInterface {
	api_queue_validate_name($queue, 'queue');
	$instances = &$GLOBALS['cacti_queue_transport_instances'];

	if (!is_array($instances)) {
		$instances = [];
	}

	if (isset($instances[$queue])) {
		return $instances[$queue];
	}
	$factories = $GLOBALS['cacti_queue_transport_factories'] ?? [];
	$factories['database'] ??= static fn (string $name) : TransportInterface => new CactiDatabaseQueueTransport($name, api_queue_lease_seconds());

	$plugin_factories = api_queue_apply_hook('queue_transports', $factories);

	if (is_array($plugin_factories)) {
		$factories = $plugin_factories;
	}
	$name = api_queue_transport_name($queue);

	if (!isset($factories[$name]) || !is_callable($factories[$name])) {
		throw new RuntimeException("Queue transport '$name' is not registered.");
	}
	$transport = $factories[$name]($queue);

	if (!$transport instanceof TransportInterface) {
		throw new RuntimeException("Queue transport '$name' must implement Symfony Messenger TransportInterface.");
	}

	return $instances[$queue] = $transport;
}

function api_queue_dispatch(Envelope $envelope) : Envelope {
	return api_queue_worker_bus()->dispatch($envelope);
}

function api_queue_renew(object $message, int|null $lease_seconds = null) : void {
	$envelope = $message instanceof Envelope ? $message : ($GLOBALS['cacti_queue_active_envelopes'][spl_object_id($message)] ?? null);

	if (!$envelope instanceof Envelope) {
		throw new InvalidArgumentException('Lease renewal is only available while handling a received message.');
	}
	$transport = api_queue_transport(api_queue_stamp($envelope)->queue());

	if (!$transport instanceof CactiQueueLeaseAwareTransportInterface) {
		throw new LogicException('The selected queue transport does not support lease renewal.');
	}
	$transport->touch($envelope, $lease_seconds ?? api_queue_lease_seconds());
}

function api_queue_stamp(Envelope $envelope) : CactiQueueStamp {
	$stamp = $envelope->last(CactiQueueStamp::class);

	if (!$stamp instanceof CactiQueueStamp) {
		throw new InvalidArgumentException('Queue envelope is missing its CactiQueueStamp.');
	}

	return $stamp;
}

function api_queue_message_id_from_envelope(Envelope $envelope) : string {
	$stamp = $envelope->last(TransportMessageIdStamp::class);

	if (!$stamp instanceof TransportMessageIdStamp) {
		throw new InvalidArgumentException('Queue envelope has no transport message ID.');
	}

	return (string) $stamp->getId();
}

function api_queue_set_lease_seconds(int $seconds) : void {
	$GLOBALS['cacti_queue_lease_seconds'] = min(86400, max(30, $seconds));
	unset($GLOBALS['cacti_queue_transport_instances']);
}
function api_queue_lease_seconds() : int {
	if (isset($GLOBALS['cacti_queue_lease_seconds'])) {
		return (int) $GLOBALS['cacti_queue_lease_seconds'];
	}

	if (($value = (int) api_queue_config_option('queue_lease_seconds')) > 0) {
		return min(86400, max(30, $value));
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
	if (($value = (int) api_queue_config_option($setting)) > 0) {
		return min(3650, $value);
	}

	return $default;
}
function api_queue_purge_all() : array {
	if (function_exists('db_table_exists') && !db_table_exists('queue_messages')) {
		return ['completed' => 0, 'dead' => 0];
	}

	return ['completed' => api_queue_purge_status('completed', api_queue_completed_retention_days()), 'dead' => api_queue_purge_status('dead', api_queue_dead_retention_days())];
}
function api_queue_purge_status(string $status, int $days) : int {
	$total = 0;

	do {
		$result = db_execute_prepared('DELETE FROM queue_messages WHERE status = ? AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 1000', [$status, $days]);

		if (!$result) {
			throw new RuntimeException("Unable to purge $status queue messages.");
		}
		$count = (int) db_affected_rows();
		$total += $count;
	} while ($count === 1000 && $total < 10000);

	return $total;
}

function api_queue_transport_name(string $queue) : string {
	$routes = [];

	$value   = json_decode(api_queue_config_option('queue_transport_routes'), true);
	$routes  = is_array($value) ? $value : [];
	$runtime = $GLOBALS['cacti_queue_transport_routes'] ?? [];

	if (is_array($runtime)) {
		$routes = array_merge($routes, $runtime);
	}

	if (isset($routes[$queue]) && is_string($routes[$queue])) {
		try {
			api_queue_validate_name($routes[$queue], 'transport');

			return $routes[$queue];
		} catch (InvalidArgumentException $e) {
		}
	}

	$default = api_queue_config_option('queue_default_transport');

	if ($default !== '') {
		try {
			api_queue_validate_name($default, 'transport');

			return $default;
		} catch (InvalidArgumentException $e) {
		}
	}

	return 'database';
}

function api_queue_topic_queue(string $topic) : string {
	$pos = strpos($topic, '.');

	return $pos === false ? $topic : substr($topic, 0, $pos);
}
function api_queue_config_option(string $name) : string {
	$reader = $GLOBALS['cacti_queue_config_reader'] ?? null;

	if (is_callable($reader)) {
		return (string) $reader($name);
	}

	return function_exists('read_config_option') ? (string) read_config_option($name) : '';
}
function api_queue_apply_hook(string $hook, mixed $value) : mixed {
	$provider = $GLOBALS['cacti_queue_hook_provider'] ?? null;

	if (is_callable($provider)) {
		return $provider($hook, $value);
	}

	return function_exists('api_plugin_hook_function') ? api_plugin_hook_function($hook, $value) : $value;
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

		if ($current === null || is_string($current) || is_int($current) || is_bool($current) || (is_float($current) && is_finite($current))) {
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
	return strtolower((string) Uuid::v7());
}
function api_queue_is_message_id(string $id) : bool {
	return Uuid::isValid($id) && strtolower($id) === $id;
}
function api_queue_limit_error(string $reason) : string {
	return mb_strcut($reason, 0, 65535, 'UTF-8');
}
