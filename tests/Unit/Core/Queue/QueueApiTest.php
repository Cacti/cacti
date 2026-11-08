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

require_once dirname(__DIR__, 4) . '/lib/api_queue.php';

/**
 * In-memory transport that records what the queue API sends and settles.
 */
class CactiTestQueueTransport implements CactiQueueTransportInterface {
	/** @var CactiQueueEnvelope[] */
	public array $sent         = [];
	public array $received     = [];
	public array $acknowledged = [];
	public array $rejected     = [];

	/**
	 * Record the envelope and stamp it with a new message id.
	 *
	 * @param CactiQueueEnvelope $envelope
	 *
	 * @return CactiQueueEnvelope
	 */
	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope {
		$this->sent[] = $envelope;

		return $envelope->with(new CactiQueueMessageIdStamp(api_queue_message_id()));
	}

	/**
	 * Hand out the queued deliveries once.
	 *
	 * @return iterable<CactiQueueEnvelope>
	 */
	public function get() : iterable {
		$received       = $this->received;
		$this->received = [];

		return $received;
	}

	/**
	 * Record an acknowledged delivery.
	 *
	 * @param CactiQueueEnvelope $envelope
	 *
	 * @return void
	 */
	public function ack(CactiQueueEnvelope $envelope) : void {
		$this->acknowledged[] = $envelope;
	}

	/**
	 * Record a rejected delivery.
	 *
	 * @param CactiQueueEnvelope $envelope
	 *
	 * @return void
	 */
	public function reject(CactiQueueEnvelope $envelope) : void {
		$this->rejected[] = $envelope;
	}
}

/**
 * Test transport that also records lease renewals.
 */
final class CactiTestLeaseQueueTransport extends CactiTestQueueTransport implements CactiQueueLeaseAwareTransportInterface {
	public array $touched = [];

	/**
	 * Record a lease renewal.
	 *
	 * @param CactiQueueEnvelope $envelope
	 * @param int                $lease_seconds
	 *
	 * @return void
	 */
	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void {
		$this->touched[] = [$envelope, $lease_seconds];
	}
}

/**
 * A class with no handler method, for handler registration tests.
 */
final class InvalidCactiQueueHandlerTarget {
}

/**
 * A message class the serializer may rebuild from its payload.
 */
final class RegisteredCactiQueueMessage implements CactiQueueMessageInterface {
	/**
	 * @param array $payload
	 */
	public function __construct(private array $payload) {
	}

	/**
	 * @return string
	 */
	public function queueTopic() : string {
		return 'registered.message';
	}

	/**
	 * @return array
	 */
	public function toQueuePayload() : array {
		return $this->payload;
	}

	/**
	 * Rebuild the message from its payload.
	 *
	 * @param array $payload
	 *
	 * @return static
	 */
	public static function fromQueuePayload(array $payload) : static {
		return new static($payload);
	}
}

beforeEach(function () {
	queue_api_test_reset_runtime();
	$GLOBALS['cacti_queue_config_reader'] = static fn (string $name) : string => (string) ($GLOBALS['cacti_queue_test_settings'][$name] ?? '');
	$GLOBALS['cacti_queue_hook_provider'] = static function (string $hook, mixed $value) : mixed {
		$callback = $GLOBALS['cacti_queue_test_hooks'][$hook] ?? null;

		return is_callable($callback) ? $callback($value) : $value;
	};
	$GLOBALS['cacti_queue_test_log']   = [];
	$GLOBALS['cacti_queue_log_writer'] = static function (string $message) : void {
		$GLOBALS['cacti_queue_test_log'][] = $message;
	};
	$this->transport = new CactiTestQueueTransport();
	api_queue_register_transport('test', fn (string $queue) => $this->transport);
	api_queue_route('reports', 'test');
});

afterEach(function () {
	queue_api_test_reset_runtime();
});

it('publishes a portable JSON envelope to the routed transport', function () {
	$envelope = api_queue_publish('reports.generate', ['report_id' => 42], [
		'priority'       => 75,
		'correlation_id' => 'request-1',
	]);

	$message = $envelope->getMessage();
	$stamp   = api_queue_stamp($envelope);
	expect($this->transport->sent)->toHaveCount(1)
		->and($message)->toBeInstanceOf(CactiQueueMessage::class)
		->and($message->queueTopic())->toBe('reports.generate')
		->and($message->payload())->toBe(['report_id' => 42])
		->and($stamp->queue())->toBe('reports')
		->and($stamp->priority())->toBe(75)
		->and(api_queue_message_id_from_envelope($envelope))->toMatch('/^[0-9a-f]{32}$/');
});

