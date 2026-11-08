<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for both Boost flush paths now that RRDtool 1.5 is the
// floor. boost_process_poller_output() (lib/boost.php) and
// boost_process_local_data_ids() (poller_boost.php) must hand every staged
// timestamp to rrdtool with --skip-past-updates, take no per-source GET_LOCK()
// and do no last-update lookup. Both functions run unmodified: lib/boost.php is
// loaded as is and the poller_boost.php functions are compiled at their
// original line numbers. Only the database and the RRDtool transport are
// replaced, with stand-ins that record what Boost asked for.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);

require_once $root . '/include/global_constants.php';

foreach (['cacti_sizeof', 'cacti_count', 'array_rekey', 'is_hexadecimal'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}

require_once $root . '/lib/boost.php';

foreach (['boost_archive_select_sql', 'boost_process_output', 'boost_process_local_data_ids'] as $function) {
	cacti_fixture_source_function($root . '/poller_boost.php', $function, '');
}

// Both flush paths load lib/rrd.php for the RRDtool transport. Point them at an
// empty library so the recording rrdtool_execute() below stays in charge.
$directory = sys_get_temp_dir() . '/cacti-boost-skip-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
file_put_contents($directory . '/rrd.php', "<?php\n");
define('CACTI_PATH_LIBRARY', $directory);

register_shutdown_function(static function () use ($directory) : void {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}

	rmdir($directory);
});

$rrd_path = $directory . '/ds_7.rrd';
touch($rrd_path);

$probe_config = [
	'boost_rrd_update_string_length'           => '2000',
	'boost_rrd_update_interval'                => '60',
	'boost_rrd_update_max_records_per_select'  => '50000',
	'storage_location'                         => '',
];

// Two poll rounds for one templated data source with two data source names.
$probe_rows = [
	['local_data_id' => 7, 'data_template_id' => 3, 'timestamp' => '1700000060', 'rrd_name' => 'in',  'output' => '1'],
	['local_data_id' => 7, 'data_template_id' => 3, 'timestamp' => '1700000060', 'rrd_name' => 'out', 'output' => '2'],
	['local_data_id' => 7, 'data_template_id' => 3, 'timestamp' => '1700000120', 'rrd_name' => 'in',  'output' => '3'],
	['local_data_id' => 7, 'data_template_id' => 3, 'timestamp' => '1700000120', 'rrd_name' => 'out', 'output' => '4'],
];

$probe_sql      = [];
$probe_commands = [];
$probe_log      = [];
$archive_table  = 'poller_output_boost_arch_1700000000';
$get_memory     = false;
$memory_used    = 0;
$debug          = false;
$boost_log      = '';
$boost_debug    = false;

/**
 * Answer the Boost queries this probe drives and record every statement.
 *
 * @param string $sql The statement Boost issued.
 *
 * @return mixed The canned result for the statement.
 */
function boost_probe_query(string $sql) : mixed {
	global $probe_sql, $probe_rows, $rrd_path, $archive_table;

	$probe_sql[] = preg_replace('/\s+/', ' ', trim($sql));

	if (str_contains($sql, 'SHOW tables')) {
		return [['Tables_in_cacti' => $archive_table]];
	}

	if (str_contains($sql, 'UNIX_TIMESTAMP(time) AS timestamp') || str_contains($sql, 'UNIX_TIMESTAMP(po.time) AS timestamp')) {
		return $probe_rows;
	}

	if (str_contains($sql, 'SELECT data_template_id')) {
		return 3;
	}

	if (str_contains($sql, 'SELECT DISTINCT data_source_name, rrd_name, rrd_path')) {
		return [
			['data_source_name' => 'in',  'rrd_name' => 'in',  'rrd_path' => $rrd_path],
			['data_source_name' => 'out', 'rrd_name' => 'out', 'rrd_path' => $rrd_path],
		];
	}

	if (str_starts_with(ltrim($sql), 'SELECT')) {
		return str_contains($sql, 'MIN(') || str_contains($sql, 'COUNT(') ? 0 : [];
	}

	return true;
}

