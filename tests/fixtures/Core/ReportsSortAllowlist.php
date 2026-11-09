<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiReportsSortAllowlistFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

/*
 * Drive the checked-in reports() list query through every report_type branch
 * with the real sanitize_sql_column(), so the ORDER BY each branch sends is the
 * one production builds. Database, rendering and request I/O are stubbed in
 * this namespace; the two production functions are loaded unchanged.
 */

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

$GLOBALS['reportit_installed'] = false;
$GLOBALS['captured_sql']       = [];
$GLOBALS['_REQUEST']           = [];

function __($text, ...$args) {
	return $text;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function grv($name) {
	return $GLOBALS['_REQUEST'][$name] ?? '';
}
function read_config_option($name) {
	return 30;
}
function db_qstr($value) {
	return "'" . addslashes($value) . "'";
}
function db_table_exists($table) {
	return $table === 'plugin_reportit_reports' && $GLOBALS['reportit_installed'];
}
function db_fetch_cell($sql) {
	return 0;
}
function db_fetch_assoc($sql) {
	$GLOBALS['captured_sql'][] = $sql;

	return [];
}
function is_reports_admin() {
	return true;
}
function get_reports_page() {
	return 'reports_admin.php';
}
function draw_reports_filter($render = false) {
}
function html_nav_bar(...$args) {
	return '';
}
function form_start(...$args) {
}
function html_start_box(...$args) {
}
function html_header_sort_checkbox(...$args) {
}
function html_end_box(...$args) {
}
function draw_actions_dropdown(...$args) {
}
function form_end(...$args) {
}

cacti_fixture_source_function($root . '/lib/functions.php', 'sanitize_sql_column', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_reports.php', 'reports', __NAMESPACE__);

function order_by_for(bool $reportit, string $report_type, string $column, string $direction) : string {
	$GLOBALS['reportit_installed'] = $reportit;
	$GLOBALS['captured_sql']       = [];
	$GLOBALS['_REQUEST']           = [
		'rows'           => '-1',
		'filter'         => '',
		'status'         => '-1',
		'page'           => '1',
		'report_type'    => $report_type,
		'sort_column'    => $column,
		'sort_direction' => $direction,
	];

	ob_start();

	try {
		reports();
	} finally {
		ob_end_clean();
	}

	if (count($GLOBALS['captured_sql']) !== 1 || !preg_match('/ORDER BY (.*) LIMIT 0,30$/s', $GLOBALS['captured_sql'][0], $match)) {
		throw new RuntimeException('reports() did not issue a single paged list query');
	}

	return $match[1];
}

$branches = [
	'combined' => [true, '-1'],
	'classic'  => [true, 'reports'],
	'reportit' => [true, 'reportit'],
	'no-addon' => [false, '-1'],
];

$cases = [
	['full_name', 'DESC', 'full_name DESC'],
	['last_runtime', 'asc', 'last_runtime ASC'],
	// clean under the character-class filter, so only the allowlist rejects it
	['ua.password', 'DESC', 'name DESC'],
	['report.name', 'ASC', 'name ASC'],
	['name UNION SELECT password FROM user_auth', 'ASC', 'name ASC'],
	['', 'ASC; DROP TABLE reports', 'name ASC'],
];

$count = 0;

foreach ($branches as $label => [$reportit, $report_type]) {
	foreach ($cases as [$column, $direction, $expected]) {
		$actual = order_by_for($reportit, $report_type, $column, $direction);

		if ($actual !== $expected) {
			throw new RuntimeException(sprintf('%s branch sorted %s %s as %s, expected %s', $label, var_export($column, true), $direction, $actual, $expected));
		}
		$count++;
	}
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
