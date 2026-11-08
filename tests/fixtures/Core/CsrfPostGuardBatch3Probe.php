<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\CsrfPostGuardBatch3;

use RuntimeException;

/*
 * Runs one CSRF-require-POST decision from production code and checks it.
 *
 * Usage: CsrfPostGuardBatch3Probe.php FILE KIND MODE, or --list for every
 * scenario. KIND is "save" (form_save), "actions" (the automation bulk-action
 * execute branch) or "dispatch" (the top-level in_array action guard). MODE is
 * "get", "tokenless", "valid", or "other" (a GET for an unguarded action).
 *
 * csrf_require_post() exits, so each decision needs its own process. The probe
 * prints a JSON verdict and exits non-zero when the decision is wrong.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

const SAVE_FILES = [
	'aggregate_graphs.php', 'auth_profile.php', 'automation_graph_rules.php', 'automation_networks.php',
	'automation_snmp.php', 'automation_templates.php', 'automation_tree_rules.php', 'cdef.php', 'color.php',
	'color_templates.php', 'data_input.php', 'data_queries.php', 'data_source_profiles.php', 'data_sources.php',
	'data_templates.php', 'gprint_presets.php', 'graph_templates.php', 'graphs.php', 'host.php',
	'host_templates.php', 'login_providers.php', 'managers.php', 'package_repos.php', 'pollers.php', 'sites.php',
	'tree.php', 'user_admin.php', 'user_group_admin.php',
];

const ACTION_FUNCTIONS = [
	'automation_graph_rules.php' => 'automation_graph_rules_form_actions',
	'automation_networks.php'    => 'form_actions',
	'automation_snmp.php'        => 'form_actions',
	'automation_templates.php'   => 'form_actions',
	'automation_tree_rules.php'  => 'automation_tree_rules_form_actions',
];

const DISPATCH_FILES = [
	'automation_graph_rules.php', 'automation_snmp.php', 'automation_templates.php', 'cdef.php',
	'color_templates.php', 'data_sources.php', 'data_templates.php', 'links.php', 'tree.php', 'vdef.php',
];

if (($argv[1] ?? '') === '--list') {
	foreach (SAVE_FILES as $file) {
		foreach (['get', 'tokenless', 'valid'] as $mode) {
			print "$file save $mode\n";
		}
	}

	foreach (array_keys(ACTION_FUNCTIONS) as $file) {
		foreach (['get', 'tokenless', 'valid'] as $mode) {
			print "$file actions $mode\n";
		}
	}

	foreach (DISPATCH_FILES as $file) {
		foreach (['get', 'tokenless', 'valid', 'other'] as $mode) {
			print "$file dispatch $mode\n";
		}
	}

	exit(0);
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);

[$file, $kind, $mode] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
$known                = [
	'save'     => SAVE_FILES,
	'actions'  => array_keys(ACTION_FUNCTIONS),
	'dispatch' => DISPATCH_FILES,
];

if (!in_array($file, $known[$kind] ?? [], true) || !in_array($mode, ['get', 'tokenless', 'valid', 'other'], true)) {
	throw new RuntimeException('Unknown CSRF guard scenario');
}

$_SERVER['REQUEST_METHOD'] = ($mode === 'get' || $mode === 'other') ? 'GET' : 'POST';
$checked                   = false;
$reached                   = false;
$action                    = '';

/**
 * Raised when execution passes the guard into the protected handler.
 */
class HandlerReached extends RuntimeException {
}

/**
 * Stands in for the CSRF token check; only "valid" mode carries a good token.
 *
 * @param bool $fatal Unused; the guard asks for a non-fatal check.
 *
 * @return bool True when the request token is valid.
 */
function csrf_check(bool $fatal = true) : bool {
	$GLOBALS['checked'] = true;

	return $GLOBALS['mode'] === 'valid';
}

/**
 * Marks the protected handler as reached and stops it before any side effect.
 *
 * @return never
 */
function handler_reached() : never {
	$GLOBALS['reached'] = true;

	throw new HandlerReached('The protected handler was reached');
}

/**
 * Request presence check. form_save() reads a request field right after the
 * guard, so in save mode the first read means the guard let the request in.
 *
 * @param string $name Request variable name.
 *
 * @return bool True for the bulk-action selected_items field.
 */
function isrv(string $name) : bool {
	if ($GLOBALS['kind'] === 'save') {
		handler_reached();
	}

	return $name === 'selected_items';
}

/**
 * Filtered request read; a form_save() read also means the guard was passed.
 *
 * @param string $name    Request variable name.
 * @param mixed  $filter  Unused filter.
 * @param array  $options Unused filter options.
 * @param mixed  $default Unused default.
 *
 * @return string A fixed request value.
 */
function gfrv(string $name, mixed $filter = null, array $options = [], mixed $default = '') : string {
	if ($GLOBALS['kind'] === 'save') {
		handler_reached();
	}

	return '1';
}

/**
 * Unfiltered request read for the bulk-action selection.
 *
 * @param string $name    Request variable name.
 * @param mixed  $default Unused default.
 *
 * @return string A serialized one-item selection.
 */
function gnrv(string $name, mixed $default = '') : string {
	return serialize([1]);
}

/**
 * Request read for the dispatcher action name.
 *
 * @param string $name Request variable name.
 *
 * @return string The scenario action.
 */
function grv(string $name) : string {
	return $name === 'action' ? $GLOBALS['action'] : '';
}

/**
 * The bulk-action execute branch decodes the selection right after its guard.
 *
 * @param string $items Serialized selection.
 *
 * @return never
 */
function sanitize_unserialize_selected_items(string $items) : never {
	handler_reached();
}

register_shutdown_function(static function () : void {
	$status   = http_response_code() ?: 200;
	$expected = [
		'get'       => [405, false, false],
		'tokenless' => [403, true, false],
		'valid'     => [200, true, true],
		'other'     => [200, false, true],
	][$GLOBALS['mode']];
	$ok = [$status, $GLOBALS['checked'], $GLOBALS['reached']] === $expected;

	print json_encode(['ok' => $ok, 'status' => $status, 'checked' => $GLOBALS['checked'], 'reached' => $GLOBALS['reached']], JSON_THROW_ON_ERROR) . "\n";

	if (!$ok) {
		fwrite(STDERR, "CSRF guard decision mismatch: {$GLOBALS['file']} {$GLOBALS['kind']} {$GLOBALS['mode']}\n");

		exit(1);
	}
});

\cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);

if ($kind === 'dispatch') {
	$source = file_get_contents($root . '/' . $file);

	if (!preg_match("/^if \\(in_array\\(grv\\('action'\\), \\[(.*?)\\], true\\)\\) \\{\\s*csrf_require_post\\(\\);\\s*\\}/ms", $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('Missing dispatcher POST guard in ' . $file);
	}
	preg_match_all("/'([^']+)'/", $match[1][0], $names);
	// An unguarded action must pass straight through to the dispatcher.
	$action    = $mode === 'other' ? 'edit' : $names[1][0];
	$line      = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-csrf-dispatch-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . $match[0][0] . "\n");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/' . $file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
	$reached = true;
} else {
	$function = $kind === 'save' ? 'form_save' : ACTION_FUNCTIONS[$file];
	\cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);

	try {
		(__NAMESPACE__ . '\\' . $function)();
	} catch (HandlerReached $expected) {
	}
}
