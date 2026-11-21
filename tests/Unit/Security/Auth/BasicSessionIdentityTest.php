<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Web Basic Authentication: the identity passed by the web server is checked
 * when a session is created. As defense in depth an existing session is held
 * to that identity on every request, and identities set by the web server's
 * own authentication module take precedence over PHP_AUTH_USER. The two
 * production functions are loaded unchanged into a fixture namespace with
 * stubs that keep the real signatures.
 */

namespace CactiBasicSessionIdentityFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

function read_config_option($config_name, $force = false) {
	return $GLOBALS['basic_identity_fixture']['options'][$config_name] ?? '';
}

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['basic_identity_fixture']['log'][] = $string;
}

function get_guest_account() {
	return $GLOBALS['basic_identity_fixture']['guest'];
}

function cacti_session_destroy() {
	$GLOBALS['basic_identity_fixture']['destroyed']++;

	$_SESSION = array();
}

function cacti_session_start($regenerate = false) {
	$GLOBALS['basic_identity_fixture']['started']++;
}

if (!function_exists(__NAMESPACE__ . '\\auth_basic_session_recheck')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'get_basic_auth_username', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'auth_basic_session_recheck', __NAMESPACE__);
}

function basic_identity_server(array $server) {
	unset($_SERVER['REMOTE_USER'], $_SERVER['REDIRECT_REMOTE_USER'], $_SERVER['PHP_AUTH_USER']);

	foreach ($server as $key => $value) {
		$_SERVER[$key] = $value;
	}
}

function basic_identity_account(array $override = array()) {
	return array_merge(array('id' => '7', 'username' => 'alice', 'enabled' => 'on', 'locked' => ''), $override);
}

beforeEach(function () {
	$GLOBALS['basic_identity_fixture'] = array(
		'options'   => array('auth_method' => 2),
		'log'       => array(),
		'guest'     => 0,
		'destroyed' => 0,
		'started'   => 0,
	);

	$_SESSION = array();

	unset($GLOBALS['config']['auth_basic_allow_php_auth_user']);

	basic_identity_server(array());
});

afterEach(function () {
	$_SESSION = array();

	unset($GLOBALS['config']['auth_basic_allow_php_auth_user'], $GLOBALS['basic_identity_fixture']);

	basic_identity_server(array());
});

test('a session is kept while the web server passes the identity it was created for', function () {
	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck('alice', basic_identity_account()))->toBeTrue()
		->and($_SESSION['sess_user_id'])->toBe('7')
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(0)
		->and($GLOBALS['basic_identity_fixture']['log'])->toBe(array());
});

test('a request without a session is left to the sign-in path', function () {
	expect(auth_basic_session_recheck('alice', basic_identity_account()))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(0)
		->and($GLOBALS['basic_identity_fixture']['started'])->toBe(0);
});

test('a session is ended when the web server no longer passes an identity', function () {
	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck(false, array()))->toBeFalse()
		->and(isset($_SESSION['sess_user_id']))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1)
		->and($GLOBALS['basic_identity_fixture']['started'])->toBe(1)
		->and($GLOBALS['basic_identity_fixture']['log'][0])->toContain('user id 7');
});

test('a session is ended when the web server passes a different identity', function () {
	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck('bob', basic_identity_account(array('id' => '9', 'username' => 'bob'))))->toBeFalse()
		->and(isset($_SESSION['sess_user_id']))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
});

test('a session is ended when another name resolves to the same account', function () {
	// o'brien and obrien both sanitize to the account name obrien
	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => "o'brien");

	expect(auth_basic_session_recheck('obrien', basic_identity_account(array('username' => 'obrien'))))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
});

test('a session created before the identity was recorded is ended once', function () {
	$_SESSION = array('sess_user_id' => '7');

	expect(auth_basic_session_recheck('alice', basic_identity_account()))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
});

test('a session is ended when it belongs to another account than the identity', function () {
	$_SESSION = array('sess_user_id' => '1', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck('alice', basic_identity_account()))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
});

test('a session is ended when its account is disabled or locked', function (array $override) {
	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck('alice', basic_identity_account($override)))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
})->with(array(
	'disabled' => array(array('enabled' => '')),
	'locked'   => array(array('locked' => 'on')),
));

test('a guest session is kept for the identity that has no account of its own', function () {
	$GLOBALS['basic_identity_fixture']['guest'] = '3';

	$_SESSION = array('sess_user_id' => '3', 'sess_basic_auth_user' => 'visitor');

	expect(auth_basic_session_recheck('visitor', array()))->toBeTrue()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(0);
});

test('a guest session is ended for another identity or when the guest account is gone', function ($guest, $username) {
	$GLOBALS['basic_identity_fixture']['guest'] = $guest;

	$_SESSION = array('sess_user_id' => '3', 'sess_basic_auth_user' => 'visitor');

	expect(auth_basic_session_recheck($username, array()))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
})->with(array(
	'another identity'      => array('3', 'other'),
	'guest account removed' => array(0, 'visitor'),
	'guest account changed' => array('4', 'visitor'),
));

