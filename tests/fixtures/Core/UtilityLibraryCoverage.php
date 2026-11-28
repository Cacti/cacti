<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Whole-file evidence for every included vendor class does not fit the
// runner's memory limit, and only library lines are gated.
if (function_exists('xdebug_set_filter')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__, 3) . '/lib/']);
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

require_once dirname(__DIR__, 2) . '/Unit/Core/Templates/TemplateExchangeFixture.php';

// lib/utility.php comes last: PHP attributes a plain variable argument to its
// own line only when the called function is already declared at compile time.
foreach (['api_poller', 'plugins', 'poller', 'data_query', 'html', 'utility'] as $library) {
	require_once CACTI_PATH_LIBRARY . '/' . $library . '.php';
}

/**
 * sqlite connection for lib/utility.php. It answers SHOW GLOBAL VARIABLES from
 * a seeded table and lets the fixture script the remote poller heartbeat and
 * connection lookups, which change between the stages of one cache flush.
 */
class UtilityCoveragePDO extends FakeMySQLPDO {
	/** @var array<int,int> heartbeat answers remote_poller_up() receives in order */
	public static array $remote_up = [];

	/** @var array<int,bool> whether each remote connection lookup finds the poller */
	public static array $remote_connect = [];

	/**
	 * Opens the connection with buffered statements so rowCount() works for SELECT.
	 */
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BufferedPDOStatement::class, []]);
	}

	/**
	 * Rewrites the MySQL-only statements, then prepares the query.
	 *
	 * @param string              $query   The SQL statement
	 * @param array<string,mixed> $options Driver options
	 *
	 * @return PDOStatement|false The prepared statement
	 */
	public function prepare(string $query, array $options = []) : PDOStatement|false {
		$query = preg_replace('/\bSQL_NO_CACHE\b/', '', $query);

		if (preg_match('/^\s*SHOW\s+GLOBAL\s+VARIABLES\s*$/i', $query)) {
			$query = 'SELECT Variable_name, Value FROM global_variables ORDER BY Variable_name';
		} elseif (str_contains($query, 'UNIX_TIMESTAMP(last_status) < ?')) {
			if (!cacti_sizeof(self::$remote_up)) {
				throw new RuntimeException('Unscripted remote poller heartbeat check');
			}
			$query = 'SELECT CASE WHEN ? IS NULL OR ? IS NULL THEN 0 ELSE ' . (int) array_shift(self::$remote_up) . ' END';
		} elseif (preg_match('/^\s*SELECT \*\s+FROM poller\s+WHERE id = \?\s*$/', $query)) {
			if (!cacti_sizeof(self::$remote_connect)) {
				throw new RuntimeException('Unscripted remote poller connection');
			}

			if (!array_shift(self::$remote_connect)) {
				$query .= ' AND 0';
			}
		}

		return parent::prepare($query, $options);
	}
}

$count = 0;

/**
 * Counts one assertion and throws when the values are not identical.
 *
 * @param mixed  $actual   The observed value
 * @param mixed  $expected The required value
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function utility_expect(mixed $actual, mixed $expected, string $message) : void {
	global $count;
	$count++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

/**
 * Counts one assertion that $haystack holds $needle.
 *
 * @param string $haystack The observed text
 * @param string $needle   The required fragment
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function utility_expect_contains(string $haystack, string $needle, string $message) : void {
	utility_expect(str_contains($haystack, $needle), true, $message . ' (' . $needle . ')');
}

/**
 * Makes $conn the default Cacti database session.
 *
 * @param PDO $conn The connection
 *
 * @return void
 */
function utility_wire(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'utility';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'utility';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['utility:0:utility' => $conn];
}

/**
 * Creates the poller_item table the cache flush writes.
 *
 * @param PDO $conn The connection
 *
 * @return void
 */
function utility_poller_item_table(PDO $conn) : void {
	$conn->exec('CREATE TABLE poller_item (local_data_id INTEGER, poller_id INTEGER, host_id INTEGER,
		action INTEGER, hostname TEXT, snmp_community TEXT, snmp_version INTEGER, snmp_timeout INTEGER,
		snmp_retries INTEGER, snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT,
		snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT, snmp_context TEXT, snmp_engine_id TEXT,
		snmp_port INTEGER, rrd_name TEXT, rrd_path TEXT, rrd_num INTEGER, rrd_step INTEGER,
		rrd_next_step INTEGER, arg1 TEXT, arg2 TEXT, arg3 TEXT, present INTEGER,
		PRIMARY KEY (local_data_id, rrd_name))');
}

