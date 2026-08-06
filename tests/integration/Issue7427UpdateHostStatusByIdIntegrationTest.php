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
*/

/*
 * Integration coverage for issue #7427: update_host_status() wrote its
 * availability row with "WHERE hostname = ?". Cacti does not require unique
 * hostnames, so polling one device rewrote the status of every device
 * sharing its hostname -- sibling devices flapped between up and down on
 * alternating polls. The fix keys the write on $host['id'].
 *
 * This calls the real update_host_status() from lib/functions.php against a
 * real (sqlite-backed) connection, so the shipped UPDATE runs rather than a
 * re-typed copy of it, and the sibling row is read back to prove it survived.
 */

require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/ping.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';

/* include/global.php decides these from the SAPI and the settings table, and
   pulling it in would need a live Cacti install. CLI is what the poller runs
   as, and the log path only has to be somewhere writable. */
if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir());
}

/**
 * sqlite reports rowCount() as 0 for SELECT, which makes
 * db_fetch_row_return() discard every row, and it has no FROM_UNIXTIME().
 * Both gaps are local to reading a host row and writing its status dates, so
 * they are patched here rather than in the shared FakeMySQLPDO.
 */
class HostStatusStatement extends PDOStatement {
	/** @var array<int,array<string,mixed>>|null rows of a SELECT, null otherwise */
	private ?array $rows = null;

	protected function __construct() {
	}

	public function execute(?array $params = null) : bool {
		$executed = parent::execute($params);

		$this->rows = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;

		return $executed;
	}

	public function rowCount() : int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args) : array {
		return $this->rows ?? parent::fetchAll($mode, ...$args);
	}
}

/**
 * A FakeMySQLPDO that hands out counting statements and rewrites FROM_UNIXTIME.
 */
class HostStatusPDO extends FakeMySQLPDO {
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [HostStatusStatement::class, []]);
	}

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		return parent::prepare(preg_replace(
			'/FROM_UNIXTIME\s*\(([^)]+)\)/i',
			"datetime($1, 'unixepoch')",
			$query
		), $options);
	}
}

/**
 * Seeds two devices behind one hostname, which is what the bug needs to show.
 *
 * @param string $hostname The hostname both devices share.
 *
 * @return HostStatusPDO A connection holding the seeded schema.
 */
function host_status_seed(string $hostname = 'dup.example.net') : HostStatusPDO {
	$conn = new HostStatusPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES
		('ping_failure_count', '1'),
		('ping_recovery_count', '1')");

	$conn->exec('CREATE TABLE host (
		id INTEGER PRIMARY KEY, hostname TEXT, status INTEGER, status_event_count INTEGER,
		status_fail_date TEXT, status_rec_date TEXT, status_last_error TEXT,
		min_time REAL, max_time REAL, cur_time REAL, avg_time REAL,
		total_polls INTEGER, failed_polls INTEGER, availability REAL,
		snmp_community TEXT, snmp_version INTEGER, deleted TEXT
	)');

	foreach ([1, 2] as $id) {
		$conn->exec("INSERT INTO host (id, hostname, status, status_event_count,
			status_fail_date, status_rec_date, status_last_error,
			min_time, max_time, cur_time, avg_time,
			total_polls, failed_polls, availability,
			snmp_community, snmp_version, deleted)
			VALUES ($id, '$hostname', " . HOST_UP . ", 0, '', '', '',
			0, 0, 0, 0, 10, 0, 100, 'public', 2, '')");
	}

	return $conn;
}

/**
 * Builds a Net_Ping carrying a successful result, since update_host_status()
 * reads the response strings and timings straight off it.
 *
 * @return Net_Ping A ping object with both SNMP and ICMP results filled in.
 */
function host_status_ping() : Net_Ping {
	$ping = new Net_Ping();

	$ping->ping_status   = 12.5;
	$ping->ping_response = 'PING: ok';
	$ping->snmp_status   = 0.0;
	$ping->snmp_response = 'SNMP: ok';

	return $ping;
}

