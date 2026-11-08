<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\RrdInfoHtml;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * rrdtool_info2html() renders the header, data source and RRA tables of
 * an RRD file. Every string it prints comes from rrdtool info, so each
 * cell must go through form_selectable_ecell(). The real function,
 * form_selectable_ecell(), htmle() and html_escape() run from source;
 * only the table chrome and the final cell writer are recorded.
 */

$root = dirname(__DIR__, 3);

define('CACTI_PATH_LIBRARY', sys_get_temp_dir() . '/cacti-rrdinfo-probe');
define('CACTI_DATE_TIME_FORMAT', 'Y-m-d H:i:s');

if (!is_dir(CACTI_PATH_LIBRARY)) {
	mkdir(CACTI_PATH_LIBRARY);
}

file_put_contents(CACTI_PATH_LIBRARY . '/time.php', "<?php\n");

$GLOBALS['rrdinfo_cells'] = [];

function __($text) {
	return $text;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function cacti_strtolower($value) {
	return strtolower((string) $value);
}
function number_format_i18n($number, $decimals = 0) {
	return (string) $number;
}
function html_start_box(...$args) {
}
function html_header(...$args) {
}
function html_end_box(...$args) {
}
function form_alternate_row(...$args) {
}
function form_end_row() {
}
function form_selectable_cell($contents, $id, $width = '', $styleclass = '', $title = '') {
	$GLOBALS['rrdinfo_cells'][] = [(string) $id, (string) $contents];

	return true;
}

\cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html.php', 'htmle', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html_utility.php', 'form_selectable_ecell', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/rrd.php', 'rrdtool_info2html', __NAMESPACE__);

$mark = '<img src=x>';

rrdtool_info2html([
	'filename'    => "a$mark.rrd",
	'rrd_version' => "0003$mark",
	'step'        => "300$mark",
	'last_update' => 1700000000,
	'ds'          => [
		"ds$mark" => [
			'type'              => "GAUGE$mark",
			'minimal_heartbeat' => "600$mark",
			'min'               => "0$mark",
			'max'               => 'U',
			'last_ds'           => "x$mark",
			'value'             => "v$mark",
			'unknown_sec'       => "0$mark",
		],
		// the remaining min/max branches: 'U', numeric, other text and unset
		"in$mark"  => ['type' => 'COUNTER', 'min' => 'U', 'max' => '100'],
		"out$mark" => ['type' => 'COUNTER', 'min' => '5', 'max' => "x$mark"],
		"cpu$mark" => ['type' => 'GAUGE'],
	],
	'rra'         => [
		"0$mark" => [
			'cf'          => "AVERAGE$mark",
			'rows'        => "500$mark",
			'cur_row'     => "1$mark",
			'pdp_per_row' => "1$mark",
			'xff'         => '0.5',
			'cdp_prep'    => [['value' => 'NaN', 'unknown_datapoints' => "0$mark"]],
		],
	],
], ['ds' => ["ds$mark" => ['min' => true]]]);

$cells = $GLOBALS['rrdinfo_cells'];

if (count($cells) < 20) {
	throw new \RuntimeException('rrdtool_info2html rendered ' . count($cells) . ' cells');
}

foreach ($cells as [$id, $contents]) {
	if (str_contains($contents, '<')) {
		throw new \RuntimeException("cell $id was not escaped: $contents");
	}
}

if (!in_array(['value', 'v&lt;img src=x&gt;'], $cells, true)) {
	throw new \RuntimeException('data source value was not rendered escaped');
}

print json_encode(['passed' => count($cells)], JSON_THROW_ON_ERROR) . "\n";
