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
 * @param string $code PHP code without opening tag
 *
 * @return array<string,mixed>
 */
function run_isolated_php_json(string $code) : array {
	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w')
	);

	$process = proc_open(array(PHP_BINARY), $descriptors, $pipes);
	expect(is_resource($process))->toBeTrue();

	fwrite($pipes[0], "<?php\nerror_reporting(E_ALL);\nini_set('display_errors', '0');\n" . $code);
	fclose($pipes[0]);

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit_code = proc_close($process);

	expect($exit_code)->toBe(0, "Child PHP exited with {$exit_code}. STDERR: {$stderr}\nSTDOUT: {$stdout}");

	$decoded = json_decode((string) $stdout, true);
	expect(is_array($decoded))->toBeTrue("Expected JSON object, got: {$stdout}\nSTDERR: {$stderr}");

	return $decoded;
}

$repo_root = dirname(__DIR__, 3);

/* -------------------------------------------------------------------------
 * 1. lib/vdef.php: cycle detection and elimination of N+1 vdef_items queries
 * ------------------------------------------------------------------------- */

test('get_vdef detects direct and mutual cycles without infinite recursion', function () use ($repo_root) {
	$vdef_file = var_export($repo_root . '/lib/vdef.php', true);

	$res = run_isolated_php_json(<<<PHP
\$vdef_functions = array(1 => 'MAXIMUM', 2 => 'MINIMUM', 3 => 'AVERAGE');
\$logs = array();
\$row_queries = 0;
\$assoc_queries = 0;

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function cacti_log(\$msg, \$stdout = false, \$fac = '') { global \$logs; \$logs[] = \$msg; }
function db_fetch_row_prepared(\$sql, \$params = array()) {
	global \$row_queries;
	\$row_queries++;
	return array('type' => '4', 'value' => 'SHOULD_NOT_BE_CALLED');
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	global \$assoc_queries;
	\$assoc_queries++;
	\$id = (int) \$params[0];
	\$fixtures = array(
		1 => array(
			array('id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'),
			array('id' => 11, 'type' => '5', 'value' => '1'),
		),
		2 => array(
			array('id' => 20, 'type' => '4', 'value' => 'A'),
			array('id' => 21, 'type' => '5', 'value' => '3'),
		),
		3 => array(
			array('id' => 30, 'type' => '4', 'value' => 'B'),
			array('id' => 31, 'type' => '5', 'value' => '2'),
		),
	);
	return isset(\$fixtures[\$id]) ? \$fixtures[\$id] : array();
}

require_once {$vdef_file};

\$self_cycle   = get_vdef(1);
\$mutual_cycle = get_vdef(2, true);

echo json_encode(array(
	'self_cycle'    => \$self_cycle,
	'mutual_cycle'  => \$mutual_cycle,
	'logs'          => \$logs,
	'row_queries'   => \$row_queries,
	'assoc_queries' => \$assoc_queries,
));
PHP);

	expect($res['self_cycle'])->toBe('CURRENT_DATA_SOURCE');
	expect($res['mutual_cycle'])->toBe('A, B');
	expect($res['row_queries'])->toBe(0);
	expect(count($res['logs']))->toBeGreaterThanOrEqual(2);
	expect($res['logs'][0])->toContain('recursive cycle');
});

