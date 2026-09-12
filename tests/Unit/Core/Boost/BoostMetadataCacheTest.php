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

function boostMetadataFetch($sql, $params = array()) {
	$GLOBALS['boost_metadata_queries'][] = array($sql, $params);

	if (strpos($sql, 'gti.task_item_id IS NULL') !== false) {
		return array(array('data_source_name' => 'unused'));
	}

	return array(array('data_name' => 'input', 'data_source_name' => 'rrd'));
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
	$end    = strpos($source, "\n/**\n * boost_process_poller_output", $start);

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
	$GLOBALS['boost_metadata_queries'] = array();
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
