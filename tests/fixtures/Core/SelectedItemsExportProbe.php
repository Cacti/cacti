<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiSelectedItemsExportFixture;

use RuntimeException;

/*
 * Runs the real form_actions() export branch of one page and prints the
 * download URL it hands to DownloadStart(). The branch ends in exit, so each
 * page and payload needs its own process; the verdict is printed from a
 * shutdown function. Payloads are fixed here rather than taken from argv.
 */

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

$pages = [
	'automation_graph_rules' => ['automation_graph_rules_form_actions', ['drp_action' => AUTOMATION_ACTION_GRAPH_EXPORT]],
	'automation_networks'    => ['form_actions', ['drp_action' => '6']],
	'automation_snmp'        => ['form_actions', ['drp_action' => '3']],
	'automation_templates'   => ['form_actions', ['drp_action' => '2']],
	'automation_tree_rules'  => ['automation_tree_rules_form_actions', ['drp_action' => AUTOMATION_ACTION_TREE_EXPORT]],
	'data_source_profiles'   => ['form_actions', ['drp_action' => '3']],
	'host_templates'         => ['form_actions', ['drp_action' => '5', 'action_type' => 'templates']],
	'host_template_archives' => ['form_actions', ['drp_action' => '2', 'action_type' => 'archives']],
];

$payloads = [
	// what html_form.php posts for two checked rows
	'list'       => serialize(['3', '7']),
	// unserialize() ignores bytes after the array, so the sanitizer accepts this
	'trailing'   => serialize(['3', '7']) . "'\n</script><b>&x=\"1",
	// is_numeric() accepts surrounding whitespace, including line breaks
	'whitespace' => serialize(["\n3", '7 ']),
];

$page    = $argv[1] ?? '';
$payload = $argv[2] ?? '';

if (!isset($pages[$page], $payloads[$payload])) {
	throw new RuntimeException('unknown page or payload');
}

[$function, $request] = $pages[$page];

$GLOBALS['request'] = $request + ['selected_items' => $payloads[$payload]];

function gnrv($key, $default = '') {
	return $GLOBALS['request'][$key] ?? $default;
}
function grv($key, $default = '') {
	return gnrv($key, $default);
}
function gfrv($key, ...$args) {
	return gnrv($key);
}
function isrv($key) {
	return isset($GLOBALS['request'][$key]);
}
function top_header() {
}
function bottom_footer() {
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function cacti_count($value) {
	return cacti_sizeof($value);
}

$functions = $root . '/lib/functions.php';
cacti_fixture_source_function($functions, 'sanitize_unserialize_selected_items', __NAMESPACE__);
cacti_fixture_source_function($functions, 'cacti_js_encode', __NAMESPACE__);
cacti_fixture_source_function($functions, 'cacti_url', __NAMESPACE__);

$file = $root . '/' . ($page === 'host_template_archives' ? 'host_templates' : $page) . '.php';
cacti_fixture_source_function($file, $function, __NAMESPACE__);

ob_start();

register_shutdown_function(static function () : void {
	$output = (string) ob_get_clean();

	// the first DownloadStart( is the function definition, the second the call
	if (!preg_match('/DownloadStart\(([^)]*)\);/', $output, $match)) {
		fwrite(STDERR, $output);

		exit(1);
	}

	$literal = $match[1];
	$url     = json_decode($literal);
	$query   = [];

	if (is_string($url)) {
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
	}

	print json_encode([
		'literal'    => $literal,
		'url'        => $url,
		'path'       => is_string($url) ? parse_url($url, PHP_URL_PATH) : null,
		'query'      => $query,
		'round_trip' => isset($query['selected_items']) ? sanitize_unserialize_selected_items($query['selected_items']) : null,
	], JSON_THROW_ON_ERROR) . "\n";
});

(__NAMESPACE__ . '\\' . $function)();