test('get_vdef resolves nested DAG VDEFs with zero N+1 item queries and caches sub-VDEFs per call', function () use ($repo_root) {
	$vdef_file = var_export($repo_root . '/lib/vdef.php', true);

	$res = run_isolated_php_json(<<<PHP
\$vdef_functions = array(1 => 'MAXIMUM', 2 => 'AVERAGE');
\$row_queries = 0;
\$assoc_queries = 0;

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function cacti_log(\$msg, \$stdout = false, \$fac = '') {}
function db_fetch_row_prepared(\$sql, \$params = array()) {
	global \$row_queries;
	\$row_queries++;
	return array('type' => '1', 'value' => '1');
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	global \$assoc_queries;
	\$assoc_queries++;
	\$id = (int) \$params[0];
	\$fixtures = array(
		10 => array(
			array('id' => 101, 'type' => '5', 'value' => '20'),
			array('id' => 102, 'type' => '5', 'value' => '20'),
		),
		20 => array(
			array('id' => 201, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'),
			array('id' => 202, 'type' => '1', 'value' => '1'),
			array('id' => 203, 'type' => '6', 'value' => '95'),
		),
	);
	return isset(\$fixtures[\$id]) ? \$fixtures[\$id] : array();
}

require_once {$vdef_file};

\$compact   = get_vdef(10, false);
\$q_after_1 = \$assoc_queries;
\$display   = get_vdef(10, true);
\$single    = get_vdef_item_name(202);

echo json_encode(array(
	'compact'     => \$compact,
	'display'     => \$display,
	'q_after_1'   => \$q_after_1,
	'row_queries' => \$row_queries,
	'single'      => \$single,
));
PHP);

	expect($res['compact'])->toBe('CURRENT_DATA_SOURCE,MAXIMUM,95,CURRENT_DATA_SOURCE,MAXIMUM,95');
	expect($res['display'])->toBe('CURRENT_DATA_SOURCE, MAXIMUM, 95, CURRENT_DATA_SOURCE, MAXIMUM, 95');
	// Only 2 assoc queries for the first get_vdef(10): one for VDEF 10 and one cached lookup for VDEF 20.
	expect($res['q_after_1'])->toBe(2);
	// Only the direct get_vdef_item_name() call ran db_fetch_row_prepared (not get_vdef()).
	expect($res['row_queries'])->toBe(1);
	expect($res['single'])->toBe('MAXIMUM');
});

/* -------------------------------------------------------------------------
 * 2. lib/rrdcheck.php: get_rrdfiles() empty guard, stream_select() pipe I/O,
 *    proxy pipe reuse, and per-DS $notified tracking
 * ------------------------------------------------------------------------- */

test('get_rrdfiles returns empty array without PHP 8 ValueError when 0 RRDs exist', function () use ($repo_root) {
	$rrdcheck_file = var_export($repo_root . '/lib/rrdcheck.php', true);

	$res = run_isolated_php_json(<<<PHP
\$config = array('rra_path' => '/var/lib/cacti/rra');
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function db_fetch_assoc_prepared(\$sql, \$params = array()) { return array(); }

require_once {$rrdcheck_file};

\$empty_multi  = get_rrdfiles(1, 4);
\$empty_single = get_rrdfiles(1, 1);

echo json_encode(array(
	'empty_multi'  => \$empty_multi,
	'empty_single' => \$empty_single,
));
PHP);

	expect($res['empty_multi'])->toBe(array());
	expect($res['empty_single'])->toBe(array());
});

test('rrdcheck_rrdtool_execute handles OK/ERROR substrings in payloads and split chunks without pipe desync', function () use ($repo_root) {
	$rrdcheck_file = var_export($repo_root . '/lib/rrdcheck.php', true);

	$res = run_isolated_php_json(<<<PHP
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function cacti_escapeshellarg(\$arg) { return escapeshellarg((string) \$arg); }
function cacti_log(\$msg, \$stdout = false, \$fac = '') {}

require_once {$rrdcheck_file};

\$worker_script = 'while ((\$line = fgets(STDIN)) !== false) { '
	. '\$cmd = trim(\$line); '
	. 'if (strpos(\$cmd, "info") === 0) { '
	. '  fwrite(STDOUT, "filename = \"/rra/OK/ERROR_device.rrd\"\n"); '
	. '  fwrite(STDOUT, "ds[ERRORS].index = 0\n"); '
	. '  fwrite(STDOUT, "ds[OK_pkts].index = 1\n"); '
	. '  fflush(STDOUT); '
	. '  usleep(10000); '
	. '  fwrite(STDOUT, "OK "); '
	. '  fflush(STDOUT); '
	. '  usleep(10000); '
	. '  fwrite(STDOUT, "u:0.01 s:0.00 r:0.02\n"); '
	. '  fflush(STDOUT); '
	. '} elseif (strpos(\$cmd, "fetch") === 0) { '
	. '  fwrite(STDOUT, "ERRORS OK_pkts\n1700000000: 1.0 2.0\nOK u:0.00 s:0.00 r:0.01\n"); '
	. '  fflush(STDOUT); '
	. '} else { '
	. '  fwrite(STDOUT, "ERROR: unknown command\n"); '
	. '  fflush(STDOUT); '
	. '} }';

\$fds = array(
	0 => array('pipe', 'r'),
	1 => array('pipe', 'w'),
	2 => array('pipe', 'w'),
);
\$proc = proc_open(array(PHP_BINARY, '-r', \$worker_script), \$fds, \$pipes);
stream_set_blocking(\$pipes[0], false);
stream_set_blocking(\$pipes[1], false);

\$out1 = rrdcheck_rrdtool_execute(array('info', '/rra/OK/ERROR_device.rrd'), \$pipes);
\$out2 = rrdcheck_rrdtool_execute(array('fetch', '/rra/OK/ERROR_device.rrd', 'LAST'), \$pipes);
\$out3 = rrdcheck_rrdtool_execute('badcmd', \$pipes);

fclose(\$pipes[0]);
fclose(\$pipes[1]);
fclose(\$pipes[2]);
proc_close(\$proc);

echo json_encode(array(
	'out1' => \$out1,
	'out2' => \$out2,
	'out3' => \$out3,
));
PHP);

	expect($res['out1'])->toContain('ds[ERRORS].index = 0');
	expect($res['out1'])->toContain('ds[OK_pkts].index = 1');
	expect($res['out1'])->toContain('OK u:0.01 s:0.00 r:0.02');
	expect($res['out2'])->toContain('1700000000: 1.0 2.0');
	expect($res['out2'])->toContain('OK u:0.00 s:0.00 r:0.01');
	expect($res['out3'])->toContain('ERROR: unknown command');
});

test('do_rrdcheck reuses opened $rrdtool_pipe in proxy mode and checks 1-hour NaNs per DS independently', function () use ($repo_root) {
	$rrdcheck_file = var_export($repo_root . '/lib/rrdcheck.php', true);

	$res = run_isolated_php_json(<<<PHP
define('RRDTOOL_OUTPUT_BOOLEAN', 1);
define('RRDTOOL_OUTPUT_STDOUT', 2);

\$tmp_rrd = tempnam(sys_get_temp_dir(), 'cacti_rrdcheck_');
touch(\$tmp_rrd);

\$config = array('rra_path' => sys_get_temp_dir());
\$debug = false;
\$type = '';
\$total_user = 0; \$total_system = 0; \$total_real = 0; \$total_dsses = 0;
\$user_time = 0; \$system_time = 0; \$real_time = 0; \$rrd_files = 0;

\$proxy_pipe_sentinel = array('proxy_socket_handle', 'rsa_pub_key');
\$rrd_init_calls = 0;
\$rrd_close_pipes = array();
\$rrd_exec_pipes = array();
\$inserted_messages = array();

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function cacti_escapeshellarg(\$arg) { return escapeshellarg((string) \$arg); }
function cacti_log(\$msg, \$stdout = false, \$fac = '') {}
function is_resource_writable(\$path) { return true; }
function set_config_option(\$name, \$value) {}
function read_config_option(\$name) {
	\$map = array(
		'poller_interval'   => 300,
		'rrdcheck_parallel' => 1,
		'storage_location'  => 1,
	);
	return isset(\$map[\$name]) ? \$map[\$name] : '';
}
function array_rekey(\$array, \$key, \$cols) {
	\$out = array();
	foreach (\$array as \$row) {
		\$out[\$row[\$key]] = array();
		foreach (\$cols as \$c) {
			\$out[\$row[\$key]][\$c] = \$row[\$c];
		}
	}
	return \$out;
}
function db_fetch_assoc(\$sql) {
	return array(
		array('id' => 1, 'step' => 300, 'steps' => 1, 'rows' => 289)
	);
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	global \$tmp_rrd;
	return array(
		array(
			'data_source_profile_id' => 1,
			'local_data_id'          => 42,
			'data_source_path'       => \$tmp_rrd,
			'data_source_names'      => 'traffic_in,traffic_out',
			'rrd_step'               => 300,
			'rrd_heartbeat'          => 600,
			'profile_heartbeat'      => 600,
			'profile_step'           => 300,
		)
	);
}
function db_execute_prepared(\$sql, \$params = array()) {
	global \$inserted_messages;
	if (strpos(\$sql, 'INSERT INTO rrdcheck') !== false && isset(\$params[1])) {
		\$inserted_messages[] = \$params[1];
	}
}
function rrd_init(\$log = true) {
	global \$rrd_init_calls, \$proxy_pipe_sentinel;
	\$rrd_init_calls++;
	return \$proxy_pipe_sentinel;
}
function rrd_close(\$pipe) {
	global \$rrd_close_pipes;
	\$rrd_close_pipes[] = \$pipe;
}
function rrdtool_execute(\$cmd, \$log, \$flag, \$pipe = false, \$logopt = '') {
	global \$rrd_exec_pipes;
	\$rrd_exec_pipes[] = \$pipe;

	if (is_string(\$cmd) && strpos(\$cmd, 'file_exists') === 0) {
		return true;
	}
	if (is_string(\$cmd) && strpos(\$cmd, 'info') === 0) {
		\$now = time();
		return "last_update = {\$now}\nstep = 300\nds[traffic_in].minimal_heartbeat = 600\nds[traffic_out].minimal_heartbeat = 600\n";
	}

	// Build 288 rows (24 hours at 300s step):
	// traffic_in is NaN for all 288 rows (24h stale -> notified['traffic_in'] = true).
	// traffic_out is valid for first 276 rows and NaN for last 12 rows (last 1h stale).
	\$lines = array('traffic_in traffic_out');
	\$base = time() - 86400;
	for (\$i = 1; \$i <= 288; \$i++) {
		\$ts = \$base + (\$i * 300);
		\$out_val = (\$i > 276) ? 'nan' : '1.5000000000e+02';
		\$lines[] = \$ts . ': nan ' . \$out_val;
	}
	return implode("\n", \$lines) . "\nOK u:0.00 s:0.00 r:0.00\n";
}

require_once {$rrdcheck_file};

do_rrdcheck(1);
@unlink(\$tmp_rrd);

echo json_encode(array(
	'rrd_init_calls'    => \$rrd_init_calls,
	'rrd_close_count'   => count(\$rrd_close_pipes),
	'all_pipes_reused'  => (count(\$rrd_exec_pipes) === 3
		&& \$rrd_exec_pipes[0] === \$proxy_pipe_sentinel
		&& \$rrd_exec_pipes[1] === \$proxy_pipe_sentinel
		&& \$rrd_exec_pipes[2] === \$proxy_pipe_sentinel
		&& \$rrd_close_pipes[0] === \$proxy_pipe_sentinel),
	'inserted_messages' => \$inserted_messages,
));
PHP);

	expect($res['rrd_init_calls'])->toBe(1);
	expect($res['rrd_close_count'])->toBe(1);
	expect($res['all_pipes_reused'])->toBeTrue();

	$joined = implode("\n", $res['inserted_messages']);
	expect($joined)->toContain("Stale values for last 24 hours, Data Source 'traffic_in'");
	expect($joined)->not->toContain("Stale values for last hour, Data Source 'traffic_in'");
	expect($joined)->toContain("Stale values for last hour, Data Source 'traffic_out'");
});

/* -------------------------------------------------------------------------
 * 3. lib/rrd.php: best-fit RRA selection, DOM cloneNode(), rrdtool resize
 *    $file_rra_id, CDEF magic-variable RPN builder, and rrd_step caching
 * ------------------------------------------------------------------------- */

test('rrdtool_select_best_fit_rra selects matching RRA for negative relative graph_start timestamps', function () use ($repo_root) {
	$rrd_file = var_export($repo_root . '/lib/rrd.php', true);

	$res = run_isolated_php_json(<<<PHP
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function read_config_option(\$n) { return ''; }
require_once {$rrd_file};

\$rras = array(
	array('id' => 1, 'steps' => 1,   'rows' => 600,  'timespan' => 86400),   // 180,000s (~2d) at 300s step
	array('id' => 2, 'steps' => 6,   'rows' => 700,  'timespan' => 604800),  // 1,260,000s (~14.5d)
	array('id' => 3, 'steps' => 24,  'rows' => 775,  'timespan' => 2618784), // 5,580,000s (~64.5d)
	array('id' => 4, 'steps' => 288, 'rows' => 797,  'timespan' => 31536000) // 68,860,800s (~797d)
);
\$now = 1795831200;

\$rra_day_rel   = rrdtool_select_best_fit_rra(\$rras, 300, -86400, -300, \$now);
\$rra_week_rel  = rrdtool_select_best_fit_rra(\$rras, 300, -604800, -300, \$now);
\$rra_month_rel = rrdtool_select_best_fit_rra(\$rras, 300, -2592000, \$now, \$now);
\$rra_abs_week  = rrdtool_select_best_fit_rra(\$rras, 300, \$now - 604800, \$now, \$now);

echo json_encode(array(
	'day_rel'   => \$rra_day_rel['id'],
	'week_rel'  => \$rra_week_rel['id'],
	'month_rel' => \$rra_month_rel['id'],
	'abs_week'  => \$rra_abs_week['id'],
));
PHP);

	expect($res['day_rel'])->toBe(1);
	expect($res['week_rel'])->toBe(2);
	expect($res['month_rel'])->toBe(3);
	expect($res['abs_week'])->toBe(2);
});

test('rrd_append_value and rrd_append_cdp_prep_ds clone nodes across all RRAs and rows', function () use ($repo_root) {
	$rrd_file = var_export($repo_root . '/lib/rrd.php', true);

	$res = run_isolated_php_json(<<<PHP
define('RRD_FILE_VERSION3', '0003');
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function read_config_option(\$n) { return ''; }
require_once {$rrd_file};

\$xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<rrd>
	<version>0003</version>
	<rra>
		<cdp_prep>
			<ds>
				<primary_value> 1.0000000000e+00 </primary_value>
				<secondary_value> 2.0000000000e+00 </secondary_value>
				<value> 3.0000000000e+00 </value>
				<unknown_datapoints> 5 </unknown_datapoints>
			</ds>
		</cdp_prep>
		<database>
			<row><v> 1.0 </v></row>
			<row><v> 2.0 </v></row>
		</database>
	</rra>
	<rra>
		<cdp_prep>
			<ds>
				<primary_value> 4.0000000000e+00 </primary_value>
				<secondary_value> 5.0000000000e+00 </secondary_value>
				<value> 6.0000000000e+00 </value>
				<unknown_datapoints> 1 </unknown_datapoints>
			</ds>
		</cdp_prep>
		<database>
			<row><v> 3.0 </v></row>
			<row><v> 4.0 </v></row>
		</database>
	</rra>
</rrd>
XML;

\$dom = new DOMDocument();
\$dom->loadXML(\$xml);

rrd_append_cdp_prep_ds(\$dom, RRD_FILE_VERSION3);
rrd_append_value(\$dom);

\$cdp_counts = array();
\$cdp_new_values = array();
foreach (\$dom->getElementsByTagName('cdp_prep') as \$cdp) {
	\$ds_nodes = \$cdp->getElementsByTagName('ds');
	\$cdp_counts[] = \$ds_nodes->length;
	\$last_ds = \$ds_nodes->item(\$ds_nodes->length - 1);
	\$cdp_new_values[] = array(
		'primary' => trim(\$last_ds->getElementsByTagName('primary_value')->item(0)->nodeValue),
		'value'   => trim(\$last_ds->getElementsByTagName('value')->item(0)->nodeValue),
		'unk'     => trim(\$last_ds->getElementsByTagName('unknown_datapoints')->item(0)->nodeValue),
	);
}

\$row_v_counts = array();
\$row_last_v = array();
foreach (\$dom->getElementsByTagName('row') as \$row) {
	\$vs = \$row->getElementsByTagName('v');
	\$row_v_counts[] = \$vs->length;
	\$row_last_v[] = trim(\$vs->item(\$vs->length - 1)->nodeValue);
}

echo json_encode(array(
	'cdp_counts'     => \$cdp_counts,
	'cdp_new_values' => \$cdp_new_values,
	'row_v_counts'   => \$row_v_counts,
	'row_last_v'     => \$row_last_v,
));
PHP);

	expect($res['cdp_counts'])->toBe(array(2, 2));
	expect($res['cdp_new_values'][0])->toBe(array('primary' => 'NaN', 'value' => 'NaN', 'unk' => '0'));
	expect($res['cdp_new_values'][1])->toBe(array('primary' => 'NaN', 'value' => 'NaN', 'unk' => '0'));
	expect($res['row_v_counts'])->toBe(array(2, 2, 2, 2));
	expect($res['row_last_v'])->toBe(array('NaN', 'NaN', 'NaN', 'NaN'));
});

test('rrdtool_cacti_compare emits file_rra_id in GROW and SHRINK resize commands', function () use ($repo_root) {
	$rrd_file = var_export($repo_root . '/lib/rrd.php', true);

	$res = run_isolated_php_json(<<<PHP
\$data_source_types = array(1 => 'GAUGE', 2 => 'COUNTER');
\$consolidation_functions = array(1 => 'AVERAGE', 3 => 'MAX');
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function read_config_option(\$n) { return ''; }
function clean_up_path(\$p) { return \$p; }
function get_data_source_path(\$id, \$expand = true) { return '/rra/test.rrd'; }
function __() { \$args = func_get_args(); \$fmt = array_shift(\$args); return count(\$args) ? vsprintf(\$fmt, \$args) : \$fmt; }
function db_fetch_row_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'FROM data_template_data') !== false) {
		return array('local_data_template_data_id' => 1, 'rrd_step' => 300, 'data_source_profile_id' => 1);
	}
	return array('host_id' => 1, 'snmp_query_id' => 0, 'snmp_index' => '');
}
function db_fetch_cell_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'SELECT heartbeat') !== false) {
		return 600;
	}
	return 0;
}
function db_fetch_assoc_prepared(\$sql, \$params = array()) {
	if (strpos(\$sql, 'FROM data_template_rrd') !== false) {
		return array(
			array('id' => 1, 'data_source_name' => 'traffic_in', 'data_source_type_id' => 2, 'rrd_heartbeat' => 600, 'rrd_maximum' => 'U', 'rrd_minimum' => '0')
		);
	}
	if (strpos(\$sql, 'FROM data_source_profiles') !== false) {
		return array(
			array('cf' => 1, 'xff' => 0.5, 'heartbeat' => 600, 'steps' => 1, 'rows' => 800),
			array('cf' => 3, 'xff' => 0.5, 'heartbeat' => 600, 'steps' => 6, 'rows' => 500),
		);
	}
	return array();
}
require_once {$rrd_file};

