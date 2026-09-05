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

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return '';
	}
}

if (!function_exists('__')) {
	function __($text, ...$args) {
		return vsprintf($text, $args);
	}
}

require_once dirname(__DIR__, 2) . '/lib/rrd.php';
require_once dirname(__DIR__, 2) . '/lib/aggregate.php';

test('invalid graph CDEFs use visible output-specific failure responses', function () : void {
	$image = fn ($message) => 'IMAGE:' . $message;

	expect(rrdtool_invalid_cdef_response(array('export_csv' => true), 7, 42, $image))->toBeFalse()
		->and(rrdtool_invalid_cdef_response(array('get_error' => true), 7, 42, $image))->toBe('ERROR: Invalid CDEF 7 for graph 42.')
		->and(rrdtool_invalid_cdef_response(array('print_source' => true), 7, 42, $image))->toBe('ERROR: Invalid CDEF 7 for graph 42.')
		->and(rrdtool_invalid_cdef_response(array(), 7, 42, $image))->toBe('IMAGE:ERROR: Invalid CDEF 7 for graph 42.');
});

test('aggregate totalling rejects absent invalid and empty definitions', function () : void {
	$cdefs = array(
		1 => array('id' => 1, 'name' => 'Invalid', 'cdef_text' => null),
		2 => array('id' => 2, 'name' => 'Empty', 'cdef_text' => ''),
		3 => array('id' => 3, 'name' => 'Valid', 'cdef_text' => 'CURRENT_DATA_SOURCE,8,*'),
	);

	expect(aggregate_cdef_for_totalling($cdefs, 999))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 1))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 2))->toBeNull()
		->and(aggregate_cdef_for_totalling($cdefs, 3))->toBe('CURRENT_DATA_SOURCE,8,*');

	$items = array(
		array('id' => 10, 'cdef_id' => 3),
		array('id' => 11, 'cdef_id' => 1),
	);

	expect(aggregate_prepare_cdef_totalling($items, $cdefs))->toBe(array(
		'items'           => array(),
		'invalid_cdef_id' => 1,
	));
});
