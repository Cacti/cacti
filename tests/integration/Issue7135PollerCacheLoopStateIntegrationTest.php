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
 * Integration coverage for issue #7135. Both output loops in
 * update_poller_cache() assign their loop state inside a guard but read it
 * after, so an output with no 'oid' (or no 'query_name') mapping inherited the
 * previous iteration's value and was cached against another output's OID or
 * script. The poller then collected the wrong value under that data source's
 * name.
 *
 * Asserting that unset() is present would not prove anything about the cache
 * that gets built, so this runs the real update_poller_cache() against a real
 * (sqlite-backed) connection with a two-output data query whose second field
 * deliberately has no oid, and reads back the poller items it produced.
 */

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';

/* After lib/functions.php, so its guarded definitions stay no-ops; it is here
   for __()/__esc(), which the data query loader calls and functions.php does
   not define. */
require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';

/* include/global.php resolves these from config and the settings table, which
   would need a live install. update_poller_cache() only uses them to build the
   RRD path and to locate the data query XML. */
foreach ([
	'CACTI_WEB'          => false,
	'CACTI_SERVER_OS'    => 'unix',
	'CACTI_PATH_BASE'    => dirname(__DIR__, 2),
	'CACTI_PATH_LIBRARY' => dirname(__DIR__, 2) . '/lib',
	'CACTI_PATH_RRA'     => dirname(__DIR__, 2) . '/rra',
	'CACTI_PATH_LOG'     => sys_get_temp_dir() . '/cacti-issue-7135-test.log',
] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

require_once dirname(__DIR__, 2) . '/lib/utility.php';

// the script query path expands |host_*| tokens, which fires a plugin hook
require_once dirname(__DIR__, 2) . '/lib/plugins.php';

/**
 * sqlite reports rowCount() as 0 for SELECT, and db_fetch_row_return() guards
 * on it, so without this every row read in update_poller_cache() comes back
 * empty.
 */
class PollerCacheStatement extends PDOStatement {
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
 * A FakeMySQLPDO that hands out counting statements.
 */
class PollerCachePDO extends FakeMySQLPDO {
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PollerCacheStatement::class]);
	}
}

/**
 * Seeds one indexed data source with two outputs. Both fixtures give the second
 * output no mapping, which is the case the leaked loop state papered over.
 *
 * @param int      $type_id The data input type, which picks the loop under test.
 * @param string   $fixture The data query XML under tests/fixtures to point at.
 * @param string[] $fields  The two output field names, in the fixture's order.
 *
 * @return PollerCachePDO A connection holding the seeded schema.
 */
