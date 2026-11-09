<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiReportsListSortFixture;

use RuntimeException;

/*
 * Changed-line coverage for the reports list sort allowlist. reports() is
 * sliced out of the page helpers so the production statements run without
 * CactiTableFilter, auth, or a database.
 */

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);

const SESS_USER_ID      = 'sess_user_id';
const MAX_DISPLAY_PAGES = 10;
const CACTI_PATH_URL    = '/cacti';

$GLOBALS['item_rows']             = [];
$GLOBALS['reports_interval']      = [];
$GLOBALS['reports_actions']       = [];
$GLOBALS['attach_types']          = [];
$GLOBALS['sched_types']           = [];
$GLOBALS['_SESSION']              = [SESS_USER_ID => 7];
$GLOBALS['cacti_reports_admin']   = false;
$GLOBALS['cacti_reports_request'] = [
	'rows'           => 15,
	'filter'         => '',
	'status'         => '-1',
	'sort_column'    => 'ua.password',
	'sort_direction' => 'DESC',
	'page'           => 1,
	'report_type'    => '-1',
];
$GLOBALS['cacti_reports_header']   = null;
$GLOBALS['cacti_reports_sql']      = [];
$GLOBALS['cacti_reports_reportit'] = true;

function __($text, ...$args) {
	return $args !== [] ? vsprintf($text, $args) : $text;
}
function draw_reports_filter($render = false) : void {
}
function grv($name, $default = '') {
	return $GLOBALS['cacti_reports_request'][$name] ?? $default;
}
function read_config_option($name, $force = false) {
	return 20;
}
function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}
function is_reports_admin() {
	return $GLOBALS['cacti_reports_admin'];
}
function db_table_exists($table) {
	return $table === 'plugin_reportit_reports' && $GLOBALS['cacti_reports_reportit'];
}

function cacti_reports_run_list(array $request, bool $admin, bool $reportit) : array {
	$GLOBALS['cacti_reports_admin']    = $admin;
	$GLOBALS['cacti_reports_reportit'] = $reportit;
	$GLOBALS['cacti_reports_request']  = array_merge($GLOBALS['cacti_reports_request'], $request);
	$GLOBALS['cacti_reports_sql']      = [];
	$GLOBALS['cacti_reports_header']   = null;

	ob_start();
	reports();
	ob_end_clean();

	return $GLOBALS['cacti_reports_sql'];
}
function db_fetch_cell($sql, ...$args) {
	$GLOBALS['cacti_reports_sql'][] = $sql;

	return 0;
}
function db_fetch_assoc($sql, ...$args) {
	$GLOBALS['cacti_reports_sql'][] = $sql;

	return [];
}
function html_nav_bar(...$args) {
	return '';
}
function get_reports_page() {
	return 'reports.php';
}
function form_start($action, $id) {
}
function html_start_box(...$args) {
}
function html_header_sort_checkbox($display_text, $sort_column, $sort_direction, $last) {
	$GLOBALS['cacti_reports_header'] = [$display_text, $sort_column, $sort_direction, $last];
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function html_end_box($trailing = true) {
}
function draw_actions_dropdown($actions) {
}
function form_end() {
}

cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_validate_sort_column', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/reports.php', 'reports_sort_column', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_reports.php', 'reports', __NAMESPACE__);

ob_start();
reports();
$non_admin_sql    = $GLOBALS['cacti_reports_sql'];
$non_admin_header = $GLOBALS['cacti_reports_header'];
ob_end_clean();

if ($non_admin_header === null) {
	throw new RuntimeException('reports() did not render sortable headers');
}

if ($non_admin_header[1] !== 'name' || $non_admin_header[2] !== 'DESC') {
	throw new RuntimeException('Rejected sort column was not replaced with the displayed default');
}

if (!isset($non_admin_header[0]['name'], $non_admin_header[0]['full_name'], $non_admin_header[0]['nosort'])) {
	throw new RuntimeException('Reports list headers lost the displayed columns');
}

$joined = implode("\n", $non_admin_sql);

if (!str_contains($joined, ' ua.id=7')) {
	throw new RuntimeException('Non-admin reports() did not filter on the user_auth alias');
}

if (!str_contains($joined, 'ORDER BY name DESC')) {
	throw new RuntimeException('UNION reports query did not sort by the allowlisted column');
}

$GLOBALS['cacti_reports_admin']                     = true;
$GLOBALS['cacti_reports_request']['sort_column']    = 'full_name';
$GLOBALS['cacti_reports_request']['sort_direction'] = 'ASC';
$GLOBALS['cacti_reports_sql']                       = [];
$GLOBALS['cacti_reports_header']                    = null;

ob_start();
reports();
$admin_sql    = $GLOBALS['cacti_reports_sql'];
$admin_header = $GLOBALS['cacti_reports_header'];
ob_end_clean();

if ($admin_header[1] !== 'full_name' || $admin_header[2] !== 'ASC') {
	throw new RuntimeException('Allowlisted owner column was not used for the header sort');
}

if (str_contains(implode("\n", $admin_sql), ' ua.id=')) {
	throw new RuntimeException('Admin reports() still constrained the owner join');
}

if (cacti_validate_sort_column('ua.password', ['name', 'full_name'], 'name') !== 'name') {
	throw new RuntimeException('cacti_validate_sort_column accepted a joined user_auth column');
}

if (reports_sort_column('nosort', $admin_header[0]) !== 'name') {
	throw new RuntimeException('reports_sort_column treated a nosort header as sortable');
}

$classic_sql = implode("\n", cacti_reports_run_list([
	'report_type'    => 'reports',
	'sort_column'    => 'name',
	'sort_direction' => 'ASC',
], true, true));

if (!str_contains($classic_sql, 'ORDER BY name ASC') || str_contains($classic_sql, 'UNION')) {
	throw new RuntimeException('Classic reports() list did not sort the reports table alone');
}

$reportit_sql = implode("\n", cacti_reports_run_list([
	'report_type'    => 'reportit',
	'sort_column'    => 'next_start',
	'sort_direction' => 'DESC',
], true, true));

if (!str_contains($reportit_sql, 'ORDER BY next_start DESC') || !str_contains($reportit_sql, 'plugin_reportit_reports') || str_contains($reportit_sql, 'UNION')) {
	throw new RuntimeException('ReportIt reports() list did not sort plugin_reportit_reports alone');
}

$legacy_sql = implode("\n", cacti_reports_run_list([
	'report_type'    => '-1',
	'sort_column'    => 'enabled',
	'sort_direction' => 'ASC',
], false, false));

if (!str_contains($legacy_sql, 'ORDER BY enabled ASC') || str_contains($legacy_sql, 'plugin_reportit')) {
	throw new RuntimeException('reports() without ReportIt did not sort the reports table');
}

print json_encode(['passed' => 5], JSON_THROW_ON_ERROR) . "\n";
