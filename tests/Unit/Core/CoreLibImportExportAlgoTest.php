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
 * Unit and regression tests for core library, utility, export, and import
 * algorithmic and correctness fixes on 1.2.31.
 *
 * Each runtime test executes the target library file from this checkout in an
 * isolated PHP CLI subprocess so tests always exercise this branch's actual
 * implementations without colliding with global bootstrap definitions.
 */

$repoRoot = realpath(__DIR__ . '/../../..');

/**
 * Run a PHP test snippet in an isolated subprocess and decode its JSON output.
 *
 * @param string $code PHP source code (without opening tag) that prints JSON.
 *
 * @return array<string, mixed>
 */
function runCoreAlgoSubprocess(string $code) : array {
	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$proc = proc_open([PHP_BINARY], $descriptors, $pipes);
	if (!is_resource($proc)) {
		throw new RuntimeException('Failed to spawn PHP subprocess');
	}

	fwrite($pipes[0], "<?php\nerror_reporting(E_ALL & ~E_DEPRECATED);\n" . $code);
	fclose($pipes[0]);

	$stdout   = stream_get_contents($pipes[1]);
	$stderr   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exitCode = proc_close($proc);

	if ($exitCode !== 0) {
		throw new RuntimeException("Subprocess exited with code $exitCode.\nSTDOUT: $stdout\nSTDERR: $stderr");
	}

	$decoded = json_decode(trim($stdout), true);
	if (!is_array($decoded)) {
		throw new RuntimeException("Invalid JSON from subprocess.\nSTDOUT: $stdout\nSTDERR: $stderr");
	}

	return $decoded;
}

