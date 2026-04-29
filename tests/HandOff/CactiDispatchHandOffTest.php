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

/* Behavioural hand-off tests for lib/cacti_dispatch.php.
 *
 * These exercise the helper end-to-end: request var -> action lookup ->
 * method assertion -> permission flag -> handler invocation. The
 * source-scan suite in tests/Unit/CactiDispatchTest.php pins the literal
 * shape of each guard; the tests here pin the runtime contract so a
 * refactor that keeps the source markers but swaps the semantics still
 * fails. */

if (!file_exists(__DIR__ . '/../../lib/cacti_dispatch.php')) {
	test('cacti_dispatch hand-off: feature not present on this branch', function () {})
		->skip('lib/cacti_dispatch.php absent — feature PR #7063 not merged into develop yet');
	return;
}

/* --------------------------------------------------------------------- */
/* Stubs for the Cacti runtime functions cacti_dispatch() depends on.    */
/* They are declared once and shared across every test in this file via  */
/* the $GLOBALS['cdho_*'] mailbox so each test can read what was logged, */
/* whether the AJAX denial helper fired, and which handlers ran.         */
/* --------------------------------------------------------------------- */

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name, $default = '') {
		if (!isset($_REQUEST[$name])) {
			return $default;
		}

		return $_REQUEST[$name];
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = -1) {
		$GLOBALS['cdho_logs'][] = ['message' => $message, 'env' => $environ];
	}
}

if (!function_exists('cacti_strtoupper')) {
	function cacti_strtoupper($s) {
		return strtoupper((string) $s);
	}
}

if (!function_exists('cacti_strtolower')) {
	function cacti_strtolower($s) {
		return strtolower((string) $s);
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($realm) {
		return !empty($GLOBALS['cdho_allowed_realms'][$realm]);
	}
}

if (!function_exists('get_client_addr')) {
	function get_client_addr() {
		return '127.0.0.1';
	}
}

if (!function_exists('raise_ajax_permission_denied')) {
	function raise_ajax_permission_denied() {
		$GLOBALS['cdho_ajax_denied'] = true;
	}
}

require_once __DIR__ . '/../../lib/cacti_dispatch.php';

beforeEach(function () {
	$_REQUEST                       = [];
	$_SERVER                        = [];
	$GLOBALS['cdho_logs']           = [];
	$GLOBALS['cdho_handler_calls']  = [];
	$GLOBALS['cdho_allowed_realms'] = [];
	$GLOBALS['cdho_ajax_denied']    = false;
	http_response_code(200);
});

/* Helper that records the action name a handler was invoked with. Used
 * to prove the dispatch table actually reached (or did not reach) a
 * specific entry rather than relying on global side effects. */
function cdho_make_handler(string $tag): callable {
	return function () use ($tag) {
		$GLOBALS['cdho_handler_calls'][] = $tag;
	};
}

/* --------------------------------------------------------------------- */
/* $action request var -> action lookup                                  */
/* --------------------------------------------------------------------- */

test('string action resolves to its handler entry', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save' => ['callback' => cdho_make_handler('save')],
		'edit' => ['callback' => cdho_make_handler('edit')],
	], 'edit');

	expect($GLOBALS['cdho_handler_calls'])->toBe(['save']);
});

test('array action input is normalized to the default action', function () {
	$_REQUEST['action']        = ['x'];
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'edit' => ['callback' => cdho_make_handler('edit')],
	], 'edit');

	expect($GLOBALS['cdho_handler_calls'])->toBe(['edit']);
});

test('action with shell metacharacters is rejected before table lookup', function () {
	/* The hostile key is also present in the table so a missed
	 * sanitisation step would invoke its handler. Rejection must
	 * happen before the isset() lookup. */
	$_REQUEST['action']        = 'save;rm -rf /';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save'         => ['callback' => cdho_make_handler('save')],
		'save;rm -rf /' => ['callback' => cdho_make_handler('hostile')],
	], '');

	expect($GLOBALS['cdho_handler_calls'])->toBe([]);
	expect(http_response_code())->toBe(403);

	$messages = array_column($GLOBALS['cdho_logs'], 'message');
	expect($messages)->toContain('WARNING: cacti_dispatch: unknown action "" from 127.0.0.1');
});

