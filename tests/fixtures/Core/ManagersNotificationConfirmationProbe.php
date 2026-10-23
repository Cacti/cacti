<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ManagersConfirmation;

use DOMDocument;
use DOMXPath;

/*
 * Renders the real managers.php notification confirmation form and reports
 * the value a browser would post back for the selected_items hidden input.
 * The item array is keyed by MIB and notification names taken from the
 * posted checkbox names, so it is not limited to numeric ids.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';

$rows = array(
	'plain'  => array('chk_IF-MIB__linkDown' => 'on', 'chk_IF-MIB__linkUp' => 'on'),
	'quoted' => array("chk_IF-MIB'><b>__linkDown\"</script>\n&amp;" => 'on'),
	'action-quoted' => array('chk_IF-MIB__linkDown' => 'on'),
);

$name = $argv[1] ?? '';

if (!isset($rows[$name])) {
	throw new \RuntimeException('unknown notification rows');
}

$_POST = $rows[$name];
$confirmationAction = $name === 'action-quoted' ? "2' onfocus='alert(1)' data-note='&quot;" : '2';

function isset_request_var($name) {
	return false;
}
function get_nfilter_request_var($name, $default = '') {
	global $confirmationAction;

	return $name === 'drp_action' ? $confirmationAction : ($name === 'id' ? '1' : $default);
}
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = array()) {
	return get_nfilter_request_var($name);
}
function escape_page_action($actions, $action) {
	return '';
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function __($text, ...$args) {
	return $text;
}
function __esc($text, ...$args) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function top_header() {
}
function bottom_footer() {
}
function form_start($action) {
	print '<form>';
}
function form_end() {
	print '</form>';
}
function html_start_box(...$args) {
	print '<table>';
}
function html_end_box(...$args) {
	print '</table>';
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'cacti_html_context_escape', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html.php', 'html_escape', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/managers.php', 'form_actions', __NAMESPACE__);

ob_start();
form_actions();
$output = (string) ob_get_clean();

$expected = array();

foreach ($rows[$name] as $key => $value) {
	list($mib, $notification) = explode('__', substr($key, 4));
	$expected[$mib][$notification] = 1;
}

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<!DOCTYPE html><html><body>' . $output . '</body></html>');

$inputs = (new DOMXPath($document))->query("//input[@name='selected_items']");
$actions = (new DOMXPath($document))->query("//input[@name='drp_action']");

print json_encode(array(
	'inputs'   => $inputs->length,
	'elements' => $document->getElementsByTagName('b')->length + $document->getElementsByTagName('script')->length,
	'value'    => $inputs->length === 1 ? $inputs->item(0)->getAttribute('value') : null,
	'expected' => serialize($expected),
	'action' => $actions->length === 1 ? $actions->item(0)->getAttribute('value') : null,
	'expected_action' => $confirmationAction,
	'event_attributes' => (new DOMXPath($document))->query("//@*[starts-with(name(), 'on')]")->length,
), JSON_THROW_ON_ERROR) . "\n";
