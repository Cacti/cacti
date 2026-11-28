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
 * algorithmic/correctness fixes on 1.2.31.
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
function runCoreAlgoSubprocess(string $code): array {
	$tmpFile = tempnam(sys_get_temp_dir(), 'cacti_algo_test_');
	file_put_contents($tmpFile, "<?php\nerror_reporting(E_ALL & ~E_DEPRECATED);\n" . $code);

	try {
		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);

		$proc = proc_open(array(PHP_BINARY, $tmpFile), $descriptors, $pipes);
		if (!is_resource($proc)) {
			throw new RuntimeException('Failed to spawn PHP subprocess');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
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
	} finally {
		if (is_file($tmpFile)) {
			unlink($tmpFile);
		}
	}
}

test('set_config_option indexes cache array instead of overwriting it and prime_common_config_settings guards scalar session cache', function () use ($repoRoot) {
	$lib      = var_export($repoRoot . '/lib/functions.php', true);
	$basePath = var_export($repoRoot, true);

	$result = runCoreAlgoSubprocess(<<<PHP
\$config = array('base_path' => $basePath, 'is_web' => true, 'poller_id' => 1, 'config_options_array' => array('existing_cli' => 'keep_cli'));
\$_SESSION = array('sess_config_array' => array('existing_web' => 'keep_web'));

function db_execute_prepared(\$sql, \$params = array(), \$log = true, \$db_conn = false) { return true; }
function db_fetch_assoc_prepared(\$sql, \$params = array(), \$log = true, \$db_conn = false) {
	return array(
		array('name' => 'auth_method', 'value' => '1'),
		array('name' => 'selected_theme', 'value' => 'modern'),
	);
}
function db_fetch_cell(\$sql, \$col_name = '', \$log = true, \$db_conn = false) { return 1; }
function db_fetch_assoc(\$sql, \$log = true, \$db_conn = false) { return array(); }
function __() { \$args = func_get_args(); return \$args[0]; }

require_once $lib;

set_config_option('new_web_key', 'web_val');
\$webAfter = \$_SESSION['sess_config_array'];

\$config['is_web'] = false;
set_config_option('new_cli_key', 'cli_val');
\$cliAfter = \$config['config_options_array'];

/* Simulate a pre-existing corrupted scalar session cache and ensure prime_common_config_settings does not throw TypeError */
\$_SESSION['sess_config_array'] = 'corrupted_scalar';
\$config['is_web'] = true;
prime_common_config_settings();

print json_encode(array(
	'web_after' => \$webAfter,
	'cli_after' => \$cliAfter,
	'cli_primed' => \$config['config_options_array'],
));
PHP);

	expect($result['web_after'])->toBe(array(
		'existing_web' => 'keep_web',
		'new_web_key'  => 'web_val',
	));
	expect($result['cli_after'])->toBe(array(
		'existing_cli' => 'keep_cli',
		'new_cli_key'  => 'cli_val',
	));
	expect($result['cli_primed']['auth_method'])->toBe('1');
	expect($result['cli_primed']['selected_theme'])->toBe('modern');
});

test('padleft pads strings without infinite looping', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

print json_encode(array(
	'zero_pad_2'  => padleft('0', 5, 2),
	'zero_pad_4'  => padleft('0', 7, 4),
	'already_fit' => padleft('0', 123, 2),
	'empty_pad'   => padleft('', 5, 3),
	'zero_value'  => padleft('0', 0, 2),
));
PHP);

	expect($result)->toBe(array(
		'zero_pad_2'  => '05',
		'zero_pad_4'  => '0007',
		'already_fit' => '123',
		'empty_pad'   => '5',
		'zero_value'  => '00',
	));
});

