<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTreeUserNamesFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Coverage probe for the Owner and Edited By columns of the tree list.
 *
 * Runs the checked-in tree() with a tree whose owner and last editor have
 * stored names that carry markup, and checks the cells print those names as
 * text. The escaping helpers are the checked-in ones; the page filter, the
 * database and the table helpers are stubs.
 *
 *   php TreeUserNames.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

const OWNER  = 'owner<i class="markup">a</i>';
const EDITOR = 'editor<i class="markup">b</i>';

$GLOBALS['cells']       = [];
$GLOBALS['request']     = ['rows' => 30, 'page' => 1, 'filter' => '', 'sort_column' => 'name', 'sort_direction' => 'ASC'];
$_SESSION[SESS_USER_ID] = 1;

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class CactiTableFilter {
	public string $rows_label = '';

	public function __construct(mixed ...$arguments) {
	}

	public function set_sort_array(string $column, string $direction) : void {
	}

	public function add_button(string $name, array $button) : void {
	}

	public function render() : void {
	}
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmlspecialchars(__($text, ...$args), ENT_QUOTES);
}

function grv(string $name) : mixed {
	return $GLOBALS['request'][$name] ?? '';
}

function read_config_option(string $name) : mixed {
	return 30;
}

function get_order_string() : string {
	return ' ORDER BY name ASC';
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	return [[
		'id'            => 7,
		'name'          => 'Fixture tree',
		'enabled'       => 'on',
		'locked'        => '1',
		'user_id'       => 11,
		'modified_by'   => 12,
		'sequence'      => 1,
		'last_modified' => '2026-11-25 10:00:00',
		'sites'         => 0,
		'branches'      => 0,
		'hosts'         => 0,
		'graphs'        => 0,
	]];
}

function get_total_row_data(mixed ...$arguments) : int {
	return 1;
}

function get_username(mixed $user_id = 0) : string {
	return [11 => OWNER, 12 => EDITOR][$user_id] ?? '';
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function filter_value(mixed $value, string $filter, string $href = '', string $title = '') : string {
	return htmle($value);
}

function form_selectable_cell(mixed $contents, mixed $id, mixed $width = '', string $styleclass = '', string $title = '') : bool {
	$GLOBALS['cells'][] = (string) $contents;

	return true;
}

function html_escape_url(string $url) : string {
	return htmlspecialchars($url, ENT_QUOTES);
}

function html_nav_bar(mixed ...$arguments) : string {
	return '';
}

function number_format_i18n(mixed $value, int $round = 2) : string {
	return (string) $value;
}

function form_start(mixed ...$arguments) : void {
}

function html_start_box(mixed ...$arguments) : void {
}

function html_header_sort_checkbox(mixed ...$arguments) : void {
}

function form_alternate_row(mixed ...$arguments) : void {
}

function form_checkbox_cell(mixed ...$arguments) : void {
}

function form_end_row() : void {
}

function html_end_box(mixed ...$arguments) : void {
}

function draw_actions_dropdown(mixed ...$arguments) : void {
}

function form_end(mixed ...$arguments) : void {
}

cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html.php', 'htmle', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_utility.php', 'form_selectable_ecell', __NAMESPACE__);
cacti_fixture_source_function($root . '/tree.php', 'tree', __NAMESPACE__);

ob_start();
tree();
ob_end_clean();

$owner  = htmlspecialchars(OWNER, ENT_QUOTES | ENT_HTML5);
$editor = htmlspecialchars(EDITOR, ENT_QUOTES | ENT_HTML5);

check(in_array($owner, $GLOBALS['cells'], true), 'The Owner column does not print the stored name as text');
check(in_array($editor, $GLOBALS['cells'], true), 'The Edited By column does not print the stored name as text');
check(!in_array(OWNER, $GLOBALS['cells'], true) && !in_array(EDITOR, $GLOBALS['cells'], true), 'A stored user name reached a cell unescaped');

print "PASS: the tree list prints stored user names as text\n";