// RRD file has RRAs in a different order than Cacti profile:
// file_rra_id 3 matches cacti_rra_id 0 (AVERAGE steps=1, needs GROW 200)
// file_rra_id 2 matches cacti_rra_id 1 (MAX steps=6, needs SHRINK 200)
\$info = array(
	'filename' => '/rra/test.rrd',
	'step'     => 300,
	'ds'       => array(
		'traffic_in' => array('type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => '0', 'max' => 'U'),
	),
	'rra'      => array(
		2 => array('cf' => '"MAX"',     'pdp_per_row' => 6, 'xff' => 0.5, 'rows' => 700),
		3 => array('cf' => '"AVERAGE"', 'pdp_per_row' => 1, 'xff' => 0.5, 'rows' => 600),
	),
);

\$diff = rrdtool_cacti_compare(10, \$info);

echo json_encode(array('resize' => \$diff['resize']));
PHP);

	expect($res['resize'])->toBe(array(
		'/rra/test.rrd 3 GROW 200',
		'/rra/test.rrd 2 SHRINK 200',
	));
});

test('rrdtool_build_cdef_magic_rpn produces identical RPN for value and count modes and rrd.php avoids N+1 rrd_step queries', function () use ($repo_root) {
	$rrd_file = var_export($repo_root . '/lib/rrd.php', true);

	$res = run_isolated_php_json(<<<PHP
function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function read_config_option(\$n) { return ''; }
require_once {$rrd_file};

\$rpn_0     = rrdtool_build_cdef_magic_rpn(array(), 1000, false);
\$rpn_1     = rrdtool_build_cdef_magic_rpn(array('a'), 1000, false);
\$rpn_3     = rrdtool_build_cdef_magic_rpn(array('a', 'b', 'c'), 1000, false);
\$rpn_cnt_2 = rrdtool_build_cdef_magic_rpn(array('a', 'b'), 1000, true);

echo json_encode(array(
	'rpn_0'     => \$rpn_0,
	'rpn_1'     => \$rpn_1,
	'rpn_3'     => \$rpn_3,
	'rpn_cnt_2' => \$rpn_cnt_2,
));
PHP);

	expect($res['rpn_0'])->toBe('');
	expect($res['rpn_1'])->toBe('TIME,1000,GT,a,a,UN,0,a,IF,IF');
	expect($res['rpn_3'])->toBe('TIME,1000,GT,a,a,UN,0,a,IF,IF,TIME,1000,GT,b,b,UN,0,b,IF,IF,TIME,1000,GT,c,c,UN,0,c,IF,IF,+,+');
	expect($res['rpn_cnt_2'])->toBe('TIME,1000,GT,1,a,UN,0,1,IF,IF,TIME,1000,GT,1,b,UN,0,1,IF,IF,+');

	$rrd_source = file_get_contents($repo_root . '/lib/rrd.php');
	expect(substr_count($rrd_source, 'SELECT rrd_step FROM data_template_data WHERE local_data_id = ?'))->toBe(1);
});

