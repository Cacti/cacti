<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ColorPage;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Runs the Colors page handlers (color.php) against a recorded fake database.
 * The handlers are loaded unchanged with their original line numbers; the web
 * entry point, authentication and the real database are never used.
 */

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

$GLOBALS['passed'] = 0;
$GLOBALS['db']     = [];
$GLOBALS['fake']   = [];

class Redirect extends RuntimeException {
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function html_escape(mixed $text) : string {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function header(string $value) : void {
	throw new Redirect($value);
}

function db_execute(string $sql) : bool {
	throw new RuntimeException('Unprepared statement: ' . $sql);
}

function db_fetch_row(string $sql) : array {
	throw new RuntimeException('Unprepared statement: ' . $sql);
}

// Each fake answer is matched by a fragment of the statement, in order.
function fake(string $sql, array $params) : mixed {
	$GLOBALS['db'][] = [preg_replace('/\s+/', ' ', $sql), $params];

	foreach ($GLOBALS['fake'] as $fragment => $answer) {
		if (str_contains($sql, $fragment)) {
			return is_callable($answer) ? $answer($params) : $answer;
		}
	}

	throw new RuntimeException('Unexpected statement: ' . $sql);
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	return fake($sql, $params);
}

function db_fetch_row_prepared(string $sql, array $params = []) : mixed {
	return fake($sql, $params);
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

function import(array $lines, array $fake, array $request = []) : array {
	$_REQUEST        = $request;
	$GLOBALS['db']   = [];
	$GLOBALS['fake'] = $fake;

	return color_import_processor($lines);
}

\cacti_fixture_source_function($root . '/color.php', 'color_import_processor', __NAMESPACE__);

// A header and a quoted name containing a comma, as color_export() writes it.
$result = import(["\"name\",\"hex\"\n", "\"Blue, dark\",\"0000AA\"\n"], ['SELECT *' => [], 'INSERT INTO colors' => true]);
check(str_contains($result[0], 'HEADER LINE PROCESSED OK') && str_contains($result[0], '(name, hex)'), 'header accepted');
check($result[1] === 'INSERT SUCCEEDED: (0000AA,Blue, dark)', 'quoted row imported');
check($GLOBALS['db'][0][1] === ['0000AA'] && $GLOBALS['db'][1][1] === ['0000AA', 'Blue, dark'], 'row looked up and inserted with bound values');
check(!str_contains($GLOBALS['db'][1][0], 'DUPLICATE'), 'plain import does not update');

// Columns in either order, unknown columns ignored.
$result = import(["hex,comment,name\n", "00AA00,ignored,Green\n"], ['SELECT *' => [], 'INSERT INTO colors' => true]);
check($result[1] === 'INSERT SUCCEEDED: (00AA00,Green)', 'reordered columns imported');

// An existing hex is skipped unless updates are allowed.
$result = import(["name,hex\n", "Red,FF0000\n"], ['SELECT *' => ['id' => 1, 'hex' => 'FF0000']]);
check($result[1] === '<strong>INSERT SKIPPED, EXISTING:</strong> (FF0000,Red)' && cacti_sizeof($GLOBALS['db']) === 1, 'existing color skipped');

$result = import(["name,hex\n", "Red,FF0000\n"], ['ON DUPLICATE KEY UPDATE' => true], ['allow_update' => 'on']);
check($result[1] === 'INSERT SUCCEEDED: (FF0000,Red)' && $GLOBALS['db'][0][1] === ['FF0000', 'Red'], 'existing color updated when allowed');

$result = import(["name,hex\n", "Red,FF0000\n"], ['ON DUPLICATE KEY UPDATE' => false], ['allow_update' => 'on']);
check($result[1] === 'INSERT FAILED: (FF0000,Red)', 'failed update reported');

$result = import(["name,hex\n", "Red,FF0000\n"], ['SELECT *' => [], 'INSERT INTO colors' => false]);
check($result[1] === 'INSERT FAILED: (FF0000,Red)', 'failed insert reported');

// Anything but six hex digits is refused before the database, and the result line is escaped.
foreach (['FFF', 'FF00001', 'GG0000', "FF00'\\", '<b>x</b>'] as $hex) {
	$result = import(["name,hex\n", '"<i>n</i>",' . $hex . "\n"], []);
	check(str_starts_with($result[1], '<strong>INSERT SKIPPED, INVALID HEX:</strong> (') && $GLOBALS['db'] === [], 'invalid hex ' . $hex . ' refused');
	check(!str_contains($result[1], '<i>') && !str_contains($result[1], '<b>x'), 'invalid hex ' . $hex . ' reported as text');
}

// A row without both columns is ignored; a header without both columns stops the import.
$result = import(["name,hex\n", "Lonely\n"], []);
check(cacti_sizeof($result) === 1 && $GLOBALS['db'] === [], 'short row ignored');

$result = import(["name,<b>colour</b>\n", "Red,FF0000\n"], []);
check(cacti_sizeof($result) === 1 && str_contains($result[0], 'HEADER LINE PROCESSING ERROR') && !str_contains($result[0], '<b>colour'), 'bad header stops the import');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
