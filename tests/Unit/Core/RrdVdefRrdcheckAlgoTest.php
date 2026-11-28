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

/**
 * Executes a self-contained PHP script in an isolated child PHP process and
 * decodes its JSON stdout payload. Using a clean subprocess guarantees tests
 * always exercise the repository's own library files with deterministic stubs.
 *
 * @return array<string, mixed>
 */
function run_isolated_php_json(string $code) : array {
	$process = proc_open([PHP_BINARY], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	], $pipes);

	expect(is_resource($process))->toBeTrue();

	$preamble = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
function cacti_sizeof(mixed $x) : int { return is_array($x) ? count($x) : 0; }
function cacti_count(mixed $x) : int { return is_array($x) ? count($x) : 0; }
function cacti_escapeshellarg(mixed $arg) : string { return escapeshellarg((string) $arg); }
function __() : string { $args = func_get_args(); $fmt = array_shift($args); return $args ? vsprintf($fmt, $args) : (string) $fmt; }
function __esc() : string { return __(...func_get_args()); }

PHP;

	fwrite($pipes[0], $preamble . $code);
	fclose($pipes[0]);

	$stdout = (string) stream_get_contents($pipes[1]);
	$stderr = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit_code = proc_close($process);

	expect($exit_code)->toBe(0, "Child PHP exited with {$exit_code}. STDERR: {$stderr}\nSTDOUT: {$stdout}");

	$decoded = json_decode($stdout, true);
	expect(is_array($decoded))->toBeTrue("Expected JSON object, got: {$stdout}\nSTDERR: {$stderr}");

	return $decoded;
}

$repo_root = dirname(__DIR__, 3);

