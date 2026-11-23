<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * A report converts a graph from PNG to JPEG or GIF in memory. The conversion
 * opens no file, so it leaves nothing with a predictable name in the shared
 * temporary directory.
 *
 * The probe runs the unchanged png2jpeg() and png2gif() from lib/reports.php
 * and records every file they open or remove.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function report_image_conversion(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/ReportImageConversionProbe.php', array(json_encode($arguments)));
}

test('a PNG graph is converted without opening a file', function ($function, $type) {
	$result = report_image_conversion($function, 'png');

	if (!$result['gd']) {
		$this->markTestSkipped('the gd extension is not loaded');
	}

	expect($result['files'])->toBe(array())
		->and($result['size'])->toBe(array(40, 20, $type));
})->with(array(
	'JPEG' => array('png2jpeg', IMAGETYPE_JPEG),
	'GIF'  => array('png2gif', IMAGETYPE_GIF),
));

test('data that is not a PNG gets the error image and opens no file', function ($function, $type, $data) {
	$result = report_image_conversion($function, base64_encode($data));

	if (!$result['gd']) {
		$this->markTestSkipped('the gd extension is not loaded');
	}

	expect($result['files'])->toBe(array())
		->and($result['size'])->toBe(array(150, 30, $type));
})->with(array(
	'JPEG from text' => array('png2jpeg', IMAGETYPE_JPEG, 'ERROR: no graph'),
	'GIF from text'  => array('png2gif', IMAGETYPE_GIF, 'ERROR: no graph'),
	'JPEG from GIF'  => array('png2jpeg', IMAGETYPE_JPEG, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
	'GIF from GIF'   => array('png2gif', IMAGETYPE_GIF, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
));
