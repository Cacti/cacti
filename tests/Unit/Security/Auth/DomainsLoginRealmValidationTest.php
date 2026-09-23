<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/DomainsLoginProcessHarness.php';

test('an empty username is rejected before LDAP', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'username' => '',
		'request'  => ['realm' => '1001', 'login_password' => 'x'],
	]);

	expect($result['error'])->toBeTrue()
		->and($result['user'])->toBe([])
		->and($result['ldap_calls'])->toBe(0);
});

test('a realm the dropdown never offered is rejected instead of falling through', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request' => ['realm' => '2', 'login_password' => 'anything'],
	]);

	expect($result['error'])->toBeTrue()
		->and($result['user'])->toBe([])
		->and($result['ldap_calls'])->toBe(0);
});

test('the local realm with a password is not treated as an LDAP domain', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request' => ['realm' => '0', 'login_password' => 'anything'],
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toContain('Login Failed')
		->and($result['ldap_calls'])->toBe(0);
});

test('a missing or non-numeric realm is rejected', function () {
	foreach ([[], ['realm' => ''], ['realm' => 'ldap'], ['realm' => '-1'], ['realm' => '1500']] as $realm) {
		$result = cacti_test_run_domains_login_process_1_2([
			'request' => $realm + ['login_password' => 'anything'],
		]);

		expect($result['error'])->toBeTrue()
			->and($result['ldap_calls'])->toBe(0);
	}
});

test('a locked account returns before any bind', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request' => ['realm' => '1001', 'login_password' => 'x'],
		'lockout' => true,
	]);

	expect($result['error'])->toBeFalse()
		->and($result['user'])->toBe([])
		->and($result['ldap_calls'])->toBe(0);
});

test('a search that returns no LDAP row fails closed without page diagnostics', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'      => ['realm' => '1001', 'login_password' => 'x'],
		'search_false' => true,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toBe('Access Denied!  Login Failed.')
		->and($result['error_msg'])->not->toContain('Unable to find');
});

test('a failed DN search fails closed without leaking the directory error', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'   => ['realm' => '1001', 'login_password' => 'x'],
		'search_ok' => false,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toBe('Access Denied!  Login Failed.');
});

test('a valid domain login still authenticates and returns the account', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'username' => 'alice',
		'request'  => ['realm' => '1001', 'login_password' => 'correct-horse'],
		'ldap_ok'  => true,
		'user_row' => ['id' => 7, 'username' => 'alice', 'realm' => 1001, 'enabled' => 'on'],
	]);

	expect($result['error'])->toBeFalse()
		->and($result['user']['id'])->toBe(7)
		->and($result['ldap_calls'])->toBe(2);
});

test('a bad password fails with a generic message and counts toward lockout', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'username' => 'alice',
		'request'  => ['realm' => '1001', 'login_password' => 'wrong'],
		'ldap_ok'  => false,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toBe('Access Denied!  Login Failed.')
		->and($result['lockout_calls'])->toBe(1);
});

test('a non-credential LDAP bind failure does not lock the account', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'        => ['realm' => '1001', 'login_password' => 'x'],
		'ldap_ok'        => false,
		'auth_error_num' => 9,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['lockout_calls'])->toBe(0);
});

test('a bind that returns no array fails closed', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'    => ['realm' => '1001', 'login_password' => 'x'],
		'auth_false' => true,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['lockout_calls'])->toBe(0);
});

test('LDAP success with no account and no domain template is rejected', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'       => ['realm' => '1001', 'login_password' => 'x'],
		'ldap_ok'       => true,
		'user_row'      => [],
		'template_user' => 0,
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toContain('Domain template is not configured')
		->and($result['copy_calls'])->toBe(0);
});

test('LDAP success copies the domain template when the account is new', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'username'       => 'bob',
		'request'        => ['realm' => '1001', 'login_password' => 'x'],
		'user_row'       => [],
		'template_user'  => 4,
		'template_row'   => ['id' => 4, 'username' => 'template'],
		'after_copy_row' => ['id' => 22, 'username' => 'bob', 'realm' => 1001],
	]);

	expect($result['error'])->toBeFalse()
		->and($result['copy_calls'])->toBe(1)
		->and($result['user']['id'])->toBe(22);
});

test('a missing template user id is an error', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'       => ['realm' => '1001', 'login_password' => 'x'],
		'user_row'      => [],
		'template_user' => 4,
		'template_row'  => [],
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toContain('Template user id')
		->and($result['copy_calls'])->toBe(0);
});

test('CN attributes override the copied full name and email when present', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'username'       => 'carol',
		'request'        => ['realm' => '1001', 'login_password' => 'x'],
		'user_row'       => [],
		'template_user'  => 4,
		'template_row'   => ['id' => 4, 'username' => 'template'],
		'cn_full_name'   => 'displayName',
		'cn_email'       => 'mail',
		'cn_response'    => [
			'error_num' => '0',
			'cn'        => ['displayName' => 'Carol', 'mail' => 'carol@example.com'],
		],
		'after_copy_row' => ['id' => 23, 'username' => 'carol', 'realm' => 1001],
	]);

	expect($result['error'])->toBeFalse()
		->and($result['copy_calls'])->toBe(1)
		->and($result['user']['id'])->toBe(23);
});

test('a CN search without cn still copies the template', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request'        => ['realm' => '1001', 'login_password' => 'x'],
		'user_row'       => [],
		'template_user'  => 4,
		'template_row'   => ['id' => 4, 'username' => 'template'],
		'cn_full_name'   => 'displayName',
		'cn_response'    => ['error_num' => '13'],
		'after_copy_row' => ['id' => 24, 'username' => 'attacker', 'realm' => 1001],
	]);

	expect($result['error'])->toBeFalse()
		->and($result['copy_calls'])->toBe(1);
});

test('an empty password on a valid domain keeps its own error path', function () {
	$result = cacti_test_run_domains_login_process_1_2([
		'request' => ['realm' => '1001', 'login_password' => ''],
	]);

	expect($result['error'])->toBeTrue()
		->and($result['error_msg'])->toContain('No password provided')
		->and($result['lockout_calls'])->toBe(1)
		->and($result['ldap_calls'])->toBe(0);
});
