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

require_once CACTI_PATH_TESTS . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';
require_once CACTI_PATH_LIBRARY . '/PackageListFilter.php';

beforeEach(function () {
	global $_CACTI_REQUEST;

	$_REQUEST       = [];
	$_GET           = [];
	$_POST          = [];
	$_CACTI_REQUEST = [];
});

test('package list filter uses default row count when rows are defaulted', function () {
	set_request_var('filter', 'alpha');
	set_request_var('rows', '-1');
	set_request_var('page', '2');
	set_request_var('sort_column', 'name');
	set_request_var('sort_direction', 'ASC');

	$filter = PackageListFilter::fromRequest(25);

	expect($filter->filter())->toBe('alpha')
		->and($filter->rows())->toBe(25)
		->and($filter->page())->toBe(2)
			->and($filter->offset())->toBe(25);
});

test('package list filter clamps non-positive rows to the default row count', function () {
	set_request_var('filter', 'alpha');
	set_request_var('rows', '0');
	set_request_var('page', '2');
	set_request_var('sort_column', 'name');
	set_request_var('sort_direction', 'ASC');

	$filter = PackageListFilter::fromRequest(25);

	expect($filter->rows())->toBe(25)
		->and($filter->offset())->toBe(25);
});

test('package list filter encodes pagination query values', function () {
	set_request_var('filter', 'a b&c');
	set_request_var('rows', '10');
	set_request_var('page', '1');
	set_request_var('sort_column', 'name');
	set_request_var('sort_direction', 'ASC');

	$filter = PackageListFilter::fromRequest(25);

	expect($filter->paginationUrl('package_repos.php'))->toBe('package_repos.php?filter=a%20b%26c');
});

test('package list filter extracts selected checkbox ids', function () {
	$selected = PackageListFilter::selectedIdsFromInput([
		'chk_12' => 'on',
		'chk_x'  => 'on',
		'other'  => 'on',
		'chk_34' => 'on',
	]);

	expect($selected)->toBe([12, 34]);
});

test('package list filter decodes only numeric selected ids', function () {
	$encoded = PackageListFilter::encodeSelectedIds([3, '7']);

	expect(PackageListFilter::decodeSelectedIds($encoded))->toBe([3, 7])
		->and(PackageListFilter::decodeSelectedIds('["3","bad"]'))->toBeFalse()
		->and(PackageListFilter::decodeSelectedIds('not json'))->toBeFalse();
});