function poller_cache_seed(int $type_id = DATA_INPUT_TYPE_SNMP_QUERY, string $fixture = 'issue7135_data_query.xml', array $fields = ['withOid', 'withoutOid']) : PollerCachePDO {
	$conn = new PollerCachePDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

	// substitute_script_query_path() expands these into the script command line
	$conn->exec("INSERT INTO settings (name, value) VALUES
		('path_php_binary', '/usr/bin/php'),
		('path_snmpget', '/usr/bin/snmpget')");

	$conn->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER,
		snmp_query_id INTEGER, snmp_index TEXT, data_template_id INTEGER)');
	$conn->exec("INSERT INTO data_local VALUES (1, 1, 1, '3', 7)");

	$conn->exec('CREATE TABLE data_input (id INTEGER PRIMARY KEY, type_id INTEGER, name TEXT, hash TEXT)');
	$conn->exec("INSERT INTO data_input VALUES (5, $type_id, 'Indexed Query', 'h5')");

	$conn->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY, local_data_id INTEGER,
		data_template_id INTEGER, data_input_id INTEGER, rrd_step INTEGER, active TEXT,
		name TEXT, data_source_path TEXT)');
	$conn->exec("INSERT INTO data_template_data VALUES
		(11, 1, 7, 5, 300, 'on', 'ds', '<path_rra>/ds.rrd'),
		(12, 0, 7, 5, 300, 'on', 'tmpl', '')");

	$conn->exec('CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, xml_path TEXT)');
	$conn->exec("INSERT INTO snmp_query VALUES (1, '<path_cacti>/tests/fixtures/$fixture')");

	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY,
		local_data_template_rrd_id INTEGER, local_data_id INTEGER, data_source_name TEXT)');
	$conn->exec("INSERT INTO data_template_rrd VALUES (21, 31, 1, '$fields[0]'), (22, 32, 1, '$fields[1]')");

	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER,
		data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (1, 7, 31, '$fields[0]'), (1, 7, 32, '$fields[1]')");

	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, data_input_id INTEGER,
		type_code TEXT, input_output TEXT)');
	$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER,
		data_template_data_id INTEGER, value TEXT)');

	/* The full host column list from cacti.sql: substitute_host_data() expands
	   |host_*| tokens straight off SELECT *, so a trimmed table shows up as
	   undefined-key warnings rather than as a failure. */
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

	$conn->exec("INSERT INTO host (id, poller_id, site_id, description, hostname,
		snmp_community, snmp_version, snmp_port, snmp_timeout, snmp_retries, ping_retries,
		max_oids, bulk_walk_size, availability_method, disabled, deleted)
		VALUES (1, 1, 0, 'dev', 'dev.example.net', 'public', 2, 161, 500, 3, 3, 10, 10, 1, '', '')");

	// the script query path expands |host_*| tokens, which joins the device's site
	$conn->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT)');

	return $conn;
}

/**
 * Points lib/database.php's default connection at the seeded handle.
 *
 * @param PDO $conn The connection update_poller_cache() should read and write.
 *
 * @return void
 */
function poller_cache_wire(PDO $conn) : void {
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

	/* get_data_query_array() memoizes by snmp_query_id, so without this the
	   second fixture would be served the first one's parsed XML. */
	$GLOBALS['data_query_xml_arrays'] = [];
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('an output with no oid mapping produces no poller item at all', function () {
	poller_cache_wire(poller_cache_seed());

	$items = update_poller_cache(1, false);

	// pre-fix this was 2: withoutOid inherited withOid's $oid and was cached
	expect($items)->toHaveCount(1);
});

test('the one poller item built is the field that actually has an oid', function () {
	poller_cache_wire(poller_cache_seed());

	$items = update_poller_cache(1, false);

	expect($items[0])->toContain('withOid')
		->and($items[0])->not->toContain('withoutOid');
});

test('the cached oid is the mapped one with the data source index appended', function () {
	poller_cache_wire(poller_cache_seed());

	$items = update_poller_cache(1, false);

	// .10 is withOid's oid in the fixture, .3 is data_local.snmp_index
	expect($items[0])->toContain('.1.3.6.1.4.1.9999.1.1.10.3');
});

test('no poller item carries an oid belonging to a different output', function () {
	poller_cache_wire(poller_cache_seed());

	$items = update_poller_cache(1, false);

	$oids = [];

	foreach ($items as $item) {
		// the tuple ends with the oid followed by three trailing fields
		preg_match('/\'(\.[0-9.]+)\'/', $item, $match);

		$oids[] = $match[1] ?? '';
	}

	// the leak showed up as the same oid appearing under two data source names
	expect($oids)->toBe(array_unique($oids));
});

test('a script query output with no query_name mapping is not cached either', function () {
	poller_cache_wire(poller_cache_seed(
		DATA_INPUT_TYPE_SCRIPT_QUERY,
		'issue7135_script_query.xml',
		['withQueryName', 'withoutQueryName']
	));

	$items = update_poller_cache(1, false);

	// same leak on the script side: pre-fix withoutQueryName inherited
	// withQueryName's $script_path and was cached against another output's script
	expect($items)->toHaveCount(1)
		->and($items[0])->toContain('withQueryName')
		->and($items[0])->not->toContain('withoutQueryName');
});
