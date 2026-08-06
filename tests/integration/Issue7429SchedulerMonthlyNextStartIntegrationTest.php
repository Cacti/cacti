<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for issue #7429. The bug was not a single wrong value
 * but a state that repeated: once api_scheduler_calculate_next_start() ran out
 * of candidate dates it returned false, api_scheduler_is_time_to_start() wrote
 * date('Y-m-d H:i', false) into next_start, and every later poller cycle read
 * that 1970 timestamp as overdue and started discovery again.
 *
 * So this drives the real function over a sequence of cycles against a real
 * (sqlite-backed) connection, the way poller_automation.php does, and watches
 * what accumulates in next_start. The same scheduler serves reports, hence the
 * second table.
 */

require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/api_scheduler.php';

if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir() . '/cacti-issue-7429-test.log');
}

/**
 * Seeds one schedule row in the given table.
 *
 * @param string $table    Either automation_networks or reports.
 * @param array  $schedule The schedule values to store.
 *
 * @return FakeMySQLPDO A connection holding the seeded schema.
 */
function scheduler_seed(string $table, array $schedule) : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	// the scheduler logs each candidate date, and cacti_log() reads its
	// verbosity out of settings before deciding whether to write
	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

	$conn->exec("CREATE TABLE $table (
		id INTEGER PRIMARY KEY, name TEXT, sched_type INTEGER, start_at TEXT,
		next_start TEXT, recur_every INTEGER, day_of_week TEXT, month TEXT,
		day_of_month TEXT, monthly_week TEXT, monthly_day TEXT, enabled TEXT
	)");

	$conn->exec("INSERT INTO $table (id, name, sched_type, start_at, next_start,
		recur_every, day_of_week, month, day_of_month, monthly_week, monthly_day, enabled)
		VALUES (1, 'test', {$schedule['sched_type']}, '{$schedule['start_at']}',
		'{$schedule['next_start']}', 1, '', '{$schedule['month']}',
		'{$schedule['day_of_month']}', '{$schedule['monthly_week']}',
		'{$schedule['monthly_day']}', 'on')");

	return $conn;
}

/**
 * Builds a monthly schedule whose date has already passed this year.
 *
 * @param array $overrides Schedule keys to replace in the default.
 *
 * @return array The values scheduler_seed() stores.
 */
function scheduler_schedule(array $overrides = []) : array {
	return array_merge([
		'sched_type'   => SCHEDULE_MONTHLY,
		'start_at'     => '09:30',
		'next_start'   => '0000-00-00 00:00:00',
		'month'        => '3',
		'day_of_month' => '15',
		'monthly_week' => '',
		'monthly_day'  => '',
	], $overrides);
}

/**
 * One poller cycle: read the row, ask the scheduler, read it back. Returns the
 * answer and the next_start the cycle left behind.
 *
 * @return array{started: bool, next_start: string}
 */
function scheduler_cycle(PDO $conn, string $table) : array {
	$row = $conn->query("SELECT * FROM $table WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

	$started = api_scheduler_is_time_to_start($row, $table);

	return [
		'started'    => $started,
		'next_start' => $conn->query("SELECT next_start FROM $table WHERE id = 1")
			->fetch(PDO::FETCH_ASSOC)['next_start'],
	];
}

/**
 * Points lib/database.php's default connection at the seeded handle.
 *
 * @param PDO $conn The connection the scheduler should read and write.
 *
 * @return void
 */
function scheduler_wire(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['config']                 = $GLOBALS['config'] ?? [];
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('a monthly schedule whose date has passed writes a future next_start, not 1970', function () {
	$conn = scheduler_seed('automation_networks', scheduler_schedule());
	scheduler_wire($conn);

	$cycle = scheduler_cycle($conn, 'automation_networks');

	expect($cycle['next_start'])->not->toStartWith('1970')
		->and(strtotime($cycle['next_start']))->toBeGreaterThan(time());
});

test('an overdue schedule fires once and then stops firing every cycle', function () {
	// an overdue next_start, which is the state the poller finds after a
	// missed run. The reported symptom was every later cycle firing too,
	// because the recalculated next_start came back as 1970.
	$conn = scheduler_seed('automation_networks', scheduler_schedule([
		'next_start' => date('Y-m-d H:i:s', time() - 86400),
	]));
	scheduler_wire($conn);

	$starts = [];

	for ($cycle = 0; $cycle < 5; $cycle++) {
		$starts[] = scheduler_cycle($conn, 'automation_networks')['started'];
	}

	expect($starts)->toBe([true, false, false, false, false]);
});

test('next_start stops drifting once it has been calculated', function () {
	$conn = scheduler_seed('automation_networks', scheduler_schedule());
	scheduler_wire($conn);

	$first  = scheduler_cycle($conn, 'automation_networks')['next_start'];
	$second = scheduler_cycle($conn, 'automation_networks')['next_start'];

	expect($second)->toBe($first);
});

test('an unschedulable configuration leaves next_start untouched', function () {
	// every candidate date fails to parse, so the calculation returns false;
	// pre-fix that false was written straight into next_start as 1970
	$conn = scheduler_seed('automation_networks', scheduler_schedule([
		'day_of_month' => '40',
		'next_start'   => '2026-03-15 09:30:00',
	]));
	scheduler_wire($conn);

	$cycle = scheduler_cycle($conn, 'automation_networks');

	expect($cycle['started'])->toBeFalse()
		->and($cycle['next_start'])->toBe('2026-03-15 09:30:00');
});

test('the same guard applies to report schedules', function () {
	$conn = scheduler_seed('reports', scheduler_schedule([
		'sched_type'   => SCHEDULE_MONTHLY_ON_DAY,
		'day_of_month' => '',
		'monthly_week' => '4',
		'monthly_day'  => '4',
	]));
	scheduler_wire($conn);

	$cycle = scheduler_cycle($conn, 'reports');

	// fourth Wednesday of March, which pre-fix was spelled 'forth' and so
	// never parsed at all
	expect($cycle['next_start'])->not->toStartWith('1970')
		->and(date('D', strtotime($cycle['next_start'])))->toBe('Wed');
});
