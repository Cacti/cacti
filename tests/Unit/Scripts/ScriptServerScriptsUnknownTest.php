<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Script Server data input scripts report 'U' for a missing device, a missing
 * row or an unreadable value instead of throwing inside the shared Script
 * Server process. The device rows use SNMP version 0, which lib/snmp.php
 * refuses before any network I/O, so every SNMP call returns its failure value.
 */

require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';

global $called_by_script_server;
$called_by_script_server = true;

$ss_scripts = [
	'ss_mikrotik_interfaces', 'ss_mikrotik_queues', 'ss_mikrotik_qtrees', 'ss_mikrotik_uptime',
	'ss_nimble_alletra_total', 'ss_nimble_alletra_volumes', 'ss_aruba_instant_ap', 'ss_aruba_instant_client',
	'ss_aruba_oscx_6x00', 'ss_fortigate_ipsec', 'ss_fortigate_ips', 'ss_fortigate_virus',
	'ss_netsnmp_lmsensors', 'ss_esxi_vhosts', 'ss_multicpu_avg', 'ss_count_oids', 'ss_host_disk',
	'ss_host_cpu', 'ss_webseer', 'ss_poller', 'ss_apache_stats', 'ss_sql', 'ss_net_snmp_disk_bytes',
	'ss_net_snmp_disk_io'
];

foreach ($ss_scripts as $ss_script) {
	require_once dirname(__DIR__, 3) . '/scripts/' . $ss_script . '.php';
}

/**
 * sqlite stand in for the server status queries, which FakeMySQLPDO does not translate
 */
class ScriptServerStatusPDO extends FakeMySQLPDO {
	public function prepare(string $query, array $options = []): PDOStatement|false {
		return parent::prepare($this->status($query), $options);
	}

	public function query(string $statement, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
		return parent::query($this->status($statement));
	}

	private function status(string $sql): string {
		$sql = preg_replace('/^\s*SHOW\s+GLOBAL\s+STATUS\s+LIKE\s+/i', 'SELECT Variable_name, Value FROM global_status WHERE Variable_name LIKE ', $sql);

		return preg_replace('/^\s*SHOW\s+GLOBAL\s+STATUS\b/i', 'SELECT Variable_name, Value FROM global_status', $sql);
	}
}

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$database_hostname = 'unit-ss';
	$database_port     = 0;
	$database_default  = 'unit-ss';

	$this->db = new ScriptServerStatusPDO();
	$this->db->exec('CREATE TABLE host (id INTEGER PRIMARY KEY, hostname TEXT, snmp_community TEXT,
		snmp_version INTEGER, snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT,
		snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT, snmp_context TEXT, snmp_engine_id TEXT,
		snmp_port INTEGER, snmp_timeout INTEGER, snmp_retries INTEGER, max_oids INTEGER)');
	$this->db->exec("INSERT INTO host VALUES (1, '127.0.0.1', 'public', 0, 'user', 'authpass', 'SHA',
		'privpass', 'AES', 'ctx', 'engine', 161, 500, 1, 10)");
	$this->db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$this->db->exec("INSERT INTO settings VALUES ('snmp_retries', '1')");

	$database_sessions = ["$database_hostname:$database_port:$database_default" => $this->db];
});

afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function ss_unknown_output(callable $call) : string {
	ob_start();
	$call();

	return (string) ob_get_clean();
}

// --- SNMP credentials loaded by host id ---

test('script server data queries no longer pass SNMP secrets as arguments', function (string $xml) {
	$contents = file_get_contents(dirname(__DIR__, 3) . '/resource/script_server/' . $xml);

	expect($contents)->toContain('|host_id| |host_snmp_version|:|host_snmp_port|:|host_snmp_timeout|:|host_ping_retries|:|host_max_oids|</arg_prepend>')
		->and($contents)->not->toContain('host_snmp_community')
		->and($contents)->not->toContain('host_snmp_password')
		->and($contents)->not->toContain('host_snmp_priv_passphrase');
})->with(['host_cpu.xml', 'host_disk.xml', 'entity_sensor.xml', 'f5_cpu.xml', 'juniper_operating.xml']);