it('preserves delivery policy across broker serialization and reception', function () {
	$serializer = new CactiQueueSerializer();
	$envelope   = api_queue_publish('reports.generate', ['id' => 4], [
		'priority' => 12, 'max_attempts' => 9, 'correlation_id' => 'request-42', 'max_payload_bytes' => 4096,
	]);
	$this->transport->received = [$serializer->decode($serializer->encode($envelope))];
	$receiver                  = new CactiQueueReceiver('reports', $this->transport);
	$received                  = iterator_to_array($receiver->get());
	$stamp                     = api_queue_stamp($received[0]);
	expect($stamp->queue())->toBe('reports')
		->and($stamp->priority())->toBe(12)
		->and($stamp->maxAttempts())->toBe(9)
		->and($stamp->correlationId())->toBe('request-42')
		->and($stamp->maxPayloadBytes())->toBe(4096);
});

it('rejects malformed broker policy headers', function () {
	$serializer = new CactiQueueSerializer();
	$envelope   = api_queue_publish('reports.generate', []);
	$encoded    = $serializer->encode($envelope);
	$policy     = json_decode($encoded['headers']['cacti_queue'], true, 512, JSON_THROW_ON_ERROR);
	$headers    = [[], '{', 'null', '{}', json_encode(array_replace($policy, ['queue' => '../invalid'])),
		json_encode(array_replace($policy, ['correlation_id' => str_repeat('x', 256)]))];

	foreach ($headers as $header) {
		$encoded['headers']['cacti_queue'] = $header;
		expect(fn () => $serializer->decode($encoded))->toThrow(CactiQueueMessageException::class);
	}
});

it('enforces serialized broker payload limits before publishing and after reception', function () {
	$serializer = new CactiQueueSerializer();
	$message    = new CactiQueueMessage('reports.generate', ['value' => str_repeat('x', 2048)]);
	$stamp      = new CactiQueueStamp('reports', max_payload_bytes: 1024);
	$envelope   = new CactiQueueEnvelope($message, [$stamp]);
	expect(fn () => $serializer->encode($envelope))->toThrow(LengthException::class);
	$encoded                           = $serializer->encode(new CactiQueueEnvelope($message));
	$encoded['headers']['cacti_queue'] = json_encode([
		'queue'          => 'reports', 'priority' => 50, 'max_attempts' => 5,
		'correlation_id' => '', 'max_payload_bytes' => 1024,
	]);
	expect(fn () => $serializer->decode($encoded))->toThrow(CactiQueueMessageException::class);
});

it('enforces database payload limits when a custom serializer bypasses the broker guard', function () {
	$serializer = new class implements CactiQueueSerializerInterface {
		public function encode(CactiQueueEnvelope $envelope) : array {
			return ['body' => str_repeat('x', 2048), 'headers' => []];
		}

		public function decode(array $encodedEnvelope) : CactiQueueEnvelope {
			return new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []));
		}
	};
	$transport = new CactiDatabaseQueueTransport('reports', 60, $serializer);
	$envelope  = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []),
		[new CactiQueueStamp('reports', max_payload_bytes: 1024)]);
	expect(fn () => $transport->send($envelope))->toThrow(LengthException::class);
});

it('routes different queues to different transports in one installation', function () {
	$notifications = new CactiTestQueueTransport();
	$poller        = new CactiTestQueueTransport();
	api_queue_register_transport('rabbitmq', static fn (string $queue) => $notifications);
	api_queue_register_transport('sqs', static fn (string $queue) => $poller);
	api_queue_route('notifications', 'rabbitmq');
	api_queue_route('poller', 'sqs');

	api_queue_publish('notifications.send', ['id' => 1]);
	api_queue_publish('poller.query', ['host_id' => 2]);

	expect($notifications->sent)->toHaveCount(1)
		->and($poller->sent)->toHaveCount(1)
		->and(api_queue_stamp($notifications->sent[0])->queue())->toBe('notifications')
		->and(api_queue_stamp($poller->sent[0])->queue())->toBe('poller');
});

