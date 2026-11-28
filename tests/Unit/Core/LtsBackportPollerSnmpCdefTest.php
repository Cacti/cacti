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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

/*
 * Regression tests for 1.2.31 LTS backports covering cmd.php down-host SNMP
 * session guards, lib/snmp.php agent formatting / empty-OID walk guards /
 * session failure logging, lib/cdef.php cycle & N+1 guards, and
 * poller_recovery.php bounded batch flushing & acknowledged-row deletion.
 */

$repoRoot = dirname(__DIR__, 3);

if (!defined('POLLER_VERBOSITY_HIGH')) {
	define('POLLER_VERBOSITY_HIGH', 4);
}

if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 0);
}

/** @var array<int, string> $ltsBackportLogs */
$GLOBALS['ltsBackportLogs'] = [];
/** @var array<int, array{sql: string, params: array<int, mixed>}> $ltsBackportQueries */
$GLOBALS['ltsBackportQueries'] = [];
/** @var array<string, mixed> $ltsBackportConfig */
$GLOBALS['ltsBackportConfig'] = [
	'snmp_retries'     => 3,
	'max_get_size'     => 10,
	'path_snmpbulkwalk' => '',
];
/** @var array<int, array<int, array{id: int, type: string, value: string}>> $ltsCdefItemsByCdef */
$GLOBALS['ltsCdefItemsByCdef'] = [];
/** @var array<int, array{type: string, value: string}> $ltsCdefItemsById */
$GLOBALS['ltsCdefItemsById'] = [];
/** @var array<int, string> $ltsCdefNames */
$GLOBALS['ltsCdefNames'] = [];
/** @var array<int, string> $cdef_functions */
$GLOBALS['cdef_functions'] = [
	1 => 'SIN',
	2 => 'COS',
	4 => 'UN',
];
/** @var array<int, string> $cdef_operators */
$GLOBALS['cdef_operators'] = [
	1 => '+',
	2 => '-',
	3 => '*',
	4 => '/',
];

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $array) : int|false {
		if (is_array($array)) {
			return count($array);
		}

		return false;
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log(string $string, bool $output = false, string $environ = 'CMDPHP', int $level = 0) : void {
		$GLOBALS['ltsBackportLogs'][] = $string;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option(string $config_name) : mixed {
		return $GLOBALS['ltsBackportConfig'][$config_name] ?? '';
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared(string $sql, array $params = []) : array|false {
		$GLOBALS['ltsBackportQueries'][] = ['sql' => $sql, 'params' => $params];
		$id                              = (int) ($params[0] ?? 0);

		if (str_contains($sql, 'FROM cdef_items')) {
			if (!array_key_exists($id, $GLOBALS['ltsCdefItemsByCdef'])) {
				return [];
			}

			return $GLOBALS['ltsCdefItemsByCdef'][$id];
		}

		if (str_contains($sql, 'FROM cdef WHERE id = ?')) {
			return isset($GLOBALS['ltsCdefNames'][$id]) ? [['id' => $id]] : [];
		}

		return [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared(string $sql, array $params = []) : array|false {
		$GLOBALS['ltsBackportQueries'][] = ['sql' => $sql, 'params' => $params];
		$id                              = (int) ($params[0] ?? 0);

		return $GLOBALS['ltsCdefItemsById'][$id] ?? false;
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
		$GLOBALS['ltsBackportQueries'][] = ['sql' => $sql, 'params' => $params];
		$id                              = (int) ($params[0] ?? 0);

		return $GLOBALS['ltsCdefNames'][$id] ?? false;
	}
}

global $config;
$config['php_snmp_support'] = false;
$config['include_path']     = $repoRoot . '/include';
$config['cacti_server_os']  = 'unix';

if (!function_exists('snmp_format_agent')) {
	require_once $repoRoot . '/lib/snmp.php';
}

if (!function_exists('get_cdef')) {
	require_once $repoRoot . '/lib/cdef.php';
}

function lts_extract_function_body(string $source, string $functionName) : string|false {
	$needle = 'function ' . $functionName . '(';
	$start  = strpos($source, $needle);

	if ($start === false) {
		return false;
	}

	$end = strpos($source, "\n}", $start + strlen($needle));

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start + 2);
}

beforeEach(function () : void {
	$GLOBALS['ltsBackportLogs']    = [];
	$GLOBALS['ltsBackportQueries'] = [];
	$GLOBALS['ltsCdefItemsByCdef'] = [];
	$GLOBALS['ltsCdefItemsById']   = [];
	$GLOBALS['ltsCdefNames']       = [];
});

test('snmp_format_agent formats IPv4, hostname, IPv6, and transport targets without bracketing IPv4/hostnames', function (string $host, mixed $port, string $expected) : void {
	expect(snmp_format_agent($host, $port))->toBe($expected);
})->with([
	'IPv4 default port'              => ['192.0.2.1', 161, '192.0.2.1'],
	'IPv4 non-default port'          => ['192.0.2.1', 1161, '192.0.2.1:1161'],
	'IPv4 empty port'                => ['192.0.2.1', '', '192.0.2.1'],
	'hostname default port'          => ['router.example.com', '161', 'router.example.com'],
	'hostname non-default port'      => ['router.example.com', '1161', 'router.example.com:1161'],
	'bare IPv6 default port'         => ['2001:db8::1', 161, '[2001:db8::1]:161'],
	'bare IPv6 non-default port'     => ['2001:db8::1', 1161, '[2001:db8::1]:1161'],
	'bracketed IPv6 without port'    => ['[2001:db8::1]', 1161, '[2001:db8::1]:1161'],
	'bracketed IPv6 with port'       => ['[2001:db8::1]:2161', 1161, '[2001:db8::1]:2161'],
	'explicit udp6 transport'        => ['udp6:[2001:db8::1]:161', 1161, 'udp6:[2001:db8::1]:161'],
	'explicit tcp transport'         => ['tcp:192.0.2.1:1161', 161, 'tcp:192.0.2.1:1161'],
	'explicit unix transport'        => ['unix:/var/run/snmpd.sock', 161, 'unix:/var/run/snmpd.sock'],
]);

test('cacti_snmp_walk skips empty or whitespace OIDs and logs a warning', function (string $emptyOid) : void {
	$result = cacti_snmp_walk('192.0.2.1', 'public', $emptyOid, '2');

	expect($result)->toBe([])
		->and($GLOBALS['ltsBackportLogs'])->not->toBeEmpty()
		->and($GLOBALS['ltsBackportLogs'][0])->toContain('SNMP walk requested without an OID');
})->with([
	'empty string'      => [''],
	'whitespace string' => ["  \t\n "],
]);

test('cacti_snmp_session helpers guard non-object sessions safely', function () : void {
	expect(cacti_snmp_session_walk(null, '.1.3.6.1.2.1.1'))->toBe([])
		->and(cacti_snmp_session_walk(false, '.1.3.6.1.2.1.1'))->toBe([])
		->and(cacti_snmp_session_get(null, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and(cacti_snmp_session_get(false, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and(cacti_snmp_session_getnext(null, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and(cacti_snmp_session_getnext(false, '.1.3.6.1.2.1.1.3.0'))->toBeFalse();
});

test('cacti_snmp_session_walk skips empty string OID and logs warning', function () : void {
	$fakeSession = new class {
		public array $info = ['hostname' => '192.0.2.10', 'timeout' => 500000];
		public int $bulk_walk_size = 10;
		public int $value_output_format = 1;
	};

	expect(cacti_snmp_session_walk($fakeSession, '   '))->toBe([])
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('SNMP walk requested without an OID');
});

test('cacti_snmp_session_get logs timeout, session error, captured warning, Throwable, and fallback errno', function () : void {
	$timeoutSession = new class {
		public array $info = ['hostname' => '192.0.2.20', 'timeout' => 500000];
		public function get(mixed $oid) : bool {
			return false;
		}
		public function getErrno() : int {
			return \phpsnmp\SNMP::ERRNO_TIMEOUT;
		}
		public function getError() : string {
			return '';
		}
	};

	expect(cacti_snmp_session_get($timeoutSession, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain("Timeout (500 ms)");

	$GLOBALS['ltsBackportLogs'] = [];
	$errorSession               = new class {
		public array $info = ['hostname' => '192.0.2.21', 'timeout' => 500000];
		public function get(mixed $oid) : bool {
			return false;
		}
		public function getErrno() : int {
			return 3;
		}
		public function getError() : string {
			return "Authentication failure\nfor USM user";
		}
	};

	expect(cacti_snmp_session_get($errorSession, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('Authentication failure for USM user');

	$GLOBALS['ltsBackportLogs'] = [];
	$warningSession             = new class {
		public array $info = ['hostname' => '192.0.2.22', 'timeout' => 500000];
		public function get(mixed $oid) : bool {
			trigger_error('Unknown security name', E_USER_WARNING);

			return false;
		}
		public function getErrno() : int {
			return 1;
		}
		public function getError() : string {
			return '';
		}
	};

	expect(cacti_snmp_session_get($warningSession, '.1.3.6.1.2.1.1.3.0'))->toBeFalse()
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('Unknown security name');

	$GLOBALS['ltsBackportLogs'] = [];
	$throwableSession           = new class {
		public array $info = ['hostname' => '192.0.2.23', 'timeout' => 500000];
		public function getnext(mixed $oid) : mixed {
			throw new \ValueError('Invalid OID syntax');
		}
		public function getErrno() : int {
			return 0;
		}
		public function getError() : string {
			return '';
		}
	};

	expect(cacti_snmp_session_getnext($throwableSession, ['.1.3.6.1.2.1.1.1', '.1.3.6.1.2.1.1.2']))->toBeFalse()
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('Invalid OID syntax')
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('.1.3.6.1.2.1.1.1,.1.3.6.1.2.1.1.2');

	$GLOBALS['ltsBackportLogs'] = [];
	$errnoOnlySession           = new class {
		public array $info = ['hostname' => '192.0.2.24', 'timeout' => 500000];
		public int $bulk_walk_size = 10;
		public int $value_output_format = 1;
		public function walk(mixed $oid, bool $suffix, int $maxRep, int $nonRep) : bool {
			return false;
		}
		public function getErrno() : int {
			return 42;
		}
		public function getError() : string {
			return '';
		}
	};

	expect(cacti_snmp_session_walk($errnoOnlySession, '.1.3.6.1.2.1.2.2.1.1'))->toBe([])
		->and($GLOBALS['ltsBackportLogs'][0] ?? '')->toContain('Error Number 42');
});

test('get_cdef resolves nested CDEFs without N+1 per-item queries and supports legacy boolean second arg', function () : void {
	$GLOBALS['ltsCdefNames']       = [1 => 'Turn Bytes into Bits', 2 => 'Double Bits'];
	$GLOBALS['ltsCdefItemsByCdef'] = [
		1 => [
			['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
			['id' => 11, 'type' => '6', 'value' => '8'],
			['id' => 12, 'type' => '2', 'value' => '3'],
		],
		2 => [
			['id' => 20, 'type' => '5', 'value' => '1'],
			['id' => 21, 'type' => '6', 'value' => '2'],
			['id' => 22, 'type' => '2', 'value' => '3'],
		],
	];

	expect(get_cdef(2))->toBe('CURRENT_DATA_SOURCE,8,*,2,*')
		->and(get_cdef(2, true))->toBe('CURRENT_DATA_SOURCE,8,*,2,*');

	$perItemQueries = array_filter(
		$GLOBALS['ltsBackportQueries'],
		static fn (array $q) : bool => str_contains($q['sql'], 'WHERE id = ?') && str_contains($q['sql'], 'cdef_items')
	);

	expect($perItemQueries)->toBe([]);
});

test('get_cdef rejects cyclic, overly deep, and missing CDEF definitions', function () : void {
	/* Direct and mutual cycles: 10 -> 11 -> 10 */
	$GLOBALS['ltsCdefNames']       = [10 => 'Cycle A', 11 => 'Cycle B', 12 => 'Self Cycle'];
	$GLOBALS['ltsCdefItemsByCdef'] = [
		10 => [['id' => 100, 'type' => '5', 'value' => '11']],
		11 => [['id' => 101, 'type' => '5', 'value' => '10']],
		12 => [['id' => 102, 'type' => '5', 'value' => '12']],
	];

	expect(get_cdef(10))->toBeNull()
		->and(get_cdef(12))->toBeNull()
		->and(get_cdef(999))->toBeNull();

	/* Nesting depth > 64 */
	$definitions = [];
	for ($i = 1; $i <= 66; $i++) {
		$definitions[$i] = $i < 66
			? [['id' => $i, 'type' => '5', 'value' => (string) ($i + 1)]]
			: [['id' => $i, 'type' => '6', 'value' => '1']];
	}

	expect(get_cdef(1, $definitions))->toBeNull()
		->and(implode("\n", $GLOBALS['ltsBackportLogs']))->toContain('exceeds the resolver nesting depth');
});

test('get_cdef reuses DAG cache and validates corrupt or unknown CDEF items', function () : void {
	$definitions = [
		1 => [
			['id' => 1, 'type' => '5', 'value' => '2'],
			['id' => 2, 'type' => '5', 'value' => '2'],
			['id' => 3, 'type' => '2', 'value' => '1'],
		],
		2 => [
			['id' => 4, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		],
	];

	expect(get_cdef(1, $definitions))->toBe('CURRENT_DATA_SOURCE,CURRENT_DATA_SOURCE,+')
		->and(get_cdef_item_name(500, ['type' => '1', 'value' => '999']))->toBeNull()
		->and(get_cdef_item_name(501, ['type' => '2', 'value' => '999']))->toBeNull()
		->and(get_cdef_item_name(502, ['type' => '99', 'value' => 'x']))->toBeNull()
		->and(get_cdef_item_name(503))->toBeNull();
});

test('cmd.php and poller_recovery.php backport regression contracts hold', function (string $relPath, ?string $functionName, array $mustContain, array $mustNotContain) use ($repoRoot) : void {
	$src     = (string) file_get_contents($repoRoot . '/' . $relPath);
	$subject = $functionName !== null ? lts_extract_function_body($src, $functionName) : $src;

	expect($subject)->not->toBeFalse();

	foreach ($mustContain as $needle) {
		expect($subject)->toContain($needle);
	}

	foreach ($mustNotContain as $forbidden) {
		expect($subject)->not->toContain($forbidden);
	}
})->with([
	'cmd.php open_snmp_session returns false for down hosts instead of null' => [
		'cmd.php',
		'open_snmp_session',
		[
			'$key = $host_id . \'_\' . $item[\'snmp_version\'] . \'_\' . $item[\'snmp_port\'];',
			'if (isset($downhosts[$key])) {',
			'return false;',
		],
		['!isset($downhosts['],
	],
	'poller_recovery.php recovery_flush_output_batch enforces packet limit and checks execution' => [
		'poller_recovery.php',
		'recovery_flush_output_batch',
		[
			'INSERT IGNORE INTO poller_output_boost',
			'($out_length + $overhead + $tuple_length) > $max_allowed_packet',
			'recovery_execute_boost_insert(',
		],
		[],
	],
	'poller_recovery.php recovery_delete_acknowledged_rows chunks exact primary-key deletes' => [
		'poller_recovery.php',
		'recovery_delete_acknowledged_rows',
		[
			'array_chunk($rows, 500)',
			'(local_data_id = ? AND rrd_name = ? AND time = ?)',
			'DELETE FROM poller_output_boost WHERE ',
		],
		['WHERE time <= ?'],
	],
	'poller_recovery.php bounds SELECT with LIMIT and retains local rows on unacknowledged batch' => [
		'poller_recovery.php',
		null,
		[
			'ORDER BY time ASC, local_data_id ASC, rrd_name ASC',
			'LIMIT \' . (int) $record_limit',
			'[$max_time], true, $local_db_cnn_id',
			'if (!recovery_flush_output_batch($sql_array, $remote_db_cnn_id, $max_allowed_packet))',
			'if (!recovery_delete_acknowledged_rows($rows, $local_db_cnn_id))',
			'if (!$transfer_failed)',
			'exit($transfer_failed ? 1 : 0);',
		],
		[
			'DELETE FROM poller_output_boost' . "\n\t\t\t\t\t\tWHERE time <= ?",
		],
	],
]);