/**
 * Stand-in for the scalar database read.
 *
 * @param string $sql The statement Boost issued.
 *
 * @return mixed The canned scalar.
 */
function db_fetch_cell(string $sql) : mixed {
	return boost_probe_query($sql);
}

/**
 * Stand-in for the prepared scalar database read.
 *
 * @param string $sql    The statement Boost issued.
 * @param array  $params The bound parameters.
 *
 * @return mixed The canned scalar.
 */
function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return boost_probe_query($sql);
}

/**
 * Stand-in for the row set database read.
 *
 * @param string $sql The statement Boost issued.
 *
 * @return mixed The canned rows.
 */
function db_fetch_assoc(string $sql) : mixed {
	return boost_probe_query($sql);
}

/**
 * Stand-in for the prepared row set database read.
 *
 * @param string $sql    The statement Boost issued.
 * @param array  $params The bound parameters.
 *
 * @return mixed The canned rows.
 */
function db_fetch_assoc_prepared(string $sql, array $params = []) : mixed {
	return boost_probe_query($sql);
}

/**
 * Stand-in for a database write.
 *
 * @param string $sql The statement Boost issued.
 *
 * @return bool Always acknowledged.
 */
function db_execute(string $sql) : bool {
	return boost_probe_query($sql) !== false;
}

/**
 * Stand-in for a prepared database write.
 *
 * @param string $sql    The statement Boost issued.
 * @param array  $params The bound parameters.
 * @param bool   $log    Whether a failure is logged.
 *
 * @return bool Always acknowledged.
 */
function db_execute_prepared(string $sql, array $params = [], bool $log = true) : bool {
	return boost_probe_query($sql) !== false;
}

/**
 * Stand-in for the affected row count of the last write.
 *
 * @return int No archive rows are forwarded in this probe.
 */
function db_affected_rows() : int {
	return 0;
}

/**
 * Stand-in for the settings read.
 *
 * @param string $name  The setting name.
 * @param bool   $force Ignored.
 *
 * @return mixed The probe setting or an empty string.
 */
function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['probe_config'][$name] ?? '';
}

/**
 * Stand-in for the settings write.
 *
 * @param string $name  The setting name.
 * @param mixed  $value The new value.
 *
 * @return void
 */
function set_config_option(string $name, mixed $value) : void {
	$GLOBALS['probe_config'][$name] = $value;
}

/**
 * Stand-in for the Cacti log.
 *
 * @param string $message The log line.
 * @param mixed  $output  Ignored.
 * @param string $area    Ignored.
 * @param int    $level   Ignored.
 *
 * @return void
 */
function cacti_log(string $message, mixed $output = false, string $area = 'CMDPHP', int $level = 0) : void {
	$GLOBALS['probe_log'][] = $message;
}

/**
 * Stand-in for the system time zone switch.
 *
 * @return void
 */
function cacti_system_zone_set() : void {
}

/**
 * Stand-in for shell argument quoting.
 *
 * @param string $string The argument.
 *
 * @return string The quoted argument.
 */
function cacti_escapeshellarg(string $string) : string {
	return "'" . str_replace("'", "'\\''", $string) . "'";
}

/**
 * Stand-in for the stored RRDtool version.
 *
 * @return string The supported floor or later.
 */
function get_rrdtool_version() : string {
	return '1.9';
}

/**
 * Stand-in for the installed RRDtool version.
 *
 * @return string The same version as the stored one.
 */
function get_installed_rrdtool_version() : string {
	return '1.9';
}

/**
 * Stand-in for the orphaned data source name lookup.
 *
 * @param int   $local_data_id The data source.
 * @param array $cache         The per-process cache.
 *
 * @return array No orphaned names.
 */