it('caps delayed delivery at one year', function () {
	$envelope = api_queue_publish('reports.generate', [], ['delay' => PHP_INT_MAX]);

	$delay = $envelope->last(CactiQueueDelayStamp::class);
	expect($delay)->toBeInstanceOf(CactiQueueDelayStamp::class)
		->and($delay->seconds())->toBe(31536000)
		->and((new CactiQueueDelayStamp(-5))->seconds())->toBe(0);
});

it('clamps delivery policy and rejects oversized correlation identifiers', function () {
	$stamp = new CactiQueueStamp('reports', -1, 101, 'request', 1);

	expect($stamp->priority())->toBe(0)
		->and($stamp->maxAttempts())->toBe(100)
		->and($stamp->correlationId())->toBe('request')
		->and($stamp->maxPayloadBytes())->toBe(1024);

	expect(fn () => new CactiQueueStamp('reports', correlation_id: str_repeat('x', 256)))
		->toThrow(LengthException::class, 'correlation_id');
});

it('validates and round trips safe queue messages', function () {
	$serializer = new CactiQueueSerializer();
	$encoded    = $serializer->encode(new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['id' => 4])));
	$message    = $serializer->decode($encoded)->getMessage();

	expect($message)->toBeInstanceOf(CactiQueueMessage::class)
		->and($message->queueTopic())->toBe('reports.generate')
		->and($message->payload())->toBe(['id' => 4]);
});

it('rejects unsafe or malformed serialized messages', function (array $encoded) {
	(new CactiQueueSerializer())->decode($encoded);
})->with([
	'missing type'  => [['body' => '{}', 'headers' => []]],
	'unknown type'  => [['body' => '{}', 'headers' => ['type' => 'MissingQueueMessage']]],
	'invalid json'  => [['body' => '{', 'headers' => ['type' => CactiQueueMessage::class]]],
	'scalar body'   => [['body' => '1', 'headers' => ['type' => CactiQueueMessage::class]]],
	'invalid shape' => [['body' => '{}', 'headers' => ['type' => CactiQueueMessage::class]]],
])->throws(CactiQueueMessageException::class);

it('rejects untrusted message types without invoking an autoloader', function (mixed $type) {
	$requested = [];
	$spy       = static function (string $class) use (&$requested) : void {
		$requested[] = $class;

		if ($class === 'CactiUnregisteredAutoloadable') {
			class_alias(InvalidCactiQueueHandlerTarget::class, $class);
		}
	};
	$failure = null;

	spl_autoload_register($spy, true, true);

	try {
		(new CactiQueueSerializer())->decode(['body' => '{}', 'headers' => ['type' => $type]]);
	} catch (Throwable $e) {
		$failure = $e;
	} finally {
		spl_autoload_unregister($spy);
	}

	expect($failure)->toBeInstanceOf(CactiQueueMessageException::class)
		->and($failure->getMessage())->toContain('is not allowed')
		->and($requested)->toBe([]);
})->with([
	'path traversal'                => ['../../x'],
	'dot segments'                  => ['Foo\\..\\Bar'],
	'leading separator'             => ['\\CactiQueueMessage'],
	'trailing newline'              => [CactiQueueMessage::class . "\n"],
	'unregistered autoloadable'     => ['CactiUnregisteredAutoloadable'],
	'registered topic, not a class' => ['reports.generate'],
	'array'                         => [['x']],
	'integer'                       => [7],
]);

it('decodes only message classes registered with a handler', function () {
	$encoded = (new CactiQueueSerializer())->encode(new CactiQueueEnvelope(new RegisteredCactiQueueMessage(['a' => 1])));
	expect(fn () => (new CactiQueueSerializer())->decode($encoded))->toThrow(CactiQueueMessageException::class);

	api_queue_register_handler(RegisteredCactiQueueMessage::class, static function () : void {
	});

	expect((new CactiQueueSerializer())->decode($encoded)->getMessage())->toBeInstanceOf(RegisteredCactiQueueMessage::class);
});

