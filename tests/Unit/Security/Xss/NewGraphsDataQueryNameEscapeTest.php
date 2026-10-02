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
 * Graphs XML parse error messages through __() (no escaping). Both
 * name-bearing messages (the xmlfielderr branch and the data-query-name
 * xmlerror branch) must use __esc(); the id-only xmlerror carries no user
 * string and stays on __().
 */

$root = dirname(__DIR__, 4);

test('New Graphs escapes the data-query and field names in every name-bearing parse error', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	// the two name-bearing messages escape; the id-only message does not
	expect(substr_count($source, "__esc('Error Parsing Data Query Resource XML file"))->toBe(2)
		->and(substr_count($source, "__('Error Parsing Data Query Resource XML file"))->toBe(1)
		->and($source)->toContain("raise_message('xmlfielderr' . \$field_name, __esc(")
		->and($source)->not->toContain("raise_message('xmlfielderr' . \$field_name, __(");
});
