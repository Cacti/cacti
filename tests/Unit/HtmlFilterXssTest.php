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

$htmlFilterSource = file_get_contents(dirname(__DIR__, 2) . '/lib/html_filter.php');

test('form_id is escaped with htmlspecialchars in create_filter', function () use ($htmlFilterSource) {
	expect(str_contains($htmlFilterSource, 'htmlspecialchars($this->form_id'))
		->toBeTrue();
});

test('form_action is escaped with htmlspecialchars in create_filter', function () use ($htmlFilterSource) {
	expect(str_contains($htmlFilterSource, 'htmlspecialchars($this->form_action'))
		->toBeTrue();
});

test('form_id escaping uses ENT_QUOTES and UTF-8', function () use ($htmlFilterSource) {
	expect(str_contains($htmlFilterSource, "htmlspecialchars(\$this->form_id, ENT_QUOTES, 'UTF-8')"))
		->toBeTrue();
});

test('form_action escaping uses ENT_QUOTES and UTF-8', function () use ($htmlFilterSource) {
	expect(str_contains($htmlFilterSource, "htmlspecialchars(\$this->form_action, ENT_QUOTES, 'UTF-8')"))
		->toBeTrue();
});

test('form_id is also escaped in create_javascript', function () use ($htmlFilterSource) {
	// The JS section also prints form_id for the jQuery selector
	$count = substr_count($htmlFilterSource, 'htmlspecialchars($this->form_id');
	expect($count)->toBeGreaterThanOrEqual(2);
});
