<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiSelectedItemsConfirmationFixture;

use DOMDocument;
use DOMXPath;
use RuntimeException;

/*
 * Renders the real form_continue_confirmation() and reports the value a
 * browser would post back for the selected_items hidden input. managers.php
 * builds the item array from posted checkbox names, so the keys are not
 * guaranteed to be numeric.
 */

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);

$arrays = [
	'list'   => ['3', '7'],
	'quoted' => ["IF-MIB'><b>" => ["linkDown\"</script>\n&amp;" => 1]],
];

$name = $argv[1] ?? '';

if (!isset($arrays[$name])) {
	throw new RuntimeException('unknown item array');
}

function gnrv($key, $default = '') {
	return $key === 'drp_action' ? '1' : $default;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function __($text, ...$args) {
	return $text;
}
function __esc($text, ...$args) {
	return htmlspecialchars($text, ENT_QUOTES);
}
function htmle($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES);
}
function top_header() {
}
function bottom_footer() {
}
function form_start($action, $id) {
	print "<form id='" . $id . "'>";
}
function form_end() {
	print '</form>';
}
function html_start_box(...$args) {
}
function html_end_box(...$args) {
}

cacti_fixture_source_function($root . '/lib/html.php', 'html_escape_attr', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_form.php', 'form_continue_confirmation', __NAMESPACE__);

ob_start();

form_continue_confirmation([
	'general' => [
		'page'       => 'managers.php',
		'actions'    => [1 => 'Disable'],
		'optvar'     => 'drp_action',
		'item_array' => $arrays[$name],
		'item_list'  => '',
	],
	'options' => [1 => ['message' => 'Confirm', 'cont' => 'Continue']],
]);

$output = (string) ob_get_clean();

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML('<!DOCTYPE html><html><body>' . $output . '</body></html>');

$inputs = (new DOMXPath($document))->query("//input[@name='selected_items']");

print json_encode([
	'inputs'   => $inputs->length,
	'elements' => $document->getElementsByTagName('b')->length + $document->getElementsByTagName('script')->length,
	'value'    => $inputs->length === 1 ? $inputs->item(0)->getAttribute('value') : null,
	'expected' => serialize($arrays[$name]),
], JSON_THROW_ON_ERROR) . "\n";
