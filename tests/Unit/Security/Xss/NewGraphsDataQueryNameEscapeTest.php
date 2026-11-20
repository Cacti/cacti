<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-h745-3r76-g253: the data-query name and field name reached the New
 * Graphs XML parse error messages unescaped. raise_message() now escapes a
 * custom message itself (GHSA-9737), so these call sites pass plain __() text
 * and must not go through raise_message_html().
 */

$root = dirname(__DIR__, 4);

test('New Graphs passes the name-bearing parse errors through the escaping raise_message', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');
	$lib    = file_get_contents($root . '/lib/functions.php');

	expect(substr_count($source, "__('Error Parsing Data Query Resource XML file"))->toBe(3)
		->and($source)->toContain("raise_message('xmlfielderr' . \$field_name, __(")
		->and($source)->not->toContain('raise_message_html(')
		->and($lib)->toMatch('/function raise_message\\(.*?\\$message = html_escape\\(/s');
});
