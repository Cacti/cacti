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

/*
 * The queue API is self-contained PHP with no Composer dependency, so any
 * caller can require it without the vendor tree being current. Load it only
 * where it is used; include/global.php deliberately does not.
 */

/**
 * A message that can be stored on a queue. Implementations must round trip
 * through JSON-compatible arrays; executable callbacks are never queued.
 */
interface CactiQueueMessageInterface {
	/**
	 * Return the routing topic of this message.
	 *
	 * @return string The topic, for example 'reports.generate'
	 */
	public function queueTopic() : string;

	/**
	 * Return the JSON-compatible data that represents this message.
	 *
	 * @return array The payload that will be stored on the queue
	 */
	public function toQueuePayload() : array;

	/**
	 * Rebuild a message from the data returned by toQueuePayload().
	 *
	 * @param array $payload The decoded queue payload
	 *
	 * @return static The rebuilt message
	 */
	public static function fromQueuePayload(array $payload) : static;
}

/**
 * The generic message used by api_queue_publish(): a topic plus JSON data.
 */
final class CactiQueueMessage implements CactiQueueMessageInterface {
	/**
	 * @param string $topic   The routing topic
	 * @param array  $payload JSON-compatible message data
	 */
	public function __construct(private string $topic, private array $payload) {
		api_queue_validate_name($topic, 'topic');
		api_queue_validate_json_value($payload);
	}

	/**
	 * @return string The routing topic
	 */
	public function queueTopic() : string {
		return $this->topic;
	}

	/**
	 * @return array The message data supplied by the producer
	 */
	public function payload() : array {
		return $this->payload;
	}

	/**
	 * @return array The stored representation of this message
	 */
	public function toQueuePayload() : array {
		return ['topic' => $this->topic, 'payload' => $this->payload];
	}

	/**
	 * @param array $payload The stored representation of a generic message
	 *
	 * @return static The rebuilt message
	 */
	public static function fromQueuePayload(array $payload) : static {
		if (!is_string($payload['topic'] ?? null) || !is_array($payload['payload'] ?? null)) {
			throw new InvalidArgumentException('Stored generic queue message is invalid.');
		}

		return new static($payload['topic'], $payload['payload']);
	}
}

/**
 * Marker for delivery metadata attached to a CactiQueueEnvelope.
 */
interface CactiQueueStampInterface {
}

/**
 * An immutable message plus the delivery metadata (stamps) that travel with it.
 */
final class CactiQueueEnvelope {
	/** @var array<class-string, list<CactiQueueStampInterface>> */
	private array $stamps = [];

	/**
	 * @param object                     $message The queued message
	 * @param CactiQueueStampInterface[] $stamps  Initial delivery metadata
	 */
	public function __construct(private object $message, array $stamps = []) {
		foreach ($stamps as $stamp) {
			$this->stamps[$stamp::class][] = $stamp;
		}
	}

	/**
	 * @return object The queued message
	 */
	public function getMessage() : object {
		return $this->message;
	}

	/**
	 * Return a copy of this envelope with additional stamps.
	 *
	 * @param CactiQueueStampInterface ...$stamps The stamps to add
	 *
	 * @return self The new envelope
	 */
	public function with(CactiQueueStampInterface ...$stamps) : self {
		$copy = clone $this;

		foreach ($stamps as $stamp) {
			$copy->stamps[$stamp::class][] = $stamp;
		}

		return $copy;
	}

	/**
	 * Return a copy of this envelope without any stamp of the given class.
	 *
	 * @param string $class The stamp class to remove
	 *
	 * @return self The new envelope
	 */
	public function withoutAll(string $class) : self {
		$copy = clone $this;
		unset($copy->stamps[$class]);

		return $copy;
	}

	/**
	 * Return the most recently added stamp of the given class.
	 *
	 * @param string $class The stamp class to find
	 *
	 * @return CactiQueueStampInterface|null The stamp, or null when none is attached
	 */
	public function last(string $class) : ?CactiQueueStampInterface {
		$stamps = $this->stamps[$class] ?? [];

		return $stamps ? $stamps[array_key_last($stamps)] : null;
	}
}

/**
 * Delivery policy: target queue, priority, retry bound and payload limit.
 */
final class CactiQueueStamp implements CactiQueueStampInterface {
	/**
	 * @param string $queue             The queue name
	 * @param int    $priority          0 (first) to 100 (last)
	 * @param int    $max_attempts      Deliveries before the message is dead-lettered, 1 to 100
	 * @param string $correlation_id    Optional caller identifier, at most 255 bytes
	 * @param int    $max_payload_bytes Largest encoded body accepted, at least 1024
	 */
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

	/**
	 * @return string The queue name
	 */
	public function queue() : string {
		return $this->queue;
	}

	/**
	 * @return int The clamped priority
	 */
	public function priority() : int {
		return $this->priority;
	}

	/**
	 * @return int The clamped delivery attempt bound
	 */
	public function maxAttempts() : int {
		return $this->max_attempts;
	}

	/**
	 * @return string The caller supplied correlation identifier
	 */
	public function correlationId() : string {
		return $this->correlation_id;
	}

	/**
	 * @return int The largest encoded body accepted for this message
	 */
	public function maxPayloadBytes() : int {
		return $this->max_payload_bytes;
	}
}

/**
 * Requests delayed delivery.
 */
final class CactiQueueDelayStamp implements CactiQueueStampInterface {
	/**
	 * @param int $seconds Seconds before the message becomes deliverable, 0 to one year
	 */
	public function __construct(private int $seconds) {
		$this->seconds = min(31536000, max(0, $seconds));
	}

	/**
	 * @return int The clamped delay in seconds
	 */
	public function seconds() : int {
		return $this->seconds;
	}
}

/**
 * The identifier a transport assigned to a stored message.
 */
final class CactiQueueMessageIdStamp implements CactiQueueStampInterface {
	/**
	 * @param string $id The transport message identifier
	 */
	public function __construct(private string $id) {
	}

