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

require_once dirname(__DIR__, 2) . '/lib/api_queue.php';

if (!function_exists('read_config_option')) {
	function read_config_option(string $name) : string {
		return (string) ($GLOBALS['cacti_queue_test_settings'][$name] ?? '');
	}
}

if (!function_exists('api_plugin_hook_function')) {
	function api_plugin_hook_function(string $hook, mixed $value) : mixed {
		$callback = $GLOBALS['cacti_queue_test_hooks'][$hook] ?? null;

		return is_callable($callback) ? $callback($value) : $value;
	}
}

class CactiTestQueueTransport implements Symfony\Component\Messenger\Transport\TransportInterface {
	/** @var Symfony\Component\Messenger\Envelope[] */
	public array $sent         = [];
	public array $received     = [];
	public array $acknowledged = [];
	public array $rejected     = [];

	public function send(Symfony\Component\Messenger\Envelope $envelope) : Symfony\Component\Messenger\Envelope {
		$this->sent[] = $envelope;

		return $envelope->with(new Symfony\Component\Messenger\Stamp\TransportMessageIdStamp(api_queue_message_id()));
	}

	public function get() : iterable {
		$received       = $this->received;
		$this->received = [];

		return $received;
	}

	public function ack(Symfony\Component\Messenger\Envelope $envelope) : void {
		$this->acknowledged[] = $envelope;
	}

	public function reject(Symfony\Component\Messenger\Envelope $envelope) : void {
		$this->rejected[] = $envelope;
	}
}

final class CactiTestLeaseQueueTransport extends CactiTestQueueTransport implements CactiQueueLeaseAwareTransportInterface {
	public array $touched = [];

	public function touch(Symfony\Component\Messenger\Envelope $envelope, int $lease_seconds) : void {
		$this->touched[] = [$envelope, $lease_seconds];
	}
}

final class InvalidCactiQueueHandlerTarget {
}

