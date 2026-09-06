<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for boost_flush_output_batch() (issue#7536).
 *
 * The poller_output_boost insert used to be hand-written four times: three
 * in cmd.php using INSERT IGNORE, one in boost_poller_on_demand() (lib/boost.php)
 * using ON DUPLICATE KEY UPDATE -- a real data-integrity inconsistency
 * (silently-drop vs last-write-wins) between two paths writing the same
 * shape of data. All four call sites now share this one helper, which
 * standardizes on INSERT IGNORE (first write wins): a genuine collision on
 * (local_data_id, rrd_name, time) represents a retry of an already-collected
 * sample, not two independently meaningful readings, since RRD only stores
 * one value per timestamp regardless.
 *
 * Runs against a real (SQLite, in-memory) PDO connection wired in as the
 * default db_* connection via $database_sessions, through FakeMySQLPDO,
 * which translates INSERT IGNORE -> INSERT OR IGNORE for the SQLite driver.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/database.php';
require_once CACTI_PATH_LIBRARY . '/boost.php';

class BoostBatchPDO extends FakeMySQLPDO {
	public int $insert_attempts = 0;

	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (stripos(ltrim($query), 'INSERT IGNORE INTO poller_output_boost') === 0) {
			$this->insert_attempts++;
		}

		return parent::prepare($query, $options);
	}
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$conn = new BoostBatchPDO();

	$conn->exec('CREATE TABLE poller_output_boost (
		local_data_id INTEGER NOT NULL,
		rrd_name TEXT NOT NULL,
		time TEXT NOT NULL,
		output TEXT NOT NULL,
		PRIMARY KEY (local_data_id, rrd_name, time)
	)');
	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

	$database_hostname = 'unit_test_host';
	$database_port     = '0';
	$database_default  = 'unit_test_db';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;

	$this->conn = $conn;
});

// Put the default db_* connection back. Left in place, the fake handle answers
// every later read_config_option() in the run and throws on Cacti's MySQL SQL,
// aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function boost_test_fetch_output(PDO $conn, int $local_data_id, string $rrd_name, string $time): ?string {
	$stmt = $conn->prepare('SELECT output FROM poller_output_boost WHERE local_data_id = ? AND rrd_name = ? AND time = ?');
	$stmt->execute([$local_data_id, $rrd_name, $time]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	return $row ? $row['output'] : null;
}

test('writes a fresh row when no key collision exists', function () {
	expect(boost_flush_output_batch(["(1,'ds','2024-01-01 00:00:00','100')"], $this->conn))->toBeTrue();

	$stmt = $this->conn->query('SELECT local_data_id, rrd_name, output FROM poller_output_boost');
	$row  = $stmt->fetch(PDO::FETCH_ASSOC);

	expect($row)->not->toBeFalse();
	expect($row['local_data_id'])->toBe(1);
	expect($row['rrd_name'])->toBe('ds');
	expect($row['output'])->toBe('100');
});

test('a genuine duplicate-key collision keeps the first write, ignoring the second (issue#7536 chosen strategy)', function () {
	// Two separate flush calls targeting the identical (local_data_id, rrd_name, time)
	// key -- e.g. a retried batch, or two writers racing on the same rounded
	// second -- with different output values so the winner is unambiguous.
	boost_flush_output_batch(["(1,'ds','2024-01-01 00:00:00','100')"], $this->conn);
	boost_flush_output_batch(["(1,'ds','2024-01-01 00:00:00','200')"], $this->conn);

	$output = boost_test_fetch_output($this->conn, 1, 'ds', '2024-01-01 00:00:00');

	// INSERT IGNORE: first write wins, the second is silently dropped.
	expect($output)->toBe('100');

	$count = (int) $this->conn->query('SELECT COUNT(*) AS c FROM poller_output_boost')->fetch(PDO::FETCH_ASSOC)['c'];
	expect($count)->toBe(1);
});

test('non-colliding rows in the same batch are all written', function () {
	boost_flush_output_batch([
		"(1,'ds1','2024-01-01 00:00:00','10')",
		"(2,'ds2','2024-01-01 00:00:00','20')",
		"(3,'ds3','2024-01-01 00:00:00','30')",
	], $this->conn);

	$count = (int) $this->conn->query('SELECT COUNT(*) AS c FROM poller_output_boost')->fetch(PDO::FETCH_ASSOC)['c'];
	expect($count)->toBe(3);

	expect(boost_test_fetch_output($this->conn, 2, 'ds2', '2024-01-01 00:00:00'))->toBe('20');
});