test('the reduced argument loads the credentials for the device', function () {
	expect(snmp_script_auth_fields(1, '3:161:500:1:10'))->toBe([
		'3', '161', '500', '1', '10', 'public', 'user', 'authpass', 'SHA', 'privpass', 'AES', 'ctx', 'engine'
	]);
});

test('a cached argument in the old layout is used as passed', function () {
	expect(snmp_script_auth_fields(1, '2:161:500:1:10:cached'))->toBe([
		'2', '161', '500', '1', '10', 'cached', '', '', '', '', '', '', ''
	]);
});

test('an unknown device leaves the credential fields empty', function () {
	expect(snmp_script_auth_fields(99, '2:161:500:1:10'))->toBe(array_pad(['2', '161', '500', '1', '10'], 13, ''))
		->and(snmp_script_auth_fields(0, '2:161:500:1:10'))->toBe(array_pad(['2', '161', '500', '1', '10'], 13, ''));
});

test('the script server scripts resolve credentials through the device', function () {
	expect(ss_host_cpu('127.0.0.1', 1, '0:161:500:1:10', 'get', 'usage', '1'))->toBe('U')
		->and(ss_host_disk('127.0.0.1', 1, '0:161:500:1:10', 'get', 'description', '1'))->toBe('U');
});

// --- mikrotik ---

test('mikrotik values that are NULL or missing are unknown', function () {
	$this->db->exec('CREATE TABLE plugin_mikrotik_interfaces (host_id INTEGER, name TEXT, curInOctets INTEGER)');
	$this->db->exec("INSERT INTO plugin_mikrotik_interfaces VALUES (1, 'ether 1', NULL), (1, 'ether2', 42)");
	$this->db->exec('CREATE TABLE plugin_mikrotik_queues (host_id INTEGER, name TEXT, curBytesIn INTEGER)');
	$this->db->exec("INSERT INTO plugin_mikrotik_queues VALUES (1, 'q1', NULL), (1, 'q2', 7)");
	$this->db->exec('CREATE TABLE plugin_mikrotik_trees (host_id INTEGER, name TEXT, curHCBytes INTEGER)');
	$this->db->exec("INSERT INTO plugin_mikrotik_trees VALUES (1, '<t1>', NULL), (1, 't2', 9)");
	$this->db->exec('CREATE TABLE plugin_mikrotik_system (host_id INTEGER, uptime INTEGER)');
	$this->db->exec('INSERT INTO plugin_mikrotik_system VALUES (2, NULL), (3, 25920000)');

	expect(ss_mikrotik_interfaces(1, 'get', 'InOctets', 'ether_1'))->toBe('U')
		->and(ss_mikrotik_interfaces(1, 'get', 'InOctets', 'missing'))->toBe('U')
		->and(ss_mikrotik_interfaces(1, 'get', 'InOctets', 'ether2'))->toBe('42')
		->and(ss_mikrotik_queues(1, 'get', 'qsInOctets', 'q1'))->toBe('U')
		->and(ss_mikrotik_queues(1, 'get', 'qsInOctets', 'q2'))->toBe('7')
		->and(ss_mikrotik_qtrees(1, 'get', 'qtBytes', 't1'))->toBe('U')
		->and(ss_mikrotik_qtrees(1, 'get', 'qtBytes', 'missing'))->toBe('U')
		->and(ss_mikrotik_qtrees(1, 'get', 'qtBytes', 't2'))->toBe('9')
		->and(ss_mikrotik_uptime(1))->toBe('U')
		->and(ss_mikrotik_uptime(2))->toBe('U')
		->and(ss_mikrotik_uptime(3))->toBe('3');
});

