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

function _(string $text) : string {
	return $text;
}

function gnrv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function grv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function gfrv(string $name, int $filter = FILTER_VALIDATE_INT, array $options = []) : mixed {
	return isset($_REQUEST[$name]) ? filter_var($_REQUEST[$name], $filter, $options) : null;
}

function read_config_option(string $name) : string {
	return '';
}

function raise_message(mixed $id, string $message = '', int $level = 0) : bool {
	$GLOBALS['messages'][] = [$id, $message, $level];

	return true;
}

function sql_save(array $save, string $table) : mixed {
	$GLOBALS['saved'][] = $save;

	return $GLOBALS['save_result'];
}

function header(string $value) : void {
	throw new Redirect($value);
}

function db_qstr(mixed $value) : string {
	return "'" . addslashes((string) $value) . "'";
}

function array_to_sql_or(array $array, string $column) : string {
	return '(' . $column . ' IN(' . implode(',', array_map(__NAMESPACE__ . '\\db_qstr', $array)) . '))';
}

function db_execute(string $sql) : bool {
	return fake($sql, []);
}

function db_fetch_row(string $sql) : mixed {
	return fake($sql, []);
}

function db_fetch_cell(string $sql) : mixed {
	return fake($sql, []);
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

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
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

// Submit the edit form and return the redirect it ends with.
function save(array $request, array $fake = [], mixed $result = 9) : string {
	$_REQUEST               = $request + (isset($request['save_component_import']) ? [] : ['save_component_color' => '1']);
	$_SESSION               = [];
	$GLOBALS['db']          = [];
	$GLOBALS['fake']        = $fake;
	$GLOBALS['saved']       = [];
	$GLOBALS['messages']    = [];
	$GLOBALS['save_result'] = $result;

	try {
		form_save();
	} catch (Redirect $redirect) {
		return $redirect->getMessage();
	}

	throw new RuntimeException('form_save() ended without a redirect');
}

\cacti_fixture_source_function($root . '/color.php', 'color_import_processor', __NAMESPACE__);
\cacti_fixture_source_function($root . '/color.php', 'form_save', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/functions.php', 'form_input_validate', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/functions.php', 'is_error_message', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/functions.php', 'sanitize_unserialize_selected_items', __NAMESPACE__);
\cacti_fixture_source_function($root . '/color.php', 'form_actions', __NAMESPACE__);

// Confirm a bulk action and return the redirect it ends with.
function action(array $request, array $fake) : string {
	$_REQUEST            = $request;
	$GLOBALS['db']       = [];
	$GLOBALS['fake']     = $fake;
	$GLOBALS['messages'] = [];

	try {
		form_actions();
	} catch (Redirect $redirect) {
		return $redirect->getMessage();
	}

	throw new RuntimeException('form_actions() ended without a redirect');
}

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

// The edit form accepts exactly six hex digits and stores them in upper case.
foreach (['FFF', 'FF00001', "FF0000\n", 'FF00GG', ''] as $hex) {
	$location = save(['id' => '0', 'name' => 'Short', 'hex' => $hex]);
	check($location === 'Location: color.php?action=edit&id=0' && $GLOBALS['saved'] === [], 'hex ' . json_encode($hex) . ' rejected');
	check(isset($_SESSION[SESS_ERROR_FIELDS]['hex']), 'hex ' . json_encode($hex) . ' marked as the failing field');
}

$location = save(['id' => '0', 'name' => 'Pink', 'hex' => 'ff00aa'], ['SELECT id FROM colors' => false]);
check($location === 'Location: color.php' && $GLOBALS['saved'][0]['hex'] === 'FF00AA', 'six digit hex saved in upper case');

// A Named Color is read only whatever the request says, and the request cannot make a color read only.
$location = save(['id' => '1', 'name' => 'Renamed', 'hex' => '000001', 'read_only' => ''], ['SELECT read_only' => ['read_only' => 'on']]);
check($location === 'Location: color.php?action=edit&id=1' && $GLOBALS['saved'] === [], 'named color not saved');
check($GLOBALS['messages'][0][0] === 'color_read_only' && $GLOBALS['messages'][0][2] === MESSAGE_LEVEL_ERROR, 'named color save reported');

$location = save(['id' => '7', 'name' => 'Teal', 'hex' => '008080', 'read_only' => 'on', 'hidden_name' => 'Other'], ['SELECT read_only' => ['read_only' => ''], 'SELECT id FROM colors' => false]);
check($location === 'Location: color.php' && $GLOBALS['saved'] === [['id' => '7', 'name' => 'Teal', 'hex' => '008080']], 'editable color saved without a read only flag');
check($GLOBALS['db'][1][1] === ['008080', '7'], 'hex owner looked up excluding the color itself');

// A hex owned by another color is refused instead of renaming that color.
$location = save(['id' => '0', 'name' => 'Copy', 'hex' => '000000'], ['SELECT id FROM colors' => 1]);
check($location === 'Location: color.php?action=edit&id=0' && $GLOBALS['saved'] === [], 'duplicate hex not saved');
check(isset($_SESSION[SESS_ERROR_FIELDS]['hex']) && $GLOBALS['messages'][0][0] === 'color_hex_exists', 'duplicate hex reported on the field');

$location = save(['id' => '7', 'name' => 'Teal', 'hex' => '008080'], ['SELECT read_only' => ['read_only' => ''], 'SELECT id FROM colors' => false], false);
check($location === 'Location: color.php' && $GLOBALS['messages'][0][0] === 2, 'failed save reported');

// An import that may update rows keeps the name of a Named Color.
import(["name,hex\n", "Red,FF0000\n"], ['ON DUPLICATE KEY UPDATE' => true], ['allow_update' => 'on']);
check(str_contains($GLOBALS['db'][0][0], "name=IF(read_only = 'on', name, VALUES(name))"), 'import update keeps named color names');

// Delete keeps Named Colors and colors in use whatever ids are posted, and says so.
$location = action(['drp_action' => '1', 'selected_items' => serialize(['3', '4'])], ['DELETE FROM colors' => true, 'SELECT COUNT(*)' => 1]);
$delete   = $GLOBALS['db'][0][0];
check($location === 'Location: color.php' && str_starts_with($delete, "DELETE FROM colors WHERE (id IN('3','4'))"), 'delete limited to the selected ids');

foreach ([
	"IFNULL(read_only, '') != 'on'",
	'gti.color_id = colors.id',
	'gti.color2_id = colors.id',
	'FROM color_template_items AS cti WHERE cti.color_id = colors.id',
	"s.name IN ('graph_unknown_color', 'business_hours_color') AND s.value = colors.id",
] as $guard) {
	check(str_contains($delete, $guard), 'delete guarded by ' . $guard);
}
check($GLOBALS['messages'] === [['color_delete_kept', '1 of the selected Colors were not deleted because they are Named Colors or still in use.', MESSAGE_LEVEL_WARN]], 'kept colors reported');

action(['drp_action' => '1', 'selected_items' => serialize(['3'])], ['DELETE FROM colors' => true, 'SELECT COUNT(*)' => 0]);
check($GLOBALS['messages'] === [], 'nothing reported when every color was deleted');

// Import without a file reports it instead of ending on a blank page; a real upload is processed.
foreach ([
	'no file chosen' => ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
	'upload error'   => ['name' => 'c.csv', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0],
	'missing field'  => null,
] as $label => $file) {
	$_FILES              = $file === null ? [] : ['import_file' => $file];
	$location            = save(['save_component_import' => '1'], [], 0);
	check($location === 'Location: color.php?action=import' && $GLOBALS['messages'] === [[35, '', 0]], 'import with ' . $label . ' reported');
}

$csv = tempnam(sys_get_temp_dir(), 'cacti-color-import-');
file_put_contents($csv, "name,hex\nRed,FF0000\n");
$_FILES   = ['import_file' => ['name' => 'c.csv', 'tmp_name' => $csv, 'error' => UPLOAD_ERR_OK, 'size' => 17]];
$location = save(['save_component_import' => '1'], ['SELECT *' => [], 'INSERT INTO colors' => true], 0);
unlink($csv);
check($location === 'Location: color.php?action=import' && ($_SESSION['import_debug_info'][1] ?? '') === 'INSERT SUCCEEDED: (FF0000,Red)', 'uploaded file imported');

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