describe('lib/functions.php core fixes', function () use ($repoRoot) {
	$lib      = var_export($repoRoot . '/lib/functions.php', true);
	$basePath = var_export($repoRoot, true);

	it('indexes config cache arrays in set_config_option() and guards scalar session caches in prime_common_config_settings()', function () use ($lib, $basePath) {
		$result = runCoreAlgoSubprocess(<<<PHP
\$config   = ['base_path' => $basePath, 'is_web' => true, 'poller_id' => 1, 'config_options_array' => ['existing_cli' => 'keep_cli']];
\$_SESSION = ['sess_config_array' => ['existing_web' => 'keep_web']];

function db_execute_prepared(\$sql, \$params = [], \$log = true, \$db_conn = false) { return true; }
function db_fetch_assoc_prepared(\$sql, \$params = [], \$log = true, \$db_conn = false) {
	return [
		['name' => 'auth_method', 'value' => '1'],
		['name' => 'selected_theme', 'value' => 'modern'],
	];
}
function db_fetch_cell(\$sql, \$col_name = '', \$log = true, \$db_conn = false) { return 1; }
function db_fetch_assoc(\$sql, \$log = true, \$db_conn = false) { return []; }
function __() { return func_get_arg(0); }

require_once $lib;

set_config_option('new_web_key', 'web_val');
\$webAfter = \$_SESSION['sess_config_array'];

\$config['is_web'] = false;
set_config_option('new_cli_key', 'cli_val');
\$cliAfter = \$config['config_options_array'];

/* Simulate a corrupted scalar session cache and verify prime_common_config_settings() does not throw TypeError */
\$_SESSION['sess_config_array'] = 'corrupted_scalar';
\$config['is_web'] = true;
prime_common_config_settings();

print json_encode([
	'web_after'  => \$webAfter,
	'cli_after'  => \$cliAfter,
	'cli_primed' => \$config['config_options_array'],
]);
PHP);

		expect($result['web_after'])->toBe(['existing_web' => 'keep_web', 'new_web_key' => 'web_val'])
			->and($result['cli_after'])->toBe(['existing_cli' => 'keep_cli', 'new_cli_key' => 'cli_val'])
			->and($result['cli_primed']['auth_method'])->toBe('1')
			->and($result['cli_primed']['selected_theme'])->toBe('modern');
	});

	it('pads strings in padleft() without infinite looping', function () use ($lib) {
		$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

print json_encode([
	'zero_pad_2'  => padleft('0', 5, 2),
	'zero_pad_4'  => padleft('0', 7, 4),
	'already_fit' => padleft('0', 123, 2),
	'empty_pad'   => padleft('', 5, 3),
	'zero_value'  => padleft('0', 0, 2),
]);
PHP);

		expect($result)->toBe([
			'zero_pad_2'  => '05',
			'zero_pad_4'  => '0007',
			'already_fit' => '123',
			'empty_pad'   => '5',
			'zero_value'  => '00',
		]);
	});

	it('decodes uncompressed and RFC 1035 compressed DNS names and rejects cyclic or truncated packets', function () use ($lib) {
		$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

\$deepChain = '';
for (\$i = 0; \$i < 18; \$i++) {
	\$deepChain .= chr(0xC0) . chr((\$i + 1) * 2);
}
\$deepChain .= "\\x01a\\x00";

\$cases = [
	'uncompressed'  => ["\\x04host\\x07example\\x03com\\x00", 0],
	'compressed'    => ["\\x07example\\x03com\\x00\\x04host\\xC0\\x00", 13],
	'self_cycle'    => ["\\xC0\\x00", 0],
	'mutual_cycle'  => ["\\xC0\\x02\\xC0\\x00", 0],
	'truncated'     => ["\\x0Aabc", 0],
	'truncated_ptr' => ["\\x04host\\xC0", 0],
	'reserved_bits' => ["\\x41a\\x00", 0],
	'deep_chain'    => [\$deepChain, 0],
];

\$out = [];
foreach (\$cases as \$name => [\$pkt, \$pos]) {
	\$out[\$name] = cacti_dns_decode_name(\$pkt, \$pos);
}

print json_encode(\$out);
PHP);

		expect($result)->toBe([
			'uncompressed'  => 'host.example.com',
			'compressed'    => 'host.example.com',
			'self_cycle'    => false,
			'mutual_cycle'  => false,
			'truncated'     => false,
			'truncated_ptr' => false,
			'reserved_bits' => false,
			'deep_chain'    => false,
		]);
	});

	it('keys get_data_source_path() static cache by expand_paths and bounds cache growth', function () use ($lib, $repoRoot) {
		$result = runCoreAlgoSubprocess(<<<PHP
\$config  = ['rra_path' => '/var/lib/cacti/rra'];
\$dbCalls = 0;

function db_fetch_row_prepared(\$sql, \$params = []) {
	global \$dbCalls;
	\$dbCalls++;
	\$id = \$params[0];
	return [
		'name'             => 'ds_' . \$id,
		'data_source_path' => '<path_rra>/host_' . \$id . '.rrd',
	];
}

require_once $lib;

print json_encode([
	'unexpanded_first' => get_data_source_path(42, false),
	'expanded_second'  => get_data_source_path(42, true),
	'expanded_cached'  => get_data_source_path(42, true),
	'expanded_first'   => get_data_source_path(99, true),
	'unexpanded_two'   => get_data_source_path(99, false),
	'db_calls'         => \$dbCalls,
]);
PHP);

		expect($result)->toBe([
			'unexpanded_first' => '<path_rra>/host_42.rrd',
			'expanded_second'  => '/var/lib/cacti/rra/host_42.rrd',
			'expanded_cached'  => '/var/lib/cacti/rra/host_42.rrd',
			'expanded_first'   => '/var/lib/cacti/rra/host_99.rrd',
			'unexpanded_two'   => '<path_rra>/host_99.rrd',
			'db_calls'         => 4,
		]);

		expect(file_get_contents($repoRoot . '/lib/functions.php'))
			->toContain('cacti_sizeof($data_source_path_cache) >= 4096');
	});

	it('keys user_setting_exists() cache by user_id and invalidates on set and clear', function () use ($lib) {
		$result = runCoreAlgoSubprocess(<<<PHP
\$settings_user = [];
\$_SESSION      = ['sess_user_id' => 1];
\$store         = ['1:selected_theme' => 'dark'];

function db_table_exists(\$table) { return true; }
function db_fetch_cell_prepared(\$sql, \$params = []) {
	global \$store;
	return isset(\$store[\$params[1] . ':' . \$params[0]]) ? 1 : 0;
}
function db_execute_prepared(\$sql, \$params = []) {
	global \$store;
	if (str_contains(strtoupper(\$sql), 'INSERT')) {
		\$store[\$params[0] . ':' . \$params[1]] = \$params[2];
	} elseif (str_contains(strtoupper(\$sql), 'DELETE')) {
		unset(\$store[\$params[1] . ':' . \$params[0]]);
	}
	return true;
}

require_once $lib;

\$u1Initial = user_setting_exists('selected_theme', 1);
\$u2Initial = user_setting_exists('selected_theme', 2);

set_user_setting('selected_theme', 'modern', 2);
\$u2AfterSet = user_setting_exists('selected_theme', 2);

clear_user_setting('selected_theme', 1);
\$u1AfterClear = user_setting_exists('selected_theme', 1);

print json_encode([
	'u1_initial'     => \$u1Initial,
	'u2_initial'     => \$u2Initial,
	'u2_after_set'   => \$u2AfterSet,
	'u1_after_clear' => \$u1AfterClear,
]);
PHP);

		expect($result)->toBe([
			'u1_initial'     => true,
			'u2_initial'     => false,
			'u2_after_set'   => true,
			'u1_after_clear' => false,
		]);
	});

	it('traverses nested directories past dot entries and first subdirectory in recursive_chown()', function () use ($lib) {
		$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

\$base = sys_get_temp_dir() . '/cacti_rchown_' . uniqid('', true);
mkdir(\$base . '/sub_a', 0755, true);
mkdir(\$base . '/sub_b', 0755, true);
file_put_contents(\$base . '/sub_a/file_a.txt', 'a');
file_put_contents(\$base . '/sub_b/file_b.txt', 'b');
file_put_contents(\$base . '/root_file.txt', 'r');

\$uid = function_exists('posix_getuid') ? posix_getuid() : fileowner(\$base);
\$gid = function_exists('posix_getgid') ? posix_getgid() : filegroup(\$base);

\$ok      = recursive_chown(\$base, \$uid, \$gid);
\$missing = recursive_chown(\$base . '/does_not_exist', \$uid, \$gid);

unlink(\$base . '/sub_a/file_a.txt');
unlink(\$base . '/sub_b/file_b.txt');
unlink(\$base . '/root_file.txt');
rmdir(\$base . '/sub_a');
rmdir(\$base . '/sub_b');
rmdir(\$base);

print json_encode(['ok' => \$ok, 'missing' => \$missing]);
PHP);

		expect($result)->toBe(['ok' => true, 'missing' => false]);
	});

	it('returns accurate paginated slices in tail_file() in a single filter pass', function () use ($lib, $repoRoot) {
		$result = runCoreAlgoSubprocess(<<<PHP
session_save_path(sys_get_temp_dir());
@session_start();
\$_SESSION = [];

function api_plugin_is_enabled(\$plugin) { return false; }
function set_request_var(\$var, \$val) { \$GLOBALS['last_page_var'] = \$val; }
function __() { return func_get_arg(0); }

require_once $lib;

\$logFile = tempnam(sys_get_temp_dir(), 'cacti_tail_');
\$lines   = [];
for (\$i = 1; \$i <= 10; \$i++) {
	\$lines[] = sprintf("2026-01-01 00:00:%02d - SYSTEM LINE %d\\n", \$i, \$i);
}
file_put_contents(\$logFile, implode('', \$lines));

\$p1 = 1; \$total1 = 0; \$page1 = tail_file(\$logFile, 3, -1, '', \$p1, \$total1);
\$p2 = 2; \$total2 = 0; \$page2 = tail_file(\$logFile, 3, -1, '', \$p2, \$total2);
\$p4 = 4; \$total4 = 0; \$page4 = tail_file(\$logFile, 3, -1, '', \$p4, \$total4);
\$p9 = 9; \$total9 = 0; \$page9 = tail_file(\$logFile, 3, -1, '', \$p9, \$total9);
\$pf = 1; \$totalf = 0; \$filtered = tail_file(\$logFile, 2, -1, 'LINE [2468]', \$pf, \$totalf);

unlink(\$logFile);

print json_encode([
	'total1'        => \$total1,
	'page1_keys'    => array_keys(\$page1),
	'page1_vals'    => array_map('trim', array_values(\$page1)),
	'page2_keys'    => array_keys(\$page2),
	'page2_vals'    => array_map('trim', array_values(\$page2)),
	'page4_keys'    => array_keys(\$page4),
	'page4_vals'    => array_map('trim', array_values(\$page4)),
	'page9_reset'   => \$p9,
	'page9_keys'    => array_keys(\$page9),
	'filtered_tot'  => \$totalf,
	'filtered_keys' => array_keys(\$filtered),
	'filtered_vals' => array_map('trim', array_values(\$filtered)),
]);
PHP);

		expect($result['total1'])->toBe(10)
			->and($result['page1_keys'])->toBe([8, 9, 10])
			->and($result['page1_vals'][0])->toContain('LINE 8')
			->and($result['page1_vals'][2])->toContain('LINE 10')
			->and($result['page2_keys'])->toBe([5, 6, 7])
			->and($result['page2_vals'][0])->toContain('LINE 5')
			->and($result['page4_keys'])->toBe([1])
			->and($result['page4_vals'][0])->toContain('LINE 1')
			->and($result['page9_reset'])->toBe(1)
			->and($result['page9_keys'])->toBe([8, 9, 10])
			->and($result['filtered_tot'])->toBe(4)
			->and($result['filtered_keys'])->toBe([3, 4])
			->and($result['filtered_vals'][0])->toContain('LINE 6')
			->and($result['filtered_vals'][1])->toContain('LINE 8');

		$functionsSource = file_get_contents($repoRoot . '/lib/functions.php');
		$tailStart       = strpos($functionsSource, 'function tail_file(');
		$tailEnd         = strpos($functionsSource, 'function determine_display_log_entry(', $tailStart);
		expect(substr($functionsSource, $tailStart, $tailEnd - $tailStart))->not->toContain('rewind($fp)');
	});

	it('accepts the 1st percentile and handles empty data safely in calculate_percentiles()', function () use ($lib) {
		$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

print json_encode([
	'empty'     => calculate_percentiles([], 95),
	'p1'        => calculate_percentiles([0, 100], 1),
	'p50'       => calculate_percentiles([10, 20, 30, 40, 50], 50),
	'p100'      => calculate_percentiles([10, 20, 30], 100),
	'p_frac'    => calculate_percentiles([0, 100], 0.25),
	'invalid_0' => calculate_percentiles([1, 2, 3], 0),
	'invalid_h' => calculate_percentiles([1, 2, 3], 101),
	'whisker'   => calculate_percentiles([10, 20, 30, 40, 50], 95, true),
]);
PHP);

		expect($result['empty'])->toBeFalse()
			->and($result['p1'])->toBe(1)
			->and($result['p50'])->toBe(30)
			->and($result['p100'])->toBe(30)
			->and($result['p_frac'])->toBe(25)
			->and($result['invalid_0'])->toBeFalse()
			->and($result['invalid_h'])->toBeFalse()
			->and($result['whisker']['50th'])->toBe(30);
	});
});

