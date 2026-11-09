<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiDiscoveryCorrectnessFixture;

use Cacti\Tests\Helpers\PhpSource;
use RuntimeException;

/*
 * Changed-line evidence for the discovery correctness fixes.
 *
 * The isolated discovery probes under tests/fixtures/ own their process
 * because they fake Cacti runtime functions globally; they are run here as
 * children with Xdebug enabled and their line hits are folded into this
 * probe's evidence. Form handlers, the network field definition and the
 * poller's top-level launch statements are cut out of the checked-in source at
 * their original line numbers and run in-process against recording doubles.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/PhpSource.php';

$root     = dirname(__DIR__, 3);
$fixtures = dirname(__DIR__);
$passed   = 0;

require_once $root . '/include/vendor/autoload.php';
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/CactiValidator.php';

/**
 * Fails the probe unless the actual value is identical to the expected one.
 *
 * @param mixed  $actual   The observed value.
 * @param mixed  $expected The required value.
 * @param string $label    What was checked.
 *
 * @return void
 */
function expect_same(mixed $actual, mixed $expected, string $label) : void {
	if ($actual !== $expected) {
		throw new RuntimeException($label . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
	}
	$GLOBALS['passed']++;
}

/**
 * Writes checked-in source to a temporary file at its original line numbers.
 *
 * @param string $file   The source file the lines belong to.
 * @param string $prefix Code placed on the first line, before the padding.
 * @param string $code   The source text that starts at $line.
 * @param int    $line   The line $code starts on in $file.
 * @param string $suffix Code appended after $code.
 *
 * @return mixed The value the temporary file returns.
 */
function load_at_line(string $file, string $prefix, string $code, int $line, string $suffix = '') : mixed {
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-discovery-slice-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . '; use Symfony\Component\Validator\Constraints as Assert; use CactiValidator; ' . $prefix . str_repeat("\n", $line - 1) . $code . $suffix);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		return require $temporary;
	} finally {
		unlink($temporary);
	}
}

/**
 * Loads one checked-in function into this namespace at its source lines.
 *
 * @param string $file     The source file.
 * @param string $function The function name.
 *
 * @return void
 */
function load_function(string $file, string $function) : void {
	$definition = PhpSource::functionDefinition(file_get_contents($file), $function);
	load_at_line($file, '', $definition['source'], $definition['line']);
}

/**
 * Loads a checked-in statement block as a closure taking its variables.
 *
 * @param string $file    The source file.
 * @param string $pattern A pattern matching exactly one block.
 *
 * @return \Closure A closure running the block with the given variables.
 */
function load_block(string $file, string $pattern) : \Closure {
	$source = file_get_contents($file);

	if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException('Expected one source block in ' . basename($file));
	}
	[$block, $offset] = $matches[0][0];

	return load_at_line($file, 'return static function (array $scope) { extract($scope);', $block, substr_count(substr($source, 0, $offset), "\n") + 1, '};');
}

/**
 * Runs an isolated probe in a child process and returns its JSON verdict.
 *
 * With coverage enabled the child records its own line hits, which are
 * merged into this probe's evidence once it has been written.
 *
 * @param string   $script The probe script.
 * @param string[] $args   The probe arguments.
 *
 * @return mixed The decoded verdict.
 */
