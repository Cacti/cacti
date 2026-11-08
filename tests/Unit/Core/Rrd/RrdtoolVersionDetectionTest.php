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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

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
	require dirname(__DIR__, 4) . '/include/global_arrays.php';

	expect($rrdtool_versions['1.10.0'])->toBe('RRDtool 1.10+')
		->and($rrdtool_versions['1.11.0'])->toBe('RRDtool 1.11+');
});

test('typed callers retain the configured capability when detection fails', function () {
	$installer = file_get_contents(dirname(__DIR__, 4) . '/lib/installer.php');
	$boost     = file_get_contents(dirname(__DIR__, 4) . '/poller_boost.php');

	expect($installer)->not->toBeFalse()
		->and($installer)->toContain('if ($detected_version === false)')
		->toContain('$rrdver = get_rrdtool_version();')
		->and($boost)->not->toBeFalse()
		->and($boost)->toContain('if ($rrdtool_ins_version !== false &&')
		->toContain('retaining the configured capability level');
});

test('an unset rrdtool_version falls back to the lowest supported release', function () {
	// get_rrdtool_version() caches its answer, so read the fallback from a
	// fresh process that has no database and no stored option.
	$script = 'require ' . var_export(dirname(__DIR__, 3) . '/bootstrap-unit.php', true) . ';'
		. ' echo "\n", json_encode([get_rrdtool_version(), read_default_config_option(\'rrdtool_version\'), array_key_first($rrdtool_versions), $data_source_types]);';
	$output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null');

	expect($output)->toBeString();

	$lines                                = explode("\n", trim($output));
	[$version, $default, $lowest, $types] = json_decode(end($lines), true);

	expect($lowest)->toBe('1.5.0')
		->and($version)->toBe($lowest)
		->and($default)->toBe($lowest)
		->and($types)->toMatchArray([6 => 'DCOUNTER', 7 => 'DDERIVE']);
});
