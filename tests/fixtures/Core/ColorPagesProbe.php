<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs unchanged functions of the color pages in their own process against
 * an SQLite database built from the scenario, and prints what they wrote:
 * the tables, the redirect, the raised messages, the fields that failed
 * validation and the page output.
 *
 * The scenario names the page functions to load, the function to call, the
 * request, the posted fields and the table rows. Page code and the library
 * helpers it uses are loaded into this namespace, so the request, database
 * and page chrome functions below stand in for the Cacti runtime.
 */

namespace CactiColorPagesFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);

$config   = array('base_path' => $root, 'is_web' => false);
$messages = array();
$_SESSION = $scenario['session'] ?? array();
$_POST    = $scenario['post'] ?? array();
$_FILES   = $scenario['files'] ?? array();

$GLOBALS['request']       = ($scenario['request'] ?? array()) + $_POST;
$GLOBALS['options']       = ($scenario['options'] ?? array()) + array('log_validation' => '', 'num_rows_table' => '30', 'default_has' => '', 'drag_and_drop' => '');
$GLOBALS['location']      = null;
$GLOBALS['raised']        = array();
$GLOBALS['checkboxes']    = array();
$GLOBALS['pushed']        = array();
$GLOBALS['affected_rows'] = 0;

$pages = array('color.php', 'color_templates.php', 'color_templates_items.php', 'graphs_items.php', 'graph_templates_items.php', 'lib/html_form.php', 'lib/aggregate.php', 'lib/api_aggregate.php', 'lib/functions.php', 'lib/html.php', 'lib/html_utility.php', 'lib/database.php');

/* the alpha keys the graph item editor offers, read from include/global_arrays.php */
$graph_color_alpha = array();

if (preg_match('/^\$graph_color_alpha = array\((.*?)\);$/ms', file_get_contents($root . '/include/global_arrays.php'), $match)) {
	preg_match_all("/'([0-9A-F]{2})'\s*=>\s*'([^']*)'/", $match[1], $keys);
	$graph_color_alpha = array_combine($keys[1], $keys[2]);
}

$helpers = array(
	array('lib/functions.php', 'cacti_sizeof'),
	array('lib/functions.php', 'cacti_count'),
	array('lib/functions.php', 'array_rekey'),
	array('lib/functions.php', 'form_input_validate'),
	array('lib/functions.php', 'is_error_message'),
	array('lib/functions.php', 'sanitize_unserialize_selected_items'),
	array('lib/functions.php', 'filter_value'),
	array('lib/html.php', 'html_escape'),
	array('lib/html_utility.php', 'form_selectable_cell'),
	array('lib/database.php', 'array_to_sql_or'),
);

foreach (array_merge($helpers, $scenario['slice']) as $slice) {
	if (!in_array($slice[0], $pages, true)) {
		throw new \RuntimeException('Not a page of the color feature: ' . $slice[0]);
	}

	\cacti_fixture_source_function($root . '/' . $slice[0], $slice[1], __NAMESPACE__);
}

$graph_item_types = array(1 => 'COMMENT', 2 => 'HRULE', 3 => 'VRULE', 4 => 'LINE1', 5 => 'LINE2', 6 => 'LINE3', 7 => 'AREA', 8 => 'STACK', 9 => 'GPRINT', 10 => 'LEGEND');

$db = new \PDO('sqlite::memory:');
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
$db->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
$db->exec("CREATE TABLE colors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT DEFAULT '', hex TEXT NOT NULL DEFAULT '', read_only TEXT DEFAULT '', UNIQUE (hex))");
$db->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY AUTOINCREMENT, graph_template_id INTEGER DEFAULT 0, local_graph_id INTEGER DEFAULT 0, color_id INTEGER DEFAULT 0, alpha TEXT DEFAULT \'FF\', graph_type_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0)');
$db->exec("CREATE TABLE color_templates (color_template_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT DEFAULT '')");
$db->exec('CREATE TABLE color_template_items (color_template_item_id INTEGER PRIMARY KEY AUTOINCREMENT, color_template_id INTEGER DEFAULT 0, color_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE aggregate_graph_templates_item (aggregate_template_id INTEGER, graph_templates_item_id INTEGER, color_template INTEGER DEFAULT 0)');
$db->exec("CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY, aggregate_template_id INTEGER DEFAULT 0, template_propogation TEXT DEFAULT '', local_graph_id INTEGER DEFAULT 0)");
$db->exec('CREATE TABLE aggregate_graphs_graph_item (aggregate_graph_id INTEGER, graph_templates_item_id INTEGER, color_template INTEGER DEFAULT 0)');