describe('lib/vdef.php cycle detection, match formatting, and N+1 elimination', function () use ($repo_root) {
	$vdef_file = var_export($repo_root . '/lib/vdef.php', true);

	it('detects direct and mutual cycles without infinite recursion or N+1 row queries', function () use ($vdef_file) {
		$res = run_isolated_php_json(<<<PHP
\$vdef_functions = [1 => 'MAXIMUM', 2 => 'MINIMUM', 3 => 'AVERAGE'];
\$logs           = [];
\$row_queries    = 0;
\$assoc_queries  = 0;

function cacti_log(\$msg, \$stdout = false, \$fac = '') : void { global \$logs; \$logs[] = \$msg; }
function db_fetch_row_prepared(\$sql, \$params = []) : array {
	global \$row_queries;
	\$row_queries++;
	return ['type' => '4', 'value' => 'SHOULD_NOT_BE_CALLED'];
}
function db_fetch_assoc_prepared(\$sql, \$params = []) : array {
	global \$assoc_queries;
	\$assoc_queries++;
	\$fixtures = [
		1 => [
			['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
			['id' => 11, 'type' => '5', 'value' => '1'],
		],
		2 => [
			['id' => 20, 'type' => '4', 'value' => 'A'],
			['id' => 21, 'type' => '5', 'value' => '3'],
		],
		3 => [
			['id' => 30, 'type' => '4', 'value' => 'B'],
			['id' => 31, 'type' => '5', 'value' => '2'],
		],
	];
	return \$fixtures[(int) \$params[0]] ?? [];
}

require_once {$vdef_file};

echo json_encode([
	'self_cycle'   => get_vdef(1),
	'mutual_cycle' => get_vdef(2, true),
	'logs'         => \$logs,
	'row_queries'  => \$row_queries,
]);
PHP);

		expect($res['self_cycle'])->toBe('CURRENT_DATA_SOURCE')
			->and($res['mutual_cycle'])->toBe('A, B')
			->and($res['row_queries'])->toBe(0)
			->and(count($res['logs']))->toBeGreaterThanOrEqual(2)
			->and($res['logs'][0])->toContain('recursive cycle');
	});

	it('resolves nested DAG VDEFs with caching, zero N+1 item queries, and int/string type matching', function () use ($vdef_file) {
		$res = run_isolated_php_json(<<<PHP
\$vdef_functions = [1 => 'MAXIMUM', 2 => 'AVERAGE'];
\$row_queries    = 0;
\$assoc_queries  = 0;

function cacti_log(\$msg, \$stdout = false, \$fac = '') : void {}
function db_fetch_row_prepared(\$sql, \$params = []) : array {
	global \$row_queries;
	\$row_queries++;
	return ['type' => '1', 'value' => '1'];
}
function db_fetch_assoc_prepared(\$sql, \$params = []) : array {
	global \$assoc_queries;
	\$assoc_queries++;
	\$fixtures = [
		10 => [
			['id' => 101, 'type' => '5', 'value' => '20'],
			['id' => 102, 'type' => '5', 'value' => '20'],
		],
		20 => [
			['id' => 201, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
			['id' => 202, 'type' => 1,   'value' => 1],
			['id' => 203, 'type' => '6', 'value' => '95'],
		],
	];
	return \$fixtures[(int) \$params[0]] ?? [];
}

require_once {$vdef_file};

\$compact   = get_vdef(10, false);
\$q_after_1 = \$assoc_queries;
\$display   = get_vdef(10, true);

echo json_encode([
	'compact'      => \$compact,
	'display'      => \$display,
	'q_after_1'    => \$q_after_1,
	'single'       => get_vdef_item_name(202),
	'row_queries'  => \$row_queries,
	'unknown_type' => get_vdef_item_value(['type' => '99', 'value' => 'X']),
	'missing_fn'   => get_vdef_item_value(['type' => 1, 'value' => 999]),
	'false_item'   => get_vdef_item_value(false),
]);
PHP);

		expect($res['compact'])->toBe('CURRENT_DATA_SOURCE,MAXIMUM,95,CURRENT_DATA_SOURCE,MAXIMUM,95')
			->and($res['display'])->toBe('CURRENT_DATA_SOURCE, MAXIMUM, 95, CURRENT_DATA_SOURCE, MAXIMUM, 95')
			->and($res['q_after_1'])->toBe(2)
			->and($res['row_queries'])->toBe(1)
			->and($res['single'])->toBe('MAXIMUM')
			->and($res['unknown_type'])->toBe('')
			->and($res['missing_fn'])->toBe('')
			->and($res['false_item'])->toBe('');
	});
});

describe('lib/rrdcheck.php empty guard, stream_select pipe I/O, proxy reuse, and per-DS tracking', function () use ($repo_root) {
	$rrdcheck_file = var_export($repo_root . '/lib/rrdcheck.php', true);

	it('returns empty array from get_rrdfiles() without PHP 8 ValueError when 0 RRDs exist', function () use ($rrdcheck_file) {
		$res = run_isolated_php_json(<<<PHP
\$config = ['rra_path' => '/var/lib/cacti/rra'];
function db_fetch_assoc_prepared(\$sql, \$params = []) : array { return []; }

require_once {$rrdcheck_file};

echo json_encode([
	'empty_multi'  => get_rrdfiles(1, 4),
	'empty_single' => get_rrdfiles(1, 1),
]);
PHP);

		expect($res['empty_multi'])->toBe([])
			->and($res['empty_single'])->toBe([]);
	});

	it('handles OK/ERROR substrings in payloads and split chunks without pipe desync', function () use ($rrdcheck_file) {
		$res = run_isolated_php_json(<<<PHP
function cacti_log(\$msg, \$stdout = false, \$fac = '') : void {}

require_once {$rrdcheck_file};

\$worker = <<<'WORKER'
while ((\$line = fgets(STDIN)) !== false) {
	\$cmd = trim(\$line);
	if (str_starts_with(\$cmd, 'info')) {
		fwrite(STDOUT, "filename = \"/rra/OK/ERROR_device.rrd\"\nds[ERRORS].index = 0\nds[OK_pkts].index = 1\n");
		fflush(STDOUT);
		usleep(10000);
		fwrite(STDOUT, "OK ");
		fflush(STDOUT);
		usleep(10000);
		fwrite(STDOUT, "u:0.01 s:0.00 r:0.02\n");
		fflush(STDOUT);
	} elseif (str_starts_with(\$cmd, 'fetch')) {
		fwrite(STDOUT, "ERRORS OK_pkts\n1700000000: 1.0 2.0\nOK u:0.00 s:0.00 r:0.01\n");
		fflush(STDOUT);
	} else {
		fwrite(STDOUT, "ERROR: unknown command\n");
		fflush(STDOUT);
	}
}
WORKER;

\$proc = proc_open([PHP_BINARY, '-r', \$worker], [
	0 => ['pipe', 'r'],
	1 => ['pipe', 'w'],
	2 => ['pipe', 'w'],
], \$pipes);
stream_set_blocking(\$pipes[0], false);
stream_set_blocking(\$pipes[1], false);

\$out1 = rrdcheck_rrdtool_execute(['info', '/rra/OK/ERROR_device.rrd'], \$pipes);
\$out2 = rrdcheck_rrdtool_execute(['fetch', '/rra/OK/ERROR_device.rrd', 'LAST'], \$pipes);
\$out3 = rrdcheck_rrdtool_execute('badcmd', \$pipes);

fclose(\$pipes[0]);
fclose(\$pipes[1]);
fclose(\$pipes[2]);
proc_close(\$proc);

echo json_encode(['out1' => \$out1, 'out2' => \$out2, 'out3' => \$out3]);
PHP);

		expect($res['out1'])->toContain('ds[ERRORS].index = 0')
			->and($res['out1'])->toContain('ds[OK_pkts].index = 1')
			->and($res['out1'])->toContain('OK u:0.01 s:0.00 r:0.02')
			->and($res['out2'])->toContain('1700000000: 1.0 2.0')
			->and($res['out2'])->toContain('OK u:0.00 s:0.00 r:0.01')
			->and($res['out3'])->toContain('ERROR: unknown command');
	});

	it('reuses opened $rrdtool_pipe in proxy mode and tracks 1-hour NaNs per DS independently', function () use ($rrdcheck_file) {
		$res = run_isolated_php_json(<<<PHP
define('RRDTOOL_OUTPUT_BOOLEAN', 1);
define('RRDTOOL_OUTPUT_STDOUT', 2);

\$tmp_rrd = tempnam(sys_get_temp_dir(), 'cacti_rrdcheck_');
touch(\$tmp_rrd);

\$config              = ['rra_path' => sys_get_temp_dir()];
\$debug               = false;
\$type                = '';
\$total_user          = 0; \$total_system = 0; \$total_real = 0; \$total_dsses = 0;
\$user_time           = 0; \$system_time  = 0; \$real_time  = 0; \$rrd_files   = 0;
\$proxy_pipe_sentinel = ['proxy_socket_handle', 'rsa_pub_key'];
\$rrd_init_calls      = 0;
\$rrd_close_pipes     = [];
\$rrd_exec_pipes      = [];
\$inserted_messages   = [];

function cacti_log(\$msg, \$stdout = false, \$fac = '') : void {}
function is_resource_writable(\$path) : bool { return true; }
function set_config_option(\$name, \$value) : void {}
function read_config_option(\$name) : mixed {
	return match (\$name) {
		'poller_interval'   => 300,
		'rrdcheck_parallel' => 1,
		'storage_location'  => 1,
		default             => '',
	};
}
function array_rekey(\$array, \$key, \$cols) : array {
	\$out = [];
	foreach (\$array as \$row) {
		foreach (\$cols as \$c) {
			\$out[\$row[\$key]][\$c] = \$row[\$c];
		}
	}
	return \$out;
}
function db_fetch_assoc(\$sql) : array {
	return [['id' => 1, 'step' => 300, 'steps' => 1, 'rows' => 289]];
}
function db_fetch_assoc_prepared(\$sql, \$params = []) : array {
	global \$tmp_rrd;
	return [[
		'data_source_profile_id' => 1,
		'local_data_id'          => 42,
		'data_source_path'       => \$tmp_rrd,
		'data_source_names'      => 'traffic_in,traffic_out',
		'rrd_step'               => 300,
		'rrd_heartbeat'          => 600,
		'profile_heartbeat'      => 600,
		'profile_step'           => 300,
	]];
}
function db_execute_prepared(\$sql, \$params = []) : void {
	global \$inserted_messages;
	if (str_contains(\$sql, 'INSERT INTO rrdcheck') && isset(\$params[1])) {
		\$inserted_messages[] = \$params[1];
	}
}
function rrd_init(\$log = true) : array {
	global \$rrd_init_calls, \$proxy_pipe_sentinel;
	\$rrd_init_calls++;
	return \$proxy_pipe_sentinel;
}
function rrd_close(\$pipe) : void {
	global \$rrd_close_pipes;
	\$rrd_close_pipes[] = \$pipe;
}
function rrdtool_execute(\$cmd, \$log, \$flag, \$pipe = false, \$logopt = '') : mixed {
	global \$rrd_exec_pipes;
	\$rrd_exec_pipes[] = \$pipe;

	if (is_string(\$cmd) && str_starts_with(\$cmd, 'file_exists')) {
		return true;
	}
	if (is_string(\$cmd) && str_starts_with(\$cmd, 'info')) {
		\$now = time();
		return "last_update = {\$now}\nstep = 300\nds[traffic_in].minimal_heartbeat = 600\nds[traffic_out].minimal_heartbeat = 600\n";
	}

	\$lines = ['traffic_in traffic_out'];
	\$base  = time() - 86400;
	for (\$i = 1; \$i <= 288; \$i++) {
		\$ts      = \$base + (\$i * 300);
		\$out_val = (\$i > 276) ? 'nan' : '1.5000000000e+02';
		\$lines[] = \$ts . ': nan ' . \$out_val;
	}
	return implode("\n", \$lines) . "\nOK u:0.00 s:0.00 r:0.00\n";
}

require_once {$rrdcheck_file};

do_rrdcheck(1);
@unlink(\$tmp_rrd);

echo json_encode([
	'rrd_init_calls'    => \$rrd_init_calls,
	'rrd_close_count'   => count(\$rrd_close_pipes),
	'all_pipes_reused'  => count(\$rrd_exec_pipes) === 3
		&& array_filter(\$rrd_exec_pipes, fn(\$p) => \$p !== \$proxy_pipe_sentinel) === []
		&& \$rrd_close_pipes[0] === \$proxy_pipe_sentinel,
	'inserted_messages' => \$inserted_messages,
]);
PHP);

		$joined = implode("\n", $res['inserted_messages']);

		expect($res['rrd_init_calls'])->toBe(1)
			->and($res['rrd_close_count'])->toBe(1)
			->and($res['all_pipes_reused'])->toBeTrue()
			->and($joined)->toContain("Stale values for last 24 hours, Data Source 'traffic_in'")
			->and($joined)->not->toContain("Stale values for last hour, Data Source 'traffic_in'")
			->and($joined)->toContain("Stale values for last hour, Data Source 'traffic_out'");
	});
});

describe('lib/rrd.php RRA selection, DOM cloning, resize index, and CDEF indexing', function () use ($repo_root) {
	$rrd_file = var_export($repo_root . '/lib/rrd.php', true);

	it('selects best-fit RRA for negative relative and absolute graph_start timestamps', function () use ($rrd_file) {
		$res = run_isolated_php_json(<<<PHP
function read_config_option(\$n) : string { return ''; }
require_once {$rrd_file};

\$rras = [
	['id' => 1, 'steps' => 1,   'rows' => 600,  'timespan' => 86400],
	['id' => 2, 'steps' => 6,   'rows' => 700,  'timespan' => 604800],
	['id' => 3, 'steps' => 24,  'rows' => 775,  'timespan' => 2618784],
	['id' => 4, 'steps' => 288, 'rows' => 797,  'timespan' => 31536000],
];
\$now = 1795831200;

echo json_encode([
	'day_rel'   => rrdtool_select_best_fit_rra(\$rras, 300, -86400, -300, \$now)['id'],
	'week_rel'  => rrdtool_select_best_fit_rra(\$rras, 300, -604800, -300, \$now)['id'],
	'month_rel' => rrdtool_select_best_fit_rra(\$rras, 300, -2592000, \$now, \$now)['id'],
	'abs_week'  => rrdtool_select_best_fit_rra(\$rras, 300, \$now - 604800, \$now, \$now)['id'],
	'empty'     => rrdtool_select_best_fit_rra([], 300, -86400, 0, \$now),
	'false_arg' => rrdtool_select_best_fit_rra(false, 300, -86400, 0, \$now),
]);
PHP);

		expect($res)->toBe([
			'day_rel'   => 1,
			'week_rel'  => 2,
			'month_rel' => 3,
			'abs_week'  => 2,
			'empty'     => null,
			'false_arg' => null,
		]);
	});

	it('clones DOM nodes across all RRAs and rows in rrd_append_cdp_prep_ds() and rrd_append_value()', function () use ($rrd_file) {
		$res = run_isolated_php_json(<<<PHP
define('RRD_FILE_VERSION3', '0003');
function read_config_option(\$n) : string { return ''; }
require_once {$rrd_file};

\$xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<rrd>
	<version>0003</version>
	<rra>
		<cdp_prep><ds><primary_value> 1.0 </primary_value><secondary_value> 2.0 </secondary_value><value> 3.0 </value><unknown_datapoints> 5 </unknown_datapoints></ds></cdp_prep>
		<database><row><v> 1.0 </v></row><row><v> 2.0 </v></row></database>
	</rra>
	<rra>
		<cdp_prep><ds><primary_value> 4.0 </primary_value><secondary_value> 5.0 </secondary_value><value> 6.0 </value><unknown_datapoints> 1 </unknown_datapoints></ds></cdp_prep>
		<database><row><v> 3.0 </v></row><row><v> 4.0 </v></row></database>
	</rra>
</rrd>
XML;

\$dom = new DOMDocument();
\$dom->loadXML(\$xml);

rrd_append_cdp_prep_ds(\$dom, RRD_FILE_VERSION3);
rrd_append_value(\$dom);

\$cdp_counts = [];
\$cdp_vals   = [];
foreach (\$dom->getElementsByTagName('cdp_prep') as \$cdp) {
	\$ds_nodes     = \$cdp->getElementsByTagName('ds');
	\$cdp_counts[] = \$ds_nodes->length;
	\$last_ds      = \$ds_nodes->item(\$ds_nodes->length - 1);
	\$cdp_vals[]   = [
		'primary' => trim(\$last_ds->getElementsByTagName('primary_value')->item(0)->nodeValue),
		'value'   => trim(\$last_ds->getElementsByTagName('value')->item(0)->nodeValue),
		'unk'     => trim(\$last_ds->getElementsByTagName('unknown_datapoints')->item(0)->nodeValue),
	];
}

\$row_v_counts = [];
\$row_last_v   = [];
foreach (\$dom->getElementsByTagName('row') as \$row) {
	\$vs             = \$row->getElementsByTagName('v');
	\$row_v_counts[] = \$vs->length;
	\$row_last_v[]   = trim(\$vs->item(\$vs->length - 1)->nodeValue);
}

echo json_encode([
	'cdp_counts'   => \$cdp_counts,
	'cdp_vals'     => \$cdp_vals,
	'row_v_counts' => \$row_v_counts,
	'row_last_v'   => \$row_last_v,
]);
PHP);

		expect($res['cdp_counts'])->toBe([2, 2])
			->and($res['cdp_vals'][0])->toBe(['primary' => 'NaN', 'value' => 'NaN', 'unk' => '0'])
			->and($res['cdp_vals'][1])->toBe(['primary' => 'NaN', 'value' => 'NaN', 'unk' => '0'])
			->and($res['row_v_counts'])->toBe([2, 2, 2, 2])
			->and($res['row_last_v'])->toBe(['NaN', 'NaN', 'NaN', 'NaN']);
	});

	it('emits file_rra_id in GROW and SHRINK resize commands from rrdtool_cacti_compare()', function () use ($rrd_file) {
		$res = run_isolated_php_json(<<<PHP
\$data_source_types       = [1 => 'GAUGE', 2 => 'COUNTER'];
\$consolidation_functions = [1 => 'AVERAGE', 3 => 'MAX'];
function read_config_option(\$n) : string { return ''; }
function clean_up_path(\$p) : string { return \$p; }
function get_data_source_path(\$id, \$expand = true) : string { return '/rra/test.rrd'; }
function db_fetch_row_prepared(\$sql, \$params = []) : array {
	return str_contains(\$sql, 'FROM data_template_data')
		? ['local_data_template_data_id' => 1, 'rrd_step' => 300, 'data_source_profile_id' => 1]
		: ['host_id' => 1, 'snmp_query_id' => 0, 'snmp_index' => ''];
}
function db_fetch_cell_prepared(\$sql, \$params = []) : int {
	return str_contains(\$sql, 'SELECT heartbeat') ? 600 : 0;
}
function db_fetch_assoc_prepared(\$sql, \$params = []) : array {
	if (str_contains(\$sql, 'FROM data_template_rrd')) {
		return [['id' => 1, 'data_source_name' => 'traffic_in', 'data_source_type_id' => 2, 'rrd_heartbeat' => 600, 'rrd_maximum' => 'U', 'rrd_minimum' => '0']];
	}
	if (str_contains(\$sql, 'FROM data_source_profiles')) {
		return [
			['cf' => 1, 'xff' => 0.5, 'heartbeat' => 600, 'steps' => 1, 'rows' => 800],
			['cf' => 3, 'xff' => 0.5, 'heartbeat' => 600, 'steps' => 6, 'rows' => 500],
		];
	}
	return [];
}
require_once {$rrd_file};

\$info = [
	'filename' => '/rra/test.rrd',
	'step'     => 300,
	'ds'       => ['traffic_in' => ['type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => '0', 'max' => 'U']],
	'rra'      => [
		2 => ['cf' => '"MAX"',     'pdp_per_row' => 6, 'xff' => 0.5, 'rows' => 700],
		3 => ['cf' => '"AVERAGE"', 'pdp_per_row' => 1, 'xff' => 0.5, 'rows' => 600],
	],
];

echo json_encode(['resize' => rrdtool_cacti_compare(10, \$info)['resize']]);
PHP);

		expect($res['resize'])->toBe([
			'/rra/test.rrd 3 GROW 200',
			'/rra/test.rrd 2 SHRINK 200',
		]);
	});

	it('indexes CDEF magic DEFs, builds RPN expressions, and avoids N+1 rrd_step queries', function () use ($repo_root, $rrd_file) {
		$res = run_isolated_php_json(<<<PHP
function read_config_option(\$n) : string { return ''; }
function generate_graph_def_name(\$id) : string { return chr(97 + (int) \$id); }
require_once {$rrd_file};

\$graph_item_types = [4 => 'LINE1', 7 => 'AREA', 9 => 'GPRINT'];
\$graph_items = [
	['graph_type_id' => 7, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in'],
	['graph_type_id' => 4, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in'],
	['graph_type_id' => 4, 'data_template_rrd_id' => 11, 'data_source_name' => 'traffic_out'],
	['graph_type_id' => 9, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in'],
];
\$cf_ds_cache = [
	10 => [1 => 0],
	11 => [1 => 1],
];

echo json_encode([
	'rpn_0'     => rrdtool_build_cdef_magic_rpn([], 1000, false),
	'rpn_1'     => rrdtool_build_cdef_magic_rpn(['a'], 1000, false),
	'rpn_3'     => rrdtool_build_cdef_magic_rpn(['a', 'b', 'c'], 1000, false),
	'rpn_cnt_2' => rrdtool_build_cdef_magic_rpn(['a', 'b'], 1000, true),
	'index'     => rrdtool_index_cdef_magic_defs(\$graph_items, \$graph_item_types, \$cf_ds_cache),
]);
PHP);

		expect($res['rpn_0'])->toBe('')
			->and($res['rpn_1'])->toBe('TIME,1000,GT,a,a,UN,0,a,IF,IF')
			->and($res['rpn_3'])->toBe('TIME,1000,GT,a,a,UN,0,a,IF,IF,TIME,1000,GT,b,b,UN,0,b,IF,IF,TIME,1000,GT,c,c,UN,0,c,IF,IF,+,+')
			->and($res['rpn_cnt_2'])->toBe('TIME,1000,GT,1,a,UN,0,1,IF,IF,TIME,1000,GT,1,b,UN,0,1,IF,IF,+')
			->and($res['index']['all_dups'][1])->toBe(['a', 'a', 'b'])
			->and($res['index']['all_nodups'][1])->toBe(['a', 'b'])
			->and($res['index']['sim_dups'][1]['traffic_in'])->toBe(['a', 'a'])
			->and($res['index']['sim_nodups'][1]['traffic_in'])->toBe(['a']);

		$rrd_source = (string) file_get_contents($repo_root . '/lib/rrd.php');
		expect(substr_count($rrd_source, 'SELECT rrd_step FROM data_template_data WHERE local_data_id = ?'))->toBe(1);
	});

	it('strips CR/LF in escape_command(), validates TEXTALIGN, and quotes dashes/alpha/SHIFT/TICK arguments', function () use ($repo_root, $rrd_file) {
		$res = run_isolated_php_json(<<<PHP
define('RRD_ALIGN_LEFT', 'left');
define('RRD_ALIGN_RIGHT', 'right');
define('RRD_ALIGN_JUSTIFIED', 'justified');
define('RRD_ALIGN_CENTER', 'center');
function read_config_option(\$n) : string { return ''; }
require_once {$rrd_file};

echo json_encode([
	'lf'        => escape_command("graph - --title='ok'\ngraph /tmp/pwn.png DEF:x=/rra/1.rrd:a:AVERAGE"),
	'crlf'      => escape_command("update /rra/1.rrd 100:1\r\ninfo /rra/2.rrd"),
	'cr'        => escape_command("fetch /rra/1.rrd AVERAGE\rquit"),
	'align_ok'  => rrd_graph_item_textalign(' left '),
	'align_ctr' => rrd_graph_item_textalign('center'),
	'align_bad' => rrd_graph_item_textalign('left DEF:x=/etc/passwd:a:AVERAGE'),
	'align_arr' => rrd_graph_item_textalign(['left']),
]);
PHP);

		expect($res['lf'])->not->toContain("\n")
			->and($res['crlf'])->not->toContain("\r")
			->and($res['crlf'])->not->toContain("\n")
			->and($res['cr'])->not->toContain("\r")
			->and($res['align_ok'])->toBe('left')
			->and($res['align_ctr'])->toBe('center')
			->and($res['align_bad'])->toBe('')
			->and($res['align_arr'])->toBe('');

		$rrd_source = (string) file_get_contents($repo_root . '/lib/rrd.php');
		expect($rrd_source)->toContain("cacti_escapeshellarg(\$graph_item['alpha'])")
			->and($rrd_source)->toContain("cacti_escapeshellarg(\$graph_item['dashes'])")
			->and($rrd_source)->toContain("cacti_escapeshellarg(\$graph_item['dash_offset'])")
			->and($rrd_source)->toContain("'SHIFT:' . \$data_source_name . ':' . (float) \$graph_item['value']")
			->and($rrd_source)->toContain("(':' . (float) \$graph_item['value'])")
			->and($rrd_source)->toContain("rrd_graph_item_textalign(\$graph_item['textalign'])");
	});

	it('batch-prefetches update metadata in rrdtool_function_update() to eliminate 2N per-RRD queries', function () use ($rrd_file) {
		$res = run_isolated_php_json(<<<PHP
define('RRDTOOL_OUTPUT_BOOLEAN', 1);
define('RRDTOOL_OUTPUT_STDOUT', 2);
define('RRDTOOL_OUTPUT_STDERR', 3);
define('RRDTOOL_OUTPUT_RETURN_STDERR', 4);
define('POLLER_VERBOSITY_DEBUG', 5);

\$config        = ['cacti_server_os' => 'unix', 'force_storage_location_local' => true];
\$cell_queries  = 0;
\$assoc_queries = [];
\$tmp_rrds      = [];

for (\$i = 1; \$i <= 5; \$i++) {
	\$p = tempnam(sys_get_temp_dir(), 'cacti_upd_');
	touch(\$p);
	\$tmp_rrds[\$i] = \$p;
}

function read_config_option(\$n) : mixed { return 0; }
function cacti_log(\$m, \$o = false, \$f = '', \$l = 0) : void {}
function get_rrdtool_version() : string { return '1.8.0'; }
function cacti_version_compare(\$a, \$b, \$op) : bool { return version_compare(\$a, \$b, \$op); }
function db_fetch_cell_prepared(\$sql, \$params = []) : mixed {
	global \$cell_queries;
	\$cell_queries++;
	return 0;
}
function db_fetch_assoc_prepared(\$sql, \$params = []) : array {
	global \$assoc_queries;
	\$assoc_queries[] = ['sql' => \$sql, 'params' => \$params];
	if (str_contains(\$sql, 'FROM data_local')) {
		return [
			['id' => 101, 'data_template_id' => 10],
			['id' => 102, 'data_template_id' => 10],
			['id' => 103, 'data_template_id' => 20],
			['id' => 104, 'data_template_id' => 0],
			['id' => 105, 'data_template_id' => 20],
		];
	}
	if (str_contains(\$sql, 'FROM data_template_rrd')) {
		return [
			['local_data_id' => 101, 'data_source_name' => 'unused_ds'],
			['local_data_id' => 103, 'data_source_name' => 'orphan_ds'],
		];
	}
	return [];
}

require_once {$rrd_file};

\$batch = [
	\$tmp_rrds[1] => ['local_data_id' => 101, 'times' => [1700000000 => ['traffic_in' => 10, 'unused_ds' => 99]]],
	\$tmp_rrds[2] => ['local_data_id' => 102, 'times' => [1700000000 => ['traffic_in' => 20]]],
	\$tmp_rrds[3] => ['local_data_id' => 103, 'times' => [1700000000 => ['traffic_in' => 30, 'orphan_ds' => 88]]],
	\$tmp_rrds[4] => ['local_data_id' => 104, 'times' => [1700000000 => ['manual_ds'  => 40]]],
	\$tmp_rrds[5] => ['local_data_id' => 105, 'times' => [1700000000 => ['traffic_in' => 50]]],
];

\$pipe1       = fopen('php://temp', 'r+');
\$processed_1 = rrdtool_function_update(\$batch, \$pipe1);
\$q_batch_1   = count(\$assoc_queries);
rewind(\$pipe1);
\$cmds_1      = array_values(array_filter(array_map('trim', explode("\n", (string) stream_get_contents(\$pipe1)))));
fclose(\$pipe1);

\$assoc_queries = [];
\$batch_pre = [
	\$tmp_rrds[1] => ['local_data_id' => 101, 'data_template_id' => 10, 'times' => [1700000300 => ['traffic_in' => 11, 'unused_ds' => 77]]],
];
\$pipe2       = fopen('php://temp', 'r+');
\$processed_2 = rrdtool_function_update(\$batch_pre, \$pipe2, [101 => ['unused_ds' => 'unused_ds']]);
\$q_batch_2   = count(\$assoc_queries);
rewind(\$pipe2);
\$cmds_2      = array_values(array_filter(array_map('trim', explode("\n", (string) stream_get_contents(\$pipe2)))));
fclose(\$pipe2);

foreach (\$tmp_rrds as \$p) {
	@unlink(\$p);
}

echo json_encode([
	'cell_queries' => \$cell_queries,
	'q_batch_1'    => \$q_batch_1,
	'processed_1'  => \$processed_1,
	'cmds_1'       => \$cmds_1,
	'q_batch_2'    => \$q_batch_2,
	'processed_2'  => \$processed_2,
	'cmds_2'       => \$cmds_2,
]);
PHP);

		expect($res['cell_queries'])->toBe(0)
			->and($res['q_batch_1'])->toBe(2)
			->and($res['processed_1'])->toBe(5)
			->and($res['cmds_1'][0])->toContain('--template traffic_in 1700000000:10')
			->and($res['cmds_1'][0])->not->toContain('unused_ds')
			->and($res['cmds_1'][2])->toContain('--template traffic_in 1700000000:30')
			->and($res['cmds_1'][2])->not->toContain('orphan_ds')
			->and($res['cmds_1'][3])->toContain('--template manual_ds 1700000000:40')
			->and($res['q_batch_2'])->toBe(0)
			->and($res['processed_2'])->toBe(1)
			->and($res['cmds_2'][0])->toContain('--template traffic_in 1700000300:11')
			->and($res['cmds_2'][0])->not->toContain('unused_ds');
	});

	it('allows repeated rrdtool_tune() calls without redeclaring print_leaves() and escapes HTML output', function () use ($rrd_file) {
		$res = run_isolated_php_json(<<<PHP
define('CACTI_CLI', false);
function read_config_option(\$n) : string { return '/usr/bin/rrdtool'; }
function html_escape(\$s) : string { return htmlspecialchars((string) \$s, ENT_QUOTES, 'UTF-8'); }
function html_header(\$h) : void {}
function html_end_box() : void {}
require_once {$rrd_file};

\$diff = [
	'ds'   => ['<script>alert(1)</script>' => ['error' => 'Bad <b>DS</b>']],
	'tune' => ['/rra/a.rrd --heartbeat <img src=x>:600'],
];

ob_start();
rrdtool_tune('/rra/a<b>.rrd', \$diff, true);
rrdtool_tune('/rra/a<b>.rrd', \$diff, true);
\$html = ob_get_clean();

echo json_encode(['html' => \$html]);
PHP);

		expect($res['html'])->not->toContain('<script>')
			->and($res['html'])->not->toContain('<b>DS</b>')
			->and($res['html'])->not->toContain('<img src=x>')
			->and($res['html'])->toContain('&lt;b&gt;DS&lt;/b&gt;')
			->and($res['html'])->toContain('&lt;img src=x&gt;')
			->and(substr_count($res['html'], 'Bad &lt;b&gt;DS&lt;/b&gt;'))->toBe(2);
	});
});

describe('lib/spikekill.php validation and comment timestamp extraction', function () use ($repo_root) {
	$spikekill_file = var_export($repo_root . '/lib/spikekill.php', true);

	it('validates float/fill methods, rejects invalid numspike values, and extracts row timestamps', function () use ($spikekill_file) {
		$res = run_isolated_php_json(<<<PHP
\$tmp_rrd = tempnam(sys_get_temp_dir(), 'cacti_sk_');
touch(\$tmp_rrd);

function get_execution_user() : string { return 'test'; }
function read_config_option(\$name, \$force = false) : mixed {
	return match (\$name) {
		'spikekill_method'     => 'stddev',
		'spikekill_number'     => 10,
		'spikekill_deviations' => 10,
		'spikekill_percent'    => 500,
		'spikekill_outliers'   => 5,
		'spikekill_avgnan'     => 'last',
		default                => '',
	};
}
function read_user_setting(\$name, \$default = '', \$force = false) : mixed { return \$default; }

require_once {$spikekill_file};

\$init = new ReflectionMethod('spikekill', 'initialize_spikekill');
\$run  = function (string \$method, mixed \$start, mixed \$end, int \$outliers, mixed \$numspike) use (\$tmp_rrd, \$init) : array {
	\$sk = new spikekill(\$tmp_rrd, \$method, 'last', 10, \$start, \$end, \$outliers, 500, \$numspike);
	\$sk->html = false;
	\$init->invoke(\$sk);
	return [\$sk, \$sk->get_errors()];
};

[, \$err_float]      = \$run('float', 'not-a-valid-date', '2026-01-02 00:00', 5, 10);
[, \$err_fill]       = \$run('fill', 1700000000, 1700003600, 0, 10);
[\$sk_neg, \$err_neg] = \$run('stddev', '', '', 5, -5);
[, \$err_abc]        = \$run('stddev', '', '', 5, 'abc');

\$rm    = new ReflectionMethod('spikekill', 'removeComments');
\$lines = [
	'<!-- 2026-11-27 18:00:00 PST / 1795831200 --><row><v>1.23e+02</v></row>',
	'<row><v>4.56e+02</v></row> <!-- 2026-11-27 18:05:00 PST / 1795831500 -->',
];
\$cleaned = \$rm->invokeArgs(\$sk_neg, [&\$lines]);

@unlink(\$tmp_rrd);

echo json_encode([
	'err_float' => \$err_float,
	'err_fill'  => \$err_fill,
	'err_neg'   => \$err_neg,
	'err_abc'   => \$err_abc,
	'cleaned'   => \$cleaned,
]);
PHP);

		expect($res['err_float'])->toContain('outlier-start and outlier-end')
			->and($res['err_fill'])->toContain('number of outliers to exclude must be a positive integer')
			->and($res['err_neg'])->toContain('Number of spikes to remove must be a positive integer')
			->and($res['err_abc'])->toContain('Number of spikes to remove must be a positive integer')
			->and($res['cleaned'])->toBe([
				'<row><timestamp> 1795831200 </timestamp><v>1.23e+02</v></row>',
				'<row><timestamp> 1795831500 </timestamp><v>4.56e+02</v></row>',
			]);
	});

	it('skips NaN samples in PHP 8 comparisons, bounds numspike across rows, aligns windowed mean, and formats CLI stats', function () use ($spikekill_file) {
		$res = run_isolated_php_json(<<<PHP
\$tmp_rrd = tempnam(sys_get_temp_dir(), 'cacti_sk2_');
touch(\$tmp_rrd);

function get_execution_user() : string { return 'test'; }
function read_config_option(\$name, \$force = false) : mixed { return ''; }
function read_user_setting(\$name, \$default = '', \$force = false) : mixed { return \$default; }
function number_format_i18n(\$num, \$dec = 0) : string { return number_format((float) \$num, (int) \$dec); }
function cacti_log(\$m, \$o = false, \$f = '', \$l = 0) : void {}

require_once {$spikekill_file};

\$sk = new spikekill(\$tmp_rrd, 'stddev', 'nan', 2, 1700001000, 1700002000, 1, 500, 1);
\$sk->html = false;

\$setProp = function (object \$obj, string \$name, mixed \$val) : void {
	\$p = new ReflectionProperty(\$obj, \$name);
	if (PHP_VERSION_ID < 80100) {
		\$p->setAccessible(true);
	}
	\$p->setValue(\$obj, \$val);
};
\$getProp = function (object \$obj, string \$name) : mixed {
	\$p = new ReflectionProperty(\$obj, \$name);
	if (PHP_VERSION_ID < 80100) {
		\$p->setAccessible(true);
	}
	return \$p->getValue(\$obj);
};

\$setProp(\$sk, 'method', SPIKE_METHOD_STDDEV);
\$setProp(\$sk, 'ds_min', [0 => 'NaN']);
\$setProp(\$sk, 'ds_max', [0 => 'NaN']);
\$setProp(\$sk, 'ds_name', [0 => 'traffic_in']);
\$setProp(\$sk, 'rra_cf', [0 => 'AVERAGE']);
\$setProp(\$sk, 'rra_pdp', [0 => 1]);
\$setProp(\$sk, 'step', 300);

\$samples = [
	0 => [
		0 => [
			1700000100 => '10',
			1700000400 => '12',
			1700000700 => '11',
			1700001200 => 'NaN',
			1700001500 => '5000',
			1700001800 => '6000',
			1700002300 => '11',
		],
	],
];
\$rra = [
	0 => [
		0 => [
			'totalsamples' => 7,
			'numsamples'   => 6,
			'sumofsamples' => 11044,
			'max_value'    => 6000,
			'min_value'    => 10,
			'variance_avg' => 'NAN',
		],
	],
];

\$calc = new ReflectionMethod('spikekill', 'calculateOverallStatistics');
if (PHP_VERSION_ID < 80100) {
	\$calc->setAccessible(true);
}
\$calc->invokeArgs(\$sk, [&\$rra, &\$samples]);

\$output_lines = [
	'<row><timestamp> 1700000100 </timestamp><v> 10 </v></row>',
	'<row><timestamp> 1700001200 </timestamp><v> NaN </v></row>',
	'<row><timestamp> 1700001500 </timestamp><v> 5000 </v></row>',
	'<row><timestamp> 1700001800 </timestamp><v> 6000 </v></row>',
];

\$upd = new ReflectionMethod('spikekill', 'updateXML');
if (PHP_VERSION_ID < 80100) {
	\$upd->setAccessible(true);
}
\$updated = \$upd->invokeArgs(\$sk, [&\$output_lines, &\$rra]);

\$outStats = new ReflectionMethod('spikekill', 'outputStatistics');
if (PHP_VERSION_ID < 80100) {
	\$outStats->setAccessible(true);
}
\$outStats->invoke(\$sk, \$rra);

@unlink(\$tmp_rrd);

echo json_encode([
	'average'       => \$rra[0][0]['average'],
	'min_cutoff'    => \$rra[0][0]['min_cutoff'],
	'max_cutoff'    => \$rra[0][0]['max_cutoff'],
	'stddev_killed' => \$rra[0][0]['stddev_killed'],
	'total_kills'   => \$getProp(\$sk, 'total_kills'),
	'updated'       => \$updated,
	'cli_stats'     => \$getProp(\$sk, 'strout'),
]);
PHP);

		expect($res['average'])->toBe(11)
			->and($res['min_cutoff'])->not->toBe('NaN')
			->and($res['max_cutoff'])->not->toBe('NaN')
			->and($res['stddev_killed'])->toBe(2)
			->and($res['total_kills'])->toBe(1)
			->and($res['updated'][1])->toContain('<v> NaN</v>')
			->and($res['updated'][2])->toContain('<v> NaN</v>')
			->and($res['updated'][3])->toContain('<v> 6000</v>')
			->and($res['cli_stats'])->toContain('traffic_in');
	});
});

describe('lib/graph_variables.php CF selection, deduplication, and binary scaling', function () use ($repo_root) {
	$gv_file = var_export($repo_root . '/lib/graph_variables.php', true);

	it('fetches only the requested CF with fallback when missing in rrdtool_function_stats()', function () use ($gv_file) {
		$res = run_isolated_php_json(<<<PHP
\$config    = ['library_path' => sys_get_temp_dir()];
\$dummy_rrd = sys_get_temp_dir() . '/rrd.php';
if (!file_exists(\$dummy_rrd)) {
	file_put_contents(\$dummy_rrd, "<?php\n");
}

\$available_cfs = ['AVERAGE' => true, 'MAX' => true];
\$fetch_log     = [];

function rrdtool_function_contains_cf(\$ldi, \$cf) : bool {
	global \$available_cfs;
	return !empty(\$available_cfs[\$cf]);
}
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0, \$show = false, \$pipe = null, \$cf = 'AVERAGE') : array {
	global \$fetch_log;
	\$fetch_log[] = ['ldi' => \$ldi, 'cf' => \$cf];
	\$val         = (\$cf === 'MAX') ? 200.0 : 100.0;
	return [
		'data_source_names' => [0 => 'traffic_in'],
		'values'            => [0 => [1000 => \$val, 1300 => \$val, 1600 => \$val]],
	];
}

require_once {$gv_file};

\$fetch_log = [];
\$avg_res   = nth_percentile([10 => ['traffic_in']], -86400, 0, 95, 0, false);
\$avg_log   = \$fetch_log;

\$fetch_log = [];
\$max_res   = nth_percentile([10 => ['traffic_in']], -86400, 0, 95, 0, true);
\$max_log   = \$fetch_log;

\$available_cfs = ['AVERAGE' => true, 'MAX' => false];
\$fetch_log     = [];
\$fb_res        = nth_percentile([10 => ['traffic_in']], -86400, 0, 95, 0, true);
\$fb_log        = \$fetch_log;

@unlink(\$dummy_rrd);

echo json_encode([
	'avg_fetches'      => \$avg_log,
	'avg_val'          => \$avg_res['traffic_in'],
	'max_fetches'      => \$max_log,
	'max_val'          => \$max_res['traffic_in'],
	'fallback_fetches' => \$fb_log,
	'fallback_val'     => \$fb_res['traffic_in'],
]);
PHP);

		expect($res['avg_fetches'])->toBe([['ldi' => 10, 'cf' => 'AVERAGE']])
			->and($res['avg_val'])->toBe(100)
			->and($res['max_fetches'])->toBe([['ldi' => 10, 'cf' => 'MAX']])
			->and($res['max_val'])->toBe(200)
			->and($res['fallback_fetches'])->toBe([['ldi' => 10, 'cf' => 'AVERAGE']])
			->and($res['fallback_val'])->toBe(100);
	});

	it('handles graphs without graphable items and scales base 1024 accurately in variable_nth_percentile()', function () use ($gv_file) {
		$res = run_isolated_php_json(<<<PHP
\$config    = ['library_path' => sys_get_temp_dir()];
\$dummy_rrd = sys_get_temp_dir() . '/rrd.php';
if (!file_exists(\$dummy_rrd)) {
	file_put_contents(\$dummy_rrd, "<?php\n");
}

\$graph_item_types = [4 => 'LINE1', 7 => 'AREA', 9 => 'GPRINT'];

function rrdtool_function_contains_cf(\$ldi, \$cf) : bool { return true; }
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0, \$show = false, \$pipe = null, \$cf = 'AVERAGE') : array {
	return [
		'data_source_names' => [0 => 'mem_used'],
		'values'            => [0 => [1000 => 1073741824, 1300 => 1073741824, 1600 => 1073741824]],
	];
}

require_once {$gv_file};

\$graph_bin  = ['base_value' => 1024];
\$graph_dec  = ['base_value' => 1000];
\$item       = ['local_data_id' => 10, 'data_source_name' => 'mem_used'];
\$gprint     = [['local_data_id' => 10, 'data_template_rrd_id' => 1, 'data_source_name' => 'mem_used', 'graph_type_id' => 9]];
\$graphable  = [['local_data_id' => 10, 'data_template_rrd_id' => 1, 'data_source_name' => 'mem_used', 'graph_type_id' => 7]];
\$m3         = ['|95:bytes:3:current:2|', '95', 'bytes', '3', 'current', '2'];
\$m6         = ['|95:bytes:6:current:2|', '95', 'bytes', '6', 'current', '2'];
\$m9         = ['|95:bytes:9:current:2|', '95', 'bytes', '9', 'current', '2'];

\$out = [
	'no_graphable' => variable_nth_percentile(\$m6, \$graph_bin, \$item, \$gprint, -86400, 0),
	'kib'          => variable_nth_percentile(\$m3, \$graph_bin, \$item, \$graphable, -86400, 0),
	'mib'          => variable_nth_percentile(\$m6, \$graph_bin, \$item, \$graphable, -86400, 0),
	'gib'          => variable_nth_percentile(\$m9, \$graph_bin, \$item, \$graphable, -86400, 0),
	'mb'           => variable_nth_percentile(\$m6, \$graph_dec, \$item, \$graphable, -86400, 0),
];

@unlink(\$dummy_rrd);

echo json_encode(\$out);
PHP);

		expect((float) $res['no_graphable'])->toBe(0.0)
			->and((float) $res['kib'])->toBe(1048576.0)
			->and((float) $res['mib'])->toBe(1024.0)
			->and((float) $res['gib'])->toBe(1.0)
			->and((float) $res['mb'])->toBe(1073.74);
	});

	it('deduplicates fetches and duplicate DS items and scales base 1024 in variable_bandwidth_summation()', function () use ($gv_file) {
		$res = run_isolated_php_json(<<<PHP
\$graph_item_types = [4 => 'LINE1', 7 => 'AREA'];
\$fetch_count      = 0;

function number_format_i18n(\$num, \$dec = 2) : string { return number_format((float) \$num, (int) \$dec, '.', ''); }
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0) : array {
	global \$fetch_count;
	\$fetch_count++;
	return [
		'data_source_names' => [0 => 'traffic_in', 1 => 'traffic_out'],
		'values'            => [0 => [1000 => 524288], 1 => [1000 => 524288]],
	];
}

require_once {$gv_file};

\$graph_bin   = ['base_value' => 1024];
\$graph_item  = ['local_data_id' => 5, 'data_source_name' => 'traffic_in'];
\$graph_items = [
	['local_data_id' => 5, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in',  'graph_type_id' => 7],
	['local_data_id' => 5, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in',  'graph_type_id' => 4],
	['local_data_id' => 5, 'data_template_rrd_id' => 11, 'data_source_name' => 'traffic_out', 'graph_type_id' => 4],
];
\$match  = ['|sum:6:total:2:auto|', '6', 'total', '2', 'auto'];
\$result = trim(variable_bandwidth_summation(\$match, \$graph_bin, \$graph_item, \$graph_items, -86400, 0, 1, 1));

echo json_encode([
	'fetch_count' => \$fetch_count,
	'result'      => \$result,
]);
PHP);

		expect($res['fetch_count'])->toBe(1)
			->and($res['result'])->toBe('1.00');
	});
});