it('does not autoload when registering a handler for a malformed name', function () {
	$requested = [];
	$spy       = static function (string $class) use (&$requested) : void {
		$requested[] = $class;
	};
	$failure = null;

	spl_autoload_register($spy, true, true);

	try {
		api_queue_register_handler('../../x', static function () : void {
		});
	} catch (Throwable $e) {
		$failure = $e;
	} finally {
		spl_autoload_unregister($spy);
	}

	expect($failure)->toBeInstanceOf(InvalidArgumentException::class)
		->and($requested)->toBe([]);
});

it('applies the consumer payload limit to the raw body regardless of the message header', function () {
	$GLOBALS['cacti_queue_test_settings']['queue_max_payload_bytes'] = '2048';
	$serializer                                                      = new CactiQueueSerializer();
	$message                                                         = new CactiQueueMessage('reports.generate', ['value' => str_repeat('x', 4096)]);
	$encoded                                                         = $serializer->encode(new CactiQueueEnvelope($message, [new CactiQueueStamp('reports', max_payload_bytes: 1048576)]));
	expect(json_decode($encoded['headers']['cacti_queue'], true)['max_payload_bytes'])->toBe(1048576)
		->and(fn () => $serializer->decode($encoded))->toThrow(CactiQueueMessageException::class);

	// The database transport hands the stored row to decode() without any header.
	$stored = ['body' => $encoded['body'], 'headers' => ['type' => CactiQueueMessage::class]];
	expect(fn () => $serializer->decode($stored))->toThrow(CactiQueueMessageException::class);

	$GLOBALS['cacti_queue_test_settings']['queue_max_payload_bytes'] = '8192';
	expect($serializer->decode($stored)->getMessage())->toBeInstanceOf(CactiQueueMessage::class);
});

it('rejects an oversized body before decoding it', function () {
	$GLOBALS['cacti_queue_test_settings']['queue_max_payload_bytes'] = '1024';
	$body                                                            = '{"topic":"reports.generate","payload":{"v":"' . str_repeat('x', 2048) . '"}}';

	expect(fn () => (new CactiQueueSerializer())->decode(['body' => $body, 'headers' => ['type' => CactiQueueMessage::class]]))
		->toThrow(CactiQueueMessageException::class);
});

it('rejects malformed broker envelopes without emitting a warning', function () {
	/* A broker may hand back an envelope missing either key, so decode() must
	   reach its own exception rather than trip an undefined index first. */
	$warnings = [];

	set_error_handler(static function (int $severity, string $message) use (&$warnings) : bool {
		$warnings[] = $message;

		return true;
	});

	try {
		expect(fn () => (new CactiQueueSerializer())->decode(['headers' => ['type' => CactiQueueMessage::class]]))
			->toThrow(CactiQueueMessageException::class, 'is not allowed')
			->and(fn () => (new CactiQueueSerializer())->decode([]))
			->toThrow(CactiQueueMessageException::class, 'is not allowed');
	} finally {
		restore_error_handler();
	}

	expect($warnings)->toBe([]);
});

it('rejects objects without an explicit queue message contract', function () {
	(new CactiQueueSerializer())->encode(new CactiQueueEnvelope(new stdClass()));
})->throws(InvalidArgumentException::class, 'CactiQueueMessageInterface');

it('validates database transport envelopes before persistence', function () {
	$transport = new CactiDatabaseQueueTransport('reports');
	$invalid   = new CactiQueueEnvelope(new stdClass(), [new CactiQueueStamp('reports')]);
	$wrong     = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []), [new CactiQueueStamp('poller')]);
	$large     = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['value' => str_repeat('x', 2048)]), [new CactiQueueStamp('reports', max_payload_bytes: 1024)]);

	expect(fn () => $transport->send($invalid))->toThrow(InvalidArgumentException::class, 'CactiQueueMessageInterface')
		->and(fn () => $transport->send($wrong))->toThrow(InvalidArgumentException::class, 'cannot be sent')
		->and(fn () => $transport->send($large))->toThrow(LengthException::class, 'exceeds')
		->and(fn () => $transport->ack(new CactiQueueEnvelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'received queue envelope');
});

