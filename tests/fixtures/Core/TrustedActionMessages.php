<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiTrustedActionFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';
require $root . '/lib/html_validate.php';
require $root . '/lib/html_utility.php';

cacti_fixture_source_function($root . '/aggregate_graphs.php', 'form_actions', __NAMESPACE__);

foreach (['api_plugin_can_install', 'api_plugin_install', 'plugin_valid_version_range'] as $function) {
	cacti_fixture_source_function($root . '/lib/plugins.php', $function, __NAMESPACE__);
}

// Capture the real action's redirect before its exit would end the other cases.
class Redirect extends RuntimeException {
}

function header(string $value) : void {
	throw new Redirect($value);
}

function __(string $format, mixed ...$args) : string {
	return vsprintf($format, $args);
}

function __esc(string $format, mixed ...$args) : string {
	return html_escape(__($format, ...$args));
}

function grv(string $name) : mixed {
	return $GLOBALS['_CACTI_REQUEST'][$name];
}

function get_graph_title(int $id) : string {
	return "Graph $id & <East>";
}

function db_fetch_assoc_prepared(string $query, array $params) : array {
	if (str_contains($query, 'SELECT DISTINCT task_item_id')) {
		return [['task_item_id' => 17]];
	}

	if (str_contains($query, 'SELECT DISTINCT graph_template_id')) {
		return $GLOBALS['template_rows'];
	}

	if (str_contains($query, 'FROM aggregate_graph_templates') || str_contains($query, 'FROM reports')) {
		return [];
	}

	throw new RuntimeException('Unexpected query: ' . $query);
}

function db_fetch_cell_prepared(string $query, array $params) : string {
	if (str_contains($query, 'FROM graph_templates') && $params === [1]) {
		return 'Ports & Routes';
	}

	if (str_contains($query, 'FROM plugin_config') && $params === ['maps']) {
		return '1.0';
	}

	throw new RuntimeException('Unexpected query: ' . $query);
}

function api_plugin_get_dependencies(string $plugin) : array {
	return ['maps' => '>=2.0 <3.0'];
}

function api_plugin_installed(string $dependency) : bool {
	return $dependency === 'maps';
}

function redirected_message(callable $action, string $location, string $key) : string {
	$_SESSION[SESS_MESSAGES] = [];

	try {
		$action();
	} catch (Redirect $redirect) {
		$row = $_SESSION[SESS_MESSAGES][$key] ?? [];

		if ($redirect->getMessage() !== 'Location: ' . $location || ($row['level'] ?? null) !== MESSAGE_LEVEL_ERROR) {
			throw new RuntimeException('Unexpected redirect or missing error message for ' . $key);
		}

		return $row['message'];
	}

	throw new RuntimeException('Expected a redirect to ' . $location);
}

$no_http_headers = true;
$_SESSION        = [SESS_USER_ID => 1];
$actions         = $agg_item_actions = [];

foreach ([
	['2', [['graph_template_id' => 1], ['graph_template_id' => 2]], 'represent elements from more than one Graph Template'],
	['2', [], 'does not appear to have any matching Aggregate Templates'],
	['2', [['graph_template_id' => 1]], '&apos;Ports &amp; Routes&apos;'],
	['4', [], '<p>You currently have no reports defined.</p>'],
] as [$action, $template_rows, $expected]) {
	$_REQUEST = $_POST = ['drp_action' => $action, 'chk_11' => 'on'];
	$message  = redirected_message(__NAMESPACE__ . '\\form_actions', 'aggregate_graphs.php', 'nonmatch_templates');

	if (!str_contains($message, $expected) || ($action === '2' && !str_contains($message, '<li>Graph 11 &amp; &lt;East&gt;</li>'))) {
		throw new RuntimeException('Unexpected aggregate action message: ' . $message);
	}
}

$message  = redirected_message(static fn () => api_plugin_install('inventory'), 'plugins.php', 'dependency_check');
$expected = '&apos;Maps&apos; versions &apos;&gt;=2.0 &lt;3.0&apos; above or in range are required to install &apos;Inventory&apos;. <br><br>Plugin cannot be installed.';

if ($message !== $expected) {
	throw new RuntimeException('Unexpected plugin dependency message: ' . $message);
}

print "Trusted action messages passed\n";
