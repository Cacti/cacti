<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ColorMarkup;

use DOMDocument;
use DOMXPath;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Renders every place that prints a stored color hex (and the graph item alpha)
 * with a value carrying markup characters, and checks that the value stays text.
 * The production functions are loaded unchanged with their original line numbers;
 * only the database and page chrome helpers are replaced.
 */

$root = dirname(__DIR__, 3);

// draw_graph_items_list() includes global_arrays.php; give it just the lookup tables it reads.
$include = sys_get_temp_dir() . '/cacti-color-markup-' . getmypid();
mkdir($include);
file_put_contents($include . '/global_arrays.php', "<?php\n\$graph_item_types = [4 => 'LINE1', 7 => 'AREA'];\n\$consolidation_functions = [1 => 'AVERAGE'];\n");
register_shutdown_function(static function () use ($include) : void {
	@unlink($include . '/global_arrays.php');
	@rmdir($include);
});
define('CACTI_PATH_INCLUDE', $include);
define('POLLER_VERBOSITY_DEVDBG', 5);
define('MAX_DISPLAY_PAGES', 21);

$GLOBALS['passed'] = 0;
$GLOBALS['rows']   = [];
$GLOBALS['cell']   = '';

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmle(__($text, ...$args));
}

function htmle(mixed $text) : string {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function html_escape_url(string $url) : string {
	return htmle($url);
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function cacti_log(mixed ...$args) : void {
}

function read_config_option(string $name) : string {
	return '';
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['cell'];
}

function db_fetch_assoc(string $sql) : array {
	if (str_contains($sql, 'FROM color_templates')) {
		return [];
	}

	return $GLOBALS['rows'];
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	return [];
}

function db_fetch_row_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['row'];
}

function array_rekey(array $array, string $key, mixed $key_value) : array {
	return [];
}

function html_header(mixed ...$args) : void {
}

function html_start_box(mixed ...$args) : void {
	print '<table>';
}

function html_end_box(mixed ...$args) : void {
	print '</table>';
}

function html_create_list(mixed ...$args) : void {
}

function DrawMatrixHeaderItem(mixed ...$args) : void {
}

function form_alternate_row(mixed ...$args) : void {
	print '<tr>';
}

function form_end_row() : void {
	print '</tr>';
}

function form_hidden_box(mixed ...$args) : void {
}

function is_menu_pick_active(string $page) : bool {
	return false;
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

function input_validate_input_number(mixed ...$args) : void {
}

function process_sanitize_render_filter(bool $render = false) : void {
}

function db_fetch_cell(string $sql) : mixed {
	return cacti_sizeof($GLOBALS['rows']);
}

function get_order_string() : string {
	return '';
}

function html_nav_bar(mixed ...$args) : string {
	return '';
}

function form_start(mixed ...$args) : void {
}

function form_end() : void {
}

function html_header_sort_checkbox(mixed ...$args) : void {
}

function filter_value(mixed $value, mixed ...$args) : string {
	return htmle($value);
}

// The real cell prints the style argument as given, so the caller must escape it.
function form_selectable_cell(mixed $contents, mixed $id, mixed $width = '', string $style = '') : void {
	print "<td style='" . $style . "'>" . $contents . '</td>';
}

function number_format_i18n(mixed $value, mixed ...$args) : string {
	return (string) $value;
}

function form_checkbox_cell(mixed ...$args) : void {
}

function draw_actions_dropdown(mixed ...$args) : void {
}

function form_continue_confirmation(array $data) : void {
	print $data['general']['item_list'];
}

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}
	$GLOBALS['passed']++;
}

// Parse the fragment and prove no element was created from the value and the text survived.
function assert_text(string $html, string $value, string $label) : void {
	$document = new DOMDocument();
	@$document->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
	$xpath = new DOMXPath($document);

	check($xpath->query('//b')->length === 0, $label . ' creates no element');
	check(str_contains($document->textContent, $value) || str_contains(html_entity_decode($html, ENT_QUOTES), $value), $label . ' keeps the value');

	foreach ($xpath->query('//*[@style]') as $node) {
		check(!$node->hasAttribute('x'), $label . ' keeps the style attribute closed');
	}
}