foreach ($scenario['db'] ?? array() as $table => $rows) {
	foreach ($rows as $row) {
		$statement = $db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, cacti_sizeof($row), '?')) . ')');
		$statement->execute(array_values($row));
	}
}

register_shutdown_function(function () use ($db) {
	$output = '';

	while (ob_get_level() > 0) {
		$output = ob_get_clean() . $output;
	}

	$tables = array();

	foreach (array('colors', 'graph_templates_item', 'color_templates', 'color_template_items') as $table) {
		$tables[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
	}

	print json_encode(array(
		'location'   => $GLOBALS['location'],
		'raised'     => $GLOBALS['raised'],
		'errors'     => array_keys($_SESSION['sess_error_fields'] ?? array()),
		'checkboxes' => $GLOBALS['checkboxes'],
		'pushed'     => $GLOBALS['pushed'],
		'result'     => $GLOBALS['result'] ?? null,
		'session'    => array_diff_key($_SESSION, array('sess_error_fields' => true, 'sess_field_values' => true)),
		'tables'     => $tables,
		'output'     => $output,
	), JSON_THROW_ON_ERROR);
});

ob_start();

$GLOBALS['result'] = call_user_func_array(__NAMESPACE__ . '\\' . $scenario['call'], $scenario['args'] ?? array());

exit;

/* ---------------- request ---------------- */

function get_request_var($name, $default = '') {
	return $GLOBALS['request'][$name] ?? $default;
}

function get_nfilter_request_var($name, $default = '') {
	return $GLOBALS['request'][$name] ?? $default;
}

function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = array()) {
	if (!isset($GLOBALS['request'][$name])) {
		return '';
	}

	if ($filter === FILTER_SANITIZE_SPECIAL_CHARS) {
		$GLOBALS['request'][$name] = filter_var($GLOBALS['request'][$name], FILTER_SANITIZE_SPECIAL_CHARS);
	} elseif ($filter === FILTER_VALIDATE_INT && $GLOBALS['request'][$name] !== '' && filter_var($GLOBALS['request'][$name], FILTER_VALIDATE_INT) === false) {
		throw new \RuntimeException('Request validation stopped the page for ' . $name);
	}

	return $GLOBALS['request'][$name];
}

function isset_request_var($name) {
	return isset($GLOBALS['request'][$name]);
}

function isempty_request_var($name) {
	return empty($GLOBALS['request'][$name]);
}

function set_request_var($name, $value) {
	$GLOBALS['request'][$name] = $value;
}

function html_escape_request_var($name) {
	return html_escape(get_request_var($name));
}

function validate_store_request_vars($filters, $session = '') {
	foreach ($filters as $name => $filter) {
		if (!isset($GLOBALS['request'][$name]) && isset($filter['default'])) {
			$GLOBALS['request'][$name] = $filter['default'];
		}
	}
}

function input_validate_input_number($value) {
	if (!is_numeric($value)) {
		throw new \RuntimeException('Not a number: ' . $value);
	}
}

function csrf_require_post() {
}

/* ---------------- database ---------------- */

function db() {
	global $db;

	return $db;
}

function db_execute($sql) {
	$GLOBALS['affected_rows'] = db()->exec($sql);

	return true;
}

function db_execute_prepared($sql, $params = array()) {
	try {
		$statement = db()->prepare($sql);
		$statement->execute($params);
	} catch (\PDOException $e) {
		return false;
	}

	$GLOBALS['affected_rows'] = $statement->rowCount();

	return true;
}

function db_affected_rows() {
	return $GLOBALS['affected_rows'];
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	$statement = db()->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll();
}

function db_fetch_assoc($sql) {
	return db_fetch_assoc_prepared($sql);
}

function db_fetch_row_prepared($sql, $params = array()) {
	$rows = db_fetch_assoc_prepared($sql, $params);

	return cacti_sizeof($rows) ? $rows[0] : array();
}

function db_fetch_cell_prepared($sql, $params = array()) {
	$row = db_fetch_row_prepared($sql, $params);

	return cacti_sizeof($row) ? reset($row) : false;
}

function db_fetch_cell($sql) {
	return db_fetch_cell_prepared($sql);
}

function db_qstr($value) {
	return db()->quote((string) $value);
}

function db_fetch_insert_id() {
	return db()->lastInsertId();
}

