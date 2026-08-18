<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$source = file_get_contents(CACTI_PATH_LIBRARY . '/dsstats.php');

if ($source === false) {
	throw new RuntimeException('Unable to read lib/dsstats.php for query efficiency tests.');
}

test('poller output batches metadata and last-value reads by local data id', function () use ($source) {
	expect($source)
		->toContain('WHERE dtr.local_data_id IN ($placeholders)')
		->toContain('WHERE local_data_id IN ($placeholders)')
		->toContain("\$ds_types[(int) \$row['local_data_id']][\$row['data_source_name']]")
		->toContain("\$last_values[(int) \$row['local_data_id']][\$row['rrd_name']]")
		->not->toContain("db_fetch_cell_prepared('SELECT SQL_NO_CACHE `value`")
		->not->toContain("db_fetch_cell_prepared('SELECT rrd_step\n")
		->not->toContain("db_fetch_cell_prepared('SELECT data_source_type_id\n");
});

test('poller output preserves sequential last values and quotes string tuples', function () use ($source) {
	expect($source)
		->toContain("\$last_values[(int) \$result['local_data_id']][\$result['rrd_name']] = \$lastval;")
		->toContain("db_qstr(\$result['rrd_name'])")
		->toContain("db_qstr(\$result['time'])")
		->toContain('$out_length = strlen($cachebuf);')
		->toContain('$last_length = strlen($lastbuf);')
		->not->toContain('$out_length += strlen($cachebuf);')
		->not->toContain('$last_length += strlen($lastbuf);');
});

test('unknown decimal counters are not passed to round', function () use ($source) {
	expect($source)
		->toContain("\$ds_type == 6 && \$lastval != 'NULL'")
		->toContain("\$ds_type == 7 && \$lastval != 'NULL'");
});