/**
 * Creates the tables update_poller_cache() and the cache flush read.
 *
 * @return UtilityCoveragePDO The seeded connection
 */
function utility_seed() : UtilityCoveragePDO {
	$conn = new UtilityCoveragePDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES ('path_php_binary', '/usr/bin/php'),
		('path_snmpget', '/usr/bin/snmpget'), ('poller_interval', '300')");
	$conn->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER,
		snmp_query_id INTEGER, snmp_index TEXT, data_template_id INTEGER)');
	$conn->exec('CREATE TABLE data_input (id INTEGER PRIMARY KEY, type_id INTEGER, name TEXT,
		hash TEXT, input_string TEXT)');
	$conn->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY, local_data_id INTEGER,
		data_template_id INTEGER, data_input_id INTEGER, rrd_step INTEGER, active TEXT,
		name TEXT, data_source_path TEXT)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY,
		local_data_template_rrd_id INTEGER, local_data_id INTEGER, data_source_name TEXT)');
	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, data_input_id INTEGER,
		data_name TEXT, type_code TEXT, input_output TEXT, update_rra TEXT)');
	$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER,
		data_template_data_id INTEGER, value TEXT)');
	$conn->exec('CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, xml_path TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER,
		data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
	$conn->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT)');
	$conn->exec('CREATE TABLE poller (id INTEGER PRIMARY KEY, name TEXT, last_status TEXT, disabled TEXT,
		dbhost TEXT, dbuser TEXT, dbpass TEXT, dbdefault TEXT, dbport INTEGER, dbretries INTEGER,
		dbssl TEXT, dbsslkey TEXT, dbsslcert TEXT, dbsslca TEXT, dbsslcapath TEXT, dbsslverifyservercert TEXT)');
	$conn->exec("INSERT INTO poller VALUES (2, 'Remote', datetime('now'), '', 'remote.example.test', 'cactiuser',
		'secret', 'cacti', 3306, 1, '', '', '', '', '', '')");
	utility_poller_item_table($conn);

	// substitute_host_data() expands |host_*| tokens straight off SELECT *
	$conn->exec('CREATE TABLE host (
		id INTEGER PRIMARY KEY, poller_id INTEGER, site_id INTEGER, host_template_id INTEGER,
		description TEXT, hostname TEXT, location TEXT, notes TEXT, external_id TEXT,
		snmp_options TEXT, snmp_community TEXT, snmp_version INTEGER, snmp_username TEXT,
		snmp_password TEXT, snmp_auth_protocol TEXT, snmp_priv_passphrase TEXT,
		snmp_priv_protocol TEXT, snmp_context TEXT, snmp_engine_id TEXT, snmp_port INTEGER,
		snmp_timeout INTEGER, snmp_retries INTEGER, snmp_sysDescr TEXT, snmp_sysObjectID TEXT,
		snmp_sysUpTimeInstance TEXT, snmp_sysContact TEXT, snmp_sysName TEXT,
		snmp_sysLocation TEXT, availability_method INTEGER, ping_method INTEGER,
		ping_port INTEGER, ping_timeout INTEGER, ping_retries INTEGER, max_oids INTEGER,
		bulk_walk_size INTEGER, device_threads INTEGER, deleted TEXT, disabled TEXT, graphs TEXT,
		data_sources TEXT, status INTEGER, status_event_count INTEGER, status_fail_date TEXT,
		status_rec_date TEXT, status_options_date TEXT, status_last_error TEXT, min_time TEXT,
		max_time TEXT, cur_time TEXT, avg_time TEXT, polling_time TEXT, current_errors INTEGER,
		total_polls INTEGER, failed_polls INTEGER, availability TEXT, last_updated TEXT,
		created TEXT
	)');

	// api_poller_cache_item_add() memoizes by device, so each scenario has its own
	foreach ([81, 82, 83, 84, 86, 87, 90] as $host_id) {
		$conn->exec("INSERT INTO host (id, poller_id, site_id, description, hostname,
			snmp_community, snmp_version, snmp_port, snmp_timeout, snmp_retries, ping_retries,
			max_oids, bulk_walk_size, availability_method, disabled, deleted)
			VALUES ($host_id, 1, 0, 'dev', 'router.example.net', 'public', 2, 161, 500, 3, 3, 10, 10, 1, '', '')");
	}

	// A multi-output script
	$conn->exec("INSERT INTO data_local VALUES (801, 81, 0, '', 811)");
	$conn->exec('INSERT INTO data_input VALUES (812, ' . DATA_INPUT_TYPE_SCRIPT . ", 'Load', 'h812', '/usr/bin/uptime.sh <host> <mode>')");
	$conn->exec("INSERT INTO data_template_data VALUES (813, 801, 811, 812, 60, 'on', 'load', '<path_rra>/load.rrd')");
	$conn->exec("INSERT INTO data_input_fields VALUES
		(814, 812, 'host', 'hostname', 'in', ''),
		(815, 812, 'mode', '', 'in', ''),
		(816, 812, 'one', '', 'out', 'on'),
		(817, 812, 'five', '', 'out', 'on')");
	$conn->exec("INSERT INTO data_input_data VALUES (814, 813, 'router.example.net'), (815, 813, 'short')");

	// An SNMP get whose port comes from the template
	$conn->exec("INSERT INTO data_local VALUES (821, 82, 0, '', 822)");
	$conn->exec('INSERT INTO data_input VALUES (823, ' . DATA_INPUT_TYPE_SNMP . ", 'Get SNMP Data', 'h823', '')");
	$conn->exec("INSERT INTO data_template_data VALUES
		(824, 821, 822, 823, 300, 'on', 'uptime', '<path_rra>/uptime.rrd'),
		(825, 0, 822, 823, 300, 'on', 'uptime template', '')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (826, 0, 821, 'sysUpTime')");
	$conn->exec("INSERT INTO data_input_fields VALUES
		(827, 823, 'oid', 'snmp_oid', 'in', ''),
		(828, 823, 'port', 'snmp_port', 'in', '')");
	$conn->exec("INSERT INTO data_input_data VALUES (827, 824, '.1.3.6.1.2.1.1.3.0'), (828, 825, '1161')");

	// A script server query
	$conn->exec("INSERT INTO data_local VALUES (831, 83, 832, '2', 833)");
	$conn->exec('INSERT INTO data_input VALUES (834, ' . DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER . ", 'Get Script Server Data (Indexed)', 'h834', '')");
	$conn->exec("INSERT INTO data_template_data VALUES
		(835, 831, 833, 834, 300, 'on', 'cpu', '<path_rra>/cpu.rrd'),
		(836, 0, 833, 834, 300, 'on', 'cpu template', '')");
	$conn->exec("INSERT INTO snmp_query VALUES (832, '<path_cacti>/resource/script_server/host_cpu.xml')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (837, 838, 831, 'cpu')");
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (1, 833, 838, 'hrProcessorLoad')");

	// A data source whose input method is gone
	$conn->exec("INSERT INTO data_local VALUES (841, 84, 0, '', 842)");
	$conn->exec("INSERT INTO data_template_data VALUES (843, 841, 842, 844, 300, 'on', 'orphan', '')");

	// An SNMP data query with one mapped and one unmapped output
	$conn->exec("INSERT INTO data_local VALUES (861, 86, 862, '3', 863)");
	$conn->exec('INSERT INTO data_input VALUES (864, ' . DATA_INPUT_TYPE_SNMP_QUERY . ", 'Indexed SNMP', 'h864', '')");
	$conn->exec("INSERT INTO data_template_data VALUES
		(865, 861, 863, 864, 300, 'on', 'ds', '<path_rra>/snmpq.rrd'),
		(866, 0, 863, 864, 300, 'on', 'tmpl', '')");
	$conn->exec("INSERT INTO snmp_query VALUES (862, '<path_cacti>/tests/fixtures/issue7135_data_query.xml')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (867, 868, 861, 'withOid'), (869, 870, 861, 'withoutOid')");
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (1, 863, 868, 'withOid'), (1, 863, 870, 'withoutOid')");

	// A script data query with one mapped and one unmapped output
	$conn->exec("INSERT INTO data_local VALUES (871, 87, 872, '4', 873)");
	$conn->exec('INSERT INTO data_input VALUES (874, ' . DATA_INPUT_TYPE_SCRIPT_QUERY . ", 'Indexed Script', 'h874', '')");
	$conn->exec("INSERT INTO data_template_data VALUES
		(875, 871, 873, 874, 300, 'on', 'ds', '<path_rra>/scriptq.rrd'),
		(876, 0, 873, 874, 300, 'on', 'tmpl', '')");
	$conn->exec("INSERT INTO snmp_query VALUES (872, '<path_cacti>/tests/fixtures/issue7135_script_query.xml')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (877, 878, 871, 'withQueryName'), (879, 880, 871, 'withoutQueryName')");
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (1, 873, 878, 'withQueryName'), (1, 873, 880, 'withoutQueryName')");

	// Whitelisted inputs: one matches, one was tampered with after the audit
	$conn->exec('INSERT INTO data_input VALUES (900, ' . DATA_INPUT_TYPE_SCRIPT . ", 'Audited', 'h900', '/usr/bin/audited.sh')");
	$conn->exec('INSERT INTO data_input VALUES (901, ' . DATA_INPUT_TYPE_SCRIPT . ", 'Tampered', 'h901', '/usr/bin/tampered.sh')");
	$conn->exec("INSERT INTO data_local VALUES (902, 90, 0, '', 903)");
	$conn->exec("INSERT INTO data_template_data VALUES (904, 902, 903, 901, 300, 'on', 'tampered', '')");

	return $conn;
}

/**
 * Returns one poller item VALUES tuple for the remote poller.
 *
 * @param int    $local_data_id The data source id
 * @param string $name          The rrd name
 * @param int    $padding       Extra argument bytes, to fill the insert buffer
 *
 * @return string The SQL tuple
 */
function utility_remote_item(int $local_data_id, string $name, int $padding = 0) : string {
	return "($local_data_id, 2, 85, 1, 'router.example.net', 'public', '2', '500', '3', '', '', '', '', '', '', '', " .
		"'161', '$name', '/rra/$name.rrd', '1', '300', '0', '/usr/bin/uptime.sh" . str_repeat('x', $padding) . "', '', '', '1')";
}

/**
 * Flushes $items for remote poller 2 with scripted heartbeat and connection
 * answers, and returns the session messages it raised.
 *
 * @param array<int,int>    $ids     The data source ids to refresh
 * @param array<int,string> $items   The poller item tuples
 * @param array<int,int>    $up      The heartbeat answers, in call order
 * @param array<int,bool>   $connect The connection lookup answers, in call order
 *
 * @return array<string,mixed> The raised messages
 */
function utility_flush(array $ids, array $items, array $up, array $connect) : array {
	global $no_http_headers;

	$no_http_headers                     = true;
	$_SESSION                            = [];
	UtilityCoveragePDO::$remote_up       = $up;
	UtilityCoveragePDO::$remote_connect  = $connect;

	poller_update_poller_cache_from_buffer($ids, $items, 2);

	utility_expect(UtilityCoveragePDO::$remote_up, [], 'Every scripted heartbeat answer was used');
	utility_expect(UtilityCoveragePDO::$remote_connect, [], 'Every scripted connection answer was used');

	return $_SESSION[SESS_MESSAGES] ?? [];
}

$conn   = utility_seed();
$remote = new UtilityCoveragePDO();
utility_poller_item_table($remote);
utility_wire($conn);
$GLOBALS['database_sessions']['remote.example.test:3306:cacti'] = $remote;
$GLOBALS['data_query_xml_arrays']                               = [];

// The whitelist rejects a tampered input and warns about an unaudited one.
$whitelist = tempnam(sys_get_temp_dir(), 'cacti-whitelist-');
file_put_contents($whitelist, json_encode(['h900' => '/usr/bin/audited.sh', 'h901' => '/usr/bin/original.sh'], JSON_THROW_ON_ERROR));
define('CACTI_WHITELIST', $whitelist);

// Every later check needs the file, so it is removed only when the run ends.
register_shutdown_function(static function () use ($whitelist) : void {
	@unlink($whitelist);
});

$log = template_exchange_capture_log(function () : void {
	utility_expect(data_input_whitelist_check(900), true, 'An audited input passes the whitelist');
	utility_expect(data_input_whitelist_check(901), false, 'A tampered input fails the whitelist');
	utility_expect(update_poller_cache(902, false), [], 'A tampered input is not cached');
});
utility_expect_contains($log, 'ERROR: Whitelist entry failed validation for Data Input: Tampered[ 901 ].  Data Collection will not run.', 'A tampered input is logged');
utility_expect_contains($log, 'WARNING: Whitelist entry missing for Data Input: Load[ 812 ].  Run CLI command input_whitelist.php --update', 'An unaudited input is logged');
utility_expect_contains($log, 'WARNING: Data Input 901 failing validation check.', 'The failed check is logged');

// A multi-output script is cached with its expanded command and no item name.
$items = update_poller_cache(801, false);
utility_expect(count($items), 1, 'A script builds one item');
utility_expect(str_starts_with($items[0], '(801, 1, 81, ' . POLLER_ACTION_SCRIPT . ','), true, 'A script item uses the script action');
utility_expect_contains($items[0], "'/usr/bin/uptime.sh ''router.example.net'' ''short'''", 'A script item carries the command');
utility_expect_contains($items[0], "'', '" . CACTI_PATH_RRA . "/load.rrd', '1', '60'", 'A multi-output script has no item name');

// An SNMP data source is cached with the oid from its input data.
$items = update_poller_cache(821, false);
utility_expect(count($items), 1, 'An SNMP get builds one item');
utility_expect(str_starts_with($items[0], '(821, 1, 82, ' . POLLER_ACTION_SNMP . ','), true, 'An SNMP item uses the SNMP action');
utility_expect_contains($items[0], "'1161'", 'The template port fills the device fields');
utility_expect_contains($items[0], "'sysUpTime'", 'An SNMP item is named after its data source item');
utility_expect_contains($items[0], "'.1.3.6.1.2.1.1.3.0'", 'An SNMP item carries its oid');

// Only the SNMP query output with an oid mapping is cached.
$items = update_poller_cache(861, false);
utility_expect(count($items), 1, 'An unmapped SNMP query output is skipped');
utility_expect_contains($items[0], "'withOid'", 'The mapped SNMP query output is cached');
utility_expect_contains($items[0], "'.1.3.6.1.4.1.9999.1.1.10.3'", 'The SNMP query oid carries the index');

// A script server query is cached as a PHP script call for its index.
$items = update_poller_cache(831, false);
utility_expect(count($items), 1, 'A script server query builds one item');
utility_expect(str_starts_with($items[0], '(831, 1, 83, ' . POLLER_ACTION_SCRIPT_PHP . ','), true, 'A script server item uses the PHP action');
utility_expect_contains($items[0], "'" . CACTI_PATH_BASE . "/scripts/ss_host_cpu.php ss_host_cpu ''router.example.net'' ''83'' ", 'A script server item calls the function');
utility_expect_contains($items[0], "''get'' ''usage'' ''2''", 'A script server item gets its index');

// Only the script query output with a query name is cached.
$items = update_poller_cache(871, false);
utility_expect(count($items), 1, 'An unmapped script query output is skipped');
utility_expect(str_starts_with($items[0], '(871, 1, 87, ' . POLLER_ACTION_SCRIPT . ','), true, 'A script query item uses the script action');
utility_expect_contains($items[0], "'withQueryName'", 'The mapped script query output is cached');
utility_expect_contains($items[0], "/scripts/issue7135_probe.php ''router.example.net'' ''get'' ''mapped'' ''4''", 'A script query item gets the mapped field');

// A data source whose input method is gone logs the corruption.
$log = template_exchange_capture_log(function () : void {
	utility_expect(update_poller_cache(841, false), [], 'A missing input caches nothing');
});
utility_expect_contains($log, 'WARNING: Repopulate Poller Cache found Data Input Missing for Data Source 841.  Database may be corrupted', 'A missing input is logged');

$down = 'Remote Poller 2 is Down, you will need to perform a FullSync once it is up again';

// A remote poller that is up but unreachable warns once and keeps the local rebuild.
$log = template_exchange_capture_log(function () use (&$messages) : void {
	$messages = utility_flush([851], [utility_remote_item(851, 'load')], [1, 1, 1], [false, false, false]);
});
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'An unreachable remote poller asks for a FullSync');
utility_expect($conn->query('SELECT rrd_name FROM poller_item WHERE local_data_id = 851')->fetchAll(PDO::FETCH_COLUMN), ['load'], 'The local cache is rebuilt');
utility_expect_contains($log, "ERROR: Remote Data Collector ID[2] to be Sync'd does not exist!", 'The failed lookup is logged');

// A remote poller that is down warns once.
$messages = utility_flush([852], [utility_remote_item(852, 'load')], [0, 0, 0], []);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A down remote poller asks for a FullSync');

// With no ids, an unreachable remote poller warns when the buffer fills.
$big = [];

for ($i = 0; $i < 150; $i++) {
	$big[] = utility_remote_item(853, 'big' . $i, 2000);
}
$messages = utility_flush([], $big, [1, 1], [false, false]);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A full buffer for an unreachable poller warns');
utility_expect((int) $conn->query('SELECT COUNT(*) FROM poller_item WHERE local_data_id = 853')->fetchColumn(), 150, 'Both buffer flushes reach the local cache');

// With no ids, a down remote poller warns when the buffer fills.
$conn->exec('DELETE FROM poller_item WHERE local_data_id = 853');
$messages = utility_flush([], $big, [0, 0], []);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A full buffer for a down poller warns');
utility_expect((int) $conn->query('SELECT COUNT(*) FROM poller_item WHERE local_data_id = 853')->fetchColumn(), 150, 'A down poller still rebuilds locally');

// With no ids, a down remote poller warns at the final flush.
$messages = utility_flush([], [utility_remote_item(854, 'load')], [0], []);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A final flush for a down poller warns');

// A remote poller that becomes unreachable before the stale rows are removed.
$remote->exec("INSERT INTO poller_item (local_data_id, poller_id, host_id, rrd_name, present) VALUES (855, 2, 85, 'stale', 1)");
$messages = utility_flush([855], [utility_remote_item(855, 'load')], [1, 1, 1], [true, true, false]);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A lost connection before the cleanup warns');
utility_expect($remote->query('SELECT rrd_name, present FROM poller_item WHERE local_data_id = 855 ORDER BY rrd_name')->fetchAll(PDO::FETCH_NUM), [['load', 1], ['stale', 0]], 'The remote keeps the stale row it could not clean');

// A remote poller that goes down before the stale rows are removed.
$remote->exec("INSERT INTO poller_item (local_data_id, poller_id, host_id, rrd_name, present) VALUES (856, 2, 85, 'stale', 1)");
$messages = utility_flush([856], [utility_remote_item(856, 'load')], [1, 1, 0], [true, true]);
utility_expect($messages['poller_down_2']['message'] ?? '', $down, 'A poller down before the cleanup warns');
utility_expect($remote->query('SELECT rrd_name, present FROM poller_item WHERE local_data_id = 856 ORDER BY rrd_name')->fetchAll(PDO::FETCH_NUM), [['load', 1], ['stale', 0]], 'The remote receives the new row before going down');

/**
 * Runs the MySQL recommendations against one server's variables.
 *
 * @param array<string,string> $variables The SHOW GLOBAL VARIABLES rows
 *
 * @return array{0:int,1:string} The status and the rendered rows
 */
function utility_recommendations(array $variables) : array {
	$server = new UtilityCoveragePDO();
	$server->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$server->exec('CREATE TABLE global_variables (Variable_name TEXT PRIMARY KEY, Value TEXT)');
	$insert = $server->prepare('INSERT INTO global_variables (Variable_name, Value) VALUES (?, ?)');

	foreach ($variables as $name => $value) {
		$insert->execute([$name, $value]);
	}
	utility_wire($server);

	ob_start();
	$status = utilities_get_mysql_recommendations();

	return [$status, (string) ob_get_clean()];
}

/**
 * Returns the rendered row for one variable, or an empty string.
 *
 * @param string $html The rendered recommendations
 * @param string $name The variable name
 *
 * @return string The row
 */
function utility_row(string $html, string $name) : string {
	return preg_match('#<td>' . preg_quote($name, '#') . '</td>.*?</tr>#s', $html, $match) ? $match[0] : '';
}

// A MySQL 5.5 server gets the pre-5.6 InnoDB advice and loses removed variables.
[$status, $html] = utility_recommendations([
	'version'                        => '5.5.62',
	'innodb_version'                 => '5.5.62',
	'collation_server'               => 'latin1_swedish_ci',
	'character_set_server'           => 'utf8mb4',
	'character_set_client'           => 'utf8mb4',
	'innodb_flush_log_at_trx_commit' => '2',
	'innodb_file_io_threads'         => '16',
]);
utility_expect($status, DB_STATUS_WARNING, 'A MySQL 5.5 server warns');
utility_expect_contains($html, 'MySQL Tuning', 'A MySQL server gets MySQL advice');
utility_expect_contains(utility_row($html, 'collation_server'), 'utf8mb4_unicode_ci collation type', 'The collation advice is shown');
utility_expect_contains(utility_row($html, 'character_set_client'), 'utf8mb4 character set', 'The client character set advice is shown');
utility_expect_contains(utility_row($html, 'innodb_flush_log_at_trx_commit'), 'flush all transactions every second rather than at commit.  This allows MySQL to perform', 'The pre-5.6 flush advice is shown');
utility_expect(utility_row($html, 'innodb_file_io_threads'), '', 'A removed variable is not recommended');

// A failed error class recommendation outranks a warning.
[$status, $html] = utility_recommendations([
	'version'               => '5.5.62',
	'innodb_version'        => '5.5.62',
	'collation_server'      => 'latin1_swedish_ci',
	'innodb_file_per_table' => 'OFF',
]);
utility_expect($status, DB_STATUS_ERROR, 'An error class failure is an error');
utility_expect_contains(utility_row($html, 'innodb_file_per_table'), 'textError', 'The failed error row is marked');

// A MySQL 8.0 server gets the IO thread and buffer pool instance advice.
[$status, $html] = utility_recommendations([
	'version'                      => '8.0.40',
	'innodb_version'               => '8.0.40',
	'collation_server'             => 'utf8mb4_unicode_ci',
	'innodb_flush_log_at_timeout'  => '3',
	'innodb_read_io_threads'       => '64',
	'innodb_write_io_threads'      => '16',
	'innodb_buffer_pool_instances' => '64',
	'innodb_io_capacity'           => '5000',
	'innodb_io_capacity_max'       => '20000',
	'innodb_flush_neighbors'       => '0',
]);
utility_expect($status, DB_STATUS_WARNING, 'Only the release below 8.4 warns');
utility_expect_contains($html, 'dev.mysql.com/doc/refman/8.0/en/server-system-variables.html', 'A MySQL server links its manual');
utility_expect_contains(utility_row($html, 'innodb_flush_log_at_timeout'), 'As of MySQL 8.0.40, the you can control how often MySQL flushes', 'The flush timeout advice names the version');
utility_expect_contains(utility_row($html, 'innodb_read_io_threads'), 'this value can go as high as 64', 'The read thread advice is shown');
utility_expect_contains(utility_row($html, 'innodb_buffer_pool_instances'), 'versions of MySQL upto and including MySQL 8.0', 'The MySQL pool instance advice is shown');
utility_expect_contains(utility_row($html, 'innodb_io_capacity_max'), '2000 * the number of active drives', 'The IO capacity advice is shown');
utility_expect(utility_row($html, 'innodb_flush_neighbors'), '', 'A variable deprecated in 8.0.20 is withheld');

// A MariaDB 10.4 server gets the pre-10.5 pool advice and atomic writes.
[$status, $html] = utility_recommendations([
	'version'                      => '10.4.34-MariaDB',
	'innodb_version'               => '10.4.34',
	'collation_server'             => 'utf8mb4_unicode_ci',
	'innodb_buffer_pool_instances' => '1',
	'innodb_doublewrite'           => 'ON',
	'innodb_use_atomic_writes'     => 'ON',
]);
utility_expect($status, DB_STATUS_ERROR, 'The doublewrite buffer is an error');
utility_expect_contains($html, 'https://mariadb.com/kb/en/server-system-variables/', 'A MariaDB server links its manual');
utility_expect_contains(utility_row($html, 'innodb_buffer_pool_instances'), 'versions of MariaDB less than 10.5', 'The MariaDB pool instance advice is shown');
utility_expect_contains(utility_row($html, 'innodb_doublewrite'), 'this setting should be off if atomic writes are enabled', 'The doublewrite advice is shown');
utility_expect_contains(utility_row($html, 'innodb_use_atomic_writes'), 'atomic writes over the doublewrite buffer', 'The atomic writes advice is shown');

// A MariaDB 10.6 server drops the obsolete pool advice.
[$status, $html] = utility_recommendations([
	'version'                         => '10.6.21-MariaDB',
	'innodb_version'                  => '10.6.21',
	'collation_server'                => 'utf8mb4_unicode_ci',
	'innodb_flush_log_at_timeout'     => '1',
	'innodb_read_io_threads'          => '32',
	'innodb_write_io_threads'         => '16',
	'innodb_io_capacity'              => '5000',
	'innodb_io_capacity_max'          => '10000',
	'innodb_flush_neighbors'          => '0',
	'innodb_additional_mem_pool_size' => '8388608',
	'innodb_buffer_pool_instances'    => '8',
]);
utility_expect($status, DB_STATUS_ERROR, 'A MariaDB 10.6 server without atomic writes is an error');
utility_expect_contains(utility_row($html, 'innodb_flush_log_at_timeout'), 'As of MariaDB 10.6.21', 'The MariaDB flush timeout advice names the version');
utility_expect(str_contains(utility_row($html, 'innodb_read_io_threads'), 'can go as high as 64'), false, 'The MariaDB read thread advice has no ceiling note');
utility_expect_contains(utility_row($html, 'innodb_io_capacity'), '100000', 'The MariaDB IO capacity advice is shown');
utility_expect_contains(utility_row($html, 'innodb_flush_neighbors'), 'set this to 0', 'The MariaDB neighbor flush advice is shown');
utility_expect(utility_row($html, 'innodb_additional_mem_pool_size'), '', 'The obsolete pool size advice is dropped');
utility_expect(utility_row($html, 'innodb_buffer_pool_instances'), '', 'The obsolete pool instance advice is dropped');

// A MariaDB 11.8 server is told to turn snapshot isolation off.
[$status, $html] = utility_recommendations([
	'version'                   => '11.8.2-MariaDB',
	'collation_server'          => 'utf8mb4_unicode_ci',
	'innodb_snapshot_isolation' => 'ON',
]);
utility_expect($status, DB_STATUS_ERROR, 'Snapshot isolation is an error');
utility_expect_contains(utility_row($html, 'innodb_snapshot_isolation'), 'Set innodb_snapshot_isolation=OFF in your [mysqld] configuration. See MDEV-39628.', 'The snapshot isolation advice is shown');

// The capability matrix lists every recommended variable by engine and release.
ob_start();
utilities_get_mysql_capabilities();
$html = (string) ob_get_clean();
utility_expect_contains($html, 'MariaDB Variable Capability Matrix', 'The matrix names the detected engine');
utility_expect_contains($html, '>innodb_snapshot_isolation</span>', 'The matrix lists snapshot isolation');
utility_expect_contains($html, 'Legacy single setting for InnoDB file I/O threads', 'The matrix describes the legacy IO thread setting');
utility_expect_contains($html, 'N/A - Not applicable to this engine', 'The matrix has a legend');

// Byte sizes convert between units.
utility_expect(utility_get_formatted_bytes('2G', 'M', $formatted), 2147483648.0, 'A size is returned in bytes');
utility_expect($formatted, '2048M', 'A size is formatted in the wanted unit');

// A memory limit below the recommendation needs a restart to apply.
$original_memory_limit = '128M';
$recommends            = null;
utility_php_verify_recommends($recommends, 'cli');
$by_name = array_column($recommends, null, 'name');
utility_expect(array_keys($by_name), ['location', 'version', 'memory_limit', 'max_execution_time', 'date.timezone'], 'Every PHP recommendation is checked');
utility_expect($by_name['memory_limit']['current'], '128M', 'The original memory limit is reported');
utility_expect($by_name['memory_limit']['status'], DB_STATUS_RESTART, 'A low memory limit needs a restart');
utility_expect($by_name['version']['status'], DB_STATUS_SUCCESS, 'This PHP meets the minimum version');

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
