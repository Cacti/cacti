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
 * domains_login_process() reads the realm straight from the request. Every arm
 * that can authenticate is gated on a domain realm, so a realm the dropdown never
 * offered used to fall past all of them and return an empty user with $error left
 * false. auth_login.php reads that combination as "authenticated, no account yet"
 * and provisions the account from the user template, which turns any username plus
 * any password into a working login.
 *
 * These tests run the shipped function body in a child process with the LDAP and
 * database calls stubbed, so the branch decisions are the real ones.
 */

$root = dirname(__DIR__, 4);

// Pull the function verbatim out of lib/auth.php so the harness runs shipped code.
$extract = static function (string $source, string $name): string {
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		throw new RuntimeException($name . '() not found');
	}

	$depth = 0;
	$len   = strlen($source);

	for ($i = strpos($source, '{', $start); $i < $len; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	throw new RuntimeException($name . '() is unbalanced');
};

// Runs domains_login_process() against the given request and directory state.
$login = static function (array $scenario) use ($root, $extract): array {
	$body = $extract(file_get_contents($root . '/lib/auth.php'), 'domains_login_process');

	$harness = <<<'PHP'
<?php
$scenario = json_decode($argv[1], true);

$GLOBALS['req']        = $scenario['request'];
$GLOBALS['domains']    = $scenario['domains'];
$GLOBALS['ldap_ok']    = $scenario['ldap_ok'];
$GLOBALS['user_row']   = $scenario['user_row'];
$GLOBALS['ldap_calls'] = 0;

$realm     = 0;
$error     = false;
$error_msg = '';

function gnrv($name, $default = '') {
	return $GLOBALS['req'][$name] ?? $default;
}

function __(...$args) {
	return (string) $args[0];
}

function get_client_addr() {
	return '203.0.113.9';
}

function cacti_log(...$args) {
}

function cacti_sizeof($array) {
	return is_array($array) ? count($array) : 0;
}

// mirrors get_auth_realms(true) under AUTH_METHOD_DOMAIN: local plus enabled domains
function get_auth_realms($login = false) {
	$realms = ['0' => ['name' => 'Local', 'selected' => false]];

	foreach ($GLOBALS['domains'] as $domain_id) {
		$realms[1000 + $domain_id] = ['name' => 'Domain ' . $domain_id, 'selected' => false];
	}

	return $realms;
}

function auth_checkclear_lockout($username, $realm) {
}

function auth_process_lockout_check($username, $realm) {
	return false;
}

function auth_process_lockout($username, $realm) {
}

function domains_ldap_search_dn($username, $realm) {
	$GLOBALS['ldap_calls']++;

	return ['error_num' => '0', 'error_text' => '', 'dn' => 'uid=' . $username . ',dc=example,dc=com'];
}

function domains_ldap_auth($username, $password = '', $dn = '', $realm = 0) {
	$GLOBALS['ldap_calls']++;

	if ($GLOBALS['ldap_ok']) {
		return ['error_num' => '0', 'error_text' => ''];
	}

	return ['error_num' => '1', 'error_text' => 'Authentication Failure'];
}

function domains_ldap_search_cn($username, $cn, $realm) {
	return ['error_num' => '0', 'cn' => []];
}

function user_copy(...$args) {
	return true;
}

function db_fetch_row_prepared($sql, $params = []) {
	return strpos($sql, 'WHERE username = ?') !== false ? $GLOBALS['user_row'] : [];
}

function db_fetch_cell_prepared($sql, $params = []) {
	return strpos($sql, 'domain_name') !== false ? 'ExampleDomain' : 0;
}

PHP;

	$harness .= $body . "\n\n";
	$harness .= '$user = domains_login_process($scenario[\'username\']);' . "\n";
	$harness .= 'print json_encode([' . "\n";
	$harness .= "\t'error'      => \$error,\n";
	$harness .= "\t'error_msg'  => \$error_msg,\n";
	$harness .= "\t'user'       => \$user,\n";
	$harness .= "\t'ldap_calls' => \$GLOBALS['ldap_calls']\n";
	$harness .= ']);' . "\n";

	$file = tempnam(sys_get_temp_dir(), 'cacti_login_');
	file_put_contents($file, $harness);

	$scenario += [
		'username' => 'attacker',
		'request'  => [],
		'domains'  => [1],
		'ldap_ok'  => true,
		'user_row' => []
	];

	$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg(json_encode($scenario)) . ' 2>&1');

	unlink($file);

	$result = json_decode((string) $output, true);

	if (!is_array($result)) {
		throw new RuntimeException('harness failed: ' . $output);
	}

	return $result;
};

test('a realm the dropdown never offered is rejected instead of falling through', function () use ($login) {
	// GHSA candidate: realm=2 with any non-empty password used to match neither the
	// LDAP arm nor the empty-password arm, so the caller saw no error and no user.
	$result = $login([
		'request' => ['realm' => '2', 'login_password' => 'anything']
	]);

	expect($result['error'])->toBeTrue()
		->and($result['user'])->toBe([])
		->and($result['ldap_calls'])->toBe(0);
});

test('a missing or non-numeric realm is rejected', function () use ($login) {
	foreach ([[], ['realm' => ''], ['realm' => 'ldap'], ['realm' => '-1'], ['realm' => '1500']] as $realm) {
		$result = $login([
			'request' => $realm + ['login_password' => 'anything']
		]);

		expect($result['error'])->toBeTrue()
			->and($result['user'])->toBe([])
			->and($result['ldap_calls'])->toBe(0);
	}
});

test('a realm belonging to a disabled domain is rejected before any bind', function () use ($login) {
	// get_auth_realms() only lists enabled domains, so 1002 is not on offer here
	$result = $login([
		'request' => ['realm' => '1002', 'login_password' => 'anything'],
		'domains' => [1]
	]);

	expect($result['error'])->toBeTrue()
		->and($result['ldap_calls'])->toBe(0);
});

test('a valid domain login still authenticates and returns the account', function () use ($login) {
	$result = $login([
		'username' => 'alice',
		'request'  => ['realm' => '1001', 'login_password' => 'correct-horse'],
		'domains'  => [1],
		'ldap_ok'  => true,
		'user_row' => ['id' => 7, 'username' => 'alice', 'realm' => 1001, 'enabled' => 'on']
	]);

	expect($result['error'])->toBeFalse()
		->and($result['user']['id'])->toBe(7)
		->and($result['ldap_calls'])->toBe(2);
});

test('a valid domain with a bad password still fails with an error', function () use ($login) {
	$result = $login([
		'username' => 'alice',
		'request'  => ['realm' => '1001', 'login_password' => 'wrong'],
		'domains'  => [1],
		'ldap_ok'  => false
	]);

	expect($result['error'])->toBeTrue()
		->and($result['user'])->toBe([]);
});

test('an empty password on a valid domain keeps its own error path', function () use ($login) {
	$result = $login([
		'request' => ['realm' => '1001', 'login_password' => ''],
		'domains' => [1]
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toContain('No password provided')
		->and($result['ldap_calls'])->toBe(0);
});

test('the local realm downgrade in auth_login.php is untouched', function () use ($root) {
	$src = file_get_contents($root . '/auth_login.php');

	// realm 0 and 1 never reach domains_login_process(); they are handled as a
	// local login before the switch, and that dispatch must stay as it is.
	expect($src)->toContain('if ($auth_method > AUTH_METHOD_BASIC && $frv_realm <= 1) {')
		->and($src)->toContain('$auth_method = AUTH_METHOD_CACTI;')
		->and($src)->toContain('if ($frv_realm == 2) {' . "\n\t\t" . '$realm = 3;');
});
