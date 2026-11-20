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

require_once dirname(__DIR__, 3) . '/lib/database.php';
require_once dirname(__DIR__, 3) . '/lib/mib_cache.php';
require_once dirname(__DIR__, 3) . '/lib/mib_parser.php';
require_once dirname(__DIR__, 3) . '/lib/reports.php';
require_once dirname(__DIR__, 3) . '/lib/spikekill.php';

test('database connection options accept existing SSL files on the installed PDO driver', function () {
	$names = ['config', 'database_sessions', 'database_details', 'database_total_queries', 'database_persist'];
	$backup = [];
	foreach ($names as $name) {
		$backup[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
	}
	$file = tempnam(sys_get_temp_dir(), 'cacti-pdo-ssl-');
	try {
		$GLOBALS['config'] = [];
		$GLOBALS['database_sessions'] = [];
		// A closed loopback port exercises construction without an external database.
		$result = db_connect_real('127.0.0.1', 'fixture', '', 'fixture', 'mysql', '1', 0, true, $file, $file, $file);
		expect($result)->toBeFalse();
		expect($GLOBALS['config']['DATABASE_ERROR'])->toHaveCount(1);
	} finally {
		unlink($file);
		foreach ($backup as $name => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$name] = $value;
			} else {
				unset($GLOBALS[$name]);
			}
		}
	}
});

test('MIB tokenization preserves a quoted description as one token', function () {
	$parser = (new ReflectionClass(MibParser::class))->newInstanceWithoutConstructor();
	expect($parser->get_tokens('DESCRIPTION "sample description"'))->toBe(['DESCRIPTION', "sample description\r\n"]);
});

test('report image conversions preserve dimensions and output formats', function () {
	$image = imagecreatetruecolor(3, 2);
	ob_start();
	imagepng($image);
	$png = ob_get_clean();
	unset($image);
	foreach (['png2jpeg' => IMAGETYPE_JPEG, 'png2gif' => IMAGETYPE_GIF] as $convert => $type) {
		$dimensions = getimagesizefromstring($convert($png));
		expect($dimensions[0])->toBe(3)->and($dimensions[1])->toBe(2)->and($dimensions[2])->toBe($type);
	}
});

test('spike standard deviation retains numeric string precision', function () {
	$class = new ReflectionClass(spikekill::class);
	$spike = $class->newInstanceWithoutConstructor();
	$method = $class->getMethod('calculateStandardDeviation');
	expect($method->invoke($spike, ['2', '4', '4', '4', '5', '5', '7', '9']))->toBe(2.0);
	expect($method->invoke($spike, ['2']))->toBeFalse();
});