function run_child(string $script, array $args = []) : mixed {
	$command     = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'memory_limit=1G'];
	$environment = getenv();

	if (getenv('CACTI_COVERAGE_FILE')) {
		$coverage                           = tempnam(sys_get_temp_dir(), 'cacti-discovery-child-');
		$environment['CACTI_COVERAGE_FILE'] = $coverage;
		$GLOBALS['cacti_child_coverage'][]  = $coverage;
		array_push($command, '-d', 'xdebug.mode=coverage', '-d', 'auto_prepend_file=' . dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php');
	}
	$pipes   = [];
	$process = proc_open(array_merge($command, [$script], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);

	if (!is_resource($process)) {
		throw new RuntimeException('Cannot start ' . basename($script));
	}
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0 || trim($errors) !== '') {
		throw new RuntimeException(basename($script) . ' failed: ' . trim($errors . $output));
	}

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

if (getenv('CACTI_COVERAGE_FILE')) {
	// Registered after FixtureCoverage's writer, so it runs once that file exists.
	register_shutdown_function(static function () : void {
		$target = getenv('CACTI_COVERAGE_FILE');
		$data   = json_decode(file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

		foreach ($GLOBALS['cacti_child_coverage'] ?? [] as $coverage) {
			foreach (json_decode(file_get_contents($coverage), true, 512, JSON_THROW_ON_ERROR) as $file => $child) {
				foreach ($child['lines'] as $line => $hits) {
					$data[$file]['lines'][$line] = max($hits, $data[$file]['lines'][$line] ?? -2);
				}
			}
			unlink($coverage);
		}
		file_put_contents($target, json_encode($data, JSON_THROW_ON_ERROR));
	});
}

// ---------------------------------------------------------------------------
// lib/api_automation.php: range parsing, enumeration, priming and arguments.
// ---------------------------------------------------------------------------
foreach ([
	'192.0.2.93/24'            => ['network' => '192.0.2.0', 'broadcast' => '192.0.2.255', 'cidr' => '24', 'type' => 'subnet', 'count' => 254, 'start' => '192.0.2.1', 'end' => '192.0.2.254'],
	'192.0.2.93/255.255.255.0' => ['network' => '192.0.2.0', 'broadcast' => '192.0.2.255', 'cidr' => 24, 'type' => 'subnet', 'count' => 254, 'start' => '192.0.2.1', 'end' => '192.0.2.254'],
	'192.168.*.*'              => ['network' => '192.168.0.0', 'broadcast' => '192.168.255.255', 'cidr' => 16, 'type' => 'subnet', 'count' => 65534, 'start' => '192.168.0.1', 'end' => '192.168.255.254'],
] as $range => $detail) {
	$verdict = run_child($fixtures . '/discovery_network_probe.php', [json_encode(['range' => $range, 'offsets' => []], JSON_THROW_ON_ERROR)]);
	expect_same($verdict['detail'], $detail, 'parsed ' . $range);
}

foreach (['192.0.2.0/24.5', '192.0.2.0/2.4e1', '10.1.*.1', '10.*.1.*', '192.0.2.0/', '/24', '192.0.2.0/24/garbage'] as $range) {
	$verdict = run_child($fixtures . '/discovery_network_probe.php', [json_encode(['range' => $range, 'offsets' => []], JSON_THROW_ON_ERROR)]);
	expect_same([$verdict['detail'], $verdict['addresses']], [false, null], 'rejected ' . $range);
}

$verdict = run_child($fixtures . '/discovery_network_probe.php', [json_encode(['range' => '10.0.0.0/15', 'offsets' => [0, 254, 255, 65534, 65535, 131069, 131070]], JSON_THROW_ON_ERROR)]);
expect_same($verdict['samples'], ['10.0.0.1', '10.0.0.255', '10.0.1.0', '10.0.255.255', '10.1.0.0', '10.1.255.254', false], 'wide range carries');

$verdict = run_child($fixtures . '/discovery_address_probe.php', [json_encode(['range' => '192.0.2.0/30,192.0.2.8/30', 'ignore' => '192.0.2.2,192.0.2.9'], JSON_THROW_ON_ERROR)]);
expect_same([$verdict['completed'], $verdict['addresses'], $verdict['logs']], [true, ['192.0.2.1', '192.0.2.10'], []], 'excluded addresses');

$verdict = run_child($fixtures . '/discovery_address_probe.php', [json_encode(['range' => '192.0.0.0/22', 'ignore' => ''], JSON_THROW_ON_ERROR)]);
expect_same([count($verdict['addresses']), count(array_unique($verdict['addresses'])), $verdict['writes']], [1022, 1022, 2], 'batched subnet');

$excluded = [];

for ($address = ip2long('192.0.0.1'); $address <= ip2long('192.0.3.254'); $address++) {
	$excluded[] = long2ip($address);
}
$verdict = run_child($fixtures . '/discovery_address_probe.php', [json_encode(['range' => '192.0.0.0/22', 'ignore' => implode(',', $excluded)], JSON_THROW_ON_ERROR)]);
expect_same($verdict, ['completed' => true, 'addresses' => [], 'writes' => 0, 'logs' => []], 'excluded batches');

$verdict = run_child($fixtures . '/discovery_address_probe.php', [json_encode(['range' => '192.0.2.0/29', 'ignore' => '', 'owned' => 4], JSON_THROW_ON_ERROR)]);
expect_same($verdict['logs'], ['NOTE: Discovery Network 7 queued 4 of 6 addresses; the rest repeat an overlapping range of this network or are already queued by another network'], 'shortfall note');

foreach ([[0, false, false, false], [3, true, true, true]] as [$thread, $force, $debug, $dryrun]) {
	$verdict = run_child($fixtures . '/discovery_launch_probe.php', [json_encode(compact('thread', 'force', 'debug', 'dryrun'), JSON_THROW_ON_ERROR)]);
	expect_same($verdict, $thread ? ['-q', '/tmp/cacti discovery/poller_automation.php', '--poller=2', '--network=7', '--thread=3', '--force', '--debug', '--dryrun']
		: ['-q', '/tmp/cacti discovery/poller_automation.php', '--poller=2', '--network=7'], 'scan arguments ' . $thread);
}

// ---------------------------------------------------------------------------
// poller_automation.php: discoverDevices() worker paths.
// ---------------------------------------------------------------------------
$worker = $fixtures . '/discovery_worker_address_probe.php';

foreach (['192.0.2.254', '192.0.2.255'] as $address) {
	$verdict = run_child($worker, [$address]);
	expect_same([$verdict['running'], $verdict['snmp']], [[$address], [$address]], 'worker probes ' . $address);
}

foreach (['managed-ip' => [1, [41]], 'managed-dns' => [1, [41]], 'deleted' => [0, []]] as $scenario => [$count, $reruns]) {
	$verdict = run_child($worker, ['192.0.2.255', $scenario, 'live']);
	expect_same([$verdict['claims'], $verdict['snmp'], $verdict['reruns'], $verdict['up_counts'], $verdict['snmp_counts']], [1, [], $reruns, $count, $count], 'managed ' . $scenario);
}

foreach (['cancel', 'missing-master'] as $scenario) {
	$verdict = run_child($worker, ['192.0.2.255', $scenario]);
	expect_same([$verdict['claims'], $verdict['writes'], $verdict['removed']], [0, [], [7]], 'cancelled ' . $scenario);
}

foreach (['duplicate-rejected' => 0, 'duplicate-allowed' => 1] as $scenario => $matches) {
	$verdict = run_child($worker, ['192.0.2.255', $scenario]);
	expect_same([$verdict['snmp'], $verdict['matches'], $verdict['host_adds']], [['192.0.2.255'], $matches, []], 'sysName policy ' . $scenario);
}

foreach (['managed-ip' => [], 'managed-sysname' => ['192.0.2.255']] as $scenario => $snmp) {
	$live = run_child($worker, ['192.0.2.255', $scenario, 'live']);
	$dry  = run_child($worker, ['192.0.2.255', $scenario, 'dryrun']);
	expect_same([$live['reruns'], $dry['reruns'], $dry['snmp'], $dry['up_counts']], [[41], [], $snmp, 1], 'dry run ' . $scenario);
}

foreach (['alive' => [1, 1, [], true], 'down' => [0, 0, ['192.0.2.255'], false]] as $mode => [$snmpCalls, $up, $down, $pingOnly]) {
	$verdict = run_child($fixtures . '/discovery_worker_ping_coverage.php', [$mode]);
	expect_same($verdict, ['finished' => true, 'snmp_calls' => $snmpCalls, 'up' => $up, 'down' => $down, 'completed' => ['192.0.2.255'], 'ping_only' => $pingOnly], 'ping ' . $mode);
}

// ---------------------------------------------------------------------------
// In-process slices: recording doubles shared by the loaded production code.
// ---------------------------------------------------------------------------
$GLOBALS['probe'] = [];

/**
 * Records one call made by the code under test.
 *
 * @param mixed ...$call The call name and arguments.
 *
 * @return void
 */
function record(mixed ...$call) : void {
	$GLOBALS['probe']['calls'][] = $call;
}

/**
 * Returns the recorded calls with the given name.
 *
 * @param string $name The call name.
 *
 * @return list<array> The matching calls without their name.
 */
function calls(string $name) : array {
	$result = [];

	foreach ($GLOBALS['probe']['calls'] ?? [] as $call) {
		if ($call[0] === $name) {
			$result[] = array_slice($call, 1);
		}
	}

	return $result;
}

function __(string $text, mixed ...$args) : string {
	return $text;
}
function read_config_option(string $name) : string {
	return ['path_php_binary' => '/usr/bin/php', 'path_webroot' => '/var/www/cacti'][$name] ?? '';
}
function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}
function cacti_strtolower(string $value) : string {
	return strtolower($value);
}
function cacti_log(mixed ...$args) : void {
}
function automation_debug(string $message) : void {
}
function automation_get_pid() : string {
	return 'fixture';
}
function exec_background(string $binary, array $args) : void {
	record('exec', $binary, $args);
}
function api_scheduler_form() : array {
	return ['sched_type' => []];
}
function isrv(string $name) : bool {
	return array_key_exists($name, $GLOBALS['probe']['request']);
}
function grv(string $name) : mixed {
	return $GLOBALS['probe']['request'][$name] ?? '';
}
function gnrv(string $name) : mixed {
	return grv($name);
}
function gfrv(string $name) : int {
	return $GLOBALS['probe']['request'][$name] = (int) grv($name);
}
function get_hash_automation(int $id, string $table) : string {
	record('hash', $id, $table);

	return str_repeat('b', 32);
}
function db_column_exists(string $table, string $column) : bool {
	return $column === 'name';
}
function db_qstr(mixed $value) : string {
	return "'" . str_replace("'", "''", (string) $value) . "'";
}
function db_like_escape(string $value) : string {
	return $value;
}
function raise_message(mixed ...$args) : void {
	record('message', $args[0]);
}
function is_error_message() : bool {
	return !empty($_SESSION[SESS_ERROR_FIELDS]);
}
function sql_save(array $row, string $table) : int {
	record('save', $row, $table);

	return 27;
}
function header(string $location) : void {
	record('header', $location);
}
function db_fetch_row_prepared(string $sql, array $params) : array {
	$probe = &$GLOBALS['probe'];

	if (str_contains($sql, 'FROM automation_networks')) {
		return $probe['network'];
	}

	if (str_contains($sql, 'FROM automation_ips')) {
		return array_shift($probe['queue']) ?? [];
	}

	if (str_contains($sql, 'FROM host')) {
		record('host_lookup', $params);

		foreach ($probe['hosts'] as $host) {
			if (in_array($host['hostname'], $params, true)) {
				return $host;
			}
		}

		return [];
	}

	throw new RuntimeException('Unexpected row lookup: ' . $sql);
}
function db_fetch_cell_prepared(string $sql, array $params) : mixed {
	if (str_contains($sql, 'SELECT command')) {
		return 'running';
	}

	if (str_contains($sql, 'SELECT field_name')) {
		return $params[0];
	}

	if ($sql === 'SELECT ?') {
		return $params[0];
	}

	if (str_contains($sql, 'hostname LIKE')) {
		record('sysname_lookup', $params);

		return $GLOBALS['probe']['cacti_sysname'];
	}

	if (str_contains($sql, 'COUNT(*)')) {
		return 0;
	}

	throw new RuntimeException('Unexpected cell lookup: ' . $sql);
}
function db_execute_prepared(string $sql, array $params = []) : bool {
	if (!str_contains($sql, 'UPDATE automation_ips')) {
		record('execute', preg_replace('/\s+/', ' ', trim($sql)), $params);
	}

	return true;
}
function db_execute(string $sql) : bool {
	record('found', $sql);

	return true;
}
function automation_get_dns_from_ip(string $address, string $dns, int $timeout) : string {
	return $GLOBALS['probe']['dns'];
}
function gethostbyaddr(string $address) : string {
	return $GLOBALS['probe']['reverse'];
}
function is_ipaddress(string $address) : bool {
	return filter_var($address, FILTER_VALIDATE_IP) !== false;
}
function markIPRunning(string $address, int $network_id) : void {
}
function markIPDone(string $address, int $network_id) : void {
	record('done', $address);
}
function addUpDevice(int $network_id, int $pid) : void {
	record('up');
}
function addSNMPDevice(int $network_id, int $pid) : void {
	record('snmp');
}
function rerunDataQueries(int $host_id, array $network) : void {
	record('rerun', $host_id);
}
function automation_valid_snmp_device(array &$device) : bool {
	$device = array_merge($device, $GLOBALS['probe']['snmp']);

	return true;
}
function automation_find_os(string $description, string $oid, string $name) : array|false {
	record('find_os', $description, $oid, $name);

	return $GLOBALS['probe']['os'];
}
function automation_add_device(array $device) : int {
	record('add', $device);

	return 55;
}
function automation_update_device(int $host_id) : void {
	record('update_device', $host_id);
}

final class Net_Ping {
	public array $host  = [];
	public int $retries = 0;
	public int $port    = 0;
}

load_function($root . '/lib/api_automation.php', 'automation_get_scan_arguments');
load_function($root . '/lib/api_automation.php', 'automation_get_next_host');
load_function($root . '/automation_networks.php', 'network_get_field_array');
load_function($root . '/automation_graph_rules.php', 'form_save');
load_function($root . '/poller_automation.php', 'discoverDevices');

// An unparsable start address yields no next host instead of a bogus one.
expect_same(automation_get_next_host('not-an-address', 4, 1, 'not-an-address'), false, 'next host of an invalid start');
expect_same(automation_get_next_host('192.0.2.1', 4, 1, '192.0.2.0/29'), '192.0.2.2', 'next host');

// The network form explains that wildcards must be trailing octets.
$fields = network_get_field_array();
expect_same(str_contains($fields['subnet_range']['description'], 'Wildcards must be the trailing octets, so a form such as 10.1.*.1 is rejected.'), true, 'subnet range help');

// Rule item saves look up the existing hash by the validated item id.
foreach (['save_component_automation_graph_rule_item' => ['automation_graph_rule_items', 'ifDescr'], 'save_component_automation_match_item' => ['automation_match_rule_items', 'name']] as $component => [$table, $field]) {
	$_SESSION         = [];
	$GLOBALS['probe'] = ['request' => [$component => '1', 'id' => '3', 'item_id' => '17', 'sequence' => '1', 'operation' => '0', 'field' => $field, 'operator' => '7', 'pattern' => 'eth0']];
	form_save();
	$save = calls('save');
	expect_same(calls('hash'), [[17, $table]], 'hash lookup for ' . $table);
	expect_same([$save[0][0]['id'], $save[0][0]['hash'], $save[0][1]], [17, str_repeat('b', 32), $table], 'saved ' . $table);
}

// The master and network launchers pass the scan mode on as separate arguments.
$GLOBALS['probe'] = [];
load_block($root . '/poller_automation.php', "/^\t\t\t\texec_background\\(read_config_option\\('path_php_binary'\\),\n\t\t\t\t\tautomation_get_scan_arguments[^\n]*\n/m")(['poller_id' => '2', 'network' => ['id' => '7'], 'force' => true, 'debug' => false, 'dryrun' => true]);
load_block($root . '/poller_automation.php', "/^\t\texec_background\\(read_config_option\\('path_php_binary'\\),\n\t\t\tautomation_get_scan_arguments[^\n]*\n/m")(['poller_id' => '2', 'network_id' => '7', 'curthread' => 3, 'force' => false, 'debug' => true, 'dryrun' => true]);
expect_same(calls('exec'), [
	['/usr/bin/php', ['-q', '/var/www/cacti/poller_automation.php', '--poller=2', '--network=7', '--force', '--dryrun']],
	['/usr/bin/php', ['-q', '/var/www/cacti/poller_automation.php', '--poller=2', '--network=7', '--thread=3', '--debug', '--dryrun']],
], 'launch arguments');

const NETWORK = [
	'name'           => 'Fixture',
	'dns_servers'    => '192.0.2.53',
	'enable_netbios' => '',
	'snmp_id'        => 7,
	'poller_id'      => 1,
	'site_id'        => 0,
	'ping_retries'   => 0,
	'ping_port'      => 0,
	'ping_method'    => PING_SNMP,
	'same_sysname'   => '',
	'add_to_cacti'   => 'on',
];

const SNMP_ANSWER = [
	'snmp_sysName'     => 'edge.example.net',
	'snmp_sysDescr'    => 'Fixture OS',
	'snmp_sysObjectID' => '.1.3.6.1.4.1.8072',
	'snmp_sysUptime'   => '100',
	'snmp_sysContact'  => 'noc',
	'snmp_sysLocation' => 'Lab',
];

/**
 * Runs discoverDevices() over one queued address.
 *
 * @param array $scenario Overrides for the network, SNMP answer and lookups.
 *
 * @return void
 */
function discover(array $scenario) : void {
	$GLOBALS['dryrun'] = $scenario['dryrun'] ?? false;
	$GLOBALS['probe']  = array_merge([
		'network'       => NETWORK,
		'queue'         => [['ip_address' => '192.0.2.10']],
		'hosts'         => [],
		'dns'           => 'edge.example.net',
		'reverse'       => 'edge.example.net',
		'cacti_sysname' => 0,
		'os'            => false,
		'calls'         => [],
	], $scenario);
	$GLOBALS['probe']['snmp'] = array_merge(SNMP_ANSWER, $scenario['snmp'] ?? []);

	expect_same(discoverDevices(7, 1), true, 'discovery finished');
}

$template = [
	'name'                => 'Fixture OS',
	'host_template'       => 9,
	'availability_method' => 2,
	'populate_location'   => 'on',
	'description_pattern' => '|sysName| |ipAddress| |dnsName| |dnsShortName| |sysLocation|',
];

// A matched new device is added with its template, location and description.
discover(['os' => $template]);
$added = calls('add')[0][0];
expect_same([$added['os'], $added['host_template'], $added['availability_method'], $added['location'], $added['description']],
	['Fixture OS', 9, 2, 'Lab', 'edge.example.net 192.0.2.10 edge.example.net edge Lab'], 'added device');
expect_same(calls('sysname_lookup')[0][0], ['edge.example.net', '192.0.2.10', 'edg%'], 'sysName lookup');
expect_same(array_column(calls('execute'), 0), [
	'UPDATE host SET snmp_sysDescr = ? WHERE id = ?', 'UPDATE host SET snmp_sysObjectID = ? WHERE id = ?',
	'UPDATE host SET snmp_sysUptimeInstance = ? WHERE id = ?', 'UPDATE host SET snmp_sysContact = ? WHERE id = ?',
	'UPDATE host SET snmp_sysName = ? WHERE id = ?', 'UPDATE host SET snmp_sysLocation = ? WHERE id = ?',
	'DELETE FROM automation_devices WHERE host_id = ?'], 'added device metadata');
expect_same([calls('update_device'), count(calls('snmp')), calls('done')], [[[55]], 1, [['192.0.2.10']]], 'added device finished');
expect_same(str_contains(calls('found')[0][0], "VALUES (7, 55, 'edge.example.net', '192.0.2.10'"), true, 'found row keeps the new host');

// Without a description pattern or location population neither is set.
discover(['os' => ['description_pattern' => '', 'populate_location' => ''] + $template, 'snmp' => ['snmp_sysLocation' => '']]);
expect_same(array_key_exists('description', calls('add')[0][0]) || array_key_exists('location', calls('add')[0][0]), false, 'plain added device');

// A dry run, a missing template and a disabled add record the device only.
foreach (['dry run' => [['os' => $template, 'dryrun' => true], ''], 'no template' => [[], ''], 'add disabled' => [['os' => $template, 'network' => ['add_to_cacti' => ''] + NETWORK], 'Fixture OS']] as $label => [$scenario, $os]) {
	discover($scenario);
	expect_same([calls('add'), calls('update_device'), calls('done')], [[], [], [['192.0.2.10']]], $label . ' adds nothing');
	expect_same(str_contains(calls('found')[0][0], "VALUES (7, 0, 'edge.example.net'") && str_contains(calls('found')[0][0], "'" . $os . "', 1, 1,"), true, $label . ' found row');
}

// sysNames that are addresses or contain numeric labels are kept whole;
// other names are also looked up by their first label.
foreach (['192.0.2.77' => ['192.0.2.77', '192.0.2.77'], 'rack.7.example' => ['rack.7.example', 'rack.7.example'], 'core.example.net' => ['core', 'core.example.net']] as $sysName => $lookup) {
	discover(['snmp' => ['snmp_sysName' => $sysName]]);
	expect_same(calls('host_lookup')[1][0], $lookup, 'short sysName of ' . $sysName);
}

// A sysName Cacti already manages is skipped before it is counted or recorded.
discover(['os' => $template, 'cacti_sysname' => 1]);
expect_same([calls('snmp'), calls('find_os'), calls('found'), calls('done')], [[], [], [], [['192.0.2.10']]], 'known sysName skipped');

// A reverse name without a domain is matched as is.
discover(['reverse' => 'edge']);
expect_same(calls('sysname_lookup')[0][0], ['edge.example.net', '192.0.2.10', 'edge%'], 'undotted reverse name');

// A deleted host found by sysName is neither counted nor reindexed.
discover(['hosts' => [['id' => 41, 'hostname' => 'edge', 'status' => 3, 'snmp_version' => 2, 'deleted' => 'on']]]);
expect_same([calls('up'), calls('rerun'), calls('found'), calls('done')], [[], [], [], [['192.0.2.10']]], 'deleted sysName host');

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
