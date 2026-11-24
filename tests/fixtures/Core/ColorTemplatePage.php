<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ColorTemplatePage;

use ErrorException;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Runs the Color Templates page handlers (color_templates.php) against a
 * recorded fake database. The handlers are loaded unchanged with their original
 * line numbers; the web entry point and the real database are never used.
 * Warnings are errors here, so reading a missing row fails the probe.
 */

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line) : bool {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// sync_color_templates() loads api_aggregate.php; push_out_aggregates() is recorded below instead.
$library = sys_get_temp_dir() . '/cacti-color-template-page-' . getmypid();
mkdir($library);
file_put_contents($library . '/api_aggregate.php', "<?php\n");
register_shutdown_function(static function () use ($library) : void {
	@unlink($library . '/api_aggregate.php');
	@rmdir($library);
});
define('CACTI_PATH_LIBRARY', $library);

$GLOBALS['passed'] = 0;
$GLOBALS['db']     = [];
$GLOBALS['fake']   = [];

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function cacti_log(mixed ...$args) : void {
}

function grv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function gfrv(string $name, int $filter = FILTER_VALIDATE_INT, array $options = []) : mixed {
	return isset($_REQUEST[$name]) ? filter_var($_REQUEST[$name], $filter, $options) : null;
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

function db_fetch_row_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	return fake($sql, $params);
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function array_rekey(array $array, string $key, string $value) : array {
	return array_column($array, $value, $key);
}

function push_out_aggregates(int $aggregate_template_id, int $local_graph_id = 0) : bool {
	$GLOBALS['pushed'][] = [$aggregate_template_id, $local_graph_id];

	return true;
}

function raise_message(mixed $id, string $message = '', int $level = 0) : bool {
	$GLOBALS['messages'][] = [$id, $message, $level];

	return true;
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

// Run one move and return the UPDATE parameter lists it issued.
function move(string $function, array $fake) : array {
	$_REQUEST        = ['color_template_item_id' => '12', 'color_template_id' => '3'];
	$GLOBALS['db']   = [];
	$GLOBALS['fake'] = $fake + ['UPDATE color_template_items' => true];
	(__NAMESPACE__ . '\\' . $function)();

	return array_values(array_map(static fn (array $call) : array => $call[1], array_filter($GLOBALS['db'], static fn (array $call) : bool => str_starts_with($call[0], 'UPDATE'))));
}

\cacti_fixture_source_function($root . '/color_templates.php', 'color_item_movedown', __NAMESPACE__);
\cacti_fixture_source_function($root . '/color_templates.php', 'color_item_moveup', __NAMESPACE__);
\cacti_fixture_source_function($root . '/color_templates.php', 'sync_color_templates', __NAMESPACE__);

// Sync one color template and return the push outs it made.
function sync(array $templates, array $graphs) : array {
	$GLOBALS['db']       = [];
	$GLOBALS['pushed']   = [];
	$GLOBALS['messages'] = [];
	$GLOBALS['fake']     = [
		'SELECT name'                         => 'Blues',
		'FROM aggregate_graph_templates_item' => $templates,
		'FROM aggregate_graphs_graph_item'    => $graphs,
	];
	sync_color_templates(7);

	return $GLOBALS['pushed'];
}

$current = ['color_template_item_id' => 12, 'sequence' => 5];

// A move swaps the sequence with the neighbour in the same template.
check(move('color_item_movedown', ['WHERE color_template_item_id = ?' => $current, 'WHERE sequence >' => ['color_template_item_id' => 13, 'sequence' => 6]]) === [[6, '3', 12], [5, '3', 13]], 'move down swaps with the next item');
check($GLOBALS['db'][0][1] === ['12', '3'], 'move down reads the item within its template');
check(move('color_item_moveup', ['WHERE color_template_item_id = ?' => $current, 'WHERE sequence <' => ['color_template_item_id' => 11, 'sequence' => 4]]) === [[4, '3', 12], [5, '3', 11]], 'move up swaps with the previous item');
check($GLOBALS['db'][0][1] === ['12', '3'], 'move up reads the item within its template');

// Moving past either end, or an item that is not in the template, changes nothing.
check(move('color_item_movedown', ['WHERE color_template_item_id = ?' => $current, 'WHERE sequence >' => []]) === [], 'last item does not move down');
check(move('color_item_moveup', ['WHERE color_template_item_id = ?' => $current, 'WHERE sequence <' => []]) === [], 'first item does not move up');
check(move('color_item_movedown', ['WHERE color_template_item_id = ?' => []]) === [], 'unknown item does not move down');
check(move('color_item_moveup', ['WHERE color_template_item_id = ?' => []]) === [], 'unknown item does not move up');

// Sync pushes out the templates and only the aggregates with their own items that use this color template.
$pushed = sync([['aggregate_template_id' => 5]], [['aggregate_template_id' => 5, 'local_graph_id' => 1001], ['aggregate_template_id' => 0, 'local_graph_id' => 1003]]);
check($pushed === [[5, 0], [0, 1001], [0, 1003]], 'templates pushed out, own-item aggregates rebuilt from their own items');
check(str_contains($GLOBALS['db'][2][0], "WHERE (ag.aggregate_template_id = 0 OR ag.template_propogation = '') AND agi.color_template = ?") && $GLOBALS['db'][2][1] === [7], 'the color template filter applies to both kinds of aggregate');
check($GLOBALS['messages'][0][1] === "Color Template 'Blues' had 1 Aggregate Templates pushed out and 2 Non-Templated Aggregates pushed out", 'sync result reported');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