it('dispatches only to a registered topic handler', function () {
	$handled = null;
	api_queue_register_handler('reports.generate', function (CactiQueueMessage $message) use (&$handled) : void {
		$handled = $message->payload();
	});
	$envelope = new CactiQueueEnvelope(
		new CactiQueueMessage('reports.generate', ['report_id' => 42]),
		[new CactiQueueStamp('reports')]
	);

	api_queue_dispatch($envelope);

	expect($handled)->toBe(['report_id' => 42]);
});

it('registers handlers by message class and clears active handler state', function () {
	$handled = false;
	api_queue_register_handler(CactiQueueMessage::class, function (CactiQueueMessage $message) use (&$handled) : void {
		$handled = true;
		expect($GLOBALS['cacti_queue_active_envelopes'][spl_object_id($message)] ?? null)->toBeInstanceOf(CactiQueueEnvelope::class);
	});
	$message  = new CactiQueueMessage('reports.generate', []);
	$envelope = new CactiQueueEnvelope($message, [new CactiQueueStamp('reports')]);

	api_queue_dispatch($envelope);

	expect($handled)->toBeTrue()
		->and($GLOBALS['cacti_queue_active_envelopes'] ?? [])->not->toHaveKey(spl_object_id($message));
});

it('prefers a topic handler over a class handler', function () {
	$handled = [];
	api_queue_register_handler(CactiQueueMessage::class, function () use (&$handled) : void {
		$handled[] = 'class';
	});
	api_queue_register_handler('reports.generate', function () use (&$handled) : void {
		$handled[] = 'topic';
	});
	$envelope = new CactiQueueEnvelope(
		new CactiQueueMessage('reports.generate', []),
		[new CactiQueueStamp('reports')]
	);

	api_queue_dispatch($envelope);

	expect($handled)->toBe(['topic']);
});

it('rejects handler classes that are not queue messages', function () {
	api_queue_register_handler(InvalidCactiQueueHandlerTarget::class, static fn () => null);
})->throws(InvalidArgumentException::class, 'Handler must target');

it('rejects invalid routing names', function () {
	api_queue_publish('reports/execute', []);
})->throws(InvalidArgumentException::class, 'Invalid queue topic');

it('rejects routing names with a trailing newline', function () {
	api_queue_publish("reports.generate\n", []);
})->throws(InvalidArgumentException::class, 'Invalid queue topic');

it('ignores an invalid configured runtime transport name', function () {
	$GLOBALS['cacti_queue_transport_routes']['reports'] = "rabbitmq\n";

	expect(api_queue_transport_name('reports'))->toBe('database');
});

it('caches transports and fails closed for invalid providers', function () {
	expect(api_queue_transport('reports'))->toBe(api_queue_transport('reports'));

	api_queue_route('unknown', 'missing');
	expect(fn () => api_queue_transport('unknown'))->toThrow(RuntimeException::class, 'not registered');

	api_queue_register_transport('invalid', static fn () => new stdClass());
	api_queue_route('broken', 'invalid');
	expect(fn () => api_queue_transport('broken'))->toThrow(RuntimeException::class, 'TransportInterface');
});

it('accepts transport and handler providers contributed by plugin hooks', function () {
	$transport                                             = new CactiTestQueueTransport();
	$GLOBALS['cacti_queue_test_hooks']['queue_transports'] = static function (array $factories) use ($transport) : array {
		$factories['plugin'] = static fn () => $transport;

		return $factories;
	};
	$GLOBALS['cacti_queue_test_hooks']['queue_handlers'] = static function (array $handlers) : array {
		$handlers['plugin.handle'] = static fn () => 'handled';

		return $handlers;
	};
	api_queue_route('plugin', 'plugin');

	expect(api_queue_transport('plugin'))->toBe($transport)
		->and(api_queue_handlers())->toHaveKey('plugin.handle');
});

it('adapts broker receivers and restores missing Cacti routing stamps', function () {
	$message                     = new CactiQueueMessage('reports.generate', []);
	$envelope                    = new CactiQueueEnvelope($message);
	$this->transport->received[] = $envelope;
	$receiver                    = new CactiQueueReceiver('reports', $this->transport);
	$received                    = iterator_to_array($receiver->get())[0];

	expect(api_queue_stamp($received)->queue())->toBe('reports');
	$receiver->ack($received);
	$receiver->reject($received);
	expect($this->transport->acknowledged)->toBe([$received])
		->and($this->transport->rejected)->toBe([$received]);

	$this->transport->received[] = $received;
	expect(iterator_to_array($receiver->get())[0])->toBe($received);
});

