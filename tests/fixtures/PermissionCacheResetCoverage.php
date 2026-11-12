<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the permission cache resets in user_admin.php and
// user_group_admin.php. Both pages dispatch a request when loaded, and their
// handlers end in exit, so each scenario runs in its own process: the unchanged
// handler is sliced out at its original line numbers, run against stand-ins for
// the request and database helpers, and a shutdown check requires that the
// handler reached the matching reset_*_perms() call with the right id before it
// left. RequestCoverage remaps the hits to the real files.
//
// Usage: PermissionCacheResetCoverage.php            run every scenario
//        PermissionCacheResetCoverage.php FILE NAME  run one scenario

namespace PermissionCacheResetFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

const SCENARIOS = [
	'user_admin.php' => [
		['update_policies', 'update_policies', ['id' => '7', 'policy_graphs' => '1'], 'user', 7],
		['form_actions', 'associate_host', ['id' => '7', 'associate_host' => '1', 'drp_action' => '1'], 'user', 7],
		['form_actions', 'associate_graph', ['id' => '7', 'associate_graph' => '1', 'drp_action' => '2'], 'user', 7],
		['form_actions', 'associate_template', ['id' => '7', 'associate_template' => '1', 'drp_action' => '1'], 'user', 7],
		['form_actions', 'associate_groups', ['id' => '7', 'associate_groups' => '1', 'drp_action' => '2'], 'user', 7],
		['form_actions', 'associate_tree', ['id' => '7', 'associate_tree' => '1', 'drp_action' => '1'], 'user', 7],
		['form_save', 'add_graph', ['id' => '7', 'save_component_graph_perms' => '1', 'add_graph_x' => '1', 'perm_graphs' => '3'], 'user', 7],
		['perm_remove', 'perm_remove', ['id' => '3', 'user_id' => '7', 'type' => 'graph'], 'user', 7],
	],
	'user_group_admin.php' => [
		['user_group_remove', 'group_remove', ['id' => '5'], 'group', 5],
		['update_policies', 'update_policies', ['id' => '5', 'policy_hosts' => '2'], 'group', 5],
		['form_actions', 'associate_host', ['id' => '5', 'associate_host' => '1', 'drp_action' => '1'], 'group', 5],
		['form_actions', 'associate_graph', ['id' => '5', 'associate_graph' => '1', 'drp_action' => '2'], 'group', 5],
		['form_actions', 'associate_template', ['id' => '5', 'associate_template' => '1', 'drp_action' => '1'], 'group', 5],
		['form_actions', 'associate_tree', ['id' => '5', 'associate_tree' => '1', 'drp_action' => '2'], 'group', 5],
		['form_actions', 'associate_member', ['id' => '5', 'associate_member' => '1', 'drp_action' => '1'], 'user', 9],
		['perm_remove', 'perm_remove', ['id' => '3', 'group_id' => '5', 'type' => 'host'], 'group', 5],
	],
];

if (!isset($argv[2])) {
	foreach (SCENARIOS as $file => $scenarios) {
		foreach ($scenarios as $scenario) {
			$arguments = getenv('CACTI_PR_XDEBUG_EXTENSION') ? ['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')] : [];
			$process   = proc_open(array_merge([PHP_BINARY, '-d', 'display_errors=stderr'], $arguments, [__FILE__, $file, $scenario[1]]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			stream_get_contents($pipes[1]);
			$errors = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);

			if (proc_close($process) !== 0) {
				fwrite(STDERR, $file . ' ' . $scenario[1] . ': ' . $errors);
				exit(1);
			}
		}
	}
	print "PASS: permission cache reset scenarios\n";
	exit(0);
}

[$file, $name] = [$argv[1], $argv[2]];
$scenario      = null;

foreach (SCENARIOS[$file] ?? [] as $candidate) {
	if ($candidate[1] === $name) {
		$scenario = $candidate;
	}
}

if ($scenario === null) {
	fwrite(STDERR, "Unknown scenario\n");
	exit(2);
}

[$function, , $request, $kind, $expected_id] = $scenario;
$GLOBALS['request']                          = $request;
$GLOBALS['resets']                           = [];
$_POST                                       = ['chk_9' => 'on'];

// Checked before RequestCoverage publishes its report. A handler that leaves
// without resetting the cache fails here and publishes no coverage.
register_shutdown_function(static function () use ($kind, $expected_id) : void {
	if ($GLOBALS['resets'] !== [[$kind, $expected_id]]) {
		fwrite(STDERR, 'Expected one ' . $kind . ' reset for ' . $expected_id . ', saw ' . json_encode($GLOBALS['resets']) . "\n");
		exit(1);
	}
});

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

function gnrv($key, $default = '') {
	return $GLOBALS['request'][$key] ?? $default;
}

function grv($key, $default = '') {
	return gnrv($key, $default);
}

function gfrv($key, $default = 0) {
	return (int) gnrv($key, $default);
}

function isrv($key) {
	return isset($GLOBALS['request'][$key]);
}

function is_error_message() {
	return false;
}

function input_validate_input_number(...$arguments) {
}

function db_execute_prepared($sql, $params = []) {
	return true;
}

function header(string $header) : void {
}

function reset_user_perms($id) {
	$GLOBALS['resets'][] = ['user', (int) $id];
}

function reset_group_perms($id) {
	$GLOBALS['resets'][] = ['group', (int) $id];
}

cacti_fixture_source_function(dirname(__DIR__, 2) . '/' . $file, $function, __NAMESPACE__);

$handler = __NAMESPACE__ . '\\' . $function;

// user_group_remove() takes the group id; the other handlers read the request
if ($function === 'user_group_remove') {
	$handler((int) $request['id']);
} else {
	$handler();
}