function poller_get_unused_data_source_names(int $local_data_id, array &$cache) : array {
	return [];
}

/**
 * Stand-in for the non-templated field name lookup.
 *
 * @param int   $local_data_id The data source.
 * @param array $cache         The per-process cache.
 *
 * @return array No non-templated names.
 */
function poller_get_nt_rrd_field_names(int $local_data_id, array &$cache) : array {
	return [];
}

/**
 * Record the RRDtool command Boost sends and acknowledge it.
 *
 * @param string $command     The RRDtool command line.
 * @param bool   $show_output Ignored.
 * @param int    $output_flag Ignored.
 * @param mixed  $pipe        Ignored.
 * @param string $logopt      Ignored.
 *
 * @return string An RRDtool acknowledgement.
 */
function rrdtool_execute(string $command, bool $show_output, int $output_flag, mixed $pipe = false, string $logopt = 'WEBLOG') : string {
	$GLOBALS['probe_commands'][] = $command;

	return 'OK u:0.00 s:0.00 r:0.00';
}

/**
 * Fail the probe with a message when a condition does not hold.
 *
 * @param bool   $condition The expectation.
 * @param string $message   What went wrong.
 *
 * @return void
 */
function boost_probe_assert(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/**
 * Check one flush: a single update with every timestamp and no lock or lookup.
 *
 * @param string $path     The flush path under test.
 * @param string $template The template the update must carry.
 *
 * @return void
 */
function boost_probe_expect_single_update(string $path, string $template) : void {
	global $probe_commands, $probe_sql, $rrd_path;

	$expected = 'update ' . cacti_escapeshellarg($rrd_path) . ' --skip-past-updates ' . $template . '1700000060:1:2 1700000120:3:4';

	// boost_process_poller_output() starts its value buffer with a separator, so compare tokens.
	$commands = array_map(static fn (string $command) : string => preg_replace('/\s+/', ' ', $command), $probe_commands);

	boost_probe_assert($commands === [$expected], $path . ' must send every staged sample in one skip-past update: ' . var_export($probe_commands, true));

	foreach ($probe_sql as $sql) {
		boost_probe_assert(!str_contains($sql, 'GET_LOCK') && !str_contains($sql, 'RELEASE_LOCK'), $path . ' must not take a per-source lock: ' . $sql);
	}
}

$probe_rrdtool_pipe = 'probe-pipe';
$passed             = [];

$probe_sql      = [];
$probe_commands = [];
boost_probe_assert(boost_process_poller_output(7, $probe_rrdtool_pipe) === 4, 'boost_process_poller_output() must report all four staged rows');
boost_probe_expect_single_update('boost_process_poller_output()', '--template in:out ');
boost_probe_assert(str_contains(end($probe_sql), 'DELETE FROM poller_output_boost'), 'boost_process_poller_output() must remove the acknowledged rows');
$passed[] = 'poller-output';

$probe_sql      = [];
$probe_commands = [];
boost_probe_assert(boost_process_local_data_ids(100, 1, $probe_rrdtool_pipe) === 4, 'boost_process_local_data_ids() must report all four staged rows');
boost_probe_expect_single_update('boost_process_local_data_ids()', '--template in:out ');
boost_probe_assert(str_contains(end($probe_sql), 'DELETE FROM poller_output_boost_local_data_ids'), 'boost_process_local_data_ids() must release the acknowledged shard');
$passed[] = 'local-data-ids';

// A data source without a template still skips past samples.
$probe_sql      = [];
$probe_commands = [];
$values         = '1700000060:1:2 1700000120:3:4';
boost_probe_assert(boost_rrdtool_function_update(7, $rrd_path, '', $values, $probe_rrdtool_pipe) === 'OK', 'An untemplated update must be acknowledged');
boost_probe_expect_single_update('boost_rrdtool_function_update()', '');
$passed[] = 'untemplated-update';

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
