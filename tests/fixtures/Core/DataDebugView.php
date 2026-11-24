<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\DataDebugView;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Renders the Data Source Troubleshooter view (debug_view() in data_debug.php)
 * against a recorded fake data_debug row. The view and its value formatting
 * are loaded unchanged with their original line numbers; the web entry point,
 * authentication and the real database are never used.
 */

$root = dirname(__DIR__, 3);

const CACTI_PATH_RRA = '/var/lib/cacti/rra';

$GLOBALS['passed'] = 0;
$GLOBALS['rows']   = [];
$GLOBALS['cells']  = [];

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmle(__($text, ...$args));
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function cacti_unserialize(string $value) : mixed {
	return unserialize($value, ['allowed_classes' => false]);
}

function gfrv(string $name) : mixed {
	return $_REQUEST[$name] ?? null;
}

function read_config_option(string $name) : string {
	return $name === 'path_rrdtool' ? '/usr/bin/rrdtool' : '';
}

function db_fetch_row_prepared(string $sql, array $params = []) : array {
	foreach ($GLOBALS['rows'] as $fragment => $row) {
		if (str_contains($sql, $fragment)) {
			return $row;
		}
	}

	throw new RuntimeException('Unexpected statement: ' . $sql);
}

function debug_icon(mixed $result) : string {
	return '[icon]';
}

function debug_icon_valid_result(mixed $result) : string {
	return '[valid]';
}

function debug_process_status(mixed $id) : string {
	return $GLOBALS['status'];
}

function get_data_source_title(mixed $id) : string {
	return $GLOBALS['title'];
}

function get_data_source_path(mixed $id, bool $expand) : string {
	return '/nonexistent/' . $id . '.rrd';
}

function html_escape_url(string $url) : string {
	return htmle($url);
}

function html_start_box(string $title, mixed ...$args) : void {
	$GLOBALS['boxes'][] = $title;
}

function html_header(array $columns) : void {
}

function html_end_box(mixed ...$args) : void {
}

function form_alternate_row(mixed ...$args) : void {
}

function form_end_row() : void {
}

// form_selectable_cell() prints its value as HTML, form_selectable_ecell() escapes it first.
function form_selectable_cell(mixed $value, mixed $id, string $width = '', string $style = '', string $title = '') : void {
	$GLOBALS['cells'][] = ['html' => (string) $value, 'title' => $title];
}

function form_selectable_ecell(mixed $value, mixed $id, string $width = '', string $style = '', string $title = '') : void {
	$GLOBALS['cells'][] = ['html' => htmle($value), 'title' => $title];
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

\cacti_fixture_source_function($root . '/data_debug.php', 'debug_view', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsdebug.php', 'dsdebug_format_value', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsdebug.php', 'dsdebug_repair_status', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html.php', 'htmle', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', __NAMESPACE__);

// Render the view for one data_debug row and return the value cell of each check, keyed by its title.
function view(array $check, string $status, string $title = 'Router - Traffic') : array {
	$_REQUEST           = ['id' => '12'];
	$GLOBALS['cells']   = [];
	$GLOBALS['boxes']   = [];
	$GLOBALS['status']  = $status;
	$GLOBALS['title']   = $title;
	$GLOBALS['rows']    = [
		'FROM data_debug'         => $check + ['datasource' => 12],
		'FROM data_template_data' => ['data_source_path' => '<path_rra>/router_traffic_in_12.rrd'],
	];

	ob_start();
	debug_view();
	ob_end_clean();

	$values = [];

	// each check is a row of title, value and result cells
	for ($i = 0; $i + 2 < cacti_sizeof($GLOBALS['cells']); $i += 3) {
		$values[$GLOBALS['cells'][$i]['html']] = $GLOBALS['cells'][$i + 1];
	}

	$values['repair'] = end($GLOBALS['cells']);

	return $values;
}

// A finished check: poller results one per line, each value escaped exactly once.
$values = view([
	'done'  => 1,
	'issue' => '',
	'info'  => serialize([
		'last_result'   => ['traffic_in' => '10', 'traffic_out' => '<b>&20'],
		'rrd_exists'    => '1',
		'active'        => 'on',
		'rrd_match'     => 1,
	]),
], 'complete', 'Router <b>&amp; Traffic');

$poller = $values['Did the poller receive valid data?'];
check($poller['html'] === "traffic_in = 10<br>\ntraffic_out = &lt;b&gt;&amp;20", 'poller results shown one per line');
check($poller['title'] === $poller['html'], 'poller results tooltip matches the cell');

$path = $values['Is RRDfile writeable by poller?'];
check($path['html'] === '/var/lib/cacti/rra/router_traffic_in_12.rrd', 'RRDfile path shown once escaped');
check($values['Is RRA Folder writeable by poller?']['html'] === '/var/lib/cacti/rra', 'RRA folder taken from the expanded path');

$title = $values['Were we able to convert the title?'];
check($title['html'] === 'Router &lt;b&gt;&amp; Traffic', 'data source title escaped once, not twice');

check($values['Does the RRDfile Exist?']['html'] === 'Yes', 'RRDfile existence shown');
check($values['Issues']['html'] === '', 'no issue for a clean check');
check($values['repair']['html'] === 'No Repair Recommendations', 'finished check stops waiting for repairs');
check(str_starts_with($GLOBALS['boxes'][0], 'Data Source Troubleshooter [ Analysis Complete!'), 'finished check offers a rerun');

// A long value is cut before it is escaped, so no entity or line break is cut in half.
$values = view([
	'done' => 0,
	'info' => serialize(['last_result' => ['in' => str_repeat('a', 94) . '&bc']]),
], 'waiting');

$poller = $values['Did the poller receive valid data?'];
check($poller['html'] === 'in = ' . str_repeat('a', 94) . '&amp;', 'long value shortened before escaping');
check($poller['title'] === 'in = ' . str_repeat('a', 94) . '&amp;bc', 'tooltip keeps the whole value');
check($values['repair']['html'] === 'Waiting on Data Source Check to Complete', 'running check still waits for repairs');
check($values['Issues']['html'] === 'Waiting on analysis and RRDfile update', 'running check reports it is waiting');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