/**
 * Reads back the columns update_host_status() writes, ordered by device id.
 *
 * @param PDO $conn The connection to read from.
 *
 * @return array One row per device.
 */
function host_status_rows(PDO $conn) : array {
	return $conn->query('SELECT id, status, total_polls, failed_polls, status_last_error
		FROM host ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
	$this->config     = $config ?? [];

	$conn = host_status_seed();

	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
	$GLOBALS['config']                 = $this->config;

	$this->conn = $conn;
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. The cached
   option array has to go too, or ping_failure_count leaks into later tests. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default, $config;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;

	$config = $this->config;
});

test('marking one device down leaves its hostname twin alone', function () {
	$ping = host_status_ping();

	update_host_status(HOST_DOWN, 2, $ping, AVAIL_PING, false);

	[$twin, $polled] = host_status_rows($this->conn);

	expect($polled['id'])->toBe(2)
		->and($polled['status'])->toBe(HOST_DOWN)
		->and($polled['failed_polls'])->toBe(1)
		->and($polled['total_polls'])->toBe(11);

	// pre-fix, the hostname match rewrote this row with device 2's numbers
	expect($twin['id'])->toBe(1)
		->and($twin['status'])->toBe(HOST_UP)
		->and($twin['failed_polls'])->toBe(0)
		->and($twin['total_polls'])->toBe(10)
		->and($twin['status_last_error'])->toBe('');
});

test('a recovering device writes back to its own row only', function () {
	$this->conn->exec('UPDATE host SET status = ' . HOST_DOWN . ', failed_polls = 4 WHERE id = 1');

	$ping = host_status_ping();

	update_host_status(HOST_UP, 1, $ping, AVAIL_PING, false);

	[$polled, $twin] = host_status_rows($this->conn);

	expect($polled['id'])->toBe(1)
		->and($polled['status'])->toBe(HOST_UP)
		->and($polled['total_polls'])->toBe(11);

	expect($twin['id'])->toBe(2)
		->and($twin['status'])->toBe(HOST_UP)
		->and($twin['total_polls'])->toBe(10);
});

test('the status write reaches the polled device even when it is the only one', function () {
	// a WHERE clause keyed on the wrong column would also pass the twin
	// assertions above by updating nothing at all
	$this->conn->exec('DELETE FROM host WHERE id = 1');

	$ping = host_status_ping();

	update_host_status(HOST_DOWN, 2, $ping, AVAIL_PING, false);

	$row = $this->conn->query('SELECT status, failed_polls, status_last_error FROM host WHERE id = 2')
		->fetch(PDO::FETCH_ASSOC);

	expect($row['status'])->toBe(HOST_DOWN)
		->and($row['failed_polls'])->toBe(1)
		->and($row['status_last_error'])->toBe('PING: ok');
});

test('soft-deleted devices are still excluded from the status write', function () {
	$this->conn->exec("UPDATE host SET deleted = 'on' WHERE id = 2");

	$ping = host_status_ping();

	update_host_status(HOST_DOWN, 2, $ping, AVAIL_PING, false);

	$row = $this->conn->query('SELECT status, failed_polls FROM host WHERE id = 2')
		->fetch(PDO::FETCH_ASSOC);

	expect($row['status'])->toBe(HOST_UP)
		->and($row['failed_polls'])->toBe(0);
});

test('the status dates land as real timestamps, not epoch zero', function () {
	$ping = host_status_ping();

	update_host_status(HOST_DOWN, 2, $ping, AVAIL_PING, false);

	$row = $this->conn->query('SELECT status_fail_date FROM host WHERE id = 2')
		->fetch(PDO::FETCH_ASSOC);

	expect($row['status_fail_date'])->toBeGreaterThan('2020-01-01 00:00:00');
});
