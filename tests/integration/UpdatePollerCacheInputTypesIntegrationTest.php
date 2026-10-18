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
 * update_poller_cache() builds a different poller item for each data input
 * type. Issue7135PollerCacheLoopStateIntegrationTest covers the SNMP and script
 * data query loops; this file runs the script, SNMP, script server query and
 * missing input paths against a sqlite-backed connection and reads back the
 * items each one produced.
 *
 * update_poller_cache() and api_poller_cache_item_add() memoize by data input,
 * template and device id for the life of the process, so every scenario here
 * uses ids no other test file seeds.
 */

require_once dirname(__DIR__, 2) . '/lib/utility.php';
require_once dirname(__DIR__, 2) . '/lib/api_poller.php';
require_once dirname(__DIR__, 2) . '/lib/plugins.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';

/**
 * global_constants.php fixes SQL_NO_CACHE from the configured database type, so
 * a full run can hand sqlite a MySQL modifier in the middle of a SELECT list.
 */
class InputTypesPDO extends FakeMySQLPDO {
	public function prepare(string $query, array $options = []) : PDOStatement|false {
		return parent::prepare(preg_replace('/\bSQL_NO_CACHE\b/', '', $query), $options);
	}
}

/**
 * Creates the tables update_poller_cache() reads, with one device.
 *
 * @param int $host_id The device the data sources belong to.
 *
 * @return InputTypesPDO The seeded connection.
 */
