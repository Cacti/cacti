<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 */

require_once CACTI_PATH_TESTS . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

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
	$rrdtool_versions = [];
	require CACTI_PATH_INCLUDE . '/global_arrays.php';

	expect($rrdtool_versions['1.10.0'])->toBe('RRDtool 1.10+')
		->and($rrdtool_versions['1.11.0'])->toBe('RRDtool 1.11+');
});

test('typed callers retain the configured capability when detection fails', function () {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');
	$boost     = file_get_contents(CACTI_PATH_BASE . '/poller_boost.php');

	expect($installer)->not->toBeFalse()
		->and($installer)->toContain('if ($detected_version === false)')
		->toContain('$rrdver = get_rrdtool_version();')
		->and($boost)->not->toBeFalse()
		->and($boost)->toContain('if ($rrdtool_ins_version !== false &&')
		->toContain('retaining the configured capability level');
});