it('rejects payloads over the configured limit', function () {
	api_queue_publish('reports.generate', ['value' => str_repeat('x', 2048)], ['max_payload_bytes' => 1024]);
})->throws(LengthException::class);

it('rejects payloads that cannot be represented as JSON data', function () {
	api_queue_publish('reports.generate', ['callback' => static fn () => true]);
})->throws(InvalidArgumentException::class, 'Queue data must contain only JSON-compatible');

it('rejects non-finite floating point payload values', function () {
	api_queue_publish('reports.generate', ['value' => INF]);
})->throws(InvalidArgumentException::class, 'Queue data must contain only JSON-compatible');

it('fails closed when no handler is registered', function () {
	$envelope = new CactiQueueEnvelope(
		new CactiQueueMessage('reports.unknown', []),
		[new CactiQueueStamp('reports')]
	);
	api_queue_dispatch($envelope);
})->throws(CactiQueueNoHandlerException::class);

it('uses unique random message identifiers', function () {
	expect(api_queue_message_id())->not->toBe(api_queue_message_id());
});

it('fails closed for invalid renewal and envelope helpers', function () {
	expect(fn () => api_queue_renew(new stdClass()))->toThrow(InvalidArgumentException::class, 'while handling')
		->and(fn () => api_queue_renew(new CactiQueueEnvelope(new stdClass(), [new CactiQueueStamp('reports')])))->toThrow(LogicException::class, 'does not support')
		->and(fn () => api_queue_stamp(new CactiQueueEnvelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'missing')
		->and(fn () => api_queue_message_id_from_envelope(new CactiQueueEnvelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'no transport');
});

it('renews leases through the optional Cacti transport capability', function () {
	$transport = new CactiTestLeaseQueueTransport();
	api_queue_register_transport('lease', static fn () => $transport);
	api_queue_route('reports', 'lease');
	$envelope = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []), [new CactiQueueStamp('reports')]);

	api_queue_renew($envelope, 90);

	expect($transport->touched)->toBe([[$envelope, 90]]);
});

it('recognizes 32 character lowercase hexadecimal message identifiers', function () {
	expect(api_queue_is_message_id('550e8400e29b41d4a716446655440000'))->toBeTrue()
		->and(api_queue_is_message_id(api_queue_message_id()))->toBeTrue()
		->and(api_queue_is_message_id("550e8400e29b41d4a716446655440000\n"))->toBeFalse()
		->and(api_queue_is_message_id('550E8400E29B41D4A716446655440000'))->toBeFalse()
		->and(api_queue_is_message_id('550e8400-e29b-41d4-a716-446655440000'))->toBeFalse();
});

it('validates metadata and stored JSON edge cases', function () {
	expect(fn () => api_queue_metadata_value(['id' => str_repeat('x', 256)], 'id'))->toThrow(LengthException::class)
		->and(fn () => api_queue_json_decode('{'))->toThrow(RuntimeException::class, 'not valid JSON')
		->and(fn () => api_queue_json_decode('1'))->toThrow(RuntimeException::class, 'decode to an array');

	$nested = [];

	for ($depth = 0; $depth < 513; $depth++) {
		$nested = [$nested];
	}
	expect(fn () => api_queue_validate_json_value($nested))->toThrow(InvalidArgumentException::class, 'maximum JSON depth');
	expect(fn () => api_queue_json_encode(["\xB1\x31"]))->toThrow(InvalidArgumentException::class, 'valid JSON');
});

it('uses configured routes, defaults, leases, and retention limits', function () {
	unset($GLOBALS['cacti_queue_transport_routes']['reports']);
	$GLOBALS['cacti_queue_test_settings'] = [
		'queue_transport_routes'          => '{"reports":"rabbitmq","bad":"invalid/name"}',
		'queue_default_transport'         => 'sqs',
		'queue_lease_seconds'             => '12',
		'queue_completed_retention_days'  => '5000',
	];

	expect(api_queue_transport_name('reports'))->toBe('rabbitmq')
		->and(api_queue_transport_name('bad'))->toBe('sqs')
		->and(api_queue_transport_name('other'))->toBe('sqs')
		->and(api_queue_lease_seconds())->toBe(30)
		->and(api_queue_completed_retention_days())->toBe(3650)
		->and(api_queue_dead_retention_days())->toBe(90);

	$GLOBALS['cacti_queue_test_settings']['queue_default_transport'] = 'invalid/name';
	expect(api_queue_transport_name('other'))->toBe('database');
});