	/**
	 * @return string The transport message identifier
	 */
	public function id() : string {
		return $this->id;
	}
}

/**
 * Proof of a database reservation. Only the holder of the token can settle it.
 */
final class CactiQueueReceiptStamp implements CactiQueueStampInterface {
	/**
	 * @param int    $id      The queue_messages row id
	 * @param string $token   The reservation token written by the claim
	 * @param int    $attempt The delivery attempt, starting at 1
	 */
	public function __construct(private int $id, private string $token, private int $attempt) {
	}

	/**
	 * @return int The queue_messages row id
	 */
	public function id() : int {
		return $this->id;
	}

	/**
	 * @return string The reservation token
	 */
	public function token() : string {
		return $this->token;
	}

	/**
	 * @return int The delivery attempt, starting at 1
	 */
	public function attempt() : int {
		return $this->attempt;
	}

	/**
	 * @return int How many times this message was delivered before this attempt
	 */
	public function redeliveryCount() : int {
		return max(0, $this->attempt - 1);
	}
}

/**
 * The reason a handler failed, recorded when the delivery is rejected.
 */
final class CactiQueueFailureStamp implements CactiQueueStampInterface {
	/**
	 * @param string $reason The failure description
	 */
	public function __construct(private string $reason) {
	}

	/**
	 * @return string The failure description
	 */
	public function reason() : string {
		return $this->reason;
	}
}

/**
 * A stored or received message could not be decoded and was not delivered.
 */
final class CactiQueueMessageException extends RuntimeException {
}

/**
 * A reservation expired or was reclaimed before the holder settled it.
 */
final class CactiQueueStaleReceiptException extends RuntimeException {
}

/**
 * No handler is registered for a received message.
 */
final class CactiQueueNoHandlerException extends RuntimeException {
}

/**
 * The consuming side of a queue: claim deliveries, then settle each one.
 */
interface CactiQueueReceiverInterface {
	/**
	 * Claim the next deliverable messages.
	 *
	 * @return iterable<CactiQueueEnvelope> Zero or more reserved deliveries
	 */
	public function get() : iterable;

	/**
	 * Mark a delivery as successfully handled.
	 *
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function ack(CactiQueueEnvelope $envelope) : void;

	/**
	 * Return a failed delivery for retry with backoff, or dead-letter it once
	 * its attempts are exhausted.
	 *
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function reject(CactiQueueEnvelope $envelope) : void;
}

/**
 * A complete queue transport. Provider plugins implement this interface.
 */
interface CactiQueueTransportInterface extends CactiQueueReceiverInterface {
	/**
	 * Durably store a message.
	 *
	 * @param CactiQueueEnvelope $envelope The message and its CactiQueueStamp
	 *
	 * @return CactiQueueEnvelope The envelope with a CactiQueueMessageIdStamp
	 */
	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope;
}

/**
 * Optional transport capability: extend a reservation for a long handler.
 */
interface CactiQueueLeaseAwareTransportInterface {
	/**
	 * @param CactiQueueEnvelope $envelope      The delivery being handled
	 * @param int                $lease_seconds The new lease length
	 *
	 * @return void
	 */
	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void;
}

/**
 * Optional transport capability used by cli/queue_admin.php.
 */
interface CactiQueueAdminTransportInterface {
	/**
	 * @return array Message counts by status
	 */
	public function health() : array;

	/**
	 * @param int $limit The maximum number of rows to return
	 *
	 * @return array The most recent dead-letter messages
	 */
	public function dead(int $limit = 50) : array;

	/**
	 * @param string $message_id The dead-letter message to make deliverable again
	 *
	 * @return void
	 */
	public function requeue(string $message_id) : void;

	/**
	 * @param int $completed_days Retention for completed messages
	 * @param int $dead_days      Retention for dead-letter messages
	 *
	 * @return array Rows removed by status
	 */
	public function purge(int $completed_days, int $dead_days) : array;
}

/**
 * Converts envelopes to and from the body/headers form a transport stores.
 */
interface CactiQueueSerializerInterface {
	/**
	 * @param array $encodedEnvelope ['body' => string, 'headers' => array]
	 *
	 * @return CactiQueueEnvelope The decoded envelope
	 */
	public function decode(array $encodedEnvelope) : CactiQueueEnvelope;

	/**
	 * @param CactiQueueEnvelope $envelope The envelope to encode
	 *
	 * @return array ['body' => string, 'headers' => array]
	 */
	public function encode(CactiQueueEnvelope $envelope) : array;
}

/**
 * Reject a body larger than the limit.
 *
 * @param string $body  The encoded body
 * @param int    $limit The byte limit
 *
 * @return void
 */
function api_queue_validate_payload_size(string $body, int $limit) : void {
	if (strlen($body) > $limit) {
		throw new LengthException("Queue payload exceeds the $limit byte limit.");
	}
}

/**
 * Check that a string is syntactically a PHP class name.
 *
 * @param string $name The candidate class name
 *
 * @return bool True when the name is well formed
 */
function api_queue_class_name_valid(string $name) : bool {
	return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $name) === 1;
}

/**
 * Broker headers are untrusted. Only the built-in message and classes registered
 * through api_queue_register_handler() qualify, and they are checked without
 * autoloading so a crafted type cannot make an autoloader include a file.
 *
 * @param string $type The stored message class
 *
 * @return bool True when the class may be instantiated
 */
function api_queue_message_type_allowed(string $type) : bool {
	if (!api_queue_class_name_valid($type)) {
		return false;
	}

	if ($type !== CactiQueueMessage::class && !array_key_exists($type, api_queue_handlers())) {
		return false;
	}

	return class_exists($type, false) && is_subclass_of($type, CactiQueueMessageInterface::class);
}

/**
 * Safe JSON serializer: broker data cannot instantiate arbitrary classes.
 */
