<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

test('current rrdtool releases retain their own capability level', function () {
	$supported = [
		'1.9.0'  => 'RRDtool 1.9+',
		'1.10.0' => 'RRDtool 1.10+',
		'1.11.0' => 'RRDtool 1.11+',
	];

	expect(get_supported_rrdtool_version('1.11.0', $supported))->toBe('1.11.0')
		->and(get_supported_rrdtool_version('1.10.3', $supported))->toBe('1.10.0')
		->and(get_supported_rrdtool_version('1.9.0', $supported))->toBe('1.9.0');
});

test('future and unsupported releases map safely', function () {
	$supported = [
		'1.5.0'  => 'RRDtool 1.5+',
		'1.11.0' => 'RRDtool 1.11+',
	];

	expect(get_supported_rrdtool_version('1.12.0', $supported))->toBe('1.11.0')
		->and(get_supported_rrdtool_version('1.4.9', $supported))->toBeFalse();
});

test('the configured capability list includes current rrdtool releases', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/include/global_arrays.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain("'1.10.0' => 'RRDtool 1.10+'")
		->and($source)->toContain("'1.11.0' => 'RRDtool 1.11+'");
});