test('cacti_dns_decode_name handles uncompressed, RFC 1035 compressed, cyclic, and truncated packets safely', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

/* Uncompressed: host.example.com */
\$uncompressed = "\\x04host\\x07example\\x03com\\x00";

/* Compressed: offset 0 has "example.com", offset 13 has "host" + pointer to offset 0 (0xC0 0x00) */
\$compressed = "\\x07example\\x03com\\x00" . "\\x04host\\xC0\\x00";

/* Self-referencing cycle: offset 0 points to offset 0 */
\$selfCycle = "\\xC0\\x00";

/* Mutual 2-node cycle: offset 0 -> 2 -> 0 */
\$mutualCycle = "\\xC0\\x02\\xC0\\x00";

/* Truncated label claiming length 10 with only 3 bytes following */
\$truncated = "\\x0Aabc";

/* Truncated compression pointer missing second byte */
\$truncatedPtr = "\\x04host\\xC0";

/* Reserved label type bits (0x40) */
\$reservedBits = "\\x41a\\x00";

/* Deep chain exceeding 16 compression pointer hops */
\$deepChain = '';
for (\$i = 0; \$i < 18; \$i++) {
	\$nextOffset = (\$i + 1) * 2;
	\$deepChain .= chr(0xC0) . chr(\$nextOffset);
}
\$deepChain .= "\\x01a\\x00";

print json_encode(array(
	'uncompressed'  => cacti_dns_decode_name(\$uncompressed, 0),
	'compressed'    => cacti_dns_decode_name(\$compressed, 13),
	'self_cycle'    => cacti_dns_decode_name(\$selfCycle, 0),
	'mutual_cycle'  => cacti_dns_decode_name(\$mutualCycle, 0),
	'truncated'     => cacti_dns_decode_name(\$truncated, 0),
	'truncated_ptr' => cacti_dns_decode_name(\$truncatedPtr, 0),
	'reserved_bits' => cacti_dns_decode_name(\$reservedBits, 0),
	'deep_chain'    => cacti_dns_decode_name(\$deepChain, 0),
));
PHP);

	expect($result['uncompressed'])->toBe('host.example.com');
	expect($result['compressed'])->toBe('host.example.com');
	expect($result['self_cycle'])->toBeFalse();
	expect($result['mutual_cycle'])->toBeFalse();
	expect($result['truncated'])->toBeFalse();
	expect($result['truncated_ptr'])->toBeFalse();
	expect($result['reserved_bits'])->toBeFalse();
	expect($result['deep_chain'])->toBeFalse();
});

test('get_data_source_path keys static cache by expand_paths and bounds cache growth', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
\$config = array('rra_path' => '/var/lib/cacti/rra');
\$dbCalls = 0;

function db_fetch_row_prepared(\$sql, \$params = array()) {
	global \$dbCalls;
	\$dbCalls++;
	\$id = \$params[0];
	return array(
		'name'             => 'ds_' . \$id,
		'data_source_path' => '<path_rra>/host_' . \$id . '.rrd',
	);
}

require_once $lib;

\$unexpandedFirst = get_data_source_path(42, false);
\$expandedSecond  = get_data_source_path(42, true);
\$expandedCached  = get_data_source_path(42, true);
\$expandedFirst   = get_data_source_path(99, true);
\$unexpandedTwo   = get_data_source_path(99, false);

print json_encode(array(
	'unexpanded_first' => \$unexpandedFirst,
	'expanded_second'  => \$expandedSecond,
	'expanded_cached'  => \$expandedCached,
	'expanded_first'   => \$expandedFirst,
	'unexpanded_two'   => \$unexpandedTwo,
	'db_calls'         => \$dbCalls,
));
PHP);

	expect($result['unexpanded_first'])->toBe('<path_rra>/host_42.rrd');
	expect($result['expanded_second'])->toBe('/var/lib/cacti/rra/host_42.rrd');
	expect($result['expanded_cached'])->toBe('/var/lib/cacti/rra/host_42.rrd');
	expect($result['expanded_first'])->toBe('/var/lib/cacti/rra/host_99.rrd');
	expect($result['unexpanded_two'])->toBe('<path_rra>/host_99.rrd');
	expect($result['db_calls'])->toBe(4);

	$functionsSource = file_get_contents($repoRoot . '/lib/functions.php');
	expect($functionsSource)->toContain('cacti_sizeof($data_source_path_cache) >= 4096');
});