describe('lib/utility.php poller cache and recommendation fixes', function () use ($repoRoot) {
	$utilitySource = file_get_contents($repoRoot . '/lib/utility.php');
	$libFunc       = var_export($repoRoot . '/lib/functions.php', true);
	$libUtil       = var_export($repoRoot . '/lib/utility.php', true);
	$basePath      = var_export($repoRoot, true);

	it('avoids O(N^2) array_merge in poller cache rebuild loops, batch-flushes at 500 rows, and bounds static host cache', function () use ($utilitySource, $libFunc, $libUtil, $basePath) {
		expect($utilitySource)->not->toContain('array_merge($poller_items,')
			->and($utilitySource)->toContain('cacti_sizeof($host_field_data) >= 1000');

		$result = runCoreAlgoSubprocess(<<<PHP
define('SQL_NO_CACHE', '');
define('DATA_INPUT_TYPE_SCRIPT', 1);
define('DATA_INPUT_TYPE_SNMP', 2);
define('DATA_INPUT_TYPE_SNMP_QUERY', 3);
define('DATA_INPUT_TYPE_SCRIPT_QUERY', 4);
define('DATA_INPUT_TYPE_PHP_SCRIPT_SERVER', 5);
define('DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER', 6);
define('POLLER_ACTION_SCRIPT', 1);
define('POLLER_ACTION_SCRIPT_PHP', 2);
define('MESSAGE_LEVEL_NONE', 0);
define('MESSAGE_LEVEL_INFO', 1);
define('MESSAGE_LEVEL_WARN', 2);
define('MESSAGE_LEVEL_ERROR', 3);
define('MESSAGE_LEVEL_MIXED', 4);

session_save_path(sys_get_temp_dir());
\$_SESSION = [];
\$messages  = [];
\$config    = [
	'base_path'          => $basePath,
	'library_path'       => sys_get_temp_dir(),
	'is_web'             => false,
	'poller_id'          => 1,
	'cacti_session_name' => 'CactiTest',
	'cookie_options'     => [],
];
file_put_contents(sys_get_temp_dir() . '/data_query.php', "<?php\\n");
file_put_contents(sys_get_temp_dir() . '/api_poller.php', "<?php\\n");

\$flushes = [];

function db_fetch_assoc_prepared(\$sql, \$params = []) {
	if (str_contains(\$sql, 'FROM data_local')) {
		\$rows = [];
		for (\$i = 1; \$i <= 605; \$i++) {
			\$rows[] = ['id' => \$i, 'host_id' => 1, 'poller_id' => 1];
		}
		for (\$i = 606; \$i <= 610; \$i++) {
			\$rows[] = ['id' => \$i, 'host_id' => 2, 'poller_id' => 2];
		}
		return \$rows;
	}
	return [];
}
function db_fetch_row_prepared(\$sql, \$params = []) { return []; }
function db_fetch_cell_prepared(\$sql, \$params = []) { return 1; }
function db_fetch_cell(\$sql, \$col_name = '', \$log = true, \$db_conn = false) { return 1; }
function db_fetch_assoc(\$sql, \$log = true, \$db_conn = false) { return []; }
function db_execute_prepared(\$sql, \$params = []) {
	global \$flushes;
	if (str_contains(\$sql, 'UPDATE poller_item')) {
		\$flushes[] = ['poller_id' => \$params[0], 'sql' => \$sql];
	}
	return true;
}
function db_execute(\$sql) { return true; }
function __() { return func_get_arg(0); }

require_once $libFunc;
require_once $libUtil;

push_out_data_input_method(1);

@unlink(sys_get_temp_dir() . '/data_query.php');
@unlink(sys_get_temp_dir() . '/api_poller.php');

\$counts = [];
foreach (\$flushes as \$f) {
	if (preg_match('/IN \\(([^)]+)\\)/', \$f['sql'], \$m)) {
		\$ids = array_filter(array_map('trim', explode(',', \$m[1])));
		\$counts[] = ['poller_id' => \$f['poller_id'], 'count' => count(\$ids)];
	}
}

print json_encode(['batches' => \$counts]);
PHP);

		/* 605 rows on poller 1 -> batches of 500 + 105; then 5 rows on poller 2 */
		expect($result['batches'])->toBe([
			['poller_id' => 1, 'count' => 500],
			['poller_id' => 1, 'count' => 105],
			['poller_id' => 2, 'count' => 5],
		]);
	});

	it('fixes MySQL memory upper-bound check, uksort extension sorting, and case-insensitive byte suffix parsing', function () use ($utilitySource, $libUtil) {
		expect($utilitySource)->toContain('$passed = ($variables[$name] <= $recommendation) && $recommendation > 0;')
			->and($utilitySource)->not->toContain('$variables[$name] >= ($recommendation/1024/1024)');

		$result = runCoreAlgoSubprocess(<<<PHP
require_once $libUtil;

\$exts = [
	'zlib'     => ['cli' => false, 'web' => false],
	'PDO'      => ['cli' => false, 'web' => false],
	'ctype'    => ['cli' => false, 'web' => false],
	'mbstring' => ['cli' => false, 'web' => false],
	'date'     => ['cli' => false, 'web' => false],
];
uksort(\$exts, 'utility_php_sort_extensions');

\$cases = [
	'm'   => ['512m', 'M'],
	'g'   => ['2g', 'M'],
	'k'   => ['64k', 'K'],
	'neg' => ['-1', 'M'],
];
\$parsed = [];
foreach (\$cases as \$key => [\$in, \$wanted]) {
	\$out = '';
	\$parsed[\$key] = ['bytes' => utility_get_formatted_bytes(\$in, \$wanted, \$out, 'B'), 'out' => \$out];
}

print json_encode([
	'sorted_keys' => array_keys(\$exts),
	'parsed'      => \$parsed,
]);
PHP);

		expect($result['sorted_keys'])->toBe(['ctype', 'date', 'mbstring', 'PDO', 'zlib'])
			->and($result['parsed'])->toBe([
				'm'   => ['bytes' => 512 * 1024 * 1024, 'out' => '512M'],
				'g'   => ['bytes' => 2 * 1024 * 1024 * 1024, 'out' => '2048M'],
				'k'   => ['bytes' => 64 * 1024, 'out' => '64K'],
				'neg' => ['bytes' => '-1', 'out' => '-1M'],
			]);
	});
});

