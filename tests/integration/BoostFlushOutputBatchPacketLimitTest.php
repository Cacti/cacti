<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for boost_flush_output_batch()'s max_allowed_packet
 * cache.
 *
 * The value used to be memoized in one static shared by every caller, but
 * the helper is invoked with two different connections -- the default one
 * for cmd.php's local writes and $remote_db_cnn_id from a remote poller --
 * and max_allowed_packet is a per-server setting. Whichever connection
 * called first decided the chunk size for all the others: too small wastes
 * round trips, too large builds an INSERT the second server rejects. The
 * cache is now keyed per connection, so each one is looked up exactly once
 * against its own server.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_TESTS . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/database.php';
require_once CACTI_PATH_LIBRARY . '/boost.php';

/**
 * Counts the max_allowed_packet lookups this connection is asked for, so
 * the cache key can be observed per connection instead of per process.
 */
class BoostPacketLookupPDO extends FakeMySQLPDO {
	public int $packet_lookups = 0;

	public function __construct() {
		parent::__construct();

		$this->exec('CREATE TABLE poller_output_boost (
			local_data_id INTEGER NOT NULL,
			rrd_name TEXT NOT NULL,
			time TEXT NOT NULL,
			output TEXT NOT NULL,
			PRIMARY KEY (local_data_id, rrd_name, time)
		)');
	}

	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (stripos($query, 'max_allowed_packet') !== false) {
			$this->packet_lookups++;
		}

		return parent::prepare($query, $options);
	}
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
});

// Put the default db_* connection back. Left cleared, every later
// read_config_option() in the run loses its connection and the suite aborts.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('max_allowed_packet is read once per connection, not once per process', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	// no default connection is registered, so both writes have to go
	// through the connection they were handed
	$database_hostname = 'unit_test_host';
	$database_port     = '0';
	$database_default  = 'unit_test_db';

	$database_sessions = [];

	$local  = new BoostPacketLookupPDO();
	$remote = new BoostPacketLookupPDO();

	boost_flush_output_batch(["(1,'ds','2024-01-01 00:00:00','10')"], $local);

	// the second connection is a different server: it must be asked for its
	// own limit rather than inheriting the one cached for $local
	boost_flush_output_batch(["(2,'ds','2024-01-01 00:00:00','20')"], $remote);

	expect($local->packet_lookups)->toBe(1);
	expect($remote->packet_lookups)->toBe(1);

	// the per-connection value is still cached: no repeat lookup on reuse
	boost_flush_output_batch(["(3,'ds','2024-01-01 00:00:00','30')"], $local);

	expect($local->packet_lookups)->toBe(1);

	// and each connection got its own rows
	foreach ([[$local, 2], [$remote, 1]] as [$conn, $expected]) {
		$count = (int) $conn->query('SELECT COUNT(*) AS c FROM poller_output_boost')->fetch(PDO::FETCH_ASSOC)['c'];
		expect($count)->toBe($expected);
	}
});
