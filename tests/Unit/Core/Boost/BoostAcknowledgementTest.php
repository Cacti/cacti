<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/boost.php';

/**
 * Statement that applies MySQL's session time zone to FROM_UNIXTIME() arguments.
 *
 * With time_zone set to America/New_York, MySQL renders both instants of the
 * 2026-11-01 fall-back hour as the same local time and stores that local time
 * back into a TIMESTAMP as the first (EDT) instant. SQLite has no session time
 * zone, so the lossy round trip is modeled here on the bound epoch values.
 */
class BoostAcknowledgementStatement extends PDOStatement {
	/** First second of the repeated (EST) 01:00 hour on 2026-11-01. */
	public const FALL_BACK_START = 1793512800;

	protected function __construct(private bool $from_unixtime) {
	}

	/**
	 * Execute after collapsing repeated-hour epochs the way MySQL does.
	 *
	 * @param array<int|string, mixed>|null $params the bound values
	 *
	 * @return bool whether the statement executed
	 */
	public function execute(?array $params = null): bool {
		if ($this->from_unixtime && $params !== null) {
			foreach ($params as $index => $value) {
				if (is_int($value) && $value >= self::FALL_BACK_START && $value < self::FALL_BACK_START + 3600) {
					$params[$index] = $value - 3600;
				}
			}
		}

		return parent::execute($params);
	}
}

class BoostAcknowledgementPDO extends FakeMySQLPDO {
	public array $batch_sizes = [];
	public string $reject     = '';

	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (str_starts_with($query, 'INSERT IGNORE INTO `poller_output_boost_acked_')) {
			$this->batch_sizes[] = substr_count($query, '?') / 2;
		}

		if ($this->reject !== '' && str_contains($query, $this->reject)) {
			// Prepares cleanly and fails on execution, like a server-side rejection.
			$query = "INSERT INTO poller_output_boost VALUES (1, 'in', '2023-11-14 22:13:20')";
		}

		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BoostAcknowledgementStatement::class, [str_contains($query, 'FROM_UNIXTIME(?)')]]);

		return parent::prepare(str_replace(['FROM_UNIXTIME(?)', 'DROP TEMPORARY TABLE'], ["datetime(?, 'unixepoch')", 'DROP TABLE'], $query), $options);
	}
}

/**
 * Point the default database session at a fake connection for one test.
 *
 * @param BoostAcknowledgementPDO $connection the connection to use
 *
 * @return callable restores the previous session
 */
function boost_acknowledgement_use(BoostAcknowledgementPDO $connection): callable {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$key                     = "$database_hostname:$database_port:$database_default";
	$previous                = $database_sessions[$key] ?? null;
	$database_sessions[$key] = $connection;

	return function () use ($key, $previous) {
		global $database_sessions;

		if ($previous === null) {
			unset($database_sessions[$key]);
		} else {
			$database_sessions[$key] = $previous;
		}
	};
}