/* -------------------------------------------------------------------------
 * 4. lib/spikekill.php: float/fill validation, numspike precedence, and
 *    removeComments() substr length
 * ------------------------------------------------------------------------- */

test('spikekill validates float/fill methods, rejects invalid numspike values, and extracts row timestamps accurately', function () use ($repo_root) {
	$spikekill_file = var_export($repo_root . '/lib/spikekill.php', true);

	$res = run_isolated_php_json(<<<PHP
\$tmp_rrd = tempnam(sys_get_temp_dir(), 'cacti_sk_');
touch(\$tmp_rrd);

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function __() { \$args = func_get_args(); \$fmt = array_shift(\$args); return count(\$args) ? vsprintf(\$fmt, \$args) : \$fmt; }
function __esc() { \$args = func_get_args(); \$fmt = array_shift(\$args); return count(\$args) ? vsprintf(\$fmt, \$args) : \$fmt; }
function get_execution_user() { return 'test'; }
function read_config_option(\$name, \$force = false) {
	\$defaults = array(
		'spikekill_method'     => 'stddev',
		'spikekill_number'     => 10,
		'spikekill_deviations' => 10,
		'spikekill_percent'    => 500,
		'spikekill_outliers'   => 5,
		'spikekill_avgnan'     => 'last',
	);
	return isset(\$defaults[\$name]) ? \$defaults[\$name] : '';
}
function read_user_setting(\$name, \$default = '', \$force = false) { return \$default; }

require_once {$spikekill_file};

\$init_method = new ReflectionMethod('spikekill', 'initialize_spikekill');

// 1. float method with invalid date triggers outlier-start/outlier-end validation
\$sk_float = new spikekill(\$tmp_rrd, 'float', 'last', 10, 'not-a-valid-date', '2026-01-02 00:00', 5, 500, 10);
\$sk_float->html = false;
\$init_method->invoke(\$sk_float);
\$err_float = \$sk_float->get_errors();

// 2. fill method with outliers = 0 triggers positive integer outliers error
\$sk_fill = new spikekill(\$tmp_rrd, 'fill', 'last', 10, 1700000000, 1700003600, 0, 500, 10);
\$sk_fill->html = false;
\$init_method->invoke(\$sk_fill);
\$err_fill = \$sk_fill->get_errors();

// 3. negative and non-numeric numspike are rejected (not bypassed by !\$this->numspike != '')
\$sk_neg = new spikekill(\$tmp_rrd, 'stddev', 'last', 10, '', '', 5, 500, -5);
\$sk_neg->html = false;
\$init_method->invoke(\$sk_neg);
\$err_neg = \$sk_neg->get_errors();

\$sk_abc = new spikekill(\$tmp_rrd, 'stddev', 'last', 10, '', '', 5, 500, 'abc');
\$sk_abc->html = false;
\$init_method->invoke(\$sk_abc);
\$err_abc = \$sk_abc->get_errors();

// 4. removeComments() extracts exact timestamp for both leading and trailing XML comments
\$rm_method = new ReflectionMethod('spikekill', 'removeComments');
\$lines = array(
	'<!-- 2026-11-27 18:00:00 PST / 1795831200 --><row><v>1.23e+02</v></row>',
	'<row><v>4.56e+02</v></row> <!-- 2026-11-27 18:05:00 PST / 1795831500 -->',
);
\$cleaned = \$rm_method->invokeArgs(\$sk_neg, array(&\$lines));

@unlink(\$tmp_rrd);

echo json_encode(array(
	'err_float' => \$err_float,
	'err_fill'  => \$err_fill,
	'err_neg'   => \$err_neg,
	'err_abc'   => \$err_abc,
	'cleaned'   => \$cleaned,
));
PHP);

	expect($res['err_float'])->toContain('outlier-start and outlier-end');
	expect($res['err_fill'])->toContain('number of outliers to exclude must be a positive integer');
	expect($res['err_neg'])->toContain('Number of spikes to remove must be a positive integer');
	expect($res['err_abc'])->toContain('Number of spikes to remove must be a positive integer');
	expect($res['cleaned'])->toBe(array(
		'<row><timestamp> 1795831200 </timestamp><v>1.23e+02</v></row>',
		'<row><timestamp> 1795831500 </timestamp><v>4.56e+02</v></row>',
	));
});