/* Upsert like the MySQL INSERT ... ON DUPLICATE KEY UPDATE that sql_save() issues. */
function sql_save($array, $table, $key = 'id', $autoinc = true) {
	if (empty($array[$key])) {
		unset($array[$key]);
	}

	$columns = array_keys($array);
	$updates = array_map(function ($column) {
		return $column . ' = excluded.' . $column;
	}, $columns);

	try {
		db_execute_prepared('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, cacti_sizeof($columns), '?')) . ') ON CONFLICT DO UPDATE SET ' . implode(', ', $updates), array_values($array)) || throw new \PDOException('save failed');
	} catch (\PDOException $e) {
		return isset($array[$key]) ? $array[$key] : false;
	}

	return isset($array[$key]) ? $array[$key] : db()->lastInsertId();
}

function get_next_sequence($id, $field, $table_name, $group_query, $key_field = 'id') {
	return 1 + (int) db_fetch_cell('SELECT MAX(' . $field . ') FROM ' . $table_name . ' WHERE ' . $group_query);
}

function get_sequence($id, $field, $table_name, $group_query) {
	return get_next_sequence($id, $field, $table_name, $group_query);
}

/* ---------------- runtime ---------------- */

function read_config_option($name, $force = false) {
	return $GLOBALS['options'][$name] ?? '';
}

function raise_message($id, $message = '', $level = MESSAGE_LEVEL_NONE) {
	$GLOBALS['raised'][] = array('id' => $id, 'message' => $message, 'level' => $level);
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
}

function cacti_debug_backtrace($entry = '', $html = false, $record = true, $limit = 0, $skip = 0) {
}

function header($value) {
	$GLOBALS['location'] = $value;
}

function cacti_header($url) {
	$GLOBALS['location'] = 'Location: ' . $url;
}

function kill_session_var($name) {
	unset($_SESSION[$name]);
}

function push_out_aggregates($aggregate_template_id, $local_graph_id = 0) {
	$GLOBALS['pushed'][] = array($aggregate_template_id, $local_graph_id);

	return true;
}

function __($text, ...$args) {
	return cacti_sizeof($args) ? vsprintf($text, $args) : $text;
}

function __esc($text, ...$args) {
	return html_escape(__($text, ...$args));
}

function __n($singular, $plural, $number) {
	return $number == 1 ? $singular : $plural;
}

function number_format_i18n($number, $decimals = null) {
	return (string) $number;
}

/* ---------------- page chrome ---------------- */

final class CactiSecureHeaders {
	public static function getNonceAttribute() {
		return '';
	}
}

function top_header() {
}

function bottom_footer() {
}

function form_start($action, $id = '', $multipart = false) {
	print '<form>';
}

function form_end() {
	print '</form>';
}

function html_start_box($title = '', $width = '', $div = '', $cell_padding = '', $align = '', $add_text = '') {
	print '<table><caption>' . $title . '</caption>';
}

function html_end_box($trailing_br = true, $div = false) {
	print '</table>';
}

function html_header($display_text, $last_item_colspan = 1) {
}

function html_header_sort_checkbox($display_text, $sort_column, $sort_direction, $include_form = true) {
}

function html_search_filter($filter = '', $label = '') {
}

function html_rows_filter($label = '') {
}

function html_nav_bar($base_url, $max_pages, $current_page, $rows_per_page, $total_rows, $colspan = 30, $object = '', $page_var = 'page', $return_to = '') {
	return '<nav data-total="' . $total_rows . '"></nav>';
}

function get_order_string() {
	return 'ORDER BY ' . get_request_var('sort_column') . ' ' . get_request_var('sort_direction');
}

function form_alternate_row($id = '', $light = false, $disabled = false) {
	print '<tr id="' . $id . '">';
}

function form_end_row() {
	print '</tr>';
}

function form_selectable_ecell($contents, $id, $width = '', $style_or_class = '', $title = '') {
	form_selectable_cell(html_escape($contents), $id, $width, $style_or_class, $title);
}

function form_checkbox_cell($title, $id, $disabled = false) {
	$GLOBALS['checkboxes'][$id] = $disabled;
}

function draw_actions_dropdown($actions, $delete_action = 1) {
}

function escape_page_action($actions, $action) {
	return '';
}

function draw_edit_form($array) {
	print '<fieldset>' . json_encode($array['fields'] ?? array()) . '</fieldset>';
}

function inject_form_variables($form_array, $arg1 = array()) {
	return $arg1;
}

function form_save_button($cancel_url, $force_type = '', $key_field = 'id', $ajax = true) {
}

function form_hidden_box($form_name, $form_previous_value, $form_default_value, $in_form = false) {
	print '<input type="hidden" name="' . $form_name . '" value="' . html_escape($form_previous_value) . '">';
}

function html_create_list($form_data, $column_display, $column_id, $form_previous_value) {
}
