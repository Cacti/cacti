<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Drives the unchanged auth_authorize() and package_accept_key() against
// fixture policy functions. Each granted or denied answer must be exactly the
// policy function's, and every unrecognized request must deny without asking
// the policy at all.

namespace Cacti\Test\AuthAuthorize {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

	$root = dirname(__DIR__, 3);

	/**
	 * Record one policy call and answer from the fixture grant list.
	 *
	 * @param string $policy The policy function name.
	 * @param int    $id     The realm or object id.
	 * @param mixed  $user   The user argument as the policy function receives it.
	 */
	function policy(string $policy, int $id, mixed $user) : bool {
		$GLOBALS['authz_calls'][] = [$policy, $id, $user];

		return in_array($policy . ':' . $id . ':' . var_export($user, true), $GLOBALS['authz_grants'], true);
	}

	function is_realm_allowed(int $realm, int|bool $check_user = false) : bool {
		return policy('realm', $realm, $check_user);
	}

	function is_graph_allowed(int $local_graph_id, int $user_id = 0) : bool {
		return policy('graph', $local_graph_id, $user_id);
	}

	function is_tree_allowed(int $tree_id, int $user_id = 0) : bool {
		return policy('tree', $tree_id, $user_id);
	}

	function is_device_allowed(int $device_id, int $user_id = 0) : bool {
		return policy('device', $device_id, $user_id);
	}

	function is_graph_template_allowed(int $graph_template_id, int $user = 0) : bool {
		return policy('graph_template', $graph_template_id, $user);
	}

	function cacti_log(string $message, bool $output = false, string $environ = 'CMDPHP') : void {
		$GLOBALS['authz_log'][] = $environ . ' ' . $message;
	}

	function raise_message($id, ...$args) : void {
		$GLOBALS['authz_messages'][] = $id;
	}

	// Stop the denied branch at its redirect instead of exiting the probe.
	function header($value) : void {
		throw new \DomainException($value);
	}

	// Stop the granted branch at its first request read, past the realm gate.
	function gfrv($name, ...$args) : void {
		throw new \LengthException($name);
	}

	\cacti_fixture_source_function($root . '/lib/auth.php', 'auth_authorize', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/package_import.php', 'package_accept_key', __NAMESPACE__);

	$GLOBALS['authz_count'] = 0;

	function expect_that(bool $condition, string $message) : void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
		$GLOBALS['authz_count']++;
	}

	/**
	 * Run one decision with fresh call and log records.
	 *
	 * @param array<int,mixed>  $arguments The auth_authorize() arguments.
	 * @param array<int,string> $grants    The fixture grant list.
	 *
	 * @return mixed The decision.
	 */
	function decide(array $arguments, array $grants = []) : mixed {
		$GLOBALS['authz_grants'] = $grants;
		$GLOBALS['authz_calls']  = [];
		$GLOBALS['authz_log']    = [];

		return auth_authorize(...$arguments);
	}

	// Every permission reaches its own policy function, with the session user
	// spelled the way that function expects: false for realms, 0 for objects.
	$routes = [
		'realm'          => [false, 5],
		'graph'          => [0, 5],
		'tree'           => [0, 5],
		'device'         => [0, 5],
		'graph_template' => [0, 5],
	];

	foreach ($routes as $permission => [$session, $other]) {
		foreach ([[0, $session], [5, $other]] as [$user, $passed]) {
			$grant = $permission . ':29:' . var_export($passed, true);

			expect_that(decide([$permission, 29, $user], [$grant]) === true, $permission . ' grant must pass through for user ' . $user);
			expect_that($GLOBALS['authz_calls'] === [[$permission, 29, $passed]], $permission . ' must ask its policy once with the user it expects');
			expect_that(decide([$permission, 29, $user]) === false, $permission . ' denial must pass through for user ' . $user);
			expect_that($GLOBALS['authz_calls'] === [[$permission, 29, $passed]], $permission . ' denial must come from its policy');
			expect_that($GLOBALS['authz_log'] === [], $permission . ' must not log a known permission');
		}

		// A grant for one permission never answers another.
		expect_that(decide([$permission, 29], ['other:29:' . var_export($session, true)]) === false, $permission . ' must not use another permission grant');
	}

	// Nothing grants when no policy function decides: an unknown, misspelled or
	// padded permission denies, is logged, and never reaches a policy function.
	foreach (['', 'REALM', 'Realm', ' realm', 'realm ', 'graphs', 'view', 'is_realm_allowed'] as $permission) {
		expect_that(decide([$permission, 29], ['realm:29:false']) === false, 'Unknown permission must deny: ' . var_export($permission, true));
		expect_that($GLOBALS['authz_calls'] === [], 'Unknown permission must not reach a policy: ' . var_export($permission, true));
		expect_that(count($GLOBALS['authz_log']) === 1 && str_starts_with($GLOBALS['authz_log'][0], 'AUTH WARNING: Unknown permission'), 'Unknown permission must be logged');
	}

	// An id below 1 names no single object, a negative user would reach the
	// unrestricted is_tree_allowed() path, and a bool would be coerced to a
	// user. This file has no strict_types, like Cacti's procedural callers.
	foreach (array_keys($routes) as $permission) {
		foreach ([[0, 0], [-1, 0], [29, -1], [29, true], [29, false]] as [$id, $user]) {
			$label = $permission . ' id ' . var_export($id, true) . ' user ' . var_export($user, true);

			expect_that(decide([$permission, $id, $user], ['realm:29:1', 'realm:29:false', $permission . ':29:1', $permission . ':29:0', $permission . ':0:0']) === false, 'Invalid input must deny: ' . $label);
			expect_that($GLOBALS['authz_calls'] === [], 'Invalid input must not reach a policy: ' . $label);
		}
	}

	// The converted call site: realm 29 gates package_accept_key() through the
	// chokepoint, which the stub policy alone cannot tell apart from a direct
	// is_realm_allowed() call.
	$accept = new \ReflectionFunction(__NAMESPACE__ . '\\package_accept_key');
	$body   = implode('', array_slice(file($root . '/package_import.php'), $accept->getStartLine() - 1, $accept->getEndLine() - $accept->getStartLine() + 1));
	expect_that(str_contains($body, "auth_authorize('realm', 29)") && !str_contains($body, 'is_realm_allowed('), 'package_accept_key must ask auth_authorize() for realm 29');

	foreach ([true, false] as $granted) {
		$GLOBALS['authz_grants']   = $granted ? ['realm:29:false'] : ['realm:17:false'];
		$GLOBALS['authz_calls']    = [];
		$GLOBALS['authz_messages'] = [];
		$outcome                   = '';

		try {
			package_accept_key();
		} catch (\LengthException $e) {
			$outcome = 'read:' . $e->getMessage();
		} catch (\DomainException $e) {
			$outcome = 'redirect:' . $e->getMessage();
		}
		$expected = $granted ? 'read:package_location' : 'redirect:Location: package_import.php';
		expect_that($outcome === $expected, 'package_accept_key gate mismatch: ' . $outcome);
		expect_that($GLOBALS['authz_calls'] === [['realm', 29, false]], 'package_accept_key must ask realm 29 for the session user');
		expect_that($GLOBALS['authz_messages'] === ($granted ? [] : ['permission_denied']), 'package_accept_key message mismatch');
	}

	print json_encode(['passed' => $GLOBALS['authz_count']], JSON_THROW_ON_ERROR) . "\n";
}