test('user_setting_exists keys cache by user_id and invalidates on set_user_setting and clear_user_setting', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
\$settings_user = array();
\$_SESSION = array('sess_user_id' => 1);
\$store = array(
	'1:selected_theme' => 'dark',
);

function db_table_exists(\$table) { return true; }
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	global \$store;
	\$key = \$params[1] . ':' . \$params[0];
	return isset(\$store[\$key]) ? 1 : 0;
}
function db_execute_prepared(\$sql, \$params = array()) {
	global \$store;
	if (stripos(\$sql, 'INSERT') !== false) {
		\$store[\$params[0] . ':' . \$params[1]] = \$params[2];
	} elseif (stripos(\$sql, 'DELETE') !== false) {
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

print json_encode(array(
	'u1_initial'     => \$u1Initial,
	'u2_initial'     => \$u2Initial,
	'u2_after_set'   => \$u2AfterSet,
	'u1_after_clear' => \$u1AfterClear,
));
PHP);

	expect($result['u1_initial'])->toBeTrue();
	expect($result['u2_initial'])->toBeFalse();
	expect($result['u2_after_set'])->toBeTrue();
	expect($result['u1_after_clear'])->toBeFalse();
});

test('recursive_chown traverses nested directories past dot entries and first subdirectory', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

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

print json_encode(array(
	'ok'      => \$ok,
	'missing' => \$missing,
));
PHP);

	expect($result['ok'])->toBeTrue();
	expect($result['missing'])->toBeFalse();
});

test('tail_file returns accurate paginated slices in a single filter pass', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
session_save_path(sys_get_temp_dir());
@session_start();
\$_SESSION = array();

function api_plugin_is_enabled(\$plugin) { return false; }
function set_request_var(\$var, \$val) { \$GLOBALS['last_page_var'] = \$val; }
function __() { \$args = func_get_args(); return \$args[0]; }

require_once $lib;

\$logFile = tempnam(sys_get_temp_dir(), 'cacti_tail_');
\$lines   = array();
for (\$i = 1; \$i <= 10; \$i++) {
	\$lines[] = sprintf("2026-01-01 00:00:%02d - SYSTEM LINE %d\\n", \$i, \$i);
}
file_put_contents(\$logFile, implode('', \$lines));

\$p1 = 1; \$total1 = 0;
\$page1 = tail_file(\$logFile, 3, -1, '', \$p1, \$total1);

\$p2 = 2; \$total2 = 0;
\$page2 = tail_file(\$logFile, 3, -1, '', \$p2, \$total2);

\$p4 = 4; \$total4 = 0;
\$page4 = tail_file(\$logFile, 3, -1, '', \$p4, \$total4);

\$p9 = 9; \$total9 = 0;
\$page9 = tail_file(\$logFile, 3, -1, '', \$p9, \$total9);

\$pf = 1; \$totalf = 0;
\$filtered = tail_file(\$logFile, 2, -1, 'LINE [2468]', \$pf, \$totalf);

unlink(\$logFile);

print json_encode(array(
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
));
PHP);

	expect($result['total1'])->toBe(10);
	expect($result['page1_keys'])->toBe(array(8, 9, 10));
	expect($result['page1_vals'][0])->toContain('LINE 8');
	expect($result['page1_vals'][2])->toContain('LINE 10');
	expect($result['page2_keys'])->toBe(array(5, 6, 7));
	expect($result['page2_vals'][0])->toContain('LINE 5');
	expect($result['page4_keys'])->toBe(array(1));
	expect($result['page4_vals'][0])->toContain('LINE 1');
	expect($result['page9_reset'])->toBe(1);
	expect($result['page9_keys'])->toBe(array(8, 9, 10));
	expect($result['filtered_tot'])->toBe(4);
	expect($result['filtered_keys'])->toBe(array(3, 4));
	expect($result['filtered_vals'][0])->toContain('LINE 6');
	expect($result['filtered_vals'][1])->toContain('LINE 8');

	$functionsSource = file_get_contents($repoRoot . '/lib/functions.php');
	$tailStart       = strpos($functionsSource, 'function tail_file(');
	$tailEnd         = strpos($functionsSource, 'function determine_display_log_entry(', $tailStart);
	$tailBody        = substr($functionsSource, $tailStart, $tailEnd - $tailStart);
	expect($tailBody)->not->toContain('rewind($fp)');
});

