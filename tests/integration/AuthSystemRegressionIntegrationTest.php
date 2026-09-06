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

/**
 * Spawns a fresh PHP process to run tests/Helpers/AuthCookieProbe.php
 * against the given fixtures, and returns its decoded results.
 *
 * See AuthCookieProbe.php for why this runs out of process: it stubs the
 * same db_* functions check_auth_cookie() calls, and those stubs only take
 * effect if nothing else in the process defined the real ones first --
 * which the combined Unit+Integration pest run can no longer guarantee.
 *
 * $scenario shape:
 *   config, users, usernames, cache, locked_users and table_exists seed fixtures.
 *   calls: check_auth_cookie/clear_auth_cookie entries with an optional cookie,
 *     or set_auth_cookie entries with a user row.
 *
 * @return array<int, array{return: mixed, executed: array<int, array{sql: string, params: array}>, cookie_calls: array, events: array, warnings: array}>
 */
function runAuthCookieProbe(array $scenario) : array {
	$php  = PHP_BINARY;
	$path = dirname(__DIR__) . '/Helpers/AuthCookieProbe.php';

	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$proc = proc_open([$php, $path], $descriptors, $pipes);

	if (!is_resource($proc)) {
		throw new RuntimeException('failed to spawn AuthCookieProbe.php');
	}

	fwrite($pipes[0], json_encode($scenario));
	fclose($pipes[0]);

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit = proc_close($proc);

	if ($exit !== 0) {
		throw new RuntimeException('AuthCookieProbe.php exited ' . $exit . ': ' . $stderr);
	}

	$decoded = json_decode($stdout, true);

	if (!is_array($decoded) || !isset($decoded['results'])) {
		throw new RuntimeException('AuthCookieProbe.php produced unparseable output: ' . $stdout);
	}

	return $decoded['results'];
}

test('malformed remember-me cookies fail closed without warnings or database mutations', function () {
	$results = runAuthCookieProbe([
		'config' => [
			'auth_cache_enabled' => 'on',
			'guest_user'         => 0,
		],
		'realms' => 1,
		'users'  => [
			42 => [
				'id'           => 42,
				'username'     => 'valid-user',
				'realm'        => 0,
				'enabled'      => 'on',
				'show_tree'    => 'on',
				'show_list'    => '',
				'show_preview' => '',
			],
		],
		'cache' => [[
			'user_id' => 42,
			'token'   => hash('sha512', 'valid-token', false),
		]],
		'calls' => [
			['type' => 'check_auth_cookie', 'cookie' => ''],
			['type' => 'check_auth_cookie', 'cookie' => '42'],
			['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token,extra'],
			['type' => 'check_auth_cookie', 'cookie' => ['42', '-1', 'valid-token']],
			['type' => 'clear_auth_cookie', 'cookie' => ['42', 'valid-token']],
		],
	]);

	foreach (array_slice($results, 0, 4) as $result) {
		expect($result['return'])->toBeFalse()
			->and($result['warnings'])->toBeEmpty()
			->and($result['executed'])->toBeEmpty()
			->and($result['cookie_calls'])->toBeEmpty();
	}

	expect($results[4]['return'])->toBeNull()
		->and($results[4]['warnings'])->toBeEmpty()
		->and($results[4]['executed'])->toBeEmpty()
		->and($results[4]['cookie_calls'])->toBe([['logout']]);
});

test('absent remember-me cookies are no-ops for check and clear', function () {
	$results = runAuthCookieProbe([
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 0],
		'calls'  => [
			['type' => 'check_auth_cookie'],
			['type' => 'clear_auth_cookie'],
		],
	]);

	expect($results[0]['return'])->toBeFalse()
		->and($results[0]['warnings'])->toBeEmpty()
		->and($results[0]['executed'])->toBeEmpty()
		->and($results[0]['cookie_calls'])->toBeEmpty()
		->and($results[1]['return'])->toBeNull()
		->and($results[1]['warnings'])->toBeEmpty()
		->and($results[1]['executed'])->toBeEmpty()
		->and($results[1]['cookie_calls'])->toBeEmpty();
});

test('legacy remember-me identity that no longer exists fails closed', function () {
	$results = runAuthCookieProbe([
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 0],
		'calls'  => [
			['type' => 'check_auth_cookie', 'cookie' => 'missing-user,legacy-token'],
		],
	]);

	expect($results[0]['return'])->toBeFalse()
		->and($results[0]['warnings'])->toBeEmpty()
		->and($results[0]['executed'])->toBeEmpty()
		->and($results[0]['cookie_calls'])->toBeEmpty();
});

