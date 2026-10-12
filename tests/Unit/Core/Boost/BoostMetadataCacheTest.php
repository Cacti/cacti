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

$root = dirname(__DIR__, 4);

function boostMetadataFetch($sql, $params = array()) {
	if (strpos($sql, 'SELECT value FROM settings') !== false) return array(array('value' => $GLOBALS['boost_metadata_generation'] ?? 'probe'));
	$GLOBALS['boost_metadata_queries'][] = array($sql, $params);

	if (strpos($sql, 'gti.task_item_id IS NULL') !== false) {
		return array(array('data_source_name' => 'unused'));
	}

	return array(array('data_name' => 'input', 'data_source_name' => 'rrd'));
}

function boostMetadataFetchAll($sql) {
	$GLOBALS['boost_metadata_queries'][] = array($sql, array());

	return $GLOBALS['boost_metadata_rows'] ?? array(array('local_data_id' => 900001, 'data_source_name' => 'unused'));
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
		'boost_load_unused_data_source_names',
		'boost_get_unused_data_source_names',
		'boost_get_input_field_names',
		'db_fetch_assoc_prepared',
		'db_fetch_assoc',
		'array_rekey',
	), array(
		'boostMetadataLoadUnused',
		'boostMetadataUnused',
		'boostMetadataFields',
		'boostMetadataFetch',
		'boostMetadataFetchAll',
		'boostMetadataRekey',
	), $functions);

	eval($functions);
}

beforeEach(function () use ($root) {
	boostMetadataLoadFunctions($root);
	$GLOBALS['boost_metadata_queries'] = array();
});

test('unused data-source metadata is loaded once for the whole process', function () {
	expect(boostMetadataUnused(900001))->toBe(array('unused' => 'unused'))
		->and(boostMetadataUnused(900001))->toBe(array('unused' => 'unused'))
		->and(boostMetadataUnused(900002))->toBe(array())
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

test('a changed generation reloads exclusions instead of retaining a stale orphan', function () {
	$GLOBALS['boost_metadata_generation'] = 'before';
	$GLOBALS['boost_metadata_rows'] = array(array('local_data_id' => 900001, 'data_source_name' => 'unused'));
	expect(boostMetadataLoadUnused(true))->toBe(array(900001 => array('unused' => 'unused')));
	$GLOBALS['boost_metadata_generation'] = 'after';
	$GLOBALS['boost_metadata_rows'] = array();
	expect(boostMetadataLoadUnused(true))->toBe(array())
		->and(boostMetadataUnused(900001))->toBe(array());
});

test('an unpublished rebuild defers output and can recover on the next page', function () {
	$GLOBALS['boost_metadata_generation'] = '';
	expect(boostMetadataLoadUnused(true))->toBeFalse();
	$GLOBALS['boost_metadata_generation'] = 'published';
	expect(boostMetadataLoadUnused(true))->toBe(array());
});
