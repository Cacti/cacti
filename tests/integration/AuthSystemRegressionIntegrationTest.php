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

$root  = dirname(__DIR__, 2);
$probe = __DIR__ . '/fixtures/auth_runtime_probe.php';

test('auth subsystem regression coverage spans cookie login, 2fa, reset tokens, basic auth, and profile mutations', function () use ($root) {
	$files = [
		'include/auth.php'        => file_get_contents($root . '/include/auth.php'),
		'auth_2fa.php'           => file_get_contents($root . '/auth_2fa.php'),
		'auth_resetpassword.php' => file_get_contents($root . '/auth_resetpassword.php'),
		'auth_profile.php'       => file_get_contents($root . '/auth_profile.php'),
		'lib/auth.php'           => file_get_contents($root . '/lib/auth.php'),
		'lib/functions.php'      => file_get_contents($root . '/lib/functions.php'),
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

test('remember-me cookie authorization rejects disabled and permissionless accounts at runtime', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, ['cookie-authorization']);

	expect($verdict['disabled'])->toBeFalse()
		->and($verdict['noaccess'])->toBeFalse()
		->and($verdict['allowed'])->toBeTrue();
});

test('remember-me cookie authorization verifies token before deleting cache rows', function () use ($probe) {
	$verdict = cacti_test_isolated_probe($probe, ['cookie-token-check']);

	expect($verdict['forged']['result'])->toBeFalse()
		->and($verdict['forged']['executed'])->toBeEmpty();

	expect($verdict['valid']['result'])->toBeFalse()
		->and($verdict['valid']['executed'])->toHaveCount(1)
		->and($verdict['valid']['executed'][0]['sql'])->toContain('DELETE FROM user_auth_cache')
		->and($verdict['valid']['executed'][0]['params'])->toBe([42]);
});