test('calculate_percentiles accepts 1st percentile and handles empty data safely', function () use ($repoRoot) {
	$lib = var_export($repoRoot . '/lib/functions.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
require_once $lib;

print json_encode(array(
	'empty'     => calculate_percentiles(array(), 95),
	'p1'        => calculate_percentiles(array(0, 100), 1),
	'p50'       => calculate_percentiles(array(10, 20, 30, 40, 50), 50),
	'p100'      => calculate_percentiles(array(10, 20, 30), 100),
	'p_frac'    => calculate_percentiles(array(0, 100), 0.25),
	'invalid_0' => calculate_percentiles(array(1, 2, 3), 0),
	'invalid_h' => calculate_percentiles(array(1, 2, 3), 101),
	'whisker'   => calculate_percentiles(array(10, 20, 30, 40, 50), 95, true),
));
PHP);

	expect($result['empty'])->toBeFalse();
	expect($result['p1'])->toBe(1);
	expect($result['p50'])->toBe(30);
	expect($result['p100'])->toBe(30);
	expect($result['p_frac'])->toBe(25);
	expect($result['invalid_0'])->toBeFalse();
	expect($result['invalid_h'])->toBeFalse();
	expect($result['whisker']['50th'])->toBe(30);
});

test('lib/utility.php poller cache rebuild loops avoid O(N^2) array_merge, batch flush, and bound static cache', function () use ($repoRoot) {
	$utilitySource = file_get_contents($repoRoot . '/lib/utility.php');

	expect($utilitySource)->not->toContain('array_merge($poller_items,');
	expect($utilitySource)->toContain('cacti_sizeof($host_field_data) >= 1000');

	$libFunc  = var_export($repoRoot . '/lib/functions.php', true);
	$libUtil  = var_export($repoRoot . '/lib/utility.php', true);
	$basePath = var_export($repoRoot, true);

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
\$_SESSION = array();
\$messages  = array();
\$config    = array(
	'base_path'          => $basePath,
	'library_path'       => sys_get_temp_dir(),
	'is_web'             => false,
	'poller_id'          => 1,
	'cacti_session_name' => 'CactiTest',
	'cookie_options'     => array(),
);
/* Stub include_once targets expected by update_poller_cache() */
file_put_contents(sys_get_temp_dir() . '/data_query.php', "<?php\n");
file_put_contents(sys_get_temp_dir() . '/api_poller.php', "<?php\n");

\$flushes = array();

function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'FROM data_local') !== false) {
		\$rows = array();
		for (\$i = 1; \$i <= 605; \$i++) {
			\$rows[] = array('id' => \$i, 'host_id' => 1, 'poller_id' => 1);
		}
		for (\$i = 606; \$i <= 610; \$i++) {
			\$rows[] = array('id' => \$i, 'host_id' => 2, 'poller_id' => 2);
		}
		return \$rows;
	}
	return array();
}
function db_fetch_row_prepared(\$sql, \$params = array()) { return array(); }
function db_fetch_cell_prepared(\$sql, \$params = array()) { return 1; }
function db_fetch_cell(\$sql, \$col_name = '', \$log = true, \$db_conn = false) { return 1; }
function db_fetch_assoc(\$sql, \$log = true, \$db_conn = false) { return array(); }
function db_execute_prepared(\$sql, \$params = array()) {
	global \$flushes;
	if (strpos(\$sql, 'UPDATE poller_item') !== false) {
		\$flushes[] = array('poller_id' => \$params[0], 'sql' => \$sql);
	}
	return true;
}
function db_execute(\$sql) { return true; }
function __() { \$args = func_get_args(); return \$args[0]; }