/* -------------------------------------------------------------------------
 * 5. lib/graph_variables.php: single-CF fetch, bandwidth summation dedup,
 *    $local_data_array initialization, and binary base-1024 scaling
 * ------------------------------------------------------------------------- */

test('rrdtool_function_stats fetches only the requested CF with fallback when missing', function () use ($repo_root) {
	$gv_file = var_export($repo_root . '/lib/graph_variables.php', true);

	$res = run_isolated_php_json(<<<PHP
\$config = array('library_path' => sys_get_temp_dir());
\$dummy_rrd = sys_get_temp_dir() . '/rrd.php';
if (!file_exists(\$dummy_rrd)) {
	file_put_contents(\$dummy_rrd, "<?php\n");
}

\$available_cfs = array('AVERAGE' => true, 'MAX' => true);
\$fetch_log = array();

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function rrdtool_function_contains_cf(\$ldi, \$cf) {
	global \$available_cfs;
	return !empty(\$available_cfs[\$cf]);
}
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0, \$show = false, \$pipe = null, \$cf = 'AVERAGE') {
	global \$fetch_log;
	\$fetch_log[] = array('ldi' => \$ldi, 'cf' => \$cf);
	\$val = (\$cf === 'MAX') ? 200.0 : 100.0;
	return array(
		'data_source_names' => array(0 => 'traffic_in'),
		'values'            => array(0 => array(1000 => \$val, 1300 => \$val, 1600 => \$val)),
	);
}

require_once {$gv_file};

// 1. AVERAGE requested when both CFs exist -> only 1 fetch (AVERAGE)
\$fetch_log = array();
\$avg_res = nth_percentile(array(10 => array('traffic_in')), -86400, 0, 95, 0, false);
\$avg_fetches = \$fetch_log;

// 2. MAX (peak) requested when both CFs exist -> only 1 fetch (MAX)
\$fetch_log = array();
\$max_res = nth_percentile(array(10 => array('traffic_in')), -86400, 0, 95, 0, true);
\$max_fetches = \$fetch_log;

// 3. MAX requested when RRD only contains AVERAGE -> falls back to AVERAGE
\$available_cfs = array('AVERAGE' => true, 'MAX' => false);
\$fetch_log = array();
\$fallback_res = nth_percentile(array(10 => array('traffic_in')), -86400, 0, 95, 0, true);
\$fallback_fetches = \$fetch_log;

@unlink(\$dummy_rrd);

echo json_encode(array(
	'avg_fetches'      => \$avg_fetches,
	'avg_val'          => \$avg_res['traffic_in'],
	'max_fetches'      => \$max_fetches,
	'max_val'          => \$max_res['traffic_in'],
	'fallback_fetches' => \$fallback_fetches,
	'fallback_val'     => \$fallback_res['traffic_in'],
));
PHP);

	expect($res['avg_fetches'])->toBe(array(array('ldi' => 10, 'cf' => 'AVERAGE')));
	expect($res['avg_val'])->toBe(100);
	expect($res['max_fetches'])->toBe(array(array('ldi' => 10, 'cf' => 'MAX')));
	expect($res['max_val'])->toBe(200);
	expect($res['fallback_fetches'])->toBe(array(array('ldi' => 10, 'cf' => 'AVERAGE')));
	expect($res['fallback_val'])->toBe(100);
});

