<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * replicate_table_to_poller() leaves excluded columns out of the
 * ON DUPLICATE KEY UPDATE clause so existing collector rows keep their own
 * value, but still inserts them so a new row does not depend on a column
 * default.  That matches the first replication to an empty table, which
 * copies every column.
 *
 * The function runs in a child process against database stubs that record
 * the generated SQL, so the stubs do not leak into the rest of the suite.
 */

function _replicate_sql(int $remote_rows) : string {
	$stub = <<<'PHP'
<?php
define('POLLER_VERBOSITY_MEDIUM', 3);

$GLOBALS['remote_rows'] = (int) $argv[2];
$GLOBALS['sql']         = [];

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
}

function db_fetch_row($sql, $log = true, $db_conn = false) {
	return ['Variable_name' => 'max_allowed_packet', 'Value' => 16777216];
}

function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
	return $GLOBALS['remote_rows'];
}

function db_column_exists($table, $column, $log = true, $db_conn = false) {
	return true;
}

function db_qstr($value, $db_conn = false) {
	return "'" . addslashes((string) $value) . "'";
}

function db_execute($sql, $log = true, $db_conn = false) {
	$GLOBALS['sql'][] = $sql;

	return true;
}

function db_affected_rows($db_conn = false) {
	return 1;
}

require $argv[1];

$data = [['host_id' => 1, 'data_query_id' => 2, 'assert_value' => 'main', 'arg1' => '.1.3']];

replicate_table_to_poller(false, $data, 'poller_reindex', ['assert_value']);

print implode("\n", $GLOBALS['sql']);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_rp_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/poller.php') . ' ' .
		escapeshellarg((string) $remote_rows) . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'replication must not error: ' . implode("\n", $output));

	return implode("\n", $output);
}

test('an excluded column is inserted but left out of the update', function () {
	$sql = _replicate_sql(5);

	expect($sql)->toStartWith('INSERT INTO poller_reindex (host_id, data_query_id, assert_value, arg1) VALUES ')
		->and($sql)->toContain("('1', '2', 'main', '.1.3')")
		->and($sql)->toEndWith(' ON DUPLICATE KEY UPDATE host_id=VALUES(host_id), data_query_id=VALUES(data_query_id), arg1=VALUES(arg1)');
});

test('an empty collector table receives and updates every column', function () {
	$sql = _replicate_sql(0);

	expect($sql)->toStartWith('INSERT INTO poller_reindex (host_id, data_query_id, assert_value, arg1) VALUES ')
		->and($sql)->toContain('assert_value=VALUES(assert_value)');
});