test('the mikrotik queue tree lookup binds the index as data', function () {
	$this->db->exec('CREATE TABLE plugin_mikrotik_trees (host_id INTEGER, name TEXT, curHCBytes INTEGER)');
	$this->db->exec("INSERT INTO plugin_mikrotik_trees VALUES (1, 'x''y', 5)");

	expect(ss_mikrotik_qtrees(1, 'get', 'qtBytes', "x'y"))->toBe('5')
		->and(file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_mikrotik_qtrees.php'))->not->toContain('db_qstr(');
});

// --- nimble ---

test('nimble scripts report unknown for a missing device or field', function () {
	expect(ss_nimble_alletra_total(99))->toBe('iowrites:U iowritebytes:U ioreads:U ioreadbytes:U ioreadhits:U' . PHP_EOL)
		->and(ss_nimble_alletra_volumes(99, 'get', 'name', '1'))->toBe('U')
		->and(ss_nimble_alletra_volumes(1, 'get', 'bogus', '1'))->toBe('U')
		->and(ss_nimble_alletra_volumes(1, 'query', 'bogus'))->toBe('U');
});

test('nimble scripts survive an unreachable device', function () {
	expect(ss_nimble_alletra_total(1))->toBe('iowrites:0 iowritebytes:0 ioreads:0 ioreadbytes:0 ioreadhits:0 ')
		->and(ss_unknown_output(fn () => ss_nimble_alletra_volumes(1, 'index')))->toBe('')
		->and(ss_unknown_output(fn () => ss_nimble_alletra_volumes(1, 'num_index')))->toBe('0')
		->and(ss_unknown_output(fn () => ss_nimble_alletra_volumes(1, 'query', 'name')))->toBe('')
		->and(ss_unknown_output(fn () => ss_nimble_alletra_volumes(1, 'query', 'size')))->toBe('');
});

// --- aruba ---

test('aruba scripts report unknown for a missing device', function () {
	expect(ss_aruba_instant_ap(99))->toBe('count:U' . PHP_EOL)
		->and(ss_aruba_instant_client(99))->toBe('count:U' . PHP_EOL)
		->and(ss_aruba_oscx_6x00(99, 'fan', 'get', 'state', '1'))->toBe('U');
});

test('aruba scripts survive an unreachable device', function () {
	expect(ss_aruba_instant_ap(1))->toBe('count:0' . PHP_EOL)
		->and(ss_aruba_instant_client(1))->toBe('count:0' . PHP_EOL)
		->and(ss_unknown_output(fn () => ss_aruba_oscx_6x00(1, 'fan', 'index')))->toBe('')
		->and(ss_unknown_output(fn () => ss_aruba_oscx_6x00(1, 'fan', 'num_index')))->toBe('0')
		->and(ss_unknown_output(fn () => ss_aruba_oscx_6x00(1, 'fan', 'query', 'state')))->toBe('')
		->and(ss_unknown_output(fn () => ss_aruba_oscx_6x00(1, 'vsf', 'index')))->toBe('');
});

// --- fortigate ---

test('fortigate scripts report unknown for a missing device', function () {
	expect(ss_fortigate_ipsec(99))->toBe('total:U down:U' . PHP_EOL)
		->and(ss_fortigate_ips(99))->toBe('ips_detected:U ips_blocked:U ips_crit:U ips_high:U ips_ano:U' . PHP_EOL)
		->and(ss_fortigate_virus(99))->toBe('vir_detected:U vir_blocked:U vir_det_web:U vir_det_smb:U vir_det_mail:U' . PHP_EOL);
});

test('fortigate scripts report unknown rather than zero when SNMP fails', function () {
	expect(ss_fortigate_ipsec(1))->toBe('total:U down:U' . PHP_EOL)
		->and(ss_fortigate_ips(1))->toBe('ips_detected:U ips_blocked:U ips_crit:U ips_high:U ips_ano:U ')
		->and(ss_fortigate_virus(1))->toBe('vir_detected:U vir_blocked:U vir_det_web:U vir_det_smb:U vir_det_mail:U' . PHP_EOL);
});

// --- lmsensors, esxi, multicpu, count_oids ---

test('lmsensors no longer inspects the poller host', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_netsnmp_lmsensors.php');

	expect($source)->not->toContain('hostnamectl')
		->and($source)->not->toContain('4294967294')
		->and(ss_netsnmp_lmsensors(1, 'voltage', 'query', 'sensorreading'))->toBeNull()
		->and(ss_netsnmp_lmsensors(1, 'voltage', 'get', 'sensorreading', '1'))->toBe('U');
});

test('esxi uses the device SNMP settings', function () {
	expect(ss_esxi_vhosts(99))->toBe("vh:0 vh_state:0 tools_run:0 tools_nrun:0 tools_ninst:0\n")
		->and(ss_esxi_vhosts(1))->toBe("vh:0 vh_state:0 tools_run:0 tools_nrun:0 tools_ninst:0\n");
});

test('multicpu averages only the numeric rows', function () {
	expect(ss_multicpu_avg_load([['value' => '10'], ['value' => 'n/a'], ['value' => '30'], []]))->toBe(20)
		->and(ss_multicpu_avg_load([['value' => 'n/a']]))->toBe(0)
		->and(ss_multicpu_avg_load([]))->toBe(0)
		->and(ss_multicpu_avg(1))->toBe("load:0\n")
		->and(ss_multicpu_avg(99))->toBe("load:0\n");
});

test('count_oids walks only a numeric OID', function (string $oid) {
	expect(ss_count_oids(1, $oid))->toBe('0');
})->with(['', 'system', '.1.3.6', '1.3.6.1.2.1.1; x', '.1.3.6.1.2.1.2.2.1.1']);

test('count_oids reports zero for a missing device', function () {
	expect(ss_count_oids(99, '.1.3.6.1.2.1.2.2.1.1'))->toBe('0');
});

// --- host_disk ---

test('host_disk unwraps a negative Integer32 reading before scaling', function () {
	expect(ss_host_disk_scale('-2147483648', '4096'))->toBe((2147483648) * 4096)
		->and(ss_host_disk_scale('-1', ''))->toBe('U')
		->and(ss_host_disk_scale('100', '4096'))->toBe(409600)
		->and(ss_host_disk_scale('100', ''))->toBe('100')
		->and(ss_host_disk_scale('U', '4096'))->toBe('U')
		->and(ss_host_disk_scale('', '4096'))->toBe('U');
});

test('host_disk get with no reading is unknown', function () {
	$this->db->exec('CREATE TABLE host_snmp_cache (host_id INTEGER, field_name TEXT, field_value TEXT, snmp_index TEXT)');

	expect(ss_host_disk('127.0.0.1', 1, '0:161:500:1:10', 'get', 'total', '1'))->toBe('U');
});

// --- webseer and poller statistics ---

test('webseer names are printed without line breaks', function () {
	$this->db->exec('CREATE TABLE plugin_webseer_urls (id INTEGER, display_name TEXT, triggered INTEGER, enabled TEXT)');
	$this->db->exec("INSERT INTO plugin_webseer_urls VALUES (1, 'site' || char(13) || char(10) || '2!9', 0, 'on')");

	expect(ss_unknown_output(fn () => ss_webseer('query', 'webseerName')))->toBe('1!site2!9' . PHP_EOL);
});

test('webseer counts are zero for an empty URL table', function () {
	$this->db->exec('CREATE TABLE plugin_webseer_urls (id INTEGER, display_name TEXT, triggered INTEGER, enabled TEXT)');

	expect(ss_webseer_counts())->toBe('triggered:0 successful:0 disabled:0');
});

// --- apache stats ---

test('apache_stats accepts a hostname or IP address with an optional port', function (string $host, string $authority) {
	expect(ss_apache_stats_authority($host))->toBe($authority);
})->with([
	'name'           => ['www.example.com', 'www.example.com'],
	'name and port'  => ['www.example.com:8080', 'www.example.com:8080'],
	'ipv4'           => ['192.0.2.10', '192.0.2.10'],
	'ipv4 and port'  => ['192.0.2.10:81', '192.0.2.10:81'],
	'ipv6'           => ['2001:db8::1', '[2001:db8::1]'],
	'ipv6 and port'  => ['[2001:db8::1]:8443', '[2001:db8::1]:8443'],
	'single label'   => ['localhost', 'localhost'],
]);

test('apache_stats refuses a host that could change the URL', function (string $host) {
	expect(ss_apache_stats_authority($host))->toBeFalse()
		->and(ss_apache_stats($host))->toBe('U');
})->with([
	'path'        => ['example.com/other'],
	'query'       => ['example.com?x=1'],
	'fragment'    => ['example.com#x'],
	'user info'   => ['user@example.com'],
	'space'       => ['example.com x'],
	'newline'     => ["example.com\nHost: x"],
	'backslash'   => ['example.com\\x'],
	'port zero'   => ['example.com:0'],
	'port range'  => ['example.com:70000'],
	'bad ipv6'    => ['[2001:db8::zz]'],
	'leading dot' => ['.example.com'],
	'scheme'      => ['http:example.com'],
]);

test('apache_stats reports unknown without a host or a reachable server', function () {
	expect(ss_apache_stats(''))->toBe('U')
		->and(ss_apache_stats('127.0.0.1:1'))->toBe('U');
});

test('apache_stats keeps the existing output for a server-status page', function () {
	$page = "Total Accesses: 120\nTotal kBytes: 340\nCPULoad: .25\nBusyWorkers: 2\nIdleWorkers: 8\nScoreboard: _WR..K\n";

	expect(ss_apache_stats_parse($page, 'all'))->toBe('apache_total_hits:120 apache_total_kbytes:340 apache_cpuload:.25 ' .
		'apache_busy_workers:2 apache_idle_workers:8 thread_W:1 threadS:0 threadR:1 threadW:1 threadK:1 threadD:0 threadC:0 ' .
		'threadL:0 threadG:0 threadI:0 thread_O:2')
		->and(ss_apache_stats_parse($page, 'hits'))->toBe('apache_total_hits:120');
});

test('apache_stats passes only numeric values and known scoreboard states', function () {
	$page = "Total Accesses: 12 34\nBusyServers: 3\nScoreboard: _Zx!W\n";

	expect(ss_apache_stats_parse($page, 'all'))->toBe('apache_total_hits:U apache_busy_workers:3 thread_W:1 threadS:0 threadR:0 ' .
		'threadW:1 threadK:0 threadD:0 threadC:0 threadL:0 threadG:0 threadI:0 thread_O:0')
		->and(ss_apache_stats_parse("<html>not a status page</html>\n", 'all'))->toBe('U');
});

test('apache_stats bounds the request', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_apache_stats.php');

	expect($source)->toContain("'timeout'         => 5")
		->and($source)->toContain("'follow_location' => 0")
		->and($source)->toContain("'max_redirects'   => 0")
		->and($source)->toContain('0, 65536)');
});