test('variable_nth_percentile handles graphs without graphable items and scales base 1024 accurately', function () use ($repo_root) {
	$gv_file = var_export($repo_root . '/lib/graph_variables.php', true);

	$res = run_isolated_php_json(<<<PHP
\$config = array('library_path' => sys_get_temp_dir());
\$dummy_rrd = sys_get_temp_dir() . '/rrd.php';
if (!file_exists(\$dummy_rrd)) {
	file_put_contents(\$dummy_rrd, "<?php\n");
}

\$graph_item_types = array(
	4 => 'LINE1',
	7 => 'AREA',
	9 => 'GPRINT',
);

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function rrdtool_function_contains_cf(\$ldi, \$cf) { return true; }
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0, \$show = false, \$pipe = null, \$cf = 'AVERAGE') {
	// Return 1 GiB = 1073741824 bytes/sec so bytes scaling is exact
	return array(
		'data_source_names' => array(0 => 'mem_used'),
		'values'            => array(0 => array(1000 => 1073741824, 1300 => 1073741824, 1600 => 1073741824)),
	);
}

require_once {$gv_file};

// 1. Graph with only GPRINT (no graphable items) must not throw PHP 8 TypeError
\$graph_bin = array('base_value' => 1024);
\$item_empty = array('local_data_id' => 10, 'data_source_name' => 'mem_used');
\$items_non_graphable = array(
	array('local_data_id' => 10, 'data_template_rrd_id' => 1, 'data_source_name' => 'mem_used', 'graph_type_id' => 9),
);
\$match_curr = array('|95:bytes:6:current:2|', '95', 'bytes', '6', 'current', '2');
\$no_graphable_res = variable_nth_percentile(\$match_curr, \$graph_bin, \$item_empty, \$items_non_graphable, -86400, 0);

// 2. Binary base 1024 scaling: 1073741824 bytes = 1048576 KiB (power 3) = 1024 MiB (power 6) = 1 GiB (power 9)
\$items_graphable = array(
	array('local_data_id' => 10, 'data_template_rrd_id' => 1, 'data_source_name' => 'mem_used', 'graph_type_id' => 7),
);
\$m3 = array('|95:bytes:3:current:2|', '95', 'bytes', '3', 'current', '2');
\$m6 = array('|95:bytes:6:current:2|', '95', 'bytes', '6', 'current', '2');
\$m9 = array('|95:bytes:9:current:2|', '95', 'bytes', '9', 'current', '2');

\$kib = variable_nth_percentile(\$m3, \$graph_bin, \$item_empty, \$items_graphable, -86400, 0);
\$mib = variable_nth_percentile(\$m6, \$graph_bin, \$item_empty, \$items_graphable, -86400, 0);
\$gib = variable_nth_percentile(\$m9, \$graph_bin, \$item_empty, \$items_graphable, -86400, 0);

// 3. Decimal base 1000 scaling: 1073741824 / 1e6 = 1073.74
\$graph_dec = array('base_value' => 1000);
\$mb = variable_nth_percentile(\$m6, \$graph_dec, \$item_empty, \$items_graphable, -86400, 0);

@unlink(\$dummy_rrd);

echo json_encode(array(
	'no_graphable' => \$no_graphable_res,
	'kib'          => \$kib,
	'mib'          => \$mib,
	'gib'          => \$gib,
	'mb'           => \$mb,
));
PHP);

	expect((float) $res['no_graphable'])->toBe(0.0);
	expect((float) $res['kib'])->toBe(1048576.0);
	expect((float) $res['mib'])->toBe(1024.0);
	expect((float) $res['gib'])->toBe(1.0);
	expect((float) $res['mb'])->toBe(1073.74);
});

