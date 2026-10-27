<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiReportOwnershipFixture;
require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
$GLOBALS['reports_admin'] = false;
$GLOBALS['reports_db']    = new \PDO('sqlite::memory:');
$GLOBALS['reports_db']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$GLOBALS['reports_db']->exec('CREATE TABLE reports (id INTEGER, user_id INTEGER, name TEXT); INSERT INTO reports VALUES (1,1,"First"),(2,2,"Second"),(3,0,"Orphan"); CREATE TABLE reports_items (id INTEGER, report_id INTEGER); INSERT INTO reports_items VALUES (11,1),(22,2); CREATE TABLE reports_log (id INTEGER, source TEXT, source_id INTEGER, report_html_output TEXT, report_attachments TEXT);');
function db_fetch_row_prepared($sql, $args = []) {
	$s = $GLOBALS['reports_db']->prepare($sql);
	$s->execute($args);

	return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
}
function db_fetch_cell_prepared($sql, $args = []) {
	$s = $GLOBALS['reports_db']->prepare($sql);
	$s->execute($args);

	return $s->fetchColumn();
}
function db_execute_prepared($sql, $args = []) {
	$s = $GLOBALS['reports_db']->prepare($sql);

	return $s->execute($args);
}
function is_reports_admin() {
	return $GLOBALS['reports_admin'];
}
function raise_message(...$args) {
}
function get_username($id) {
	return 'Fixture';
}
function grv($key) {
	return '';
}

foreach (['reports_owner_authorized', 'reports_generate_history_html', 'reports_remove_history'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/reports.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html_reports.php', 'reports_item_authorized', __NAMESPACE__);

function reports_log(...$arguments): void {
}
function isrv($name) {
	return isset($GLOBALS['report_post'][$name]);
}
function gnrv($name) {
	return $GLOBALS['report_post'][$name] ?? '';
}
function gfrv($name) {
	return (int) ($GLOBALS['report_post'][$name] ?? 0);
}
function ierv($name) {
	return empty($GLOBALS['report_post'][$name]);
}
function form_input_validate($value, ...$arguments) {
	return $value;
}
function get_reports_page() {
	return 'reports.php';
}

foreach (['duplicate_reports', 'reports_add_devices', 'reports_add_graphs'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/reports.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html_reports.php', 'reports_form_save', __NAMESPACE__);
$_SESSION[SESS_USER_ID] = 1;
duplicate_reports(2, 'Fixture');

if (reports_add_devices(2, [], [], '') !== false || reports_add_graphs(2, 1, [], '') !== false) {
	throw new \RuntimeException('Direct report operation bypassed ownership.');
}

if (($argv[1] ?? '') === 'form') {
	$GLOBALS['report_post'] = $argv[2] === 'parent' ? ['save_component_report' => true, 'id' => 2] : ['save_component_report_item' => true, 'id' => 0, 'report_id' => 2];
	register_shutdown_function(function () { print json_encode(['reports' => (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports'), 'items' => (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_items')]); });
	reports_form_save();

	throw new \RuntimeException('Cross-owner form save did not stop.');
}
$cases = 0;

foreach ([false, true] as $admin) {
	$GLOBALS['reports_admin'] = $admin;

	foreach ([0, 1, 2] as $user) {
		$_SESSION[SESS_USER_ID] = $user;

		foreach ([1, 2, 3] as $report) {
			$owner   = [1 => 1, 2 => 2, 3 => 0][$report];
			$allowed = $user > 0 && $owner > 0 && ($admin || $user === $owner);
			$GLOBALS['reports_db']->exec('DELETE FROM reports_log');
			db_execute_prepared('INSERT INTO reports_log VALUES (10,?,?,?,?)', ['reports', $report, '<p>Fixture</p>', base64_encode('[]')]);

			if ((reports_generate_history_html(10, REPORTS_OUTPUT_STDOUT, $report) !== '') !== $allowed || reports_generate_history_html(10, REPORTS_OUTPUT_STDOUT, 99) !== '') {
				throw new \RuntimeException('History preview ownership failed.');
			}
			reports_remove_history(10, 99);

			if ((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log') !== 1) {
				throw new \RuntimeException('History from a different parent was removed.');
			}
			reports_remove_history(10, $report);

			if ((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log') !== ($allowed ? 0 : 1)) {
				throw new \RuntimeException('History delete ownership failed.');
			}
			$cases++;
		}

		foreach ([11 => 1, 22 => 2] as $item => $owner) {
			if (reports_item_authorized($item) !== ($user > 0 && ($admin || $user === $owner)) || reports_item_authorized($item, 99)) {
				throw new \RuntimeException('Item ownership or parent boundary failed.');
			}
			$cases++;
		}
	}
}
$GLOBALS['reports_admin'] = true;
$_SESSION[SESS_USER_ID]   = 1;
$GLOBALS['reports_db']->exec('DELETE FROM reports_log');
db_execute_prepared('INSERT INTO reports_log VALUES (10,?,?,?,?)', ['plugin-fixture', 1, '<p>Plugin</p>', base64_encode('[]')]);
reports_remove_history(10, 1);

if (reports_generate_history_html(10) !== '' || (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM reports_log') !== 1 || reports_item_authorized(999)) {
	throw new \RuntimeException('Foreign or missing resources crossed the report boundary.');
}
print json_encode(['cases' => $cases, 'foreign_history_preserved' => true]);