final class CactiQueueSerializer implements CactiQueueSerializerInterface {
	/**
	 * @param array $encodedEnvelope ['body' => string, 'headers' => array]
	 *
	 * @return CactiQueueEnvelope The decoded envelope
	 */
	public function decode(array $encodedEnvelope) : CactiQueueEnvelope {
		$type = $encodedEnvelope['headers']['type'] ?? null;
		$body = array_key_exists('body', $encodedEnvelope) ? $encodedEnvelope['body'] : null;

		if (!is_string($type) || !is_string($body) || !api_queue_message_type_allowed($type)) {
			throw new CactiQueueMessageException('Stored queue message type is not allowed.');
		}

		try {
			// The consumer decides how much it will parse; a message header can lower this limit but never raise it.
			api_queue_validate_payload_size($body, api_queue_payload_limit());

			$payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

			if (!is_array($payload)) {
				throw new UnexpectedValueException('Message body must be an object or array.');
			}

			$envelope = new CactiQueueEnvelope($type::fromQueuePayload($payload));
			$metadata = $encodedEnvelope['headers']['cacti_queue'] ?? null;

			if ($metadata !== null) {
				if (!is_string($metadata)) {
					throw new UnexpectedValueException('Queue policy header must be JSON.');
				}

				$policy = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);

				if (!is_array($policy) || !is_string($policy['queue'] ?? null) ||
					!is_int($policy['priority'] ?? null) || !is_int($policy['max_attempts'] ?? null) ||
					!is_string($policy['correlation_id'] ?? null) || !is_int($policy['max_payload_bytes'] ?? null)) {
					throw new UnexpectedValueException('Queue policy header is invalid.');
				}

				$stamp = new CactiQueueStamp($policy['queue'], $policy['priority'], $policy['max_attempts'],
					$policy['correlation_id'], $policy['max_payload_bytes']);
				api_queue_validate_payload_size($body, $stamp->maxPayloadBytes());
				$envelope = $envelope->with($stamp);
			}

			return $envelope;
		} catch (Throwable $e) {
			throw new CactiQueueMessageException('Stored queue message could not be decoded.', 0, $e);
		}
	}

	/**
	 * @param CactiQueueEnvelope $envelope The envelope to encode
	 *
	 * @return array ['body' => string, 'headers' => array]
	 */
	public function encode(CactiQueueEnvelope $envelope) : array {
		$message = $envelope->getMessage();

		if (!$message instanceof CactiQueueMessageInterface) {
			throw new InvalidArgumentException('Queued messages must implement CactiQueueMessageInterface.');
		}

		$body    = api_queue_json_encode($message->toQueuePayload());
		$headers = ['type' => $message::class];
		$stamp   = $envelope->last(CactiQueueStamp::class);

		if ($stamp instanceof CactiQueueStamp) {
			api_queue_validate_payload_size($body, $stamp->maxPayloadBytes());
			$headers['cacti_queue'] = api_queue_json_encode([
				'queue'             => $stamp->queue(),
				'priority'          => $stamp->priority(),
				'max_attempts'      => $stamp->maxAttempts(),
				'correlation_id'    => $stamp->correlationId(),
				'max_payload_bytes' => $stamp->maxPayloadBytes(),
			]);
		}

		return ['body' => $body, 'headers' => $headers];
	}
}

/**
 * The built-in transport, backed by the queue_messages table.
 *
 * Claims use SELECT ... FOR UPDATE SKIP LOCKED inside a transaction, so
 * concurrent workers each take a different row instead of waiting on the
 * row lock of the first candidate. SKIP LOCKED needs MariaDB 10.6 or MySQL
 * 8.0.1; develop requires MariaDB 10.11 or MySQL 8.4.
 */
final class CactiDatabaseQueueTransport implements CactiQueueTransportInterface, CactiQueueLeaseAwareTransportInterface, CactiQueueAdminTransportInterface {
	private CactiQueueSerializerInterface $serializer;

	/**
	 * @param string                             $queue         The queue this transport serves
	 * @param int                                $lease_seconds Reservation length for claimed messages
	 * @param CactiQueueSerializerInterface|null $serializer    Defaults to CactiQueueSerializer
	 */
	public function __construct(private string $queue, private int $lease_seconds = 3600, CactiQueueSerializerInterface|null $serializer = null) {
		api_queue_validate_name($queue, 'queue');
		$this->lease_seconds = max(1, $lease_seconds);
		$this->serializer    = $serializer !== null ? $serializer : new CactiQueueSerializer();
	}

	/**
	 * @param CactiQueueEnvelope $envelope The message and its CactiQueueStamp
	 *
	 * @return CactiQueueEnvelope The envelope with a CactiQueueMessageIdStamp
	 */
	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope {
		$stamp   = api_queue_stamp($envelope);
		$message = $envelope->getMessage();

		if (!$message instanceof CactiQueueMessageInterface) {
			throw new InvalidArgumentException('Queued messages must implement CactiQueueMessageInterface.');
		}

		if ($stamp->queue() !== $this->queue) {
			throw new InvalidArgumentException("Message for queue '{$stamp->queue()}' cannot be sent through '{$this->queue}'.");
		}

		$encoded = $this->serializer->encode($envelope);
		$body    = (string) $encoded['body'];

		// A custom serializer may skip the CactiQueueSerializer size check.
		api_queue_validate_payload_size($body, $stamp->maxPayloadBytes());

		$message_id = api_queue_message_id();
		$delay      = $envelope->last(CactiQueueDelayStamp::class);
		$delay      = $delay instanceof CactiQueueDelayStamp ? $delay->seconds() : 0;
		$metadata   = ['schema' => 'cacti.queue.v3', 'created_at' => gmdate('c'), 'correlation_id' => $stamp->correlationId()];
		$stored     = db_execute_prepared('INSERT INTO queue_messages
			(message_id, queue_name, topic, message_type, payload, metadata, status, priority, available_at, attempts, max_attempts, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 0, ?, NOW())', [
			$message_id, $this->queue, $message->queueTopic(), $message::class, $body,
			api_queue_json_encode($metadata), 'pending', $stamp->priority(), $delay, $stamp->maxAttempts(),
		]);