test('variable_bandwidth_summation deduplicates fetches and duplicate DS items and scales base 1024 accurately', function () use ($repo_root) {
	$gv_file = var_export($repo_root . '/lib/graph_variables.php', true);

	$res = run_isolated_php_json(<<<PHP
\$graph_item_types = array(
	4 => 'LINE1',
	7 => 'AREA',
);

\$fetch_count = 0;

function cacti_sizeof(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function cacti_count(\$x) { return is_array(\$x) ? count(\$x) : 0; }
function number_format_i18n(\$num, \$dec = 2) { return number_format((float) \$num, (int) \$dec, '.', ''); }
function rrdtool_function_fetch(\$ldi, \$start, \$end, \$res = 0) {
	global \$fetch_count;
	\$fetch_count++;
	// With rra_steps=1, ds_steps=1: traffic_in sums to 524288, traffic_out sums to 524288 -> total = 1048576 (1 MiB)
	return array(
		'data_source_names' => array(0 => 'traffic_in', 1 => 'traffic_out'),
		'values'            => array(
			0 => array(1000 => 524288),
			1 => array(1000 => 524288),
		),
	);
}

require_once {$gv_file};

\$graph_bin = array('base_value' => 1024);
\$graph_item = array('local_data_id' => 5, 'data_source_name' => 'traffic_in');
// Three graphable items all sharing local_data_id=5, including a duplicate AREA+LINE1 for traffic_in
\$graph_items = array(
	array('local_data_id' => 5, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in',  'graph_type_id' => 7),
	array('local_data_id' => 5, 'data_template_rrd_id' => 10, 'data_source_name' => 'traffic_in',  'graph_type_id' => 4),
	array('local_data_id' => 5, 'data_template_rrd_id' => 11, 'data_source_name' => 'traffic_out', 'graph_type_id' => 4),
);

\$match = array('|sum:6:total:2:auto|', '6', 'total', '2', 'auto');
\$result = trim(variable_bandwidth_summation(\$match, \$graph_bin, \$graph_item, \$graph_items, -86400, 0, 1, 1));

echo json_encode(array(
	'fetch_count' => \$fetch_count,
	'result'      => \$result,
));
PHP);

	expect($res['fetch_count'])->toBe(1);
	expect($res['result'])->toBe('1.00');
});
