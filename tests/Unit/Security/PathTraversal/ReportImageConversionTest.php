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
 * png2jpeg() and png2gif() are the unchanged functions from lib/reports.php,
 * loaded into this namespace so any file they open or remove is recorded
 * here.
 */

namespace ReportImageConversionTest;

function fopen($filename, $mode) {
	$GLOBALS['report_image_files'][] = array('fopen', $filename);

	return \fopen($filename, $mode);
}

function imagecreatefrompng($filename) {
	$GLOBALS['report_image_files'][] = array('imagecreatefrompng', $filename);

	return \imagecreatefrompng($filename);
}

function unlink($filename) {
	$GLOBALS['report_image_files'][] = array('unlink', $filename);

	return \unlink($filename);
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/reports.php');

foreach (array('png2jpeg', 'png2gif') as $function) {
	if (!preg_match('/^function ' . $function . ' \(.*?^\}/ms', $source, $match)) {
		throw new \RuntimeException('Cannot locate source function ' . $function);
	}

	// only the checked-in function body is evaluated
	eval('namespace ' . __NAMESPACE__ . '; ' . $match[0]);
}

function report_image_png() {
	$image = imagecreatetruecolor(40, 20);

	imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 200, 30, 30));

	ob_start();
	imagepng($image);

	return ob_get_clean();
}

beforeEach(function () {
	if (!function_exists('imagecreatefromstring')) {
		$this->markTestSkipped('the gd extension is not loaded');
	}

	$GLOBALS['report_image_files'] = array();
});

test('a PNG graph is converted without opening a file', function ($function, $signature, $type) {
	$converted = $function(report_image_png());

	expect($GLOBALS['report_image_files'])->toBe(array())
		->and(substr($converted, 0, strlen($signature)))->toBe($signature);

	$size = getimagesizefromstring($converted);

	expect(array($size[0], $size[1], $size[2]))->toBe(array(40, 20, $type));
})->with(array(
	'JPEG' => array(__NAMESPACE__ . '\png2jpeg', "\xff\xd8\xff", IMAGETYPE_JPEG),
	'GIF'  => array(__NAMESPACE__ . '\png2gif', 'GIF8', IMAGETYPE_GIF),
));

test('data that is not a PNG gets the error image and opens no file', function ($function, $type, $data) {
	$converted = $function($data);
	$size      = getimagesizefromstring($converted);

	expect($GLOBALS['report_image_files'])->toBe(array())
		->and(array($size[0], $size[1], $size[2]))->toBe(array(150, 30, $type));
})->with(array(
	'JPEG from text' => array(__NAMESPACE__ . '\png2jpeg', IMAGETYPE_JPEG, 'ERROR: no graph'),
	'GIF from text'  => array(__NAMESPACE__ . '\png2gif', IMAGETYPE_GIF, 'ERROR: no graph'),
	'JPEG from GIF'  => array(__NAMESPACE__ . '\png2jpeg', IMAGETYPE_JPEG, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
	'GIF from GIF'   => array(__NAMESPACE__ . '\png2gif', IMAGETYPE_GIF, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
));
