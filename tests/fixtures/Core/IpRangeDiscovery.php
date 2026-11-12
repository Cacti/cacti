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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';

$ipRangeCases = [
	['', ['192.0.2.1'], false],
	['192.0.2.1', [], false],
	['192.0.2.1', [' ', ' 192.0.2.1 '], true],
	['192.0.2.1', ['192.0.2.2'], false],
	['192.0.2.1', ['192.0.2.0/24'], true],
	['198.51.100.1', ['192.0.2.0/24'], false],
	['192.0.2.127', ['192.0.2.0/25'], true],
	['192.0.2.128', ['192.0.2.0/25'], false],
	['2001:db8:7fff::1', ['2001:db8::/33'], true],
	['2001:db8:8000::1', ['2001:db8::/33'], false],
	['192.0.2.1', ['::ffff:192.0.2.1'], false],
	['2001:db8::1', ['192.0.2.0/24'], false],
	['router.example.net', ['router.example.net'], true],
	['router.example.net', ['other.example.net'], false],
	['192.0.2.1', ['not-an-address', '192.0.2.0/24'], true],
	['192.0.2.1', ['198.51.100.0/24', '192.0.2.1'], true],
	['192.0.2.1', ['0.0.0.0/0'], true],
	['2001:db8::1', ['::/0'], true],
	['::1', ['0:0:0:0:0:0:0:1'], true],
	['192.0.2.1', ['192.0.2.0/33'], false],
	['2001:db8::1', ['2001:db8::/129'], false],
	['192.0.2.1', ['192.0.2.1/32'], true],
	['192.0.2.2', ['192.0.2.1/32'], false],
	['2001:db8::1', ['2001:db8::1/128'], true],
	['2001:db8::2', ['2001:db8::1/128'], false],
	['192.0.2.1', ['192.0.2.0/024'], true],
	['2001:db8::1', ['2001:db8::/032'], true],
	['192.0.2.1', ['0.0.0.0/00'], true],
	['2001:db8::1', ['::/00'], true],
	// A malformed prefix such as '2x' must neither throw nor hide a later
	// valid entry.
	['10.1.1.1', ['10.0.0.0/2x'], false],
	['10.1.1.1', ['10.0.0.0/8.5'], false],
	['2001:db8::1', ['2001:db8::/3x'], false],
	['10.1.1.1', ['10.0.0.0/2x', '10.0.0.0/08'], true],
];

// Malformed prefixes never match.
foreach (['', '-1', '+1', '1.5', '1e1', ' 24', '24/1', 'unknown', '999999999999999999999'] as $prefix) {
	$ipRangeCases[] = ['192.0.2.1', ['192.0.2.0/' . $prefix], false];
	$ipRangeCases[] = ['2001:db8::1', ['2001:db8::/' . $prefix], false];
}
$ipMembershipChecks = 0;
$ipRangeCheck       = static function (bool $actual, bool $expected, string $label) use (&$ipMembershipChecks) : void {
	if ($actual !== $expected) {
		throw new RuntimeException('IP membership contract failed: ' . $label);
	}
	$ipMembershipChecks++;
};

// The list helper and the single-range primitive must agree on every case
// that is a valid address tested against one entry.
foreach ($ipRangeCases as $index => [$address, $ranges, $expected]) {
	$ipRangeCheck(cacti_ip_in_ranges($address, $ranges), $expected, 'list helper ' . $index);

	if (filter_var($address, FILTER_VALIDATE_IP) !== false && count($ranges) === 1) {
		$ipRangeCheck(cacti_ip_in_cidr($address, $ranges[0]), $expected, 'primitive ' . $index);
	}
}

foreach ([
	['invalid', '192.0.2.0/24'],
	['192.0.2.1', 'invalid'],
	['192.0.2.1', 'invalid/24'],
	['192.0.2.1', '2001:db8::/32'],
	['192.0.2.1', '192.0.2.0/unknown'],
	['192.0.2.1', '192.0.2.0/-1'],
	['192.0.2.1', '192.0.2.0/33'],
	['2001:db8::1', '2001:db8::/129'],
] as $index => [$address, $range]) {
	$ipRangeCheck(cacti_ip_in_cidr($address, $range), false, 'invalid primitive ' . $index);
}

// A list may carry non-string values from loosely typed configuration; they
// are skipped without hiding a later valid block.
$ipRangeCheck(cacti_ip_in_cidr('192.0.2.1', [null, 7, '192.0.2.0/24']), true, 'non-string entries');

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

