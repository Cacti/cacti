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

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';

// Exercise the production aggregate renderer and HTML escaping helpers without a database.
require_once dirname(__DIR__, 3) . '/lib/html.php';
require_once dirname(__DIR__, 3) . '/lib/api_aggregate.php';

define('POLLER_ID', 1);
define('POLLER_VERBOSITY_DEVDBG', 5);
$graph_item_types        = [1 => 'GPRINT'];
$consolidation_functions = [1 => 'AVERAGE'];
$mode                    = $argv[1] ?? '';
$label                   = $mode === 'empty' ? null : ($argv[2] ?? '<em>Literal label</em>');

// draw_graph_items_list() includes global_arrays.php, which needs a full install; an empty
// stand-in keeps the item type and consolidation arrays defined above.
$include_dir = sys_get_temp_dir() . '/cacti-label-probe-' . getmypid();
@mkdir($include_dir);
file_put_contents($include_dir . '/global_arrays.php', '<?php' . PHP_EOL);
register_shutdown_function(function () use ($include_dir) : void {
	@unlink($include_dir . '/global_arrays.php');
	@rmdir($include_dir);
});
define('CACTI_PATH_INCLUDE', $include_dir);

function db_fetch_assoc(string $sql): array {
	global $label;

	if (str_contains($sql, 'FROM color_templates')) {
		return [];
	}

	return [[
		'id'            => 1, 'text_format' => 'Traffic', 'value' => '', 'hard_return' => '',
		'graph_type_id' => 1, 'consolidation_function_id' => 1,
		'gprint_name'   => $label, 'cdef_name' => $label, 'vdef_name' => $label, 'hex' => ''
	]];
}
function cacti_log(...$args): void {
}
function cacti_sizeof($value): int {
	return count($value);
}
function cacti_count($value): int {
	return count($value);
}
function __(string $value, ...$args): string {
	return $args ? sprintf($value, ...$args) : $value;
}
function __esc(string $value): string {
	return htmle($value);
}
function is_cacti_release(): bool {
	return true;
}
function get_current_page(): string {
	return 'aggregate_graphs.php';
}
function ierv(string $key): bool {
	return true;
}
function isrv(string $key): bool {
	return false;
}
function api_plugin_hook_function(string $name, mixed $value): mixed {
	return $value;
}
function form_alternate_row(): void {
	print '<tr>';
}
function form_hidden_box(...$args): void {
}
function form_get_table_id(mixed $increment = false): string {
	return 'graph_templates:0';
}
function form_process_visible_display_text(string $table_id, array $display_text): array {
	return $display_text;
}

ob_start();

if ($mode === 'graph') {
	// The regular graph item list puts its GPRINT, CDEF and VDEF names in the same columns.
	draw_graph_items_list([[
		'id'            => 1, 'sequence' => 1, 'text_format' => 'Traffic', 'value' => '', 'hard_return' => '', 'legend' => '',
		'graph_type_id' => 1, 'consolidation_function_id' => 1, 'data_source_name' => 'traffic_in',
		'gprint_name'   => $label, 'cdef_name' => $label, 'vdef_name' => $label, 'hex' => ''
	]], 'graph_templates.php', 'graph_template_id=1', true);
} else {
	draw_aggregate_graph_items_list(1);
}
$html     = ob_get_clean();
$document = new DOMDocument();
@$document->loadHTML($html);
$xpath = new DOMXPath($document);
$cells = $xpath->query('//tr[td]/td');
$names = [];

// The regular list has an extra Legend column ahead of the definition names.
foreach ($mode === 'graph' ? [5, 6, 7] : [4, 5, 6] as $index) {
	$names[] = $cells->item($index)->textContent;
}

if ($names !== array_fill(0, 3, $label ?? '') || $xpath->query('//td//em')->length !== 0) {
	throw new RuntimeException('Graph item definition names were interpreted as markup or changed.');
}
print json_encode(['names' => $names, 'elements' => $xpath->query('//td//em')->length]);