// --- database status ---

function ss_unknown_status(PDO $db, array $status) : void {
	$db->exec('CREATE TABLE global_status (Variable_name TEXT, Value TEXT)');

	$insert = $db->prepare('INSERT INTO global_status VALUES (?, ?)');

	foreach ($status as $name => $value) {
		$insert->execute([$name, $value]);
	}
}

test('ss_sql reports the mysqladmin status fields from the server counters', function () {
	ss_unknown_status($this->db, [
		'Uptime'       => '1610375', 'Threads_connected' => '1', 'Queries' => '5290022', 'Questions' => '5165095',
		'Slow_queries' => '0', 'Opened_tables' => '27965', 'Open_tables' => '136'
	]);

	// MariaDB, which has no flush counter in its status line
	expect(ss_sql())->toBe('Uptime:1610375 Threads:1 Questions:5290022 SlowQueries:0 Opens:27965 OpenTables:136 QPS:3.284');
});

test('ss_sql includes the flush count when the server reports one', function () {
	expect(ss_sql_format([
		'Uptime'        => '1000', 'Threads_connected' => '4', 'Queries' => '2005', 'Slow_queries' => '2',
		'Opened_tables' => '50', 'Flush_commands' => '1', 'Open_tables' => '40'
	]))->toBe('Uptime:1000 Threads:4 Questions:2005 SlowQueries:2 Opens:50 FlushTables:1 OpenTables:40 QPS:2.005');
});