// The trusted proxy matcher shares the strict CIDR parser: a malformed entry
// must neither throw nor hide a later valid one, and an
// IPv4-mapped entry must never match the bare IPv4 address.
foreach ([
	['192.0.2.1', ['', '192.0.2.0/24'], true],
	['192.0.2.1', ['192.0.2.0/024'], true],
	['192.0.2.1', ['198.51.100.0/24'], false],
	['192.0.2.1', ['10.0.0.0/2x'], false],
	['192.0.2.1', ['192.0.2.0/2x', '192.0.2.0/24'], true],
	['192.0.2.1', ['::ffff:192.0.2.1'], false],
	['2001:db8::1', ['192.0.2.0/24'], false],
	['2001:db8:7fff::1', ['2001:db8::/33'], true],
	['::1', ['0:0:0:0:0:0:0:1'], true],
	['proxy.example.net', ['proxy.example.net'], true],
] as $index => [$address, $trusted, $expected]) {
	$ipRangeCheck(cacti_trusted_proxy_match($address, $trusted), $expected, 'trusted proxy ' . $index);
}

// Use real discovery and database helpers with an isolated in-memory database.
// No hosts are contacted: priming only writes candidate addresses to the table.
$ipDiscoveryChecks = (static function () : int {
	// A regression in offset advancement must fail instead of hanging the suite.
	$previousTimeLimit = (int) ini_get('max_execution_time');
	set_time_limit(10);
	$names = ['database_sessions', 'database_hostname', 'database_port', 'database_default',
		'database_total_queries', 'database_last_error', 'database_log', 'affected_rows',
		'error_logged', 'config', 'debug'];
	$saved = [];

	foreach ($names as $name) {
		if (array_key_exists($name, $GLOBALS)) {
			$saved[$name] = $GLOBALS[$name];
		}
	}

	try {
		$conn = new PDO('sqlite::memory:');
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn->exec('CREATE TABLE automation_networks (id INTEGER PRIMARY KEY, subnet_range TEXT, ignore_ips TEXT)');
		$conn->exec('CREATE TABLE automation_ips (ip_address TEXT PRIMARY KEY, hostname TEXT, network_id INTEGER, pid INTEGER, status INTEGER, thread INTEGER)');
		$GLOBALS['database_hostname']                                 = 'discovery-fixture';
		$GLOBALS['database_port']                                     = 0;
		$GLOBALS['database_default']                                  = 'discovery-fixture';
		$GLOBALS['database_sessions']                                 = ['discovery-fixture:0:discovery-fixture' => $conn];
		$GLOBALS['config']['config_options_array']['log_verbosity']   = POLLER_VERBOSITY_NONE;
		$GLOBALS['config']['config_options_array']['selective_debug'] = '';
		$GLOBALS['debug']                                             = false;

		$cases = [
			['192.0.2.0/29', '', ['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4', '192.0.2.5', '192.0.2.6'], 3],
			['192.0.2.0/29', '192.0.2.1, 192.0.2.3,192.0.2.6', ['192.0.2.2', '192.0.2.4', '192.0.2.5'], 3],
			['192.0.2.0/29', '192.0.2.0/30', ['192.0.2.4', '192.0.2.5', '192.0.2.6'], 3],
			['192.0.2.0/29', '192.0.2.0/29', [], 2],
			['192.0.2.0/30,198.51.100.0/30', '198.51.100.0/24', ['192.0.2.1', '192.0.2.2'], 3],
			['192.0.0.0/22', '', 1022, 4],
			['192.0.0.0/22', '192.0.0.0/22', [], 2],
			// A malformed entry is dropped with a log line instead of aborting
			// the sweep; the valid entries after it still apply.
			['192.0.2.0/29', '192.0.2.0/2x, 192.0.2.0/ 8,192.0.2.1,192.0.2.0/30', ['192.0.2.4', '192.0.2.5', '192.0.2.6'], 3],
			// Empty entries from doubled or trailing commas are skipped silently.
			['192.0.2.0/29', '192.0.2.1,, 192.0.2.3, ,', ['192.0.2.2', '192.0.2.4', '192.0.2.5', '192.0.2.6'], 3],
		];

		foreach ($cases as $index => [$subnets, $ignored, $expected, $queries]) {
			$conn->exec('DELETE FROM automation_networks');
			$conn->exec('DELETE FROM automation_ips');
			$statement = $conn->prepare('INSERT INTO automation_networks (id, subnet_range, ignore_ips) VALUES (?, ?, ?)');
			$statement->execute([7, $subnets, $ignored]);
			$GLOBALS['database_total_queries'] = 0;
			automation_primeIPAddressTable(7);
			$rows   = $conn->query('SELECT ip_address FROM automation_ips ORDER BY ip_address')->fetchAll(PDO::FETCH_COLUMN);
			$actual = is_int($expected) ? count($rows) : $rows;

			if ($actual !== $expected || $GLOBALS['database_total_queries'] !== $queries) {
				throw new RuntimeException('Discovery ignore or batching contract failed: ' . $index);
			}
		}

		return count($cases);
	} finally {
		set_time_limit($previousTimeLimit);

		foreach ($names as $name) {
			if (array_key_exists($name, $saved)) {
				$GLOBALS[$name] = $saved[$name];
			} else {
				unset($GLOBALS[$name]);
			}
		}
	}
})();

print json_encode(['membership' => $ipMembershipChecks, 'discovery' => $ipDiscoveryChecks], JSON_THROW_ON_ERROR) . "\n";
