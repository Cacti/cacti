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

function boostMetadataFetch($sql, $params = []) {
	$GLOBALS['boost_metadata_queries'][] = [$sql, $params];

	if ($GLOBALS['boost_metadata_fail_once'] ?? false) {
		$GLOBALS['boost_metadata_fail_once'] = false;

		return false;
	}

	if (strpos($sql, 'gti.task_item_id IS NULL') !== false) {
		return [['data_source_name' => 'unused']];
	}

	return [['data_name' => 'input', 'data_source_name' => 'rrd']];
}

function boostMetadataRekey($rows, $key, $value) {
	$result = [];

	foreach ($rows as $row) {
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
	$functions = str_replace([
		'boost_get_unused_data_source_names',
		'boost_get_input_field_names',
		'db_fetch_assoc_prepared',
		'array_rekey',
	], [
		'boostMetadataUnused',
		'boostMetadataFields',
		'boostMetadataFetch',
		'boostMetadataRekey',
	], $functions);

	eval($functions);
}

beforeEach(function () use ($root) {
	boostMetadataLoadFunctions($root);
	$GLOBALS['boost_metadata_queries'] = [];
});

test('unused data-source metadata is queried once per local data source', function () {
	expect(boostMetadataUnused(900001))->toBe(['unused' => 'unused'])
		->and(boostMetadataUnused(900001))->toBe(['unused' => 'unused'])
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(1);
});

test('templated and non-templated field maps use separate cached queries', function () {
	expect(boostMetadataFields(900002, true))->toBe(['input' => 'rrd'])
		->and(boostMetadataFields(900002, true))->toBe(['input' => 'rrd'])
		->and(boostMetadataFields(900002, false))->toBe(['input' => 'rrd'])
		->and(boostMetadataFields(900002, false))->toBe(['input' => 'rrd'])
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(2);
});

test('invalid local data-source IDs never reach metadata SQL', function () {
	expect(boostMetadataUnused(0))->toBe([])
		->and(boostMetadataFields(-1, true))->toBe([])
		->and($GLOBALS['boost_metadata_queries'])->toBe([]);
});

test('failed Boost metadata reads are retried rather than cached as empty', function ($kind, $id) {
	$GLOBALS['boost_metadata_fail_once'] = true;
	$read                                = $kind === 'unused' ? fn () => boostMetadataUnused($id) : fn () => boostMetadataFields($id, true);
	expect($read())->toBeFalse()->and($read())->toBeArray()->not->toBeEmpty()
		->and($GLOBALS['boost_metadata_queries'])->toHaveCount(2);
})->with([['unused', 900010], ['fields', 900011]]);

test('Boost source metadata remains bounded as workers visit new sources', function ($kind) {
	$name = $kind === 'unused' ? 'boostMetadataUnused' : 'boostMetadataFields';

	for ($id = 1000000; $id <= 1001000; $id++) {
		$name($id, true);
	}
	$cache = (new ReflectionFunction($name))->getStaticVariables()['cache'];
	expect(count($cache))->toBeLessThanOrEqual(1000);
	$before = count($GLOBALS['boost_metadata_queries']);
	$name(1000000, true);
	expect(count($GLOBALS['boost_metadata_queries']))->toBe($before + 1);
})->with(['unused', 'fields']);
