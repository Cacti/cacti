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

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