describe('lib/export.php and lib/import.php fixes', function () use ($repoRoot) {
	$libFunc = var_export($repoRoot . '/lib/functions.php', true);
	$libExp  = var_export($repoRoot . '/lib/export.php', true);
	$libXml  = var_export($repoRoot . '/lib/xml.php', true);
	$libImp  = var_export($repoRoot . '/lib/import.php', true);
	$libDir  = var_export($repoRoot . '/lib', true);

	it('resolves transitive CDEF inheritance at depth >= 2 and guards cycles in resolve_dependencies()', function () use ($libFunc, $libExp) {
		$result = runCoreAlgoSubprocess(<<<PHP
\$cdefEdges = [
	1  => [2],
	2  => [3],
	3  => [4],
	10 => [11],
	11 => [10],
];
\$seenSql = [];

function db_qstr(\$s) { return "'" . addslashes((string) \$s) . "'"; }
function array_to_sql_or(\$array, \$sql_column) {
	if (!cacti_sizeof(\$array)) {
		return '';
	}
	return \$sql_column . ' IN (' . implode(', ', array_map('db_qstr', \$array)) . ')';
}
function db_fetch_assoc_prepared(\$sql, \$params = []) {
	return str_contains(\$sql, 'SELECT cdef_id')
		? [['cdef_id' => \$params[0] === 100 ? 1 : 10]]
		: [];
}
function db_fetch_assoc(\$sql) {
	global \$cdefEdges, \$seenSql;
	\$seenSql[] = \$sql;
	\$ids = [];
	if (preg_match_all("/cdef_id\\s*=\\s*'([^']+)'/", \$sql, \$m)) {
		\$ids = \$m[1];
	} elseif (preg_match('/cdef_id\\s+IN\\s*\\(([^)]+)\\)/i', \$sql, \$m)) {
		\$ids = array_map(static fn(string \$v) : string => trim(\$v, " '\\""), explode(',', \$m[1]));
	}
	\$out = [];
	foreach (\$ids as \$id) {
		foreach (\$cdefEdges[(int) \$id] ?? [] as \$target) {
			\$out[] = ['cdef_id' => \$target];
		}
	}
	return \$out;
}
function db_fetch_cell_prepared(\$sql, \$params = []) { return false; }
function api_plugin_hook_function(\$hook, \$data = null) { return \$data; }

require_once $libFunc;
require_once $libExp;

\$chainDeps = resolve_dependencies('graph_template', 100, []);
\$cycleDeps = resolve_dependencies('graph_template', 200, []);

print json_encode([
	'chain_cdefs' => array_keys(\$chainDeps['cdef']),
	'cycle_cdefs' => array_keys(\$cycleDeps['cdef']),
	'had_array'   => count(array_filter(\$seenSql, static fn(string \$s) : bool => str_contains(\$s, 'Array'))),
]);
PHP);

		/* Leaves must appear before parents for import order */
		expect($result['chain_cdefs'])->toBe([4, 3, 2, 1])
			->and($result['cycle_cdefs'])->toBe([11, 10])
			->and($result['had_array'])->toBe(0);
	});

	it('guards false xml_to_* return, fixes data_source_profiles table/cache key, and preserves _cdef_id in lib/import.php', function () use ($repoRoot, $libFunc, $libXml, $libImp, $libDir) {
		$importSource = file_get_contents($repoRoot . '/lib/import.php');

		expect($importSource)->not->toContain("FROM data_source_profile\n")
			->and($importSource)->toContain("FROM data_source_profiles\n\t\t\tWHERE hash = ?")
			->and($importSource)->toContain("\$hash_cache['data_source_profile'][\$hash] = \$dsp_id;")
			->and($importSource)->toContain("'data_source_profile' => array(\n\t\t\t'table' => 'data_source_profiles',");

		$result = runCoreAlgoSubprocess(<<<PHP
define('CACTI_VERSION', '1.2.31');
define('POLLER_VERBOSITY_NONE', 1);
define('POLLER_VERBOSITY_LOW', 2);
define('POLLER_VERBOSITY_MEDIUM', 3);
define('POLLER_VERBOSITY_HIGH', 4);
define('POLLER_VERBOSITY_DEBUG', 5);
define('POLLER_VERBOSITY_DEVDBG', 6);

\$config = [
	'library_path'         => $libDir,
	'is_web'               => false,
	'config_options_array' => [
		'log_verbosity'   => POLLER_VERBOSITY_NONE,
		'log_destination' => 0,
	],
];

\$fields_cdef_edit      = ['name' => []];
\$fields_cdef_item_edit = ['sequence' => [], 'type' => [], 'value' => []];
\$hash_type_codes       = ['cdef' => '05', 'cdef_item' => '14', 'data_source_profile' => '20'];
\$cacti_version_codes   = ['1.2.31' => '0102'];
\$hash_version_codes    = ['0102' => '1.2.31'];
\$preview_only          = false;
\$import_debug_info     = [];
\$import_messages       = [];
\$savedRows             = [];

function db_fetch_assoc(\$sql) { return []; }
function db_fetch_row_prepared(\$sql, \$params = []) { return []; }
function db_fetch_cell_prepared(\$sql, \$params = []) {
	if (\$params[0] === 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb') {
		return 77;
	}
	if (str_contains(\$sql, 'FROM data_source_profiles')) {
		return 'Default 5 Min Profile';
	}
	return false;
}
function sql_save(\$save, \$table) {
	global \$savedRows;
	\$savedRows[] = ['table' => \$table, 'save' => \$save];
	return \$table === 'cdef' ? 55 : 66;
}
function html_escape(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function __() { return func_get_arg(0); }

require_once $libFunc;
require_once $libXml;
require_once $libImp;

\$cdefXml = [
	'name'  => 'Composite CDEF',
	'items' => [
		'hash_140102cccccccccccccccccccccccccccccccc' => [
			'sequence' => '1',
			'type'     => '5',
			'value'    => 'hash_050102bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
		],
	],
];
\$cache = [];
xml_to_cdef('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', \$cdefXml, \$cache);
\$cdefDebug = \$import_debug_info;

\$friendly = hash_to_friendly_name('hash_200102dddddddddddddddddddddddddddddddd', false);

\$xmlBadCdef = '<cacti>'
	. '<hash_050102eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee>'
	. '<name>Bad Item Hash CDEF</name>'
	. '<items><not_a_valid_hash><sequence>1</sequence><type>1</type><value>1</value></not_a_valid_hash></items>'
	. '</hash_050102eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee>'
	. '</cacti>';

\$importRes = import_xml_data(\$xmlBadCdef, false, 1, false);

print json_encode([
	'cdef_type'       => \$cdefDebug['type'],
	'cdef_item_value' => \$savedRows[1]['save']['value'],
	'friendly'        => \$friendly,
	'import_is_array' => is_array(\$importRes),
]);
PHP);

		expect($result)->toBe([
			'cdef_type'       => 'new',
			'cdef_item_value' => 77,
			'friendly'        => 'Default 5 Min Profile',
			'import_is_array' => true,
		]);
	});
});