require_once $libFunc;
require_once $libUtil;

push_out_data_input_method(1);

@unlink(sys_get_temp_dir() . '/data_query.php');
@unlink(sys_get_temp_dir() . '/api_poller.php');

\$counts = array();
foreach (\$flushes as \$f) {
	if (preg_match('/IN \\(([^)]+)\\)/', \$f['sql'], \$m)) {
		\$ids = array_filter(array_map('trim', explode(',', \$m[1])));
		\$counts[] = array('poller_id' => \$f['poller_id'], 'count' => count(\$ids));
	}
}

print json_encode(array('batches' => \$counts));
PHP);

	/* 605 rows on poller 1 -> batches of 500 + 105; then 5 rows on poller 2 */
	expect($result['batches'])->toBe(array(
		array('poller_id' => 1, 'count' => 500),
		array('poller_id' => 1, 'count' => 105),
		array('poller_id' => 2, 'count' => 5),
	));
});

test('lib/utility.php fixes MySQL memory check, uksort extension sorting, and case-insensitive byte suffix parsing', function () use ($repoRoot) {
	$utilitySource = file_get_contents($repoRoot . '/lib/utility.php');

	/* ALGO2-33(1): upper-bound comparison uses <= in matching byte units */
	expect($utilitySource)->toContain('$passed = ($variables[$name] <= $recommendation) && $recommendation > 0;');
	expect($utilitySource)->not->toContain('$variables[$name] >= ($recommendation/1024/1024)');

	$libUtil = var_export($repoRoot . '/lib/utility.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
require_once $libUtil;

\$exts = array(
	'zlib'      => array('cli' => false, 'web' => false),
	'PDO'       => array('cli' => false, 'web' => false),
	'ctype'     => array('cli' => false, 'web' => false),
	'mbstring'  => array('cli' => false, 'web' => false),
	'date'      => array('cli' => false, 'web' => false),
);
uksort(\$exts, 'utility_php_sort_extensions');

\$outM = '';
\$bytesM = utility_get_formatted_bytes('512m', 'M', \$outM, 'B');

\$outG = '';
\$bytesG = utility_get_formatted_bytes('2g', 'M', \$outG, 'B');

\$outK = '';
\$bytesK = utility_get_formatted_bytes('64k', 'K', \$outK, 'B');

\$outNeg = '';
\$bytesNeg = utility_get_formatted_bytes('-1', 'M', \$outNeg, 'B');

print json_encode(array(
	'sorted_keys' => array_keys(\$exts),
	'bytes_m'     => \$bytesM,
	'out_m'       => \$outM,
	'bytes_g'     => \$bytesG,
	'out_g'       => \$outG,
	'bytes_k'     => \$bytesK,
	'out_k'       => \$outK,
	'bytes_neg'   => \$bytesNeg,
	'out_neg'     => \$outNeg,
));
PHP);

	expect($result['sorted_keys'])->toBe(array('ctype', 'date', 'mbstring', 'PDO', 'zlib'));
	expect($result['bytes_m'])->toBe(512 * 1024 * 1024);
	expect($result['out_m'])->toBe('512M');
	expect($result['bytes_g'])->toBe(2 * 1024 * 1024 * 1024);
	expect($result['out_g'])->toBe('2048M');
	expect($result['bytes_k'])->toBe(64 * 1024);
	expect($result['out_k'])->toBe('64K');
	expect($result['bytes_neg'])->toBe('-1');
	expect($result['out_neg'])->toBe('-1M');
});

