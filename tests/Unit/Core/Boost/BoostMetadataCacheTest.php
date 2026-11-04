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

$root = dirname(__DIR__, 4);

function boostMetadataSizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function boostMetadataFetch($sql, $params = array()) {
	$GLOBALS['boost_metadata_queries'][] = array($sql, $params);

	if (strpos($sql, 'SELECT DISTINCT dl.id AS local_data_id') !== false) {
		if (!empty($GLOBALS['boost_metadata_fail_prime'])) {
			return false;
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
	if (function_exists('boostMetadataPrime')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_reset_rrd_metadata_page_cache(');
	$end    = strpos($source, "\nfunction boost_get_rrd_filename_and_template(", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_reset_rrd_metadata_page_cache',
		'boost_prime_rrd_metadata_cache',
		'cacti_sizeof',
		'db_fetch_assoc_prepared',
	), array(
		'boostMetadataReset',
		'boostMetadataPrime',
		'boostMetadataSizeof',
		'boostMetadataFetch',
	), $function);

	eval($function);
}

function boostMetadataRekey($rows, $key, $value) {
	$result = array();

	foreach($rows as $row) {
		$result[$row[$key]] = $row[$value];
	}

	return $result;
}

function boostMetadataLoadFunctions($root) {
	if (function_exists('boostMetadataUnused')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_get_unused_data_source_names(');
	$end    = strpos($source, "\nfunction boost_process_poller_output(", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$functions = substr($source, $start, $end - $start);
	$functions = str_replace(array(
		'boost_get_unused_data_source_names',
		'boost_get_input_field_names',
		'db_fetch_assoc_prepared',
		'array_rekey',
	), array(
		'boostMetadataUnused',
		'boostMetadataFields',
		'boostMetadataFetch',
		'boostMetadataRekey',
	), $functions);

	eval($functions);
}

beforeEach(function () use ($root) {
	boostMetadataLoadFunctions($root);
	boostMetadataLoadPrimeFunction($root);
	$GLOBALS['boost_metadata_queries'] = array();
	$GLOBALS['boost_metadata_fail_prime'] = false;
	$GLOBALS['boost_rrd_metadata_cache'] = array();
	$GLOBALS['boost_unused_data_source_cache'] = array();
	$GLOBALS['boost_input_field_cache'] = array();
});

test('unused data-source metadata is queried once per local data source', function () {
	expect(boostMetadataUnused(900001))->toBe(array('unused' => 'unused'))
		->and(boostMetadataUnused(900001))->toBe(array('unused' => 'unused'))
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(1);
});

test('templated and non-templated field maps use separate cached queries', function () {
	expect(boostMetadataFields(900002, true))->toBe(array('input' => 'rrd'))
		->and(boostMetadataFields(900002, true))->toBe(array('input' => 'rrd'))
		->and(boostMetadataFields(900002, false))->toBe(array('input' => 'rrd'))
		->and(boostMetadataFields(900002, false))->toBe(array('input' => 'rrd'))
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(2);
});

test('invalid local data-source IDs never reach metadata SQL', function () {
	expect(boostMetadataUnused(0))->toBe(array())
		->and(boostMetadataFields(-1, true))->toBe(array())
		->and($GLOBALS['boost_metadata_queries'])->toBe(array());
});

test('RRD destination metadata is prefetched in bounded chunks and reused', function () {
	$ids = range(1, 1201);

	expect(boostMetadataPrime($ids, 500))->toBeTrue()
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3)
		->and(array_map('count', array_column($GLOBALS['boost_metadata_queries'], 1)))->toBe(array(500, 500, 201))
		->and($GLOBALS['boost_rrd_metadata_cache'])->toHaveCount(1201)
		->and($GLOBALS['boost_rrd_metadata_cache'][42])->toBe(array(
			'rrd_path'     => '/rra/42.rrd',
			'rrd_template' => 'value',
		))
		->and(boostMetadataUnused(42))->toBe(array())
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3);

	expect(boostMetadataPrime(array(1, 42, 1201), 500))->toBeTrue()
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(3);
});

test('page metadata caches are explicitly bounded by reset', function () {
	boostMetadataPrime(array(1, 2, 3), 500);
	boostMetadataFields(3, true);
	boostMetadataReset();

	expect($GLOBALS['boost_rrd_metadata_cache'])->toBe(array())
		->and($GLOBALS['boost_unused_data_source_cache'])->toBe(array())
		->and($GLOBALS['boost_input_field_cache'])->toBe(array());
});

test('a failed metadata prefetch is fatal and does not poison the cache', function () {
	$GLOBALS['boost_metadata_fail_prime'] = true;

	expect(boostMetadataPrime(array(7, 8), 500))->toBeFalse()
		->and($GLOBALS['boost_rrd_metadata_cache'])->toBe(array());
});