test('Boost acknowledgement removes only the fetched keys in one statement', function (bool $failure) {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$connection = new BoostAcknowledgementPDO();
	$connection->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, rrd_name TEXT, time TEXT, PRIMARY KEY (local_data_id, rrd_name, time))');
	$insert = $connection->prepare('INSERT INTO poller_output_boost VALUES (?, ?, ?)');
	$rows   = [];

	for ($index = 0; $index < 999; $index++) {
		$timestamp = 1700000000 + $index;
		$rows[]    = ['timestamp' => $timestamp, 'rrd_name' => 'in'];
		$insert->execute([1, 'in', gmdate('Y-m-d H:i:s', $timestamp)]);
	}

	foreach (['in', 'out'] as $name) {
		$rows[] = ['timestamp' => 1700000999, 'rrd_name' => $name];
		$insert->execute([1, $name, gmdate('Y-m-d H:i:s', 1700000999)]);
	}

	$insert->execute([1, 'late', gmdate('Y-m-d H:i:s', 1700001000)]);
	$insert->execute([2, 'in', gmdate('Y-m-d H:i:s', 1700000000)]);

	if ($failure) {
		$connection->exec("CREATE TRIGGER reject_acknowledgement BEFORE DELETE ON poller_output_boost WHEN OLD.time = '" . gmdate('Y-m-d H:i:s', 1700000999) . "' BEGIN SELECT RAISE(ABORT, 'Injected acknowledgement failure.'); END");
	}
	$key                     = "$database_hostname:$database_port:$database_default";
	$previous                = $database_sessions[$key] ?? null;
	$database_sessions[$key] = $connection;

	try {
		expect(boost_delete_output_keys(1, $rows))->toBe(!$failure);
		expect($connection->batch_sizes)->toBe([500, 500, 1]);
		$remaining = $connection->query('SELECT * FROM poller_output_boost ORDER BY local_data_id, time, rrd_name')->fetchAll();
		// A rejected delete is atomic: every fetched row stays in poller_output_boost for the next run.
		expect($remaining)->toHaveCount($failure ? 1003 : 2);
		expect(array_column(array_slice($remaining, -2), 'rrd_name'))->toBe(['late', 'in']);
	} finally {
		if ($previous === null) {
			unset($database_sessions[$key]);
		} else {
			$database_sessions[$key] = $previous;
		}
	}
})->with([false, true]);

test('Boost acknowledgement accepts an empty fetched batch', function () {
	expect(boost_delete_output_keys(1, []))->toBeTrue();
});

test('Boost acknowledgement keeps every row when staging the keys fails', function (string $statement) {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$connection = new BoostAcknowledgementPDO();
	$connection->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, rrd_name TEXT, time TEXT, PRIMARY KEY (local_data_id, rrd_name, time))');
	$connection->exec("INSERT INTO poller_output_boost VALUES (1, 'in', '2023-11-14 22:13:20')");
	$connection->reject      = $statement;
	$key                     = "$database_hostname:$database_port:$database_default";
	$previous                = $database_sessions[$key] ?? null;
	$database_sessions[$key] = $connection;

	try {
		expect(boost_delete_output_keys(1, [['timestamp' => 1700000000, 'rrd_name' => 'in']]))->toBeFalse();
		expect($connection->query('SELECT COUNT(*) FROM poller_output_boost')->fetchColumn())->toBe(1);
	} finally {
		if ($previous === null) {
			unset($database_sessions[$key]);
		} else {
			$database_sessions[$key] = $previous;
		}
	}
})->with(['CREATE TEMPORARY TABLE', 'INSERT IGNORE INTO `poller_output_boost_acked_']);

test('Boost acknowledgement deletes both instants of a DST fall-back hour', function () {
	$connection = new BoostAcknowledgementPDO();
	$connection->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, rrd_name TEXT, time TEXT, PRIMARY KEY (local_data_id, rrd_name, time))');
	$insert = $connection->prepare('INSERT INTO poller_output_boost VALUES (?, ?, ?)');

	// 01:30 EDT and 01:30 EST share a local time but are an hour apart in UTC.
	$edt  = BoostAcknowledgementStatement::FALL_BACK_START - 1800;
	$est  = BoostAcknowledgementStatement::FALL_BACK_START + 1800;
	$late = BoostAcknowledgementStatement::FALL_BACK_START + 5400;

	foreach ([$edt, $est, $late] as $timestamp) {
		$insert->execute([1, 'in', gmdate('Y-m-d H:i:s', $timestamp)]);
	}

	$restore = boost_acknowledgement_use($connection);

	try {
		$fetched = [['timestamp' => $edt, 'rrd_name' => 'in'], ['timestamp' => $est, 'rrd_name' => 'in']];

		expect(boost_delete_output_keys(1, $fetched))->toBeTrue();
		expect($connection->query('SELECT time FROM poller_output_boost')->fetchAll(PDO::FETCH_COLUMN))->toBe([gmdate('Y-m-d H:i:s', $late)]);
	} finally {
		$restore();
	}
});
