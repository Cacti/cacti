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
 * Runs the unchanged png2jpeg() and png2gif() from lib/reports.php in their
 * own process, recording every file they open or remove, and prints the
 * converted image.
 */

namespace CactiReportImageConversionFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);

function fopen($filename, $mode) {
	$GLOBALS['files'][] = array('fopen', $filename);

	return \fopen($filename, $mode);
}

function imagecreatefrompng($filename) {
	$GLOBALS['files'][] = array('imagecreatefrompng', $filename);

	return \imagecreatefrompng($filename);
}

function unlink($filename) {
	$GLOBALS['files'][] = array('unlink', $filename);

	return \unlink($filename);
}

/* the two functions are declared with a space before the parenthesis, which
 * the shared source loader does not match */
$source = file_get_contents($root . '/lib/reports.php');

foreach (array('png2jpeg', 'png2gif') as $function) {
	if (!preg_match('/^function ' . $function . ' \(.*?^\}/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new \RuntimeException('Cannot locate source function ' . $function);
	}

	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');

	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", substr_count(substr($source, 0, $match[0][1]), "\n")) . $match[0][0]);

	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/lib/reports.php');

	try {
		require $temporary;
	} finally {
		\unlink($temporary);
	}
}

function report_image_conversion($function, $data) {
	$GLOBALS['files'] = array();

	if (!function_exists('imagecreatefromstring')) {
		return array('gd' => false);
	}

	if ($data === 'png') {
		$image = imagecreatetruecolor(40, 20);

		imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 200, 30, 30));

		ob_start();
		imagepng($image);

		$data = ob_get_clean();
	} else {
		$data = base64_decode($data);
	}

	$converted = call_user_func(__NAMESPACE__ . '\\' . $function, $data);
	$size      = getimagesizefromstring($converted);

	return array('gd' => true, 'files' => $GLOBALS['files'], 'size' => array($size[0], $size[1], $size[2]));
}

print json_encode(report_image_conversion(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