it('falls back to the Cacti configuration and plugin hook APIs', function () {
	unset($GLOBALS['cacti_queue_config_reader'], $GLOBALS['cacti_queue_hook_provider']);

	expect(api_queue_config_option('queue_missing_setting'))->toBeString()
		->and(api_queue_apply_hook('queue_missing_hook', ['unchanged' => true]))->toBe(['unchanged' => true]);
});

it('clamps the database visibility lease to safe limits', function () {
	expect(api_queue_lease_seconds())->toBe(3600);

	api_queue_set_lease_seconds(1);
	expect(api_queue_lease_seconds())->toBe(30);

	api_queue_set_lease_seconds(100000);
	expect(api_queue_lease_seconds())->toBe(86400);
});

it('does not embed executable command dispatch in the queue API', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/api_queue.php');

	expect($source)->not->toContain('run_command')
		->and($source)->not->toContain('eval(')
		->and($source)->not->toContain('unserialize(');
});

it('guards queue command entry points against web execution', function () {
	$root = dirname(__DIR__, 4);

	foreach (['queue_worker.php', 'queue_admin.php'] as $script) {
		$source = file_get_contents("$root/cli/$script");

		expect($source)->toContain("PHP_SAPI !== 'cli'")
			->and($source)->toContain("defined('CACTI_CLI')");
	}
});

it('keeps envelopes immutable and returns the most recent stamp', function () {
	$first    = new CactiQueueStamp('reports', 10);
	$second   = new CactiQueueStamp('reports', 20);
	$envelope = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []), [$first]);
	$updated  = $envelope->with($second);

	expect($envelope->last(CactiQueueStamp::class))->toBe($first)
		->and($updated->last(CactiQueueStamp::class))->toBe($second)
		->and($updated->withoutAll(CactiQueueStamp::class)->last(CactiQueueStamp::class))->toBeNull()
		->and($updated->last(CactiQueueStamp::class))->toBe($second)
		->and($updated->last(CactiQueueReceiptStamp::class))->toBeNull();
});

it('reports the redelivery count of a receipt', function () {
	expect((new CactiQueueReceiptStamp(1, 'token', 1))->redeliveryCount())->toBe(0)
		->and((new CactiQueueReceiptStamp(1, 'token', 4))->redeliveryCount())->toBe(3);
});

it('acknowledges handled deliveries and rejects failed ones with the failure reason', function () {
	api_queue_register_handler('reports.generate', static function () : void {
	});
	api_queue_register_handler('reports.fail', static function () : void {
		throw new RuntimeException("handler\nbroke");
	});
	$ok                        = new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', []), [new CactiQueueStamp('reports')]);
	$bad                       = new CactiQueueEnvelope(new CactiQueueMessage('reports.fail', []), [new CactiQueueStamp('reports')]);
	$this->transport->received = [$ok, $bad];
	$worker                    = new CactiQueueWorker(new CactiQueueReceiver('reports', $this->transport));

	$worker->run(true);

	expect($this->transport->acknowledged)->toBe([$ok])
		->and($this->transport->rejected)->toHaveCount(1)
		->and($this->transport->rejected[0]->last(CactiQueueFailureStamp::class)->reason())->toBe("handler\nbroke")
		->and($worker->failed())->toBeTrue()
		->and($GLOBALS['cacti_queue_test_log'])->toBe(['Queue message failed: handler broke']);
});

it('heartbeats its registered process once per poll', function () {
	$beats  = 0;
	$worker = new CactiQueueWorker($this->transport, static function () use (&$beats) : void {
		$beats++;
	});

	$worker->run(true);

	expect($beats)->toBe(1);
});

