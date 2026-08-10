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

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

/**
 * Spawns a fresh PHP process to run tests/Helpers/AuthCookieProbe.php
 * against the given fixtures, and returns its decoded results.
 *
 * See AuthCookieProbe.php for why this runs out of process: it stubs the
 * same db_* functions check_auth_cookie() and
 * auth_cookie_user_currently_allowed() call, and those stubs only take
 * effect if nothing else in the process defined the real ones first --
 * which the combined Unit+Integration pest run can no longer guarantee.
 *
 * $scenario shape:
 *   config, group_realms, groups, users, cache: seed the probe's fixtures.
 *   realms: default realm count for every call; a call may override it.
 *   calls: list of either
 *     {type: 'auth_cookie_user_currently_allowed', user: array, realms?: int}
 *     {type: 'check_auth_cookie', cookie?: string, realms?: int}
 *
 * @return array<int, array{return: mixed, executed: array<int, array{sql: string, params: array}>}>
 */
function runAuthCookieProbe(array $scenario) : array {
	$php  = PHP_BINARY;
	$path = dirname(__DIR__) . '/Helpers/AuthCookieProbe.php';

	$descriptors = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$proc = proc_open(escapeshellcmd($php) . ' ' . escapeshellarg($path), $descriptors, $pipes);

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

test('auth subsystem regression coverage spans cookie login, 2fa, reset tokens, basic auth, and profile mutations', function () {
	$root  = dirname(__DIR__, 2);
	$files = [
		'include/auth.php'        => file_get_contents($root . '/include/auth.php'),
		'auth_2fa.php'            => file_get_contents($root . '/auth_2fa.php'),
		'auth_resetpassword.php'  => file_get_contents($root . '/auth_resetpassword.php'),
		'auth_profile.php'        => file_get_contents($root . '/auth_profile.php'),
		'lib/auth.php'            => file_get_contents($root . '/lib/auth.php'),
		'lib/functions.php'       => file_get_contents($root . '/lib/functions.php'),
	];

	foreach ($files as $path => $contents) {
		expect($contents)->not->toBeFalse("Unable to read $path");
	}

	expect($files['include/auth.php'])->toContain('$cookie_user = check_auth_cookie();')
		->and($files['include/auth.php'])->toContain('if (empty($_SESSION[SESS_USER_2FA])')
		->and($files['auth_2fa.php'])->toContain("read_config_option('secpass_2fatime')")
		->and($files['auth_resetpassword.php'])->toContain('AND expiry > NOW()')
		->and($files['lib/functions.php'])->toContain("'cacti_remembers'")
		->and($files['auth_profile.php'])->toContain('auth_profile_require_post();')
		->and($files['auth_profile.php'])->toContain('__csrf_magic: csrfMagicToken')
		->and($files['include/auth.php'])->toContain('auth_user_has_access($current_user)')
		->and($files['lib/auth.php'])->toContain('auth_cookie_user_currently_allowed($user_info)')
		->and($files['lib/auth.php'])->not->toContain('$result[\'secret\']');
});

test('remember-me cookie authorization rejects disabled and permissionless accounts at runtime', function () {
	$results = runAuthCookieProbe([
		'config' => ['guest_user' => 0],
		'calls'  => [
			[
				'type'   => 'auth_cookie_user_currently_allowed',
				'realms' => 0,
				'user'   => [
					'id'           => 10,
					'username'     => 'disabled',
					'enabled'      => '',
					'show_tree'    => '',
					'show_list'    => '',
					'show_preview' => '',
				],
			],
			[
				'type'   => 'auth_cookie_user_currently_allowed',
				'realms' => 0,
				'user'   => [
					'id'           => 11,
					'username'     => 'noaccess',
					'enabled'      => 'on',
					'show_tree'    => '',
					'show_list'    => '',
					'show_preview' => '',
				],
			],
			[
				'type'   => 'auth_cookie_user_currently_allowed',
				'realms' => 1,
				'user'   => [
					'id'           => 12,
					'username'     => 'allowed',
					'enabled'      => 'on',
					'show_tree'    => '',
					'show_list'    => '',
					'show_preview' => '',
				],
			],
		],
	]);

	expect($results[0]['return'])->toBeFalse()
		->and($results[1]['return'])->toBeFalse()
		->and($results[2]['return'])->toBeTrue();
});

test('remember-me cookie authorization verifies token before deleting cache rows', function () {
	$results = runAuthCookieProbe([
		'config' => [
			'auth_cache_enabled' => 'on',
			'guest_user'         => 0,
		],
		'realms' => 1,
		'users'  => [
			42 => [
				'id'           => 42,
				'username'     => 'disabled',
				'realm'        => 0,
				'enabled'      => '',
				'show_tree'    => '',
				'show_list'    => '',
				'show_preview' => '',
			],
		],
		'cache' => [
			[
				'user_id' => 42,
				'token'   => hash('sha512', 'valid-token', false),
			],
		],
		'calls' => [
			['type' => 'check_auth_cookie', 'cookie' => '42,-1,forged-token'],
			['type' => 'check_auth_cookie', 'cookie' => '42,-1,valid-token'],
		],
	]);

	expect($results[0]['return'])->toBeFalse()
		->and($results[0]['executed'])->toBeEmpty()
		->and($results[1]['return'])->toBeFalse()
		->and($results[1]['executed'])->toHaveCount(1)
		->and($results[1]['executed'][0]['sql'])->toContain('DELETE FROM user_auth_cache')
		->and($results[1]['executed'][0]['params'])->toBe([42]);
});