function input_types_seed(int $host_id) : InputTypesPDO {
	$conn = new InputTypesPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES
		('path_php_binary', '/usr/bin/php'),
		('log_destination', '1'),
		('log_verbosity', '2'),
		('path_cactilog', " . $conn->quote(sys_get_temp_dir() . '/cacti-input-types-' . getmypid() . '.log') . ')');

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
	$conn->exec("INSERT INTO host (id, poller_id, site_id, description, hostname,
		snmp_community, snmp_version, snmp_port, snmp_timeout, snmp_retries, ping_retries,
		max_oids, bulk_walk_size, availability_method, disabled, deleted)
		VALUES ($host_id, 1, 0, 'dev', 'router.example.net', 'public', 2, 161, 500, 3, 3, 10, 10, 1, '', '')");

	return $conn;
}

function input_types_wire(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'inputtypes';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'inputtypes';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['database_sessions']      = ['inputtypes:0:inputtypes' => $conn];

	// earlier files leave their own log settings in the option cache
	$GLOBALS['config'][OPTIONS_CLI] = [];
}

beforeEach(function () {
	$this->global_state = [];

	foreach (['database_sessions', 'database_hostname', 'database_port', 'database_default', 'database_total_queries', 'data_query_xml_arrays', 'config'] as $name) {
		$this->global_state[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
	}

	$GLOBALS['data_query_xml_arrays'] = [];
	$this->log_file                   = sys_get_temp_dir() . '/cacti-input-types-' . getmypid() . '.log';
	@unlink($this->log_file);
});

afterEach(function () {
	foreach ($this->global_state as $name => [$existed, $value]) {
		if ($existed) {
			$GLOBALS[$name] = $value;
		} else {
			unset($GLOBALS[$name]);
		}
	}

	@unlink($this->log_file);
});

test('a multi-output script is cached with its expanded command and no item name', function () {
	$conn = input_types_seed(81);

	$conn->exec("INSERT INTO data_local VALUES (801, 81, 0, '', 811)");
	$conn->exec('INSERT INTO data_input VALUES (812, ' . DATA_INPUT_TYPE_SCRIPT . ", 'Load', 'h812', '/usr/bin/uptime.sh <host> <mode>')");
	$conn->exec("INSERT INTO data_template_data VALUES (813, 801, 811, 812, 60, 'on', 'load', '<path_rra>/load.rrd')");
	$conn->exec("INSERT INTO data_input_fields VALUES
		(814, 812, 'host', 'hostname', 'in', ''),
		(815, 812, 'mode', '', 'in', ''),
		(816, 812, 'one', '', 'out', 'on'),
		(817, 812, 'five', '', 'out', 'on')");
	$conn->exec("INSERT INTO data_input_data VALUES (814, 813, 'router.example.net'), (815, 813, 'short')");
	input_types_wire($conn);

	$items = update_poller_cache(801, false);

	expect($items)->toHaveCount(1)
		->and($items[0])->toStartWith('(801, 1, 81, ' . POLLER_ACTION_SCRIPT . ',')
		->and($items[0])->toContain("'/usr/bin/uptime.sh ''router.example.net'' ''short'''")
		->and($items[0])->toContain("'', '" . CACTI_PATH_RRA . "/load.rrd', '1', '60'");
});

test('an SNMP data source is cached with the oid from its input data', function () {
	$conn = input_types_seed(82);

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
	input_types_wire($conn);

	$items = update_poller_cache(821, false);

	expect($items)->toHaveCount(1)
		->and($items[0])->toStartWith('(821, 1, 82, ' . POLLER_ACTION_SNMP . ',')
		->and($items[0])->toContain("'router.example.net', 'public'")
		->and($items[0])->toContain("'1161'")
		->and($items[0])->toContain("'sysUpTime'")
		->and($items[0])->toContain("'.1.3.6.1.2.1.1.3.0'");
});

test('a script server query is cached as a PHP script call for its index', function () {
	$conn = input_types_seed(83);

	$conn->exec("INSERT INTO data_local VALUES (831, 83, 832, '2', 833)");
	$conn->exec('INSERT INTO data_input VALUES (834, ' . DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER . ", 'Get Script Server Data (Indexed)', 'h834', '')");
	$conn->exec("INSERT INTO data_template_data VALUES
		(835, 831, 833, 834, 300, 'on', 'cpu', '<path_rra>/cpu.rrd'),
		(836, 0, 833, 834, 300, 'on', 'cpu template', '')");
	$conn->exec("INSERT INTO snmp_query VALUES (832, '<path_cacti>/resource/script_server/host_cpu.xml')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (837, 838, 831, 'cpu')");
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (1, 833, 838, 'hrProcessorLoad')");
	input_types_wire($conn);

	$items = update_poller_cache(831, false);

	expect($items)->toHaveCount(1)
		->and($items[0])->toStartWith('(831, 1, 83, ' . POLLER_ACTION_SCRIPT_PHP . ',')
		->and($items[0])->toContain("'cpu'")
		->and($items[0])->toContain("'" . CACTI_PATH_BASE . "/scripts/ss_host_cpu.php ss_host_cpu ''router.example.net'' ''83'' ")
		->and($items[0])->toContain("''get'' ''usage'' ''2''");
});

test('a data source whose input method is gone logs the corruption and caches nothing', function () {
	$conn = input_types_seed(84);

	$conn->exec("INSERT INTO data_local VALUES (841, 84, 0, '', 842)");
	$conn->exec("INSERT INTO data_template_data VALUES (843, 841, 842, 844, 300, 'on', 'orphan', '')");
	input_types_wire($conn);

	$items = update_poller_cache(841, false);
	$log   = (string) @file_get_contents($this->log_file);

	expect($items)->toBe([])
		->and($log)->toContain('WARNING: Repopulate Poller Cache found Data Input Missing for Data Source 841.  Database may be corrupted');
});

/**
 * Seeds poller_item and a disabled remote poller, which remote_poller_up()
 * reports as down whenever it last checked in.
 */
function input_types_seed_remote() : InputTypesPDO {
	$conn = input_types_seed(85);

	$conn->exec("INSERT INTO settings (name, value) VALUES ('poller_interval', '300')");
	$conn->exec('CREATE TABLE poller (id INTEGER PRIMARY KEY, last_status TEXT, disabled TEXT)');
	$conn->exec("INSERT INTO poller VALUES (2, datetime('now'), 'on')");
	$conn->exec('CREATE TABLE poller_item (local_data_id INTEGER, poller_id INTEGER, host_id INTEGER,
		action INTEGER, hostname TEXT, snmp_community TEXT, snmp_version INTEGER, snmp_timeout INTEGER,
		snmp_retries INTEGER, snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT,
		snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT, snmp_context TEXT, snmp_engine_id TEXT,
		snmp_port INTEGER, rrd_name TEXT, rrd_path TEXT, rrd_num INTEGER, rrd_step INTEGER,
		rrd_next_step INTEGER, arg1 TEXT, arg2 TEXT, arg3 TEXT, present INTEGER,
		PRIMARY KEY (local_data_id, rrd_name))');
	$conn->exec("INSERT INTO poller_item (local_data_id, poller_id, host_id, rrd_name, present)
		VALUES (851, 2, 85, 'stale', 1)");

	return $conn;
}

function input_types_remote_item() : string {
	return "(851, 2, 85, 1, 'router.example.net', 'public', '2', '500', '3', '', '', '', '', '', '', '', " .
		"'161', 'load', '/rra/load.rrd', '1', '300', '0', '/usr/bin/uptime.sh', '', '', '1')";
}

test('a flush for a disabled remote poller rebuilds locally and asks for one FullSync', function () {
	global $no_http_headers;

	$conn = input_types_seed_remote();
	input_types_wire($conn);

	$prior_session   = $_SESSION ?? null;
	$prior_headers   = $no_http_headers ?? null;
	$no_http_headers = true;
	$_SESSION        = [];
	$items           = [input_types_remote_item()];

	try {
		poller_update_poller_cache_from_buffer([851], $items, 2);
		$messages = $_SESSION[SESS_MESSAGES];
	} finally {
		$_SESSION        = $prior_session;
		$no_http_headers = $prior_headers;
	}

	$rows = $conn->query('SELECT rrd_name, present FROM poller_item WHERE local_data_id = 851 ORDER BY rrd_name')->fetchAll();

	expect($rows)->toBe([['rrd_name' => 'load', 'present' => 1]])
		->and($messages)->toHaveCount(1)
		->and($messages['poller_down_2']['message'])->toBe('Remote Poller 2 is Down, you will need to perform a FullSync once it is up again')
		->and($messages['poller_down_2']['level'])->toBe(MESSAGE_LEVEL_WARN);
});

test('a flush with no data source ids for a disabled remote poller still warns once', function () {
	global $no_http_headers;

	$conn = input_types_seed_remote();
	input_types_wire($conn);

	$prior_session   = $_SESSION ?? null;
	$prior_headers   = $no_http_headers ?? null;
	$no_http_headers = true;
	$_SESSION        = [];
	$items           = [input_types_remote_item()];

	try {
		poller_update_poller_cache_from_buffer([], $items, 2);
		$messages = $_SESSION[SESS_MESSAGES];
	} finally {
		$_SESSION        = $prior_session;
		$no_http_headers = $prior_headers;
	}

	// without ids nothing is marked stale, so the existing row survives
	$rows = $conn->query('SELECT rrd_name FROM poller_item WHERE local_data_id = 851 ORDER BY rrd_name')->fetchAll(PDO::FETCH_COLUMN);

	expect($rows)->toBe(['load', 'stale'])
		->and(array_keys($messages))->toBe(['poller_down_2'])
		->and($messages['poller_down_2']['message'])->toContain('Remote Poller 2 is Down');
});
