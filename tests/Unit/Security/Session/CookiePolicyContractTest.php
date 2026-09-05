<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function cacti_test_cookie_sources() : array {
	static $sources;

	if (!isset($sources)) {
		$root  = dirname(__DIR__, 4);
		$files = [
			'functions' => 'lib/functions.php',
			'auth'      => 'lib/auth.php',
			'global'    => 'include/global.php',
			'tfa'       => 'auth_2fa.php',
			'logout'    => 'logout.php',
			'login'     => 'auth_login.php',
			'change'    => 'auth_changepassword.php',
			'include'   => 'include/auth.php',
			'zoom'      => 'include/js/jquery.zoom.js',
			'storage'   => 'include/js/js.storage.js',
		];
		$sources = [];

		foreach ($files as $name => $file) {
			$source = file_get_contents($root . '/' . $file);

			if ($source === false) {
				throw new RuntimeException("Unable to read $file for cookie policy tests.");
			}

			$sources[$name] = $source;
		}
	}

	return $sources;
}

test('server cookie writers enforce path domain secure httponly and SameSite policy', function () : void {
	$cookie_sources = cacti_test_cookie_sources();

	foreach (['cacti_cookie_set', 'cacti_cookie_logout', 'cacti_cookie_session_set', 'cacti_cookie_session_logout'] as $function) {
		$start = strpos($cookie_sources['functions'], "function $function(");
		expect($start)->not->toBeFalse("$function must exist");
		$next = strpos($cookie_sources['functions'], "\nfunction ", $start + 10);
		$body = substr($cookie_sources['functions'], $start, $next === false ? null : $next - $start);

		expect($body)->toContain("'path'     => CACTI_PATH_URL")
			->toContain("'domain'   => \$domain")
			->toContain("'secure'   => \$secure")
			->toContain("'httponly' => true")
			->toContain("'samesite' => 'Strict'");
	}
});

test('session bootstrap enables strict secure cookie settings', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	expect($cookie_sources['global'])->toContain("ini_set('session.cookie_httponly', true)")
		->toContain("ini_set('session.cookie_path', CACTI_PATH_URL)")
		->toContain("ini_set('session.use_strict_mode', true)")
		->toContain("ini_set('session.cookie_samesite', 'Strict')")
		->toContain('if (cacti_is_https())')
		->toContain("ini_set('session.cookie_secure', true)");
});

test('remember-me parsing accepts exactly legacy or current field counts', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	$start          = strpos($cookie_sources['auth'], 'function check_auth_cookie()');

	if ($start === false) {
		throw new RuntimeException('check_auth_cookie() must exist');
	}

	$end            = strpos($cookie_sources['auth'], '/**', $start + 10);

	if ($end === false) {
		throw new RuntimeException('check_auth_cookie() must have a following docblock boundary');
	}

	$body           = substr($cookie_sources['auth'], $start, $end - $start);

	expect($body)->toContain('cacti_sizeof($parts) == 2')
		->toContain('cacti_sizeof($parts) == 3')
		->toMatch('/else\s*\{\s*return false;\s*\}/')
		->toContain("hash('sha512', \$token, false)")
		->toContain('auth_cookie_user_currently_allowed($user_info)')
		->toContain('set_auth_cookie($user_info)');
});

test('2FA cookie binds user time user-agent and secret and has bounded parsing', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	expect($cookie_sources['tfa'])->toContain("explode(':', \$_COOKIE[session_name() . '_otp'], 2)")
		->toContain('cacti_count($tfaCookie) == 2')
		->toContain("hash_hmac('sha1', \$user['username'] . ':' . \$tfaMins . ':' . \$tfaCookieTime . ':' . \$_SERVER['HTTP_USER_AGENT'], \$user['tfa_secret'])")
		->toContain("cacti_cookie_set(session_name() . '_otp', \$cookie, time() + (\$cookie_lifetime))");
});

test('logout revokes the server token before clearing browser cookies', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	$revoke         = strpos($cookie_sources['logout'], 'clear_auth_cookie();');
	$clear          = strpos($cookie_sources['logout'], 'cacti_cookie_logout();');

	expect($revoke)->not->toBeFalse()
		->and($clear)->not->toBeFalse()
		->and($revoke)->toBeLessThan($clear);
});

test('login restore and password-change flows invoke the cookie lifecycle at safe boundaries', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	$set            = strpos($cookie_sources['login'], 'set_auth_cookie($user);');
	$transition     = strpos($cookie_sources['login'], "cacti_auth_transition((int) \$user['id'], 'login')");

	expect($set)->not->toBeFalse()
		->and($transition)->not->toBeFalse()
		->and($set)->toBeGreaterThan($transition)
		->and($cookie_sources['change'])->toContain('cacti_cookie_logout();')
		->and($cookie_sources['include'])->toContain('$cookie_user = check_auth_cookie();')
		->toContain("cacti_auth_transition((int) \$cookie_user, 'cookie_restore')")
		->toContain('clear_auth_cookie();');
});

test('zoom cookie fallback uses isolated storage prefixes and persists every custom setting mutation', function () : void {
	$cookie_sources = cacti_test_cookie_sources();
	expect($cookie_sources['zoom'])->toContain("cookieName\t\t\t: 'cacti_zoom'")
		->toContain('storage.isSet(zoom.options.cookieName)')
		->toContain('zoom.custom = deserialize(storage.get(zoom.options.cookieName))')
		->and(substr_count($cookie_sources['zoom'], 'storage.set(zoom.options.cookieName, serialize(zoom.custom))'))->toBe(6)
		->and($cookie_sources['storage'])->toContain('var cookie_local_prefix = "ls_";')
		->toContain('var cookie_session_prefix = "ss_";')
		->toContain('window.localCookieStorage')
		->toContain('window.sessionCookieStorage')
		->toContain('window.cookieStorage');
});
