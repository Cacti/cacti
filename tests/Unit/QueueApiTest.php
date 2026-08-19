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

final class CactiTestQueueTransport implements CactiQueueTransportInterface {
	/** @var CactiQueueEnvelope[] */
	public array $sent = [];

	public function send(CactiQueueEnvelope $envelope) : CactiQueueEnvelope {
		$this->sent[] = $envelope;

		return $envelope;
	}

	public function get() : iterable {
		return [];
	}

	public function ack(CactiQueueEnvelope $envelope) : void {
	}

	public function reject(CactiQueueEnvelope $envelope, string $reason) : void {
	}

	public function retry(CactiQueueEnvelope $envelope, int $delay_seconds, string $reason) : void {
	}

	public function touch(CactiQueueEnvelope $envelope, int $lease_seconds) : void {
	}

	public function health() : array {
		return ['transport' => 'test'];
	}
}

beforeEach(function () {
	queue_api_test_reset_runtime();
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

	expect($this->transport->sent)->toHaveCount(1)
		->and($envelope->queue())->toBe('reports')
		->and($envelope->topic())->toBe('reports.generate')
		->and($envelope->payload())->toBe(['report_id' => 42])
		->and($envelope->metadata()['schema'])->toBe('cacti.queue.v1')
		->and($envelope->metadata()['priority'])->toBe(75)
		->and($envelope->messageId())->toMatch('/^[0-9a-f-]{36}$/');
});

it('caps delayed delivery at one year', function () {
	$envelope = api_queue_publish('reports.generate', [], ['delay' => PHP_INT_MAX]);

	expect($envelope->metadata()['delay'])->toBe(31536000);
});

it('dispatches only to a registered topic handler', function () {
	$handled = null;
	api_queue_register_handler('reports.generate', function (CactiQueueEnvelope $envelope) use (&$handled) : void {
		$handled = $envelope->payload();
	});
	$envelope = new CactiQueueEnvelope('message-1', 'reports', 'reports.generate', ['report_id' => 42]);

	api_queue_dispatch($envelope);

	expect($handled)->toBe(['report_id' => 42]);
});

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
	$envelope = new CactiQueueEnvelope('message-1', 'reports', 'reports.unknown', []);
	api_queue_dispatch($envelope);
})->throws(RuntimeException::class, 'No handler is registered');

it('uses unique UUID message identifiers', function () {
	expect(api_queue_message_id())->not->toBe(api_queue_message_id());
});

it('adds the delivery attempt to a received envelope', function () {
	$received = (new CactiQueueEnvelope(
		'550e8400-e29b-41d4-a716-446655440000',
		'reports',
		'reports.generate',
		[]
	))->withReceipt(42, str_repeat('a', 48), 3);

	expect($received->receiptId())->toBe(42)
		->and($received->metadata()['attempt'])->toBe(3);
});

it('rejects caller supplied identifiers that are not UUID version 4', function () {
	api_queue_publish('reports.generate', [], ['message_id' => 'message-1']);
})->throws(InvalidArgumentException::class, 'canonical UUID version 4');

it('recognizes only canonical UUID version 4 message identifiers', function () {
	expect(api_queue_is_message_id('550e8400-e29b-41d4-a716-446655440000'))->toBeTrue()
		->and(api_queue_is_message_id('550e8400-e29b-11d4-a716-446655440000'))->toBeFalse()
		->and(api_queue_is_message_id("550e8400-e29b-41d4-a716-446655440000\n"))->toBeFalse();
});

it('clamps the database visibility lease to safe limits', function () {
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
		$GLOBALS['cacti_queue_handlers'],
		$GLOBALS['cacti_queue_lease_seconds']
	);
}
