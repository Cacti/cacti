<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\GraphItemsList;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/**
 * Translation stub that returns the source string unchanged.
 *
 * @param string $text The string to translate.
 *
 * @return string The untranslated string.
 */
function __($text) {
	return $text;
}

/**
 * Escaping stub matching the production htmle() contract.
 *
 * @param mixed $text The value to escape.
 *
 * @return string The HTML-escaped value.
 */
function htmle($text) {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Translation and escaping stub for attribute text.
 *
 * @param string $text The string to translate and escape.
 *
 * @return string The escaped string.
 */
function __esc($text) {
	return htmle($text);
}

/**
 * URL escaping stub for the move and delete action links.
 *
 * @param string $url The URL to escape.
 *
 * @return string The escaped URL.
 */
function html_escape_url($url) {
	return htmle($url);
}

/**
 * Counting stub matching cacti_sizeof() for arrays.
 *
 * @param mixed $value The value to count.
 *
 * @return int The element count, or 0 for non-arrays.
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * Header stub; the probe only inspects the item rows.
 *
 * @param array $items The header definition.
 *
 * @return void
 */
function html_header($items) {
}

// draw_graph_items_list() includes global_arrays.php for its lookup tables, so
// point CACTI_PATH_INCLUDE at a throwaway directory holding just those arrays.
$include = sys_get_temp_dir() . '/cacti-graph-items-' . getmypid();
mkdir($include);
file_put_contents($include . '/global_arrays.php', "<?php\n\$graph_item_types = [4 => 'LINE1'];\n\$consolidation_functions = [1 => 'AVERAGE'];\n");
define('CACTI_PATH_INCLUDE', $include);

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html.php', 'draw_graph_items_list', __NAMESPACE__);

$label = '<img src=x onerror=alert(1)>';
$item  = [
	'id'                        => 1,
	'graph_type_id'             => 4,
	'data_source_name'          => 'traffic_in',
	'text_format'               => 'Inbound',
	'hard_return'               => '',
	'sequence'                  => 1,
	'consolidation_function_id' => 1,
	'legend'                    => '',
	'gprint_name'               => '',
	'cdef_name'                 => $label,
	'vdef_name'                 => $label,
	'hex'                       => 'FF0000',
	'alpha'                     => 'FF',
	'hex2'                      => '',
	'alpha2'                    => '',
];

try {
	ob_start();
	draw_graph_items_list([$item, array_merge($item, ['id' => 2, 'cdef_name' => '', 'vdef_name' => ''])], 'graph_templates_items.php', 'graph_template_id=1', true);
	$html = ob_get_clean();
} finally {
	unlink($include . '/global_arrays.php');
	rmdir($include);
}

$document = new \DOMDocument();
@$document->loadHTML($html);
$xpath = new \DOMXPath($document);
$rows  = [];

foreach ($xpath->query('//tr') as $row) {
	$cells  = $xpath->query('td', $row);
	$rows[] = [$cells->item(6)->textContent, $cells->item(7)->textContent];
}

print json_encode(['rows' => $rows, 'images' => $xpath->query('//img')->length], JSON_THROW_ON_ERROR) . "\n";
