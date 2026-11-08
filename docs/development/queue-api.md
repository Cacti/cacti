# Queue API

Cacti's queue API is a small, self-contained queue interface in `lib/api_queue.php`. It has no Composer dependency. The built-in database transport works without another service; plugins can provide RabbitMQ, Apache Kafka, Redis Streams, Amazon SQS, or another broker without changing producers and handlers.

The API requires Cacti develop and PHP 8.3 or later. Provider plugins implement `CactiQueueTransportInterface` (`send`, `get`, `ack`, `reject`); Cacti supplies routing, safe serialization, handler registration, database defaults, and worker lifecycle management. Cacti does not load the file on every page: require it where it is used.

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
	static function (CactiQueueMessage $message) : void {
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

Without `--once` or a positive `--time-limit`, the worker runs until it receives SIGTERM or SIGINT. Run it under the operating system's service manager and restart it on failure. The worker is `CactiQueueWorker`: it claims a delivery, runs its handler, then acknowledges or rejects it, with Cacti logging, signal handling, and database visibility leases around it. A message that cannot be decoded is dead-lettered and logged, and a stale receipt after a lease expiry is logged as a duplicate delivery; neither stops the worker.

The repository includes `service/cacti-queue@.service` as a hardened systemd template. It runs as `apache:apache`, matching the existing Cacti daemon service. Adjust the literal PHP and Cacti paths and override the account for installations that use another Cacti service user, then enable one instance per queue (for example, `cacti-queue@reports.service`).

A worker only decodes a message whose class is already loaded in that process and has a handler registered in that worker (`api_queue_register_handler()` or the `queue_handlers` hook). Any other message type, including a class that has not been loaded, is dead-lettered with the reason `Stored queue message type is not allowed.` Load plugin message classes and register their handlers before the worker starts consuming.

The consumer, not the message, sets the payload limit. Every raw body is checked against `queue_max_payload_bytes` (default 262144, minimum 1024, maximum 67108864) before it is decoded, for the database and provider transports alike. A message header can lower that limit for its own body but cannot raise it.

Workers acknowledge successful handling. Exceptions cause retries with exponential backoff (2^attempts seconds, at most one hour) until `max_attempts`, after which the database transport records the message as `dead`. A received envelope's `CactiQueueReceiptStamp` reports the attempt number and `redeliveryCount()`. Database workers claim with `SELECT ... FOR UPDATE SKIP LOCKED` inside a transaction, so concurrent workers take different messages without waiting on each other. Expired leases are reclaimed only while delivery attempts remain; exhausted messages are moved to the dead-letter state. Delivery is at least once, so handlers must be idempotent.

The database visibility lease defaults to 3600 seconds and can be changed with `queue_lease_seconds` or `--lease=seconds`. Set it above the longest normal handler duration. A long-running handler must call `api_queue_renew($message)` periodically (typically before one third of the lease elapses); stale acknowledgements, retries, and renewals fail instead of silently changing a reclaimed message.

Primary-poller maintenance removes completed messages after 7 days and dead messages after 90 days in bounded batches of at most 10,000 rows per run. Completed payloads and metadata remain available during retention for diagnosis. Override the windows with `queue_completed_retention_days` and `queue_dead_retention_days`. Inspect health and dead letters, requeue a repaired message, or run retention explicitly with:

```console
php cli/queue_admin.php --queue=reports --health
php cli/queue_admin.php --queue=reports --dead --limit=50
php cli/queue_admin.php --queue=reports --requeue=550e8400e29b41d4a716446655440000
php cli/queue_admin.php --queue=reports --purge
```

## Provider plugins

A plugin registers a named factory through the `queue_transports` hook. The resulting object must implement `CactiQueueTransportInterface`. Encode and decode broker data with `CactiQueueSerializer` or an equivalently strict JSON serializer implementing `CactiQueueSerializerInterface`; do not use native PHP object serialization for broker data.

```php
function rabbitmq_queue_transports(array $transports) : array {
	$transports['rabbitmq'] = static function (string $queue) : CactiQueueTransportInterface {
		return create_rabbitmq_transport($queue, new CactiQueueSerializer());
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
When both a topic handler and a message-class handler are registered for the same message, the more specific topic handler takes precedence and only that handler runs.
A custom message class is accepted from a broker only after it is registered with `api_queue_register_handler()` or the `queue_handlers` hook; the broker-supplied type is never autoloaded.

## Routing

`queue_default_transport` selects the default provider and `queue_transport_routes` contains an optional JSON object for per-queue routing:

```json
{
	"reports": "rabbitmq",
	"discovery": "kafka"
}
```

Routes may also be set at runtime with `api_queue_route()`. If no route is configured, Cacti uses the built-in `database` transport.

One installation may route different queues to different providers at the same time. Transport implementations should provide durable publication, make acknowledgement explicit, and reclaim deliveries whose lease or broker visibility timeout expires. Providers may additionally implement `CactiQueueAdminTransportInterface` to expose non-sensitive health and dead-letter operations through `queue_admin.php`.

## Verification

The PHP 8.3 CI job runs the queue unit and MySQL 8.4 integration suites with Xdebug, publishes a text report, and enforces 100% line coverage for `lib/api_queue.php`.

Run the full producer-to-worker path in disposable PHP and MySQL 8.4 containers with:

```console
tests/e2e/queue/run.sh
```

The E2E test installs a test-only plugin handler, publishes two messages through `api_queue_publish()`, starts two concurrent worker processes, and verifies distinct handling, lease renewal, acknowledgement state, admin health, and invalid CLI argument handling. Its Compose project, database, network, and volumes are removed on exit.
