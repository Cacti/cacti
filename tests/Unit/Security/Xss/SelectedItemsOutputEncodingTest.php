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
 * The export and download branches of form_actions() hand a URL to
 * DownloadStart() inside an inline <script>. The URL must be built from the
 * validated selection and encoded as a JavaScript literal, never copied from
 * the request. form_continue_confirmation() writes the serialized selection
 * into a hidden input, which must survive a browser round trip intact.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$exportPages = [
	'automation_graph_rules',
	'automation_networks',
	'automation_snmp',
	'automation_templates',
	'automation_tree_rules',
	'data_source_profiles',
];

$downloadPages = [
	'host_templates'         => 'templates',
	'host_template_archives' => 'archives',
];

function selected_items_export_probe(string $page, string $payload) : array {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/SelectedItemsExportProbe.php', [$page, $payload]);
}

function expect_inert_js_literal(string $literal) : void {
	expect($literal)->toStartWith('"')
		->and($literal)->toEndWith('"')
		->and(substr_count($literal, '"'))->toBe(2)
		->and($literal)->not->toContain("'")
		->and($literal)->not->toContain('<')
		->and($literal)->not->toContain('&')
		->and($literal)->not->toContain("\n");
}

test('export URL round-trips a normal selection', function (string $page) : void {
	$verdict = selected_items_export_probe($page, 'list');

	expect_inert_js_literal($verdict['literal']);
	expect($verdict['path'])->toBe($page . '.php')
		->and($verdict['query'])->toBe(['action' => 'export', 'selected_items' => serialize(['3', '7'])])
		->and($verdict['round_trip'])->toBe(['3', '7']);
})->with($exportPages);

test('export URL drops bytes trailing the serialized selection', function (string $page) : void {
	$verdict = selected_items_export_probe($page, 'trailing');

	expect_inert_js_literal($verdict['literal']);
	expect($verdict['url'])->not->toContain('</script>')
		->and($verdict['query'])->toBe(['action' => 'export', 'selected_items' => serialize(['3', '7'])])
		->and($verdict['round_trip'])->toBe(['3', '7']);
})->with($exportPages);

test('export URL encodes line breaks inside selected values', function (string $page) : void {
	$verdict = selected_items_export_probe($page, 'whitespace');

	expect_inert_js_literal($verdict['literal']);
	expect($verdict['query']['selected_items'])->toBe(serialize(["\n3", '7 ']));
})->with($exportPages);

test('template download URL carries only the validated ids', function (string $page, string $type) : void {
	foreach (['list', 'trailing'] as $payload) {
		$verdict = selected_items_export_probe($page, $payload);

		expect_inert_js_literal($verdict['literal']);
		expect($verdict['path'])->toBe('host_templates.php')
			->and($verdict['query'])->toBe(['action' => 'download', 'action_type' => $type, 'ids' => '3,7']);
	}

	$verdict = selected_items_export_probe($page, 'whitespace');

	expect_inert_js_literal($verdict['literal']);
	expect($verdict['query']['ids'])->toBe("\n3,7 ");
})->with(array_map(null, array_keys($downloadPages), array_values($downloadPages)));

test('confirmation form posts back a numeric selection unchanged', function () : void {
	$verdict = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/SelectedItemsConfirmationProbe.php', ['list']);

	expect($verdict['inputs'])->toBe(1)
		->and($verdict['value'])->toBe(serialize(['3', '7']));
});

test('confirmation form keeps quoted item keys inside the hidden input', function () : void {
	$verdict = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/SelectedItemsConfirmationProbe.php', ['quoted']);

	expect($verdict['inputs'])->toBe(1)
		->and($verdict['elements'])->toBe(0)
		->and($verdict['value'])->toBe($verdict['expected']);
});
