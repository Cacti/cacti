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
 |
 | Tests for png2jpeg() and png2gif() in lib/reports.php.
 | Verifies image conversion works without explicit imagedestroy() (PHP 8.5).
 +-------------------------------------------------------------------------+
*/

$basePath = dirname(__DIR__, 2);

beforeEach(function () use ($basePath) {
	$config = [
		'base_path'         => $basePath,
		'cacti_server_os'   => str_starts_with(PHP_OS, 'WIN') ? 'win32' : 'unix',
	];
	$GLOBALS['config'] = $config;
	require_once $basePath . '/include/global_path.php';
	require_once $basePath . '/lib/functions.php';
	require_once $basePath . '/lib/reports.php';
});

// Create minimal 1x1 PNG using GD
function createMinimalPng() : string {
	$im = imagecreate(1, 1);
	if ($im === false) {
		throw new RuntimeException('imagecreate failed');
	}
	imagecolorallocate($im, 255, 0, 0);
	ob_start();
	imagepng($im);
	$png = ob_get_clean();

	return $png;
}

test('png2jpeg returns non-empty string for valid PNG', function () {
	$png = createMinimalPng();
	$jpeg = png2jpeg($png);

	expect($jpeg)->toBeString()
		->and($jpeg)->not->toBe('')
		->and(substr($jpeg, 0, 2))->toBe("\xFF\xD8");
});

test('png2jpeg returns empty string for empty input', function () {
	$jpeg = png2jpeg('');

	expect($jpeg)->toBe('')
		->and($jpeg)->toBeString();
});

test('png2gif returns non-empty string for valid PNG', function () {
	$png = createMinimalPng();
	$gif = png2gif($png);

	expect($gif)->toBeString()
		->and($gif)->not->toBe('')
		->and(substr($gif, 0, 6))->toMatch('/^GIF8[79]a/');
});

test('png2gif returns empty string for empty input', function () {
	$gif = png2gif('');

	expect($gif)->toBe('')
		->and($gif)->toBeString();
});
