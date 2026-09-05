<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

function consolidatedSource(string $path): string {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);

	expect($source)->not->toBeFalse("$path must be readable");

	return $source;
}

test('poller cleanup retains active rows and avoids a repeated engine conversion', function (): void {
	$source = consolidatedSource('poller.php');

	expect($source)->toContain("AND end_time != '0000-00-00 00:00:00'")
		->and($source)->toContain("TABLE_NAME = 'poller_output'")
		->and(strpos($source, "TABLE_NAME = 'poller_output'"))
		->toBeLessThan(strpos($source, 'ALTER TABLE poller_output ENGINE=MEMORY'));
});

test('database recovery and identifier handling retain the corrected connection', function (): void {
	$source = consolidatedSource('lib/database.php');

	expect($source)->toContain('function db_check_reconnect(mixed &$db_conn = false')
		->and($source)->toContain('$db_conn = $cnn_id;')
		->and($source)->toContain('if (!$db_conn->inTransaction())')
		->and($source)->toContain("str_replace('`', '``', \$k)")
		->and($source)->toContain("VALUES(' . \$ek . ')'");
});

test('CSV export capacity follows the selected archive resolution', function (): void {
	$source = consolidatedSource('lib/rrd.php');
	$start  = 0;
	$end    = 90 * 24 * 60 * 60;
	$step   = 300;

	expect(max(10000, (int) ceil(abs($end - $start) / $step) + 10))->toBe(25930)
		->and($source)->toContain('$export_rows = (int) ceil(abs($graph_end - $graph_start) / $export_step) + 10;')
		->and($source)->toContain("'--maxrows=' . max(10000, \$export_rows)");
});

test('automation host offsets carry across every IPv4 octet', function (): void {
	$source = consolidatedSource('lib/api_automation.php');
	$offset = static fn (string $start, int $count): string|false => long2ip((int) ip2long($start) + $count);

	expect($offset('10.1.255.255', 1))->toBe('10.2.0.0')
		->and($offset('10.255.255.255', 1))->toBe('11.0.0.0')
		->and($source)->toContain('return long2ip($base + $count);');
});

test('SNMP engine uptime is used only when it covers system uptime', function (): void {
	$source = consolidatedSource('cmd.php');
	$select = static fn (int|false $engine, int|false $system): int|false => $engine !== false && ($system === false || $engine >= $system) ? $engine : $system;

	expect($select(100, 500))->toBe(500)
		->and($select(600, 500))->toBe(600)
		->and($select(600, false))->toBe(600)
		->and($source)->toContain('$uptimeAlt >= $uptimeSys');
});

test('single-value lm-sensors reads use query-path scaling', function (): void {
	$source = consolidatedSource('scripts/ss_netsnmp_lmsensors.php');

	expect($source)->toContain("if (\$sensor_type == 'voltage' || \$sensor_type == 'temperature')")
		->and($source)->toContain('$snmp_test = $snmp_test / 1000;')
		->and(42000 / 1000)->toBe(42);
});

test('theme validation marks the configured theme valid', function (): void {
	$source = consolidatedSource('lib/functions.php');
	$probe  = strpos($source, "file_exists(CACTI_PATH_INCLUDE . '/themes/' . \$theme . '/main.css')");

	expect($probe)->not->toBeFalse()
		->and(strpos($source, '$valid = true;', $probe))->not->toBeFalse();
});

test('automation sorting accepts query aliases', function (): void {
	$source = consolidatedSource('lib/api_automation.php');

	expect($source)->toContain("static \$aliases = ['site_name', 'host_template_name'];")
		->and($source)->toContain('in_array($column, $aliases, true)');
});

test('automation filters bind the selected values and columns', function (): void {
	$devices = consolidatedSource('automation_devices.php');
	$html    = consolidatedSource('lib/html.php');

	expect($devices)->toContain("'os = ?'")
		->and($devices)->toContain('$sql_params[] = $os;')
		->and($html)->toContain("'h.host_template_id = ' . grv('host_template_id')")
		->and($html)->not->toContain("'h.location = ' . grv('host_template_id')");
});

test('remote host listing applies hostname and description filters', function (): void {
	$source = consolidatedSource('api/include/db_functions.php');

	expect($source)->toContain('hostname LIKE ?')
		->and($source)->toContain('description LIKE ?')
		->and($source)->toContain("\$values[]     = '%' . \$params['hostname'] . '%';")
		->and($source)->toContain("\$values[]     = '%' . \$params['description'] . '%';");
});

test('direct collector address authorization precedes hostname availability', function (): void {
	$source = consolidatedSource('remote_agent.php');
	$direct = strpos($source, 'if ($direct_match) {');
	$empty  = strpos($source, 'if (!cacti_sizeof($allowed_hostnames)) {');

	expect($direct)->not->toBeFalse()
		->and($empty)->not->toBeFalse()
		->and($direct)->toBeLessThan($empty);
});

test('cactid passes a writable reconnect handle', function (): void {
	$source = consolidatedSource('cactid.php');

	expect($source)->toContain('$reconnect_conn = false;')
		->and($source)->toContain('db_check_reconnect($reconnect_conn, $logrecon);');
});