test('resolve_dependencies resolves transitive CDEF inheritance at depth >= 2 and guards cycles', function () use ($repoRoot) {
	$libFunc = var_export($repoRoot . '/lib/functions.php', true);
	$libExp  = var_export($repoRoot . '/lib/export.php', true);

	$result = runCoreAlgoSubprocess(<<<PHP
\$cdefEdges = array(
	/* Chain of depth 3: 1 -> 2 -> 3 -> 4 */
	1 => array(2),
	2 => array(3),
	3 => array(4),
	/* Cycle: 10 -> 11 -> 10 */
	10 => array(11),
	11 => array(10),
);
\$seenSql = array();

function db_qstr(\$s) { return "'" . addslashes((string) \$s) . "'"; }
function array_to_sql_or(\$array, \$sql_column) {
	if (!cacti_sizeof(\$array)) {
		return '';
	}
	\$vals = array();
	foreach (\$array as \$v) {
		\$vals[] = db_qstr(\$v);
	}
	return \$sql_column . ' IN (' . implode(', ', \$vals) . ')';
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT cdef_id') !== false) {
		return array(array('cdef_id' => \$params[0] === 100 ? 1 : 10));
	}
	return array();
}
function db_fetch_assoc(\$sql) {
	global \$cdefEdges, \$seenSql;
	\$seenSql[] = \$sql;
	\$out = array();
	if (preg_match_all("/cdef_id\\s*=\\s*'([^']+)'/", \$sql, \$m)) {
		\$ids = \$m[1];
	} elseif (preg_match('/cdef_id\\s+IN\\s*\\(([^)]+)\\)/i', \$sql, \$m)) {
		\$ids = array_map(function (\$v) { return trim(\$v, " '\""); }, explode(',', \$m[1]));
	} else {
		\$ids = array();
	}
	foreach (\$ids as \$id) {
		if (isset(\$cdefEdges[(int) \$id])) {
			foreach (\$cdefEdges[(int) \$id] as \$target) {
				\$out[] = array('cdef_id' => \$target);
			}
		}
	}
	return \$out;
}
function db_fetch_cell_prepared(\$sql, \$params = array()) { return false; }
function api_plugin_hook_function(\$hook, \$data = null) { return \$data; }

require_once $libFunc;
require_once $libExp;

\$chainDeps = resolve_dependencies('graph_template', 100, array());
\$cycleDeps = resolve_dependencies('graph_template', 200, array());

print json_encode(array(
	'chain_cdefs' => array_keys(\$chainDeps['cdef']),
	'cycle_cdefs' => array_keys(\$cycleDeps['cdef']),
	'had_array'   => count(array_filter(\$seenSql, function (\$s) { return strpos(\$s, 'Array') !== false; })),
));
PHP);

	/* Leaves must appear before parents for import order */
	expect($result['chain_cdefs'])->toBe(array(4, 3, 2, 1));
	expect($result['cycle_cdefs'])->toBe(array(11, 10));
	expect($result['had_array'])->toBe(0);
});

