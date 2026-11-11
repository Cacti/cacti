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
 * Each theme adds a search icon after the filter inputs on every page render
 * and must skip an input that already has one. The icon is inserted as the
 * input's next sibling, so a guard on a child of the input never matches and
 * a further icon is added each time.
 */

$themes = glob(dirname(__DIR__, 4) . '/include/themes/*/main.js');

test('every theme guards the filter icon on the next sibling', function (string $file) {
	$source = file_get_contents($file);

	if (!str_contains($source, "<i class='ti ti-search filter'/>")) {
		expect(true)->toBeTrue();

		return;
	}

	expect($source)->not->toMatch('/input\[id="[a-z]+"\] > i\[/');

	foreach (['filter', 'filterd', 'rfilter'] as $id) {
		expect($source)->toContain("\$('input[id=\"$id\"]').next('i[class=\"ti ti-search filter\"]').length < 1");
	}
})->with(array_combine(array_map(fn ($f) => basename(dirname($f)), $themes), array_map(fn ($f) => [$f], $themes)));
