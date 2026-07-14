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

require_once dirname(__DIR__, 2) . '/lib/AggregateGraphItemsFilter.php';

test('aggregate graph items filter exposes typed request state', function () {
	$filter = new AggregateGraphItemsFilter(
		42,
		-1,
		50,
		3,
		'router',
		'true',
		'title_cache',
		'ASC',
		'1,2,3'
	);

	expect($filter->aggregateGraphLocalId())->toBe(42)
		->and($filter->rowSelection())->toBe(-1)
		->and($filter->rowsPerPage())->toBe(50)
		->and($filter->page())->toBe(3)
		->and($filter->rfilter())->toBe('router')
		->and($filter->sortColumn())->toBe('title_cache')
		->and($filter->sortDirection())->toBe('ASC')
		->and($filter->localGraphIds())->toBe('1,2,3')
		->and($filter->hasRfilter())->toBeTrue()
		->and($filter->hasLocalGraphIds())->toBeTrue();
});

test('aggregate graph items filter reports matching and custom graph list state', function () {
	$filter = new AggregateGraphItemsFilter(42, 20, 20, 1, '', 'false', 'title_cache', 'ASC', '');

	expect($filter->matchingOnly())->toBeFalse()
		->and($filter->matchingChecked())->toBeFalse()
		->and($filter->hasRfilter())->toBeFalse()
		->and($filter->hasLocalGraphIds())->toBeFalse();
});

test('aggregate graph items filter normalizes matching checkbox state', function () {
	$filter = new AggregateGraphItemsFilter(42, 20, 20, 1, 'router.*', 'on', 'title_cache', 'ASC', '9');

	expect($filter->matchingOnly())->toBeTrue()
		->and($filter->matchingChecked())->toBeTrue()
		->and($filter->hasLocalGraphIds())->toBeTrue();
});

// Search text is reflected into a value attribute; must be HTML-escaped.
test('aggregate items rfilter input escapes attribute context', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/aggregate_graphs.php');
	expect($src)->toContain("html_escape(\$filter->rfilter())");
	expect($src)->not->toMatch("/value='<\?php print \\\$filter->rfilter\(\); \?>'/");
});
