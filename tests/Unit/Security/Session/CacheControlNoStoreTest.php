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
 * include/global.php sent two Cache-Control headers in a row:
 *
 *   header('Cache-Control: no-store, no-cache, must-revalidate');
 *   header('Cache-Control: max-age=31536000');
 *
 * header() replaces by default, so only the second one reached the browser and
 * every authenticated page advertised itself as cacheable for a year, three
 * lines below a comment saying we do not want these pages cached. The max-age
 * arrived with a Lighthouse performance change (ced9f430f4) that was aimed at
 * static content but landed on the path every page takes.
 */

$globalSource = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');

test('the page headers keep no-store', function () use ($globalSource) {
	expect($globalSource)->toContain("header('Cache-Control: no-store, no-cache, must-revalidate');");
});

test('no second Cache-Control header overrides it', function () use ($globalSource) {
	expect(substr_count($globalSource, 'Cache-Control:'))->toBe(1);
	expect($globalSource)->not->toContain('max-age=31536000');
});

test('the surviving directive forbids storing the response', function () use ($globalSource) {
	preg_match_all("/header\('Cache-Control: ([^']+)'\);/", $globalSource, $matches);

	expect($matches[1])->toHaveCount(1);
	expect($matches[1][0])->toContain('no-store');
});
