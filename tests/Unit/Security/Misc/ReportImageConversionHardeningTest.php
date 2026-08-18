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

/**
 * png2jpeg()/png2gif() used to write each report graph to a predictable
 * /tmp/<timestamp>.png before decoding it with imagecreatefrompng(). Two
 * concurrent reports could collide on the same filename, and the shared,
 * predictable path was vulnerable to symlink races. The fix decodes the PNG
 * directly from memory with imagecreatefromstring() and drops the temp file
 * entirely.
 */

beforeEach(function () {
	require_once CACTI_PATH_LIBRARY . '/reports.php';
});

test('reports.php decodes PNG data in memory via imagecreatefromstring', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/reports.php');
	expect($src)->not->toBeFalse('lib/reports.php must be readable');

	expect($src)->toContain('imagecreatefromstring($png_data)');
});

test('png2jpeg no longer writes a predictable temp file', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/reports.php');
	expect($src)->not->toBeFalse('lib/reports.php must be readable');

	expect($src)->not->toContain("'/tmp/' . time() . '.png'")
		->and($src)->not->toMatch('/\bfopen\s*\(\s*\$fn\b/')
		->and($src)->not->toContain('imagecreatefrompng($fn)');
});

test('reports.php contains no fopen or predictable-path file writes', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/reports.php');
	expect($src)->not->toBeFalse('lib/reports.php must be readable');

	foreach (['png2jpeg', 'png2gif'] as $function) {
		$start = strpos($src, "function $function(");
		expect($start)->not->toBeFalse("$function() must exist");

		$end = strpos($src, "\nfunction ", $start + 1);
		expect($end)->not->toBeFalse("$function() must have a following function");

		$body = substr($src, $start, $end - $start);
		expect($body)->not->toMatch('/\bfopen\s*\(/')
			->and($body)->not->toMatch('/\bfile_put_contents\s*\(\s*[\'"]\/tmp\//');
	}
});

test('png2jpeg round-trips a minimal valid PNG without touching disk', function () {
	if (!extension_loaded('gd') || !function_exists('imagecreate')) {
		test()->markTestSkipped('GD is not available');
	}

	$im = imagecreate(1, 1);
	expect($im)->not->toBeFalse('GD must create the source image');
	imagecolorallocate($im, 255, 0, 0);
	ob_start();
	imagepng($im);
	$png = ob_get_clean();

	$jpeg = png2jpeg($png);

	expect($jpeg)->toBeString()
		->and($jpeg)->not->toBe('')
		->and(substr($jpeg, 0, 2))->toBe("\xFF\xD8");
});

test('png2gif round-trips a minimal valid PNG without touching disk', function () {
	if (!extension_loaded('gd') || !function_exists('imagecreate')) {
		test()->markTestSkipped('GD is not available');
	}

	$im = imagecreate(1, 1);
	expect($im)->not->toBeFalse('GD must create the source image');
	imagecolorallocate($im, 255, 0, 0);
	ob_start();
	imagepng($im);
	$png = ob_get_clean();

	$gif = png2gif($png);

	expect($gif)->toBeString()
		->and($gif)->not->toBe('')
		->and(substr($gif, 0, 6))->toMatch('/^GIF8[79]a/');
});
