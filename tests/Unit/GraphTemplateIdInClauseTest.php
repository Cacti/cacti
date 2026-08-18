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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
// database.php must load before html.php so db_in_clause/db_qstr are the real
// implementations and html.php's dq_ branch resolves against them.
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/html.php';

it('parses a numeric cg_ id without coercion', function () {
	expect(html_transform_graph_template_ids('cg_5'))->toBe('5');
});

it('skips a non-numeric cg_ remainder instead of coercing it to 0', function () {
	expect(html_transform_graph_template_ids('cg_abc'))->toBe('');
});

it('skips an empty cg_ remainder instead of coercing it to 0', function () {
	expect(html_transform_graph_template_ids('cg_'))->toBe('');
});

it('keeps the valid cg_ id and drops the garbage one in a mixed list', function () {
	expect(html_transform_graph_template_ids('cg_5,cg_abc'))->toBe('5');
});

it('keeps a plain numeric id alongside a cg_ id', function () {
	expect(html_transform_graph_template_ids('7,cg_3'))->toBe('7,3');
});

it('produces IN (NULL), never IN (), for an empty template set', function () {
	expect(db_in_clause('gl.graph_template_id', ''))
		->toBe('(gl.graph_template_id IN (NULL))');
});

it('produces IN (NULL) when a transformed cg_ filter collapses to empty', function () {
	$graph_templates = html_transform_graph_template_ids('cg_abc');

	expect(db_in_clause('gl.graph_template_id', $graph_templates))
		->toBe('(gl.graph_template_id IN (NULL))');
});

it('builds a numeric IN clause for a valid template list', function () {
	expect(db_in_clause('gl.graph_template_id', '5,6'))
		->toBe('(gl.graph_template_id IN (5,6))');
});

it('preserves template id 0 as a real not-templated id', function () {
	expect(db_in_clause('gl.graph_template_id', 0))
		->toBe('(gl.graph_template_id IN (0))');
});