test('ss_sql handles a zero uptime and falls back to Questions', function () {
	expect(ss_sql_format([
		'Uptime'        => '0', 'Threads_connected' => '1', 'Questions' => '7', 'Slow_queries' => '0',
		'Opened_tables' => '1', 'Open_tables' => '1'
	]))->toBe('Uptime:0 Threads:1 Questions:7 SlowQueries:0 Opens:1 OpenTables:1 QPS:0.000');
});

test('ss_sql is unknown when the counters cannot be read', function () {
	ss_unknown_status($this->db, []);

	expect(ss_sql())->toBe('U')
		->and(ss_sql_format(['Uptime' => '1']))->toBe('U')
		->and(ss_sql_format([
			'Uptime'        => 'x', 'Threads_connected' => '1', 'Queries' => '7', 'Slow_queries' => '0',
			'Opened_tables' => '1', 'Open_tables' => '1'
		]))->toBe('U');
});

test('sql.php reads the query counter over the database connection', function () {
	// sql.php is a standalone CLI script that loads cli_check.php itself, so it is checked by source
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/sql.php');

	expect($source)->toContain("db_fetch_cell(\"SHOW GLOBAL STATUS LIKE 'Queries'\", 'Value')")
		->and($source)->toContain('ctype_digit((string) $queries) ? $queries : \'U\'')
		->and($source)->not->toContain('shell_exec')
		->and($source)->not->toContain('database_password');
});

