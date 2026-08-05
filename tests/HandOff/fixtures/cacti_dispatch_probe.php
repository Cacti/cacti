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

/*
 * Probe for CactiDispatchHandOffTest. Run by that test through
 * cacti_test_isolated_probe(); prints a JSON verdict on stdout.
 *
 * The stubs below carry names lib/functions.php, lib/html_utility.php and
 * lib/auth.php declare, so they must not exist in the PHPUnit process: a
 * sibling test that pulls include/global.php would otherwise fatal on the
 * redeclaration. Running here keeps them off the shared process entirely.
 *
 * argv[1] is the scenario as JSON:
 *
 *   request    array  Seeds $_REQUEST.
 *   server     array  Seeds $_SERVER.
 *   default    string Default action passed to cacti_dispatch().
 *   actions    array  action name => {handler, method?, realm?, object_acl?}
 *
 * 'handler' is a tag recorded when the callback runs. 'object_acl' is either
 * a bool (wrapped in a closure returning it) or a string, which is passed
 * through untouched so the non-callable misdeclaration path stays reachable.
 */

function get_nfilter_request_var($name, $default = '') {
	if (!isset($_REQUEST[$name])) {
		return $default;
	}

	return $_REQUEST[$name];
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = -1) {
	$GLOBALS['cdho_logs'][] = ['message' => $message, 'env' => $environ];
}

function cacti_strtoupper($s) {
	return strtoupper((string) $s);
}

function cacti_strtolower($s) {
	return strtolower((string) $s);
}

function is_realm_allowed($realm) {
	return !empty($GLOBALS['cdho_allowed_realms'][$realm]);
}

function get_client_addr() {
	return '127.0.0.1';
}

function raise_ajax_permission_denied() {
	$GLOBALS['cdho_ajax_denied'] = true;
}

require_once dirname(__DIR__, 3) . '/lib/cacti_dispatch.php';

$scenario = json_decode($argv[1] ?? '', true);

if (!is_array($scenario)) {
	fwrite(STDERR, 'scenario is not valid JSON' . PHP_EOL);

	exit(2);
}

$_REQUEST                       = $scenario['request'] ?? [];
$_SERVER                        = $scenario['server'] ?? [];
$GLOBALS['cdho_logs']           = [];
$GLOBALS['cdho_handler_calls']  = [];
$GLOBALS['cdho_allowed_realms'] = $scenario['allowed_realms'] ?? [];
$GLOBALS['cdho_ajax_denied']    = false;

http_response_code(200);

$actions = [];

foreach ($scenario['actions'] ?? [] as $name => $spec) {
	$tag   = $spec['handler'];
	$entry = [
		'callback' => function () use ($tag) {
			$GLOBALS['cdho_handler_calls'][] = $tag;
		},
	];

	if (isset($spec['method'])) {
		$entry['method'] = $spec['method'];
	}

	if (isset($spec['realm'])) {
		$entry['realm'] = $spec['realm'];
	}

	if (array_key_exists('object_acl', $spec)) {
		$acl = $spec['object_acl'];

		$entry['object_acl'] = is_bool($acl)
			? function () use ($acl) { return $acl; }
			: $acl;
	}

	$actions[$name] = $entry;
}

cacti_dispatch($actions, $scenario['default'] ?? '');

echo json_encode([
	'handler_calls' => $GLOBALS['cdho_handler_calls'],
	'logs'          => $GLOBALS['cdho_logs'],
	'ajax_denied'   => $GLOBALS['cdho_ajax_denied'],
	'http_code'     => http_response_code(),
]);
