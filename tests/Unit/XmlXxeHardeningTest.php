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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/xml.php';

test('xml2array uses libxml_disable_entity_loader', function () {
	$xmlPath = __DIR__ . '/../../lib/xml.php';
	$contents = file_get_contents($xmlPath);

	expect($contents)->toContain('libxml_disable_entity_loader(true)');
});

test('xml2array handles basic XML correctly', function () {
	$xml = '<cacti><hash_00>value</hash_00></cacti>';
	$result = xml2array($xml);

	expect($result)->toBeArray();
	expect($result['hash_00'])->toBe('value');
});