beforeEach(function () {
	queue_api_test_reset_runtime();
	$GLOBALS['cacti_queue_config_reader'] = static fn (string $name) : string => (string) ($GLOBALS['cacti_queue_test_settings'][$name] ?? '');
	$GLOBALS['cacti_queue_hook_provider'] = static function (string $hook, mixed $value) : mixed {
		$callback = $GLOBALS['cacti_queue_test_hooks'][$hook] ?? null;

		return is_callable($callback) ? $callback($value) : $value;
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
		->and(api_queue_message_id_from_envelope($envelope))->toMatch('/^[0-9a-f-]{36}$/');
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

	$delay = $envelope->last(Symfony\Component\Messenger\Stamp\DelayStamp::class);
	expect($delay)->toBeInstanceOf(Symfony\Component\Messenger\Stamp\DelayStamp::class)
		->and($delay->getDelay())->toBe(31536000000);
});

it('clamps delivery policy and rejects oversized correlation identifiers', function () {
	$stamp = new CactiQueueStamp('reports', -1, 101, 'request', 1);

	expect($stamp->priority())->toBe(0)
		->and($stamp->maxAttempts())->toBe(100)
		->and($stamp->correlationId())->toBe('request')
		->and($stamp->maxPayloadBytes())->toBe(1024);

	new CactiQueueStamp('reports', correlation_id: str_repeat('x', 256));
})->throws(LengthException::class, 'correlation_id');

it('validates and round trips safe queue messages', function () {
	$serializer = new CactiQueueSerializer();
	$encoded    = $serializer->encode(new Symfony\Component\Messenger\Envelope(new CactiQueueMessage('reports.generate', ['id' => 4])));
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
	(new CactiQueueSerializer())->encode(new Symfony\Component\Messenger\Envelope(new stdClass()));
})->throws(InvalidArgumentException::class, 'CactiQueueMessageInterface');

it('validates database transport envelopes before persistence', function () {
	$transport = new CactiDatabaseQueueTransport('reports');
	$wrong     = new Symfony\Component\Messenger\Envelope(new CactiQueueMessage('reports.generate', []), [new CactiQueueStamp('poller')]);
	$large     = new Symfony\Component\Messenger\Envelope(new CactiQueueMessage('reports.generate', ['value' => str_repeat('x', 2048)]), [new CactiQueueStamp('reports', max_payload_bytes: 1024)]);

	expect(fn () => $transport->send($wrong))->toThrow(InvalidArgumentException::class, 'cannot be sent')
		->and(fn () => $transport->send($large))->toThrow(LengthException::class, 'exceeds')
		->and(fn () => $transport->ack(new Symfony\Component\Messenger\Envelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'received queue envelope');
});

it('dispatches only to a registered topic handler', function () {
	$handled = null;
	api_queue_register_handler('reports.generate', function (CactiQueueMessage $message) use (&$handled) : void {
		$handled = $message->payload();
	});
	$envelope = new Symfony\Component\Messenger\Envelope(
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
		expect($GLOBALS['cacti_queue_active_envelopes'][spl_object_id($message)] ?? null)->toBeInstanceOf(Symfony\Component\Messenger\Envelope::class);
	});
	$message  = new CactiQueueMessage('reports.generate', []);
	$envelope = new Symfony\Component\Messenger\Envelope($message, [new CactiQueueStamp('reports')]);

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
	$envelope = new Symfony\Component\Messenger\Envelope(
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
	$envelope                    = new Symfony\Component\Messenger\Envelope($message);
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
	$envelope = new Symfony\Component\Messenger\Envelope(
		new CactiQueueMessage('reports.unknown', []),
		[new CactiQueueStamp('reports')]
	);
	api_queue_dispatch($envelope);
})->throws(Symfony\Component\Messenger\Exception\NoHandlerForMessageException::class);

it('uses unique UUID message identifiers', function () {
	expect(api_queue_message_id())->not->toBe(api_queue_message_id());
});

it('fails closed for invalid renewal and envelope helpers', function () {
	expect(fn () => api_queue_renew(new stdClass()))->toThrow(InvalidArgumentException::class, 'while handling')
		->and(fn () => api_queue_renew(new Symfony\Component\Messenger\Envelope(new stdClass(), [new CactiQueueStamp('reports')])))->toThrow(LogicException::class, 'does not support')
		->and(fn () => api_queue_stamp(new Symfony\Component\Messenger\Envelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'missing')
		->and(fn () => api_queue_message_id_from_envelope(new Symfony\Component\Messenger\Envelope(new stdClass())))->toThrow(InvalidArgumentException::class, 'no transport');
});

it('renews leases through the optional Cacti transport capability', function () {
	$transport = new CactiTestLeaseQueueTransport();
	api_queue_register_transport('lease', static fn () => $transport);
	api_queue_route('reports', 'lease');
	$envelope = new Symfony\Component\Messenger\Envelope(new CactiQueueMessage('reports.generate', []), [new CactiQueueStamp('reports')]);

	api_queue_renew($envelope, 90);

	expect($transport->touched)->toBe([[$envelope, 90]]);
});

it('recognizes canonical UUID message identifiers', function () {
	expect(api_queue_is_message_id('550e8400-e29b-41d4-a716-446655440000'))->toBeTrue()
		->and(api_queue_is_message_id(api_queue_message_id()))->toBeTrue()
		->and(api_queue_is_message_id("550e8400-e29b-41d4-a716-446655440000\n"))->toBeFalse();
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
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/api_queue.php');

	expect($source)->not->toContain('run_command')
		->and($source)->not->toContain('eval(')
		->and($source)->not->toContain('unserialize(');
});

it('guards queue command entry points against web execution', function () {
	$root = dirname(__DIR__, 2);

	foreach (['queue_worker.php', 'queue_admin.php'] as $script) {
		$source = file_get_contents("$root/cli/$script");

		expect($source)->toContain("PHP_SAPI !== 'cli'")
			->and($source)->toContain("defined('CACTI_CLI')");
	}
});

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
		$GLOBALS['cacti_queue_lease_seconds']
	);
}