foreach ([
	[$root . '/lib/html_form.php', 'form_color_dropdown'],
	[$root . '/lib/html.php', 'draw_graph_items_list'],
	[$root . '/lib/api_aggregate.php', 'draw_aggregate_graph_items_list'],
	[$root . '/color_templates.php', 'draw_color_template_items_list'],
	[$root . '/color.php', 'form_actions'],
	[$root . '/color.php', 'color'],
] as [$file, $function]) {
	\cacti_fixture_source_function($file, $function, __NAMESPACE__);
}

$hex   = "F0'x=1 \"><b>0";
$alpha = "'x";

// Color dropdown options: data-color and the option style.
$GLOBALS['cell'] = 'FF0000';
$GLOBALS['rows'] = [['id' => 3, 'name' => 'Red', 'hex' => $hex]];
ob_start();
form_color_dropdown('color_id', 3, 'None', 0);
$html = ob_get_clean();
assert_text($html, $hex, 'color dropdown');
check(str_contains($html, "data-color='" . htmle($hex) . "'"), 'color dropdown data attribute');

// Graph and graph template item list: AREA has primary and gradient colors, LINE1 only the primary.
$item = [
	'id'                        => 1,
	'sequence'                  => 1,
	'graph_type_id'             => 7,
	'data_source_name'          => 'traffic_in',
	'text_format'               => 'Inbound',
	'value'                     => '',
	'hard_return'               => '',
	'legend'                    => '',
	'consolidation_function_id' => 1,
	'gprint_name'               => '',
	'cdef_name'                 => '',
	'vdef_name'                 => '',
	'hex'                       => $hex,
	'alpha'                     => $alpha,
	'hex2'                      => $hex,
	'alpha2'                    => $alpha,
];
ob_start();
draw_graph_items_list([$item, array_merge($item, ['id' => 2, 'sequence' => 2, 'graph_type_id' => 4])], 'graph_templates.php', 'id=1', true);
$html = ob_get_clean();
assert_text($html, $hex . $alpha, 'graph item list');
check(substr_count($html, htmle($hex . $alpha)) === 6, 'graph item list prints both colors of each item escaped');

// Aggregate item list.
$GLOBALS['rows'] = [[
	'id'                        => 1,
	'text_format'               => 'Inbound',
	'value'                     => '',
	'hard_return'               => '',
	'graph_type_id'             => 4,
	'consolidation_function_id' => 1,
	'cdef_name'                 => '',
	'vdef_name'                 => '',
	'gprint_name'               => '',
	'hex'                       => $hex,
]];
ob_start();
draw_aggregate_graph_items_list(1);
$html = ob_get_clean();
assert_text($html, $hex, 'aggregate item list');

// Color template item list, with and without a color.
ob_start();
draw_color_template_items_list([
	['color_template_item_id' => 1, 'color_template_id' => 1, 'hex' => $hex],
	['color_template_item_id' => 2, 'color_template_id' => 1, 'hex' => null],
], 'color_templates.php', 'color_template_id=1', true);
$html = ob_get_clean();
assert_text($html, $hex, 'color template item list');
check(str_contains($html, "<td style=''></td>"), 'color template item without a color keeps a closed style attribute');

// Delete confirmation list on the Colors page.
$GLOBALS['row'] = ['name' => 'Red', 'hex' => $hex];
$_POST          = $_REQUEST = ['chk_3' => 'on'];
ob_start();
form_actions();
$html = ob_get_clean();
assert_text($html, $hex, 'color delete confirmation');

// Colors list: the swatch cell style.
$GLOBALS['rows'] = [['id' => 3, 'name' => 'Red', 'hex' => $hex, 'read_only' => '', 'graphs' => 0, 'templates' => 0]];
$_REQUEST        = ['rows' => '10', 'page' => '1', 'filter' => ''];
ob_start();
color();
$html = ob_get_clean();
assert_text($html, $hex, 'color list');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