test('remember-me cookie clear and set lifecycle covers legacy identities and token hashing', function () {
	$results = runAuthCookieProbe([
		'config'    => ['auth_cache_enabled' => 'on', 'guest_user' => 0],
		'usernames' => ['legacy-user' => 43],
		'calls'     => [
			['type' => 'clear_auth_cookie', 'cookie' => '42,old-token'],
			['type' => 'clear_auth_cookie', 'cookie' => 'legacy-user,7,legacy-token'],
			['type' => 'clear_auth_cookie', 'cookie' => 'broken'],
			['type' => 'clear_auth_cookie', 'cookie' => 'legacy,user,7,legacy-token'],
			['type' => 'set_auth_cookie', 'user' => ['id' => 44, 'realm' => 2]],
		],
	]);

	expect($results[0]['cookie_calls'])->toBe([['logout']])
		->and($results[0]['executed'][0]['params'])->toBe(['42', hash('sha512', 'old-token', false)])
		->and($results[0]['events'][0][0])->toBe('database')
		->and($results[0]['events'][1])->toBe(['cookie', 'logout'])
		->and($results[1]['cookie_calls'])->toBe([['logout']])
		->and($results[1]['executed'][0]['params'])->toBe([43, hash('sha512', 'legacy-token', false)])
		->and($results[2]['executed'])->toBeEmpty()
		->and($results[2]['warnings'])->toBeEmpty()
		->and($results[2]['cookie_calls'])->toBe([['logout']])
		->and($results[3]['executed'])->toBeEmpty()
		->and($results[3]['warnings'])->toBeEmpty()
		->and($results[3]['cookie_calls'])->toBe([['logout']]);

	$insert  = $results[4]['executed'][0];
	$setCall = $results[4]['cookie_calls'][0];

	expect($insert['sql'])->toContain('INSERT INTO user_auth_cache')
		->and($insert['params'][0])->toBe(44)
		->and($insert['params'][1])->toBe('192.0.2.10')
		->and($setCall[0])->toBe('set')
		->and($setCall[1])->toBe(44)
		->and($setCall[2])->toBe(2)
		->and($setCall[3])->toMatch('/^[a-f0-9]{64}$/D')
		->and($insert['params'][2])->toBe(hash('sha512', $setCall[3], false));
});

test('persistent credentials follow the safe login and logout ordering', function () {
	$authLogin = file_get_contents(dirname(__DIR__, 2) . '/auth_login.php');
	$logout    = file_get_contents(dirname(__DIR__, 2) . '/logout.php');

	$transition = strpos($authLogin, "cacti_auth_transition((int)\$user['id'], 'login')");
	$mint       = strpos($authLogin, 'set_auth_cookie($user);');
	$revoke     = strpos($logout, 'clear_auth_cookie();');
	$session    = strpos($logout, 'cacti_cookie_logout();');

	expect($transition)->not->toBeFalse()
		->and($mint)->not->toBeFalse()
		->and($mint)->toBeGreaterThan($transition)
		->and($revoke)->not->toBeFalse()
		->and($session)->not->toBeFalse()
		->and($revoke)->toBeLessThan($session)
		->and(substr_count($logout, 'clear_auth_cookie();'))->toBe(1);
});

test('valid remember-me cookie rotates the token and records the login', function () {
	$results = runAuthCookieProbe([
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 0],
		'realms' => 1,
		'users'  => [
			42 => [
				'id'           => 42,
				'username'     => 'allowed',
				'realm'        => 0,
				'enabled'      => 'on',
				'locked'       => '',
				'show_tree'    => 'on',
				'show_list'    => '',
				'show_preview' => '',
			],
		],
		'cache' => [[
			'user_id' => 42,
			'token'   => hash('sha512', 'valid-token', false),
			'hostname' => '192.0.2.10',
		]],
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]);

	$result = $results[0];

	expect($result['return'])->toBe(42)
		->and($result['warnings'])->toBeEmpty()
		->and($result['cookie_calls'][0])->toBe(['logout'])
		->and($result['cookie_calls'][1][0])->toBe('set')
		->and($result['executed'])->toHaveCount(3)
		->and($result['executed'][0]['sql'])->toContain('INSERT IGNORE INTO user_log')
		->and($result['executed'][1]['sql'])->toContain('DELETE FROM user_auth_cache')
		->and($result['executed'][2]['sql'])->toContain('INSERT INTO user_auth_cache');
});

test('remember-me authorization rejects token host guest lockout and disabled-cache failures', function () {
	$user = [
		'id' => 42, 'username' => 'allowed', 'realm' => 0, 'enabled' => 'on', 'locked' => '',
		'show_tree' => 'on', 'show_list' => '', 'show_preview' => '',
	];

	$base = [
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 0, 'secpass_lockfailed' => 0],
		'users'  => [42 => $user],
		'cache'  => [[
			'user_id' => 42,
			'token' => hash('sha512', 'valid-token', false),
			'hostname' => '192.0.2.10',
		]],
	];

	$wrongToken = runAuthCookieProbe($base + [
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,wrong-token']],
	]);
	$wrongHost = runAuthCookieProbe(array_replace($base, [
		'cache' => [[
			'user_id' => 42,
			'token' => hash('sha512', 'valid-token', false),
			'hostname' => '198.51.100.20',
		]],
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]));
	$guest = runAuthCookieProbe(array_replace($base, [
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 42, 'secpass_lockfailed' => 0],
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]));
	$locked = runAuthCookieProbe(array_replace($base, [
		'config' => ['auth_cache_enabled' => 'on', 'guest_user' => 0, 'secpass_lockfailed' => 3],
		'locked_users' => ['allowed' => array_replace($user, ['locked' => 'on'])],
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]));
	$disabled = runAuthCookieProbe([
		'config' => ['auth_cache_enabled' => 'off'],
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]);
	$missingTable = runAuthCookieProbe([
		'config' => ['auth_cache_enabled' => 'on'],
		'table_exists' => false,
		'calls' => [['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token']],
	]);

	foreach ([$wrongToken, $wrongHost, $guest, $locked, $disabled, $missingTable] as $results) {
		expect($results[0]['return'])->toBeFalse()
			->and($results[0]['executed'])->toBeEmpty()
			->and($results[0]['cookie_calls'])->toBeEmpty();
	}
});
