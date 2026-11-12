<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: a COUNTER that wraps from last to current has advanced by
 * 2^n - last + current, but the hourly rate added 2^n - 1.
 */

namespace DsstatsCounterWrapTest;

function read_config_option($name, $force = false) {
	return ($name == 'dsstats_enable' ? 'on' : 300);
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP') {
}

function dsstats_error_handler($errno, $errmsg, $filename, $linenum, $vars = array()) {
	return false;
}

function db_fetch_assoc($sql) {
	if (strpos($sql, 'data_source_type_id') !== false) {
		return array(
			array('data_source_name' => 'ctr32', 'data_source_type_id' => 2, 'rrd_step' => 100, 'rrd_maximum' => 'U'),
			array('data_source_name' => 'ctr64', 'data_source_type_id' => 2, 'rrd_step' => 1, 'rrd_maximum' => 'U'),
		);
	}

	return array();
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return $GLOBALS['dsstats_wrap_last'][$params[1]];
}

function db_execute($sql) {
	$GLOBALS['dsstats_wrap_writes'][] = $sql;

	return true;
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

if ($source === false || preg_match('/^function dsstats_poller_output\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_poller_output() for the counter wrap test.');
}

// the code is lib/dsstats.php from this checkout, with the error handler of this namespace
\cacti_fixture_require_code('namespace DsstatsCounterWrapTest;' . str_replace("set_error_handler('dsstats_error_handler')", "set_error_handler('DsstatsCounterWrapTest\\\\dsstats_error_handler')", $matches[0]));

test('a wrapped COUNTER adds 2^32 or 2^64 to the delta', function ($rrd_name, $last, $current, $rate) {
	$GLOBALS['dsstats_wrap_last']   = array($rrd_name => $last);
	$GLOBALS['dsstats_wrap_writes'] = array();

	$updates = array(array('local_data_id' => 7, 'times' => array(2000 => array($rrd_name => $current))));

	dsstats_poller_output($updates);

	expect($GLOBALS['dsstats_wrap_writes'][0])->toContain("'$rrd_name', '" . date('Y-m-d H:i:s', 2000) . "', $rate)");
})->with(array(
	// 2^32 - 4294967000 = 296, plus 704 after the wrap, over 100 seconds
	array('ctr32', '4294967000', '704', '10'),
	// 2^64 - 6144 is exact as a double, and the small delta must stay exact
	array('ctr64', '18446744073709545472', '1000', '7144'),
));
