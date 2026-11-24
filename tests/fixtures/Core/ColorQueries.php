<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ColorQueries;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Runs the functions that read colors for graph and aggregate items with a
 * recording fake database and checks the statements they issue. The functions
 * are loaded unchanged with their original line numbers.
 */

$root = dirname(__DIR__, 3);

$GLOBALS['passed'] = 0;
$GLOBALS['db']     = [];
$GLOBALS['fake']   = [];

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmlspecialchars(__($text, ...$args), ENT_QUOTES, 'UTF-8');
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function grv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function gfrv(string $name, mixed ...$args) : mixed {
	return $_REQUEST[$name] ?? null;
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function ierv(string $name) : bool {
	return empty($_REQUEST[$name]);
}

// Each fake answer is matched by a fragment of the statement, first match wins.
function fake(string $sql, array $params) : mixed {
	$GLOBALS['db'][] = [preg_replace('/\s+/', ' ', $sql), $params];

	foreach ($GLOBALS['fake'] as $fragment => $answer) {
		if (str_contains($sql, $fragment)) {
			return $answer;
		}
	}

	throw new RuntimeException('Unexpected statement: ' . $sql);
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function db_fetch_assoc(string $sql) : mixed {
	return fake($sql, []);
}

function db_execute(string $sql) : mixed {
	return fake($sql, []);
}

function db_execute_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function array_rekey(mixed $array, string $key, mixed $value) : array {
	return [];
}

function api_plugin_hook_function(string $name, mixed $data) : mixed {
	return $data;
}

function get_graph_title(mixed $id) : string {
	return 'Graph ' . $id;
}

function html_start_box(mixed ...$args) : void {
}

function html_end_box(mixed ...$args) : void {
}

function draw_graph_items_list(mixed ...$args) : void {
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

\cacti_fixture_source_function($root . '/graphs.php', 'item', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/utility.php', 'object_cache_update_graph_totals', __NAMESPACE__);

// The graph item list takes the gradient color from color2_id, as the graph template item list does.
$_REQUEST        = ['id' => '5'];
$GLOBALS['fake'] = ['FROM graph_templates_item' => [], 'FROM graph_local' => 1];
ob_start();
item();
ob_end_clean();
check(str_contains($GLOBALS['db'][0][0], 'LEFT JOIN colors AS c ON color_id = c.id LEFT JOIN colors AS c2 ON color2_id = c2.id'), 'graph item list joins the gradient color on color2_id');

// The full recount refreshes every color's cached totals with one statement, not one per color.
$GLOBALS['db']   = [];
$GLOBALS['fake'] = ['SELECT id FROM colors' => [['id' => 1], ['id' => 2], ['id' => 3]], 'SELECT' => [], 'UPDATE' => true];
object_cache_update_graph_totals();
$colors = array_values(array_filter($GLOBALS['db'], static fn (array $call) : bool => str_contains($call[0], ' colors')));
check(cacti_sizeof($colors) === 1, 'colors recounted with one statement');
check(str_starts_with($colors[0][0], 'UPDATE colors AS c LEFT JOIN ( SELECT color_id AS id,') && str_ends_with($colors[0][0], 'ON t.id = c.id SET c.graphs = IFNULL(t.graphs, 0), c.templates = IFNULL(t.templates, 0)'), 'unused colors reset to zero in the same statement');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
