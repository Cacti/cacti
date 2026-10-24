<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\GraphMarkup;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
function aggregate_build_children_url($id) {
	return '';
}
function db_fetch_cell_prepared($sql, $params) {
	return 0;
}
function is_realm_allowed($realm) {
	return in_array($realm, $GLOBALS['graph_markup_realms'] ?? [], true);
}
function read_config_option($key) {
	return $GLOBALS['graph_markup_config'][$key] ?? '';
}
function read_user_setting($key) {
	return $GLOBALS['graph_markup_user'][$key] ?? '';
}
function __esc($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function htmle($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function api_plugin_hook($type, $args) {
	$GLOBALS['graph_markup_hook'] = $args;
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/html.php', 'graph_drilldown_icons', __NAMESPACE__);

if (!defined('CACTI_PATH_URL')) {
	define('CACTI_PATH_URL', '/cacti/');
}

// Render with the realtime and spikekill realms enabled so every action icon -
// including the conditional ones - is exercised for changed-line coverage.
$GLOBALS['graph_markup_realms'] = [25, 1043];
$GLOBALS['graph_markup_config'] = ['realtime_enabled' => 'on'];
$GLOBALS['graph_markup_user']   = ['realtime_mode' => '1'];
$GLOBALS['graph_markup_theme']  = 'modern';
ob_start();
graph_drilldown_icons(7);
$html     = ob_get_clean();
$document = new \DOMDocument();
$document->loadHTML($html);
$xpath = new \DOMXPath($document);
$menu  = $xpath->query('//span[contains(@class, "graphDrillDownMenu")]')->item(0);

if ($menu === null || $menu->getAttribute('role') !== 'button' || $menu->getAttribute('tabindex') !== '0' || $menu->getAttribute('aria-label') !== 'Graph Actions' || $menu->getAttribute('aria-expanded') !== 'false' || $menu->hasAttribute('title')) {
	throw new \RuntimeException('Graph menu accessibility or tooltip contract failed');
}

if ($GLOBALS['graph_markup_hook']['local_graph_id'] !== 7) {
	throw new \RuntimeException('Graph plugin hook identity was lost');
}

// Second render exercising the non-default realtime mode (window.open branch).
$GLOBALS['graph_markup_user'] = ['realtime_mode' => '2'];
ob_start();
graph_drilldown_icons(7);
ob_get_clean();

print json_encode(['passed' => 2], JSON_THROW_ON_ERROR) . "\n";
