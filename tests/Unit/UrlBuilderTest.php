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

require_once __DIR__ . '/../../lib/functions.php';

test('html_url encodes query values using RFC 3986', function () {
	expect(html_url('graphs.php', ['action' => 'edit item', 'id' => 7]))
		->toBe('graphs.php?action=edit%20item&id=7');
});

test('html_url appends to an existing query string', function () {
	expect(html_url('graphs.php?action=list', ['page' => 2]))
		->toBe('graphs.php?action=list&page=2');
});

test('html_url leaves a page unchanged when there are no arguments', function () {
	expect(html_url('graphs.php'))->toBe('graphs.php');
});

test('redirect call sites use the shared local redirect helper', function () {
	foreach (['graph_templates.php', 'graphs.php', 'vdef.php'] as $page) {
		$source = file_get_contents(__DIR__ . '/../../' . $page);
		expect($source)->toContain('cacti_redirect(');
	}
});