// --- disk counters ---

function ss_unknown_disk_device(object $test, string $dimension, array $previous) : array {
	global $config;

	$saved = $config[OPTIONS_CLI] ?? [];

	// a stand-in for net-snmp answers, so the counter arithmetic runs without a device
	foreach (['path_snmpbulkwalk', 'path_snmpwalk', 'path_snmpget'] as $option) {
		$config[OPTIONS_CLI][$option] = dirname(__DIR__, 2) . '/fixtures/fake_snmp.sh';
	}

	$test->db->exec("INSERT INTO host VALUES (2, '127.0.0.1', 'public', 2, '', '', '', '', '', '', '', 1, 10, 1, 10)");
	$test->db->exec('CREATE TABLE host_value_cache (host_id INTEGER, dimension TEXT, value TEXT, time_to_live INTEGER)');

	$insert = $test->db->prepare('INSERT INTO host_value_cache VALUES (2, ?, ?, -1)');
	$insert->execute([$dimension, json_encode($previous)]);

	return $saved;
}

test('a disk byte counter that is not a number makes that measure unknown', function () {
	global $config;

	$saved = ss_unknown_disk_device($this, '2_bytes', ['uptime' => 0, 'br1' => '500', 'br3' => '100', 'bw1' => '1000', 'bw3' => '1000']);

	try {
		expect(ss_net_snmp_disk_bytes(2))->toBe('bytesread:U byteswritten:3000');
	} finally {
		$config[OPTIONS_CLI] = $saved;
	}
})->skip(DIRECTORY_SEPARATOR === '\\', 'uses a shell stand-in for net-snmp');

test('a disk I/O counter that is not a number makes that measure unknown', function () {
	global $config;

	$saved = ss_unknown_disk_device($this, '2_io', ['uptime' => 0, 'dr1' => '500', 'dr3' => '100', 'dw1' => '1000', 'dw3' => '1000']);

	try {
		expect(ss_net_snmp_disk_io(2))->toBe('reads:U writes:3000');
	} finally {
		$config[OPTIONS_CLI] = $saved;
	}
})->skip(DIRECTORY_SEPARATOR === '\\', 'uses a shell stand-in for net-snmp');
