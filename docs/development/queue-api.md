# Queue API

Cacti's queue API provides a transport-neutral contract for durable asynchronous work. The built-in database transport works without another service; plugins can provide RabbitMQ, Apache Kafka, Redis Streams, Amazon SQS, or another broker without changing producers and handlers.

The API requires PHP 8.1. Its transport lifecycle deliberately mirrors Symfony Messenger's `send`, `get`, `ack`, and `reject` model. A provider plugin may therefore adapt a Symfony Messenger transport, while Cacti core avoids imposing Messenger and its dependency tree on installations that use only the database transport.

## Publishing and handling messages

Messages contain JSON-compatible data, never executable PHP callbacks or shell commands.

```php
require_once(CACTI_PATH_LIBRARY . '/api_queue.php');

api_queue_publish('reports.generate', ['report_id' => 42], [
	'priority'       => 75,
	'max_attempts'   => 5,
	'correlation_id' => 'web-request-123',
]);

api_queue_register_handler(
	'reports.generate',
	static function (CactiQueueEnvelope $message) : void {
		generate_report((int) $message->payload()['report_id']);
	}
);
```

Topics and queue names must use lowercase letters and numbers separated by `.`, `_`, or `-`. Unless explicitly supplied, the queue is the first segment of the topic (`reports.generate` uses `reports`). Payloads default to a 256 KiB maximum, delayed delivery is capped at one year, and lower numeric priority values run first.

Run a worker for each queue:

```console
php cli/queue_worker.php --queue=reports
php cli/queue_worker.php --queue=reports --once
```

Without `--once` or a positive `--time-limit`, the worker runs until it receives SIGTERM or SIGINT. Run it under the operating system's service manager and restart it on failure. Transient transport errors use the configured sleep interval and terminate the worker only after five consecutive failures.

The repository includes `service/cacti-queue@.service` as a hardened systemd template. Adjust the literal PHP and Cacti paths for the installation, add a packaging-specific `User` and `Group` override, then enable one instance per queue (for example, `cacti-queue@reports.service`).

Workers acknowledge successful handling. Exceptions cause exponential retries until `max_attempts`, after which the database transport records the message as `dead`. Expired leases are reclaimed only while delivery attempts remain; exhausted messages are moved to the dead-letter state. Delivery is at least once, so handlers must be idempotent.

The database visibility lease defaults to 3600 seconds and can be changed with `queue_lease_seconds` or `--lease=seconds`. Set it above the longest normal handler duration. A long-running handler must call `api_queue_renew($message)` periodically (typically before one third of the lease elapses); stale acknowledgements, retries, and renewals fail instead of silently changing a reclaimed message.

Primary-poller maintenance removes completed messages after 7 days and dead messages after 90 days in bounded batches of at most 10,000 rows per run. Completed payloads and metadata remain available during retention for diagnosis. Override the windows with `queue_completed_retention_days` and `queue_dead_retention_days`. Inspect health and dead letters, requeue a repaired message, or run retention explicitly with:

```console
php cli/queue_admin.php --queue=reports --health
php cli/queue_admin.php --queue=reports --dead --limit=50
php cli/queue_admin.php --queue=reports --requeue=550e8400-e29b-41d4-a716-446655440000
php cli/queue_admin.php --queue=reports --purge
```

## Provider plugins

A plugin registers a named factory through the `queue_transports` hook. The resulting object must implement `CactiQueueTransportInterface`, including `touch()` for renewing a broker visibility timeout or database lease.

```php
function rabbitmq_queue_transports(array $transports) : array {
	$transports['rabbitmq'] = static function (string $queue) : CactiQueueTransportInterface {
		return new RabbitMqQueueTransport($queue);
	};

	return $transports;
}

api_plugin_register_hook(
	'rabbitmq',
	'queue_transports',
	'rabbitmq_queue_transports',
	'queue.php'
);
```

A RabbitMQ or SQS adapter maps one Cacti queue to a broker queue. A Kafka adapter normally maps the Cacti queue to a Kafka topic and uses the message topic as a header; its `ack` operation commits the consumer offset. Broker-specific connection, serialization, and redelivery details remain inside the adapter.

Handlers may also be contributed through the `queue_handlers` hook by returning the topic-to-callable map with plugin handlers added.

## Routing

`queue_default_transport` selects the default provider and `queue_transport_routes` contains an optional JSON object for per-queue routing:

```json
{
	"reports": "rabbitmq",
	"discovery": "kafka"
}
```

Routes may also be set at runtime with `api_queue_route()`. If no route is configured, Cacti uses the built-in `database` transport.

Transport implementations should preserve the message ID and metadata, provide durable publication, make acknowledgement explicit, and reclaim deliveries whose lease or broker visibility timeout expires. `health()` should return non-sensitive operational data suitable for diagnostics.

## Verification

The PHP 8.1 CI job runs the queue unit and MySQL 8.4 integration suites with Xdebug, publishes Clover and text reports, and enforces at least 60% line coverage for `lib/api_queue.php`.

Run the full producer-to-worker path in disposable PHP 8.1 and MySQL 8.4 containers with:

```console
tests/e2e/queue/run.sh
```

The E2E test installs a test-only plugin handler, publishes two messages through `api_queue_publish()`, starts two concurrent worker processes, and verifies distinct handling, lease renewal, acknowledgement state, admin health, and invalid CLI argument handling. Its Compose project, database, network, and volumes are removed on exit.
