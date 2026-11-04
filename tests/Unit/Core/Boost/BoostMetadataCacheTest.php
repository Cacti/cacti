<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace BoostMetadataCacheFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	$GLOBALS['boost_metadata_queries'][] = array($sql, $params);

	if (strpos($sql, 'SELECT DISTINCT dl.id AS local_data_id') !== false) {
		if (!empty($GLOBALS['boost_metadata_fail_prime'])) {
			return false;
		}

		if (is_array($GLOBALS['boost_metadata_prime_rows'])) {
			return $GLOBALS['boost_metadata_prime_rows'];
		}

		$rows = array();

		foreach($params as $local_data_id) {
			$rows[] = array(
				'local_data_id'   => $local_data_id,
				'data_template_id' => 1,
				'data_source_name' => 'value',
				'rrd_name'        => 'value',
				'rrd_path'        => '/rra/' . $local_data_id . '.rrd',
				'unused'          => 0,
			);
		}

		return $rows;
	}

	if (strpos($sql, 'gti.task_item_id IS NULL') !== false) {
		return array(array('data_source_name' => 'unused'));
	}

	return array(array('data_name' => 'input', 'data_source_name' => 'rrd'));
}

function boostMetadataLoadPrimeFunction($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_prime_rrd_metadata_cache')) {
		return;
	}

	foreach(array(
		'boost_reset_rrd_metadata_page_cache',
		'boost_prime_rrd_metadata_cache',
	) as $function) {
		\cacti_fixture_source_function($root . '/lib/boost.php', $function, __NAMESPACE__);
	}
}

function array_rekey($rows, $key, $value) {
	$result = array();

	foreach($rows as $row) {
		$result[$row[$key]] = $row[$value];
	}

	return $result;
}

function boostMetadataLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_get_unused_data_source_names')) {
		return;
	}

	foreach(array(
		'boost_get_unused_data_source_names',
		'boost_get_input_field_names',
	) as $function) {
		\cacti_fixture_source_function($root . '/lib/boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostMetadataLoadFunctions($root);
	boostMetadataLoadPrimeFunction($root);
	$GLOBALS['boost_metadata_queries'] = array();
	$GLOBALS['boost_metadata_fail_prime'] = false;
	$GLOBALS['boost_metadata_prime_rows'] = null;
	$GLOBALS['boost_rrd_metadata_cache'] = array();
	$GLOBALS['boost_unused_data_source_cache'] = array();
	$GLOBALS['boost_input_field_cache'] = array();
});

test('unused data-source metadata is queried once per local data source', function () {
	expect(boost_get_unused_data_source_names(900001))->toBe(array('unused' => 'unused'))
		->and(boost_get_unused_data_source_names(900001))->toBe(array('unused' => 'unused'))
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(1);
});

test('templated and non-templated field maps use separate cached queries', function () {
	expect(boost_get_input_field_names(900002, true))->toBe(array('input' => 'rrd'))
		->and(boost_get_input_field_names(900002, true))->toBe(array('input' => 'rrd'))
		->and(boost_get_input_field_names(900002, false))->toBe(array('input' => 'rrd'))
		->and(boost_get_input_field_names(900002, false))->toBe(array('input' => 'rrd'))
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(2);
});

test('invalid local data-source IDs never reach metadata SQL', function () {
	expect(boost_get_unused_data_source_names(0))->toBe(array())
		->and(boost_get_input_field_names(-1, true))->toBe(array())
		->and($GLOBALS['boost_metadata_queries'])->toBe(array());
});

test('RRD destination metadata is prefetched in bounded chunks and reused', function () {
	$ids = range(1, 1201);

	expect(boost_prime_rrd_metadata_cache($ids, 500))->toBeTrue()
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3)
		->and(array_map('count', array_column($GLOBALS['boost_metadata_queries'], 1)))->toBe(array(500, 500, 201))
		->and($GLOBALS['boost_rrd_metadata_cache'])->toHaveCount(1201)
		->and($GLOBALS['boost_rrd_metadata_cache'][42])->toBe(array(
			'rrd_path'     => '/rra/42.rrd',
			'rrd_template' => 'value',
		))
		->and(boost_get_unused_data_source_names(42))->toBe(array())
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3);

	expect(boost_prime_rrd_metadata_cache(array(1, 42, 1201), 500))->toBeTrue()
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3);
});

test('page metadata caches are explicitly bounded by reset', function () {
	boost_prime_rrd_metadata_cache(array(1, 2, 3), 500);
	boost_get_input_field_names(3, true);
	boost_reset_rrd_metadata_page_cache();

	expect($GLOBALS['boost_rrd_metadata_cache'])->toBe(array())
		->and($GLOBALS['boost_unused_data_source_cache'])->toBe(array())
		->and($GLOBALS['boost_input_field_cache'])->toBe(array());
});

test('a failed metadata prefetch is fatal and does not poison the cache', function () {
	$GLOBALS['boost_metadata_fail_prime'] = true;

	expect(boost_prime_rrd_metadata_cache(array(7, 8), 500))->toBeFalse()
		->and($GLOBALS['boost_rrd_metadata_cache'])->toBe(array());
});

test('metadata prefetch initializes missing caches and isolates unrelated and unused rows', function () {
	unset($GLOBALS['boost_rrd_metadata_cache']);
	$GLOBALS['boost_unused_data_source_cache'] = 'invalid';
	$GLOBALS['boost_metadata_prime_rows'] = array(
		array(
			'local_data_id' => 999, 'data_template_id' => 1, 'data_source_name' => 'other',
			'rrd_name' => 'other', 'rrd_path' => '/rra/other.rrd', 'unused' => 0,
		),
		array(
			'local_data_id' => 7, 'data_template_id' => 1, 'data_source_name' => 'unused',
			'rrd_name' => 'unused', 'rrd_path' => '/rra/7.rrd', 'unused' => 1,
		),
		array(
			'local_data_id' => 8, 'data_template_id' => 0, 'data_source_name' => 'value',
			'rrd_name' => '', 'rrd_path' => '/rra/8.rrd', 'unused' => 0,
		),
	);

	expect(boost_prime_rrd_metadata_cache(array(7, 8), 500))->toBeTrue()
		->and($GLOBALS['boost_rrd_metadata_cache'][7])->toBe(array('rrd_path' => '', 'rrd_template' => ''))
		->and($GLOBALS['boost_unused_data_source_cache'][7])->toBe(array('unused' => 'unused'))
		->and($GLOBALS['boost_rrd_metadata_cache'][8])->toBe(array(
			'rrd_path' => '/rra/8.rrd', 'rrd_template' => 'value',
		));
});