it('rejects a delivery that has no handler instead of stopping the worker', function () {
	$this->transport->received = [new CactiQueueEnvelope(new CactiQueueMessage('reports.unknown', []), [new CactiQueueStamp('reports')])];
	$worker                    = new CactiQueueWorker($this->transport);

	$worker->run(true);

	expect($this->transport->rejected)->toHaveCount(1)
		->and($worker->failed())->toBeTrue();
});

it('logs a stale receipt as a duplicate delivery and keeps consuming', function () {
	$transport = new class extends CactiTestQueueTransport {
		public function ack(CactiQueueEnvelope $envelope) : void {
			parent::ack($envelope);

			throw new CactiQueueStaleReceiptException('lease expired');
		}
	};
	api_queue_register_handler('reports.generate', static function () : void {
	});
	$transport->received = [
		new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['n' => 1]), [new CactiQueueStamp('reports')]),
		new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['n' => 2]), [new CactiQueueStamp('reports')]),
	];
	$worker = new CactiQueueWorker($transport);

	$worker->run(true);

	expect($transport->acknowledged)->toHaveCount(2)
		->and($worker->failed())->toBeFalse()
		->and($GLOBALS['cacti_queue_test_log'][0])->toContain('reclaimed before it was settled');
});

it('survives an undecodable provider delivery and keeps polling', function () {
	$receiver = new class implements CactiQueueReceiverInterface {
		public int $polls = 0;

		public function get() : iterable {
			$this->polls++;

			if ($this->polls === 1) {
				throw new CactiQueueMessageException('poison');
			}

			return [];
		}

		public function ack(CactiQueueEnvelope $envelope) : void {
		}

		public function reject(CactiQueueEnvelope $envelope) : void {
		}
	};
	$worker = new CactiQueueWorker($receiver);

	$worker->run(false, 1, 1);

	expect($receiver->polls)->toBeGreaterThan(1)
		->and($worker->failed())->toBeTrue()
		->and($GLOBALS['cacti_queue_test_log'][0])->toContain('could not be decoded: poison');
});

it('stops after the current delivery when asked to stop', function () {
	$worker   = null;
	$receiver = new class implements CactiQueueReceiverInterface {
		public array $acknowledged = [];

		public function get() : iterable {
			return [
				new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['n' => 1]), [new CactiQueueStamp('reports')]),
				new CactiQueueEnvelope(new CactiQueueMessage('reports.generate', ['n' => 2]), [new CactiQueueStamp('reports')]),
			];
		}

		public function ack(CactiQueueEnvelope $envelope) : void {
			$this->acknowledged[] = $envelope;
		}

		public function reject(CactiQueueEnvelope $envelope) : void {
		}
	};
	api_queue_register_handler('reports.generate', static function () use (&$worker) : void {
		$worker->stop();
	});
	$worker = new CactiQueueWorker($receiver);

	$worker->run();

	expect($receiver->acknowledged)->toHaveCount(1);
});

it('writes single-line queue log entries through cacti_log by default', function () {
	unset($GLOBALS['cacti_queue_log_writer']);

	expect(fn () => api_queue_log("line one\nline two"))->not->toThrow(Throwable::class);
});

it('has no Composer dependency and is not loaded on every page', function () {
	$root = dirname(__DIR__, 4);

	expect(file_get_contents("$root/lib/api_queue.php"))->not->toContain('Symfony\\')
		->and(file_get_contents("$root/include/global.php"))->not->toContain('api_queue.php');
});

/**
 * Clear the queue API's process globals so each test starts from defaults.
 *
 * @return void
 */
function queue_api_test_reset_runtime() : void {
	unset(
		$GLOBALS['cacti_queue_transport_factories'],
		$GLOBALS['cacti_queue_transport_instances'],
		$GLOBALS['cacti_queue_transport_routes'],
		$GLOBALS['cacti_queue_send_bus'],
		$GLOBALS['cacti_queue_active_envelopes'],
		$GLOBALS['cacti_queue_test_settings'],
		$GLOBALS['cacti_queue_test_hooks'],
		$GLOBALS['cacti_queue_config_reader'],
		$GLOBALS['cacti_queue_hook_provider'],
		$GLOBALS['cacti_queue_handlers'],
		$GLOBALS['cacti_queue_lease_seconds'],
		$GLOBALS['cacti_queue_log_writer'],
		$GLOBALS['cacti_queue_test_log']
	);
}