		if (!$stored) {
			throw new RuntimeException('Unable to persist the queue message.');
		}

		return $envelope->with(new CactiQueueMessageIdStamp($message_id));
	}

	/**
	 * Claim the next deliverable message. A message that cannot be decoded is
	 * dead-lettered and logged rather than thrown, so one bad row cannot stop
	 * the worker from reaching the messages queued behind it.
	 *
	 * @return iterable<CactiQueueEnvelope> Zero or one reserved delivery
	 */
	public function get() : iterable {
		$token = bin2hex(random_bytes(24));
		$this->reapExpired();
		$row = $this->claim($token);

		if (!$row) {
			return [];
		}

		try {
			$envelope = $this->serializer->decode(['body' => (string) $row['payload'], 'headers' => ['type' => (string) $row['message_type']]]);
			$metadata = api_queue_json_decode((string) $row['metadata']);
			$stamp    = new CactiQueueStamp((string) $row['queue_name'], (int) $row['priority'], (int) $row['max_attempts'], (string) ($metadata['correlation_id'] ?? ''));
		} catch (Throwable $e) {
			$previous = $e->getPrevious();
			$reason   = $e->getMessage() . ($previous !== null ? ' ' . $previous->getMessage() : '');

			$this->markDead((int) $row['id'], $token, $reason);
			api_queue_log(sprintf("Queue message '%s' on '%s' was moved to the dead-letter state: %s", (string) $row['message_id'], $this->queue, $reason));

			return [];
		}

		return [$envelope->with(
			$stamp,
			new CactiQueueReceiptStamp((int) $row['id'], $token, (int) $row['attempts']),
			new CactiQueueMessageIdStamp((string) $row['message_id'])
		)];
	}

	/**
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function ack(CactiQueueEnvelope $envelope) : void {
		$receipt = $this->receipt($envelope);
		$result  = db_execute_prepared('UPDATE queue_messages SET status = ?, completed_at = NOW(), reservation_token = NULL, reserved_until = NULL WHERE id = ? AND reservation_token = ? AND status = ?', ['completed', $receipt->id(), $receipt->token(), 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; acknowledgement was refused.');
	}

	/**
	 * Retry with exponential backoff (2^attempts seconds, at most one hour),
	 * or dead-letter the message once max_attempts deliveries have failed.
	 *
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function reject(CactiQueueEnvelope $envelope) : void {
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

		$delay  = min(3600, 2 ** min(12, (int) $row['attempts']));
		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?, reservation_token = NULL, reserved_until = NULL, completed_at = NULL WHERE id = ? AND reservation_token = ?', ['pending', $delay, api_queue_limit_error($reason), $receipt->id(), $receipt->token()]);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; retry was refused.');
	}

	/**
	 * @param CactiQueueEnvelope $envelope      The delivery being handled
	 * @param int                $lease_seconds The new lease length
	 *
	 * @return void
	 */
	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void {
		$receipt = $this->receipt($envelope);
		$result  = db_execute_prepared('UPDATE queue_messages SET reserved_until = GREATEST(DATE_ADD(NOW(), INTERVAL ? SECOND), DATE_ADD(reserved_until, INTERVAL 1 SECOND)) WHERE id = ? AND reservation_token = ? AND status = ?', [max(1, $lease_seconds), $receipt->id(), $receipt->token(), 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; lease renewal was refused.');
	}

	/**
	 * @return array Message counts by status for this queue
	 */
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

	/**
	 * @param int $limit The maximum number of rows to return, 1 to 500
	 *
	 * @return array The most recent dead-letter messages
	 */
	public function dead(int $limit = 50) : array {
		$maximum = min(500, max(1, $limit));
		$rows    = db_fetch_assoc_prepared('SELECT message_id, topic, message_type, attempts, max_attempts, last_error, created_at, completed_at FROM queue_messages WHERE queue_name = ? AND status = ? ORDER BY completed_at DESC, id DESC LIMIT ' . $maximum, [$this->queue, 'dead']);

		if (!is_array($rows)) {
			throw new RuntimeException('Unable to read dead-letter queue messages.');
		}

		return $rows;
	}

	/**
	 * @param string $message_id The dead-letter message to make deliverable again
	 *
	 * @return void
	 */
	public function requeue(string $message_id) : void {
		if (!api_queue_is_message_id($message_id)) {
			throw new InvalidArgumentException('Queue message_id must be 32 lowercase hexadecimal characters.');
		}

		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, attempts = 0, available_at = NOW(), reserved_until = NULL, reservation_token = NULL, last_error = NULL, completed_at = NULL WHERE queue_name = ? AND message_id = ? AND status = ?', ['pending', $this->queue, $message_id, 'dead']);

		if (!$result || (int) db_affected_rows() !== 1) {
			throw new RuntimeException('Dead-letter message was not found or could not be requeued.');
		}
	}

	/**
	 * @param int $completed_days Retention for completed messages
	 * @param int $dead_days      Retention for dead-letter messages
	 *
	 * @return array Rows removed by status
	 */
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

	/**
	 * Reserve one deliverable row for this queue.
	 *
	 * @param string $token The reservation token to write
	 *
	 * @return array The reserved row, or an empty array when the queue is idle
	 */
	private function claim(string $token) : array {
		// db_begin_transaction() returns false only without a connection, and
		// reapExpired() has already failed closed in that case.
		// @codeCoverageIgnoreStart
		if (!db_begin_transaction()) {
			throw new RuntimeException('Unable to claim the next queue message.');
		}
		// @codeCoverageIgnoreEnd

		try {
			$candidate = db_fetch_row_prepared('SELECT id FROM queue_messages
				WHERE queue_name = ? AND status = ? AND available_at <= NOW() AND attempts < max_attempts
				ORDER BY priority ASC, available_at ASC, id ASC
				LIMIT 1
				FOR UPDATE SKIP LOCKED', [$this->queue, 'pending']);

			if (!is_array($candidate)) {
				throw new RuntimeException('Unable to claim the next queue message.');
			}

			if ($candidate) {
				$claimed = db_execute_prepared('UPDATE queue_messages
					SET status = ?, reservation_token = ?, reserved_until = DATE_ADD(NOW(), INTERVAL ? SECOND), attempts = attempts + 1, available_at = NOW()
					WHERE id = ? AND status = ?', ['reserved', $token, $this->lease_seconds, $candidate['id'], 'pending']);

				if (!$claimed || (int) db_affected_rows() !== 1) {
					throw new RuntimeException('Unable to claim the next queue message.');
				}
			}

			if (!db_commit_transaction()) {
				throw new RuntimeException('Unable to commit the queue message claim.');
			}
		} catch (Throwable $e) {
			api_queue_rollback();

			throw $e;
		}

		if (!$candidate) {
			return [];
		}

		$row = db_fetch_row_prepared('SELECT * FROM queue_messages WHERE id = ? AND reservation_token = ? AND status = ?', [$candidate['id'], $token, 'reserved']);

		// The row can only change here through an out-of-band edit or delete
		// between the committed claim and this token-scoped read.
		return is_array($row) ? $row : [];
	}

	/**
	 * Release expired reservations that still have attempts left and
	 * dead-letter the ones that do not.
	 *
	 * @return void
	 */
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

	/**
	 * @param int    $id     The queue_messages row id
	 * @param string $token  The reservation token
	 * @param string $reason The failure to record
	 *
	 * @return void
	 */
	private function markDead(int $id, string $token, string $reason) : void {
		$result = db_execute_prepared('UPDATE queue_messages SET status = ?, last_error = ?, reservation_token = NULL, reserved_until = NULL, completed_at = NOW() WHERE id = ? AND reservation_token = ? AND status = ?', ['dead', api_queue_limit_error($reason), $id, $token, 'reserved']);
		$this->requireReceiptUpdated($result, 'Queue message receipt is stale; dead-letter update was refused.');
	}

	/**
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return CactiQueueReceiptStamp The reservation receipt
	 */
	private function receipt(CactiQueueEnvelope $envelope) : CactiQueueReceiptStamp {
		$receipt = $envelope->last(CactiQueueReceiptStamp::class);

		if (!$receipt instanceof CactiQueueReceiptStamp) {
			throw new InvalidArgumentException('A received queue envelope is required.');
		}

		return $receipt;
	}

	/**
	 * @param mixed  $result  The db_execute_prepared() result
	 * @param string $message The stale receipt description
	 *
	 * @return void
	 */
	private function requireReceiptUpdated(mixed $result, string $message) : void {
		if (!$result || (int) db_affected_rows() !== 1) {
			throw new CactiQueueStaleReceiptException($message);
		}
	}
}

/**
 * Restores the Cacti route when a broker serializer does not persist stamps.
 */
final class CactiQueueReceiver implements CactiQueueReceiverInterface {
	/**
	 * @param string                      $queue    The queue being consumed
	 * @param CactiQueueReceiverInterface $receiver The transport to read from
	 */
	public function __construct(private string $queue, private CactiQueueReceiverInterface $receiver) {
	}

	/**
	 * @return iterable<CactiQueueEnvelope> Deliveries with a CactiQueueStamp
	 */
	public function get() : iterable {
		foreach ($this->receiver->get() as $envelope) {
			if (!$envelope->last(CactiQueueStamp::class)) {
				$envelope = $envelope->with(new CactiQueueStamp($this->queue));
			}

			yield $envelope;
		}
	}

	/**
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function ack(CactiQueueEnvelope $envelope) : void {
		$this->receiver->ack($envelope);
	}

	/**
	 * @param CactiQueueEnvelope $envelope A delivery returned by get()
	 *
	 * @return void
	 */
	public function reject(CactiQueueEnvelope $envelope) : void {
		$this->receiver->reject($envelope);
	}
}

/**
 * Consumes one queue: claim, handle, then acknowledge or reject each delivery.
 */
final class CactiQueueWorker {
	private bool $stopped = false;

	private bool $failed = false;

	/**
	 * @param CactiQueueReceiverInterface $receiver  The queue to consume
	 * @param Closure|null                $heartbeat Called once per poll so a
	 *                                               registered process stays current
	 */
	public function __construct(private CactiQueueReceiverInterface $receiver, private ?Closure $heartbeat = null) {
	}

	/**
	 * Ask the worker to stop after the current delivery. Safe to call from a
	 * signal handler.
	 *
	 * @return void
	 */
	public function stop() : void {
		$this->stopped = true;
	}

	/**
	 * @return bool True when any delivery failed while running
	 */
	public function failed() : bool {
		return $this->failed;
	}

	/**
	 * Process deliveries until stopped.
	 *
	 * @param bool $once          Stop after a single poll of the queue
	 * @param int  $sleep_seconds Idle wait between polls of an empty queue
	 * @param int  $time_limit    Stop after this many seconds, 0 for no limit
	 *
	 * @return void
	 */
	public function run(bool $once = false, int $sleep_seconds = 2, int $time_limit = 0) : void {
		$started = time();

		while (!$this->stopped) {
			$busy = false;

			if ($this->heartbeat !== null) {
				($this->heartbeat)();
			}

			try {
				foreach ($this->receiver->get() as $envelope) {
					$busy = true;
					$this->handle($envelope);

					if ($this->stopped) {
						break;
					}
				}
			} catch (CactiQueueMessageException $e) {
				// A provider transport could not decode one delivery. Keep
				// consuming so the messages behind it are not starved.
				$busy         = true;
				$this->failed = true;
				api_queue_log('Queue delivery could not be decoded: ' . $e->getMessage());
			}

			if ($once || ($time_limit > 0 && time() - $started >= $time_limit)) {
				break;
			}

			if (!$busy && !$this->stopped) {
				sleep(max(1, $sleep_seconds));
			}
		}
	}

	/**
	 * @param CactiQueueEnvelope $envelope The delivery to handle
	 *
	 * @return void
	 */
	private function handle(CactiQueueEnvelope $envelope) : void {
		try {
			api_queue_dispatch($envelope);
		} catch (Throwable $e) {
			$this->failed = true;
			api_queue_log('Queue message failed: ' . $e->getMessage());
			$this->settle(fn () => $this->receiver->reject($envelope->with(new CactiQueueFailureStamp($e->getMessage()))));

			return;
		}

		$this->settle(fn () => $this->receiver->ack($envelope));
	}

	/**
	 * Acknowledge or reject a delivery. A stale receipt means the lease ran
	 * out and another worker may already own the message; with at-least-once
	 * delivery that is a duplicate to log, not a reason to stop the worker.
	 *
	 * @param callable $operation The ack or reject call
	 *
	 * @return void
	 */
	private function settle(callable $operation) : void {
		try {
			$operation();
		} catch (CactiQueueStaleReceiptException $e) {
			api_queue_log('Queue delivery was reclaimed before it was settled: ' . $e->getMessage());
		}
	}
}

/**
 * Publish a generic message.
 *
 * @param string $topic   The routing topic; its first segment is the default queue
 * @param array  $payload JSON-compatible message data
 * @param array  $options queue, priority, max_attempts, correlation_id, max_payload_bytes, delay (seconds)
 *
 * @return CactiQueueEnvelope The stored envelope with its CactiQueueMessageIdStamp
 */
function api_queue_publish(string $topic, array $payload, array $options = []) : CactiQueueEnvelope {
	return api_queue_dispatch_message(new CactiQueueMessage($topic, $payload), $options);
}

/**
 * Publish any message that implements CactiQueueMessageInterface.
 *
 * @param CactiQueueMessageInterface $message The message to publish
 * @param array                      $options See api_queue_publish()
 *
 * @return CactiQueueEnvelope The stored envelope with its CactiQueueMessageIdStamp
 */
function api_queue_dispatch_message(CactiQueueMessageInterface $message, array $options = []) : CactiQueueEnvelope {
	$queue   = (string) ($options['queue'] ?? api_queue_topic_queue($message->queueTopic()));
	$stamps  = [new CactiQueueStamp($queue, (int) ($options['priority'] ?? 50), (int) ($options['max_attempts'] ?? 5), api_queue_metadata_value($options, 'correlation_id'), (int) ($options['max_payload_bytes'] ?? 262144))];
	$encoded = (new CactiQueueSerializer())->encode(new CactiQueueEnvelope($message));

	api_queue_validate_payload_size((string) $encoded['body'], $stamps[0]->maxPayloadBytes());

	$delay = (int) ($options['delay'] ?? 0);

	if ($delay > 0) {
		$stamps[] = new CactiQueueDelayStamp($delay);
	}

	return api_queue_transport($queue)->send(new CactiQueueEnvelope($message, $stamps));
}

/**
 * Register a transport factory under a name usable in routes.
 *
 * @param string   $name    The transport name
 * @param callable $factory fn (string $queue) : CactiQueueTransportInterface
 *
 * @return void
 */
function api_queue_register_transport(string $name, callable $factory) : void {
	api_queue_validate_name($name, 'transport');
	$GLOBALS['cacti_queue_transport_factories'][$name] = $factory;
}

/**
 * Register a handler for a topic or for a message class.
 *
 * @param string   $topic_or_class A topic name or a CactiQueueMessageInterface class
 * @param callable $handler        fn (CactiQueueMessageInterface $message) : void
 *
 * @return void
 */
function api_queue_register_handler(string $topic_or_class, callable $handler) : void {
	if (!api_queue_class_name_valid($topic_or_class) || !class_exists($topic_or_class)) {
		api_queue_validate_name($topic_or_class, 'topic');
	} elseif (!is_subclass_of($topic_or_class, CactiQueueMessageInterface::class)) {
		throw new InvalidArgumentException('Handler must target a Cacti queue message class or topic.');
	}

	$GLOBALS['cacti_queue_handlers'][$topic_or_class] = $handler;
}

/**
 * @return array Registered handlers, including those added by the queue_handlers hook
 */
function api_queue_handlers() : array {
	$handlers        = $GLOBALS['cacti_queue_handlers'] ?? [];
	$plugin_handlers = api_queue_apply_hook('queue_handlers', $handlers);

	if (is_array($plugin_handlers)) {
		$handlers = $plugin_handlers;
	}

	return $handlers;
}

/**
 * Route a queue to a named transport for the rest of this process.
 *
 * @param string $queue     The queue name
 * @param string $transport The registered transport name
 *
 * @return void
 */
function api_queue_route(string $queue, string $transport) : void {
	api_queue_validate_name($queue, 'queue');
	api_queue_validate_name($transport, 'transport');
	$GLOBALS['cacti_queue_transport_routes'][$queue] = $transport;
	unset($GLOBALS['cacti_queue_transport_instances'][$queue]);
}

/**
 * Return the transport for a queue, creating and caching it on first use.
 *
 * @param string $queue The queue name
 *
 * @return CactiQueueTransportInterface The routed transport
 */
function api_queue_transport(string $queue) : CactiQueueTransportInterface {
	api_queue_validate_name($queue, 'queue');
	$instances = &$GLOBALS['cacti_queue_transport_instances'];

	if (!is_array($instances)) {
		$instances = [];
	}

	if (isset($instances[$queue])) {
		return $instances[$queue];
	}

	$factories = $GLOBALS['cacti_queue_transport_factories'] ?? [];

	if (!isset($factories['database'])) {
		$factories['database'] = static fn (string $name) : CactiQueueTransportInterface => new CactiDatabaseQueueTransport($name, api_queue_lease_seconds());
	}

	$plugin_factories = api_queue_apply_hook('queue_transports', $factories);

	if (is_array($plugin_factories)) {
		$factories = $plugin_factories;
	}

	$name = api_queue_transport_name($queue);

	if (!isset($factories[$name]) || !is_callable($factories[$name])) {
		throw new RuntimeException("Queue transport '$name' is not registered.");
	}

	$transport = $factories[$name]($queue);

	if (!$transport instanceof CactiQueueTransportInterface) {
		throw new RuntimeException("Queue transport '$name' must implement CactiQueueTransportInterface.");
	}

	return $instances[$queue] = $transport;
}

/**
 * Find the handler for a message. A topic handler is more specific than a
 * class handler, so it wins when both exist.
 *
 * @param object $message The received message
 *
 * @return callable|null The handler, or null when none is registered
 */
function api_queue_handler_for(object $message) : ?callable {
	$handlers = api_queue_handlers();
	$handler  = $message instanceof CactiQueueMessageInterface ? ($handlers[$message->queueTopic()] ?? null) : null;

	if (!is_callable($handler)) {
		$handler = $handlers[$message::class] ?? null;
	}

	return is_callable($handler) ? $handler : null;
}

/**
 * Run the registered handler for a received delivery.
 *
 * @param CactiQueueEnvelope $envelope The delivery to handle
 *
 * @return void
 */
function api_queue_dispatch(CactiQueueEnvelope $envelope) : void {
	$message = $envelope->getMessage();
	$handler = api_queue_handler_for($message);

	if ($handler === null) {
		throw new CactiQueueNoHandlerException(sprintf('No queue handler is registered for %s.', $message instanceof CactiQueueMessageInterface ? $message->queueTopic() : $message::class));
	}

	// api_queue_renew() finds the delivery for the message object it is given.
	$id                                           = spl_object_id($message);
	$GLOBALS['cacti_queue_active_envelopes'][$id] = $envelope;

	try {
		$handler($message);
	} finally {
		unset($GLOBALS['cacti_queue_active_envelopes'][$id]);
	}
}

/**
 * Extend the lease of the delivery being handled.
 *
 * @param object   $message       The message passed to the handler, or its envelope
 * @param int|null $lease_seconds The new lease length, or the configured default
 *
 * @return void
 */
function api_queue_renew(object $message, int|null $lease_seconds = null) : void {
	$envelope = $message instanceof CactiQueueEnvelope ? $message : ($GLOBALS['cacti_queue_active_envelopes'][spl_object_id($message)] ?? null);

	if (!$envelope instanceof CactiQueueEnvelope) {
		throw new InvalidArgumentException('Lease renewal is only available while handling a received message.');
	}

	$transport = api_queue_transport(api_queue_stamp($envelope)->queue());

	if (!$transport instanceof CactiQueueLeaseAwareTransportInterface) {
		throw new LogicException('The selected queue transport does not support lease renewal.');
	}

	$transport->touch($envelope, $lease_seconds !== null ? $lease_seconds : api_queue_lease_seconds());
}

/**
 * @param CactiQueueEnvelope $envelope An envelope carrying delivery policy
 *
 * @return CactiQueueStamp The delivery policy
 */
function api_queue_stamp(CactiQueueEnvelope $envelope) : CactiQueueStamp {
	$stamp = $envelope->last(CactiQueueStamp::class);

	if (!$stamp instanceof CactiQueueStamp) {
		throw new InvalidArgumentException('Queue envelope is missing its CactiQueueStamp.');
	}

	return $stamp;
}

/**
 * @param CactiQueueEnvelope $envelope A sent or received envelope
 *
 * @return string The transport message identifier
 */
function api_queue_message_id_from_envelope(CactiQueueEnvelope $envelope) : string {
	$stamp = $envelope->last(CactiQueueMessageIdStamp::class);

	if (!$stamp instanceof CactiQueueMessageIdStamp) {
		throw new InvalidArgumentException('Queue envelope has no transport message ID.');
	}

	return $stamp->id();
}

/**
 * Override the database lease for this process, 30 seconds to one day.
 *
 * @param int $seconds The lease length
 *
 * @return void
 */
function api_queue_set_lease_seconds(int $seconds) : void {
	$GLOBALS['cacti_queue_lease_seconds'] = min(86400, max(30, $seconds));
	unset($GLOBALS['cacti_queue_transport_instances']);
}

/**
 * @return int The database lease length in seconds
 */
function api_queue_lease_seconds() : int {
	if (isset($GLOBALS['cacti_queue_lease_seconds'])) {
		return (int) $GLOBALS['cacti_queue_lease_seconds'];
	}

	$value = (int) api_queue_config_option('queue_lease_seconds');

	if ($value > 0) {
		return min(86400, max(30, $value));
	}

	return 3600;
}

/**
 * @return int The largest body a consumer will decode
 */
function api_queue_payload_limit() : int {
	$value = (int) api_queue_config_option('queue_max_payload_bytes');

	if ($value > 0) {
		return min(67108864, max(1024, $value));
	}

	return 262144;
}

/**
 * @return int Days to keep completed messages
 */
function api_queue_completed_retention_days() : int {
	return api_queue_retention_days('queue_completed_retention_days', 7);
}

/**
 * @return int Days to keep dead-letter messages
 */
function api_queue_dead_retention_days() : int {
	return api_queue_retention_days('queue_dead_retention_days', 90);
}

/**
 * @param string $setting The settings name
 * @param int    $default The value used when the setting is unset
 *
 * @return int The retention in days, at most ten years
 */
function api_queue_retention_days(string $setting, int $default) : int {
	$value = (int) api_queue_config_option($setting);

	if ($value > 0) {
		return min(3650, $value);
	}

	return $default;
}

/**
 * Remove expired terminal messages across all queues for poller maintenance.
 *
 * @return array Rows removed by status
 */
function api_queue_purge_all() : array {
	if (function_exists('db_table_exists') && !db_table_exists('queue_messages')) {
		return ['completed' => 0, 'dead' => 0];
	}

	return ['completed' => api_queue_purge_status('completed', api_queue_completed_retention_days()), 'dead' => api_queue_purge_status('dead', api_queue_dead_retention_days())];
}

/**
 * Delete expired messages of one status in batches of 1000, at most 10000
 * per call so maintenance yields to other work.
 *
 * @param string $status The terminal status
 * @param int    $days   The retention in days
 *
 * @return int Rows removed
 */
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

/**
 * Resolve the transport name for a queue: runtime route, configured route,
 * configured default, then 'database'. Invalid names are ignored.
 *
 * @param string $queue The queue name
 *
 * @return string The transport name
 */
function api_queue_transport_name(string $queue) : string {
	$value   = json_decode(api_queue_config_option('queue_transport_routes'), true);
	$routes  = is_array($value) ? $value : [];
	$runtime = $GLOBALS['cacti_queue_transport_routes'] ?? [];

	if (is_array($runtime)) {
		$routes = array_merge($routes, $runtime);
	}

	if (isset($routes[$queue]) && is_string($routes[$queue]) && api_queue_name_valid($routes[$queue], 'transport')) {
		return $routes[$queue];
	}

	$default = api_queue_config_option('queue_default_transport');

	if ($default !== '' && api_queue_name_valid($default, 'transport')) {
		return $default;
	}

	return 'database';
}

/**
 * @param string $topic The routing topic
 *
 * @return string The first topic segment, used as the default queue
 */
function api_queue_topic_queue(string $topic) : string {
	$pos = strpos($topic, '.');

	return $pos === false ? $topic : substr($topic, 0, $pos);
}

/**
 * Read a setting; tests may substitute $GLOBALS['cacti_queue_config_reader'].
 *
 * @param string $name The settings name
 *
 * @return string The setting value, or '' when unavailable
 */
function api_queue_config_option(string $name) : string {
	$reader = $GLOBALS['cacti_queue_config_reader'] ?? null;

	if (is_callable($reader)) {
		return (string) $reader($name);
	}

	return function_exists('read_config_option') ? (string) read_config_option($name) : '';
}

/**
 * Run a plugin hook; tests may substitute $GLOBALS['cacti_queue_hook_provider'].
 *
 * @param string $hook  The hook name
 * @param mixed  $value The value passed through the hook
 *
 * @return mixed The value returned by the hook
 */
function api_queue_apply_hook(string $hook, mixed $value) : mixed {
	$provider = $GLOBALS['cacti_queue_hook_provider'] ?? null;

	if (is_callable($provider)) {
		return $provider($hook, $value);
	}

	return function_exists('api_plugin_hook_function') ? api_plugin_hook_function($hook, $value) : $value;
}

/**
 * Write a single-line queue log entry; tests may substitute
 * $GLOBALS['cacti_queue_log_writer'].
 *
 * @param string $message The message to log
 *
 * @return void
 */
function api_queue_log(string $message) : void {
	// Messages can carry handler exception text; keep each entry on one line.
	$message = (string) preg_replace('/[\r\n]+/', ' ', $message);
	$writer  = $GLOBALS['cacti_queue_log_writer'] ?? null;

	if (is_callable($writer)) {
		$writer($message);
	} elseif (function_exists('cacti_log')) {
		cacti_log($message, false, 'QUEUE');
	}
}

/**
 * Roll back a failed claim. PDO throws when no transaction is open (for
 * example after a failed commit), and the claim failure is the error worth
 * reporting, so a rollback failure is ignored.
 *
 * @return void
 */
function api_queue_rollback() : void {
	try {
		db_rollback_transaction();
	} catch (Throwable $e) {
	}
}

/**
 * @param string $name The topic, queue or transport name
 * @param string $kind 'topic', 'queue' or 'transport'
 *
 * @return bool True when the name is valid for its kind
 */
function api_queue_name_valid(string $name, string $kind) : bool {
	$maximum = $kind === 'topic' ? 128 : 64;

	return strlen($name) <= $maximum && preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $name) === 1;
}

/**
 * @param string $name The topic, queue or transport name
 * @param string $kind 'topic', 'queue' or 'transport'
 *
 * @return void
 */
function api_queue_validate_name(string $name, string $kind) : void {
	if (!api_queue_name_valid($name, $kind)) {
		throw new InvalidArgumentException("Invalid queue $kind '$name'.");
	}
}

/**
 * @param array  $options The publish options
 * @param string $name    The option to read
 *
 * @return string The option value, at most 255 bytes
 */
function api_queue_metadata_value(array $options, string $name) : string {
	$value = (string) ($options[$name] ?? '');

	if (strlen($value) > 255) {
		throw new LengthException("Queue $name exceeds the 255 byte limit.");
	}

	return $value;
}

/**
 * @param array $value JSON-compatible data
 *
 * @return string The JSON encoding
 */
function api_queue_json_encode(array $value) : string {
	api_queue_validate_json_value($value);

	try {
		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	} catch (JsonException $e) {
		throw new InvalidArgumentException('Queue data must be valid JSON.', 0, $e);
	}
}

/**
 * Reject values JSON cannot represent faithfully, iteratively so deep input
 * cannot exhaust the stack.
 *
 * @param mixed $value The value to check
 *
 * @return void
 */
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

/**
 * @param string $value Stored JSON
 *
 * @return array The decoded array
 */
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

/**
 * @return string A new random message identifier, 32 lowercase hex characters
 */
function api_queue_message_id() : string {
	return bin2hex(random_bytes(16));
}

/**
 * @param string $id A candidate message identifier
 *
 * @return bool True for a 32 character lowercase hexadecimal identifier
 */
function api_queue_is_message_id(string $id) : bool {
	return preg_match('/^[0-9a-f]{32}\z/', $id) === 1;
}

/**
 * @param string $reason A failure description
 *
 * @return string The description cut to fit a TEXT column without splitting a character
 */
function api_queue_limit_error(string $reason) : string {
	return mb_strcut($reason, 0, 65535, 'UTF-8');
}
