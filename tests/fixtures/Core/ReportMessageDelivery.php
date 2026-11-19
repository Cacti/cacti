<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiReportMessageFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';

foreach (['raise_message', 'raise_message_html', 'raise_message_has_markup'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

foreach (['reports_graphs_action_execute', 'reports_queue', 'reports_add_devices'] as $function) {
	cacti_fixture_source_function($root . '/lib/reports.php', $function, __NAMESPACE__);
}

function __(string $format, mixed ...$args) : string {
	return vsprintf($format, $args);
}

function cacti_log(string $text, bool $print, string $category, int $level) : void {
	$GLOBALS['fixture_logs'][] = [$text, $category, $level];
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function gnrv(string $name) : mixed {
	return $_REQUEST[$name];
}

function gfrv(string $name) : int {
	return (int) $_REQUEST[$name];
}

function input_validate_input_number(int $id, string $name) : void {
	if ($id <= 0) {
		throw new RuntimeException("Unexpected $name $id");
	}
}

function db_fetch_cell_prepared(string $sql, array $params) : mixed {
	if (str_contains($sql, 'FROM reports_items') && str_contains($sql, 'SELECT id')) {
		return $params[0] === 13;
	}

	if (str_contains($sql, 'SELECT description')) {
		return 'North & South';
	}

	return 1;
}

function db_fetch_row_prepared(string $sql, array $params) : array {
	return str_contains($sql, 'FROM graph_local') ? ['host_id' => 0, 'graph_template_id' => 1] : ['font_size' => 10];
}

function sql_save(array $record, string $table) : int {
	$GLOBALS['saved_records'][$table][] = $record;

	return $table === 'reports_items' ? (int) ($record['local_graph_id'] === 11) : $GLOBALS['queue_result'];
}

function db_execute_prepared(string $sql, array $params) : void {
	$GLOBALS['device_inserts'][] = $params;
}

function get_graph_title(int $graph_id) : string {
	return "Graph $graph_id & <East>";
}

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function message_is(string $key, int $level, string $expected) : void {
	$row = $_SESSION[SESS_MESSAGES][$key] ?? [];
	check(($row['level'] ?? null) === $level && ($row['message'] ?? null) === $expected, "Unexpected session message for $key");
}

$no_http_headers = true;
$_SESSION        = [SESS_USER_ID => 1];
$_REQUEST        = ['selected_items' => serialize([11, 12, 13]), 'reports_id' => 7, 'timespan' => 2, 'alignment' => 0];

check(reports_graphs_action_execute('reports') === 'reports', 'Graph selection returned the wrong action');
check(array_column($GLOBALS['saved_records']['reports_items'], 'local_graph_id') === [11, 12], 'Duplicate graph reached the database');
message_is('report_send', MESSAGE_LEVEL_INFO, "Created Report Graph Item '<i>Graph 11 &amp; &lt;East&gt;</i>'<br>"
	. "Failed Adding Report Graph Item '<i>Graph 12 &amp; &lt;East&gt;</i>' Already Exists<br>"
	. "Skipped Report Graph Item '<i>Graph 13 &amp; &lt;East&gt;</i>' Already Exists<br>");

check(!reports_add_devices(7, [11, 13], [2], ''), 'Duplicate device did not affect the result');
check(count($GLOBALS['device_inserts']) === 1 && $GLOBALS['device_inserts'][0][3] === 11, 'Unexpected device insert');
message_is('reports_add_device_11', MESSAGE_LEVEL_INFO, 'Device &apos;North &amp; South&apos; successfully added to Report.');
message_is('reports_no_add_device_13', MESSAGE_LEVEL_WARN, 'Device &apos;North &amp; South&apos; not added to Report as it already exists on report.');

foreach ([1, 0] as $result) {
	$GLOBALS['queue_result'] = $result;
	check(reports_queue('Sales & Returns', 1, 'daily', 12, 'report', []) === $result, 'Queue result was lost');
}

message_is('report_scheduled', MESSAGE_LEVEL_INFO, 'The Report &apos;Sales &amp; Returns&apos; from source daily with id 12 is scheduled to run!');
message_is('report_not_scheduled', MESSAGE_LEVEL_ERROR, 'The Report &apos;Sales &amp; Returns&apos; from source daily with id 12 was not scheduled to run due to an error!');

raise_message('legacy', 'North &amp; South', MESSAGE_LEVEL_WARN);
message_is('legacy', MESSAGE_LEVEL_WARN, 'North &amp; South');
[$notice, $category, $level] = end($GLOBALS['fixture_logs']);
check(str_contains($notice, basename(__FILE__)) && $category === 'WEBUI' && $level === POLLER_VERBOSITY_DEBUG, 'Legacy producer did not receive a diagnostic with its location');
$messages = [7 => ['message' => 'Saved <b>OK</b>', 'level' => MESSAGE_LEVEL_INFO]];
raise_message(7);
message_is('7', MESSAGE_LEVEL_INFO, 'Saved <b>OK</b>');
print "Report message delivery passed\n";