/* --------------------------------------------------------------------- */
/* $_SERVER['REQUEST_METHOD'] -> method assertion                        */
/* --------------------------------------------------------------------- */

test('GET request against a POST entry is rejected with method-mismatch log', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save' => [
			'callback' => cdho_make_handler('save'),
			'method'   => 'POST',
		],
	], '');

	expect($GLOBALS['cdho_handler_calls'])->toBe([]);

	$messages = array_column($GLOBALS['cdho_logs'], 'message');
	expect($messages)->toContain('WARNING: cacti_dispatch: method mismatch for action "save" (expected POST, got GET)');
});

test('absent REQUEST_METHOD with ANY entry still dispatches', function () {
	$_REQUEST['action'] = 'save';
	unset($_SERVER['REQUEST_METHOD']);

	cacti_dispatch([
		'save' => [
			'callback' => cdho_make_handler('save'),
			'method'   => 'ANY',
		],
	], '');

	expect($GLOBALS['cdho_handler_calls'])->toBe(['save']);
});

/* --------------------------------------------------------------------- */
/* permission flag -> handler invocation                                 */
/* --------------------------------------------------------------------- */

test('object_acl returning false suppresses handler and runs the deny path', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save' => [
			'callback'   => cdho_make_handler('save'),
			'object_acl' => function () { return false; },
		],
	], '');

	expect($GLOBALS['cdho_handler_calls'])->toBe([]);
	expect(http_response_code())->toBe(403);
});

test('object_acl returning true invokes the handler exactly once', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$seen                      = [];

	cacti_dispatch([
		'save' => [
			'callback'   => function () use (&$seen) { $seen[] = 'save'; },
			'object_acl' => function () { return true; },
		],
	], '');

	expect($seen)->toBe(['save']);
});

/* --------------------------------------------------------------------- */
/* object_acl -> fail-closed                                             */
/* --------------------------------------------------------------------- */

test('non-callable object_acl logs ERROR and denies instead of silently allowing', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save' => [
			'callback'   => cdho_make_handler('save'),
			'object_acl' => 'not_a_real_function_anywhere',
		],
	], '');

	expect($GLOBALS['cdho_handler_calls'])->toBe([]);
	expect(http_response_code())->toBe(403);

	$errors = array_filter(
		$GLOBALS['cdho_logs'],
		fn ($entry) => str_starts_with($entry['message'], 'ERROR: cacti_dispatch: object_acl')
	);
	expect($errors)->not->toBeEmpty();
});

/* --------------------------------------------------------------------- */
/* AJAX vs non-AJAX denial                                               */
/* --------------------------------------------------------------------- */

test('AJAX denial calls raise_ajax_permission_denied', function () {
	$_REQUEST['action']                  = 'save';
	$_SERVER['REQUEST_METHOD']           = 'GET';
	$_SERVER['HTTP_X_REQUESTED_WITH']    = 'XMLHttpRequest';

	cacti_dispatch([
		'save' => [
			'callback'   => cdho_make_handler('save'),
			'object_acl' => function () { return false; },
		],
	], '');

	expect($GLOBALS['cdho_ajax_denied'])->toBeTrue();
});

test('non-AJAX denial sets an explicit 403 instead of falling through to 200', function () {
	$_REQUEST['action']        = 'save';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	cacti_dispatch([
		'save' => [
			'callback'   => cdho_make_handler('save'),
			'object_acl' => function () { return false; },
		],
	], '');

	expect($GLOBALS['cdho_ajax_denied'])->toBeFalse();
	expect(http_response_code())->toBe(403);
});
