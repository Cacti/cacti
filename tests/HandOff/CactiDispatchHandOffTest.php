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
 * fails.
 *
 * cacti_dispatch() reaches the Cacti runtime through global functions that
 * lib/ owns, so the stubs standing in for them live in a probe script that
 * runs in its own process (see fixtures/cacti_dispatch_probe.php). Each
 * test below states a scenario, the probe runs it, and the JSON verdict
 * carries back the handler calls, the log lines, the AJAX denial flag and
 * the HTTP status. */

if (!file_exists(__DIR__ . '/../../lib/cacti_dispatch.php')) {
	test('cacti_dispatch hand-off: feature not present on this branch', function () {})
		->skip('lib/cacti_dispatch.php absent — feature PR #7063 not merged into develop yet');
	return;
}

require_once CACTI_PATH_TESTS . '/Helpers/IsolatedProbe.php';

function cdho_dispatch(array $scenario): array {
	return cacti_test_isolated_probe(
		__DIR__ . '/fixtures/cacti_dispatch_probe.php',
		[json_encode($scenario, JSON_THROW_ON_ERROR)]
	);
}

/* --------------------------------------------------------------------- */
/* $action request var -> action lookup                                  */
/* --------------------------------------------------------------------- */

test('string action resolves to its handler entry', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => 'edit',
		'actions' => [
			'save' => ['handler' => 'save'],
			'edit' => ['handler' => 'edit'],
		],
	]);

	expect($verdict['handler_calls'])->toBe(['save']);
});

test('array action input is normalized to the default action', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => ['x']],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => 'edit',
		'actions' => [
			'edit' => ['handler' => 'edit'],
		],
	]);

	expect($verdict['handler_calls'])->toBe(['edit']);
});

test('action with shell metacharacters is rejected before table lookup', function () {
	/* The hostile key is also present in the table so a missed
	 * sanitisation step would invoke its handler. Rejection must
	 * happen before the isset() lookup. */
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save;rm -rf /'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save'          => ['handler' => 'save'],
			'save;rm -rf /' => ['handler' => 'hostile'],
		],
	]);

	expect($verdict['handler_calls'])->toBe([]);
	expect($verdict['http_code'])->toBe(403);

	$messages = array_column($verdict['logs'], 'message');
	expect($messages)->toContain('WARNING: cacti_dispatch: unknown action "" from 127.0.0.1');
});

/* --------------------------------------------------------------------- */
/* $_SERVER['REQUEST_METHOD'] -> method assertion                        */
/* --------------------------------------------------------------------- */

test('GET request against a POST entry is rejected with method-mismatch log', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'method' => 'POST'],
		],
	]);

	expect($verdict['handler_calls'])->toBe([]);

	$messages = array_column($verdict['logs'], 'message');
	expect($messages)->toContain('WARNING: cacti_dispatch: method mismatch for action "save" (expected POST, got GET)');
});

test('absent REQUEST_METHOD with ANY entry still dispatches', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => [],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'method' => 'ANY'],
		],
	]);

	expect($verdict['handler_calls'])->toBe(['save']);
});

/* --------------------------------------------------------------------- */
/* permission flag -> handler invocation                                 */
/* --------------------------------------------------------------------- */

test('object_acl returning false suppresses handler and runs the deny path', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'object_acl' => false],
		],
	]);

	expect($verdict['handler_calls'])->toBe([]);
	expect($verdict['http_code'])->toBe(403);
});

test('object_acl returning true invokes the handler exactly once', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'object_acl' => true],
		],
	]);

	expect($verdict['handler_calls'])->toBe(['save']);
});

/* --------------------------------------------------------------------- */
/* object_acl -> fail-closed                                             */
/* --------------------------------------------------------------------- */

test('non-callable object_acl logs ERROR and denies instead of silently allowing', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'object_acl' => 'not_a_real_function_anywhere'],
		],
	]);

	expect($verdict['handler_calls'])->toBe([]);
	expect($verdict['http_code'])->toBe(403);

	$errors = array_filter(
		$verdict['logs'],
		fn ($entry) => str_starts_with($entry['message'], 'ERROR: cacti_dispatch: object_acl')
	);
	expect($errors)->not->toBeEmpty();
});

/* --------------------------------------------------------------------- */
/* AJAX vs non-AJAX denial                                               */
/* --------------------------------------------------------------------- */

test('AJAX denial calls raise_ajax_permission_denied', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => [
			'REQUEST_METHOD'        => 'GET',
			'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
		],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'object_acl' => false],
		],
	]);

	expect($verdict['ajax_denied'])->toBeTrue();
});

test('non-AJAX denial sets an explicit 403 instead of falling through to 200', function () {
	$verdict = cdho_dispatch([
		'request' => ['action' => 'save'],
		'server'  => ['REQUEST_METHOD' => 'GET'],
		'default' => '',
		'actions' => [
			'save' => ['handler' => 'save', 'object_acl' => false],
		],
	]);

	expect($verdict['ajax_denied'])->toBeFalse();
	expect($verdict['http_code'])->toBe(403);
});
