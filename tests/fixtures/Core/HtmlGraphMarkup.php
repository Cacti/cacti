<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\HtmlGraphMarkup;

use DOMDocument;
use DOMXPath;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html.php', 'graph_drilldown_display_class', __NAMESPACE__);

/*
 * Request/changed-line coverage probe for the Details and Time View drilldown
 * menus in lib/html_graph.php.  html_graph_single_view() and html_graph_zoom()
 * emit the three-bar menu inline and are only reached from authenticated page
 * requests the unit/e2e suites do not drive, so the production functions are
 * loaded in isolation with their I/O boundaries stubbed.  The rendered markup
 * is asserted so the PHP output - not just handwritten browser HTML - is
 * exercised.
 */

if (!defined('CACTI_PATH_URL')) {
	define('CACTI_PATH_URL', '/cacti/');
}

$GLOBALS['hgm_request'] = [
	'local_graph_id' => 1,
	'rra_id'         => 1,
	'action'         => 'view',
	'view_type'      => '',
	'thumbnails'     => 'false',
	'business_hours' => '',
];

$GLOBALS['hgm_rras'] = [
	['id' => 1, 'timespan' => 86400, 'step' => 300, 'rows' => 600, 'steps' => 1, 'rrd_step' => 300, 'name' => 'Daily (5 Minute Average)'],
];

$GLOBALS['hgm_graph'] = [
	'local_graph_id'    => 1,
	'width'             => 700,
	'height'            => 200,
	'title_cache'       => 'Test Graph Title',
	'graph_template_id' => 5,
	'host_id'           => 1,
	'disabled'          => '',
];

// --- Stubbed I/O boundaries -------------------------------------------------
function html_graph_single_validate() {}
function html_graph_check_access() {}
function html_graph_get_info() { return ['rras' => $GLOBALS['hgm_rras'], 'title' => $GLOBALS['hgm_graph']['title_cache']]; }
function get_current_page() { return 'graph.php'; }
function top_header() {}
function top_graph_header() {}
function general_header() {}
function bottom_footer() {}
function html_start_box() {}
function html_end_box() {}
function api_plugin_hook_function($name, $data = []) { return $data; }
function api_plugin_hook($name, $data = []) { return $data; }
function aggregate_build_children_url() { return ''; }
function is_realm_allowed($realm) { return true; }
function read_user_setting($name, $default = '') {
	if ($name === 'graph_actions_display') {
		return $GLOBALS['hgm_graph_actions'] ?? $default;
	}

	if ($name === 'page_refresh') {
		return 30;
	}

	if ($name === 'show_graph_title') {
		return 'on';
	}

	return $default;
}
function read_config_option($name) { return ''; }
function cacti_sizeof($value) { return is_countable($value) ? count($value) : 0; }
function grv($name, $default = '') { return $GLOBALS['hgm_request'][$name] ?? $default; }
function gnrv($name, $default = '') { return $GLOBALS['hgm_request'][$name] ?? $default; }
function isrv($name) { return isset($GLOBALS['hgm_request'][$name]); }
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) { return (int) ($GLOBALS['hgm_request'][$name] ?? 0); }
function htmle($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function htmlerv($name) { return htmlspecialchars((string) ($GLOBALS['hgm_request'][$name] ?? ''), ENT_QUOTES, 'UTF-8'); }
function __esc($text, ...$args) { return htmlspecialchars($args !== [] ? vsprintf($text, $args) : $text, ENT_QUOTES, 'UTF-8'); }
function db_fetch_cell_prepared($sql, $params = []) { return 300; }
function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'data_source_profiles_rra')) {
		return ['id' => 1, 'step' => 300, 'steps' => 1, 'name' => 'Daily (5 Minute Average)', 'rows' => 600];
	}

	return $GLOBALS['hgm_graph'];
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html_graph.php', 'html_graph_single_view', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html_graph.php', 'html_graph_zoom', __NAMESPACE__);

function hgm_render(callable $view) : DOMXPath {
	$html = '';

	ob_start();

	try {
		$view();
	} finally {
		$html = ob_get_clean();
	}

	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$document->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
	libxml_use_internal_errors($previous);

	return new DOMXPath($document);
}

$passed = 0;

// --- Time View (html_graph_single_view) -------------------------------------
$xpath = hgm_render(__NAMESPACE__ . '\\html_graph_single_view');

$menu = $xpath->query("//div[contains(@class, 'graphDrillDownToggle')]//span[contains(@class, 'graphDrillDownMenu')]")->item(0);

if ($menu === null || $menu->getAttribute('role') !== 'button' || $menu->getAttribute('aria-label') !== 'Graph Actions') {
	throw new RuntimeException('Time View menu button missing from html_graph_single_view output');
}

if ($xpath->query("//div[contains(@class, 'graphDrillDownToggle')]//div[contains(@class, 'iconWrapper')]//a[contains(@class, 'utils')]")->length !== 1) {
	throw new RuntimeException('Time View action icons are not wrapped in the hidden iconWrapper');
}

$passed++;

// --- Details (html_graph_zoom) ----------------------------------------------
$xpath = hgm_render(__NAMESPACE__ . '\\html_graph_zoom');

if ($xpath->query("//div[contains(@class, 'graphDrillDownToggle')]//span[contains(@class, 'graphDrillDownMenu')]")->length !== 1) {
	throw new RuntimeException('Details menu button missing from html_graph_zoom output');
}

// The title must be a direct child of graphWrapperOuter (outside the toggle's
// position:relative / fit-content positioning container), not nested beside the
// graph and toggle in the first inner div.
if ($xpath->query("//div[contains(@class, 'graphWrapperOuter')]/div[normalize-space(.) = 'Test Graph Title']")->length !== 1) {
	throw new RuntimeException('Details graph title is not rendered outside the positioning container');
}

if ($xpath->query("//div[contains(@class, 'graphWrapperOuter')]/div[contains(@class, 'graphDrillDownToggle')]")->length !== 0) {
	throw new RuntimeException('Details toggle should be nested inside the positioning container, not a direct child');
}

$passed++;

// Both detail renderers must honour the preference and reject unknown modes.
foreach (['icons' => 'graphDrillDownAlways', 'menu' => 'graphDrillDownToggle', 'unknown' => 'graphDrillDownToggle'] as $mode => $class) {
	$GLOBALS['hgm_graph_actions'] = $mode;
	foreach (['html_graph_single_view', 'html_graph_zoom'] as $view) {
		$xpath = hgm_render(__NAMESPACE__ . '\\' . $view);
		if ($xpath->query("//div[contains(@class, '$class')]//div[contains(@class, 'iconWrapper')]//a")->length === 0) {
			throw new RuntimeException("$view did not render actions for $mode");
		}
		$other = $class === 'graphDrillDownAlways' ? 'graphDrillDownToggle' : 'graphDrillDownAlways';
		if ($xpath->query("//div[contains(@class, '$other')]")->length !== 0) {
			throw new RuntimeException("$view mixed action display modes");
		}
		$passed++;
	}
}

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