test('splits a batch before the database packet limit without losing rows', function () {
	$large_output = str_repeat('x', 2200000);

	expect(boost_flush_output_batch([
		"(11,'large_1','2024-01-01 00:00:00','{$large_output}')",
		"(12,'large_2','2024-01-01 00:00:00','{$large_output}')",
	], $this->conn))->toBeTrue();

	$count = (int) $this->conn->query('SELECT COUNT(*) FROM poller_output_boost')->fetchColumn();

	expect($count)->toBe(2)
		->and($this->conn->insert_attempts)->toBe(2)
		->and(strlen(boost_test_fetch_output($this->conn, 11, 'large_1', '2024-01-01 00:00:00')))->toBe(2200000)
		->and(strlen(boost_test_fetch_output($this->conn, 12, 'large_2', '2024-01-01 00:00:00')))->toBe(2200000);
});

test('an empty tuple list is a no-op', function () {
	expect(boost_flush_output_batch([], $this->conn))->toBeTrue();

	$count = (int) $this->conn->query('SELECT COUNT(*) AS c FROM poller_output_boost')->fetch(PDO::FETCH_ASSOC)['c'];
	expect($count)->toBe(0);
});

test('reports a failed database acknowledgement to its caller', function () {
	$this->conn->exec('DROP TABLE poller_output_boost');

	expect(boost_flush_output_batch(["(1,'ds','2024-01-01 00:00:00','100')"], $this->conn))->toBeFalse();
});

test('publishes a multi-chunk graph cache object without leaving a temporary file', function () {
	$directory = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$cache_file = $directory . '/cache.png';
	$output     = str_repeat('png-data', 393217);

	try {
		expect(boost_atomic_write_cache($cache_file, $output))->toBeTrue();
		expect(file_get_contents($cache_file))->toBe($output);
		expect(fileperms($cache_file) & 0777)->toBe(0644);
		expect(glob($directory . '/.boost-*'))->toBe([]);
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('the Windows cache replacement fallback replaces an existing object', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	$temp_file  = $directory . '/.boost-replacement';
	mkdir($directory, 0700);
	file_put_contents($cache_file, 'old');
	file_put_contents($temp_file, 'new');

	try {
		expect(boost_replace_cache_file_on_windows($temp_file, $cache_file))->toBeTrue();
		expect(file_get_contents($cache_file))->toBe('new');
		expect(file_exists($temp_file))->toBeFalse();
	} finally {
		@unlink($temp_file);
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('atomic cache publication replaces an existing object on the native platform', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	mkdir($directory, 0700);
	file_put_contents($cache_file, 'old');

	try {
		expect(boost_atomic_write_cache($cache_file, 'new'))->toBeTrue();
		expect(file_get_contents($cache_file))->toBe('new');
		expect(glob($directory . '/.boost-*'))->toBe([]);
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('atomic cache publication supports an empty object', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	mkdir($directory, 0700);

	try {
		expect(boost_atomic_write_cache($cache_file, ''))->toBeTrue();
		expect(file_get_contents($cache_file))->toBe('');
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('a failed cache publication removes its temporary object', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/occupied';
	mkdir($directory, 0700);
	mkdir($cache_file, 0700);

	try {
		expect(boost_atomic_write_cache($cache_file, 'new'))->toBeFalse();
		expect(is_dir($cache_file))->toBeTrue();
		expect(glob($directory . '/.boost-*'))->toBe([]);
	} finally {
		@rmdir($cache_file);
		@rmdir($directory);
	}
});

test('the Windows replacement helper fails closed when no destination exists', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/missing.png';
	$temp_file  = $directory . '/.boost-replacement';
	mkdir($directory, 0700);
	file_put_contents($temp_file, 'new');

	try {
		expect(boost_replace_cache_file_on_windows($temp_file, $cache_file))->toBeFalse();
		expect(file_get_contents($temp_file))->toBe('new');
		expect(file_exists($cache_file))->toBeFalse();
	} finally {
		@unlink($temp_file);
		@rmdir($directory);
	}
});

test('the Windows replacement helper preserves the existing object when publication fails', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-cache-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	$temp_file  = $directory . '/missing-replacement';
	mkdir($directory, 0700);
	file_put_contents($cache_file, 'old');

	try {
		expect(boost_replace_cache_file_on_windows($temp_file, $cache_file))->toBeFalse();
		expect(file_get_contents($cache_file))->toBe('old');
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});
