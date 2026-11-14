<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: dsstats_write_buffer() added the whole buffer length after
 * every row, so the counter grew quadratically and the bulk inserts were
 * flushed long before they approached the packet size.
 */

namespace DsstatsWriteBufferTest;

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function db_execute($sql) {
	$GLOBALS['dsstats_buffer_writes'][] = $sql;

	return true;
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

// the code is lib/dsstats.php from this checkout
\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/dsstats.php', 'dsstats_write_buffer', __NAMESPACE__);

test('40000 statistics are written in batches close to the packet size', function () {
	$GLOBALS['dsstats_buffer_writes'] = array();

	$stats = array();

	for ($local_data_id = 1; $local_data_id <= 20000; $local_data_id++) {
		$stats[$local_data_id] = array(
			'traffic_in'  => array('AVG' => '1234.567890', 'MAX' => '9876.543210'),
			'traffic_out' => array('AVG' => '1234.567890', 'MAX' => '9876.543210'),
		);
	}

	dsstats_write_buffer($stats, 'daily');

	$writes = $GLOBALS['dsstats_buffer_writes'];
	$bytes  = array_sum(array_map('strlen', $writes));
	$rows   = array_sum(array_map(function ($sql) {
		return substr_count($sql, "','traffic_");
	}, $writes));

	expect($rows)->toBe(40000)
		->and(count($writes))->toBeLessThanOrEqual((int) ceil($bytes / 264000) + 1)
		->and(min(array_map('strlen', array_slice($writes, 0, -1))))->toBeGreaterThan(200000);
});
