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

namespace DsstatsPollerOutputBatchingTest;

$GLOBALS['dsstats_prepared_queries'] = [];
$GLOBALS['dsstats_writes']           = [];
$GLOBALS['dsstats_logs']             = [];

function read_config_option(string $name) : mixed {
	return match ($name) {
		'dsstats_enable'  => 'on',
		'poller_interval' => 300,
		default           => false,
	};
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_strtolower(string $value) : string {
	return strtolower($value);
}

function dsstats_is_unknown_data(string $value) : bool {
	return $value == 'NULL' || $value == 'U' || strtolower($value) == 'nan';
}

function array_rekey(array $rows, string $key, string|array $values) : array {
	$result = [];

	foreach ($rows as $row) {
		if (is_array($values)) {
			$result[$row[$key]] = array_intersect_key($row, array_flip($values));
		} else {
			$result[$row[$key]] = $row[$values];
		}
	}

	return $result;
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	$GLOBALS['dsstats_prepared_queries'][] = [$sql, $params];

	if (str_contains($sql, 'FROM data_template_rrd AS dtr')) {
		return [
			['local_data_id' => 1, 'data_source_name' => "g'au", 'data_source_type_id' => 1, 'rrd_step' => 60, 'rrd_maximum' => 'U'],
			['local_data_id' => 2, 'data_source_name' => 'ctr', 'data_source_type_id' => 2, 'rrd_step' => 2, 'rrd_maximum' => 'U'],
			['local_data_id' => 3, 'data_source_name' => 'dctr', 'data_source_type_id' => 6, 'rrd_step' => 60, 'rrd_maximum' => 'U'],
		];
	}

	return [
		['local_data_id' => 2, 'rrd_name' => 'ctr', 'value' => 10],
		['local_data_id' => 3, 'rrd_name' => 'dctr', 'value' => 10],
	];
}

function db_fetch_assoc(string $sql) : array {
	return [];
}

function db_execute(string $sql) : bool {
	$GLOBALS['dsstats_writes'][] = $sql;

	return true;
}

function db_qstr(mixed $value) : string {
	return "'" . str_replace("'", "''", (string) $value) . "'";
}

function cacti_log(string $message, bool $print = false, string $tag = '') : void {
	$GLOBALS['dsstats_logs'][] = $message;
}

function set_error_handler(callable|string $callback) : mixed {
	return null;
}

function restore_error_handler() : bool {
	return true;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/dsstats.php for the poller-output batching test.');
}

if (preg_match('/function dsstats_poller_output\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_poller_output() for the batching test.');
}

$function = str_replace('function dsstats_poller_output(', 'function dsstats_poller_output_under_test(', $matches[0]);
eval('namespace DsstatsPollerOutputBatchingTest;' . $function);

test('poller output batches reads and keeps metadata and last values per data source', function () {
	$updates = [
		['local_data_id' => 1, 'times' => [1000 => ["g'au" => 5]]],
		['local_data_id' => 2, 'times' => [1000 => ['ctr' => 14], 1002 => ['ctr' => 18]]],
		['local_data_id' => 3, 'times' => [1000 => ['dctr' => 'U']]],
	];

	dsstats_poller_output_under_test($updates);

	expect($GLOBALS['dsstats_prepared_queries'])->toHaveCount(2)
		->and($GLOBALS['dsstats_prepared_queries'][0][1])->toBe([1, 2, 3])
		->and($GLOBALS['dsstats_prepared_queries'][1][1])->toBe([1, 2, 3])
		->and($GLOBALS['dsstats_writes'])->toHaveCount(2);

	$cache_write = $GLOBALS['dsstats_writes'][0];
	$last_write  = $GLOBALS['dsstats_writes'][1];

	expect($cache_write)
		->toContain("1, 'g''au'")
		->toContain("2, 'ctr', '1970-01-01 00:16:40', 2")
		->toContain("2, 'ctr', '1970-01-01 00:16:42', 2")
		->toContain("3, 'dctr', '1970-01-01 00:16:40', NULL")
		->and($last_write)
		->toContain("2, 'ctr', 14, 2")
		->toContain("2, 'ctr', 18, 2")
		->toContain("3, 'dctr', NULL, NULL");
});
