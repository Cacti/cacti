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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace InstallerMalformedTemplateTest;

$root = dirname(__DIR__, 3);

require_once $root . '/tests/Helpers/SourceSlice.php';

foreach (['lib/xml.php' => ['xml2array', 'get_children'], 'lib/import.php' => ['import_xml_data', 'parse_xml_hash']] as $file => $functions) {
	foreach ($functions as $name) {
		cacti_fixture_source_function($root . '/' . $file, $name, __NAMESPACE__);
	}
}

test('malformed templates return failure before database work', function (string $xml) {
	$GLOBALS['config']['library_path'] = dirname(__DIR__, 3) . '/lib';
	$GLOBALS['import_messages']        = [];
	expect(import_xml_data($xml, false, 1))->toBeFalse();
})->with([
	'not xml',
	'<cacti/>',
	'<cacti><hash_100001' . str_repeat('a', 32) . '><name>partial</name>',
	'<cacti><invalid><name>bad hash</name></invalid></cacti>',
]);

test('well formed XML still returns the complete template tree', function () {
	expect(xml2array('<cacti><template><name>Example</name></template></cacti>'))
		->toBe(['template' => ['name' => 'Example']]);
});
