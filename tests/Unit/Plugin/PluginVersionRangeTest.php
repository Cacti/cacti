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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

test('plugin version constraints preserve comparator boundaries (#7968)', function ($range, $version, $expected) {
	expect(plugin_valid_version_range($range, $version))->toBe($expected);
})->with([
	['1.2.0', '1.2.0', true],
	['1.2.0', '1.3.0', true],
	['1.2.0', '1.1.0', false],
	['>=1.2.0 <1.4.0', '1.3.0', true],
	['>=1.2.0 <1.4.0', '1.2.0', true],
	['>=1.2.0 <1.4.0', '1.4.0', false],
	['>1.2.0 <=1.4.0', '1.2.0', false],
	['>1.2.0 <=1.4.0', '1.4.0', true],
	['>1.2.0 <=1.4.0', '1.4.1', false],
	['=1.3.0 >=1.2.0', '1.3.0', true],
	['=1.3.0 >=1.2.0', '1.3.1', false],
	['<1.4.0', '1.3.0', true],
	['<=1.4.0', '1.4.0', true],
	['>1.2.0', '1.2.0', false],
	['>=1.2.0', '1.2.0', true],
	['=1.2.0', '1.2.1', false],
	["  >=1.2.0\t\t<1.4.0\n", '1.3.0', true],
	['>=1.4.0 <1.2.0', '1.3.0', false],
	['', '1.3.0', false],
	['>=1.2.0 garbage', '1.3.0', false],
	['!=1.2.0', '1.3.0', false],
	['>= 1.2.0', '1.3.0', false],
]);

test('plugin compatibility and INFO status agree on matching version ranges', function ($range, $compatible) {
	$name      = 'version_fixture_' . bin2hex(random_bytes(6));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	mkdir($directory);

	try {
		file_put_contents($directory . '/INFO', "[info]\nname = $name\ncompat = \"$range\"\n");
		$info = plugin_load_info_file($directory . '/INFO');
		expect($info['status'])->toBe($compatible ? 0 : -1);
		expect(plugin_is_compatible($name)['compat'])->toBe($compatible);
	} finally {
		unlink($directory . '/INFO');
		rmdir($directory);
	}
})->with([
	['>=1.0.0 <2.0.0', true],
	['1.0.0', true],
	['>=2.0.0 <3.0.0', false],
]);