test('lib/import.php guards false xml_to_* return, fixes data_source_profiles table/cache key, and preserves _cdef_id', function () use ($repoRoot) {
	$importSource = file_get_contents($repoRoot . '/lib/import.php');

	/* Table typo fixed and cache key aligned */
	expect($importSource)->not->toContain('FROM data_source_profile' . "\n");
	expect($importSource)->toContain("FROM data_source_profiles\n\t\t\tWHERE hash = ?");
	expect($importSource)->toContain("\$hash_cache['data_source_profile'][\$hash] = \$dsp_id;");
	expect($importSource)->toContain("'data_source_profile' => array(\n\t\t\t'table' => 'data_source_profiles',");

	$libFunc = var_export($repoRoot . '/lib/functions.php', true);
	$libXml  = var_export($repoRoot . '/lib/xml.php', true);
	$libImp  = var_export($repoRoot . '/lib/import.php', true);
	$libDir  = var_export($repoRoot . '/lib', true);

	$result = runCoreAlgoSubprocess(<<<PHP
define('CACTI_VERSION', '1.2.31');
define('POLLER_VERBOSITY_NONE', 1);
define('POLLER_VERBOSITY_LOW', 2);
define('POLLER_VERBOSITY_MEDIUM', 3);
define('POLLER_VERBOSITY_HIGH', 4);
define('POLLER_VERBOSITY_DEBUG', 5);
define('POLLER_VERBOSITY_DEVDBG', 6);

\$config = array(
	'library_path' => $libDir,
	'is_web'       => false,
	'config_options_array' => array(
		'log_verbosity'   => POLLER_VERBOSITY_NONE,
		'log_destination' => 0,
	),
);

\$fields_cdef_edit = array('name' => array());
\$fields_cdef_item_edit = array('sequence' => array(), 'type' => array(), 'value' => array());
\$hash_type_codes = array(
	'cdef'                => '05',
	'cdef_item'           => '14',
	'data_source_profile' => '20',
);
\$cacti_version_codes = array('1.2.31' => '0102');
\$preview_only = false;
\$import_debug_info = array();
\$import_messages = array();
\$hash_version_codes = array('0102' => '1.2.31');
\$savedRows = array();
\$queries = array();

function db_fetch_assoc(\$sql) { return array(); }
function db_fetch_row_prepared(\$sql, \$params = array()) { return array(); }
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	global \$queries;
	\$queries[] = \$sql;
	/* Parent CDEF hash does not exist yet (new), inherited CDEF hash exists with id 77 */
	if (\$params[0] === 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb') {
		return 77;
	}
	if (strpos(\$sql, 'FROM data_source_profiles') !== false) {
		return 'Default 5 Min Profile';
	}
	return false;
}
function sql_save(\$save, \$table) {
	global \$savedRows;
	\$savedRows[] = array('table' => \$table, 'save' => \$save);
	return (\$table === 'cdef') ? 55 : 66;
}
function html_escape(\$s) { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function __() { \$args = func_get_args(); return \$args[0]; }

require_once $libFunc;
require_once $libXml;
require_once $libImp;

/* 1. Test xml_to_cdef does not clobber _cdef_id when decoding an inherited CDEF item */
\$cdefXml = array(
	'name'  => 'Composite CDEF',
	'items' => array(
		'hash_140102cccccccccccccccccccccccccccccccc' => array(
			'sequence' => '1',
			'type'     => '5',
			'value'    => 'hash_050102bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
		),
	),
);
\$cache = array();
xml_to_cdef('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', \$cdefXml, \$cache);
\$cdefDebug = \$import_debug_info;

/* 2. Test hash_to_friendly_name queries data_source_profiles */
\$friendly = hash_to_friendly_name('hash_200102dddddddddddddddddddddddddddddddd', false);

/* 3. Test import_xml_data does not throw PHP 8 TypeError when an xml_to_* handler returns false */
\$xmlBadCdef = '<cacti>'
	. '<hash_050102eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee>'
	. '<name>Bad Item Hash CDEF</name>'
	. '<items><not_a_valid_hash><sequence>1</sequence><type>1</type><value>1</value></not_a_valid_hash></items>'
	. '</hash_050102eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee>'
	. '</cacti>';

\$importRes = import_xml_data(\$xmlBadCdef, false, 1, false);

print json_encode(array(
	'cdef_type'       => \$cdefDebug['type'],
	'cdef_item_value' => \$savedRows[1]['save']['value'],
	'friendly'        => \$friendly,
	'import_is_array' => is_array(\$importRes),
));
PHP);

	expect($result['cdef_type'])->toBe('new');
	expect($result['cdef_item_value'])->toBe(77);
	expect($result['friendly'])->toBe('Default 5 Min Profile');
	expect($result['import_is_array'])->toBeTrue();
});