test('a session of an account that was removed is not treated as a guest session', function () {
	$GLOBALS['basic_identity_fixture']['guest'] = '3';

	$_SESSION = array('sess_user_id' => '7', 'sess_basic_auth_user' => 'alice');

	expect(auth_basic_session_recheck('alice', array()))->toBeFalse()
		->and($GLOBALS['basic_identity_fixture']['destroyed'])->toBe(1);
});

test('the identity set by the web server takes precedence over PHP_AUTH_USER', function () {
	basic_identity_server(array('REMOTE_USER' => 'verified', 'PHP_AUTH_USER' => 'decoded'));
	expect(get_basic_auth_username())->toBe('verified');

	basic_identity_server(array('REDIRECT_REMOTE_USER' => 'verified', 'PHP_AUTH_USER' => 'decoded'));
	expect(get_basic_auth_username())->toBe('verified');
});

test('PHP_AUTH_USER is still accepted by default when it is the only identity', function () {
	basic_identity_server(array('PHP_AUTH_USER' => 'alice'));
	expect(get_basic_auth_username())->toBe('alice');

	// an empty REMOTE_USER is not an identity
	basic_identity_server(array('REMOTE_USER' => '', 'PHP_AUTH_USER' => 'alice'));
	expect(get_basic_auth_username())->toBe('alice');
});

test('PHP_AUTH_USER is not used once the site turns it off', function () {
	$GLOBALS['config']['auth_basic_allow_php_auth_user'] = false;

	basic_identity_server(array('PHP_AUTH_USER' => 'alice'));
	expect(get_basic_auth_username())->toBeFalse();

	basic_identity_server(array('REMOTE_USER' => 'alice', 'PHP_AUTH_USER' => 'decoded'));
	expect(get_basic_auth_username())->toBe('alice');
});

test('no identity is returned when the web server passes none', function () {
	expect(get_basic_auth_username())->toBeFalse();

	basic_identity_server(array('REMOTE_USER' => '', 'PHP_AUTH_USER' => ''));
	expect(get_basic_auth_username())->toBeFalse();
});

test('a missing map file entry is logged at sign-in and not on each later request', function () {
	$mapfile = tempnam(sys_get_temp_dir(), 'cacti-basic-map-');
	file_put_contents($mapfile, "bob,robert\n");

	$GLOBALS['basic_identity_fixture']['options']['path_basic_mapfile'] = $mapfile;

	basic_identity_server(array('REMOTE_USER' => 'alice'));

	try {
		expect(get_basic_auth_username())->toBe('alice')
			->and($GLOBALS['basic_identity_fixture']['log'])->toHaveCount(1);

		$_SESSION['sess_user_id'] = '7';

		expect(get_basic_auth_username())->toBe('alice')
			->and($GLOBALS['basic_identity_fixture']['log'])->toHaveCount(1);
	} finally {
		unlink($mapfile);
	}
});

test('include/auth.php rechecks the session on every Web Basic request', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/auth.php');

	$recheck = strpos($source, 'auth_basic_session_recheck($username, $current_user);');
	$signin  = strpos($source, "if (\$auth_method == 2 && !isset(\$_SESSION['sess_user_id'])) {");
	$start   = strpos($source, "if (\$auth_method == 2) {\n\t\t\$username     = get_basic_auth_username();");

	// the recheck is not conditional on the session being absent, and runs before sign-in
	expect($start)->not->toBeFalse()
		->and($recheck)->toBeGreaterThan($start)
		->and($signin)->toBeGreaterThan($recheck)
		->and(substr($source, $start, $recheck - $start))->not->toContain('sess_user_id');

	// the account is looked up by the name auth_login.php stores it under
	expect($source)->toContain('array(sanitize_search_string($username)));');
});

test('both sign-in paths record the identity the session is held to', function () {
	$root  = dirname(__DIR__, 4);
	$auth  = file_get_contents($root . '/include/auth.php');
	$login = file_get_contents($root . '/auth_login.php');

	expect($auth)->toContain("\$_SESSION['sess_basic_auth_user'] = \$username;")
		->and($login)->toMatch('/if \(\$auth_method == 2\) \{\s+\$_SESSION\[\'sess_basic_auth_user\'\] = get_basic_auth_username\(\);/');
});

test('a locked account ends the request with the access denied page', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/auth.php');
	$start  = strpos($source, "cacti_auth_transition((int)\$current_user['id'], 'basic_auth')");
	$body   = substr($source, $start, 220);

	expect($start)->not->toBeFalse()
		->and($body)->toContain("auth_display_custom_error_message(__('Access Denied! User account locked.'));")
		->and($body)->toContain('exit;')
		->and($body)->not->toContain('return false;');
});

test('PHP_AUTH_USER stays on unless config.php sets the option to false', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');

	expect($source)->toContain("\$config['auth_basic_allow_php_auth_user'] = (!isset(\$auth_basic_allow_php_auth_user) || \$auth_basic_allow_php_auth_user !== false);");
});
